<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFinance extends Migration
{
    public function up()
    {
        // FINANCIAL CATEGORIES
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'type'       => ['type' => 'ENUM', 'constraint' => ['INCOME', 'EXPENSE']],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('financial_categories');

        // FINANCIAL TRANSACTIONS (LEDGER - source of truth arus kas)
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'transaction_date' => ['type' => 'DATE'],
            'type'             => ['type' => 'ENUM', 'constraint' => ['INCOME', 'EXPENSE']],
            'category_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'amount'           => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'description'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'reference_type'   => [
                'type'       => 'ENUM',
                'constraint' => ['SALE', 'MANUAL_INCOME', 'MANUAL_EXPENSE', 'RECEIVABLE_PAYMENT', 'PAYABLE_PAYMENT'],
            ],
            'reference_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'account_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'supplier_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'attachment_path'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Idempotency: satu business event hanya menghasilkan satu financial transaction per type
        $this->forge->addUniqueKey(['reference_type', 'reference_id', 'type']);
        $this->forge->addKey('transaction_date');
        $this->forge->createTable('financial_transactions');

        // RECEIVABLES (PIUTANG)
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'account_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'transaction_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'invoice_number'   => ['type' => 'VARCHAR', 'constraint' => 50],
            'transaction_date' => ['type' => 'DATE'],
            'due_date'         => ['type' => 'DATE', 'null' => true],
            'total_amount'     => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'paid_amount'      => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'remaining_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'status'           => [
                'type'       => 'ENUM',
                'constraint' => ['UNPAID', 'PARTIAL', 'PAID', 'OVERDUE'],
                'default'    => 'UNPAID',
            ],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('receivables');

        // PAYABLES (HUTANG)
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'supplier_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'transaction_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'invoice_number'   => ['type' => 'VARCHAR', 'constraint' => 50],
            'transaction_date' => ['type' => 'DATE'],
            'due_date'         => ['type' => 'DATE', 'null' => true],
            'total_amount'     => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'paid_amount'      => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'remaining_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'status'           => [
                'type'       => 'ENUM',
                'constraint' => ['UNPAID', 'PARTIAL', 'PAID', 'OVERDUE'],
                'default'    => 'UNPAID',
            ],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('payables');
    }

    public function down()
    {
        $this->forge->dropTable('payables', true);
        $this->forge->dropTable('receivables', true);
        $this->forge->dropTable('financial_transactions', true);
        $this->forge->dropTable('financial_categories', true);
    }
}
