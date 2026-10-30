<?php

namespace App\Models;

use CodeIgniter\Model;

class PricePeriodPackingModel extends Model
{
    protected $table         = 'price_period_packings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'price_period_id', 'packing_material_id', 'price', 'capacity_kg',
        'cost_per_kg', 'is_manual_override', 'notes',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'packing_material_id' => 'required|integer',
        'price'               => 'required|decimal|greater_than_equal_to[0]',
        'cost_per_kg'         => 'required|decimal|greater_than_equal_to[0]',
    ];

    public function getByPeriod(int $pricePeriodId): array
    {
        return $this->select('price_period_packings.*, packing_materials.code, packing_materials.name, packing_materials.unit')
            ->join('packing_materials', 'packing_materials.id = price_period_packings.packing_material_id')
            ->where('price_period_packings.price_period_id', $pricePeriodId)
            ->orderBy('packing_materials.name', 'ASC')
            ->findAll();
    }

    /**
     * Cari 1 baris harga packing untuk kombinasi Price Period + Packing Material tertentu.
     * Dipakai oleh Price Calculation Engine.
     */
    public function findByPeriodAndMaterial(int $pricePeriodId, int $packingMaterialId): ?array
    {
        return $this->where('price_period_id', $pricePeriodId)
            ->where('packing_material_id', $packingMaterialId)
            ->first();
    }

    /**
     * @param array $items [['packing_material_id'=>int, 'price'=>float, 'capacity_kg'=>?float,
     *                       'cost_per_kg'=>?float (manual override), 'is_manual_override'=>bool, 'notes'=>?string], ...]
     */
    public function replaceForPeriod(int $pricePeriodId, array $items): array
    {
        $db = db_connect();
        $db->transStart();

        $this->where('price_period_id', $pricePeriodId)->delete();

        foreach ($items as $item) {
            $price      = (float) $item['price'];
            $capacity   = isset($item['capacity_kg']) && $item['capacity_kg'] !== '' ? (float) $item['capacity_kg'] : null;
            $isManual   = ! empty($item['is_manual_override']);
            $costPerKg  = $isManual
                ? (float) ($item['cost_per_kg'] ?? 0)
                : ($capacity && $capacity > 0 ? round($price / $capacity, 4) : $price);

            $this->insert([
                'price_period_id'     => $pricePeriodId,
                'packing_material_id' => (int) $item['packing_material_id'],
                'price'               => $price,
                'capacity_kg'         => $capacity,
                'cost_per_kg'         => $costPerKg,
                'is_manual_override'  => $isManual ? 1 : 0,
                'notes'               => $item['notes'] ?? null,
            ], false);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan Packing Price.'];
        }

        return ['success' => true, 'message' => 'Packing Price berhasil disimpan.'];
    }
}
