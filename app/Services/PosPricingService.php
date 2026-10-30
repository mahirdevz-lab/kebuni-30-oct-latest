<?php

namespace App\Services;

/**
 * Harga retail berversi (pos_product_prices) + markdown terkontrol (pos_markdowns).
 * Transaksi lama tidak terpengaruh: harga disalin ke pos_sale_items saat checkout.
 */
class PosPricingService
{
    /** @return array{list_price:float, unit_price:float, markdown_id:?int}|null null = belum ada harga retail */
    public function priceFor(int $productId, ?string $at = null): ?array
    {
        $at = $at ?: date('Y-m-d H:i:s');
        $db = db_connect();

        $price = $db->table('pos_product_prices')
            ->where('product_id', $productId)->where('effective_from <=', $at)
            ->orderBy('effective_from', 'DESC')->orderBy('id', 'DESC')->get(1)->getRowArray();
        if (! $price) {
            return null;
        }

        $list = (float) $price['retail_price'];
        $md   = $db->table('pos_markdowns')
            ->where('product_id', $productId)->where('status', 'ACTIVE')
            ->where('start_at <=', $at)->where('end_at >=', $at)
            ->orderBy('promo_price', 'ASC')->get(1)->getRowArray();

        if ($md && (float) $md['promo_price'] < $list) {
            return ['list_price' => $list, 'unit_price' => (float) $md['promo_price'], 'markdown_id' => (int) $md['id']];
        }

        return ['list_price' => $list, 'unit_price' => $list, 'markdown_id' => null];
    }

    public function setRetailPrice(int $productId, float $price, ?string $effectiveFrom = null, ?int $userId = null): array
    {
        if ($price < 0) {
            return ['success' => false, 'message' => 'Harga tidak boleh negatif.'];
        }
        $now = date('Y-m-d H:i:s');
        db_connect()->table('pos_product_prices')->insert([
            'product_id' => $productId, 'retail_price' => round($price, 2),
            'effective_from' => $effectiveFrom ?: $now, 'created_by' => $userId, 'created_at' => $now,
        ]);
        (new AuditLogService())->log('Ubah Harga Retail', 'pos', 'PRODUCT', $productId, 'Harga retail Rp ' . number_format($price, 0, ',', '.'));

        return ['success' => true, 'message' => 'Harga retail tersimpan.'];
    }

    public function createMarkdown(int $productId, float $promoPrice, string $reason, string $startAt, string $endAt, ?int $userId = null): array
    {
        $current = $this->priceFor($productId);
        if (! $current) {
            return ['success' => false, 'message' => 'Produk belum memiliki harga retail.'];
        }
        if (trim($reason) === '') {
            return ['success' => false, 'message' => 'Alasan markdown wajib diisi.'];
        }
        if ($promoPrice < 0 || $promoPrice >= $current['list_price']) {
            return ['success' => false, 'message' => 'Harga promo harus di bawah harga normal.'];
        }
        if (strtotime($endAt) <= strtotime($startAt)) {
            return ['success' => false, 'message' => 'Periode berlaku tidak valid.'];
        }

        $now = date('Y-m-d H:i:s');
        db_connect()->table('pos_markdowns')->insert([
            'product_id' => $productId, 'normal_price' => $current['list_price'], 'promo_price' => round($promoPrice, 2),
            'reason' => $reason, 'start_at' => $startAt, 'end_at' => $endAt, 'status' => 'ACTIVE',
            'approved_by' => $userId, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        (new AuditLogService())->log('Markdown', 'pos', 'PRODUCT', $productId, "Rp {$current['list_price']} -> Rp {$promoPrice}: {$reason}");

        return ['success' => true, 'message' => 'Markdown tersimpan.'];
    }
}
