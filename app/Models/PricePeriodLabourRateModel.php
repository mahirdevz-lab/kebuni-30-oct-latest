<?php

namespace App\Models;

use CodeIgniter\Model;

class PricePeriodLabourRateModel extends Model
{
    protected $table            = 'price_period_labour_rates';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'price_period_id',
        'labour_rate_id',
        'rate_per_person',
        'notes',
    ];

    /**
     * Ambil semua master kuli + tarifnya untuk price_period_id tertentu
     */
    public function getByPeriod(int $periodId): array
    {
        $db = db_connect();
        
        // Query JOIN ke master tabel labour_rates (sesuaikan nama tabel master kuli di DB-mu)
        return $db->table('labour_rates master')
            ->select('master.id, master.name, COALESCE(ppl.rate_per_person, 0) as rate_per_person, ppl.notes')
            ->join('price_period_labour_rates ppl', 'ppl.labour_rate_id = master.id AND ppl.price_period_id = ' . $db->escape($periodId), 'left')
            ->get()
            ->getResultArray();
    }

    /**
     * Upsert dinamis berdasarkan array input rates[labour_rate_id] => value
     */
    public function updateForPeriod(int $periodId, array $rates): array
    {
        foreach ($rates as $labourRateId => $rateValue) {
            $existing = $this->where('price_period_id', $periodId)
                             ->where('labour_rate_id', $labourRateId)
                             ->first();

            $payload = [
                'price_period_id' => $periodId,
                'labour_rate_id'  => (int) $labourRateId,
                'rate_per_person' => (float) $rateValue,
            ];

            if ($existing) {
                $this->update($existing['id'], $payload);
            } else {
                $this->insert($payload);
            }
        }

        return [
            'success' => true,
            'message' => 'Tarif labour berhasil disimpan.',
        ];
    }
}