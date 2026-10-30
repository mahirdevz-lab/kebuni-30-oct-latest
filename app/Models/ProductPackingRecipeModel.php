<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductPackingRecipeModel extends Model
{
    protected $table         = 'product_packing_recipes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['product_id', 'packing_material_id', 'quantity_usage'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'product_id'          => 'required|integer',
        'packing_material_id' => 'required|integer',
        'quantity_usage'      => 'required|decimal|greater_than[0]',
    ];

    /**
     * ID product yang punya minimal 1 recipe (untuk indikator di tabel product, c.4 PRD).
     *
     * @return int[]
     */
    public function productIdsWithRecipe(): array
    {
        $rows = $this->distinct()->select('product_id')->findAll();

        return array_map('intval', array_column($rows, 'product_id'));
    }

    /**
     * Ambil recipe milik satu product beserta detail packing material-nya.
     */
    public function getByProduct(int $productId): array
    {
        return $this->select('product_packing_recipes.*, packing_materials.name as packing_name, packing_materials.code as packing_code, packing_materials.capacity_kg')
            ->join('packing_materials', 'packing_materials.id = product_packing_recipes.packing_material_id')
            ->where('product_packing_recipes.product_id', $productId)
            ->findAll();
    }

    /**
     * Ganti seluruh recipe milik sebuah product (hapus lama, insert baru) dalam 1 transaction.
     *
     * @param array $items [['packing_material_id' => int, 'quantity_usage' => float], ...]
     */
    public function replaceForProduct(int $productId, array $items): array
    {
        $db = db_connect();
        $db->transStart();

        $this->where('product_id', $productId)->delete();

        foreach ($items as $item) {
            $this->insert([
                'product_id'          => $productId,
                'packing_material_id' => (int) $item['packing_material_id'],
                'quantity_usage'      => (float) $item['quantity_usage'],
            ], false);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan recipe packing.'];
        }

        return ['success' => true, 'message' => 'Packing recipe berhasil disimpan.'];
    }
}
