<?php

namespace App\Helpers;

/**
 * Helper sederhana untuk proteksi halaman khusus Admin.
 * Dipakai karena sistem role/permission DB belum difungsikan.
 *
 * Cara pakai di controller:
 *   if (! \App\Helpers\AdminGuard::isAdmin()) {
 *       return redirect()->to(base_url('admin/dashboard'))->with('error', 'Akses ditolak.');
 *   }
 *   // atau untuk AJAX:
 *   if (! \App\Helpers\AdminGuard::isAdmin()) {
 *       return $this->response->setJSON(['success' => false, 'message' => 'Akses ditolak.'])->setStatusCode(403);
 *   }
 */
class AdminGuard
{
    /**
     * Role ID yang dianggap admin: 1 = Super Admin saja.
     * (Admin biasa / Finance / HR tidak boleh akses area khusus.)
     */
    public const ADMIN_ROLE_IDS = [1];

    /**
     * Apakah user yang login adalah Admin / Super Admin.
     */
    public static function isAdmin(): bool
    {
        $roleId = session()->get('role_id');

        if (! $roleId) {
            $userId = session()->get('user_id');
            if (! $userId) {
                return false;
            }
            $row = db_connect()->table('users')->select('role_id')->where('id', $userId)->get()->getRowArray();
            $roleId = $row['role_id'] ?? null;
        }

        return in_array((int) $roleId, self::ADMIN_ROLE_IDS, true);
    }
}
