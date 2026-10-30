<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Pelunasan sisa tagihan pelanggan: pembayaran susulan atas penjualan yang sudah ada (idempoten via client_uuid). */
class AddBalancePaymentColumns extends Migration
{
    public function up()
    {
        $this->forge->addColumn('pos_payments', [
            'client_uuid' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'sale_id'],
            'is_balance'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'client_uuid'],
        ]);
        $this->db->query('ALTER TABLE pos_payments ADD UNIQUE KEY pos_payments_client_uuid_unique (client_uuid)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE pos_payments DROP KEY pos_payments_client_uuid_unique');
        $this->forge->dropColumn('pos_payments', ['client_uuid', 'is_balance']);
    }
}
