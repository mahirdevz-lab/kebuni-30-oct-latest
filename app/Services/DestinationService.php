<?php

namespace App\Services;

use App\Models\DestinationModel;

class DestinationService
{
    protected DestinationModel $model;

    public function __construct()
    {
        $this->model = new DestinationModel();
    }

    public function all(): array
    {
        return $this->model->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Daftar berpaginasi + filter (BUG-1/BUG-3 PRD-BACKOFFICE-UI T2).
     */
    public function paginate(?string $search, ?string $status, int $perPage = 10)
    {
        return $this->model->scopeList($search, $status)->paginate($perPage);
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

        return ['success' => true, 'message' => 'Destination berhasil ditambahkan.', 'id' => $this->model->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Destination tidak ditemukan.'];
        }

        if (! $this->model->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Destination berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Destination tidak ditemukan.'];
        }

        $usedInRoute = db_connect()->table('transport_routes')
            ->where('destination_id', $id)
            ->countAllResults();

        if ($usedInRoute > 0) {
            return ['success' => false, 'message' => 'Destination tidak dapat dihapus karena masih dipakai di transport route.'];
        }

        $this->model->delete($id);

        return ['success' => true, 'message' => 'Destination berhasil dihapus.'];
    }
}
