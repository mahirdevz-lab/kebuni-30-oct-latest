<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Target KPI mingguan tim sosmed (berlaku per tanggal, ada riwayat).
 */
class SosmedTargetModel extends Model
{
    protected $table            = 'sosmed_targets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['feeds_per_week', 'videos_per_week', 'effective_from'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = false;

    /**
     * Target yang berlaku untuk suatu tanggal.
     */
    public function activeAt(string $date): ?array
    {
        return $this->where('effective_from <=', $date)
            ->orderBy('effective_from', 'DESC')
            ->first();
    }

    /**
     * Riwayat target (terbaru dulu).
     */
    public function history(): array
    {
        return $this->orderBy('effective_from', 'DESC')->findAll();
    }
}
