<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersAndRoles extends Migration
{
    public function up()
    {
        // ROLES
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 50], // Super Admin, Admin, Finance, HR
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('roles');

        // PERMISSIONS
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 100], // e.g. account.view, account.create
            'module'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('permissions');

        // ROLE_PERMISSIONS (pivot)
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'role_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'permission_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['role_id', 'permission_id']);
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permission_id', 'permissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('role_permissions');

        // USERS
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'role_id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'full_name'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'username'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'             => ['type' => 'VARCHAR', 'constraint' => 150],
            'password_hash'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'remember_token'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'            => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'last_login_at'     => ['type' => 'DATETIME', 'null' => true],
            'last_login_ip'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('email');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('users');

        // ACTIVITY LOG
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'action'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'module'         => ['type' => 'VARCHAR', 'constraint' => 50],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'reference_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'ip_address'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->createTable('activity_logs');
    }

    public function down()
    {
        $this->forge->dropTable('activity_logs', true);
        $this->forge->dropTable('users', true);
        $this->forge->dropTable('role_permissions', true);
        $this->forge->dropTable('permissions', true);
        $this->forge->dropTable('roles', true);
    }
}
