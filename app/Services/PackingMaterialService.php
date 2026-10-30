<?php

namespace App\Services;

use App\Models\PackingMaterialModel;

class PackingMaterialService
{
    protected PackingMaterialModel $model;

    public function __construct()
    {
        $this->model = new PackingMaterialModel();
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

        return ['success' => true, 'message' => 'Packing material berhasil ditambahkan.', 'id' => $this->model->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Packing material tidak ditemukan.'];
        }

        // Sertakan id agar placeholder {id} pada rule is_unique[packing_materials.code,id,{id}]
        // ter-resolve — tanpa ini update dengan kode yang sama ditolak sebagai duplikat.
        $data['id'] = $id;

        if (! $this->model->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->model->errors()];
        }

        return ['success' => true, 'message' => 'Packing material berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->model->find($id)) {
            return ['success' => false, 'message' => 'Packing material tidak ditemukan.'];
        }

        $usedInRecipe = db_connect()->table('product_packing_recipes')
            ->where('packing_material_id', $id)
            ->countAllResults();

        if ($usedInRecipe > 0) {
            return ['success' => false, 'message' => 'Packing material tidak dapat dihapus karena masih dipakai di recipe product.'];
        }

        $this->model->delete($id);

        return ['success' => true, 'message' => 'Packing material berhasil dihapus.'];
    }

    /**
     * Cost per Kg = Price / Capacity Kg (jika material berbasis kapasitas).
     * Jika tidak punya kapasitas (mis. Koran, dihitung langsung per Kg), kembalikan price apa adanya.
     * Nilai ini murni untuk kalkulasi preview di master data;
     * harga aktual tetap berasal dari Price Period (fase berikutnya).
     */
    public function calculateCostPerKg(?float $price, ?float $capacityKg): ?float
    {
        if ($price === null) {
            return null;
        }

        if (! $capacityKg || $capacityKg <= 0) {
            return $price; // sudah dianggap harga per Kg langsung
        }

        return round($price / $capacityKg, 4);
    }
}

