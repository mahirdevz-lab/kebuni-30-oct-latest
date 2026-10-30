<?php

namespace App\Services;

use App\Models\PaymentTermModel;

class PaymentTermService
{
    protected PaymentTermModel $model;

    public function __construct()
    {
        $this->model = new PaymentTermModel();
    }

    public function all(): array
    {
        return $this->model->orderBy('days', 'ASC')->findAll();
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

        return ['success' => true, 'message' => 'Payment term berhasil ditambahkan.', 'id' => $this->model->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Payment term tidak ditemukan.'];
        }

        if (! $this->model->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Payment term berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Payment term tidak ditemukan.'];
        }

        $this->model->delete($id);

        return ['success' => true, 'message' => 'Payment term berhasil dihapus.'];
    }

    /**
     * Compound interest: Final Price = Cash Price × (1 + Daily Interest)^Days
     * Cash (days=0) tidak mendapat tambahan bunga apapun.
     */
    public function calculateFinalPrice(float $cashPrice, array $paymentTerm): float
    {
        $days = (int) $paymentTerm['days'];

        if ($days <= 0) {
            return round($cashPrice, 2);
        }

        $dailyRate = ((float) $paymentTerm['daily_interest_percent']) / 100;
        $final     = $cashPrice * (1 + $dailyRate) ** $days;

        return round($final, 2);
    }
}
