<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table            = 'orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'order_number',
        'order_date',
        'customer_id',
        'price_period_id',
        'payment_term_id',
        'total_qty',
        'total_cogs',
        'grand_total',
        'estimated_profit',
        'status',
    ];
    protected $useTimestamps    = true;
}