<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Diskon manual (dengan otorisasi supervisor) & PIN supervisor di layar kasir. */
class AddDiscountAndSupervisorPin extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'pin_hash'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'pin_failed'       => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'pin_locked_until' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addColumn('pos_sales', [
            'discount_type'        => ['type' => 'ENUM', 'constraint' => ['PCT', 'AMT'], 'null' => true, 'after' => 'discount_total'],
            'discount_value'       => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true, 'after' => 'discount_type'],
            'discount_reason'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'discount_value'],
            'discount_approved_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'discount_reason'],
        ]);
        $this->forge->addColumn('pos_sale_items', [
            'discount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0, 'after' => 'markdown_id'],
        ]);

        $now = date('Y-m-d H:i:s');
        $this->db->table('permissions')->insert(['name' => 'Beri Diskon Manual POS', 'slug' => 'pos.discount', 'module' => 'pos', 'created_at' => $now, 'updated_at' => $now]);
        $pid = (int) $this->db->table('permissions')->where('slug', 'pos.discount')->get()->getRow('id');
        foreach (['supervisor-toko', 'admin'] as $role) {
            $roleId = (int) $this->db->table('roles')->where('slug', $role)->get()->getRow('id');
            $this->db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $pid]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('pos_sale_items', 'discount');
        $this->forge->dropColumn('pos_sales', ['discount_type', 'discount_value', 'discount_reason', 'discount_approved_by']);
        $this->forge->dropColumn('users', ['pin_hash', 'pin_failed', 'pin_locked_until']);
        $this->db->table('permissions')->where('slug', 'pos.discount')->delete();
    }
}
