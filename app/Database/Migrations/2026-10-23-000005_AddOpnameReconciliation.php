<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Prosedur opname dari owner (2026-10-23): blind count -> rekonsiliasi -> hitung ulang selisih besar -> konfirmasi -> posting.
 * - stock_opname_counts : riwayat setiap input hitung per putaran.
 * - kolom rekonsiliasi pada stock_opname_lines & putaran pada stock_opname_sessions.
 * - setting ambang selisih besar & ambang risiko shrinkage untuk matriks produk.
 */
class AddOpnameReconciliation extends Migration
{
    private const SETTINGS = [
        'opname_variance_pct'     => '5',     // selisih besar bila |selisih| >= 5% dari saldo buku
        'opname_variance_value'   => '50000', // ... atau |nilai selisih| >= Rp50.000
        'shrinkage_high_risk_pct' => '5',     // produk "high risk" bila penyusutan >= 5% dari persediaan keluar
    ];

    public function up()
    {
        $this->forge->addColumn('stock_opname_sessions', [
            'count_round' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1, 'after' => 'status'],
        ]);

        $this->forge->addColumn('stock_opname_lines', [
            'count_round'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0, 'after' => 'counted_at'],
            'added_during_count' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'count_round'],
            'recount_requested' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'added_during_count'],
            'variance_flag'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'recount_requested'],
            'resolution'        => ['type' => 'ENUM', 'constraint' => ['CONFIRMED'], 'null' => true, 'after' => 'variance_flag'],
            'resolution_reason' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'resolution'],
            'resolution_note'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'resolution_reason'],
            'resolved_by'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'resolution_note'],
            'resolved_at'       => ['type' => 'DATETIME', 'null' => true, 'after' => 'resolved_by'],
        ]);

        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'session_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'line_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'round'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'counted_qty' => ['type' => 'DECIMAL', 'constraint' => '14,3'],
            'note'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'counted_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'counted_at'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['line_id', 'round']);
        $this->forge->addForeignKey('session_id', 'stock_opname_sessions', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('line_id', 'stock_opname_lines', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('stock_opname_counts');

        $now = date('Y-m-d H:i:s');
        foreach (self::SETTINGS as $k => $v) {
            if (! $this->db->table('settings')->where('setting_key', $k)->countAllResults()) {
                $this->db->table('settings')->insert(['setting_key' => $k, 'setting_value' => $v, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // Data lama (bila ada): hasil hitung yang sudah tersimpan dianggap putaran 1.
        $this->db->query('UPDATE stock_opname_lines SET count_round = 1 WHERE counted_qty IS NOT NULL');
        $this->db->query('INSERT INTO stock_opname_counts (session_id, line_id, round, counted_qty, note, counted_by, counted_at)
                          SELECT session_id, id, 1, counted_qty, note, counted_by, COALESCE(counted_at, NOW()) FROM stock_opname_lines WHERE counted_qty IS NOT NULL');
    }

    public function down()
    {
        $this->forge->dropTable('stock_opname_counts', true);
        $this->forge->dropColumn('stock_opname_lines', ['count_round', 'added_during_count', 'recount_requested', 'variance_flag', 'resolution', 'resolution_reason', 'resolution_note', 'resolved_by', 'resolved_at']);
        $this->forge->dropColumn('stock_opname_sessions', 'count_round');
        $this->db->table('settings')->whereIn('setting_key', array_keys(self::SETTINGS))->delete();
    }
}
