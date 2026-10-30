<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Daftar akun & role (untuk memeriksa siapa yang punya akses POS). Tidak menampilkan password/PIN. */
class StaffList extends BaseCommand
{
    protected $group       = 'Kebuni POS';
    protected $name        = 'staff:list';
    protected $description = 'Tampilkan akun aktif beserta role dan status PIN.';

    public function run(array $params)
    {
        $rows = db_connect()->query(
            "SELECT u.id, u.username, u.full_name, r.name AS role, u.status, (u.pin_hash IS NOT NULL) AS has_pin, u.last_login_at
             FROM users u JOIN roles r ON r.id = u.role_id WHERE u.deleted_at IS NULL ORDER BY r.id, u.username"
        )->getResultArray();
        $body = array_map(static fn ($r) => [$r['id'], $r['username'], $r['full_name'], $r['role'], $r['status'], $r['has_pin'] ? 'ya' : '-', $r['last_login_at'] ?? '-'], $rows);
        CLI::table($body, ['ID', 'Username', 'Nama', 'Role', 'Status', 'PIN', 'Login terakhir']);

        return EXIT_SUCCESS;
    }
}
