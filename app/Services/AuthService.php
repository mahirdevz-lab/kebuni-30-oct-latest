<?php

namespace App\Services;

use App\Models\RoleModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * AuthService
 *
 * Menangani seluruh business logic autentikasi & otorisasi:
 * - Verifikasi kredensial
 * - Membuat session login
 * - Cek permission user saat ini
 *
 * Controller dan Filter cukup memanggil service ini,
 * tidak melakukan pengecekan role/permission secara manual berulang-ulang.
 */
class AuthService
{
    protected UserModel $userModel;
    protected RoleModel $roleModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->roleModel = new RoleModel();
    }

    /**
     * Verifikasi login dan buat session jika valid.
     * Mengembalikan array user (tanpa password) jika sukses, atau null jika gagal.
     */
    public function attempt(string $login, string $password, ?IncomingRequest $request = null): ?array
    {
        $user = $this->userModel->findActiveByLogin($login);

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            return null;
        }

        $permissions = $this->roleModel->getPermissionSlugs((int) $user['role_id']);
        $role        = $this->roleModel->find($user['role_id']);

        session()->set([
            'isLoggedIn'   => true,
            'user_id'      => $user['id'],
            'full_name'    => $user['full_name'],
            'username'     => $user['username'],
            'role_id'      => $user['role_id'],
            'role_slug'    => $role['slug'] ?? null,
            'role_name'    => $role['name'] ?? null,
            'permissions'  => $permissions,
        ]);

        $this->userModel->update($user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $request?->getIPAddress(),
        ]);

        unset($user['password_hash']);

        (new AuditLogService())->log('Login', 'auth', 'USER', (int) $user['id'], 'Login berhasil: ' . $user['username']);

        return $user;
    }

    public function logout(): void
    {
        (new AuditLogService())->log('Logout', 'auth', 'USER', (int) session()->get('user_id'), 'Logout');
        session()->destroy();
    }

    public function check(): bool
    {
        return (bool) session()->get('isLoggedIn');
    }

    public function user(): ?array
    {
        if (! $this->check()) {
            return null;
        }

        return [
            'id'        => session()->get('user_id'),
            'full_name' => session()->get('full_name'),
            'username'  => session()->get('username'),
            'role_id'   => session()->get('role_id'),
            'role_slug' => session()->get('role_slug'),
            'role_name' => session()->get('role_name'),
        ];
    }

    /**
     * Cek apakah user yang sedang login memiliki permission tertentu.
     * Contoh slug: 'account.create', 'finance.view'
     */
    public function hasPermission(string $slug): bool
    {
        if (! $this->check()) {
            return false;
        }

        // Super Admin selalu punya akses penuh
        if (session()->get('role_slug') === 'super-admin') {
            return true;
        }

        $permissions = session()->get('permissions') ?? [];

        return in_array($slug, $permissions, true);
    }

    public function hasRole(string ...$roleSlugs): bool
    {
        return $this->check() && in_array(session()->get('role_slug'), $roleSlugs, true);
    }
}
