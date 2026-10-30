<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stok awal toko (mutasi OPENING) saat go-live. Dokumen draft -> diposting sekali; setiap produk hanya boleh
 * punya stok awal bila belum ada mutasi apa pun di ledger (koreksi berikutnya lewat stock opname).
 */
class CreateStockOpenings extends Migration
{
    public function up()
    {
        $fk = static fn (bool $null = false) => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'opening_no'   => ['type' => 'VARCHAR', 'constraint' => 30],
            'location_id'  => $fk(),
            'effective_at' => ['type' => 'DATETIME'],   // waktu cut-off stok awal (mis. go-live)
            'status'       => ['type' => 'ENUM', 'constraint' => ['DRAFT', 'POSTED', 'CANCELLED'], 'default' => 'DRAFT'],
            'notes'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'   => $fk(true),
            'posted_by'    => $fk(true),
            'posted_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('opening_no');
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('stock_openings');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'opening_id'  => $fk(),
            'product_id'  => $fk(),
            'qty'         => ['type' => 'DECIMAL', 'constraint' => '14,3'],
            'unit_cost'   => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'cost_source' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true], // asal harga default (mis. "Price period PP-...")
            'note'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ledger_id'   => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['opening_id', 'product_id']);
        $this->forge->addForeignKey('opening_id', 'stock_openings', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('stock_opening_lines');

        $now = date('Y-m-d H:i:s');
        foreach (['stock.opening' => 'Siapkan Stok Awal', 'stock.opening_post' => 'Posting Stok Awal'] as $slug => $name) {
            $this->db->table('permissions')->insert(['name' => $name, 'slug' => $slug, 'module' => 'stock', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['admin-inventori' => ['stock.opening'], 'admin' => ['stock.opening', 'stock.opening_post']] as $role => $slugs) {
            $roleId = (int) $this->db->table('roles')->where('slug', $role)->get()->getRow('id');
            foreach ($slugs as $s) {
                $pid = (int) $this->db->table('permissions')->where('slug', $s)->get()->getRow('id');
                $this->db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $pid]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('stock_opening_lines', true);
        $this->forge->dropTable('stock_openings', true);
        $this->db->table('permissions')->whereIn('slug', ['stock.opening', 'stock.opening_post'])->delete();
    }
}
