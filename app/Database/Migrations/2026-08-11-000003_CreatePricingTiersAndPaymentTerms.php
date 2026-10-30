<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePricingTiersAndPaymentTerms extends Migration
{
    public function up()
    {
        // PRICING TIERS - markup berdasarkan volume order, configurable & historical
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 100], // "Tier 1", "1-499 Kg", dst.
            'min_qty'        => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'max_qty'        => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true], // null = tanpa batas atas (mis. 5000+)
            'markup_percent' => ['type' => 'DECIMAL', 'constraint' => '10,4'],
            'start_date'     => ['type' => 'DATE', 'null' => true],
            'end_date'       => ['type' => 'DATE', 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'notes'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->createTable('pricing_tiers');

        // PAYMENT TERMS - cash / tempo dengan bunga majemuk harian
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'                   => ['type' => 'VARCHAR', 'constraint' => 100], // Cash, Tempo 7 Hari, dst.
            'days'                   => ['type' => 'INT', 'constraint' => 5, 'default' => 0],
            'daily_interest_percent' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0],
            'status'                 => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'notes'                  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->createTable('payment_terms');
    }

    public function down()
    {
        $this->forge->dropTable('payment_terms', true);
        $this->forge->dropTable('pricing_tiers', true);
    }
}
