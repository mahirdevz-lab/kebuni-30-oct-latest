<?php

namespace App\Services;

use App\Models\ProductModel;
use App\Models\ProductPackingRecipeModel;

class ProductService
{
    protected ProductModel $productModel;
    protected ProductPackingRecipeModel $recipeModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
        $this->recipeModel  = new ProductPackingRecipeModel();
    }

    public function paginate(?string $search, ?int $categoryId, ?string $status, int $perPage = 10)
    {
        return $this->productModel->scopeList($search, $categoryId, $status)->paginate($perPage);
    }

    public function pager()
    {
        return $this->productModel->pager;
    }

    /**
     * ID product yang punya minimal 1 packing recipe (indikator di tabel, c.4 PRD).
     * @return int[]
     */
    public function recipeProductIds(): array
    {
        return $this->recipeModel->productIdsWithRecipe();
    }

    public function find(int $id): ?array
    {
        return $this->productModel->find($id);
    }

    public function activeOptions(): array
    {
        return $this->productModel->activeOptions();
    }

    public function create(array $data): array
    {
        $data['status']          = $data['status'] ?? 'aktif';
        $data['shrinkage_type']  = $data['shrinkage_type'] ?? 'PERCENTAGE';
        $data['shrinkage_value'] = $data['shrinkage_value'] ?? 0;

        if (! $this->productModel->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->productModel->errors()];
        }

        return ['success' => true, 'message' => 'Product berhasil ditambahkan.', 'id' => $this->productModel->getInsertID()];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->productModel->find($id)) {
            return ['success' => false, 'message' => 'Product tidak ditemukan.'];
        }

        if (! $this->productModel->update($id, $data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->productModel->errors()];
        }

        return ['success' => true, 'message' => 'Product berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->productModel->find($id)) {
            return ['success' => false, 'message' => 'Product tidak ditemukan.'];
        }

        $this->productModel->delete($id);

        return ['success' => true, 'message' => 'Product berhasil dihapus.'];
    }

    /**
     * Hitung shrinkage cost berdasarkan purchase price & konfigurasi shrinkage product.
     * Formula PERCENTAGE: purchase_price * (shrinkage_value / 100)
     * Formula FIXED: shrinkage_value langsung (nominal rupiah per unit)
     */
    public function calculateShrinkageCost(array $product, float $purchasePrice): float
    {
        if ($product['shrinkage_type'] === 'FIXED') {
            return (float) $product['shrinkage_value'];
        }

        return round($purchasePrice * ((float) $product['shrinkage_value'] / 100), 4);
    }

    // ---------------- PACKING RECIPE ----------------

    public function getRecipe(int $productId): array
    {
        return $this->recipeModel->getByProduct($productId);
    }

    /**
     * @param array $items [['packing_material_id' => int, 'quantity_usage' => float], ...]
     */
    public function saveRecipe(int $productId, array $items): array
    {
        if (! $this->productModel->find($productId)) {
            return ['success' => false, 'message' => 'Product tidak ditemukan.'];
        }

        return $this->recipeModel->replaceForProduct($productId, $items);
    }
}
