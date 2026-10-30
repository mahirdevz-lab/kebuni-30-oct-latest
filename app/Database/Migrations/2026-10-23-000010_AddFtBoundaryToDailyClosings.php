<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Batas id financial_transactions saat EOD, untuk menghitung mutasi Kas Kasir SETELAH penutupan secara pasti
 * (perbandingan waktu rapuh bila transaksi & EOD terjadi dalam detik yang sama).
 */
class AddFtBoundaryToDailyClosings extends Migration
{
    public function up()
    {
        $this->forge->addColumn('pos_daily_closings', [
            'ft_max_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'closed_at'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('pos_daily_closings', 'ft_max_id');
    }
}
