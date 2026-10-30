<?php

namespace App\Services;

use App\Models\PricePeriodModel;
use App\Models\ProductModel;
use App\Models\StockOpnameModel;
use Config\Database;

/**
 * Final Report per Price Period — ringkasan omzet/modal/laba,
 * tracking avg harga jual aktual vs harga rekomendasi (diskon kualitas),
 * piutang per customer, dan shrinkage dari stok opname.
 */
class FinalReportService
{
    protected $db;
    protected StockOpnameModel $opnameModel;

    /** Status order yang dihitung sebagai penjualan valid. */
    protected array $validStatuses = ['Submitted', 'Approved', 'Processing', 'Completed'];

    public function __construct()
    {
        $this->db          = Database::connect();
        $this->opnameModel = new StockOpnameModel();
    }

    public function getPeriod(int $periodId): ?array
    {
        return (new PricePeriodModel())->find($periodId);
    }

    /** Daftar periode untuk dropdown filter. */
    public function periodOptions(): array
    {
        return (new PricePeriodModel())
            ->select('id, period_code, name')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Bangun seluruh data final report untuk satu price period,
     * opsional dibatasi ke satu order saja.
     *
     * @return array{period, summary, products, receivables, stock, order}
     */
    public function build(int $periodId, ?int $orderId = null): array
    {
        $period = $this->getPeriod($periodId);

        $products    = $this->productReport($periodId, $orderId);
        $receivables = $this->receivableReport($periodId, $orderId);
        $transactions = $this->transactionReport($periodId, $orderId);
        $matrix      = $orderId ? null : $this->marginMatrix($periodId, $products);
        // Stok opname hanya relevan di level periode, bukan per transaksi.
        $stock       = $orderId ? null : $this->stockReport($periodId, $products);

        $omzet  = array_sum(array_column($products, 'omzet'));
        $cogs   = array_sum(array_column($products, 'cogs'));
        $profit = $omzet - $cogs;

        // Komisi sales (hutang): total, sudah dibayar, sisa
        $commission = $this->commissionReport($periodId, $orderId);

        $order = null;
        if ($orderId) {
            $order = $this->db->table('sales_orders so')
                ->select('so.id, so.order_no, so.order_date, a.nama_account as customer')
                ->join('accounts a', 'a.id = so.account_id', 'left')
                ->where('so.id', $orderId)
                ->get()->getRowArray();
        }

        return [
            'period'  => $period,
            'order'   => $order,
            'summary' => [
                'omzet'          => round($omzet, 2),
                'modal'          => round($cogs, 2),
                'profit'         => round($profit, 2),
                'margin_percent' => $omzet > 0 ? round(($profit / $omzet) * 100, 2) : 0,
                'total_qty'      => round(array_sum(array_column($products, 'qty')), 2),
                'total_discount' => round(array_sum(array_column($products, 'discount_total')), 2),
                'n_product'      => count($products),
                'n_order'        => (int) array_sum(array_column($products, 'n_order')),
            ],
            'products'    => $products,
            'receivables' => $receivables,
            'transactions'=> $transactions,
            'matrix'      => $matrix,
            'stock'       => $stock,
            'commission'  => $commission,
        ];
    }

    /**
     * Ringkasan hutang komisi sales.
     * Jika $orderId diisi, hanya order tersebut.
     */
    public function commissionReport(int $periodId, ?int $orderId = null): array
    {
        $builder = $this->db->table('sales_orders')
            ->select('COALESCE(SUM(commission_amount), 0) AS total, COALESCE(SUM(commission_paid_amount), 0) AS paid')
            ->whereIn('status', ['Submitted', 'Approved', 'Processing', 'Completed'])
            ->where('commission_amount >', 0);

        if ($orderId) {
            $builder->where('id', $orderId);
        } else {
            $builder->where('price_period_id', $periodId);
        }

        $row = $builder->get()->getRowArray() ?: ['total' => 0, 'paid' => 0];
        $total = (float) $row['total'];
        $paid  = (float) $row['paid'];

        return [
            'total'     => round($total, 2),
            'paid'      => round($paid, 2),
            'remaining' => round(max(0, $total - $paid), 2),
        ];
    }

    /**
     * Per produk: qty, omzet, COGS, profit, margin, avg jual aktual,
     * avg rekomendasi, dan total diskon kualitas.
     * Jika $orderId diisi, hanya agregasi order tersebut.
     */
    public function productReport(int $periodId, ?int $orderId = null): array
    {
        $builder = $this->db->table('sales_order_items soi')
            ->select("
                soi.product_id,
                MAX(soi.product_code_snapshot) as code,
                MAX(soi.product_name_snapshot) as name,
                MAX(soi.unit_snapshot) as unit,
                SUM(soi.qty) as qty,
                SUM(soi.subtotal_final) as omzet,
                SUM(soi.cogs_destination * soi.qty) as cogs,
                SUM(soi.final_price * soi.qty) / NULLIF(SUM(soi.qty), 0) as avg_recommended,
                SUM(soi.subtotal_final) / NULLIF(SUM(soi.qty), 0) as avg_actual,
                SUM((soi.final_price - soi.selling_price) * soi.qty) as discount_total,
                COUNT(DISTINCT soi.sales_order_id) as n_order
            ")
            ->join('sales_orders so', 'so.id = soi.sales_order_id')
            ->where('so.price_period_id', $periodId)
            ->where('so.deleted_at', null)
            ->whereIn('so.status', $this->validStatuses)
            ->groupBy('soi.product_id')
            ->orderBy('omzet', 'DESC');

        if ($orderId) {
            $builder->where('so.id', $orderId);
        }

        $rows = $builder->get()->getResultArray();

        return array_map(static function ($r) {
            $omzet  = (float) $r['omzet'];
            $cogs   = (float) $r['cogs'];
            $profit = $omzet - $cogs;
            $qty    = (float) $r['qty'];

            return [
                'product_id'      => (int) $r['product_id'],
                'code'            => $r['code'],
                'name'            => $r['name'],
                'unit'            => $r['unit'] ?: 'Kg',
                'qty'             => round($qty, 2),
                'omzet'           => round($omzet, 2),
                'cogs'            => round($cogs, 2),
                'profit'          => round($profit, 2),
                'margin_percent'  => $omzet > 0 ? round(($profit / $omzet) * 100, 2) : 0,
                'avg_recommended' => round((float) $r['avg_recommended'], 2),
                'avg_actual'      => round((float) $r['avg_actual'], 2),
                'discount_total'  => round((float) $r['discount_total'], 2),
                'discount_percent'=> (float) $r['avg_recommended'] > 0
                    ? round((((float) $r['avg_recommended'] - (float) $r['avg_actual']) / (float) $r['avg_recommended']) * 100, 2)
                    : 0,
                'n_order'         => (int) $r['n_order'],
            ];
        }, $rows);
    }

    /**
     * Piutang per customer untuk order pada periode ini.
     * Jika $orderId diisi, hanya untuk customer dari order tersebut.
     */
    public function receivableReport(int $periodId, ?int $orderId = null): array
    {
        $builder = $this->db->table('sales_orders')
            ->distinct()
            ->select('account_id')
            ->where('price_period_id', $periodId)
            ->where('deleted_at', null)
            ->whereIn('status', $this->validStatuses);

        if ($orderId) {
            $builder->where('id', $orderId);
        }

        $accountIds = array_column($builder->get()->getResultArray(), 'account_id');

        if ($accountIds === []) {
            return ['rows' => [], 'total_tagihan' => 0, 'total_terbayar' => 0, 'total_sisa' => 0];
        }

        $rows = $this->db->table('receivables r')
            ->select("
                a.nama_account as customer,
                SUM(r.total_amount) as tagihan,
                SUM(r.paid_amount) as terbayar,
                SUM(r.remaining_amount) as sisa,
                MAX(r.due_date) as jatuh_tempo,
                SUM(CASE WHEN r.status = 'OVERDUE' THEN 1 ELSE 0 END) as n_overdue
            ")
            ->join('accounts a', 'a.id = r.account_id')
            ->whereIn('r.account_id', $accountIds)
            ->where('r.remaining_amount >', 0)
            ->groupBy('r.account_id')
            ->orderBy('sisa', 'DESC')
            ->get()->getResultArray();

        return [
            'rows'           => $rows,
            'total_tagihan'  => round(array_sum(array_column($rows, 'tagihan')), 2),
            'total_terbayar' => round(array_sum(array_column($rows, 'terbayar')), 2),
            'total_sisa'     => round(array_sum(array_column($rows, 'sisa')), 2),
        ];
    }

    /**
     * Transaksi per customer dalam satu periode — SEMUA yang transaksi (bukan cuma yang punya piutang).
     * Kolom: customer, jumlah order, total omzet, total terbayar, sisa piutang, status.
     */
    public function transactionReport(int $periodId, ?int $orderId = null): array
    {
        // Agregat piutang per account (receivables keyed by account_id).
        // Tanpa filter remaining > 0 biar yang lunas ikut kehitung terbayarnya.
        $recvMap = [];
        $recvRows = $this->db->table('receivables r')
            ->select("
                r.account_id,
                SUM(r.paid_amount) as terbayar,
                SUM(r.remaining_amount) as sisa,
                SUM(CASE WHEN r.status = 'OVERDUE' THEN 1 ELSE 0 END) as n_overdue,
                COUNT(*) as n_recv
            ")
            ->groupBy('r.account_id')
            ->get()->getResultArray();
        foreach ($recvRows as $rr) {
            $recvMap[(int) $rr['account_id']] = $rr;
        }

        $builder = $this->db->table('sales_orders so')
            ->select("
                so.account_id,
                MAX(a.nama_account) as customer,
                COUNT(DISTINCT so.id) as n_order,
                SUM(so.grand_total) as omzet
            ")
            ->join('accounts a', 'a.id = so.account_id', 'left')
            ->where('so.price_period_id', $periodId)
            ->where('so.deleted_at', null)
            ->whereIn('so.status', $this->validStatuses)
            ->groupBy('so.account_id')
            ->orderBy('omzet', 'DESC');

        if ($orderId) {
            $builder->where('so.id', $orderId);
        }

        $rows = $builder->get()->getResultArray();

        // Gabungkan info piutang per customer.
        // Kalau tidak ada record receivable = bayar cash lunas di muka → terbayar = omzet.
        foreach ($rows as &$row) {
            $aid   = (int) $row['account_id'];
            $omzet = (float) $row['omzet'];
            if (isset($recvMap[$aid])) {
                $row['terbayar']  = (float) $recvMap[$aid]['terbayar'];
                $row['sisa']      = (float) $recvMap[$aid]['sisa'];
                $row['n_overdue'] = (int) $recvMap[$aid]['n_overdue'];
            } else {
                $row['terbayar']  = $omzet;
                $row['sisa']      = 0;
                $row['n_overdue'] = 0;
            }
        }
        unset($row);

        return [
            'rows'          => $rows,
            'total_order'   => (int) array_sum(array_column($rows, 'n_order')),
            'total_omzet'   => round(array_sum(array_column($rows, 'omzet')), 2),
            'total_terbayar'=> round(array_sum(array_column($rows, 'terbayar')), 2),
            'total_sisa'    => round(array_sum(array_column($rows, 'sisa')), 2),
            'n_customer'    => count($rows),
        ];
    }

    /**
     * Matriks margin 4 kuadran (menu engineering) per produk + analisis otomatis.
     * Kuadran: Star (populer+profit), Plow Horse (populer, margin rendah),
     *          Puzzle (kurang populer, margin tinggi), Dog (kurang populer+margin rendah).
     */
    public function marginMatrix(int $periodId, array $products = []): array
    {
        if ($products === []) {
            $products = $this->productReport($periodId);
        }
        if ($products === []) {
            return ['quadrants' => [], 'analysis' => [], 'avg_qty' => 0, 'avg_margin' => 0];
        }

        $avgQty    = array_sum(array_column($products, 'qty')) / count($products);
        $margins   = array_column($products, 'margin_percent');
        $avgMargin = array_sum($margins) / count($margins);

        $quadrants = ['star' => [], 'plowhorse' => [], 'puzzle' => [], 'dog' => []];
        foreach ($products as $p) {
            $populer = $p['qty'] >= $avgQty;
            $profit  = $p['margin_percent'] >= $avgMargin;
            if ($populer && $profit) {
                $quadrants['star'][] = $p;
            } elseif ($populer && ! $profit) {
                $quadrants['plowhorse'][] = $p;
            } elseif (! $populer && $profit) {
                $quadrants['puzzle'][] = $p;
            } else {
                $quadrants['dog'][] = $p;
            }
        }

        // Analisis otomatis
        $analysis = [];
        $nStar = count($quadrants['star']);
        $nDog  = count($quadrants['dog']);
        $nPlow = count($quadrants['plowhorse']);
        $nPuz  = count($quadrants['puzzle']);

        if ($nStar > 0) {
            $topStar = $quadrants['star'][0];
            foreach ($quadrants['star'] as $q) {
                if ($q['profit'] > $topStar['profit']) $topStar = $q;
            }
            $analysis[] = "⭐ {$nStar} produk STAR — andalan. Top: {$topStar['name']} (margin " . number_format($topStar['margin_percent'], 1) . "%). Pertahankan stok & harga.";
        }
        if ($nPlow > 0) {
            $analysis[] = "🐴 {$nPlow} produk PLOW HORSE — laris tapi margin tipis. Cek COGS atau naikkan harga bertahap.";
        }
        if ($nPuz > 0) {
            $analysis[] = "🧩 {$nPuz} produk PUZZLE — margin bagus tapi kurang laris. Dorong promosi/bundling.";
        }
        if ($nDog > 0) {
            $worst = $quadrants['dog'][0];
            foreach ($quadrants['dog'] as $q) {
                if ($q['margin_percent'] < $worst['margin_percent']) $worst = $q;
            }
            $analysis[] = "🐕 {$nDog} produk DOG — sepi & margin rendah. Pertimbangkan stop jual. Terparah: {$worst['name']} (margin " . number_format($worst['margin_percent'], 1) . "%).";
        }
        // Insight omzet vs profit
        $topOmzet = $products[0];
        foreach ($products as $p) {
            if ($p['omzet'] > $topOmzet['omzet']) $topOmzet = $p;
        }
        $analysis[] = "💰 Omzet terbesar: {$topOmzet['name']} (Rp " . number_format($topOmzet['omzet'], 0, ',', '.') . ").";

        return [
            'quadrants'  => $quadrants,
            'analysis'   => $analysis,
            'avg_qty'    => round($avgQty, 2),
            'avg_margin' => round($avgMargin, 2),
        ];
    }

    /**
     * Stok opname + shrinkage per produk.
     * stok_buku = stok_awal + pembelian - terjual
     * shrinkage = stok_buku - stok_aktual (qty & nilai)
     */
    public function stockReport(int $periodId, array $products = []): array
    {
        if ($products === []) {
            $products = $this->productReport($periodId);
        }

        $opnames = $this->opnameModel->getByPeriod($periodId);

        // COGS manual dari Excel Depay (2026-10-18) — sumber utama biar pasti sama.
        // Key = nama produk lowercase tanpa spasi berlebih.
        $manualCogs = [
            'kol'                 => 8337,
            'kentang ab'          => 14835,
            'kentang a'           => 14835,
            'sawi putih'          => 10947,
            'sawi'                => 10947,
            'kentang c'           => 13007,
            'tomat merah'         => 10032,
            'tomat'               => 10032,
            'labu siam'           => 10593,
            'labu'                => 10593,
            'kunyit'              => 11048,
            'jahe gajah'          => 15088,
            'jahe'                => 15088,
            'cabe merah keriting' => 51698,
            'cmk'                 => 51698,
            'cabe rawit merah'    => 73918,
            'cabe ori'            => 73918,
            'timun'               => 8265,
            'bawang brebes'       => 27531,
            'bawang'              => 27531,
            'jagung'              => 11300,
        ];

        $buyPriceMap = [];

        // 1. Coba dari nama produk di periode ini (pakai mapping manual)
        $prodNames = $this->db->table('products')
            ->select('id, product_name')
            ->get()->getResultArray();
        foreach ($prodNames as $pn) {
            $key = strtolower(trim(preg_replace('/\s+/', ' ', $pn['product_name'])));
            if (isset($manualCogs[$key])) {
                $buyPriceMap[(int) $pn['id']] = $manualCogs[$key];
            }
        }

        // 2. COGS Destination rata-rata tertimbang per produk (fallback kalau tidak ada di mapping).
        // = SUM(cogs_destination * qty) / SUM(qty) dari sales order items periode ini.
        $cogsRows = $this->db->table('sales_order_items soi')
            ->select('soi.product_id, SUM(soi.cogs_destination * soi.qty) / NULLIF(SUM(soi.qty), 0) as avg_cogs')
            ->join('sales_orders so', 'so.id = soi.sales_order_id')
            ->where('so.price_period_id', $periodId)
            ->where('so.deleted_at', null)
            ->whereIn('so.status', $this->validStatuses)
            ->groupBy('soi.product_id')
            ->get()->getResultArray();
        foreach ($cogsRows as $cr) {
            $pid = (int) $cr['product_id'];
            if (! isset($buyPriceMap[$pid])) {
                $buyPriceMap[$pid] = (float) $cr['avg_cogs'];
            }
        }
        // Fallback: kalau produk belum pernah terjual, pakai purchase_price + shrinkage_cost.
        $fallback = $this->db->table('price_period_products ppp')
            ->select('ppp.product_id, ppp.purchase_price, ppp.shrinkage_percent, p.shrinkage_value, p.shrinkage_type')
            ->join('products p', 'p.id = ppp.product_id', 'left')
            ->where('ppp.price_period_id', $periodId)
            ->get()->getResultArray();
        foreach ($fallback as $bp) {
            $pid = (int) $bp['product_id'];
            if (isset($buyPriceMap[$pid]) && $buyPriceMap[$pid] > 0) {
                continue;
            }
            $pp = (float) $bp['purchase_price'];
            if ($bp['shrinkage_percent'] !== null) {
                $shCost = round($pp * ((float) $bp['shrinkage_percent'] / 100), 4);
            } else {
                $shCost = ($bp['shrinkage_type'] ?? '') === 'PERCENTAGE'
                    ? round($pp * ((float) ($bp['shrinkage_value'] ?? 0) / 100), 4)
                    : (float) ($bp['shrinkage_value'] ?? 0);
            }
            $buyPriceMap[$pid] = $pp + $shCost;
        }

        $rows          = [];
        $totalShrinkV  = 0;
        foreach ($products as $p) {
            $pid = $p['product_id'];
            $op  = $opnames[$pid] ?? null;

            $stokAwal   = (float) ($op['stok_awal'] ?? 0);
            $pembelian  = (float) ($op['pembelian'] ?? 0);
            $stokAktual = (float) ($op['stok_aktual'] ?? 0);
            $terjual    = $p['qty'];

            // Saldo buku = stok awal (fisik, termasuk free goods) - terjual.
            // Kolom pembelian hanya catatan jumlah order, tidak ditambah ke stok.
            $stokBuku     = $stokAwal - $terjual;
            // Audit F1: hanya baris yang stok aktualnya SUDAH diinput (tidak NULL) yang dihitung susutnya.
            // Baris opname dengan stok aktual kosong = belum dihitung, bukan "habis".
            $hasOpname    = $op !== null && $op['stok_aktual'] !== null;
            // Shrinkage = Aktual - Buku (negatif berarti susut/hilang)
            $shrinkQty    = $hasOpname ? $stokAktual - $stokBuku : 0;
            $buyPrice     = $buyPriceMap[$pid] ?? 0;
            $shrinkValue  = $hasOpname ? $shrinkQty * $buyPrice : 0;
            $totalShrinkV += $shrinkValue;

            // Valuasi stok @ harga beli periode ini.
            $bukuValue   = $stokBuku * $buyPrice;
            $aktualValue = $hasOpname ? $stokAktual * $buyPrice : 0;
            // Laba/rugi: Qty = Aktual + Shrinkage ; Value = Qty × COGS (sesuai rumus Excel)
            $labaRugiQty = $hasOpname ? $stokAktual + $shrinkQty : 0;
            $labaRugi    = $hasOpname ? $labaRugiQty * $buyPrice : 0;

            $rows[] = [
                'product_id'   => $pid,
                'code'         => $p['code'],
                'name'         => $p['name'],
                'unit'         => $p['unit'],
                'stok_awal'    => $stokAwal,
                'pembelian'    => $pembelian,
                'terjual'      => $terjual,
                'stok_buku'    => round($stokBuku, 2),
                'buku_value'   => round($bukuValue, 2),
                'stok_aktual'  => $hasOpname ? $stokAktual : null,
                'aktual_value' => round($aktualValue, 2),
                'has_opname'   => $hasOpname,
                'shrink_qty'   => round($shrinkQty, 2),
                'shrink_value' => round($shrinkValue, 2),
                'laba_rugi_qty' => round($labaRugiQty, 2),
                'laba_rugi'    => round($labaRugi, 2),
                'buy_price'    => $buyPrice,
                'notes'        => $op['notes'] ?? '',
            ];
        }

        return [
            'rows'                => $rows,
            'total_shrink_value'  => round($totalShrinkV, 2),
            'n_input'             => count(array_filter($rows, static fn ($r) => $r['has_opname'])),
            'n_pending'           => count(array_filter($rows, static fn ($r) => ! $r['has_opname'])),
        ];
    }

    /** Daftar order pada satu periode untuk dropdown filter. */
    public function orderOptions(int $periodId): array
    {
        return $this->db->table('sales_orders so')
            ->select('so.id, so.order_no, so.order_date, a.nama_account as customer, so.grand_total')
            ->join('accounts a', 'a.id = so.account_id', 'left')
            ->where('so.price_period_id', $periodId)
            ->where('so.deleted_at', null)
            ->whereIn('so.status', $this->validStatuses)
            ->orderBy('so.order_date', 'DESC')
            ->orderBy('so.id', 'DESC')
            ->get()->getResultArray();
    }

    /** Daftar produk aktif untuk form opname. */
    public function productOptions(): array
    {
        return (new ProductModel())
            ->select('id, product_code, product_name, unit')
            ->where('status', 'aktif')
            ->orderBy('product_name', 'ASC')
            ->findAll();
    }

    public function saveOpname(int $periodId, array $rows, ?int $userId = null): void
    {
        $this->opnameModel->replaceForPeriod($periodId, $rows, $userId);
    }
}
