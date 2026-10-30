<?php

namespace App\Models;

use CodeIgniter\Model;

class FinancialCategoryModel extends Model
{
    protected $table         = 'financial_categories';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'type'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[100]',
        'type' => 'required|in_list[INCOME,EXPENSE]',
    ];

    public function optionsByType(string $type): array
    {
        return $this->select('id, name')
            ->where('type', $type)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
