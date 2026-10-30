<?php

namespace App\Services;

/**
 * Promo terjadwal per produk (di luar markdown):
 *  - BOGO : untuk setiap `buy_qty` yang dibeli, pelanggan mendapat `get_qty` gratis (harga Rp0, HPP tetap diakui), maksimal `max_sets` kelipatan per transaksi.
 *  - TIER : bila qty >= `min_qty`, harga satuan menjadi `tier_price` (harus di bawah harga normal).
 * Diterapkan otomatis di server saat checkout (snapshot promo_id disimpan pada baris penjualan); tampilan kasir hanya pratinjau.
 */
class PromoService
{
    /** @return array<int, array{BOGO?:array, TIER?:array}> promo aktif per product_id */
    public function activeFor(array $productIds, ?string $at = null): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (! $productIds) {
            return [];
        }
        $at   = $at ?: date('Y-m-d H:i:s');
        $rows = db_connect()->table('pos_promos')->whereIn('product_id', $productIds)->where('status', 'ACTIVE')
            ->where('start_at <=', $at)->where('end_at >=', $at)->orderBy('id')->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            // Satu promo per jenis per produk (pembuatan menolak tumpang tindih); jika ada data lama, yang terbaru dipakai.
            $out[(int) $r['product_id']][$r['promo_type']] = $r;
        }

        return $out;
    }

    /**
     * @param array{BOGO?:array, TIER?:array}|null $promos
     * @return array{unit_price:float, price_promo_id:?int, free_qty:float, free_promo_id:?int}
     */
    public function evaluate(?array $promos, float $qty, float $unitPrice): array
    {
        $res = ['unit_price' => $unitPrice, 'price_promo_id' => null, 'free_qty' => 0.0, 'free_promo_id' => null];
        if (! $promos) {
            return $res;
        }
        if (isset($promos['TIER'])) {
            $t = $promos['TIER'];
            if ($qty + 0.0005 >= (float) $t['min_qty'] && (float) $t['tier_price'] < $unitPrice) {
                $res['unit_price']     = round((float) $t['tier_price'], 2);
                $res['price_promo_id'] = (int) $t['id'];
            }
        }
        if (isset($promos['BOGO']) && (float) $promos['BOGO']['buy_qty'] > 0) {
            $b    = $promos['BOGO'];
            $sets = (int) floor(($qty + 0.0005) / (float) $b['buy_qty']);
            if ($b['max_sets'] !== null) {
                $sets = min($sets, (int) $b['max_sets']);
            }
            if ($sets > 0) {
                $res['free_qty']      = round($sets * (float) $b['get_qty'], 3);
                $res['free_promo_id'] = (int) $b['id'];
            }
        }

        return $res;
    }

    public function create(array $in, ?int $userId): array
    {
        $db      = db_connect();
        $type    = strtoupper((string) ($in['promo_type'] ?? ''));
        $product = (int) ($in['product_id'] ?? 0);
        $name    = trim((string) ($in['name'] ?? ''));
        $start   = strtotime((string) ($in['start_at'] ?? ''));
        $end     = strtotime((string) ($in['end_at'] ?? ''));

        if (! in_array($type, ['BOGO', 'TIER'], true)) {
            return ['success' => false, 'message' => 'Pilih jenis promo.'];
        }
        if (! $db->table('products')->where('id', $product)->where('deleted_at', null)->countAllResults()) {
            return ['success' => false, 'message' => 'Produk tidak ditemukan.'];
        }
        if ($name === '' || ! $start || ! $end || $end <= $start) {
            return ['success' => false, 'message' => 'Nama dan periode berlaku (mulai < berakhir) wajib diisi.'];
        }
        $price = (new PosPricingService())->priceFor($product);
        if (! $price) {
            return ['success' => false, 'message' => 'Produk belum memiliki harga retail.'];
        }

        $row = ['name' => mb_substr($name, 0, 120), 'promo_type' => $type, 'product_id' => $product, 'start_at' => date('Y-m-d H:i:s', $start), 'end_at' => date('Y-m-d H:i:s', $end),
            'reason' => ! empty($in['reason']) ? mb_substr(trim((string) $in['reason']), 0, 255) : null, 'status' => 'ACTIVE', 'created_by' => $userId];

        if ($type === 'BOGO') {
            $buy = round((float) ($in['buy_qty'] ?? 0), 3);
            $get = round((float) ($in['get_qty'] ?? 0), 3);
            if ($buy <= 0 || $get <= 0) {
                return ['success' => false, 'message' => 'Isi jumlah beli dan jumlah gratis (> 0).'];
            }
            $max = isset($in['max_sets']) && $in['max_sets'] !== '' ? (int) $in['max_sets'] : null;
            if ($max !== null && $max <= 0) {
                return ['success' => false, 'message' => 'Batas kelipatan harus > 0 atau dikosongkan.'];
            }
            $row += ['buy_qty' => $buy, 'get_qty' => $get, 'max_sets' => $max];
        } else {
            $min  = round((float) ($in['min_qty'] ?? 0), 3);
            $tier = round((float) ($in['tier_price'] ?? 0), 2);
            if ($min <= 0 || $tier <= 0) {
                return ['success' => false, 'message' => 'Isi minimal qty dan harga bertingkat (> 0).'];
            }
            if ($tier >= $price['list_price']) {
                return ['success' => false, 'message' => 'Harga bertingkat harus di bawah harga normal (Rp ' . number_format($price['list_price'], 0, ',', '.') . ').'];
            }
            $row += ['min_qty' => $min, 'tier_price' => $tier];
        }

        // Tolak tumpang tindih promo aktif sejenis pada produk yang sama.
        $overlap = $db->table('pos_promos')->where('product_id', $product)->where('promo_type', $type)->where('status', 'ACTIVE')
            ->where('start_at <', $row['end_at'])->where('end_at >', $row['start_at'])->countAllResults();
        if ($overlap) {
            return ['success' => false, 'message' => 'Sudah ada promo ' . $type . ' aktif untuk produk ini pada periode yang tumpang tindih. Batalkan yang lama dulu.'];
        }

        $now = date('Y-m-d H:i:s');
        $db->table('pos_promos')->insert($row + ['created_at' => $now, 'updated_at' => $now]);
        $id = (int) $db->insertID();
        (new AuditLogService())->log('Buat Promo', 'pos', 'POS_PROMO', $id, "{$type} {$name}");

        return ['success' => true, 'message' => 'Promo tersimpan dan berlaku sesuai periode.', 'id' => $id];
    }

    public function cancel(int $id): array
    {
        $db = db_connect();
        $db->table('pos_promos')->where('id', $id)->where('status', 'ACTIVE')->update(['status' => 'CANCELLED', 'updated_at' => date('Y-m-d H:i:s')]);
        if (! $db->affectedRows()) {
            return ['success' => false, 'message' => 'Promo tidak ditemukan atau sudah dibatalkan.'];
        }
        (new AuditLogService())->log('Batalkan Promo', 'pos', 'POS_PROMO', $id, null);

        return ['success' => true, 'message' => 'Promo dibatalkan (transaksi lama tidak berubah).'];
    }
}
