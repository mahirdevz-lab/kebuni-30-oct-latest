<?php

namespace App\Models;

use CodeIgniter\Model;

class OpexCategoryModel extends Model
{
    protected $table         = 'opex_categories';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['code', 'name', 'status', 'notes'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'id'     => 'permit_empty|integer',
        'code'   => 'required|max_length[30]|is_unique[opex_categories.code,id,{id}]',
        'name'   => 'required|min_length[2]|max_length[100]',
        'status' => 'permit_empty|in_list[aktif,nonaktif]',
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
        return $this->select('id, code, name')
            ->where('status', 'aktif')
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
