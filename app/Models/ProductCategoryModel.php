<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductCategoryModel extends Model
{
    protected $table         = 'product_categories';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'status'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'id'     => 'permit_empty|integer',
        'name'   => 'required|min_length[2]|max_length[100]|is_unique[product_categories.name,id,{id}]',
        'status' => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    protected $validationMessages = [
        'name' => ['is_unique' => 'Kategori dengan nama ini sudah ada.'],
    ];

    public function activeOptions(): array
    {
        return $this->select('id, name')->where('status', 'aktif')->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Scope daftar + filter untuk pagination (BUG-1/BUG-3 PRD-BACKOFFICE-UI T2).
     */
    public function scopeList(?string $search = null, ?string $status = null)
    {
        $builder = $this->orderBy('name', 'ASC');

        if ($search) {
            $builder->like('name', $search);
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder;
    }
}
