<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Broken Stock / known shrinkage (PRD FR-09, FR-11).
 *
 * Draft TIDAK mengurangi stok. Setelah disetujui, mutasi BROKEN diposting ke stock_ledger
 * sehingga saldo buku turun sebelum opname berikutnya -> tidak terhitung ulang sebagai unknown shrinkage.
 */
class CreateBrokenStock extends Migration
{
    public function up()
    {
        $fk = static fn (bool $null = false) => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'report_no'     => ['type' => 'VARCHAR', 'constraint' => 30],
            'location_id'   => $fk(),
            'product_id'    => $fk(),
            'qty'           => ['type' => 'DECIMAL', 'constraint' => '14,3'],
            'unit'          => ['type' => 'VARCHAR', 'constraint' => 20],
            'reason_code'   => ['type' => 'ENUM', 'constraint' => ['BUSUK', 'RUSAK', 'KADALUARSA', 'HAMA', 'LAINNYA']],
            'reason'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'photo_path'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'est_unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0], // estimasi saat lapor (avg cost)
            'est_value'     => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'posted_value'  => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true], // nilai aktual dari ledger saat posting
            'status'        => ['type' => 'ENUM', 'constraint' => ['DRAFT', 'POSTED', 'REJECTED', 'CANCELLED'], 'default' => 'DRAFT'],
            'reported_by'   => $fk(true),
            'reported_at'   => ['type' => 'DATETIME'],
            'approved_by'   => $fk(true),
            'approved_at'   => ['type' => 'DATETIME', 'null' => true],
            'reject_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ledger_id'     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('report_no');
        $this->forge->addKey(['status', 'reported_at']);
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('stock_broken_reports');

        $now = date('Y-m-d H:i:s');
        foreach (['stock.broken' => 'Lapor Broken Stock', 'stock.broken_approve' => 'Setujui Broken Stock'] as $slug => $name) {
            $this->db->table('permissions')->insert(['name' => $name, 'slug' => $slug, 'module' => 'stock', 'created_at' => $now, 'updated_at' => $now]);
        }
        $map = [
            'kasir'           => ['stock.broken'],
            'admin-inventori' => ['stock.broken'],
            'supervisor-toko' => ['stock.broken', 'stock.broken_approve'],
            'admin'           => ['stock.broken', 'stock.broken_approve'],
            'finance'         => [], // laporan shrinkage cukup lewat stock.view yang sudah dimiliki
        ];
        foreach ($map as $role => $slugs) {
            $roleId = (int) $this->db->table('roles')->where('slug', $role)->get()->getRow('id');
            foreach ($slugs as $s) {
                $pid = (int) $this->db->table('permissions')->where('slug', $s)->get()->getRow('id');
                $this->db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $pid]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('stock_broken_reports', true);
        $this->db->table('permissions')->whereIn('slug', ['stock.broken', 'stock.broken_approve'])->delete();
    }
}
