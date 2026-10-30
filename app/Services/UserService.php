<?php

namespace App\Services;

use App\Models\RoleModel;
use App\Models\UserModel;

class UserService
{
    protected UserModel $model;
    protected RoleModel $roleModel;
    protected AuditLogService $audit;

    public function __construct()
    {
        $this->model     = new UserModel();
        $this->roleModel = new RoleModel();
        $this->audit     = new AuditLogService();
    }

    public function paginate(?string $search, ?int $roleId, ?string $status, int $perPage = 10)
    {
        return $this->model->scopeList($search, $roleId, $status)->paginate($perPage);
    }

    public function pager()
    {
        return $this->model->pager;
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function roles(): array
    {
        return $this->roleModel->orderBy('name', 'ASC')->findAll();
    }

    public function create(array $data): array
    {
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);
        $data['status'] = $data['status'] ?? 'active';

        if (! $this->model->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        $newId = $this->model->getInsertID();
        $this->audit->log('Tambah User', 'user', 'USER', (int) $newId, 'Username ' . ($data['username'] ?? ''));

        return ['success' => true, 'message' => 'User berhasil ditambahkan.', 'id' => $newId];
    }

    public function update(int $id, array $data, int $currentUserId): array
    {
        $user = $this->model->find($id);
        if (! $user) {
            return ['success' => false, 'message' => 'User tidak ditemukan.'];
        }

        if ($id === $currentUserId && ($data['status'] ?? 'active') !== 'active') {
            return ['success' => false, 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.'];
        }

        if ($this->isLastActiveSuperAdmin($user) && ((int) $data['role_id'] !== (int) $user['role_id'] || ($data['status'] ?? 'active') !== 'active')) {
            return ['success' => false, 'message' => 'Super Admin aktif terakhir tidak boleh diubah role/statusnya.'];
        }

        if (! empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        unset($data['password']);

        // 'id' harus ada di data agar placeholder {id} pada rule is_unique terisi (mengabaikan baris user ini sendiri).
        $data['id'] = $id;

        if (! $this->model->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        $this->audit->log('Ubah User', 'user', 'USER', $id, 'Username ' . ($data['username'] ?? $user['username']) . (isset($data['password_hash']) ? ' (password diganti)' : ''));

        return ['success' => true, 'message' => 'User berhasil diperbarui.'];
    }

    public function delete(int $id, int $currentUserId): array
    {
        $user = $this->model->find($id);
        if (! $user) {
            return ['success' => false, 'message' => 'User tidak ditemukan.'];
        }

        if ($id === $currentUserId) {
            return ['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri.'];
        }

        if ($this->isLastActiveSuperAdmin($user)) {
            return ['success' => false, 'message' => 'Super Admin aktif terakhir tidak boleh dihapus.'];
        }

        $this->model->delete($id);
        $this->audit->log('Hapus User', 'user', 'USER', $id, 'Username ' . $user['username']);

        return ['success' => true, 'message' => 'User berhasil dihapus.'];
    }

    protected function isLastActiveSuperAdmin(array $user): bool
    {
        $role = $this->roleModel->find($user['role_id']);
        if (! $role || $role['slug'] !== 'super-admin' || $user['status'] !== 'active') {
            return false;
        }

        return $this->model->where('role_id', $role['id'])->where('status', 'active')->countAllResults() <= 1;
    }
}
