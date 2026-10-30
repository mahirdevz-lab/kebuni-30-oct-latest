<?php

namespace App\Services;

use App\Models\SettingModel;

/**
 * Stock opname setelah toko tutup (PRD FR-10/11) dengan prosedur owner (2026-10-23):
 *
 *   1. Blind count (putaran 1)  : semua produk dihitung; saldo buku tidak ditampilkan ke siapa pun.
 *   2. Rekonsiliasi             : setelah semua terisi (submit), buku vs fisik dibandingkan; selisih besar ditandai.
 *   3. Hitung ulang             : baris selisih besar WAJIB dihitung ulang fisik (putaran 2, 3, ...) secara blind.
 *   4. Konfirmasi               : bila setelah hitung ulang masih besar, supervisor wajib mencatat penyebab.
 *   5. Posting                  : fisik menjadi saldo buku lewat OPNAME_ADJUST (efektif pada cut-off);
 *                                 selisih kurang otomatis = unknown shrinkage.
 *
 * - Sesi hanya bisa dibuka setelah EOD; waktu buka = cut-off; saldo buku & avg cost di-snapshot.
 * - Selama COUNTING, checkout, penerimaan, & persetujuan Broken Stock ditolak -> tidak ada mutasi setelah cut-off.
 * - Hitung ulang / tambah produk ditolak bila produk itu sudah punya mutasi setelah cut-off (fisik sudah berubah).
 * - Selisih = stok buku saat cut-off - stok fisik. Positif = fisik kurang, negatif = fisik lebih.
 */
class StockOpnameService
{
    public const CONFIRM_REASONS = [
        'HILANG'           => 'Hilang / pencurian',
        'SUSUT_ALAMI'      => 'Susut alami (berat, kelembapan)',
        'RUSAK_TAK_LAPOR'  => 'Barang rusak tidak dilaporkan',
        'SALAH_TRANSAKSI'  => 'Salah catat transaksi sebelumnya',
        'PENERIMAAN_BELUM' => 'Penerimaan belum dicatat (fisik lebih)',
        'LAINNYA'          => 'Lainnya',
    ];

    public function __construct(private ?StockLedgerService $ledger = null)
    {
        $this->ledger ??= new StockLedgerService();
    }

    public function activeSession(int $locationId): ?array
    {
        return db_connect()->table('stock_opname_sessions')->where('location_id', $locationId)
            ->whereIn('status', ['COUNTING', 'SUBMITTED'])->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    public function isCounting(int $locationId): bool
    {
        return (bool) db_connect()->table('stock_opname_sessions')->where('location_id', $locationId)->where('status', 'COUNTING')->countAllResults();
    }

    /** Ambang "selisih besar" dari setting. */
    public function thresholds(): array
    {
        $s = new SettingModel();

        return ['pct' => (float) $s->value('opname_variance_pct', '5'), 'value' => (float) $s->value('opname_variance_value', '50000')];
    }

    public function isLargeVariance(array $line, ?array $t = null): bool
    {
        if ($line['counted_qty'] === null) {
            return false;
        }
        $t    = $t ?? $this->thresholds();
        $book = (float) $line['book_qty'];
        $diff = $book - (float) $line['counted_qty'];
        if (abs($diff) < 0.0005) {
            return false;
        }
        if ($book <= 0) {
            return true; // buku nol/negatif tapi fisik ada (atau sebaliknya) selalu diperiksa
        }

        return abs($diff) / $book * 100 >= $t['pct'] || abs($diff * (float) $line['avg_cost']) >= $t['value'];
    }

    /**
     * Status rekonsiliasi per baris: OK | NEED_RECOUNT | NEED_CONFIRM | CONFIRMED.
     * Selisih besar wajib dihitung ulang minimal sekali; bila masih besar, wajib dikonfirmasi.
     */
    public static function lineState(array $l): string
    {
        if (! (int) $l['variance_flag']) {
            return 'OK';
        }
        if ((int) $l['count_round'] < 2) {
            return 'NEED_RECOUNT';
        }

        return $l['resolution'] === 'CONFIRMED' ? 'CONFIRMED' : 'NEED_CONFIRM';
    }

    public function open(?int $userId, ?string $notes = null): array
    {
        $days = new DailyClosingService();
        $loc  = $days->locationId();
        $db   = db_connect();

        if ($days->openDay()) {
            return ['success' => false, 'message' => 'Toko masih buka. Selesaikan EOD (tutup kas harian) terlebih dahulu.'];
        }
        if ($this->activeSession($loc)) {
            return ['success' => false, 'message' => 'Masih ada sesi opname yang belum selesai.'];
        }
        // Barang rusak yang sudah dibuang tetapi belum disetujui akan terbaca sebagai unknown shrinkage.
        if ($drafts = (new BrokenStockService($this->ledger))->draftCount($loc)) {
            return ['success' => false, 'message' => "Masih ada {$drafts} laporan Broken Stock berstatus draft. Setujui atau tolak dulu supaya tidak terhitung sebagai unknown shrinkage."];
        }
        $last = $days->lastDay();

        $db->transBegin();
        try {
            $now = date('Y-m-d H:i:s');
            $db->table('stock_opname_sessions')->insert([
                'session_no' => 'SO-TMP-' . bin2hex(random_bytes(6)), 'location_id' => $loc,
                'daily_closing_id' => $last['id'] ?? null, 'cutoff_at' => $now, 'status' => 'COUNTING', 'count_round' => 1,
                'opened_by' => $userId, 'notes' => $notes, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $sid = (int) $db->insertID();
            $no  = sprintf('SO-%s-%04d', date('Ymd'), $sid);
            $db->table('stock_opname_sessions')->where('id', $sid)->update(['session_no' => $no]);

            // Snapshot saldo buku saat cut-off: semua produk POS aktif + produk yang punya saldo.
            $db->query(
                "INSERT INTO stock_opname_lines (session_id, product_id, book_qty, avg_cost)
                 SELECT ?, p.id, COALESCE(sb.qty, 0), COALESCE(sb.avg_cost, 0)
                 FROM products p
                 LEFT JOIN stock_balances sb ON sb.product_id = p.id AND sb.location_id = ?
                 WHERE p.deleted_at IS NULL AND ((p.is_pos = 1 AND p.status = 'aktif') OR (sb.product_id IS NOT NULL AND sb.qty <> 0))",
                [$sid, $loc]
            );
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Buka opname gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Sesi opname gagal dibuat.'];
        }

        (new AuditLogService())->log('Buka Stock Opname', 'stock', 'STOCK_OPNAME', $sid, "{$no} cut-off {$now}");

        return ['success' => true, 'message' => "Sesi {$no} dibuka. Cut-off {$now}.", 'id' => $sid];
    }

    /** Baris boleh diisi pada putaran ini? Putaran 1: semua; putaran berikutnya: hanya yang diminta hitung ulang. */
    public static function isCountable(array $line, array $session): bool
    {
        return $session['status'] === 'COUNTING' && ((int) $session['count_round'] === 1 || (int) $line['recount_requested'] === 1);
    }

    /** Simpan hasil hitung satu produk (dipanggil per baris dari HP). */
    public function count(int $sessionId, int $productId, ?float $qty, ?string $note, ?int $userId): array
    {
        $db   = db_connect();
        $sess = $db->table('stock_opname_sessions')->where('id', $sessionId)->get()->getRowArray();
        if (! $sess || $sess['status'] !== 'COUNTING') {
            return ['success' => false, 'message' => 'Sesi tidak dalam tahap hitung.'];
        }
        $line = $db->table('stock_opname_lines')->where('session_id', $sessionId)->where('product_id', $productId)->get()->getRowArray();
        if (! $line) {
            return ['success' => false, 'message' => 'Produk tidak ada di sesi ini.'];
        }
        if (! self::isCountable($line, $sess)) {
            return ['success' => false, 'message' => 'Produk ini tidak termasuk hitung ulang putaran ini.'];
        }
        if ($qty !== null && $qty < 0) {
            return ['success' => false, 'message' => 'Hasil hitung tidak boleh negatif.'];
        }

        $round = (int) $sess['count_round'];
        $note  = $note !== null ? (mb_substr(trim($note), 0, 255) ?: null) : $line['note'];
        $now   = date('Y-m-d H:i:s');
        $data  = ['note' => $note];

        if ($qty === null) {
            $data += ['counted_qty' => null, 'difference' => null, 'diff_value' => null, 'counted_by' => null, 'counted_at' => null];
            $db->table('stock_opname_counts')->where('line_id', $line['id'])->where('round', $round)->delete();
        } else {
            $qty  = round($qty, 3);
            $diff = round((float) $line['book_qty'] - $qty, 3);
            // Riwayat per putaran (upsert): koreksi ketik pada putaran yang sama menimpa, putaran baru menambah baris.
            $db->query(
                'INSERT INTO stock_opname_counts (session_id, line_id, round, counted_qty, note, counted_by, counted_at) VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE counted_qty = VALUES(counted_qty), note = VALUES(note), counted_by = VALUES(counted_by), counted_at = VALUES(counted_at)',
                [$sessionId, $line['id'], $round, $qty, $note, $userId, $now]
            );
            $data += [
                'counted_qty' => $qty, 'difference' => $diff, 'diff_value' => round($diff * (float) $line['avg_cost'], 2),
                'counted_by' => $userId, 'counted_at' => $now,
            ];
        }
        // count_round = berapa kali produk ini benar-benar dihitung (bukan nomor putaran sesi), sehingga produk yang baru
        // ditemukan di putaran 2 tetap dianggap "baru dihitung sekali".
        $data['count_round'] = (int) $db->table('stock_opname_counts')->where('line_id', $line['id'])->countAllResults();
        $db->table('stock_opname_lines')->where('id', $line['id'])->update($data);

        // Sengaja tidak mengembalikan selisih: hitung bersifat blind.
        return ['success' => true, 'message' => 'Tersimpan.'];
    }

    /** Tambah produk yang ditemukan fisik tetapi tidak ada di daftar sesi (kelengkapan assortment). */
    public function addLine(int $sessionId, int $productId, ?int $userId): array
    {
        $db   = db_connect();
        $sess = $db->table('stock_opname_sessions')->where('id', $sessionId)->get()->getRowArray();
        if (! $sess || $sess['status'] !== 'COUNTING') {
            return ['success' => false, 'message' => 'Sesi tidak dalam tahap hitung.'];
        }
        $p = $db->table('products')->where('id', $productId)->where('deleted_at', null)->get()->getRowArray();
        if (! $p) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan.'];
        }
        if ($db->table('stock_opname_lines')->where('session_id', $sessionId)->where('product_id', $productId)->countAllResults()) {
            return ['success' => false, 'message' => 'Produk sudah ada di daftar hitung.'];
        }
        if ($this->mutatedAfterCutoff($sess, [$productId])) {
            return ['success' => false, 'message' => 'Produk ini sudah punya mutasi setelah cut-off; hitung di sesi opname berikutnya.'];
        }

        $bal = $this->ledger->balance($productId, (int) $sess['location_id']);
        $db->table('stock_opname_lines')->insert([
            'session_id' => $sessionId, 'product_id' => $productId, 'book_qty' => (float) $bal['qty'], 'avg_cost' => (float) $bal['avg_cost'],
            'added_during_count' => 1, 'recount_requested' => (int) $sess['count_round'] > 1 ? 1 : 0,
        ]);
        (new AuditLogService())->log('Tambah Produk Opname', 'stock', 'STOCK_OPNAME', $sessionId, $p['product_name'] . ' (ditemukan saat hitung)');

        return ['success' => true, 'message' => $p['product_name'] . ' ditambahkan ke daftar hitung.'];
    }

    public function submit(int $sessionId, ?int $userId): array
    {
        $db   = db_connect();
        $sess = $db->table('stock_opname_sessions')->where('id', $sessionId)->get()->getRowArray();
        if (! $sess || $sess['status'] !== 'COUNTING') {
            return ['success' => false, 'message' => 'Sesi tidak dalam tahap hitung.'];
        }
        $missing = $db->table('stock_opname_lines')->where('session_id', $sessionId)->where('counted_qty', null)->countAllResults();
        if ($missing) {
            return ['success' => false, 'message' => "Masih ada {$missing} produk yang belum dihitung. Isi 0 jika barang memang tidak ada."];
        }

        // Rekonsiliasi: tandai selisih besar berdasarkan hasil hitung terakhir.
        $t      = $this->thresholds();
        $lines  = $db->table('stock_opname_lines')->where('session_id', $sessionId)->get()->getResultArray();
        $flags  = 0;
        $recnt  = 0;
        foreach ($lines as $l) {
            $large = $this->isLargeVariance($l, $t);
            $flags += $large ? 1 : 0;
            $recnt += ($large && (int) $l['count_round'] < 2) ? 1 : 0;
            $upd = ['variance_flag' => $large ? 1 : 0, 'recount_requested' => 0];
            if ((int) $l['recount_requested'] === 1 || ! $large) {
                // Hasil hitung baru -> konfirmasi lama tidak berlaku lagi.
                $upd += ['resolution' => null, 'resolution_reason' => null, 'resolution_note' => null, 'resolved_by' => null, 'resolved_at' => null];
            }
            $db->table('stock_opname_lines')->where('id', $l['id'])->update($upd);
        }

        $db->table('stock_opname_sessions')->where('id', $sessionId)->update([
            'status' => 'SUBMITTED', 'submitted_by' => $userId, 'submitted_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditLogService())->log('Submit Stock Opname', 'stock', 'STOCK_OPNAME', $sessionId, $sess['session_no'] . " putaran {$sess['count_round']}, {$flags} selisih besar");

        $msg = 'Hitungan lengkap dan tidak ada selisih besar. Siap direkonsiliasi & diposting.';
        if ($flags) {
            $msg = "Hitungan lengkap. {$flags} produk selisih besar: "
                . ($recnt ? "{$recnt} perlu dicek ulang fisik" : '')
                . ($recnt && $flags - $recnt ? ', ' : '')
                . ($flags - $recnt ? ($flags - $recnt) . ' sudah dihitung ulang dan perlu konfirmasi penyebab' : '') . '.';
        }

        return ['success' => true, 'message' => $msg, 'flags' => $flags];
    }

    /** Minta hitung ulang fisik untuk baris tertentu (putaran baru, tetap blind). */
    public function requestRecount(int $sessionId, array $lineIds, ?int $userId): array
    {
        $db      = db_connect();
        $lineIds = array_values(array_unique(array_map('intval', $lineIds)));
        $sess    = $db->table('stock_opname_sessions')->where('id', $sessionId)->get()->getRowArray();
        if (! $sess || $sess['status'] !== 'SUBMITTED') {
            return ['success' => false, 'message' => 'Hitung ulang hanya untuk sesi yang sudah disubmit.'];
        }
        if (! $lineIds) {
            return ['success' => false, 'message' => 'Pilih produk yang akan dihitung ulang.'];
        }
        if ((new DailyClosingService())->openDay()) {
            return ['success' => false, 'message' => 'Toko sedang buka; hitung ulang hanya setelah EOD agar cut-off tetap jelas.'];
        }
        $lines = $db->table('stock_opname_lines')->where('session_id', $sessionId)->whereIn('id', $lineIds)->get()->getResultArray();
        if (count($lines) !== count($lineIds)) {
            return ['success' => false, 'message' => 'Sebagian produk tidak ditemukan di sesi ini.'];
        }
        if ($mut = $this->mutatedAfterCutoff($sess, array_column($lines, 'product_id'))) {
            return ['success' => false, 'message' => "{$mut} produk sudah punya mutasi setelah cut-off (toko sempat berjualan), fisiknya sudah berubah. Konfirmasi selisihnya atau batalkan sesi dan buat opname baru."];
        }

        $round = (int) $sess['count_round'] + 1;
        $db->table('stock_opname_lines')->where('session_id', $sessionId)->whereIn('id', $lineIds)->update([
            'recount_requested' => 1, 'counted_qty' => null, 'difference' => null, 'diff_value' => null, 'counted_by' => null, 'counted_at' => null,
        ]);
        $db->table('stock_opname_sessions')->where('id', $sessionId)->update(['status' => 'COUNTING', 'count_round' => $round, 'updated_at' => date('Y-m-d H:i:s')]);
        (new AuditLogService())->log('Minta Hitung Ulang', 'stock', 'STOCK_OPNAME', $sessionId, $sess['session_no'] . " putaran {$round}: " . count($lineIds) . ' produk');

        return ['success' => true, 'message' => count($lineIds) . " produk dibuka untuk hitung ulang (putaran {$round}). Checkout terkunci sampai disubmit."];
    }

    /** Supervisor yakin selisih benar setelah hitung ulang -> catat penyebab. */
    public function confirmLine(int $sessionId, int $lineId, string $reason, string $note, ?int $userId): array
    {
        $db   = db_connect();
        $sess = $db->table('stock_opname_sessions')->where('id', $sessionId)->get()->getRowArray();
        if (! $sess || $sess['status'] !== 'SUBMITTED') {
            return ['success' => false, 'message' => 'Konfirmasi hanya untuk sesi yang sudah disubmit.'];
        }
        $line = $db->table('stock_opname_lines')->where('session_id', $sessionId)->where('id', $lineId)->get()->getRowArray();
        if (! $line || self::lineState($line) === 'OK') {
            return ['success' => false, 'message' => 'Baris ini tidak memerlukan konfirmasi.'];
        }
        if (self::lineState($line) === 'NEED_RECOUNT') {
            return ['success' => false, 'message' => 'Selisih besar wajib dihitung ulang fisik minimal sekali sebelum dikonfirmasi.'];
        }
        if (! isset(self::CONFIRM_REASONS[$reason]) || trim($note) === '') {
            return ['success' => false, 'message' => 'Pilih penyebab dan isi catatan hasil pemeriksaan.'];
        }
        $db->table('stock_opname_lines')->where('id', $lineId)->update([
            'resolution' => 'CONFIRMED', 'resolution_reason' => $reason, 'resolution_note' => mb_substr(trim($note), 0, 255),
            'resolved_by' => $userId, 'resolved_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditLogService())->log('Konfirmasi Selisih Opname', 'stock', 'STOCK_OPNAME', $sessionId, "line {$lineId}: " . self::CONFIRM_REASONS[$reason] . ' - ' . trim($note));

        return ['success' => true, 'message' => 'Selisih dikonfirmasi.'];
    }

    /** Setujui & posting adjustment ke ledger (efektif pada waktu cut-off). */
    public function approve(int $sessionId, ?int $userId): array
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $sess = $db->query('SELECT * FROM stock_opname_sessions WHERE id = ? FOR UPDATE', [$sessionId])->getRowArray();
            if (! $sess || $sess['status'] !== 'SUBMITTED') {
                throw new StockException('Hanya sesi berstatus SUBMITTED yang dapat disetujui.');
            }
            $all = $db->table('stock_opname_lines')->where('session_id', $sessionId)->get()->getResultArray();
            $pending = ['NEED_RECOUNT' => 0, 'NEED_CONFIRM' => 0];
            foreach ($all as $l) {
                $st = self::lineState($l);
                if (isset($pending[$st])) {
                    $pending[$st]++;
                }
            }
            if ($pending['NEED_RECOUNT'] || $pending['NEED_CONFIRM']) {
                throw new StockException("Belum bisa posting: {$pending['NEED_RECOUNT']} produk selisih besar belum dihitung ulang, {$pending['NEED_CONFIRM']} belum dikonfirmasi.");
            }

            $lines = array_values(array_filter($all, static fn ($l) => (float) $l['difference'] != 0));
            usort($lines, static fn ($a, $b) => $a['product_id'] <=> $b['product_id']);

            foreach ($lines as $l) {
                $diff  = (float) $l['difference'];
                $qty   = abs($diff);
                $entry = [
                    'product_id' => (int) $l['product_id'], 'location_id' => (int) $sess['location_id'],
                    'movement_type' => 'OPNAME_ADJUST', 'direction' => $diff > 0 ? 'OUT' : 'IN', 'qty' => $qty,
                    'unit_cost' => (float) $l['avg_cost'],
                    'reference_type' => 'STOCK_OPNAME', 'reference_id' => $sessionId, 'reference_line' => (int) $l['id'],
                    'reason' => ($diff > 0 ? 'Unknown shrinkage ' : 'Selisih lebih ') . $sess['session_no']
                        . ($l['resolution_reason'] ? ' [' . self::CONFIRM_REASONS[$l['resolution_reason']] . ']' : '')
                        . ($l['note'] ? ': ' . $l['note'] : ''),
                    'user_id' => $userId, 'effective_at' => $sess['cutoff_at'], 'allow_negative' => true,
                ];
                if ($diff < 0) {
                    $entry['total_cost'] = round($qty * (float) $l['avg_cost'], 2);
                }
                $res = $this->ledger->post($entry);
                $db->table('stock_opname_lines')->where('id', $l['id'])->update(['ledger_id' => $res['id']]);
            }

            $now = date('Y-m-d H:i:s');
            $db->table('stock_opname_sessions')->where('id', $sessionId)->update([
                'status' => 'POSTED', 'approved_by' => $userId, 'approved_at' => $now, 'updated_at' => $now,
            ]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Posting opname gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Posting opname gagal.'];
        }

        (new AuditLogService())->log('Posting Stock Opname', 'stock', 'STOCK_OPNAME', $sessionId, $sess['session_no'] . ': ' . count($lines) . ' adjustment');

        return ['success' => true, 'message' => count($lines) . ' adjustment diposting; fisik kini menjadi saldo buku.'];
    }

    public function cancel(int $sessionId, string $reason): array
    {
        if (trim($reason) === '') {
            return ['success' => false, 'message' => 'Alasan pembatalan wajib diisi.'];
        }
        $db   = db_connect();
        $sess = $db->table('stock_opname_sessions')->where('id', $sessionId)->get()->getRowArray();
        if (! $sess || ! in_array($sess['status'], ['COUNTING', 'SUBMITTED'], true)) {
            return ['success' => false, 'message' => 'Sesi tidak dapat dibatalkan.'];
        }
        $db->table('stock_opname_sessions')->where('id', $sessionId)->update([
            'status' => 'CANCELLED', 'notes' => mb_substr(trim(($sess['notes'] ? $sess['notes'] . ' | ' : '') . 'Batal: ' . $reason), 0, 255), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditLogService())->log('Batalkan Stock Opname', 'stock', 'STOCK_OPNAME', $sessionId, $reason);

        return ['success' => true, 'message' => 'Sesi opname dibatalkan; tidak ada adjustment yang diposting.'];
    }

    /** Jumlah produk (dari daftar) yang punya mutasi stok setelah cut-off sesi. */
    private function mutatedAfterCutoff(array $sess, array $productIds): int
    {
        if (! $productIds) {
            return 0;
        }

        return (int) db_connect()->table('stock_ledger')->select('COUNT(DISTINCT product_id) AS c', false)
            ->where('location_id', $sess['location_id'])->whereIn('product_id', $productIds)
            ->where('posted_at >', $sess['cutoff_at'])->get()->getRow('c');
    }
}
