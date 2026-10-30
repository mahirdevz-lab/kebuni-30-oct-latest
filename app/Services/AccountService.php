<?php

namespace App\Services;

use App\Models\AccountModel;

class AccountService
{
    protected AccountModel $accountModel;

    public function __construct()
    {
        $this->accountModel = new AccountModel();
    }

    public function paginate(?string $search, ?string $status, int $perPage = 10)
    {
        return $this->accountModel->scopeList($search, $status)->paginate($perPage);
    }

    public function pager()
    {
        return $this->accountModel->pager;
    }

    /**
     * Daftar untuk <select> dropdown: limit eksplisit, TANPA pager.
     * Pengganti pemakaian paginate() "palsu" (BUG-5 audit pagination).
     */
    public function dropdown(?string $status = null, int $limit = 500): array
    {
        return $this->accountModel->scopeList(null, $status)->limit($limit)->findAll();
    }

    public function find(int $id): ?array
    {
        return $this->accountModel->find($id);
    }

    /**
     * @return array{success: bool, message: string, errors?: array}
     */
    public function create(array $data): array
    {
        $data['status'] = $data['status'] ?? 'aktif';

        if (! $this->accountModel->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->accountModel->errors()];
        }

        return ['success' => true, 'message' => 'Account berhasil didaftarkan.', 'id' => $this->accountModel->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->accountModel->find($id)) {
            return ['success' => false, 'message' => 'Account tidak ditemukan.'];
        }

        // Rule unique email/username perlu tahu id saat ini, tapi tidak ada unique rule di account.
        if (! $this->accountModel->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->accountModel->errors()];
        }

        return ['success' => true, 'message' => 'Account berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        $account = $this->accountModel->find($id);

        if (! $account) {
            return ['success' => false, 'message' => 'Account tidak ditemukan.'];
        }

        $this->accountModel->delete($id);

        return ['success' => true, 'message' => 'Account berhasil dihapus.'];
    }

    public function toggleStatus(int $id): array
    {
        $account = $this->accountModel->find($id);

        if (! $account) {
            return ['success' => false, 'message' => 'Account tidak ditemukan.'];
        }

        $newStatus = $account['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        $this->accountModel->update($id, ['status' => $newStatus]);

        return ['success' => true, 'message' => "Status berhasil diubah menjadi {$newStatus}.", 'status' => $newStatus];
    }
}
