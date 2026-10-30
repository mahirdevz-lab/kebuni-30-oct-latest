<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Membuat akun staf toko (kasir, supervisor-toko, admin-inventori, finance, admin).
 * Password acak dibuat & ditampilkan SEKALI di terminal ini; tidak disimpan di file/log.
 *
 *   php spark staff:create <username> "<Nama Lengkap>" <role> [email]
 *   php spark staff:list
 */
class StaffCreate extends BaseCommand
{
    protected $group       = 'Kebuni POS';
    protected $name        = 'staff:create';
    protected $description = 'Buat akun staf toko dengan password acak (tampil sekali).';
    protected $usage       = 'staff:create <username> "<Nama Lengkap>" <role> [email]';
    protected $arguments   = [
        'username' => 'Username login (huruf kecil, angka, titik/underscore; min. 3 karakter)',
        'nama'     => 'Nama lengkap (pakai tanda kutip bila ada spasi)',
        'role'     => 'kasir | supervisor-toko | admin-inventori | finance | admin',
        'email'    => 'Opsional; default <username>@kebuni.local',
    ];

    private const ROLES = ['kasir', 'supervisor-toko', 'admin-inventori', 'finance', 'admin'];

    public function run(array $params)
    {
        $username = strtolower(trim((string) ($params[0] ?? '')));
        $name     = trim((string) ($params[1] ?? ''));
        $role     = strtolower(trim((string) ($params[2] ?? '')));
        $email    = strtolower(trim((string) ($params[3] ?? ''))) ?: $username . '@kebuni.local';

        if (! preg_match('/^[a-z0-9._]{3,50}$/', $username) || mb_strlen($name) < 3) {
            CLI::error('Username (a-z, 0-9, titik, underscore, min. 3) dan nama lengkap (min. 3) wajib diisi.');
            CLI::write($this->usage);

            return EXIT_ERROR;
        }
        if (! in_array($role, self::ROLES, true)) {
            CLI::error('Role harus salah satu dari: ' . implode(', ', self::ROLES));

            return EXIT_ERROR;
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            CLI::error('Email tidak valid.');

            return EXIT_ERROR;
        }

        $db   = db_connect();
        $role = $db->table('roles')->where('slug', $role)->get()->getRowArray();
        if (! $role) {
            CLI::error('Role belum ada di database. Jalankan "php spark migrate" dulu.');

            return EXIT_ERROR;
        }
        if ($db->table('users')->groupStart()->where('username', $username)->orWhere('email', $email)->groupEnd()->countAllResults()) {
            CLI::error('Username atau email sudah dipakai.');

            return EXIT_ERROR;
        }

        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'; // tanpa karakter yang mirip (l, I, 1, O, 0)
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $now = date('Y-m-d H:i:s');
        $db->table('users')->insert([
            'role_id' => (int) $role['id'], 'full_name' => $name, 'username' => $username, 'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        $db->table('activity_logs')->insert([
            'user_id' => null, 'action' => 'Buat Akun Staf (CLI)', 'module' => 'user', 'reference_type' => 'USER', 'reference_id' => $id,
            'description' => "{$username} ({$role['name']})", 'created_at' => $now,
        ]);

        CLI::write('Akun dibuat:', 'green');
        CLI::write("  Nama     : {$name}");
        CLI::write("  Username : {$username}");
        CLI::write("  Role     : {$role['name']}");
        CLI::write("  Password : {$password}", 'yellow');
        CLI::write('Catat password ini sekarang — tidak akan ditampilkan lagi. Minta pengguna menggantinya di menu Profil setelah login pertama.');
        if (in_array($role['slug'], ['supervisor-toko', 'admin'], true)) {
            CLI::write('Supervisor/Admin: setelah login, setel PIN di menu POS → PIN Supervisor.', 'cyan');
        }

        return EXIT_SUCCESS;
    }
}
