<?php

namespace App\Services;

use App\Models\TransportRouteModel;

class TransportRouteService
{
    protected TransportRouteModel $model;

    public function __construct()
    {
        $this->model = new TransportRouteModel();
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
        $data['status']        = $data['status'] ?? 'aktif';
        $data['default_basis'] = $data['default_basis'] ?? 'CAPACITY';

        if (! $this->model->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Transport route berhasil ditambahkan.', 'id' => $this->model->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Transport route tidak ditemukan.'];
        }

        if (! $this->model->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Transport route berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Transport route tidak ditemukan.'];
        }

        $this->model->delete($id);

        return ['success' => true, 'message' => 'Transport route berhasil dihapus.'];
    }

    /**
     * Cost per Kg = Total Transport Cost / Volume (Capacity atau Actual, tergantung basis).
     * Nilai transport cost & volume aktual akan disimpan di Price Period (fase berikutnya);
     * method ini disiapkan untuk dipakai ulang oleh Pricing Engine nanti.
     */
    public function calculateCostPerKg(float $totalTransportCost, float $volumeKg): float
    {
        if ($volumeKg <= 0) {
            return 0;
        }

        return round($totalTransportCost / $volumeKg, 4);
    }
}
