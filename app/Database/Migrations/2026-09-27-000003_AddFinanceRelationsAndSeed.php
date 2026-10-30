<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Melengkapi relasi FK pada tabel finance yang dibuat di 2026-08-10-000005_CreateFinance.php
 * (tabel-tabel tsb sebelumnya dibuat tanpa FK constraint sama sekali), sekaligus
 * seed kategori default supaya form Income/Expense langsung bisa dipakai.
 */
class AddFinanceRelationsAndSeed extends Migration
{
    public function up()
    {
        $this->forge->addForeignKey('category_id', 'financial_categories', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->processIndexes('financial_transactions');

        // transaction_id harus BIGINT UNSIGNED agar cocok dengan financial_transactions.id
        // (dibuat BIGINT karena ledger). Widen dulu sebelum menambah FK, kalau tidak MySQL
        // menolak dengan errno 150 (tipe kolom FK harus sama).
        $this->forge->modifyColumn('receivables', [
            'transaction_id' => ['name' => 'transaction_id', 'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addForeignKey('transaction_id', 'financial_transactions', 'id', 'SET NULL', 'SET NULL');
        $this->forge->processIndexes('receivables');

        $this->forge->modifyColumn('payables', [
            'transaction_id' => ['name' => 'transaction_id', 'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addForeignKey('transaction_id', 'financial_transactions', 'id', 'SET NULL', 'SET NULL');
        $this->forge->processIndexes('payables');

        $now = date('Y-m-d H:i:s');

        $this->db->table('financial_categories')->insertBatch([
            ['name' => 'Penjualan',        'type' => 'INCOME',  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pembayaran Piutang', 'type' => 'INCOME', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pendapatan Lain-lain', 'type' => 'INCOME', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Operasional',       'type' => 'EXPENSE', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sewa',              'type' => 'EXPENSE', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Listrik & Utility', 'type' => 'EXPENSE', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Gaji Karyawan',     'type' => 'EXPENSE', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pembelian ke Supplier', 'type' => 'EXPENSE', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pembayaran Hutang', 'type' => 'EXPENSE', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Biaya Tak Terduga', 'type' => 'EXPENSE', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pengeluaran Lain-lain', 'type' => 'EXPENSE', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down()
    {
        $this->forge->dropForeignKey('financial_transactions', $this->fkName('financial_transactions', 'category_id'));
        $this->forge->dropForeignKey('financial_transactions', $this->fkName('financial_transactions', 'account_id'));
        $this->forge->dropForeignKey('financial_transactions', $this->fkName('financial_transactions', 'supplier_id'));
        $this->forge->dropForeignKey('financial_transactions', $this->fkName('financial_transactions', 'created_by'));
        $this->forge->dropForeignKey('receivables', $this->fkName('receivables', 'transaction_id'));
        $this->forge->dropForeignKey('payables', $this->fkName('payables', 'transaction_id'));

        $this->db->table('financial_categories')->truncate();
    }

    private function fkName(string $table, string $column): string
    {
        return $table . '_' . $column . '_foreign';
    }
}
