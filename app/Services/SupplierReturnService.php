<?php

namespace App\Services;

/**
 * Retur barang ke supplier (mutasi SUPPLIER_RETURN, PRD FR-07).
 * - Stok keluar dengan biaya moving average saat itu; tidak boleh membuat stok negatif.
 * - Bila merujuk penerimaan barang, qty dibatasi (diterima reguler + bonus) - yang sudah diretur dari penerimaan itu.
 * - Nilai kredit/refund dari supplier hanya dicatat sebagai informasi; pemetaan jurnal/hutang menunggu keputusan akuntansi.
 */
class SupplierReturnService
{
    public const REASONS = [
        'RUSAK'        => 'Barang rusak saat diterima',
        'BUSUK'        => 'Busuk / layu',
        'TIDAK_SESUAI' => 'Tidak sesuai pesanan / kualitas',
        'KELEBIHAN'    => 'Kelebihan kiriman',
        'LAINNYA'      => 'Lainnya',
    ];

    public function __construct(private ?StockLedgerService $ledger = null)
    {
        $this->ledger ??= new StockLedgerService();
    }

    /**
     * @param array{supplier_id:int, receipt_id?:?int, reason_code:string, reason?:?string, credit_amount?:float, settlement?:string,
     *              reference_no?:?string, return_date?:string, items:list<array{product_id:int, qty:float}>} $in
     */
    public function create(array $in, ?int $userId): array
    {
        $db       = db_connect();
        $supplier = (int) ($in['supplier_id'] ?? 0);
        $code     = (string) ($in['reason_code'] ?? '');
        $reason   = trim((string) ($in['reason'] ?? '')) ?: null;
        $receipt  = ! empty($in['receipt_id']) ? (int) $in['receipt_id'] : null;
        $credit   = round((float) ($in['credit_amount'] ?? 0), 2);
        $settle   = in_array($in['settlement'] ?? 'BELUM', ['BELUM', 'NOTA_KREDIT', 'REFUND'], true) ? ($in['settlement'] ?? 'BELUM') : 'BELUM';
        $date     = $in['return_date'] ?? date('Y-m-d');

        if (! $db->table('suppliers')->where('id', $supplier)->where('deleted_at', null)->countAllResults()) {
            return ['success' => false, 'message' => 'Supplier wajib dipilih.'];
        }
        if (! isset(self::REASONS[$code])) {
            return ['success' => false, 'message' => 'Pilih alasan retur.'];
        }
        if ($code === 'LAINNYA' && ! $reason) {
            return ['success' => false, 'message' => 'Jelaskan alasan retur.'];
        }
        if ($credit < 0 || strtotime($date) === false || strtotime($date) > strtotime('+1 day')) {
            return ['success' => false, 'message' => 'Nilai kredit atau tanggal tidak valid.'];
        }

        $lines = [];
        foreach ($in['items'] ?? [] as $i) {
            $pid = (int) ($i['product_id'] ?? 0);
            $qty = round((float) str_replace(',', '.', (string) ($i['qty'] ?? 0)), 3);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            if (isset($lines[$pid])) {
                return ['success' => false, 'message' => 'Ada produk yang tercantum dua kali.'];
            }
            $lines[$pid] = $qty;
        }
        if (! $lines) {
            return ['success' => false, 'message' => 'Isi minimal satu produk dengan qty > 0.'];
        }
        ksort($lines); // kunci produk berurutan (cegah deadlock)

        $loc = (new DailyClosingService())->locationId();
        $db->transBegin();
        try {
            if ((new StockOpnameService($this->ledger))->isCounting($loc)) {
                throw new StockException('Stock opname sedang dihitung. Retur dicatat setelah hasil hitung disubmit.');
            }
            if ($receipt !== null) {
                $rc = $db->query('SELECT * FROM goods_receipts WHERE id = ? FOR UPDATE', [$receipt])->getRowArray();
                if (! $rc || (int) $rc['supplier_id'] !== $supplier) {
                    throw new StockException('Penerimaan barang tidak ditemukan atau bukan milik supplier ini.');
                }
                $received = array_column($db->query('SELECT product_id, SUM(qty_regular + qty_bonus) q FROM goods_receipt_items WHERE receipt_id = ? GROUP BY product_id', [$receipt])->getResultArray(), 'q', 'product_id');
                $returned = array_column($db->query('SELECT i.product_id, SUM(i.qty) q FROM supplier_return_items i JOIN supplier_returns r ON r.id = i.return_id WHERE r.receipt_id = ? GROUP BY i.product_id', [$receipt])->getResultArray(), 'q', 'product_id');
                foreach ($lines as $pid => $qty) {
                    $left = (float) ($received[$pid] ?? 0) - (float) ($returned[$pid] ?? 0);
                    if ($qty > $left + 0.0005) {
                        $name = $db->table('products')->where('id', $pid)->get()->getRow('product_name');
                        throw new StockException("Qty retur {$name} melebihi sisa dari penerimaan {$rc['receipt_no']} (" . rtrim(rtrim(number_format(max(0, $left), 3, ',', '.'), '0'), ',') . ').');
                    }
                }
            }

            $now = date('Y-m-d H:i:s');
            $db->table('supplier_returns')->insert([
                'return_no' => 'RS-TMP-' . bin2hex(random_bytes(6)), 'supplier_id' => $supplier, 'location_id' => $loc, 'receipt_id' => $receipt,
                'return_date' => date('Y-m-d', strtotime($date)), 'reason_code' => $code, 'reason' => $reason ? mb_substr($reason, 0, 255) : null,
                'credit_amount' => $credit, 'settlement' => $settle, 'reference_no' => ! empty($in['reference_no']) ? mb_substr(trim((string) $in['reference_no']), 0, 60) : null,
                'status' => 'POSTED', 'created_by' => $userId, 'created_at' => $now,
            ]);
            $id = (int) $db->insertID();
            $no = sprintf('RS-%s-%05d', date('Ymd'), $id);

            $total = 0.0;
            foreach ($lines as $pid => $qty) {
                $db->table('supplier_return_items')->insert(['return_id' => $id, 'product_id' => $pid, 'qty' => $qty]);
                $itemId = (int) $db->insertID();
                $res    = $this->ledger->post([
                    'product_id' => $pid, 'location_id' => $loc, 'movement_type' => 'SUPPLIER_RETURN', 'qty' => $qty,
                    'reference_type' => 'SUPPLIER_RETURN', 'reference_id' => $id, 'reference_line' => $itemId,
                    'reason' => "Retur supplier {$no}: " . self::REASONS[$code], 'user_id' => $userId,
                ]);
                $db->table('supplier_return_items')->where('id', $itemId)->update(['unit_cost' => $res['unit_cost'], 'cost_value' => $res['total_cost'], 'ledger_id' => $res['id']]);
                $total += $res['total_cost'];
            }
            $db->table('supplier_returns')->where('id', $id)->update(['return_no' => $no, 'cost_value' => round($total, 2)]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                $msg = $e->available !== null
                    ? 'Stok tidak cukup untuk diretur (tersedia ' . rtrim(rtrim(number_format($e->available, 3, ',', '.'), '0'), ',') . ').'
                    : $e->getMessage();

                return ['success' => false, 'message' => $msg];
            }
            log_message('error', 'Retur supplier gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Retur gagal disimpan. Tidak ada stok yang berubah.'];
        }

        (new AuditLogService())->log('Retur ke Supplier', 'stock', 'SUPPLIER_RETURN', $id, "{$no}: " . count($lines) . ' produk, nilai Rp ' . number_format($total, 0, ',', '.'));

        return ['success' => true, 'message' => "Retur {$no} diposting; stok berkurang senilai Rp " . number_format($total, 0, ',', '.') . '.', 'id' => $id, 'return_no' => $no];
    }
}
