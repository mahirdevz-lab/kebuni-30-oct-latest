<?php

namespace App\Models;

use CodeIgniter\Model;

class StockOpnameModel extends Model
{
    protected $table            = 'stock_opnames';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'price_period_id', 'product_id', 'stok_awal', 'pembelian',
        'stok_aktual', 'notes', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'price_period_id' => 'required|integer',
        'product_id'      => 'required|integer',
        'stok_awal'       => 'permit_empty|decimal|greater_than_equal_to[0]',
        'pembelian'       => 'permit_empty|decimal|greater_than_equal_to[0]',
        'stok_aktual'     => 'permit_empty|decimal|greater_than_equal_to[0]',
    ];

    /** Ambil opname per periode, keyed by product_id. */
    public function getByPeriod(int $periodId): array
    {
        $rows = $this->where('price_period_id', $periodId)->findAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['product_id']] = $r;
        }

        return $out;
    }

    /** Simpan/bulk-replace opname satu periode (upsert per product). */
    public function replaceForPeriod(int $periodId, array $rows, ?int $userId = null): void
    {
        foreach ($rows as $r) {
            $productId = (int) ($r['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            // stok_aktual kosong = belum dihitung (NULL), bukan 0; 0 hanya bila memang diisi 0 (barang habis).
            $aktual = $r['stok_aktual'] ?? null;
            $data = [
                'price_period_id' => $periodId,
                'product_id'      => $productId,
                'stok_awal'       => (float) ($r['stok_awal'] ?? 0),
                'pembelian'       => (float) ($r['pembelian'] ?? 0),
                'stok_aktual'     => ($aktual === null || $aktual === '') ? null : (float) $aktual,
                'notes'           => $r['notes'] ?? null,
            ];

            $existing = $this->where('price_period_id', $periodId)
                ->where('product_id', $productId)
                ->first();

            if ($existing) {
                $this->update($existing['id'], $data);
            } else {
                $data['created_by'] = $userId;
                $this->insert($data);
            }
        }
    }
}
