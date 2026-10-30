<?php

namespace App\Services;

use App\Models\PricePeriodLabourRateModel;
use App\Models\PricePeriodModel;
use App\Models\SalesOrderItemModel;
use App\Models\SalesOrderModel;
use App\Services\FinanceService;

/**
 * SalesOrderService â€” Taking Order / PO.
 *
 * Menyesuaikan PriceCalculationService::calculateOrder(items, paymentTermId, labourCounts, legacyMale, legacyFemale):
 * - Labour dihitung di level ORDER (headcount x rate per labour_rate_id), dialokasikan rata ke semua item.
 * - Packing dapat override manual per item (packing_ids) atau fallback ke recipe product.
 * - Transport route dipilih per ITEM.
 *
 * Header & item HANYA bisa diedit selama status Draft. Begitu Submit, seluruh snapshot terkunci â€”
 * perubahan harga master/Price Period/Labour Rate setelahnya TIDAK PERNAH mempengaruhi PO ini lagi.
 */
class SalesOrderService
{
    protected SalesOrderModel $orderModel;
    protected SalesOrderItemModel $itemModel;
    protected PricePeriodModel $periodModel;
    protected PricePeriodLabourRateModel $labourRateModel;
    protected PriceCalculationService $engine;
    protected FinanceService $financeService;
    protected AuditLogService $auditLog;

    public function __construct()
    {
        $this->orderModel      = new SalesOrderModel();
        $this->itemModel       = new SalesOrderItemModel();
        $this->periodModel     = new PricePeriodModel();
        $this->labourRateModel = new PricePeriodLabourRateModel();
        $this->engine          = new PriceCalculationService();
        $this->financeService  = new FinanceService();
        $this->auditLog        = new AuditLogService();
    }

    public function paginate(?string $search, ?string $status, ?string $from, ?string $to, int $perPage = 10)
    {
        return $this->orderModel->scopeList($search, $status, $from, $to)->paginate($perPage);
    }

    public function pager()
    {
        return $this->orderModel->pager;
    }

    public function find(int $id): ?array
    {
        return $this->orderModel->find($id);
    }

    public function findWithRelations(int $id): ?array
    {
        return $this->orderModel->findWithRelations($id);
    }

    public function getItems(int $orderId): array
    {
        return $this->itemModel->getByOrder($orderId);
    }

    public function isEditable(array $order): bool
    {
        return $order['status'] === 'Draft';
    }

    /**
     * Simpan sebagai Draft (create baru jika $orderId null, replace item jika sudah ada).
     * Menghitung ulang seluruh snapshot harga via Price Calculation Engine, all-or-nothing.
     *
     * @param array $header [order_date, account_id, payment_term_id, price_period_id, notes]
     * @param array $items  [['product_id'=>, 'transport_route_id'=>?, 'packing_ids'=>[], 'qty'=>, 'manual_price'=>?], ...]
     * @param array $labourCounts [labour_rate_id => count]
     */
    public function saveDraft(?int $orderId, array $header, array $items, array $labourCounts = []): array
    {
        if (empty($items)) {
            return ['success' => false, 'message' => 'Minimal harus ada 1 item.'];
        }

        $pricePeriod = $this->periodModel->find((int) $header['price_period_id']);
        if (! $pricePeriod) {
            return ['success' => false, 'message' => 'Price Period tidak ditemukan.'];
        }
        if ($pricePeriod['status'] !== 'PUBLISHED') {
            return ['success' => false, 'message' => 'Price Period harus berstatus Published untuk dapat digunakan pada Taking Order.'];
        }

        if ($orderId) {
            $existing = $this->orderModel->find($orderId);
            if (! $existing) {
                return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
            }
            if (! $this->isEditable($existing)) {
                return ['success' => false, 'message' => 'Sales Order hanya dapat diedit selama status Draft.'];
            }
        }

        // Siapkan item untuk engine (tambahkan price_period_id di tiap item)
        // Redesign COGS 2026-10-18: transport & opex multi manual, labour per-item check
        $engineItems = array_map(static fn (array $i) => [
            'product_id'      => (int) $i['product_id'],
            'qty'             => (float) $i['qty'],
            'transport_ids'   => $i['transport_ids'] ?? (! empty($i['transport_route_id']) ? [(int) $i['transport_route_id']] : []),
            'packing_ids'     => $i['packing_ids'] ?? [],
            'opex_ids'        => $i['opex_ids'] ?? [],
            'labour_checked'  => ! empty($i['labour_checked']),
            'cost_of_fund_checked' => ! empty($i['cost_of_fund_checked']),
            'manual_price'    => $i['manual_price'] ?? null,
            'price_period_id' => (int) $header['price_period_id'],
        ], $items);

        $calc = $this->engine->calculateOrder($engineItems, (int) $header['payment_term_id'], $labourCounts);

        $lineErrors = [];
        foreach ($calc['lines'] as $i => $line) {
            if (! empty($line['errors'])) {
                $lineErrors["item_{$i}"] = $line['errors'];
            }
        }
        if (! empty($lineErrors)) {
            return ['success' => false, 'message' => 'Sebagian item tidak dapat dihitung. Periksa kembali Price Period.', 'errors' => $lineErrors];
        }

        // Komisi sales: % diinput manual per order, base = total (harga beli + shrinkage) x qty (bukan grand total)
        $commissionPercent = (float) ($header['commission_percent'] ?? 0);
        $commissionBase    = (float) ($calc['summary']['commission_base'] ?? 0);
        $commissionAmount  = round($commissionBase * $commissionPercent / 100, 2);
        $salesId           = ! empty($header['sales_id']) ? (int) $header['sales_id'] : null;

        // Snapshot labour model baru (redesign 2026-10-18): budget periode +
        // alokasi per-kg ke item yang dichecklist, agar audit trail lengkap.
        $labourSnapshot = [
            'labour_budget'      => $calc['summary']['labour_budget'] ?? 0,
            'labour_cost_per_kg' => $calc['summary']['labour_cost_per_kg'] ?? 0,
            'checked_items'      => array_values(array_filter(array_map(
                static fn (array $i) => ! empty($i['labour_checked']) ? (int) $i['product_id'] : null,
                $items
            ))),
        ];

        $db = db_connect();
        $db->transStart();

        $isNew = ! $orderId;
        if ($isNew) {
            $orderId = $this->orderModel->insert([
                'order_no'        => $this->orderModel->generateOrderNo(),
                'order_date'      => $header['order_date'],
                'account_id'      => (int) $header['account_id'],
                'sales_id'           => $salesId,
                'payment_term_id' => (int) $header['payment_term_id'],
                'price_period_id' => (int) $header['price_period_id'],
                'status'          => 'Draft',
                'notes'           => $header['notes'] ?? null,
                'commission_percent' => $commissionPercent,
                'created_by'      => session()->get('user_id'),
            ]);
        } else {
            $this->orderModel->update($orderId, [
                'order_date'      => $header['order_date'],
                'account_id'      => (int) $header['account_id'],
                'sales_id'           => $salesId,
                'payment_term_id' => (int) $header['payment_term_id'],
                'price_period_id' => (int) $header['price_period_id'],
                'notes'           => $header['notes'] ?? null,
                'commission_percent' => $commissionPercent,
            ]);
        }

        $itemRows = [];
        $overrideNotes = [];

        foreach ($calc['lines'] as $idx => $line) {
            $itemRows[] = [
                'product_id'                 => $line['product']['id'],
                'product_code_snapshot'      => $line['product']['code'],
                'product_name_snapshot'      => $line['product']['name'],
                'unit_snapshot'              => $line['product']['unit'],
                'transport_route_id'         => $items[$idx]['transport_route_id'] ?? null,
                'transport_breakdown_json'   => json_encode($line['transport_breakdown'] ?? []),
                'opex_breakdown_json'        => json_encode($line['opex_breakdown'] ?? []),
                'opex_cost'                  => $line['opex_cost'] ?? 0,
                'cost_of_fund'               => $line['cost_of_fund'] ?? 0,
                'destination_label_snapshot' => $line['destination_label'],
                'qty'                        => $line['qty'],
                'purchase_price'             => $line['purchase_price'],
                'packing_cost'               => $line['packing_cost'],
                'total_packing_pcs'          => $line['total_packing_pcs'] ?? 0,
                'packing_breakdown_json'     => json_encode($line['packing_breakdown'] ?? []),
                'labour_cost'                => $line['labour_cost'] ?? 0,
                'shrinkage_cost'             => $line['shrinkage_cost'],
                'opex_ho'                    => $line['opex_ho'] ?? 0,
                'opex_sukabumi'              => $line['opex_sukabumi'] ?? 0,
                'opex_destination'           => $line['opex_destination'] ?? 0,
                'via_sukabumi_warehouse'     => ($line['via_sukabumi_warehouse'] ?? true) ? 1 : 0,
                'cogs_sk'                    => $line['cogs_sk'],
                'transport_cost'             => $line['transport_cost'],
                'cogs_destination'           => $line['cogs_destination'],
                'tier_name_snapshot'         => $line['tier_name'],
                'markup_percent'             => $line['markup_percent'],
                'cash_price'                 => $line['cash_price'],
                'interest_amount'            => $line['interest_amount'],
                'final_price'                => $line['final_price'],
                'manual_price'               => $line['manual_price'],
                'selling_price'              => $line['selling_price'],
                'subtotal_cash'              => $line['subtotal_cash'],
                'subtotal_final'             => $line['subtotal_final'],
            ];

            if ($line['manual_price'] !== null) {
                $overrideNotes[] = sprintf(
                    '%s: sistem Rp%s -> manual Rp%s%s',
                    $line['product']['name'],
                    number_format($line['recommended_price'], 0, ',', '.'),
                    number_format($line['manual_price'], 0, ',', '.'),
                    $line['is_below_recommended'] ? ' (DI BAWAH REKOMENDASI)' : ''
                );
            }
        }

        $this->itemModel->replaceForOrder($orderId, $itemRows);

        $s = $calc['summary'];
        $this->orderModel->update($orderId, [
            'labour_counts_snapshot'   => json_encode($labourSnapshot),
            'total_qty'                => $s['total_qty'] ?? 0,
            'total_labour_cost'        => $s['total_labour_cost'] ?? 0,
            'labour_cost_per_kg'       => $s['labour_cost_per_kg'] ?? 0,
            'total_cogs'               => $s['total_cogs'] ?? 0,
            'grand_total'              => $s['grand_total'] ?? 0,
            'grand_total_cash'         => $s['grand_total_cash'] ?? 0,
            'estimated_profit'         => $s['estimated_profit'] ?? 0,
            'estimated_profit_percent' => $s['estimated_profit_percent'] ?? 0,
            'commission_amount'        => $commissionAmount,
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan Sales Order.'];
        }

        $this->auditLog->log(
            $isNew ? 'Create PO' : 'Update PO Draft',
            'sales_order',
            'SALES_ORDER',
            $orderId,
            $isNew ? 'Sales Order dibuat sebagai Draft.' : 'Sales Order Draft diperbarui.'
        );
        if (! empty($overrideNotes)) {
          $this->auditLog->log('Manual Price Override', 'sales_order', 'SALES_ORDER', $orderId, implode(' | ', $overrideNotes));
        }
        return ['success' => true, 'message' => 'Sales Order berhasil disimpan sebagai Draft.', 'id' => $orderId, 'summary' => $calc['summary']];
    }

    public function submit(int $id): array
    {
        $order = $this->orderModel->find($id);
        if (! $order) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }
        if ($order['status'] !== 'Draft') {
            return ['success' => false, 'message' => 'Hanya Sales Order berstatus Draft yang dapat di-submit.'];
        }
        if ($this->itemModel->getByOrder($id) === []) {
            return ['success' => false, 'message' => 'Sales Order tidak memiliki item.'];
        }

        $this->orderModel->update($id, ['status' => 'Submitted']);
        $this->auditLog->log('Submit PO', 'sales_order', 'SALES_ORDER', $id, 'Sales Order disubmit untuk approval. Harga terkunci.');

        return ['success' => true, 'message' => 'Sales Order berhasil disubmit dan menunggu approval. Harga sekarang terkunci.'];
    }

    public function approve(int $id): array
    {
        $order = $this->orderModel->find($id);
        if (! $order) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }
        if ($order['status'] !== 'Submitted') {
            return ['success' => false, 'message' => 'Hanya Sales Order berstatus Submitted yang dapat di-approve.'];
        }

        $this->orderModel->update($id, [
            'status'      => 'Approved',
            'approved_by' => session()->get('user_id'),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        $this->auditLog->log('Approve PO', 'sales_order', 'SALES_ORDER', $id, 'Sales Order disetujui.');

        return ['success' => true, 'message' => 'Sales Order berhasil disetujui.'];
    }

    public function process(int $id): array
    {
        $order = $this->orderModel->find($id);
        if (! $order) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }
        if ($order['status'] !== 'Approved') {
            return ['success' => false, 'message' => 'Hanya Sales Order berstatus Approved yang dapat diproses.'];
        }

        $this->orderModel->update($id, ['status' => 'Processing']);
        $this->auditLog->log('Process PO', 'sales_order', 'SALES_ORDER', $id, 'Sales Order mulai diproses.');

        return ['success' => true, 'message' => 'Sales Order berhasil diproses.'];
    }

    public function complete(int $id): array
    {
        $order = $this->orderModel->find($id);
        if (! $order) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }
        if ($order['status'] !== 'Processing') {
            return ['success' => false, 'message' => 'Hanya Sales Order berstatus Processing yang dapat diselesaikan.'];
        }

        $this->orderModel->update($id, ['status' => 'Completed']);
        $this->auditLog->log('Complete PO', 'sales_order', 'SALES_ORDER', $id, 'Sales Order selesai.');

        // Bukukan ke Finance: langsung income (cash) atau jadi Piutang (kredit),
        // tergantung Payment Term. Lihat FinanceService::recordFromCompletedSalesOrder().
        $this->financeService->recordFromCompletedSalesOrder($this->orderModel->find($id));

        return ['success' => true, 'message' => 'Sales Order berhasil diselesaikan.'];
    }

    public function cancel(int $id, ?string $reason = null): array
    {
        $order = $this->orderModel->find($id);
        if (! $order) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }
        if (in_array($order['status'], ['Completed', 'Cancelled'], true)) {
            return ['success' => false, 'message' => 'Sales Order berstatus ' . $order['status'] . ' tidak dapat dibatalkan.'];
        }

        $this->orderModel->update($id, ['status' => 'Cancelled']);
        $this->auditLog->log('Cancel PO', 'sales_order', 'SALES_ORDER', $id, $reason ?: 'Sales Order dibatalkan.');

        return ['success' => true, 'message' => 'Sales Order berhasil dibatalkan.'];
    }

    public function delete(int $id): array
    {
        $order = $this->orderModel->find($id);
        if (! $order) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }
        if ($order['status'] !== 'Draft') {
            return ['success' => false, 'message' => 'Hanya Sales Order berstatus Draft yang dapat dihapus.'];
        }

        // HARD DELETE untuk Draft: hapus items dulu, lalu order-nya permanen.
        $db = db_connect();
        $db->transStart();
        $db->table('sales_order_items')->where('sales_order_id', $id)->delete();
        $this->orderModel->delete($id, true); // purge = true → hard delete
        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menghapus Sales Order.'];
        }

        $this->auditLog->log('Delete PO', 'sales_order', 'SALES_ORDER', $id, 'Sales Order Draft dihapus permanen (hard delete).');

        return ['success' => true, 'message' => 'Sales Order berhasil dihapus permanen.'];
    }

    public function getAuditTrail(int $id): array
    {
        return (new \App\Models\ActivityLogModel())->getByReference('SALES_ORDER', $id);
    }

    /**
     * Catat pembayaran komisi (cicilan / lunas).
     * Update commission_paid_amount + commission_status (UNPAID/PARTIAL/PAID).
     */
    public function payCommission(int $orderId, float $amount, string $paymentDate, ?string $notes = null, $proofFile = null): array
    {
        $order = $this->orderModel->find($orderId);
        if (! $order) {
            return ['success' => false, 'message' => 'Sales Order tidak ditemukan.'];
        }

        $totalCommission = (float) ($order['commission_amount'] ?? 0);
        if ($totalCommission <= 0) {
            return ['success' => false, 'message' => 'Order ini tidak ada komisinya.'];
        }

        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Nominal pembayaran harus lebih dari 0.'];
        }

        $alreadyPaid = (float) ($order['commission_paid_amount'] ?? 0);
        $remaining   = $totalCommission - $alreadyPaid;

        if ($amount > $remaining + 0.01) {
            return ['success' => false, 'message' => 'Nominal melebihi sisa hutang komisi (Rp ' . number_format($remaining, 0, ',', '.') . ').'];
        }

        $db = db_connect();
        $db->transStart();

        // Upload bukti transfer (opsional)
        $proofPath = null;
        if ($proofFile && $proofFile->isValid() && ! $proofFile->hasMoved()) {
            $ext = strtolower($proofFile->getExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'webp'])) {
                $db->transRollback();
                return ['success' => false, 'message' => 'Format bukti harus JPG/PNG/WebP/PDF.'];
            }
            if ($proofFile->getSize() > 5 * 1024 * 1024) {
                $db->transRollback();
                return ['success' => false, 'message' => 'Ukuran bukti maksimal 5MB.'];
            }
            $fileName = 'komisi_' . $orderId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $proofFile->move(WRITEPATH . 'uploads/commission_proofs', $fileName);
            $proofPath = 'uploads/commission_proofs/' . $fileName;
        }

        (new \App\Models\CommissionPaymentModel())->insert([
            'sales_order_id' => $orderId,
            'amount'         => $amount,
            'payment_date'   => $paymentDate,
            'notes'          => $notes,
            'proof_path'     => $proofPath,
            'created_by'     => session()->get('user_id'),
        ]);

        $newPaid = $alreadyPaid + $amount;
        $status  = $newPaid >= $totalCommission - 0.01 ? 'PAID' : 'PARTIAL';

        $this->orderModel->update($orderId, [
            'commission_paid_amount' => $newPaid,
            'commission_status'      => $status,
        ]);

        // Audit trail
        (new \App\Models\ActivityLogModel())->log(
            'SALES_ORDER',
            $orderId,
            'commission_payment',
            'Pembayaran komisi Rp ' . number_format($amount, 0, ',', '.') . ' (' . $status . ')'
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan pembayaran komisi.'];
        }

        return [
            'success'   => true,
            'message'   => 'Pembayaran komisi Rp ' . number_format($amount, 0, ',', '.') . ' tercatat.',
            'status'    => $status,
            'paid'      => $newPaid,
            'remaining' => max(0, $totalCommission - $newPaid),
        ];
    }

    /**
     * Ringkasan komisi untuk Final Report (per price period atau per transaksi).
     */
    public function getCommissionSummary(?int $pricePeriodId = null, ?int $orderId = null): array
    {
        $builder = $this->orderModel->builder()
            ->select('COALESCE(SUM(commission_amount), 0) AS total, COALESCE(SUM(commission_paid_amount), 0) AS paid')
            ->whereIn('status', ['Submitted', 'Approved', 'Processing', 'Completed']);

        if ($orderId) {
            $builder->where('id', $orderId);
        } elseif ($pricePeriodId) {
            $builder->where('price_period_id', $pricePeriodId);
        }

        $row = $builder->get()->getRowArray();

        $total = (float) ($row['total'] ?? 0);
        $paid  = (float) ($row['paid'] ?? 0);

        return [
            'total'     => $total,
            'paid'      => $paid,
            'remaining' => max(0, $total - $paid),
        ];
    }
}

