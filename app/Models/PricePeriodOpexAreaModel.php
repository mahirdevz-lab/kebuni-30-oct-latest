<?php

namespace App\Models;

use CodeIgniter\Model;

class PricePeriodOpexAreaModel extends Model
{
    protected $table         = 'price_period_opex_areas';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'price_period_id', 'opex_area_id', 'allocation_volume_kg',
        'total_opex', 'opex_cost_per_kg', 'notes',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'opex_area_id'         => 'required|integer',
        'allocation_volume_kg' => 'required|decimal|greater_than[0]',
    ];

    /**
     * Ambil seluruh area OPEX pada 1 Price Period, masing-masing lengkap
     * dengan rincian item (kategori biaya) di bawahnya.
     */
    public function getByPeriod(int $pricePeriodId): array
    {
        $areas = $this->select('price_period_opex_areas.*, opex_areas.code as area_code, opex_areas.name as area_name')
            ->join('opex_areas', 'opex_areas.id = price_period_opex_areas.opex_area_id')
            ->where('price_period_opex_areas.price_period_id', $pricePeriodId)
            ->orderBy('opex_areas.name', 'ASC')
            ->findAll();

        if (empty($areas)) {
            return [];
        }

        $itemModel = new PricePeriodOpexItemModel();
        foreach ($areas as &$area) {
            $area['items'] = $itemModel->getByArea($area['id']);
        }
        unset($area);

        return $areas;
    }

    /**
     * Rp/Kg OPEX untuk 1 area (by code, mis. 'HO' / 'SUKABUMI') pada Price Period tertentu.
     * Dipakai oleh Price Calculation Engine. Return 0 jika belum diisi.
     */
    public function findCostPerKgByCode(int $pricePeriodId, string $areaCode): float
    {
        $row = $this->select('price_period_opex_areas.opex_cost_per_kg')
            ->join('opex_areas', 'opex_areas.id = price_period_opex_areas.opex_area_id')
            ->where('price_period_opex_areas.price_period_id', $pricePeriodId)
            ->where('opex_areas.code', $areaCode)
            ->first();

        return $row ? (float) $row['opex_cost_per_kg'] : 0.0;
    }

    /**
     * Rp/Kg OPEX untuk area yang ditautkan ke sebuah Destination tertentu
     * (mis. area "Jakarta" yang destination_id-nya = id destination Jakarta).
     * Dipakai supaya engine otomatis tahu OPEX area mana yang berlaku
     * berdasarkan Transport Route/Destination yang dipilih di order.
     */
    public function findCostPerKgByDestinationId(int $pricePeriodId, int $destinationId): float
    {
        $row = $this->select('price_period_opex_areas.opex_cost_per_kg')
            ->join('opex_areas', 'opex_areas.id = price_period_opex_areas.opex_area_id')
            ->where('price_period_opex_areas.price_period_id', $pricePeriodId)
            ->where('opex_areas.destination_id', $destinationId)
            ->first();

        return $row ? (float) $row['opex_cost_per_kg'] : 0.0;
    }

    /**
     * @param array $areas [
     *   [
     *     'opex_area_id' => int, 'allocation_volume_kg' => float, 'notes' => ?string,
     *     'items' => [['opex_category_id' => int, 'amount' => float, 'notes' => ?string], ...],
     *   ], ...
     * ]
     */
    public function replaceForPeriod(int $pricePeriodId, array $areas): array
    {
        $db = db_connect();
        $db->transStart();

        // Hapus semua area (dan item di bawahnya via ON DELETE CASCADE) untuk periode ini.
        $this->where('price_period_id', $pricePeriodId)->delete();

        $itemModel = new PricePeriodOpexItemModel();

        foreach ($areas as $area) {
            $items = $area['items'] ?? [];

            $totalOpex = 0.0;
            foreach ($items as $item) {
                $totalOpex += (float) ($item['amount'] ?? 0);
            }

            $allocationVolume = (float) ($area['allocation_volume_kg'] ?? 0);
            $costPerKg        = $allocationVolume > 0 ? round($totalOpex / $allocationVolume, 4) : 0.0;

            $this->insert([
                'price_period_id'      => $pricePeriodId,
                'opex_area_id'         => (int) $area['opex_area_id'],
                'allocation_volume_kg' => $allocationVolume,
                'total_opex'           => round($totalOpex, 2),
                'opex_cost_per_kg'     => $costPerKg,
                'notes'                => $area['notes'] ?? null,
            ], false);

            $areaId = $this->getInsertID();

            foreach ($items as $item) {
                if (empty($item['opex_category_id'])) {
                    continue;
                }

                $itemModel->insert([
                    'price_period_opex_area_id' => $areaId,
                    'opex_category_id'          => (int) $item['opex_category_id'],
                    'amount'                    => (float) ($item['amount'] ?? 0),
                    'notes'                     => $item['notes'] ?? null,
                ], false);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan data OPEX.'];
        }

        return ['success' => true, 'message' => 'Data OPEX berhasil disimpan.'];
    }

    /**
     * Satu baris OPEX area pada Price Period by opex_area_id, lengkap dengan
     * code & nama area. Dipakai engine COGS manual (redesign 2026-10-18).
     * Return null jika area belum dianggarkan pada periode ini.
     */
    public function findByPeriodAndArea(int $pricePeriodId, int $opexAreaId): ?array
    {
        return $this->select('price_period_opex_areas.*, opex_areas.code as area_code, opex_areas.name as area_name')
            ->join('opex_areas', 'opex_areas.id = price_period_opex_areas.opex_area_id')
            ->where('price_period_opex_areas.price_period_id', $pricePeriodId)
            ->where('price_period_opex_areas.opex_area_id', $opexAreaId)
            ->first();
    }

    /**
     * Rp/Kg OPEX untuk 1 area (by opex_area_id) pada Price Period tertentu.
     * Return null jika belum diisi (bedakan dengan 0).
     */
    public function findCostPerKgById(int $pricePeriodId, int $opexAreaId): ?float
    {
        $row = $this->findByPeriodAndArea($pricePeriodId, $opexAreaId);

        return $row ? (float) $row['opex_cost_per_kg'] : null;
    }

    /**
     * Daftar OPEX area yang sudah dianggarkan pada 1 Price Period
     * (untuk picker manual di form order).
     *
     * @return array [['opex_area_id'=>, 'area_code'=>, 'area_name'=>, 'opex_cost_per_kg'=>], ...]
     */
    public function optionsByPeriod(int $pricePeriodId): array
    {
        return $this->select('price_period_opex_areas.opex_area_id, opex_areas.code as area_code, opex_areas.name as area_name, price_period_opex_areas.opex_cost_per_kg')
            ->join('opex_areas', 'opex_areas.id = price_period_opex_areas.opex_area_id')
            ->where('price_period_opex_areas.price_period_id', $pricePeriodId)
            ->where('opex_areas.status', 'aktif')
            ->orderBy('opex_areas.name', 'ASC')
            ->findAll();
    }
}

