<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Metode bayar baru: TRANSFER (ke rekening bank, default "Bank Utama").
 * Seperti QRIS: menunggu verifikasi supervisor/keuangan; setelah dikonfirmasi langsung masuk ke rekening tujuan
 * (setting pos_transfer_account), tanpa clearing/MDR.
 */
class AddTransferPaymentMethod extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE pos_payments MODIFY method ENUM('CASH','QRIS','TRANSFER') NOT NULL");
        $this->db->query("ALTER TABLE pos_payments MODIFY corrected_method ENUM('CASH','QRIS','TRANSFER') NULL");
        $this->db->query("ALTER TABLE pos_payment_corrections MODIFY from_method ENUM('CASH','QRIS','TRANSFER') NOT NULL");
        $this->db->query("ALTER TABLE pos_payment_corrections MODIFY to_method ENUM('CASH','QRIS','TRANSFER') NOT NULL");

        if (! $this->db->table('settings')->where('setting_key', 'pos_transfer_account')->countAllResults()) {
            $acc = $this->db->table('cash_accounts')->where('name', 'Bank Utama')->get()->getRow('id')
                ?: $this->db->table('cash_accounts')->where('type', 'BANK')->orderBy('id')->get()->getRow('id');
            $now = date('Y-m-d H:i:s');
            $this->db->table('settings')->insert(['setting_key' => 'pos_transfer_account', 'setting_value' => (string) ($acc ?: ''), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down()
    {
        if ($this->db->table('pos_payments')->whereIn('method', ['TRANSFER'])->countAllResults()
            || $this->db->table('pos_payments')->where('corrected_method', 'TRANSFER')->countAllResults()
            || $this->db->table('pos_payment_corrections')->groupStart()->where('from_method', 'TRANSFER')->orWhere('to_method', 'TRANSFER')->groupEnd()->countAllResults()) {
            throw new \RuntimeException('Tidak bisa rollback: sudah ada pembayaran/koreksi dengan metode TRANSFER.');
        }
        $this->db->query("ALTER TABLE pos_payment_corrections MODIFY to_method ENUM('CASH','QRIS') NOT NULL");
        $this->db->query("ALTER TABLE pos_payment_corrections MODIFY from_method ENUM('CASH','QRIS') NOT NULL");
        $this->db->query("ALTER TABLE pos_payments MODIFY corrected_method ENUM('CASH','QRIS') NULL");
        $this->db->query("ALTER TABLE pos_payments MODIFY method ENUM('CASH','QRIS') NOT NULL");
        $this->db->table('settings')->where('setting_key', 'pos_transfer_account')->delete();
    }
}
