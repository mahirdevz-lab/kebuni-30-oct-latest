<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menyelaraskan ENUM financial_transactions.reference_type dengan kode yang sudah ada,
 * lalu menambah tipe referensi untuk POS.
 *
 * Latar belakang: FinanceService::transferFunds() dan FinancialTransactionModel sudah memakai
 * TRANSFER_OUT / TRANSFER_IN, tetapi ENUM di DB tidak memuatnya. Karena sql_mode tidak strict,
 * MySQL menyimpan nilai tsb sebagai string kosong ''. Migration ini:
 *   1. memperluas ENUM,
 *   2. memperbaiki baris pemindahan dana yang tersimpan '' (dikenali dari kategori "Pemindahan Dana"
 *      -- EXPENSE => TRANSFER_OUT, INCOME => TRANSFER_IN),
 *   3. menambah POS_PAYMENT (penerimaan pembayaran POS), QRIS_MDR (biaya MDR), QRIS_SETTLEMENT.
 */
class FixFinanceReferenceTypesForPos extends Migration
{
    private const BASE = ['SALE', 'MANUAL_INCOME', 'MANUAL_EXPENSE', 'RECEIVABLE_PAYMENT', 'PAYABLE_PAYMENT'];
    private const NEW  = ['TRANSFER_OUT', 'TRANSFER_IN', 'POS_PAYMENT', 'POS_REFUND', 'QRIS_MDR', 'QRIS_SETTLEMENT'];

    public function up()
    {
        $this->setEnum(array_merge(self::BASE, self::NEW));

        $catId = $this->db->table('financial_categories')->select('id')->where('name', 'Pemindahan Dana')->get()->getRow('id');

        if ($catId) {
            $this->db->table('financial_transactions')
                ->where('reference_type', '')->where('category_id', $catId)->where('type', 'EXPENSE')
                ->update(['reference_type' => 'TRANSFER_OUT']);

            $this->db->table('financial_transactions')
                ->where('reference_type', '')->where('category_id', $catId)->where('type', 'INCOME')
                ->update(['reference_type' => 'TRANSFER_IN']);
        }

        $now = date('Y-m-d H:i:s');
        foreach ([['Biaya MDR QRIS', 'EXPENSE'], ['Refund Penjualan POS', 'EXPENSE']] as [$name, $type]) {
            $exists = $this->db->table('financial_categories')->where('name', $name)->where('type', $type)->countAllResults();
            if (! $exists) {
                $this->db->table('financial_categories')->insert(['name' => $name, 'type' => $type, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down()
    {
        // Baris yang sudah memakai tipe baru dikembalikan ke '' agar ENUM lama dapat diterapkan.
        $this->db->table('financial_transactions')->whereIn('reference_type', self::NEW)->update(['reference_type' => '']);
        $this->setEnum(self::BASE);
    }

    private function setEnum(array $values): void
    {
        $list = "'" . implode("','", $values) . "'";
        $this->db->query("ALTER TABLE financial_transactions MODIFY reference_type ENUM($list) NOT NULL");
    }
}
