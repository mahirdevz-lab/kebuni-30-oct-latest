<?php

namespace App\Services;

use App\Models\SettingModel;

/**
 * Penerimaan barang (PRD FR-06): kuantitas reguler + bonus supplier.
 * Bonus dicatat sebagai mutasi BONUS terpisah dari PURCHASE (dan terpisah dari PROMO_FREE penjualan).
 *
 * Kebijakan biaya bonus (setting bonus_cost_policy, menunggu keputusan Keuangan - PRD A6.1):
 *   allocate : total biaya baris dibagi ke seluruh unit (reguler + bonus) -> rata-rata turun.
 *   zero     : bonus bernilai Rp0; seluruh biaya melekat pada kuantitas reguler.
 */
class GoodsReceiptService
{
    public function __construct(
        private ?StockLedgerService $ledger = null,
        private ?AuditLogService $audit = null,
    ) {
        $this->ledger ??= new StockLedgerService();
        $this->audit  ??= new AuditLogService();
    }

    /**
     * @param array{supplier_id?:?int, receipt_date?:string, reference_no?:?string, notes?:?string,
     *              items:list<array{product_id:int, qty_regular:float, qty_bonus?:float, line_cost?:float}>} $data
     */
    public function create(array $data, ?int $userId = null): array
    {
        $items = [];
        foreach ($data['items'] ?? [] as $i) {
            $reg   = round((float) ($i['qty_regular'] ?? 0), 3);
            $bonus = round((float) ($i['qty_bonus'] ?? 0), 3);
            $cost  = round((float) ($i['line_cost'] ?? 0), 2);
            if ((int) ($i['product_id'] ?? 0) <= 0 || ($reg <= 0 && $bonus <= 0) || $reg < 0 || $bonus < 0 || $cost < 0) {
                return ['success' => false, 'message' => 'Item penerimaan tidak valid (produk, kuantitas, atau biaya).'];
            }
            if ($reg > 0 && $cost <= 0) {
                return ['success' => false, 'message' => 'Total biaya baris wajib diisi (> 0) untuk barang reguler. Isi total = qty × harga per unit.'];
            }
            if ($reg == 0 && $cost > 0) {
                return ['success' => false, 'message' => 'Baris hanya-bonus tidak boleh memiliki biaya pembelian.'];
            }
            $items[] = ['product_id' => (int) $i['product_id'], 'reg' => $reg, 'bonus' => $bonus, 'cost' => $cost];
        }
        if (! $items) {
            return ['success' => false, 'message' => 'Minimal satu item penerimaan.'];
        }
        if (empty($data['supplier_id'])) {
            return ['success' => false, 'message' => 'Supplier wajib dipilih.'];
        }

        $settings   = new SettingModel();
        $locationId = (int) $settings->value('pos_location_id', '1');
        if ((new StockOpnameService($this->ledger))->isCounting($locationId)) {
            return ['success' => false, 'message' => 'Stock opname sedang berlangsung. Penerimaan barang dicatat setelah hasil hitung disubmit.'];
        }
        $policy     = $settings->value('bonus_cost_policy', 'allocate') === 'zero' ? 'zero' : 'allocate';
        $now        = date('Y-m-d H:i:s');
        $db         = db_connect();

        $db->transBegin();
        try {
            $db->table('goods_receipts')->insert([
                'receipt_no'   => 'GR-TMP-' . bin2hex(random_bytes(6)),
                'supplier_id'  => (int) $data['supplier_id'],
                'location_id'  => $locationId,
                'receipt_date' => $data['receipt_date'] ?? date('Y-m-d'),
                'reference_no' => $data['reference_no'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'status'       => 'POSTED',
                'created_by'   => $userId,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            $receiptId = (int) $db->insertID();
            $receiptNo = sprintf('GR-%s-%05d', date('Ymd', strtotime($data['receipt_date'] ?? 'now')), $receiptId);
            $db->table('goods_receipts')->where('id', $receiptId)->update(['receipt_no' => $receiptNo]);

            // Kunci per produk berurutan agar tidak deadlock antar penerimaan.
            usort($items, static fn ($a, $b) => $a['product_id'] <=> $b['product_id']);

            foreach ($items as $n => $it) {
                $db->table('goods_receipt_items')->insert([
                    'receipt_id' => $receiptId, 'product_id' => $it['product_id'],
                    'qty_regular' => $it['reg'], 'qty_bonus' => $it['bonus'], 'line_cost' => $it['cost'],
                ]);
                $lineId = (int) $db->insertID();

                $allQty = $it['reg'] + $it['bonus'];
                if ($policy === 'allocate' && $allQty > 0) {
                    $bonusCost = $it['reg'] > 0 ? round($it['cost'] * $it['bonus'] / $allQty, 2) : 0.0;
                    $regCost   = round($it['cost'] - $bonusCost, 2);
                } else {
                    $bonusCost = 0.0;
                    $regCost   = $it['cost'];
                }

                $base = [
                    'product_id' => $it['product_id'], 'location_id' => $locationId,
                    'reference_type' => 'GOODS_RECEIPT', 'reference_id' => $receiptId, 'reference_line' => $lineId,
                    'user_id' => $userId, 'effective_at' => $now,
                ];
                if ($it['reg'] > 0) {
                    $this->ledger->post($base + ['movement_type' => 'PURCHASE', 'qty' => $it['reg'], 'total_cost' => $regCost, 'reason' => 'Penerimaan ' . $receiptNo]);
                }
                if ($it['bonus'] > 0) {
                    $this->ledger->post($base + ['movement_type' => 'BONUS', 'qty' => $it['bonus'], 'total_cost' => $bonusCost, 'reason' => 'Bonus supplier ' . $receiptNo]);
                }
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Penerimaan barang gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => $e instanceof StockException ? $e->getMessage() : 'Penerimaan barang gagal disimpan.'];
        }

        $this->audit->log('Penerimaan Barang', 'stock', 'GOODS_RECEIPT', $receiptId, $receiptNo . ' (' . count($items) . ' item, kebijakan bonus: ' . $policy . ')');

        return ['success' => true, 'message' => 'Penerimaan ' . $receiptNo . ' berhasil diposting.', 'id' => $receiptId, 'receipt_no' => $receiptNo];
    }
}
