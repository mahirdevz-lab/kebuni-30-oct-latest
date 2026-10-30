<?php

namespace App\Services;

use App\Models\PricePeriodLabourRateModel; // <-- Added
use App\Models\PricePeriodModel;
use App\Models\PricePeriodOpexAreaModel;
use App\Models\PricePeriodPackingModel;
use App\Models\PricePeriodProductModel;
use App\Models\PricePeriodTransportModel;

class PricePeriodService
{
    protected PricePeriodModel $periodModel;
    protected PricePeriodProductModel $productModel;
    protected PricePeriodPackingModel $packingModel;
    protected PricePeriodTransportModel $transportModel;
    protected PricePeriodLabourRateModel $labourModel; // <-- Added
    protected PricePeriodOpexAreaModel $opexModel;

    public function __construct()
    {
        $this->periodModel    = new PricePeriodModel();
        $this->productModel   = new PricePeriodProductModel();
        $this->packingModel   = new PricePeriodPackingModel();
        $this->transportModel = new PricePeriodTransportModel();
        $this->labourModel    = new PricePeriodLabourRateModel(); // <-- Added
        $this->opexModel      = new PricePeriodOpexAreaModel();
    }

    public function paginate(?string $search, ?string $status, int $perPage = 10)
    {
        return $this->periodModel->scopeList($search, $status)->paginate($perPage);
    }

    public function pager()
    {
        return $this->periodModel->pager;
    }

    /**
     * Daftar untuk <select> dropdown: limit eksplisit, TANPA pager.
     * Pengganti pemakaian paginate() "palsu" (BUG-5 audit pagination).
     */
    public function dropdown(?string $status = null, int $limit = 500): array
    {
        return $this->periodModel->scopeList(null, $status)->limit($limit)->findAll();
    }

    public function find(int $id): ?array
    {
        return $this->periodModel->find($id);
    }

    public function isUsedByTransaction(int $pricePeriodId): bool
    {
        if (! db_connect()->tableExists('sales_orders')) {
            return false;
        }

        return db_connect()->table('sales_orders')
            ->where('price_period_id', $pricePeriodId)
            ->countAllResults() > 0;
    }

    public function isEditable(array $period): bool
    {
        if ($period['status'] === 'CLOSED') {
            return false;
        }

        if ($period['status'] === 'PUBLISHED' && $this->isUsedByTransaction($period['id'])) {
            return false;
        }

        return true;
    }

    public function create(array $data): array
    {
        $data['period_code'] = $this->periodModel->generatePeriodCode($data['start_date']);
        $data['status']      = 'DRAFT';

        if ($data['end_date'] < $data['start_date']) {
            return ['success' => false, 'message' => 'End Date tidak boleh lebih awal dari Start Date.'];
        }

        if (! $this->periodModel->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->periodModel->errors()];
        }

        return ['success' => true, 'message' => 'Price Period berhasil dibuat.', 'id' => $this->periodModel->getInsertID()];
    }

    public function updateHeader(int $id, array $data): array
    {
        $period = $this->periodModel->find($id);

        if (! $period) {
            return ['success' => false, 'message' => 'Price Period tidak ditemukan.'];
        }

        if (! $this->isEditable($period)) {
            return ['success' => false, 'message' => 'Price Period sudah Closed / sudah dipakai transaksi, tidak dapat diedit. Buat Price Period baru (revision).'];
        }

        if (isset($data['end_date'], $data['start_date']) && $data['end_date'] < $data['start_date']) {
            return ['success' => false, 'message' => 'End Date tidak boleh lebih awal dari Start Date.'];
        }

        // Defensive: labour_budget hanya dikirim jika kolomnya sudah ada (migrate sudah jalan).
        // Tanpa ini, edit header gagal total kalau kolom belum ada.
        if (array_key_exists('labour_budget', $data)) {
            $db = db_connect();
            if (! $db->fieldExists('labour_budget', 'price_periods')) {
                unset($data['labour_budget']);
            }
        }

        if (! $this->periodModel->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->periodModel->errors()];
        }

        return ['success' => true, 'message' => 'Price Period berhasil diperbarui.'];
    }

    public function publish(int $id): array
    {
        $period = $this->periodModel->find($id);

        if (! $period) {
            return ['success' => false, 'message' => 'Price Period tidak ditemukan.'];
        }

        if ($period['status'] !== 'DRAFT') {
            return ['success' => false, 'message' => 'Hanya Price Period berstatus Draft yang dapat dipublish.'];
        }

        $productCount = $this->productModel->where('price_period_id', $id)->countAllResults();
        if ($productCount === 0) {
            return ['success' => false, 'message' => 'Minimal harus ada 1 Product Price sebelum publish.'];
        }

        $this->periodModel->update($id, ['status' => 'PUBLISHED']);

        return ['success' => true, 'message' => 'Price Period berhasil dipublish dan siap digunakan untuk transaksi.'];
    }

    public function close(int $id): array
    {
        $period = $this->periodModel->find($id);

        if (! $period) {
            return ['success' => false, 'message' => 'Price Period tidak ditemukan.'];
        }

        if ($period['status'] !== 'PUBLISHED') {
            return ['success' => false, 'message' => 'Hanya Price Period berstatus Published yang dapat di-close.'];
        }

        $this->periodModel->update($id, ['status' => 'CLOSED']);

        return ['success' => true, 'message' => 'Price Period berhasil di-close. Tidak dapat digunakan untuk transaksi baru.'];
    }

    public function delete(int $id): array
    {
        $period = $this->periodModel->find($id);

        if (! $period) {
            return ['success' => false, 'message' => 'Price Period tidak ditemukan.'];
        }

        if ($period['status'] !== 'DRAFT') {
            return ['success' => false, 'message' => 'Hanya Price Period berstatus Draft yang dapat dihapus.'];
        }

        $this->periodModel->delete($id);

        return ['success' => true, 'message' => 'Price Period berhasil dihapus.'];
    }

    public function duplicate(int $id, string $newStartDate, string $newEndDate): array
    {
        $period = $this->periodModel->find($id);

        if (! $period) {
            return ['success' => false, 'message' => 'Price Period tidak ditemukan.'];
        }

        if ($newEndDate < $newStartDate) {
            return ['success' => false, 'message' => 'End Date tidak boleh lebih awal dari Start Date.'];
        }

        $db = db_connect();
        $db->transStart();

        $newId = $this->periodModel->insert([
            'period_code' => $this->periodModel->generatePeriodCode($newStartDate),
            'name'        => $period['name'] . ' (Revision)',
            'start_date'  => $newStartDate,
            'end_date'    => $newEndDate,
            'status'      => 'DRAFT',
            'notes'       => $period['notes'],
        ]);

        foreach ($this->productModel->getByPeriod($id) as $row) {
            $this->productModel->insert([
                'price_period_id'   => $newId,
                'product_id'        => $row['product_id'],
                'purchase_price'    => $row['purchase_price'],
                'shrinkage_percent' => $row['shrinkage_percent'],
                'notes'             => $row['notes'],
            ], false);
        }

        foreach ($this->packingModel->getByPeriod($id) as $row) {
            $this->packingModel->insert([
                'price_period_id'     => $newId,
                'packing_material_id' => $row['packing_material_id'],
                'price'               => $row['price'],
                'capacity_kg'         => $row['capacity_kg'],
                'cost_per_kg'         => $row['cost_per_kg'],
                'is_manual_override'  => $row['is_manual_override'],
                'notes'               => $row['notes'],
            ], false);
        }

        foreach ($this->transportModel->getByPeriod($id) as $row) {
            $this->transportModel->insert([
                'price_period_id'      => $newId,
                'transport_route_id'   => $row['transport_route_id'],
                'total_transport_cost' => $row['total_transport_cost'],
                'volume_kg'            => $row['volume_kg'],
                'basis'                => $row['basis'],
                'cost_per_kg'          => $row['cost_per_kg'],
                'notes'                => $row['notes'],
            ], false);
        }

        // Tambahkan di dalam method duplicate() pada PricePeriodService.php:
        $labourRates = $this->labourModel->where('price_period_id', $id)->findAll();
        foreach ($labourRates as $row) {
            $this->labourModel->insert([
                'price_period_id' => $newId,
                'labour_rate_id'  => $row['labour_rate_id'],
                'rate_per_person' => $row['rate_per_person'],
                'notes'           => $row['notes'],
            ], false);
        }

        $opexAreasPayload = [];
        foreach ($this->opexModel->getByPeriod($id) as $area) {
            $opexAreasPayload[] = [
                'opex_area_id'         => $area['opex_area_id'],
                'allocation_volume_kg' => $area['allocation_volume_kg'],
                'notes'                => $area['notes'],
                'items'                => array_map(static fn ($item) => [
                    'opex_category_id' => $item['opex_category_id'],
                    'amount'           => $item['amount'],
                    'notes'            => $item['notes'],
                ], $area['items']),
            ];
        }
        if (! empty($opexAreasPayload)) {
            $this->opexModel->replaceForPeriod($newId, $opexAreasPayload);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menduplikasi Price Period.'];
        }

        return ['success' => true, 'message' => 'Price Period baru berhasil dibuat sebagai revision.', 'id' => $newId];
    }

    // ---------------- PRODUCT PRICE ----------------

    public function getProductPrices(int $periodId): array
    {
        return $this->productModel->getByPeriod($periodId);
    }

    public function saveProductPrices(int $periodId, array $items): array
    {
        $period = $this->periodModel->find($periodId);

        if (! $period || ! $this->isEditable($period)) {
            return ['success' => false, 'message' => 'Price Period tidak dapat diedit.'];
        }

        return $this->productModel->replaceForPeriod($periodId, $items);
    }

    // ---------------- PACKING PRICE ----------------

    public function getPackingPrices(int $periodId): array
    {
        return $this->packingModel->getByPeriod($periodId);
    }

    public function savePackingPrices(int $periodId, array $items): array
    {
        $period = $this->periodModel->find($periodId);

        if (! $period || ! $this->isEditable($period)) {
            return ['success' => false, 'message' => 'Price Period tidak dapat diedit.'];
        }

        return $this->packingModel->replaceForPeriod($periodId, $items);
    }

    // ---------------- LABOUR RATES ----------------

    public function getLabourRates(int $periodId): array
    {
        return $this->labourModel->getByPeriod($periodId);
    }

    public function saveLabourRates(int $periodId, array $data): array
    {
        $period = $this->periodModel->find($periodId);

        if (! $period || ! $this->isEditable($period)) {
            return ['success' => false, 'message' => 'Price Period tidak dapat diedit.'];
        }

        return $this->labourModel->updateForPeriod($periodId, $data);
    }

    // ---------------- TRANSPORT PRICE ----------------

    public function getTransportPrices(int $periodId): array
    {
        return $this->transportModel->getByPeriod($periodId);
    }

    public function saveTransportPrices(int $periodId, array $items): array
    {
        $period = $this->periodModel->find($periodId);

        if (! $period || ! $this->isEditable($period)) {
            return ['success' => false, 'message' => 'Price Period tidak dapat diedit.'];
        }

        return $this->transportModel->replaceForPeriod($periodId, $items);
    }

    // ---------------- OPEX ----------------

    public function getOpex(int $periodId): array
    {
        return $this->opexModel->getByPeriod($periodId);
    }

    public function saveOpex(int $periodId, array $areas): array
    {
        $period = $this->periodModel->find($periodId);

        if (! $period || ! $this->isEditable($period)) {
            return ['success' => false, 'message' => 'Price Period tidak dapat diedit.'];
        }

        return $this->opexModel->replaceForPeriod($periodId, $areas);
    }
}
