<?php

namespace App\Services;

use App\Models\PaymentTermModel;
use App\Models\PricePeriodPackingModel;
use App\Models\PricePeriodProductModel;
use App\Models\PricePeriodTransportModel;
use App\Models\ProductModel;
use App\Models\ProductPackingRecipeModel;
use App\Models\PricePeriodLabourRateModel;
use App\Models\PackingMaterialModel;
use App\Models\PricePeriodOpexAreaModel;
use App\Models\TransportRouteModel;

/**
 * PriceCalculationService — Pricing Engine.
 *
 * Mengikuti urutan kalkulasi WAJIB sesuai spesifikasi (section 24):
 * 1. Ambil Price Period            6. Ambil Transport Cost      11. Tentukan Pricing Tier
 * 2. Ambil Purchase Price          7. Hitung COGS Destination   12. Ambil Markup
 * 3. Ambil Packing Price           8. Hitung total volume        13. Hitung Cash Selling Price
 * 4. Hitung Packing Cost/Kg        9. -                          14. Ambil Payment Term
 * 5. Hitung Shrinkage                                            15-18. Compound Interest -> Final Price -> Subtotal
 *
 * PENTING: Service ini TIDAK menyimpan apapun ke database — murni kalkulasi.
 * Snapshot harga baru terjadi ketika PO/Taking Order benar-benar dibuat (fase berikutnya),
 * dengan menyalin output service ini apa adanya ke tabel sales_order_items.
 */
class PriceCalculationService
{
    protected PricePeriodProductModel $periodProductModel;
    protected PricePeriodPackingModel $periodPackingModel;
    protected PricePeriodTransportModel $periodTransportModel;
    protected ProductModel $productModel;
    protected PackingMaterialModel $packingMaterialModel;
    protected ProductPackingRecipeModel $recipeModel;
    protected PaymentTermModel $paymentTermModel;
    protected PricingTierService $tierService;
    protected PaymentTermService $paymentTermService;
    protected PricePeriodLabourRateModel $labourRateModel;
    protected PricePeriodProductModel $ppProductModel;
    protected PricePeriodOpexAreaModel $opexAreaModel;
    protected TransportRouteModel $transportRouteModel;
    protected \CodeIgniter\Database\BaseConnection $db;

    /** Satuan pembulatan harga jual akhir (Rp). Ubah sesuai kebijakan, mis. 1/10/50/100. */
    protected int $roundingUnit = 1;

    public function __construct()
    {
        $this->db                   = \Config\Database::connect();
        $this->periodProductModel   = new PricePeriodProductModel();
        $this->labourRateModel = new PricePeriodLabourRateModel();
        $this->packingMaterialModel  = new PackingMaterialModel();
        $this->periodPackingModel   = new PricePeriodPackingModel();
        $this->periodTransportModel = new PricePeriodTransportModel();
        $this->productModel         = new ProductModel();
        $this->recipeModel          = new ProductPackingRecipeModel();
        $this->paymentTermModel     = new PaymentTermModel();
        $this->periodLabourRateModel = new PricePeriodLabourRateModel();
        $this->tierService          = new PricingTierService();
        $this->paymentTermService   = new PaymentTermService();
        $this->opexAreaModel        = new PricePeriodOpexAreaModel();
        $this->transportRouteModel  = new TransportRouteModel();
    }

    protected function roundPrice(float $value): float
    {
        // Selalu dibulatkan ke ATAS agar harga jual tidak pernah jatuh di bawah modal karena pembulatan
        if ($this->roundingUnit <= 1) {
            return ceil($value);
        }

        return ceil($value / $this->roundingUnit) * $this->roundingUnit;
    }

    public function calculateOrder(array $items, int $paymentTermId, array $labourCounts = [], int $legacyMale = 0, int $legacyFemale = 0): array
    {
        if (empty($items)) {
            return [
                'summary' => [
                    'total_qty'                => 0,
                    'total_labour_cost'        => 0,
                    'labour_cost_per_kg'       => 0,
                    'total_cogs'               => 0,
                    'grand_total'              => 0,
                    'estimated_profit'         => 0,
                    'estimated_profit_percent' => 0,
                ],
                'lines' => [],
            ];
        }

        $pricePeriodId = (int) ($items[0]['price_period_id'] ?? 0);

        // 1. HITUNG ALOKASI KULI PER ORDER (redesign 2026-10-18)
        // Labour budget dianggarkan per Price Period, dibagi ke item yang
        // dichecklist labour, proporsional terhadap qty masing-masing item.
        $labourBudget = 0.0;
        if ($pricePeriodId > 0) {
            // Defensive: kolom labour_budget mungkin belum ada kalau migrate belum jalan.
            if ($this->db->fieldExists('labour_budget', 'price_periods')) {
                $period = $this->db->table('price_periods')->select('labour_budget')->where('id', $pricePeriodId)->get()->getRowArray();
                $labourBudget = (float) ($period['labour_budget'] ?? 0);
            }
        }

        $checkedQty = 0.0;
        foreach ($items as $item) {
            if (! empty($item['labour_checked'])) {
                $checkedQty += (float) ($item['qty'] ?? 0);
            }
        }

        // labour/kg yang dibebankan ke item yang dichecklist
        $labourCostPerKgChecked = ($labourBudget > 0 && $checkedQty > 0) ? ($labourBudget / $checkedQty) : 0.0;

        // 2. HITUNG TOTAL VOLUME QTY ORDER (KG)
        $totalQty = 0.0;
        foreach ($items as $item) {
            $totalQty += (float) ($item['qty'] ?? 0);
        }

        // Alokasi Beban Kuli per Kg (legacy: rata ke semua item; kini hanya ke yang dichecklist)
        $labourCostPerKg = $labourCostPerKgChecked;

        // 3. KALKULASI SETIAP ITEM MENGGUNAKAN calculateItem()
        $lines                  = [];
        $totalCogsAll           = 0.0;
        $grandTotalSales        = 0.0;
        $grandTotalSubtotalCash = 0.0;
        $commissionBase         = 0.0; // base komisi: total (harga beli + shrinkage) x qty

        foreach ($items as $item) {
            // Susun DTO parameter untuk calculateItem
            $params = [
                'price_period_id'    => $pricePeriodId,
                'product_id'         => $item['product_id'] ?? 0,
                'qty'                => $item['qty'] ?? 0,
                'payment_term_id'    => $paymentTermId,
                'manual_price'       => $item['manual_price'] ?? null,
                // Kuli: hanya item yang dichecklist yang kena alokasi budget
                'labour_cost_per_kg' => ! empty($item['labour_checked']) ? $labourCostPerKgChecked : 0.0,
                // Cost of Fund: 1% dari total COGS, dicentang per item
                'cost_of_fund_checked' => ! empty($item['cost_of_fund_checked']),
                'via_sukabumi_warehouse' => $item['via_sukabumi_warehouse'] ?? true,
                'custom_packings'    => [],
                'custom_transports'  => [],
                'custom_opex'        => [],
            ];

            // Tautkan Packing (0..N, manual)
            if (! empty($item['packing_ids']) && is_array($item['packing_ids'])) {
                foreach ($item['packing_ids'] as $pId) {
                    $params['custom_packings'][] = ['packing_material_id' => (int) $pId];
                }
            } elseif (! empty($item['packing_id'])) {
                $params['custom_packings'][] = ['packing_material_id' => (int) $item['packing_id']];
            }

            // Tautkan Transport (0..N, manual — multi-leg)
            if (! empty($item['transport_ids']) && is_array($item['transport_ids'])) {
                foreach ($item['transport_ids'] as $tId) {
                    $params['custom_transports'][] = ['transport_route_id' => (int) $tId];
                }
            } elseif (! empty($item['transport_route_id'])) {
                // backward compat: single route lama
                $params['custom_transports'][] = ['transport_route_id' => (int) $item['transport_route_id']];
            }

            // Tautkan Opex (0..N, manual)
            if (! empty($item['opex_ids']) && is_array($item['opex_ids'])) {
                foreach ($item['opex_ids'] as $oId) {
                    $params['custom_opex'][] = ['opex_area_id' => (int) $oId];
                }
            }

            // Jalankan fungsi kalkulasi item utama
            $calcResult = $this->calculateItem($params);

            if (empty($calcResult['errors'])) {
                $totalCogsAll           += $calcResult['total_cogs'];
                $grandTotalSales        += $calcResult['subtotal_final'];
                $grandTotalSubtotalCash += $calcResult['subtotal_cash'];
                $commissionBase         += ($calcResult['purchase_price'] + $calcResult['shrinkage_cost']) * $calcResult['qty'];
            }

            $lines[] = $calcResult;
        }

        $estimatedProfit        = $grandTotalSales - $totalCogsAll;
        $estimatedProfitPercent = $grandTotalSales > 0 ? round(($estimatedProfit / $grandTotalSales) * 100, 2) : 0;

        return [
            'summary' => [
                'total_qty'                => round($totalQty, 2),
                'total_labour_cost'        => round($checkedQty > 0 ? $labourBudget : 0, 2),
                'labour_budget'            => round($labourBudget, 2),
                'labour_cost_per_kg'       => round($labourCostPerKgChecked, 4),
                'total_cogs'               => round($totalCogsAll, 2),
                'grand_total'              => round($grandTotalSales, 2),
                'grand_total_cash'         => round($grandTotalSubtotalCash, 2),
                'estimated_profit'         => round($estimatedProfit, 2),
                'estimated_profit_percent' => $estimatedProfitPercent,
                'commission_base'          => round($commissionBase, 2),
            ],
            'lines' => $lines,
        ];
    }

    /**
     * Hitung Rincian Per Item
     */
    public function calculateItem(array $params): array
    {
        $errors   = [];
        $warnings = [];

        $pricePeriodId    = (int) ($params['price_period_id'] ?? 0);
        $productId        = (int) ($params['product_id'] ?? 0);
        $qty              = (float) ($params['qty'] ?? 0);
        $paymentTermId    = (int) ($params['payment_term_id'] ?? 0);
        $manualPrice      = isset($params['manual_price']) && $params['manual_price'] !== '' ? (float) $params['manual_price'] : null;
        
        $customPackings   = $params['custom_packings'] ?? [];
        $labourCostPerKg  = (float) ($params['labour_cost_per_kg'] ?? 0);
        // Default true: item dianggap lewat Gudang Sukabumi kecuali dinyatakan direct dari petani ke buyer.
        $viaSukabumiWarehouse = ! array_key_exists('via_sukabumi_warehouse', $params) || (bool) $params['via_sukabumi_warehouse'];

        if ($qty <= 0) {
            $errors[] = 'Quantity harus lebih besar dari 0.';
        }

        // 1-2. Purchase Price dari Price Period
        $product = $this->productModel->find($productId);
        if (! $product) {
            $errors[] = 'Product tidak ditemukan.';
        }

        $periodProduct = $this->periodProductModel->findByPeriodAndProduct($pricePeriodId, $productId);
        if (! $periodProduct) {
            $errors[] = 'Product ini belum memiliki Purchase Price pada Price Period yang dipilih.';
        }

        if (! empty($errors)) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $purchasePrice = (float) $periodProduct['purchase_price'];

        // 3-4. Packing Price & Packing Cost/Kg (Berbasis Unit/Pcs & Capacity)
        $totalPackingCost = 0.0;
        $totalPackingPcs  = 0.0; // Counter total pcs packing
        $packingBreakdown = [];

        if (! empty($customPackings) && is_array($customPackings)) {
            // A. JIKA ADA INPUT PACKING MANUAL PER ITEM ORDER
            foreach ($customPackings as $cp) {
                $materialId = (int) ($cp['packing_material_id'] ?? 0);
                
                $packingMaterial = $this->packingMaterialModel->find($materialId);
                if (! $packingMaterial) {
                    $errors[] = "Packing Material ID {$materialId} tidak ditemukan.";
                    continue;
                }

                $periodPacking = $this->periodPackingModel->findByPeriodAndMaterial($pricePeriodId, $materialId);
                if (! $periodPacking) {
                    $errors[] = "Packing material \"{$packingMaterial['name']}\" belum memiliki harga pada Price Period ini.";
                    continue;
                }

                // Defensive check nama kolom harga
                $pricePerUnit = (float) ($periodPacking['price_per_unit'] ?? $periodPacking['price'] ?? $periodPacking['unit_price'] ?? 0);
                $capacityKg   = ! empty($packingMaterial['capacity_kg']) ? (float) $packingMaterial['capacity_kg'] : null;

                // Tentukan Jumlah Pcs Packing
                if (isset($cp['qty_pcs']) && (float)$cp['qty_pcs'] > 0) {
                    $qtyPcs = (float) $cp['qty_pcs'];
                } elseif ($capacityKg && $capacityKg > 0) {
                    $qtyPcs = ceil($qty / $capacityKg);
                } else {
                    $qtyPcs = 1;
                }

                $itemTotalPackingCost = $qtyPcs * $pricePerUnit;
                $totalPackingCost    += $itemTotalPackingCost;
                $totalPackingPcs     += $qtyPcs;

                $packingBreakdown[] = [
                    'packing_material_id' => $materialId,
                    'packing_code'        => $packingMaterial['code'] ?? '',
                    'packing_name'        => $packingMaterial['name'],
                    'unit'                => $packingMaterial['unit'] ?? 'Pcs',
                    'capacity_kg'         => $capacityKg,
                    'qty_pcs'             => $qtyPcs, // Total Pcs Terpakai untuk Qty Order ini
                    'pcs_per_kg_product'  => $qty > 0 ? round($qtyPcs / $qty, 4) : 0, // Kebutuhan Pcs per 1 Kg Produk
                    'price_per_unit'      => $pricePerUnit,
                    'total_cost'          => $itemTotalPackingCost,
                    'cost_per_kg_product' => $qty > 0 ? round($itemTotalPackingCost / $qty, 4) : 0,
                    'is_custom'           => true
                ];
            }
        } else {
            // B. FALLBACK KE RECIPE PRODUCT DEFAULT
            $recipe = $this->recipeModel->getByProduct($productId);
            foreach ($recipe as $r) {
                $materialId    = (int) $r['packing_material_id'];
                $periodPacking = $this->periodPackingModel->findByPeriodAndMaterial($pricePeriodId, $materialId);

                if (! $periodPacking) {
                    $warnings[] = "Packing material \"{$r['packing_name']}\" belum memiliki harga pada Price Period ini — biaya packing-nya dihitung Rp 0.";
                    continue;
                }

                // Defensive check nama kolom harga
                $pricePerUnit = (float) ($periodPacking['price_per_unit'] ?? $periodPacking['price'] ?? $periodPacking['unit_price'] ?? 0);
                $capacityKg   = ! empty($r['capacity_kg']) ? (float) $r['capacity_kg'] : null;

                if ($capacityKg && $capacityKg > 0) {
                    $qtyPcs = ceil($qty / $capacityKg);
                } else {
                    $qtyPcs = (float) $r['quantity_usage'];
                }

                $itemTotalPackingCost = $qtyPcs * $pricePerUnit;
                $totalPackingCost    += $itemTotalPackingCost;
                $totalPackingPcs     += $qtyPcs;

                $packingBreakdown[] = [
                    'packing_material_id' => $materialId,
                    'packing_code'        => $r['packing_code'] ?? '',
                    'packing_name'        => $r['packing_name'],
                    'unit'                => $r['unit'] ?? 'Pcs',
                    'capacity_kg'         => $capacityKg,
                    'qty_pcs'             => $qtyPcs, // Total Pcs Terpakai untuk Qty Order ini
                    'pcs_per_kg_product'  => $qty > 0 ? round($qtyPcs / $qty, 4) : 0, // Kebutuhan Pcs per 1 Kg Produk
                    'price_per_unit'      => $pricePerUnit,
                    'total_cost'          => $itemTotalPackingCost,
                    'cost_per_kg_product' => $qty > 0 ? round($itemTotalPackingCost / $qty, 4) : 0,
                    'is_custom'           => false
                ];
            }
        }

        if (! empty($errors)) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Konversi Total Biaya Packing ke nilai Per Kg
        $packingCostPerKg = $qty > 0 ? ($totalPackingCost / $qty) : 0;

        // 5. Shrinkage
        $shrinkagePercent = $periodProduct['shrinkage_percent'] !== null
            ? (float) $periodProduct['shrinkage_percent']
            : ((float) $product['shrinkage_value']);
        $shrinkageType = $periodProduct['shrinkage_percent'] !== null ? 'PERCENTAGE' : $product['shrinkage_type'];

        $shrinkageCost = $shrinkageType === 'PERCENTAGE'
            ? round($purchasePrice * ($shrinkagePercent / 100), 4)
            : $shrinkagePercent;

        // 6a. OPEX (0..N, MANUAL — redesign 2026-10-18)
        // User memilih sendiri area OPEX mana yang dipakai dari pool Price Period.
        $customOpex       = $params['custom_opex'] ?? [];
        $opexCostPerKg    = 0.0;
        $opexBreakdown    = [];

        foreach ((array) $customOpex as $co) {
            $opexAreaId = (int) ($co['opex_area_id'] ?? 0);
            if ($opexAreaId <= 0) {
                continue;
            }

            $areaRow = $this->opexAreaModel->findByPeriodAndArea($pricePeriodId, $opexAreaId);
            if (! $areaRow) {
                $warnings[] = "OPEX area ID {$opexAreaId} belum memiliki biaya pada Price Period ini — dihitung Rp 0.";
                continue;
            }

            $areaCost = (float) $areaRow['opex_cost_per_kg'];
            $opexCostPerKg += $areaCost;
            $opexBreakdown[] = [
                'opex_area_id'  => $opexAreaId,
                'area_code'     => $areaRow['area_code'] ?? '',
                'area_name'     => $areaRow['area_name'] ?? ('Opex #' . $opexAreaId),
                'cost_per_kg'   => round($areaCost, 4),
            ];
        }

        // 6b. COGS komponen produk (sebelum transport)
        $cogsSk = $purchasePrice + $packingCostPerKg + $labourCostPerKg + $shrinkageCost + $opexCostPerKg;

        // 7-8. Transport (0..N, MANUAL — multi-leg, redesign 2026-10-18)
        $customTransports   = $params['custom_transports'] ?? [];
        $transportCostPerKg = 0.0;
        $transportBreakdown = [];
        $transportLabels    = [];

        foreach ((array) $customTransports as $ct) {
            $routeId = (int) ($ct['transport_route_id'] ?? 0);
            if ($routeId <= 0) {
                continue;
            }

            $periodTransport = $this->periodTransportModel->findByPeriodAndRoute($pricePeriodId, $routeId);

            if (! $periodTransport) {
                $errors[] = 'Transport route ini belum memiliki harga pada Price Period yang dipilih.';

                return ['errors' => $errors, 'warnings' => $warnings];
            }

            $legCost = (float) $periodTransport['cost_per_kg'];
            $transportCostPerKg += $legCost;
            $transportLabels[] = ($periodTransport['origin'] ?? '') . ' → ' . ($periodTransport['destination_name'] ?? '');
            $transportBreakdown[] = [
                'transport_route_id' => $routeId,
                'origin'             => $periodTransport['origin'] ?? '',
                'destination'        => $periodTransport['destination_name'] ?? '',
                'cost_per_kg'        => round($legCost, 4),
            ];
        }

        $destinationLabel = ! empty($transportLabels)
            ? implode(' + ', $transportLabels)
            : 'Origin (tanpa transport)';

        $cogsDestination = $cogsSk + $transportCostPerKg;

        // Cost of Fund: 1% dari total COGS (paling akhir, setelah semua COGS selesai).
        // Dicentang per item via kolom COF di form.
        $cofChecked = ! empty($params['cost_of_fund_checked']);
        $costOfFund = $cofChecked ? round($cogsDestination * 0.01, 4) : 0.0;
        $cogsDestinationFinal = $cogsDestination + $costOfFund;

        // 9-11. Pricing Tier berdasarkan qty
        $tier = $this->tierService->findForQty($qty);
        if (! $tier) {
            $errors[] = "Tidak ada Pricing Tier yang cocok untuk qty {$qty}.";

            return ['errors' => $errors, 'warnings' => $warnings];
        }
        // Margin flat 15% dari total COGS (tier tetap tampil sebagai info, markup tier tidak dipakai)
        $markupPercent = 15.0;

        // 12-13. Cash Selling Price = COGS final (termasuk Cost of Fund) + 15%
        $cashPrice = $this->roundPrice($cogsDestinationFinal * 1.15);

        // 14-16. Payment Term & Compound Interest -> Final Price
        $paymentTerm = $this->paymentTermModel->find($paymentTermId);
        if (! $paymentTerm) {
            $errors[] = 'Payment Term tidak ditemukan.';

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $finalPrice     = $this->roundPrice($this->paymentTermService->calculateFinalPrice($cashPrice, $paymentTerm));
        $interestAmount = $finalPrice - $cashPrice;

        // Harga yang benar-benar dipakai untuk subtotal & profit
        $recommendedPrice   = $finalPrice;
        $sellingPrice       = $manualPrice ?? $recommendedPrice;
        $isBelowRecommended = $manualPrice !== null && $manualPrice < $recommendedPrice;

        // 17-18. Subtotal & Profit
        $subtotalCash  = $cashPrice * $qty;
        $subtotalFinal = $sellingPrice * $qty;
        $totalCogs     = $cogsDestination * $qty;

        $estimatedProfit        = $subtotalFinal - $totalCogs;
        $estimatedProfitPercent = $subtotalFinal > 0 ? round(($estimatedProfit / $subtotalFinal) * 100, 2) : 0;

        return [
            'errors'                   => [],
            'warnings'                 => $warnings,
            'product'                  => [
                'id'   => $product['id'], 
                'code' => $product['product_code'] ?? '', 
                'name' => $product['product_name'] ?? $product['name'] ?? '', 
                'unit' => $product['unit'] ?? 'Kg'
            ],
            'qty'                      => $qty,
            'destination_label'        => $destinationLabel,
            'purchase_price'           => $purchasePrice,
            'packing_cost'             => round($packingCostPerKg, 4),
            'total_packing_pcs'        => $totalPackingPcs, // Total Pcs Packing Keseluruhan Item ini
            'labour_cost'              => round($labourCostPerKg, 4),
            'packing_breakdown'        => $packingBreakdown,
            'shrinkage_cost'           => $shrinkageCost,
            'via_sukabumi_warehouse'   => $viaSukabumiWarehouse,
            'opex_cost'                => round($opexCostPerKg, 4),
            'opex_breakdown'           => $opexBreakdown,
            'opex_ho'                  => 0, // legacy, dipertahankan agar view lama tidak error
            'opex_sukabumi'            => 0, // legacy
            'opex_destination'         => 0, // legacy
            'cogs_sk'                  => round($cogsSk, 4),
            'transport_cost'           => $transportCostPerKg,
            'transport_breakdown'      => $transportBreakdown,
            'cogs_destination'         => round($cogsDestination, 4),
            'cost_of_fund'             => round($costOfFund, 4),
            'cogs_destination_final'   => round($cogsDestinationFinal, 4),
            'tier_name'                => $tier['name'],
            'markup_percent'           => $markupPercent,
            'cash_price'               => $cashPrice,
            'payment_term_name'        => $paymentTerm['name'],
            'payment_term_days'        => (int) $paymentTerm['days'],
            'interest_amount'          => $interestAmount,
            'final_price'              => $finalPrice,
            'recommended_price'        => $recommendedPrice,
            'manual_price'             => $manualPrice,
            'is_below_recommended'     => $isBelowRecommended,
            'selling_price'            => $sellingPrice,
            'subtotal_cash'            => round($subtotalCash, 2),
            'subtotal_final'           => round($subtotalFinal, 2),
            'total_cogs'               => round($totalCogs, 2),
            'estimated_profit'         => round($estimatedProfit, 2),
            'estimated_profit_percent' => $estimatedProfitPercent,
        ];
    }
}
