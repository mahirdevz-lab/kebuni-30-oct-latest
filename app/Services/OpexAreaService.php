<?php

namespace App\Services;

use App\Models\OpexAreaModel;

class OpexAreaService
{
    protected OpexAreaModel $model;

    public function __construct()
    {
        $this->model = new OpexAreaModel();
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
        $data['status']         = $data['status'] ?? 'aktif';
        $data['destination_id'] = ! empty($data['destination_id']) ? (int) $data['destination_id'] : null;

        if (! $this->model->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'OPEX Area berhasil ditambahkan.', 'id' => $this->model->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'OPEX Area tidak ditemukan.'];
        }

        $data['destination_id'] = ! empty($data['destination_id']) ? (int) $data['destination_id'] : null;

        if (! $this->model->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'OPEX Area berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'OPEX Area tidak ditemukan.'];
        }

        $usedInPeriods = db_connect()->table('price_period_opex_areas')
            ->where('opex_area_id', $id)
            ->countAllResults();

        if ($usedInPeriods > 0) {
            return ['success' => false, 'message' => 'OPEX Area tidak dapat dihapus karena sudah dipakai pada Price Period.'];
        }

        $this->model->delete($id);

        return ['success' => true, 'message' => 'OPEX Area berhasil dihapus.'];
    }
}
