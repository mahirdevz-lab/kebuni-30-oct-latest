<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Satu piutang/hutang bisa dibayar berkali-kali (cicilan), tetapi unique key lama
 * (reference_type, reference_id, type) hanya mengizinkan 1 baris ledger per referensi,
 * sehingga cicilan ke-2 dst. tidak tercatat. Tambah reference_seq (nomor urut pembayaran)
 * ke unique key; SALE/manual tetap seq 0 sehingga idempotensinya tidak berubah.
 */
class AddReferenceSeqToFinancialTransactions extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE `financial_transactions` ADD COLUMN `reference_seq` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `reference_id`');
        $this->db->query('ALTER TABLE `financial_transactions` DROP INDEX `reference_type_reference_id_type`');
        $this->db->query('ALTER TABLE `financial_transactions` ADD UNIQUE KEY `reference_type_reference_id_type_seq` (`reference_type`, `reference_id`, `type`, `reference_seq`)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `financial_transactions` DROP INDEX `reference_type_reference_id_type_seq`');
        $this->db->query('ALTER TABLE `financial_transactions` ADD UNIQUE KEY `reference_type_reference_id_type` (`reference_type`, `reference_id`, `type`)');
        $this->db->query('ALTER TABLE `financial_transactions` DROP COLUMN `reference_seq`');
    }
}
