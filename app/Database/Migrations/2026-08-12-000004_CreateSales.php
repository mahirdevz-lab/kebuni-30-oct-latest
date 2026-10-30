<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Master Sales (closer) - TERPISAH dari `users` (akun login back office)
 * dan `accounts` (customer). Sales di sini murni identitas untuk keperluan
 * pencatatan komisi/fee closing pada Taking Order, tidak perlu akun login.
 */
class CreateSales extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'notes'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->createTable('sales');
    }

    public function down()
    {
        $this->forge->dropTable('sales', true);
    }
}