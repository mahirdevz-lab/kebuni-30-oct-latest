<?php

namespace App\Models;

use CodeIgniter\Model;

class PricePeriodTransportModel extends Model
{
    protected $table         = 'price_period_transports';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'price_period_id', 'transport_route_id', 'total_transport_cost',
        'volume_kg', 'basis', 'cost_per_kg', 'notes',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'transport_route_id'    => 'required|integer',
        'total_transport_cost'  => 'required|decimal|greater_than_equal_to[0]',
        'volume_kg'             => 'required|decimal|greater_than[0]',
        'basis'                 => 'permit_empty|in_list[CAPACITY,ACTUAL]',
    ];

    public function getByPeriod(int $pricePeriodId): array
    {
        return $this->select('price_period_transports.*, transport_routes.origin, destinations.name as destination_name')
            ->join('transport_routes', 'transport_routes.id = price_period_transports.transport_route_id')
            ->join('destinations', 'destinations.id = transport_routes.destination_id')
            ->where('price_period_transports.price_period_id', $pricePeriodId)
            ->orderBy('destinations.name', 'ASC')
            ->findAll();
    }

    /**
     * Cari 1 baris transport cost untuk kombinasi Price Period + Transport Route tertentu,
     * lengkap dengan label origin/destination (dipakai Price Calculation Engine).
     */
    public function findByPeriodAndRoute(int $pricePeriodId, int $transportRouteId): ?array
    {
        return $this->select('price_period_transports.*, transport_routes.origin, destinations.name as destination_name')
            ->join('transport_routes', 'transport_routes.id = price_period_transports.transport_route_id')
            ->join('destinations', 'destinations.id = transport_routes.destination_id')
            ->where('price_period_transports.price_period_id', $pricePeriodId)
            ->where('price_period_transports.transport_route_id', $transportRouteId)
            ->first();
    }

    /**
     * @param array $items [['transport_route_id'=>int, 'total_transport_cost'=>float,
     *                       'volume_kg'=>float, 'basis'=>'CAPACITY'|'ACTUAL', 'notes'=>?string], ...]
     */
    public function replaceForPeriod(int $pricePeriodId, array $items): array
    {
        $db = db_connect();
        $db->transStart();

        $this->where('price_period_id', $pricePeriodId)->delete();

        foreach ($items as $item) {
            $totalCost = (float) $item['total_transport_cost'];
            $volume    = (float) $item['volume_kg'];
            $costPerKg = $volume > 0 ? round($totalCost / $volume, 4) : 0;

            $this->insert([
                'price_period_id'      => $pricePeriodId,
                'transport_route_id'   => (int) $item['transport_route_id'],
                'total_transport_cost' => $totalCost,
                'volume_kg'            => $volume,
                'basis'                => $item['basis'] ?? 'CAPACITY',
                'cost_per_kg'          => $costPerKg,
                'notes'                => $item['notes'] ?? null,
            ], false);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan Transport Price.'];
        }

        return ['success' => true, 'message' => 'Transport Price berhasil disimpan.'];
    }
}
