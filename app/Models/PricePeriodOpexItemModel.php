<?php

namespace App\Models;

use CodeIgniter\Model;

class PricePeriodOpexItemModel extends Model
{
    protected $table         = 'price_period_opex_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['price_period_opex_area_id', 'opex_category_id', 'amount', 'notes'];
    protected $useTimestamps = true;

    public function getByArea(int $pricePeriodOpexAreaId): array
    {
        return $this->select('price_period_opex_items.*, opex_categories.code as category_code, opex_categories.name as category_name')
            ->join('opex_categories', 'opex_categories.id = price_period_opex_items.opex_category_id')
            ->where('price_period_opex_area_id', $pricePeriodOpexAreaId)
            ->orderBy('opex_categories.name', 'ASC')
            ->findAll();
    }
}
