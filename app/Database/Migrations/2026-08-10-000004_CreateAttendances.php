<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAttendances extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'nama'                  => ['type' => 'VARCHAR', 'constraint' => 150],
            'attendance_type'       => ['type' => 'ENUM', 'constraint' => ['CHECK_IN', 'CHECK_OUT']],
            'attendance_date'       => ['type' => 'DATE'],       // ditentukan SERVER
            'attendance_time'       => ['type' => 'TIME'],       // ditentukan SERVER
            'photo_path'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'latitude'              => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'longitude'             => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'accuracy'              => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'company_location_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'distance_from_office'  => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'location_status'       => [
                'type'       => 'ENUM',
                'constraint' => ['INSIDE_AREA', 'OUTSIDE_AREA', 'UNKNOWN'],
                'default'    => 'UNKNOWN',
            ],
            'is_late'               => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'device_info'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ip_address'            => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notes'                 => ['type' => 'TEXT', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Kombinasi untuk validasi duplicate: nama + tanggal + type
        $this->forge->addUniqueKey(['nama', 'attendance_date', 'attendance_type']);
        $this->forge->addKey('attendance_date');
        $this->forge->addForeignKey('company_location_id', 'company_locations', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('attendances');
    }

    public function down()
    {
        $this->forge->dropTable('attendances', true);
    }
}
