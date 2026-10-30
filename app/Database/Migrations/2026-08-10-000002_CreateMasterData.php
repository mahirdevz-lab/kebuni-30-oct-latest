<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMasterData extends Migration
{
    public function up()
    {
        // ACCOUNTS
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama_account'   => ['type' => 'VARCHAR', 'constraint' => 150],
            'alamat'         => ['type' => 'TEXT', 'null' => true],
            'kota'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'nomor_telepon'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'contact_person' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->createTable('accounts');

        // SUPPLIERS
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nama_supplier'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'alamat'         => ['type' => 'TEXT', 'null' => true],
            'kota'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'nomor_telepon'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'contact_person' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->createTable('suppliers');

        // COMPANY LOCATIONS (untuk geofence absensi)
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'latitude'     => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'longitude'    => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'radius_meter' => ['type' => 'INT', 'constraint' => 11, 'default' => 100],
            'status'       => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('company_locations');

        // COMPANY PROFILE
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_name'=> ['type' => 'VARCHAR', 'constraint' => 150],
            'logo_path'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'address'     => ['type' => 'TEXT', 'null' => true],
            'phone'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('company_profiles');
    }

    public function down()
    {
        $this->forge->dropTable('company_profiles', true);
        $this->forge->dropTable('company_locations', true);
        $this->forge->dropTable('suppliers', true);
        $this->forge->dropTable('accounts', true);
    }
}
