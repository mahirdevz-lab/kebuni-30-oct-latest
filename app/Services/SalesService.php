<?php

namespace App\Services;

use App\Models\SalesModel;

class SalesService
{
    protected SalesModel $model;

    public function __construct()
    {
        $this->model = new SalesModel();
    }

    public function all(): array
    {
        return $this->model->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Daftar berpaginasi + pencarian (BUG-1/BUG-3 PRD-BACKOFFICE-UI T6).
     */
    public function paginate(?string $search, int $perPage = 10)
    {
        return $this->model->scopeList($search)->paginate($perPage);
    }

    public function pager()
    {
        return $this->model->pager;
    }

    public function activeOptions(): array
    {
        return $this->model->activeOptions();
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function create(array $data): array
    {
        $data['status'] = $data['status'] ?? 'aktif';

        if (! $this->model->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Sales berhasil ditambahkan.', 'id' => $this->model->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Sales tidak ditemukan.'];
        }

        if (! $this->model->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Sales berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Sales tidak ditemukan.'];
        }

        $usedInOrder = db_connect()->table('sales_orders')
            ->where('sales_id', $id)
            ->countAllResults();

        if ($usedInOrder > 0) {
            return ['success' => false, 'message' => 'Sales tidak dapat dihapus karena sudah dipakai di Taking Order. Nonaktifkan saja.'];
        }

        $this->model->delete($id);

        return ['success' => true, 'message' => 'Sales berhasil dihapus.'];
    }
}