<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Perbaikan temuan audit F1 (docs/AUDIT-FINAL-REPORT.md): stock_opnames.stok_aktual tidak bisa membedakan
 * "belum dihitung" dari "habis (0)", sehingga Final Report menghitung seluruh saldo buku sebagai susut.
 *
 * - Kolom dibuat NULL-able; NULL = belum diinput.
 * - Data lama: semua baris saat ini (periode 116, 13 baris) bernilai 0 dan diinput bersamaan dengan stok awal
 *   (belum pernah dihitung), sehingga 0 -> NULL. Bila ada produk yang memang habis, input ulang 0 di Final Report.
 */
class MakeOpnameStokAktualNullable extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE stock_opnames MODIFY stok_aktual DECIMAL(18,2) NULL DEFAULT NULL');
        $this->db->query('UPDATE stock_opnames SET stok_aktual = NULL WHERE stok_aktual = 0');
    }

    public function down()
    {
        $this->db->query('UPDATE stock_opnames SET stok_aktual = 0 WHERE stok_aktual IS NULL');
        $this->db->query('ALTER TABLE stock_opnames MODIFY stok_aktual DECIMAL(18,2) NOT NULL DEFAULT 0.00');
    }
}
