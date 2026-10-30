<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSalesCommissionToSalesOrders extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales_orders', [
            'sales_user_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'account_id'],
            'commission_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0, 'after' => 'estimated_profit_percent'],
            'commission_amount'  => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'commission_percent'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sales_orders', ['sales_user_id', 'commission_percent', 'commission_amount']);
    }
}