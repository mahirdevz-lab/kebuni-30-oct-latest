<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Taking Order / PO — menyesuaikan PriceCalculationService versi terbaru:
 * - Labour dihitung di level ORDER (headcount x rate per labour_rate_id, dialokasikan rata ke semua item).
 * - Packing dapat dipilih manual per item (packing_ids) atau fallback ke recipe product.
 * - Transport route dipilih per ITEM (bukan header), sesuai simulator.
 *
 * sales_orders.labour_counts_snapshot & sales_order_items.packing_breakdown_json menyimpan
 * detail JSON agar PO lama tetap utuh walau master/Price Period berubah di kemudian hari.
 */
class CreateSalesOrders extends Migration
{
    public function up()
    {
        // SALES ORDERS (header)
        $this->forge->addField([
            'id'                       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'order_no'                 => ['type' => 'VARCHAR', 'constraint' => 30],
            'order_date'               => ['type' => 'DATE'],
            'account_id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'price_period_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'payment_term_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status'                   => [
                'type'       => 'ENUM',
                'constraint' => ['Draft', 'Submitted', 'Approved', 'Processing', 'Completed', 'Cancelled'],
                'default'    => 'Draft',
            ],
            'notes'                    => ['type' => 'TEXT', 'null' => true],
            'labour_counts_snapshot'   => ['type' => 'TEXT', 'null' => true],
            'total_qty'                => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'total_labour_cost'        => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'labour_cost_per_kg'       => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'total_cogs'               => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'grand_total'              => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'grand_total_cash'         => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'estimated_profit'         => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'estimated_profit_percent' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'created_by'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'approved_by'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'approved_at'              => ['type' => 'DATETIME', 'null' => true],
            'created_at'               => ['type' => 'DATETIME', 'null' => true],
            'updated_at'               => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'               => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('order_no');
        $this->forge->addKey('status');
        $this->forge->addKey('order_date');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('price_period_id', 'price_periods', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('payment_term_id', 'payment_terms', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('sales_orders');

        // SALES ORDER ITEMS (price snapshot per item)
        $this->forge->addField([
            'id'                         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'sales_order_id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'product_id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'product_code_snapshot'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'product_name_snapshot'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'unit_snapshot'              => ['type' => 'VARCHAR', 'constraint' => 20],
            'transport_route_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'destination_label_snapshot' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'qty'                        => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'purchase_price'             => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'packing_cost'               => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'total_packing_pcs'          => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'packing_breakdown_json'     => ['type' => 'TEXT', 'null' => true],
            'labour_cost'                => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'shrinkage_cost'             => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'cogs_sk'                    => ['type' => 'DECIMAL', 'constraint' => '18,4'],
            'transport_cost'             => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'cogs_destination'           => ['type' => 'DECIMAL', 'constraint' => '18,4'],
            'tier_name_snapshot'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'markup_percent'             => ['type' => 'DECIMAL', 'constraint' => '10,4'],
            'cash_price'                 => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'interest_amount'            => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'final_price'                => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'manual_price'               => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'selling_price'              => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'subtotal_cash'              => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'subtotal_final'             => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'notes'                      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'                 => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                 => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sales_order_id');
        $this->forge->addForeignKey('sales_order_id', 'sales_orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('sales_order_items');
    }

    public function down()
    {
        $this->forge->dropTable('sales_order_items', true);
        $this->forge->dropTable('sales_orders', true);
    }
}
