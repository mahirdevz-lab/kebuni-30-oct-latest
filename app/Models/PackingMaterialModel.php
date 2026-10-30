<?php

namespace App\Models;

use CodeIgniter\Model;

class PackingMaterialModel extends Model
{
    protected $table            = 'packing_materials';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = ['code', 'name', 'unit', 'capacity_kg', 'status', 'notes'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'id'     => 'permit_empty|integer',
        'code'        => 'required|max_length[30]|is_unique[packing_materials.code,id,{id}]',
        'name'        => 'required|min_length[2]|max_length[100]',
        'unit'        => 'required|max_length[20]',
        'capacity_kg' => 'permit_empty|decimal|greater_than[0]',
        'status'      => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    protected $validationMessages = [
        'code' => ['is_unique' => 'Code sudah digunakan.'],
    ];

    public function scopeList(?string $search = null, ?string $status = null)
    {
        $builder = $this->orderBy('created_at', 'DESC');

        if ($search) {
            $builder->groupStart()
                ->like('name', $search)
                ->orLike('code', $search)
                ->groupEnd();
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder;
    }

    public function activeOptions(): array
    {
        return $this->select('id, code, name, unit, capacity_kg')
            ->where('status', 'aktif')
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
