<?php

namespace App\Services;

use App\Models\OpexCategoryModel;

class OpexCategoryService
{
    protected OpexCategoryModel $model;

    public function __construct()
    {
        $this->model = new OpexCategoryModel();
    }

    public function paginate(?string $search, ?string $status, int $perPage = 10)
    {
        return $this->model->scopeList($search, $status)->paginate($perPage);
    }

    public function pager()
    {
        return $this->model->pager;
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function activeOptions(): array
    {
        return $this->model->activeOptions();
    }

    public function create(array $data): array
    {
        $data['status'] = $data['status'] ?? 'aktif';

        if (! $this->model->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Kategori OPEX berhasil ditambahkan.', 'id' => $this->model->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Kategori OPEX tidak ditemukan.'];
        }

        if (! $this->model->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Kategori OPEX berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Kategori OPEX tidak ditemukan.'];
        }

        $usedInItems = db_connect()->table('price_period_opex_items')
            ->where('opex_category_id', $id)
            ->countAllResults();

        if ($usedInItems > 0) {
            return ['success' => false, 'message' => 'Kategori OPEX tidak dapat dihapus karena sudah dipakai pada Price Period.'];
        }

        $this->model->delete($id);

        return ['success' => true, 'message' => 'Kategori OPEX berhasil dihapus.'];
    }
}
