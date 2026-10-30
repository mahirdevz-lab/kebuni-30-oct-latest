<?php

namespace App\Services;

use App\Models\SupplierModel;

class SupplierService
{
    protected SupplierModel $supplierModel;

    public function __construct()
    {
        $this->supplierModel = new SupplierModel();
    }

    public function paginate(?string $search, ?string $status, int $perPage = 10)
    {
        return $this->supplierModel->scopeList($search, $status)->paginate($perPage);
    }

    public function pager()
    {
        return $this->supplierModel->pager;
    }

    public function find(int $id): ?array
    {
        return $this->supplierModel->find($id);
    }

    public function activeOptions(): array
    {
        return $this->supplierModel->activeOptions();
    }

    public function create(array $data): array
    {
        $data['status'] = $data['status'] ?? 'aktif';

        if (! $this->supplierModel->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->supplierModel->errors()];
        }

        return ['success' => true, 'message' => 'Supplier berhasil didaftarkan.', 'id' => $this->supplierModel->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->supplierModel->find($id)) {
            return ['success' => false, 'message' => 'Supplier tidak ditemukan.'];
        }

        if (! $this->supplierModel->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->supplierModel->errors()];
        }

        return ['success' => true, 'message' => 'Supplier berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        $supplier = $this->supplierModel->find($id);

        if (! $supplier) {
            return ['success' => false, 'message' => 'Supplier tidak ditemukan.'];
        }

        $this->supplierModel->delete($id);

        return ['success' => true, 'message' => 'Supplier berhasil dihapus.'];
    }

    public function toggleStatus(int $id): array
    {
        $supplier = $this->supplierModel->find($id);

        if (! $supplier) {
            return ['success' => false, 'message' => 'Supplier tidak ditemukan.'];
        }

        $newStatus = $supplier['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        $this->supplierModel->update($id, ['status' => $newStatus]);

        return ['success' => true, 'message' => "Status berhasil diubah menjadi {$newStatus}.", 'status' => $newStatus];
    }
}
