<?php

namespace App\Models;

use CodeIgniter\Model;

class PricePeriodProductModel extends Model
{
    protected $table         = 'price_period_products';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['price_period_id', 'product_id', 'purchase_price', 'shrinkage_percent', 'notes'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'product_id'     => 'required|integer',
        'purchase_price' => 'required|decimal|greater_than_equal_to[0]',
    ];

    public function getByPeriod(int $pricePeriodId): array
    {
        return $this->select('price_period_products.*, products.product_code, products.product_name, products.unit, products.shrinkage_type, products.shrinkage_value as master_shrinkage_value')
            ->join('products', 'products.id = price_period_products.product_id')
            ->where('price_period_products.price_period_id', $pricePeriodId)
            ->orderBy('products.product_name', 'ASC')
            ->findAll();
    }

    /**
     * Cari 1 baris purchase price untuk kombinasi Price Period + Product tertentu.
     * Dipakai oleh Price Calculation Engine.
     */
    public function findByPeriodAndProduct(int $pricePeriodId, int $productId): ?array
    {
        return $this->where('price_period_id', $pricePeriodId)
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * Ganti seluruh baris purchase price milik satu Price Period (all-or-nothing).
     * @param array $items [['product_id'=>int, 'purchase_price'=>float, 'shrinkage_percent'=>?float, 'notes'=>?string], ...]
     */
    public function replaceForPeriod(int $pricePeriodId, array $items): array
    {
        $db = db_connect();
        $db->transStart();

        $this->where('price_period_id', $pricePeriodId)->delete();

        foreach ($items as $item) {
            $this->insert([
                'price_period_id'   => $pricePeriodId,
                'product_id'        => (int) $item['product_id'],
                'purchase_price'    => (float) $item['purchase_price'],
                'shrinkage_percent' => isset($item['shrinkage_percent']) && $item['shrinkage_percent'] !== '' ? (float) $item['shrinkage_percent'] : null,
                'notes'             => $item['notes'] ?? null,
            ], false);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menyimpan Product Price.'];
        }

        return ['success' => true, 'message' => 'Product Price berhasil disimpan.'];
    }
}
