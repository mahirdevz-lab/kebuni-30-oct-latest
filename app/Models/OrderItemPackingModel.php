<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderItemPackingModel extends Model
{
    protected $table            = 'order_item_packings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'order_item_id',
        'packing_material_id',
        'qty_pcs',
        'pcs_per_kg_product',
        'price_per_unit',
        'total_cost',
        'is_custom',
    ];
}