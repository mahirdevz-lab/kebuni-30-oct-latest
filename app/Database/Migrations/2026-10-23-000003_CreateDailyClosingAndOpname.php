<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Buka/Tutup Kas Harian (EOD, PRD A4) dan Stock Opname setelah toko tutup (PRD FR-10/11, keputusan user 2026-10-23).
 *
 * Alur: buka hari (kas awal laci) -> berjualan -> EOD (kas fisik, selisih; checkout terkunci) -> sesi opname (cut-off = waktu buka sesi,
 * saldo buku di-snapshot) -> hitung fisik dari HP -> submit -> setujui -> OPNAME_ADJUST diposting ke stock_ledger.
 */
class CreateDailyClosingAndOpname extends Migration
{
    public function up()
    {
        $id    = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
        $fk    = static fn (bool $null = false) => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
        $money = static fn (bool $null = false) => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => $null, 'default' => $null ? null : 0];
        $qty   = static fn (bool $null = false) => ['type' => 'DECIMAL', 'constraint' => '14,3', 'null' => $null];

        $this->forge->addField([
            'id'             => $id,
            'location_id'    => $fk(),
            'business_date'  => ['type' => 'DATE'],
            'status'         => ['type' => 'ENUM', 'constraint' => ['OPEN', 'CLOSED'], 'default' => 'OPEN'],
            'opening_cash'   => $money(),
            'opened_by'      => $fk(true),
            'opened_at'      => ['type' => 'DATETIME'],
            // Diisi saat EOD (snapshot; tidak dihitung ulang setelah ditutup)
            'cash_sales'     => $money(true),
            'cash_refunds'   => $money(true),
            'cash_transfers' => $money(true),   // setoran/pindah dana dari Kas Kasir (+ masuk / - keluar)
            'cash_other'     => $money(true),
            'expected_cash'  => $money(true),
            'physical_cash'  => $money(true),
            'difference'     => $money(true),   // fisik - seharusnya
            'qris_confirmed' => $money(true),
            'qris_pending'   => $money(true),
            'qris_pending_count' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'sales_count'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'sales_total'    => $money(true),
            'closed_by'      => $fk(true),
            'closed_at'      => ['type' => 'DATETIME', 'null' => true],
            'notes'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['location_id', 'business_date']);
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_daily_closings');

        // Setiap penjualan terikat ke hari kas (EOD) tempat ia dicatat.
        $this->forge->addColumn('pos_sales', [
            'daily_closing_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'location_id'],
        ]);
        $this->db->query('ALTER TABLE pos_sales ADD CONSTRAINT pos_sales_daily_closing_id_foreign FOREIGN KEY (daily_closing_id) REFERENCES pos_daily_closings (id) ON DELETE RESTRICT ON UPDATE RESTRICT');

        $this->forge->addField([
            'id'               => $id,
            'session_no'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'location_id'      => $fk(),
            'daily_closing_id' => $fk(true),
            'cutoff_at'        => ['type' => 'DATETIME'],
            'status'           => ['type' => 'ENUM', 'constraint' => ['COUNTING', 'SUBMITTED', 'POSTED', 'CANCELLED'], 'default' => 'COUNTING'],
            'opened_by'        => $fk(true),
            'submitted_by'     => $fk(true),
            'submitted_at'     => ['type' => 'DATETIME', 'null' => true],
            'approved_by'      => $fk(true),
            'approved_at'      => ['type' => 'DATETIME', 'null' => true],
            'notes'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('session_no');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('daily_closing_id', 'pos_daily_closings', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('stock_opname_sessions');

        $this->forge->addField([
            'id'          => $id,
            'session_id'  => $fk(),
            'product_id'  => $fk(),
            'book_qty'    => $qty(),                // saldo buku saat cut-off
            'avg_cost'    => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'counted_qty' => $qty(true),            // null = belum dihitung
            'difference'  => $qty(true),            // buku - fisik (positif = fisik kurang)
            'diff_value'  => $money(true),
            'counted_by'  => $fk(true),
            'counted_at'  => ['type' => 'DATETIME', 'null' => true],
            'note'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ledger_id'   => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['session_id', 'product_id']);
        $this->forge->addForeignKey('session_id', 'stock_opname_sessions', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('stock_opname_lines');

        // Permissions & mapping
        $now   = date('Y-m-d H:i:s');
        $perms = [
            'pos.close_day'         => 'Buka/Tutup Kas Harian (EOD)',
            'stock.opname'          => 'Input Hitung Fisik Opname',
            'stock.opname_approve'  => 'Setujui & Posting Opname',
        ];
        foreach ($perms as $slug => $name) {
            $this->db->table('permissions')->insert(['name' => $name, 'slug' => $slug, 'module' => explode('.', $slug)[0], 'created_at' => $now, 'updated_at' => $now]);
        }
        $map = [
            'kasir'           => ['pos.close_day', 'stock.opname'],
            'supervisor-toko' => ['pos.close_day', 'stock.opname', 'stock.opname_approve'],
            'admin-inventori' => ['stock.opname'],
            'admin'           => ['pos.close_day', 'stock.opname', 'stock.opname_approve'],
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
        $this->forge->dropTable('stock_opname_lines', true);
        $this->forge->dropTable('stock_opname_sessions', true);
        if ($this->db->fieldExists('daily_closing_id', 'pos_sales')) {
            $this->db->query('ALTER TABLE pos_sales DROP FOREIGN KEY pos_sales_daily_closing_id_foreign');
            $this->forge->dropColumn('pos_sales', 'daily_closing_id');
        }
        $this->forge->dropTable('pos_daily_closings', true);
        $this->db->table('permissions')->whereIn('slug', ['pos.close_day', 'stock.opname', 'stock.opname_approve'])->delete();
    }
}
