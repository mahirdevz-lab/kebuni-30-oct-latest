<?php

namespace App\Services;

/**
 * Broken Stock (PRD FR-09): barang rusak/busuk dicatat SEBELUM dibuang sebagai known shrinkage.
 *
 * - Draft tidak mengurangi stok resmi (tidak ada fitur karantina).
 * - Setelah disetujui: mutasi BROKEN (keluar, biaya moving average) diposting ke stock_ledger.
 * - Persetujuan ditolak selama opname COUNTING (cut-off harus bersih); sesi opname juga tidak bisa dibuka
 *   selama masih ada draft, supaya barang yang sudah dibuang tidak terbaca sebagai unknown shrinkage.
 */
class BrokenStockService
{
    public const REASONS = [
        'BUSUK'      => 'Busuk / layu',
        'RUSAK'      => 'Rusak fisik / memar',
        'KADALUARSA' => 'Melewati batas kualitas / kadaluarsa',
        'HAMA'       => 'Hama / jamur',
        'LAINNYA'    => 'Lainnya',
    ];

    public function __construct(private ?StockLedgerService $ledger = null)
    {
        $this->ledger ??= new StockLedgerService();
    }

    public function draftCount(int $locationId): int
    {
        return db_connect()->table('stock_broken_reports')->where('location_id', $locationId)->where('status', 'DRAFT')->countAllResults();
    }

    /**
     * @param array{product_id:int, qty:float, reason_code:string, reason?:?string} $data
     */
    public function create(array $data, ?string $photoPath, ?int $userId): array
    {
        $db        = db_connect();
        $productId = (int) ($data['product_id'] ?? 0);
        $qty       = round((float) ($data['qty'] ?? 0), 3);
        $code      = strtoupper((string) ($data['reason_code'] ?? ''));
        $reason    = trim((string) ($data['reason'] ?? '')) ?: null;

        $product = $db->table('products')->where('id', $productId)->where('deleted_at', null)->get()->getRowArray();
        if (! $product) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan.'];
        }
        if ($qty <= 0) {
            return ['success' => false, 'message' => 'Kuantitas harus lebih besar dari 0.'];
        }
        if (! isset(self::REASONS[$code])) {
            return ['success' => false, 'message' => 'Pilih alasan kerusakan.'];
        }
        if ($code === 'LAINNYA' && ! $reason) {
            return ['success' => false, 'message' => 'Jelaskan alasan untuk kategori "Lainnya".'];
        }

        $loc  = (new DailyClosingService())->locationId();
        $bal  = $this->ledger->balance($productId, $loc);
        $avg  = (float) $bal['avg_cost'];
        $now  = date('Y-m-d H:i:s');

        $db->table('stock_broken_reports')->insert([
            'report_no' => 'BS-TMP-' . bin2hex(random_bytes(6)), 'location_id' => $loc, 'product_id' => $productId,
            'qty' => $qty, 'unit' => $product['unit'], 'reason_code' => $code, 'reason' => $reason ? mb_substr($reason, 0, 255) : null,
            'photo_path' => $photoPath, 'est_unit_cost' => $avg, 'est_value' => round($qty * $avg, 2),
            'status' => 'DRAFT', 'reported_by' => $userId, 'reported_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        $no = sprintf('BS-%s-%05d', date('Ymd'), $id);
        $db->table('stock_broken_reports')->where('id', $id)->update(['report_no' => $no]);

        (new AuditLogService())->log('Lapor Broken Stock', 'stock', 'BROKEN_STOCK', $id, "{$no} {$product['product_name']} {$qty} {$product['unit']} ({$code})");

        $warn = $qty > (float) $bal['qty'] ? ' Perhatian: melebihi stok buku (' . rtrim(rtrim(number_format((float) $bal['qty'], 3, ',', '.'), '0'), ',') . ' ' . $product['unit'] . ').' : '';

        return ['success' => true, 'message' => "Laporan {$no} tersimpan sebagai draft. Stok belum berkurang sampai disetujui." . $warn, 'id' => $id];
    }

    public function approve(int $id, ?int $userId): array
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $r = $db->query('SELECT * FROM stock_broken_reports WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if (! $r || $r['status'] !== 'DRAFT') {
                throw new StockException('Hanya laporan berstatus draft yang dapat disetujui.');
            }
            if ((new StockOpnameService($this->ledger))->isCounting((int) $r['location_id'])) {
                throw new StockException('Stock opname sedang dihitung. Setujui laporan setelah hasil hitung disubmit.');
            }

            $res = $this->ledger->post([
                'product_id' => (int) $r['product_id'], 'location_id' => (int) $r['location_id'],
                'movement_type' => 'BROKEN', 'qty' => (float) $r['qty'],
                'reference_type' => 'BROKEN_STOCK', 'reference_id' => $id,
                'reason' => self::REASONS[$r['reason_code']] . ($r['reason'] ? ': ' . $r['reason'] : '') . ' (' . $r['report_no'] . ')',
                'user_id' => $userId,
            ]);

            $now = date('Y-m-d H:i:s');
            $db->table('stock_broken_reports')->where('id', $id)->update([
                'status' => 'POSTED', 'approved_by' => $userId, 'approved_at' => $now,
                'posted_value' => $res['total_cost'], 'ledger_id' => $res['id'], 'updated_at' => $now,
            ]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                $msg = $e->available !== null
                    ? 'Stok buku tidak cukup (' . rtrim(rtrim(number_format($e->available, 3, ',', '.'), '0'), ',') . '). Periksa penerimaan yang belum dicatat atau hitung ulang lewat opname.'
                    : $e->getMessage();

                return ['success' => false, 'message' => $msg];
            }
            log_message('error', 'Approve broken stock gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Persetujuan gagal disimpan.'];
        }

        (new AuditLogService())->log('Setujui Broken Stock', 'stock', 'BROKEN_STOCK', $id, $r['report_no'] . ' Rp ' . number_format($res['total_cost'], 0, ',', '.'));

        return ['success' => true, 'message' => $r['report_no'] . ' disetujui; stok berkurang dan tercatat sebagai known shrinkage.'];
    }

    public function reject(int $id, string $reason, ?int $userId): array
    {
        if (trim($reason) === '') {
            return ['success' => false, 'message' => 'Alasan penolakan wajib diisi.'];
        }

        return $this->close($id, 'REJECTED', $reason, $userId, null);
    }

    /** Pelapor membatalkan draftnya sendiri (mis. salah input). */
    public function cancel(int $id, ?int $userId): array
    {
        return $this->close($id, 'CANCELLED', null, $userId, $userId);
    }

    private function close(int $id, string $status, ?string $reason, ?int $userId, ?int $mustBeReporter): array
    {
        $db = db_connect();
        $r  = $db->table('stock_broken_reports')->where('id', $id)->get()->getRowArray();
        if (! $r || $r['status'] !== 'DRAFT') {
            return ['success' => false, 'message' => 'Hanya laporan draft yang dapat diubah.'];
        }
        if ($mustBeReporter !== null && (int) $r['reported_by'] !== $mustBeReporter) {
            return ['success' => false, 'message' => 'Hanya pelapor yang dapat membatalkan draft ini.'];
        }
        $db->table('stock_broken_reports')->where('id', $id)->where('status', 'DRAFT')->update([
            'status' => $status, 'reject_reason' => $reason ? mb_substr(trim($reason), 0, 255) : null,
            'approved_by' => $status === 'REJECTED' ? $userId : null, 'approved_at' => $status === 'REJECTED' ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        (new AuditLogService())->log($status === 'REJECTED' ? 'Tolak Broken Stock' : 'Batal Broken Stock', 'stock', 'BROKEN_STOCK', $id, $r['report_no'] . ($reason ? ': ' . $reason : ''));

        return ['success' => true, 'message' => $status === 'REJECTED' ? 'Laporan ditolak; stok tidak berubah.' : 'Draft dibatalkan.'];
    }
}
