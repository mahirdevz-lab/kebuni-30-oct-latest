<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'product_code', 'product_name', 'category_id', 'unit',
        'shrinkage_type', 'shrinkage_value', 'status', 'notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public const UNITS = ['Kg', 'Pcs', 'Liter', 'Box', 'Unit', 'Dus', 'Peti'];

    protected $validationRules = [
        'id'     => 'permit_empty|integer',
        'product_code'    => 'required|max_length[30]|is_unique[products.product_code,id,{id}]',
        'product_name'    => 'required|min_length[2]|max_length[150]',
        'category_id'     => 'permit_empty|integer',
        'unit'            => 'required|in_list[Kg,Pcs,Liter,Box,Unit,Dus,Peti]',
        'shrinkage_type'  => 'permit_empty|in_list[PERCENTAGE,FIXED]',
        'shrinkage_value' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'status'          => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    protected $validationMessages = [
        'product_code' => ['is_unique' => 'Product Code sudah digunakan.'],
    ];

    public function scopeList(?string $search = null, ?int $categoryId = null, ?string $status = null)
    {
        $builder = $this->select('products.*, product_categories.name as category_name')
            ->join('product_categories', 'product_categories.id = products.category_id', 'left')
            ->orderBy('products.created_at', 'DESC');

        if ($search) {
            $builder->groupStart()
                ->like('products.product_name', $search)
                ->orLike('products.product_code', $search)
                ->groupEnd();
        }

        if ($categoryId) {
            $builder->where('products.category_id', $categoryId);
        }

        if ($status) {
            $builder->where('products.status', $status);
        }

        return $builder;
    }

    public function activeOptions(): array
    {
        return $this->select('id, product_code, product_name, unit, shrinkage_type, shrinkage_value')
            ->where('status', 'aktif')
            ->orderBy('product_name', 'ASC')
            ->findAll();
    }
}
