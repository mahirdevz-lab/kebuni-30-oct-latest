<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $superAdminRoleId = $this->db->table('roles')->where('slug', 'super-admin')->get()->getRow('id');

        // PENTING: ganti password ini segera setelah login pertama kali.
        $this->db->table('users')->insert([
            'role_id'       => $superAdminRoleId,
            'full_name'     => 'Super Administrator',
            'username'      => 'superadmin',
            'email'         => 'superadmin@backoffice.local',
            'password_hash' => password_hash('ChangeMe123!', PASSWORD_DEFAULT),
            'status'        => 'active',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }
}
