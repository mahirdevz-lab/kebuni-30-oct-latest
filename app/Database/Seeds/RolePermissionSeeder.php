<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // ROLES
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Akses penuh ke seluruh sistem', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Admin',       'slug' => 'admin',       'description' => 'Mengelola operasional harian',   'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Finance',     'slug' => 'finance',     'description' => 'Mengelola modul keuangan',        'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HR',          'slug' => 'hr',          'description' => 'Mengelola absensi & karyawan',    'created_at' => $now, 'updated_at' => $now],
        ];
        
        $this->db->table('roles')->insertBatch($roles);

        // PERMISSIONS (module.action)
$modules = ['dashboard', 'account', 'sales', 'supplier', 'product', 'price_period', 'pricing_tier', 'payment_term', 'sales_order', 'transaction', 'attendance', 'finance', 'report', 'user', 'setting', 'company_profile'];        $actions = ['view', 'create', 'update', 'delete'];

        $permissions = [];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'name'       => ucfirst($action) . ' ' . ucfirst(str_replace('_', ' ', $module)),
                    'slug'       => "{$module}.{$action}",
                    'module'     => $module,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $this->db->table('permissions')->insertBatch($permissions);

        // MAP: Super Admin gets ALL permissions
        $superAdminId = $this->db->table('roles')->where('slug', 'super-admin')->get()->getRow('id');
        $allPermissionIds = $this->db->table('permissions')->select('id')->get()->getResultArray();
        $rolePermissions = [];
        foreach ($allPermissionIds as $p) {
            $rolePermissions[] = ['role_id' => $superAdminId, 'permission_id' => $p['id']];
        }
        $this->db->table('role_permissions')->insertBatch($rolePermissions);

        // MAP: Finance role -> finance, report, dashboard modules
        $financeId = $this->db->table('roles')->where('slug', 'finance')->get()->getRow('id');
        $financePermIds = $this->db->table('permissions')
            ->whereIn('module', ['finance', 'report', 'dashboard'])
            ->get()->getResultArray();
        $rows = [];
        foreach ($financePermIds as $p) {
            $rows[] = ['role_id' => $financeId, 'permission_id' => $p['id']];
        }
        $this->db->table('role_permissions')->insertBatch($rows);

        // MAP: HR role -> attendance, report, dashboard modules
        $hrId = $this->db->table('roles')->where('slug', 'hr')->get()->getRow('id');
        $hrPermIds = $this->db->table('permissions')
            ->whereIn('module', ['attendance', 'report', 'dashboard'])
            ->get()->getResultArray();
        $rows = [];
        foreach ($hrPermIds as $p) {
            $rows[] = ['role_id' => $hrId, 'permission_id' => $p['id']];
        }
        $this->db->table('role_permissions')->insertBatch($rows);

        // MAP: Admin role -> everything except user & setting (system administration)
        $adminId = $this->db->table('roles')->where('slug', 'admin')->get()->getRow('id');
        $adminPermIds = $this->db->table('permissions')
            ->whereNotIn('module', ['user', 'setting'])
            ->get()->getResultArray();
        $rows = [];
        foreach ($adminPermIds as $p) {
            $rows[] = ['role_id' => $adminId, 'permission_id' => $p['id']];
        }
        $this->db->table('role_permissions')->insertBatch($rows);
    }
}
