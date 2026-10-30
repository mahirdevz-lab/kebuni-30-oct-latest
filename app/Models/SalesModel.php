<?php

namespace App\Models;

use CodeIgniter\Model;

class SalesModel extends Model
{
    protected $table            = 'sales';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = ['name', 'phone', 'email', 'status', 'notes'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'   => 'required|min_length[2]|max_length[100]',
        'phone'  => 'permit_empty|max_length[30]',
        'email'  => 'permit_empty|valid_email|max_length[150]',
        'status' => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    public function activeOptions(): array
    {
        return $this->select('id, name')->where('status', 'aktif')->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Scope daftar + pencarian (BUG-1/BUG-3 PRD-BACKOFFICE-UI T6).
     */
    public function scopeList(?string $search = null)
    {
        $builder = $this->orderBy('name', 'ASC');

        if ($search) {
            $builder->groupStart()
                ->like('name', $search)
                ->orLike('phone', $search)
                ->orLike('email', $search)
                ->groupEnd();
        }

        return $builder;
    }
}