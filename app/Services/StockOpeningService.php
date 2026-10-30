<?php

namespace App\Services;

/**
 * Stok awal toko saat go-live (mutasi OPENING di stock_ledger).
 *
 * - Disusun sebagai draft (hasil hitung fisik go-live + biaya per unit), lalu diposting sekali oleh yang berwenang.
 * - Produk hanya boleh diberi stok awal bila BELUM punya mutasi apa pun di lokasi tsb; setelah itu koreksi lewat stock opname.
 * - Biaya default = purchase_price price period terbaru yang memuat produk (bukan angka Final Report — lihat docs/AUDIT-FINAL-REPORT.md).
 */
class StockOpeningService
{
    public function __construct(private ?StockLedgerService $ledger = null)
    {
        $this->ledger ??= new StockLedgerService();
    }

    /** @return array{cost:float, source:?string} */
    /**
     * Biaya awal tidak diambil dari Price Period (modul B2B). Stok awal diisi manual dari nota/hitungan;
     * produk yang sudah punya mutasi tidak bisa diberi stok awal, jadi tidak ada riwayat biaya POS untuk dijadikan default.
     */
    public function defaultCost(int $productId): array
    {
        return ['cost' => 0.0, 'source' => null];
    }

    /** Produk yang sudah punya mutasi di lokasi (tidak bisa diberi stok awal lagi). */
    public function productsWithMovements(int $locationId): array
    {
        return array_map('intval', array_column(db_connect()->query('SELECT DISTINCT product_id FROM stock_ledger WHERE location_id = ?', [$locationId])->getResultArray(), 'product_id'));
    }

    public function createDraft(string $effectiveAt, ?string $notes, ?int $userId): array
    {
        $ts = strtotime($effectiveAt);
        if (! $ts) {
            return ['success' => false, 'message' => 'Tanggal & jam cut-off tidak valid.'];
        }
        if ($ts > time()) {
            return ['success' => false, 'message' => 'Cut-off stok awal tidak boleh di masa depan.'];
        }
        $db  = db_connect();
        $loc = (new DailyClosingService())->locationId();
        if ($db->table('stock_openings')->where('location_id', $loc)->where('status', 'DRAFT')->countAllResults()) {
            return ['success' => false, 'message' => 'Masih ada draft stok awal. Lanjutkan atau batalkan draft tersebut.'];
        }
        $now = date('Y-m-d H:i:s');
        $db->table('stock_openings')->insert([
            'opening_no' => 'SA-TMP-' . bin2hex(random_bytes(6)), 'location_id' => $loc, 'effective_at' => date('Y-m-d H:i:s', $ts),
            'status' => 'DRAFT', 'notes' => $notes ? mb_substr(trim($notes), 0, 255) : null, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        $db->table('stock_openings')->where('id', $id)->update(['opening_no' => sprintf('SA-%s-%04d', date('Ymd', $ts), $id)]);
        (new AuditLogService())->log('Buat Draft Stok Awal', 'stock', 'STOCK_OPENING', $id, 'cut-off ' . date('Y-m-d H:i', $ts));

        return ['success' => true, 'message' => 'Draft stok awal dibuat.', 'id' => $id];
    }

    /**
     * Simpan seluruh baris draft (replace).
     * @param list<array{product_id:int, qty:float|string, unit_cost:float|string, note?:?string}> $lines
     */
    public function saveLines(int $openingId, array $lines, ?string $effectiveAt = null, ?string $notes = null): array
    {
        $db  = db_connect();
        $doc = $db->table('stock_openings')->where('id', $openingId)->get()->getRowArray();
        if (! $doc || $doc['status'] !== 'DRAFT') {
            return ['success' => false, 'message' => 'Hanya draft yang dapat diubah.'];
        }
        $clean = [];
        foreach ($lines as $l) {
            $pid  = (int) ($l['product_id'] ?? 0);
            $qty  = round((float) str_replace(',', '.', (string) ($l['qty'] ?? 0)), 3);
            $cost = round((float) str_replace(',', '.', (string) ($l['unit_cost'] ?? 0)), 4);
            if ($pid <= 0) {
                continue;
            }
            if ($qty < 0 || $cost < 0) {
                return ['success' => false, 'message' => 'Qty dan biaya tidak boleh negatif.'];
            }
            if (isset($clean[$pid])) {
                return ['success' => false, 'message' => 'Ada produk yang tercantum dua kali.'];
            }
            $clean[$pid] = [
                'opening_id' => $openingId, 'product_id' => $pid, 'qty' => $qty, 'unit_cost' => $cost,
                'cost_source' => isset($l['cost_source']) ? mb_substr((string) $l['cost_source'], 0, 60) : null,
                'note' => isset($l['note']) && trim((string) $l['note']) !== '' ? mb_substr(trim((string) $l['note']), 0, 255) : null,
            ];
        }
        $upd = ['updated_at' => date('Y-m-d H:i:s')];
        if ($effectiveAt !== null) {
            $ts = strtotime($effectiveAt);
            if (! $ts || $ts > time()) {
                return ['success' => false, 'message' => 'Tanggal & jam cut-off tidak valid / di masa depan.'];
            }
            $upd['effective_at'] = date('Y-m-d H:i:s', $ts);
        }
        if ($notes !== null) {
            $upd['notes'] = mb_substr(trim($notes), 0, 255) ?: null;
        }

        $db->transStart();
        $db->table('stock_opening_lines')->where('opening_id', $openingId)->delete();
        if ($clean) {
            $db->table('stock_opening_lines')->insertBatch(array_values($clean));
        }
        $db->table('stock_openings')->where('id', $openingId)->update($upd);
        $db->transComplete();
        if (! $db->transStatus()) {
            return ['success' => false, 'message' => 'Draft gagal disimpan.'];
        }

        return ['success' => true, 'message' => count($clean) . ' baris tersimpan.'];
    }

    public function post(int $openingId, ?int $userId): array
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $doc = $db->query('SELECT * FROM stock_openings WHERE id = ? FOR UPDATE', [$openingId])->getRowArray();
            if (! $doc || $doc['status'] !== 'DRAFT') {
                throw new StockException('Hanya draft yang dapat diposting.');
            }
            $loc = (int) $doc['location_id'];
            if ((new StockOpnameService($this->ledger))->isCounting($loc)) {
                throw new StockException('Stock opname sedang dihitung. Posting stok awal setelah hasil hitung disubmit.');
            }
            if (strtotime($doc['effective_at']) > time()) {
                throw new StockException('Cut-off stok awal masih di masa depan.');
            }
            $lines = $db->table('stock_opening_lines l')->select('l.*, p.product_name')->join('products p', 'p.id = l.product_id')
                ->where('l.opening_id', $openingId)->where('l.qty >', 0)->orderBy('l.product_id')->get()->getResultArray();
            if (! $lines) {
                throw new StockException('Belum ada baris dengan qty > 0.');
            }
            $moved    = $this->productsWithMovements($loc);
            $conflict = array_values(array_filter($lines, static fn ($l) => in_array((int) $l['product_id'], $moved, true)));
            if ($conflict) {
                throw new StockException('Produk berikut sudah punya mutasi stok sehingga tidak bisa diberi stok awal (koreksi lewat stock opname): '
                    . implode(', ', array_column($conflict, 'product_name')) . '.');
            }
            $zeroCost = array_values(array_filter($lines, static fn ($l) => (float) $l['unit_cost'] <= 0));
            if ($zeroCost) {
                throw new StockException('Biaya per unit masih 0 untuk: ' . implode(', ', array_column($zeroCost, 'product_name')) . '. Isi biaya agar nilai persediaan & HPP benar.');
            }

            $value = 0.0;
            foreach ($lines as $l) {
                $total = round((float) $l['qty'] * (float) $l['unit_cost'], 2);
                $res   = $this->ledger->post([
                    'product_id' => (int) $l['product_id'], 'location_id' => $loc, 'movement_type' => 'OPENING',
                    'qty' => (float) $l['qty'], 'total_cost' => $total,
                    'reference_type' => 'STOCK_OPENING', 'reference_id' => $openingId, 'reference_line' => (int) $l['id'],
                    'reason' => 'Stok awal ' . $doc['opening_no'] . ($l['note'] ? ': ' . $l['note'] : ''),
                    'user_id' => $userId, 'effective_at' => $doc['effective_at'],
                ]);
                $db->table('stock_opening_lines')->where('id', $l['id'])->update(['ledger_id' => $res['id']]);
                $value += $total;
            }
            // Produk yang diberi stok awal toko otomatis ditandai dijual di POS (harga retail tetap diatur di Katalog).
            $db->table('products')->whereIn('id', array_map('intval', array_column($lines, 'product_id')))->update(['is_pos' => 1]);
            $db->table('stock_opening_lines')->where('opening_id', $openingId)->where('qty', 0)->delete();

            $now = date('Y-m-d H:i:s');
            $db->table('stock_openings')->where('id', $openingId)->update(['status' => 'POSTED', 'posted_by' => $userId, 'posted_at' => $now, 'updated_at' => $now]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Posting stok awal gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Posting stok awal gagal.'];
        }

        (new AuditLogService())->log('Posting Stok Awal', 'stock', 'STOCK_OPENING', $openingId, $doc['opening_no'] . ': ' . count($lines) . ' produk, Rp ' . number_format($value, 0, ',', '.'));

        return ['success' => true, 'message' => count($lines) . ' produk diposting sebagai stok awal (nilai Rp ' . number_format($value, 0, ',', '.') . ').'];
    }

    public function cancel(int $openingId): array
    {
        $db = db_connect();
        $db->table('stock_openings')->where('id', $openingId)->where('status', 'DRAFT')->update(['status' => 'CANCELLED', 'updated_at' => date('Y-m-d H:i:s')]);
        if (! $db->affectedRows()) {
            return ['success' => false, 'message' => 'Hanya draft yang dapat dibatalkan.'];
        }
        (new AuditLogService())->log('Batal Draft Stok Awal', 'stock', 'STOCK_OPENING', $openingId, null);

        return ['success' => true, 'message' => 'Draft dibatalkan.'];
    }
}
