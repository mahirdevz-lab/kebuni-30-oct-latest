<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Retur ke supplier (mutasi SUPPLIER_RETURN), promo terjadwal (BOGO & harga bertingkat),
 * pengaturan saran pemesanan ulang, dan izin terkait.
 */
class CreateSupplierReturnsAndPromos extends Migration
{
    public function up()
    {
        $fk = static fn (bool $null = false) => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'return_no'     => ['type' => 'VARCHAR', 'constraint' => 30],
            'supplier_id'   => $fk(),
            'location_id'   => $fk(),
            'receipt_id'    => $fk(true),
            'return_date'   => ['type' => 'DATE'],
            'reason_code'   => ['type' => 'ENUM', 'constraint' => ['RUSAK', 'BUSUK', 'TIDAK_SESUAI', 'KELEBIHAN', 'LAINNYA']],
            'reason'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'cost_value'    => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],   // nilai persediaan keluar (moving average)
            'credit_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],   // nilai yang diakui supplier (informasi)
            'settlement'    => ['type' => 'ENUM', 'constraint' => ['BELUM', 'NOTA_KREDIT', 'REFUND'], 'default' => 'BELUM'],
            'reference_no'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'status'        => ['type' => 'ENUM', 'constraint' => ['POSTED'], 'default' => 'POSTED'],
            'created_by'    => $fk(true),
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('return_no');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('supplier_returns');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'return_id'  => $fk(),
            'product_id' => $fk(),
            'qty'        => ['type' => 'DECIMAL', 'constraint' => '14,3'],
            'unit_cost'  => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'cost_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'ledger_id'  => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('return_id', 'supplier_returns', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('supplier_return_items');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'promo_type' => ['type' => 'ENUM', 'constraint' => ['BOGO', 'TIER']],
            'product_id' => $fk(),
            'start_at'   => ['type' => 'DATETIME'],
            'end_at'     => ['type' => 'DATETIME'],
            'buy_qty'    => ['type' => 'DECIMAL', 'constraint' => '14,3', 'null' => true],   // BOGO: beli N
            'get_qty'    => ['type' => 'DECIMAL', 'constraint' => '14,3', 'null' => true],   // BOGO: gratis M
            'max_sets'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // BOGO: batas kelipatan per transaksi
            'min_qty'    => ['type' => 'DECIMAL', 'constraint' => '14,3', 'null' => true],   // TIER: minimal qty
            'tier_price' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],   // TIER: harga satuan
            'reason'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['ACTIVE', 'CANCELLED'], 'default' => 'ACTIVE'],
            'created_by' => $fk(true),
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['product_id', 'status', 'start_at', 'end_at']);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_promos');

        $this->forge->addColumn('pos_sale_items', [
            'promo_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'markdown_id'],
        ]);

        $now = date('Y-m-d H:i:s');
        foreach (['reorder_cover_days' => '7', 'reorder_lookback_days' => '14'] as $k => $v) {
            if (! $this->db->table('settings')->where('setting_key', $k)->countAllResults()) {
                $this->db->table('settings')->insert(['setting_key' => $k, 'setting_value' => $v, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $this->db->table('permissions')->insert(['name' => 'Retur ke Supplier', 'slug' => 'stock.supplier_return', 'module' => 'stock', 'created_at' => $now, 'updated_at' => $now]);
        $pid = (int) $this->db->table('permissions')->where('slug', 'stock.supplier_return')->get()->getRow('id');
        foreach (['admin-inventori', 'supervisor-toko', 'admin'] as $role) {
            $roleId = (int) $this->db->table('roles')->where('slug', $role)->get()->getRow('id');
            $this->db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $pid]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('pos_sale_items', 'promo_id');
        $this->forge->dropTable('pos_promos', true);
        $this->forge->dropTable('supplier_return_items', true);
        $this->forge->dropTable('supplier_returns', true);
        $this->db->table('settings')->whereIn('setting_key', ['reorder_cover_days', 'reorder_lookback_days'])->delete();
        $this->db->table('permissions')->where('slug', 'stock.supplier_return')->delete();
    }
}
