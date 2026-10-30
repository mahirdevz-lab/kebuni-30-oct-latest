<?php

namespace App\Models;

use CodeIgniter\Model;

class PricingTierModel extends Model
{
    protected $table         = 'pricing_tiers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'min_qty', 'max_qty', 'markup_percent', 'start_date', 'end_date', 'status', 'notes'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'name'           => 'required|min_length[2]|max_length[100]',
        'min_qty'        => 'required|decimal|greater_than_equal_to[0]',
        'max_qty'        => 'permit_empty|decimal|greater_than[0]',
        'markup_percent' => 'required|decimal|greater_than_equal_to[0]',
        'start_date'     => 'permit_empty|valid_date[Y-m-d]',
        'end_date'       => 'permit_empty|valid_date[Y-m-d]',
        'status'         => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    public function activeList(): array
    {
        return $this->where('status', 'aktif')->orderBy('min_qty', 'ASC')->findAll();
    }

    public function allOrdered(): array
    {
        return $this->orderBy('min_qty', 'ASC')->findAll();
    }

    /**
     * Scope daftar + filter untuk pagination (BUG-1/BUG-3 PRD-BACKOFFICE-UI T2).
     */
    public function scopeList(?string $search = null, ?string $status = null)
    {
        $builder = $this->orderBy('min_qty', 'ASC');

        if ($search) {
            $builder->like('name', $search);
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder;
    }

    /**
     * Cek apakah range qty [minQty, maxQty] tumpang tindih dengan tier aktif lain.
     * maxQty null dianggap tidak terbatas (infinity).
     */
    public function hasQtyOverlap(float $minQty, ?float $maxQty, ?int $excludeId = null): bool
    {
        $others = $this->where('status', 'aktif');
        if ($excludeId) {
            $others->where('id !=', $excludeId);
        }

        foreach ($others->findAll() as $tier) {
            $existingMin = (float) $tier['min_qty'];
            $existingMax = $tier['max_qty'] !== null ? (float) $tier['max_qty'] : INF;
            $newMax      = $maxQty ?? INF;

            // Dua range overlap jika: newMin <= existingMax DAN existingMin <= newMax
            if ($minQty <= $existingMax && $existingMin <= $newMax) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cari tier yang cocok untuk qty tertentu (dipakai Pricing Calculation Engine).
     */
    public function findForQty(float $qty): ?array
    {
        foreach ($this->activeList() as $tier) {
            $min = (float) $tier['min_qty'];
            $max = $tier['max_qty'] !== null ? (float) $tier['max_qty'] : INF;

            if ($qty >= $min && $qty <= $max) {
                return $tier;
            }
        }

        return null;
    }
}
