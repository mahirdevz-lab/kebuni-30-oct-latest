<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Retur pelanggan & koreksi metode pembayaran (PRD FR-02: retur/refund/koreksi butuh izin & audit trail;
 * Business Rules: transaksi yang diposting tidak dihapus, koreksi lewat transaksi koreksi).
 */
class CreatePosReturns extends Migration
{
    private const FT_ENUM_OLD = ['SALE', 'MANUAL_INCOME', 'MANUAL_EXPENSE', 'RECEIVABLE_PAYMENT', 'PAYABLE_PAYMENT', 'TRANSFER_OUT', 'TRANSFER_IN', 'POS_PAYMENT', 'POS_REFUND', 'QRIS_MDR', 'QRIS_SETTLEMENT'];

    public function up()
    {
        $fk    = static fn (bool $null = false, string $t = 'INT') => ['type' => $t, 'constraint' => $t === 'INT' ? 11 : 20, 'unsigned' => true, 'null' => $null];
        $money = ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0];

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'return_no'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'sale_id'          => $fk(false, 'BIGINT'),
            'location_id'      => $fk(),
            'return_date'      => ['type' => 'DATETIME'],
            'daily_closing_id' => $fk(true),
            'reason_code'      => ['type' => 'ENUM', 'constraint' => ['SALAH_PRODUK', 'RUSAK', 'TIDAK_SESUAI', 'BATAL_BELI', 'LAINNYA']],
            'reason'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'return_value'     => $money,   // nilai barang diretur (harga dibayar)
            'refund_amount'    => $money,   // uang yang dikembalikan
            'due_reduction'    => $money,   // pengurang sisa tagihan (bila penjualan belum lunas)
            'cost_value'       => $money,   // HPP yang dibalik
            'refund_method'    => ['type' => 'ENUM', 'constraint' => ['CASH', 'TRANSFER', 'NONE'], 'default' => 'CASH'],
            'cash_account_id'  => $fk(true),
            'financial_transaction_id' => $fk(true, 'BIGINT'),
            'status'           => ['type' => 'ENUM', 'constraint' => ['POSTED'], 'default' => 'POSTED'],
            'created_by'       => $fk(true),
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('return_no');
        $this->forge->addKey('sale_id');
        $this->forge->addKey('return_date');
        $this->forge->addForeignKey('sale_id', 'pos_sales', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_returns');

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'return_id'        => $fk(),
            'sale_item_id'     => $fk(false, 'BIGINT'),
            'product_id'       => $fk(),
            'qty'              => ['type' => 'DECIMAL', 'constraint' => '14,3'],
            'unit_price'       => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'value'            => $money,
            'unit_cost'        => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0], // biaya pokok asli saat jual
            'cost_value'       => $money,
            'item_condition'   => ['type' => 'ENUM', 'constraint' => ['RESTOCK', 'DAMAGED']],
            'ledger_id'        => $fk(true, 'BIGINT'),
            'broken_report_id' => $fk(true),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('return_id');
        $this->forge->addKey('sale_item_id');
        $this->forge->addForeignKey('return_id', 'pos_returns', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('sale_item_id', 'pos_sale_items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_return_items');

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'payment_id'      => $fk(false, 'BIGINT'),
            'sale_id'         => $fk(false, 'BIGINT'),
            'from_method'     => ['type' => 'ENUM', 'constraint' => ['CASH', 'QRIS']],
            'to_method'       => ['type' => 'ENUM', 'constraint' => ['CASH', 'QRIS']],
            'from_account_id' => $fk(),
            'to_account_id'   => $fk(),
            'amount'          => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'business_date'   => ['type' => 'DATE'],
            'reason'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'transfer_out_id' => $fk(true, 'BIGINT'),
            'transfer_in_id'  => $fk(true, 'BIGINT'),
            'created_by'      => $fk(true),
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('payment_id'); // satu pembayaran hanya bisa dikoreksi sekali
        $this->forge->addForeignKey('payment_id', 'pos_payments', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_payment_corrections');

        $this->forge->addColumn('pos_payments', [
            'corrected_method' => ['type' => 'ENUM', 'constraint' => ['CASH', 'QRIS'], 'null' => true, 'after' => 'method'],
        ]);

        $this->setFtEnum(array_merge(self::FT_ENUM_OLD, ['POS_RETURN_REFUND']));

        // Permissions
        $now = date('Y-m-d H:i:s');
        foreach (['pos.return' => 'Retur Penjualan POS', 'pos.payment_correct' => 'Koreksi Metode Pembayaran POS'] as $slug => $name) {
            $this->db->table('permissions')->insert(['name' => $name, 'slug' => $slug, 'module' => 'pos', 'created_at' => $now, 'updated_at' => $now]);
        }
        $map = ['supervisor-toko' => ['pos.return', 'pos.payment_correct'], 'admin' => ['pos.return', 'pos.payment_correct'], 'finance' => ['pos.payment_correct']];
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
        $this->forge->dropTable('pos_payment_corrections', true);
        $this->forge->dropTable('pos_return_items', true);
        $this->forge->dropTable('pos_returns', true);
        $this->forge->dropColumn('pos_payments', 'corrected_method');
        $this->db->table('financial_transactions')->where('reference_type', 'POS_RETURN_REFUND')->update(['reference_type' => '']);
        $this->setFtEnum(self::FT_ENUM_OLD);
        $this->db->table('permissions')->whereIn('slug', ['pos.return', 'pos.payment_correct'])->delete();
    }

    private function setFtEnum(array $values): void
    {
        $this->db->query("ALTER TABLE financial_transactions MODIFY reference_type ENUM('" . implode("','", $values) . "') NOT NULL");
    }
}
