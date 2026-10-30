<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Snapshot OPEX per item pada saat Taking Order/PO dibuat,
 * mengikuti pola field snapshot lain (packing_cost, labour_cost, dst.)
 * di sales_order_items agar histori harga tetap immutable.
 */
class AddOpexToSalesOrderItems extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales_order_items', [
            'opex_ho'                => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0, 'after' => 'shrinkage_cost'],
            'opex_sukabumi'          => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0, 'after' => 'opex_ho'],
            'opex_destination'       => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0, 'after' => 'opex_sukabumi'],
            'via_sukabumi_warehouse' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'opex_destination'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sales_order_items', ['opex_ho', 'opex_sukabumi', 'opex_destination', 'via_sukabumi_warehouse']);
    }
}
