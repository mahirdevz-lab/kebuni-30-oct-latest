<?php

namespace App\Services;

/**
 * PIN supervisor untuk otorisasi di layar kasir (diskon manual, override stok).
 * PIN 4-6 digit disimpan sebagai hash; 5 kali salah -> terkunci 15 menit. Semua pemakaian masuk audit log.
 */
class SupervisorAuthService
{
    public const MAX_FAILS   = 5;
    public const LOCK_MINUTES = 15;

    /** Apakah user memiliki izin (role super-admin otomatis punya semua). */
    public function userCan(int $userId, string $slug): bool
    {
        $row = db_connect()->query(
            "SELECT 1 FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.status = 'active' AND u.deleted_at IS NULL
               AND (r.slug = 'super-admin' OR EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = r.id AND p.slug = ?))",
            [$userId, $slug]
        )->getRowArray();

        return (bool) $row;
    }

    /** Supervisor yang bisa dipilih di kasir: punya izin (diskon/override) dan sudah menyetel PIN. */
    public function eligible(): array
    {
        return db_connect()->query(
            "SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.status = 'active' AND u.deleted_at IS NULL AND u.pin_hash IS NOT NULL
               AND (r.slug = 'super-admin' OR EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
                    WHERE rp.role_id = r.id AND p.slug IN ('pos.discount', 'pos.override')))
             ORDER BY u.full_name"
        )->getResultArray();
    }

    public function hasPin(int $userId): bool
    {
        return (bool) db_connect()->table('users')->where('id', $userId)->where('pin_hash IS NOT NULL', null, false)->countAllResults();
    }

    /** Menyetel PIN sendiri; wajib password akun. */
    public function setPin(int $userId, string $password, string $pin): array
    {
        if (! preg_match('/^\d{4,6}$/', $pin)) {
            return ['success' => false, 'message' => 'PIN harus 4–6 digit angka.'];
        }
        if (preg_match('/^(\d)\1+$/', $pin) || in_array($pin, ['1234', '12345', '123456', '4321', '0123', '123123'], true)) {
            return ['success' => false, 'message' => 'PIN terlalu mudah ditebak. Pilih angka lain.'];
        }
        $db   = db_connect();
        $user = $db->table('users')->where('id', $userId)->get()->getRowArray();
        if (! $user || ! password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Password akun salah.'];
        }
        if (! $this->userCan($userId, 'pos.discount') && ! $this->userCan($userId, 'pos.override')) {
            return ['success' => false, 'message' => 'Akun Anda tidak berwenang memberi diskon / override.'];
        }
        $db->table('users')->where('id', $userId)->update(['pin_hash' => password_hash($pin, PASSWORD_DEFAULT), 'pin_failed' => 0, 'pin_locked_until' => null]);
        (new AuditLogService())->log('Setel PIN Supervisor', 'pos', 'USER', $userId, 'PIN diperbarui');

        return ['success' => true, 'message' => 'PIN tersimpan.'];
    }

    /**
     * Verifikasi PIN supervisor untuk satu kemampuan ('pos.discount' / 'pos.override').
     * @return array{success:bool, message?:string, user?:array}
     */
    public function verify(int $supervisorId, string $pin, string $ability, ?int $cashierId = null): array
    {
        $db   = db_connect();
        $user = $db->table('users')->where('id', $supervisorId)->where('status', 'active')->where('deleted_at', null)->get()->getRowArray();
        if (! $user || ! $user['pin_hash']) {
            return ['success' => false, 'message' => 'Supervisor tidak ditemukan atau belum menyetel PIN.'];
        }
        if ($user['pin_locked_until'] && strtotime($user['pin_locked_until']) > time()) {
            return ['success' => false, 'message' => 'PIN supervisor terkunci sampai ' . date('H:i', strtotime($user['pin_locked_until'])) . ' karena terlalu sering salah.'];
        }
        if (! password_verify($pin, $user['pin_hash'])) {
            $fails = (int) $user['pin_failed'] + 1;
            $upd   = ['pin_failed' => $fails];
            if ($fails >= self::MAX_FAILS) {
                $upd = ['pin_failed' => 0, 'pin_locked_until' => date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60)];
            }
            $db->table('users')->where('id', $supervisorId)->update($upd);
            (new AuditLogService())->log('PIN Supervisor Salah', 'pos', 'USER', $supervisorId, "percobaan ke-{$fails}" . ($cashierId ? ", di sesi kasir user #{$cashierId}" : ''));

            return ['success' => false, 'message' => $fails >= self::MAX_FAILS
                ? 'PIN salah. Terkunci ' . self::LOCK_MINUTES . ' menit.'
                : 'PIN salah (' . (self::MAX_FAILS - $fails) . ' percobaan tersisa).'];
        }
        if (! $this->userCan($supervisorId, $ability)) {
            return ['success' => false, 'message' => 'Supervisor ini tidak berwenang untuk tindakan tersebut.'];
        }
        $db->table('users')->where('id', $supervisorId)->update(['pin_failed' => 0, 'pin_locked_until' => null]);

        return ['success' => true, 'user' => ['id' => (int) $user['id'], 'full_name' => $user['full_name']]];
    }
}
