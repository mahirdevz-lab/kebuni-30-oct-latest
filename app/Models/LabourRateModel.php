<?php

namespace App\Models;

use CodeIgniter\Model;

class LabourRateModel extends Model
{
    protected $table            = 'labour_rates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'code', 'name', 'gender', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'code'   => 'required|min_length[2]|max_length[50]|is_unique[labour_rates.code,id,{id}]',
        'name'   => 'required|min_length[3]|max_length[100]',
        'gender' => 'required|in_list[MALE,FEMALE,OTHER]',
        'notes'  => 'permit_empty|max_length[255]',
    ];

    /**
     * Scope list untuk data master kuli dengan pencarian
     */
    public function scopeList(?string $search = null)
    {
        $builder = $this->orderBy('id', 'ASC');

        if ($search) {
            $builder->groupStart()
                ->like('code', $search)
                ->orLike('name', $search)
                ->groupEnd();
        }

        return $builder;
    }
}