<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderItemModel extends Model
{
    protected $table            = 'order_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'order_id',
        'product_id',
        'qty',
        'transport_route_id',
        'purchase_price',
        'labour_cost',
        'transport_cost',
        'packing_cost',
        'shrinkage_cost',
        'cogs_sk',
        'cogs_destination',
        'recommended_price',
        'selling_price',
        'subtotal',
        'profit',
    ];
}