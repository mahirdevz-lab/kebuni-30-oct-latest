<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentTermModel extends Model
{
    protected $table         = 'payment_terms';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'days', 'daily_interest_percent', 'status', 'notes'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'name'                   => 'required|min_length[2]|max_length[100]',
        'days'                   => 'required|integer|greater_than_equal_to[0]',
        'daily_interest_percent' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'status'                 => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    public function activeOptions(): array
    {
        return $this->where('status', 'aktif')->orderBy('days', 'ASC')->findAll();
    }

    /**
     * Scope daftar + filter untuk pagination (BUG-1/BUG-3 PRD-BACKOFFICE-UI T2).
     */
    public function scopeList(?string $search = null, ?string $status = null)
    {
        $builder = $this->orderBy('days', 'ASC');

        if ($search) {
            $builder->like('name', $search);
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder;
    }
}
