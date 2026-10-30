<?php

namespace App\Services;

use App\Models\PricingTierModel;

class PricingTierService
{
    protected PricingTierModel $model;

    public function __construct()
    {
        $this->model = new PricingTierModel();
    }

    public function all(): array
    {
        return $this->model->allOrdered();
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

    public function find(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function findForQty(float $qty): ?array
    {
        return $this->model->findForQty($qty);
    }

    public function create(array $data): array
    {
        $data['status']  = $data['status'] ?? 'aktif';
        $data['max_qty'] = $data['max_qty'] === '' ? null : ($data['max_qty'] ?? null);

        if ($data['max_qty'] !== null && (float) $data['max_qty'] <= (float) $data['min_qty']) {
            return ['success' => false, 'message' => 'Max Qty harus lebih besar dari Min Qty.'];
        }

        if ($this->model->hasQtyOverlap((float) $data['min_qty'], $data['max_qty'] !== null ? (float) $data['max_qty'] : null)) {
            return ['success' => false, 'message' => 'Range qty tumpang tindih dengan tier aktif lain.'];
        }

        if (! $this->model->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Pricing tier berhasil ditambahkan.', 'id' => $this->model->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        $tier = $this->model->find($id);

        if (! $tier) {
            return ['success' => false, 'message' => 'Pricing tier tidak ditemukan.'];
        }

        $data['max_qty'] = $data['max_qty'] === '' ? null : ($data['max_qty'] ?? null);

        if ($data['max_qty'] !== null && (float) $data['max_qty'] <= (float) $data['min_qty']) {
            return ['success' => false, 'message' => 'Max Qty harus lebih besar dari Min Qty.'];
        }

        if ($this->model->hasQtyOverlap((float) $data['min_qty'], $data['max_qty'] !== null ? (float) $data['max_qty'] : null, $id)) {
            return ['success' => false, 'message' => 'Range qty tumpang tindih dengan tier aktif lain.'];
        }

        if (! $this->model->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Pricing tier berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Pricing tier tidak ditemukan.'];
        }

        $this->model->delete($id);

        return ['success' => true, 'message' => 'Pricing tier berhasil dihapus.'];
    }
}
