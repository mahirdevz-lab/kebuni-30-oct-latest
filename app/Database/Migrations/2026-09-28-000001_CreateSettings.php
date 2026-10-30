<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Pengaturan aplikasi (key-value), dikelola dari menu System > Setting. */
class CreateSettings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'setting_value' => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('setting_key');
        $this->forge->createTable('settings');

        $now = date('Y-m-d H:i:s');
        $this->db->table('settings')->insertBatch([
            ['setting_key' => 'company_name',          'setting_value' => 'Back Office',  'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'attendance_late_after', 'setting_value' => '08:00',        'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('settings', true);
    }
}
