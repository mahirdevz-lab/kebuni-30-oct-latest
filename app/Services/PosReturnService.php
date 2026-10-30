<?php

namespace App\Services;

use App\Models\FinancialTransactionModel;
use App\Models\SettingModel;

/**
 * Retur pelanggan & koreksi metode pembayaran setelah transaksi selesai (termasuk setelah EOD).
 *
 * Penjualan asli TIDAK diubah; retur adalah dokumen baru dengan jejak sendiri:
 * - Stok: CUSTOMER_RETURN masuk dengan biaya pokok ASLI saat jual (HPP terbalik persis).
 *   Barang rusak: setelah masuk, otomatis dicatat Broken Stock berstatus disetujui (BROKEN keluar) -> known shrinkage.
 * - Uang: refund tunai dari Kas Kasir (wajib hari kas OPEN; masuk EOD hari itu) atau transfer dari rekening lain.
 *   Refund dibatasi uang yang sudah diterima; sisanya mengurangi sisa tagihan.
 * - Koreksi metode bayar: pemindahan dana antar akun pada tanggal bisnis penjualan asli; baris pembayaran hanya ditandai.
 */
class PosReturnService
{
    public const REASONS = [
        'SALAH_PRODUK' => 'Salah produk / salah input kasir',
        'RUSAK'        => 'Barang rusak / busuk',
        'TIDAK_SESUAI' => 'Tidak sesuai (kualitas/berat)',
        'BATAL_BELI'   => 'Pelanggan batal beli',
        'LAINNYA'      => 'Lainnya',
    ];

    public function __construct(private ?StockLedgerService $ledger = null)
    {
        $this->ledger ??= new StockLedgerService();
    }

    /** Qty yang sudah diretur per sale_item_id. */
    public function returnedQty(int $saleId): array
    {
        $rows = db_connect()->query(
            'SELECT ri.sale_item_id, SUM(ri.qty) AS q FROM pos_return_items ri JOIN pos_returns r ON r.id = ri.return_id
             WHERE r.sale_id = ? AND r.status = ? GROUP BY ri.sale_item_id',
            [$saleId, 'POSTED']
        )->getResultArray();

        return array_map('floatval', array_column($rows, 'q', 'sale_item_id'));
    }

    /**
     * @param array{items:list<array{sale_item_id:int, qty:float, condition:string}>, reason_code:string, reason?:?string,
     *              refund_method:string, cash_account_id?:?int} $in
     */
    public function create(int $saleId, array $in, ?int $userId): array
    {
        $code   = (string) ($in['reason_code'] ?? '');
        $reason = trim((string) ($in['reason'] ?? '')) ?: null;
        $method = strtoupper((string) ($in['refund_method'] ?? 'CASH'));
        if (! isset(self::REASONS[$code])) {
            return ['success' => false, 'message' => 'Pilih alasan retur.'];
        }
        if ($code === 'LAINNYA' && ! $reason) {
            return ['success' => false, 'message' => 'Jelaskan alasan retur.'];
        }
        if (! in_array($method, ['CASH', 'TRANSFER'], true)) {
            return ['success' => false, 'message' => 'Pilih cara pengembalian uang.'];
        }

        $req = [];
        foreach ($in['items'] ?? [] as $it) {
            $q = round((float) ($it['qty'] ?? 0), 3);
            if ($q <= 0) {
                continue;
            }
            $cond = strtoupper((string) ($it['condition'] ?? 'RESTOCK'));
            if (! in_array($cond, ['RESTOCK', 'DAMAGED'], true)) {
                return ['success' => false, 'message' => 'Kondisi barang tidak valid.'];
            }
            $req[] = ['sale_item_id' => (int) $it['sale_item_id'], 'qty' => $q, 'condition' => $cond];
        }
        if (! $req) {
            return ['success' => false, 'message' => 'Isi qty barang yang diretur.'];
        }

        $settings = new SettingModel();
        $db       = db_connect();
        $db->transBegin();
        try {
            $sale = $db->query('SELECT * FROM pos_sales WHERE id = ? FOR UPDATE', [$saleId])->getRowArray();
            if (! $sale || $sale['status'] !== 'POSTED') {
                throw new StockException('Penjualan tidak ditemukan atau sudah dibatalkan.');
            }
            if ($db->table('pos_payments')->where('sale_id', $saleId)->where('status', 'PENDING_VERIFICATION')->countAllResults()) {
                throw new StockException('Masih ada pembayaran QRIS/Transfer yang belum diverifikasi pada penjualan ini. Verifikasi/tolak dulu.');
            }
            $loc = (int) $sale['location_id'];
            if ((new StockOpnameService($this->ledger))->isCounting($loc)) {
                throw new StockException('Stock opname sedang dihitung. Retur dicatat setelah hasil hitung disubmit.');
            }

            $items = array_column($db->table('pos_sale_items')->where('sale_id', $saleId)->get()->getResultArray(), null, 'id');
            $done  = $this->returnedQty($saleId);
            $lines = [];
            $value = 0.0;
            $cost  = 0.0;
            foreach ($req as $r) {
                $si = $items[$r['sale_item_id']] ?? null;
                if (! $si) {
                    throw new StockException('Barang tidak ada di penjualan ini.');
                }
                $left = round((float) $si['qty'] - ($done[$si['id']] ?? 0), 3);
                if ($r['qty'] > $left + 0.0005) {
                    throw new StockException('Qty retur melebihi sisa yang bisa diretur (' . rtrim(rtrim(number_format($left, 3, ',', '.'), '0'), ',') . ' ' . $si['unit'] . ').');
                }
                $unitPrice = $si['line_type'] === 'PROMO_FREE' || (float) $si['qty'] <= 0 ? 0.0 : (float) $si['line_total'] / (float) $si['qty'];
                $lv        = round($r['qty'] * $unitPrice, 2);
                $lc        = round($r['qty'] * (float) $si['unit_cost'], 2);
                $value    += $lv;
                $cost     += $lc;
                $lines[]   = $r + ['si' => $si, 'unit_price' => round($unitPrice, 2), 'value' => $lv, 'cost' => $lc];
            }
            $value = round($value, 2);

            // Refund dibatasi uang yang sudah diterima dikurangi refund sebelumnya.
            $prior    = (float) $db->query('SELECT COALESCE(SUM(refund_amount),0) s FROM pos_returns WHERE sale_id = ? AND status = ?', [$saleId, 'POSTED'])->getRow('s');
            $paidNet  = max(0, round((float) $sale['paid_total'] - $prior, 2));
            $refund   = min($value, $paidNet);
            $dueRed   = min(round($value - $refund, 2), (float) $sale['balance_due']);
            $now      = date('Y-m-d H:i:s');

            $dayId = null;
            $bizDate = date('Y-m-d');
            $accId = null;
            if ($refund > 0) {
                if ($method === 'CASH') {
                    $day = $db->query("SELECT * FROM pos_daily_closings WHERE location_id = ? AND status = 'OPEN' ORDER BY id DESC LIMIT 1 LOCK IN SHARE MODE", [$loc])->getRowArray();
                    if (! $day) {
                        throw new StockException('Refund tunai butuh toko dalam keadaan buka (kas harian OPEN). Buka toko dulu atau pilih refund transfer.');
                    }
                    $dayId   = (int) $day['id'];
                    $bizDate = $day['business_date'];
                    $accId   = (int) $settings->value('pos_cash_account', '0');
                } else {
                    $accId = (int) ($in['cash_account_id'] ?? 0);
                    if (! $accId || ! $db->table('cash_accounts')->where('id', $accId)->where('is_active', 1)->countAllResults()) {
                        throw new StockException('Pilih rekening sumber transfer refund.');
                    }
                }
            } else {
                $method = 'NONE';
            }

            $db->table('pos_returns')->insert([
                'return_no' => 'RT-TMP-' . bin2hex(random_bytes(6)), 'sale_id' => $saleId, 'location_id' => $loc, 'return_date' => $now,
                'daily_closing_id' => $dayId, 'reason_code' => $code, 'reason' => $reason ? mb_substr($reason, 0, 255) : null,
                'return_value' => $value, 'refund_amount' => $refund, 'due_reduction' => $dueRed, 'cost_value' => round($cost, 2),
                'refund_method' => $method, 'cash_account_id' => $accId, 'status' => 'POSTED', 'created_by' => $userId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $rid = (int) $db->insertID();
            $no  = sprintf('RT-%s-%05d', date('Ymd'), $rid);
            $db->table('pos_returns')->where('id', $rid)->update(['return_no' => $no]);

            usort($lines, static fn ($a, $b) => $a['si']['product_id'] <=> $b['si']['product_id']);
            foreach ($lines as $l) {
                $si = $l['si'];
                $db->table('pos_return_items')->insert([
                    'return_id' => $rid, 'sale_item_id' => $si['id'], 'product_id' => $si['product_id'], 'qty' => $l['qty'],
                    'unit_price' => $l['unit_price'], 'value' => $l['value'], 'unit_cost' => $si['unit_cost'], 'cost_value' => $l['cost'],
                    'item_condition' => $l['condition'],
                ]);
                $itemId = (int) $db->insertID();

                $inRes = $this->ledger->post([
                    'product_id' => (int) $si['product_id'], 'location_id' => $loc, 'movement_type' => 'CUSTOMER_RETURN',
                    'qty' => $l['qty'], 'total_cost' => $l['cost'],
                    'reference_type' => 'POS_RETURN', 'reference_id' => $rid, 'reference_line' => $itemId,
                    'reason' => "Retur {$no} dari {$sale['sale_no']}", 'user_id' => $userId,
                ]);
                $upd = ['ledger_id' => $inRes['id']];

                if ($l['condition'] === 'DAMAGED') {
                    // Barang retur rusak langsung jadi known shrinkage lewat Broken Stock yang sudah disetujui.
                    $db->table('stock_broken_reports')->insert([
                        'report_no' => 'BS-TMP-' . bin2hex(random_bytes(6)), 'location_id' => $loc, 'product_id' => $si['product_id'],
                        'qty' => $l['qty'], 'unit' => $si['unit'], 'reason_code' => 'RUSAK', 'reason' => "Retur pelanggan rusak ({$no})",
                        'est_unit_cost' => $si['unit_cost'], 'est_value' => $l['cost'], 'status' => 'POSTED',
                        'reported_by' => $userId, 'reported_at' => $now, 'approved_by' => $userId, 'approved_at' => $now,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $bid = (int) $db->insertID();
                    $bno = sprintf('BS-%s-%05d', date('Ymd'), $bid);
                    $out = $this->ledger->post([
                        'product_id' => (int) $si['product_id'], 'location_id' => $loc, 'movement_type' => 'BROKEN', 'qty' => $l['qty'],
                        'reference_type' => 'BROKEN_STOCK', 'reference_id' => $bid,
                        'reason' => BrokenStockService::REASONS['RUSAK'] . ": retur pelanggan ({$bno}, {$no})", 'user_id' => $userId,
                    ]);
                    $db->table('stock_broken_reports')->where('id', $bid)->update(['report_no' => $bno, 'ledger_id' => $out['id'], 'posted_value' => $out['total_cost']]);
                    $upd['broken_report_id'] = $bid;
                }
                $db->table('pos_return_items')->where('id', $itemId)->update($upd);
            }

            if ($refund > 0) {
                $ft    = new FinancialTransactionModel();
                $catId = $db->table('financial_categories')->where('name', 'Refund Penjualan POS')->where('type', 'EXPENSE')->get()->getRow('id');
                $ftId  = $ft->recordFromReference('POS_RETURN_REFUND', $rid, [
                    'transaction_date' => $bizDate, 'type' => 'EXPENSE', 'category_id' => $catId ?: null, 'amount' => $refund,
                    'description' => "Refund retur {$no} ({$sale['sale_no']})", 'cash_account_id' => $accId,
                    'account_id' => $sale['customer_id'] ?: null, 'created_by' => $userId,
                ]);
                if (! $ftId) {
                    throw new \RuntimeException('Gagal mencatat refund: ' . implode('; ', $ft->errors()));
                }
                $db->table('pos_returns')->where('id', $rid)->update(['financial_transaction_id' => $ftId]);
            }

            if ($dueRed > 0) {
                $newDue = round((float) $sale['balance_due'] - $dueRed, 2);
                $db->table('pos_sales')->where('id', $saleId)->update([
                    'balance_due' => $newDue, 'payment_status' => $newDue <= 0 ? 'PAID' : $sale['payment_status'], 'updated_at' => $now,
                ]);
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Retur POS gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Retur gagal disimpan. Tidak ada stok/kas yang berubah.'];
        }

        (new AuditLogService())->log('Retur POS', 'pos', 'POS_RETURN', $rid, "{$no} dari {$sale['sale_no']}: nilai Rp " . number_format($value, 0, ',', '.') . ", refund Rp " . number_format($refund, 0, ',', '.') . " ({$method})");

        return ['success' => true, 'message' => "Retur {$no} tersimpan. Refund Rp " . number_format($refund, 0, ',', '.') . ($method === 'CASH' ? ' dari Kas Kasir.' : ($method === 'TRANSFER' ? ' via transfer.' : '.')), 'id' => $rid, 'refund' => $refund];
    }

    /** Koreksi metode bayar yang salah dicatat (Tunai / QRIS / Transfer) lewat pemindahan dana antar akun. */
    public function correctPayment(int $paymentId, string $toMethod, string $reason, ?int $userId): array
    {
        $toMethod = strtoupper($toMethod);
        if (! in_array($toMethod, PosSaleService::METHODS, true)) {
            return ['success' => false, 'message' => 'Metode tujuan tidak valid.'];
        }
        if (trim($reason) === '') {
            return ['success' => false, 'message' => 'Alasan koreksi wajib diisi.'];
        }
        $settings = new SettingModel();
        $db       = db_connect();
        $db->transBegin();
        try {
            $p = $db->query('SELECT * FROM pos_payments WHERE id = ? FOR UPDATE', [$paymentId])->getRowArray();
            if (! $p || $p['status'] !== 'CONFIRMED') {
                throw new StockException('Hanya pembayaran terkonfirmasi yang dapat dikoreksi.');
            }
            if ($p['corrected_method']) {
                throw new StockException('Pembayaran ini sudah pernah dikoreksi.');
            }
            if ($p['method'] === $toMethod) {
                throw new StockException('Metode tujuan sama dengan metode tercatat.');
            }
            $sale = $db->table('pos_sales')->where('id', $p['sale_id'])->get()->getRowArray();
            if (! $sale || $sale['status'] !== 'POSTED') {
                throw new StockException('Penjualan sudah dibatalkan.');
            }
            $from = (int) $p['cash_account_id'];
            $to   = (new PosSaleService())->accountFor($toMethod);
            $biz  = $sale['daily_closing_id']
                ? ($db->table('pos_daily_closings')->select('business_date')->where('id', $sale['daily_closing_id'])->get()->getRow('business_date') ?: substr($sale['sale_date'], 0, 10))
                : substr($sale['sale_date'], 0, 10);
            $now  = date('Y-m-d H:i:s');
            $amt  = (float) $p['amount'];

            $db->table('pos_payment_corrections')->insert([
                'payment_id' => $paymentId, 'sale_id' => $p['sale_id'], 'from_method' => $p['method'], 'to_method' => $toMethod,
                'from_account_id' => $from, 'to_account_id' => $to, 'amount' => $amt, 'business_date' => $biz,
                'reason' => mb_substr(trim($reason), 0, 255), 'created_by' => $userId, 'created_at' => $now,
            ]);
            $cid   = (int) $db->insertID();
            $catId = $db->table('financial_categories')->where('name', 'Pemindahan Dana')->get()->getRow('id');
            $label = PosSaleService::label($p['method']) . ' → ' . PosSaleService::label($toMethod);
            $desc  = "Koreksi metode bayar {$sale['sale_no']} ({$label})";
            $ft    = new FinancialTransactionModel();

            $ft->insert(['transaction_date' => $biz, 'type' => 'EXPENSE', 'category_id' => $catId ?: null, 'amount' => $amt, 'description' => $desc,
                'reference_type' => 'TRANSFER_OUT', 'reference_id' => $cid, 'cash_account_id' => $from, 'created_by' => $userId]);
            $outId = (int) $ft->getInsertID();
            $ft->insert(['transaction_date' => $biz, 'type' => 'INCOME', 'category_id' => $catId ?: null, 'amount' => $amt, 'description' => $desc,
                'reference_type' => 'TRANSFER_IN', 'reference_id' => $outId, 'cash_account_id' => $to, 'created_by' => $userId]);
            $inId = (int) $ft->getInsertID();
            if (! $outId || ! $inId) {
                throw new \RuntimeException('Gagal mencatat pemindahan dana: ' . implode('; ', $ft->errors()));
            }

            $db->table('pos_payment_corrections')->where('id', $cid)->update(['transfer_out_id' => $outId, 'transfer_in_id' => $inId]);
            $db->table('pos_payments')->where('id', $paymentId)->update(['corrected_method' => $toMethod, 'updated_at' => $now]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Koreksi pembayaran gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Koreksi gagal disimpan.'];
        }

        (new AuditLogService())->log('Koreksi Metode Bayar', 'pos', 'POS_PAYMENT', $paymentId, "{$sale['sale_no']} {$label} Rp " . number_format($amt, 0, ',', '.') . ': ' . trim($reason));

        return ['success' => true, 'message' => "Metode bayar dikoreksi ({$label}); dana dipindahkan pada tanggal bisnis {$biz}."];
    }
}
