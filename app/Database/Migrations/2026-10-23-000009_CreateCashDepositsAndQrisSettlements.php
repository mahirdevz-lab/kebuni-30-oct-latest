<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Setoran Kas Kasir & settlement QRIS (PRD A4) + kontrol kas awal saat buka toko.
 */
class CreateCashDepositsAndQrisSettlements extends Migration
{
    public function up()
    {
        $fk    = static fn (bool $null = false, string $t = 'INT') => ['type' => $t, 'constraint' => $t === 'INT' ? 11 : 20, 'unsigned' => true, 'null' => $null];
        $money = ['type' => 'DECIMAL', 'constraint' => '18,2'];

        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'deposit_no'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'from_account_id'  => $fk(),
            'to_account_id'    => $fk(),
            'amount'           => $money,
            'daily_closing_id' => $fk(true),
            'timing'           => ['type' => 'ENUM', 'constraint' => ['DURING_DAY', 'AFTER_EOD']],
            'business_date'    => ['type' => 'DATE'],
            'reference_no'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'proof_path'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notes'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'transfer_out_id'  => $fk(true, 'BIGINT'),
            'transfer_in_id'   => $fk(true, 'BIGINT'),
            'created_by'       => $fk(true),
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('deposit_no');
        $this->forge->addKey('business_date');
        $this->forge->addForeignKey('daily_closing_id', 'pos_daily_closings', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_cash_deposits');

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'settlement_no'   => ['type' => 'VARCHAR', 'constraint' => 30],
            'settlement_date' => ['type' => 'DATE'],
            'clearing_account_id' => $fk(),
            'bank_account_id' => $fk(),
            'gross_amount'    => $money,   // total pembayaran QRIS yang disettle
            'net_amount'      => $money,   // dana yang masuk ke bank
            'mdr_amount'      => $money,   // bruto - bersih
            'payment_count'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'reference_no'    => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'proof_path'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notes'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'transfer_out_id' => $fk(true, 'BIGINT'),
            'transfer_in_id'  => $fk(true, 'BIGINT'),
            'mdr_txn_id'      => $fk(true, 'BIGINT'),
            'created_by'      => $fk(true),
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('settlement_no');
        $this->forge->createTable('pos_qris_settlements');

        $this->forge->addColumn('pos_payments', [
            'settlement_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'financial_transaction_id'],
        ]);
        $this->db->query('ALTER TABLE pos_payments ADD KEY pos_payments_settlement_idx (settlement_id)');

        $this->forge->addColumn('pos_daily_closings', [
            'expected_opening_cash' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true, 'after' => 'opening_cash'],
        ]);

        $now = date('Y-m-d H:i:s');
        foreach (['pos.cash_deposit' => 'Setoran Kas Kasir', 'pos.qris_settle' => 'Settlement QRIS'] as $slug => $name) {
            $this->db->table('permissions')->insert(['name' => $name, 'slug' => $slug, 'module' => 'pos', 'created_at' => $now, 'updated_at' => $now]);
        }
        $map = ['supervisor-toko' => ['pos.cash_deposit'], 'admin' => ['pos.cash_deposit', 'pos.qris_settle'], 'finance' => ['pos.cash_deposit', 'pos.qris_settle']];
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
        $this->forge->dropTable('pos_qris_settlements', true);
        $this->forge->dropTable('pos_cash_deposits', true);
        $this->db->query('ALTER TABLE pos_payments DROP KEY pos_payments_settlement_idx');
        $this->forge->dropColumn('pos_payments', 'settlement_id');
        $this->forge->dropColumn('pos_daily_closings', 'expected_opening_cash');
        $this->db->table('permissions')->whereIn('slug', ['pos.cash_deposit', 'pos.qris_settle'])->delete();
    }
}
