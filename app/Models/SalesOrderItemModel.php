<?php

namespace App\Models;

use CodeIgniter\Model;

class SalesOrderItemModel extends Model
{
    protected $table            = 'sales_order_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'sales_order_id',
        'product_id',
        'product_code_snapshot',
        'product_name_snapshot',
        'unit_snapshot',
        'transport_route_id',
        'destination_label_snapshot',
        'qty',
        'purchase_price',
        'packing_cost',
        'total_packing_pcs',
        'packing_breakdown_json', 'transport_breakdown_json', 'opex_breakdown_json', 'opex_cost', 'cost_of_fund',
        'labour_cost',
        'shrinkage_cost',
        'opex_ho',
        'opex_sukabumi',
        'opex_destination',
        'via_sukabumi_warehouse',
        'cogs_sk',
        'transport_cost',
        'cogs_destination',
        'tier_name_snapshot',
        'markup_percent',
        'cash_price',
        'interest_amount',
        'final_price',
        'manual_price',
        'selling_price',
        'subtotal_cash',
        'subtotal_final',
        'notes',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Ambil semua item berdasarkan Sales Order.
     *
     * Dipakai oleh:
     * SalesOrderService::getItems()
     * SalesOrderService::submit()
     */
    public function getByOrder(int $orderId): array
    {
        return $this
            ->where('sales_order_id', $orderId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Replace seluruh item dalam Sales Order.
     *
     * Dipakai ketika:
     * - Create Draft
     * - Update Draft
     *
     * Transaction ditangani oleh SalesOrderService,
     * jadi model TIDAK membuka transaction baru.
     */
    public function replaceForOrder(
        int $orderId,
        array $items
    ): bool {
        // Hapus item lama
        $this
            ->where('sales_order_id', $orderId)
            ->delete();

        // Tidak ada item baru
        if (empty($items)) {
            return true;
        }

        $rows = [];

        foreach ($items as $item) {
            $rows[] = [
                'sales_order_id'             => $orderId,

                'product_id'                 => $item['product_id'] ?? null,
                'product_code_snapshot'      => $item['product_code_snapshot'] ?? null,
                'product_name_snapshot'      => $item['product_name_snapshot'] ?? null,
                'unit_snapshot'              => $item['unit_snapshot'] ?? null,

                'transport_route_id'         => $item['transport_route_id'] ?? null,
                'destination_label_snapshot' => $item['destination_label_snapshot'] ?? null,

                'qty'                        => $item['qty'] ?? 0,
                'purchase_price'             => $item['purchase_price'] ?? 0,

                'packing_cost'               => $item['packing_cost'] ?? 0,
                'total_packing_pcs'          => $item['total_packing_pcs'] ?? 0,

                'packing_breakdown_json'     =>
                    $item['packing_breakdown_json'] ?? null,

                'transport_breakdown_json'   =>
                    $item['transport_breakdown_json'] ?? null,
                'opex_breakdown_json'        =>
                    $item['opex_breakdown_json'] ?? null,
                'opex_cost'                  => $item['opex_cost'] ?? 0,
                'cost_of_fund'               => $item['cost_of_fund'] ?? 0,

                'labour_cost'                => $item['labour_cost'] ?? 0,
                'shrinkage_cost'             => $item['shrinkage_cost'] ?? 0,

                'opex_ho'                    => $item['opex_ho'] ?? 0,
                'opex_sukabumi'              => $item['opex_sukabumi'] ?? 0,
                'opex_destination'           => $item['opex_destination'] ?? 0,
                'via_sukabumi_warehouse'     => $item['via_sukabumi_warehouse'] ?? 1,

                'cogs_sk'                    => $item['cogs_sk'] ?? 0,
                'transport_cost'             => $item['transport_cost'] ?? 0,
                'cogs_destination'           => $item['cogs_destination'] ?? 0,

                'tier_name_snapshot'         => $item['tier_name_snapshot'] ?? null,
                'markup_percent'             => $item['markup_percent'] ?? 0,

                'cash_price'                 => $item['cash_price'] ?? 0,
                'interest_amount'            => $item['interest_amount'] ?? 0,
                'final_price'                => $item['final_price'] ?? 0,

                'manual_price'               => $item['manual_price'] ?? null,
                'selling_price'              => $item['selling_price'] ?? 0,

                'subtotal_cash'              => $item['subtotal_cash'] ?? 0,
                'subtotal_final'             => $item['subtotal_final'] ?? 0,

                'notes'                      => $item['notes'] ?? null,
            ];
        }

        return (bool) $this->insertBatch($rows);
    }

    /**
     * Ambil breakdown packing dalam bentuk array.
     */
    public function getPackingBreakdown(int $id): array
    {
        $item = $this->find($id);

        if (! $item || empty($item['packing_breakdown_json'])) {
            return [];
        }

        return json_decode(
            $item['packing_breakdown_json'],
            true
        ) ?: [];
    }

    /**
     * Simpan breakdown packing.
     */
    public function setPackingBreakdown(
        int $id,
        array $packingBreakdown
    ): bool {
        return $this->update($id, [
            'packing_breakdown_json' => json_encode(
                $packingBreakdown,
                JSON_UNESCAPED_UNICODE
            ),
        ]);
    }

    /**
     * Total quantity berdasarkan Sales Order.
     */
    public function getTotalQty(int $orderId): float
    {
        $result = $this
            ->selectSum('qty', 'total')
            ->where('sales_order_id', $orderId)
            ->first();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Total COGS berdasarkan Sales Order.
     */
    public function getTotalCogs(int $orderId): float
    {
        $result = $this
            ->selectSum('cogs_destination', 'total')
            ->where('sales_order_id', $orderId)
            ->first();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Total cash.
     */
    public function getTotalCash(int $orderId): float
    {
        $result = $this
            ->selectSum('subtotal_cash', 'total')
            ->where('sales_order_id', $orderId)
            ->first();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Total final.
     */
    public function getTotalFinal(int $orderId): float
    {
        $result = $this
            ->selectSum('subtotal_final', 'total')
            ->where('sales_order_id', $orderId)
            ->first();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Ambil item berdasarkan transport route.
     */
    public function getByTransportRoute(int $routeId): array
    {
        return $this
            ->where('transport_route_id', $routeId)
            ->orderBy('sales_order_id', 'DESC')
            ->findAll();
    }

    /**
     * Ambil item berdasarkan produk.
     */
    public function getByProduct(int $productId): array
    {
        return $this
            ->where('product_id', $productId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /** Status order yang dianggap valid untuk analisa margin (Draft & Cancelled diabaikan). */
    protected array $validStatuses = ['Submitted', 'Approved', 'Processing', 'Completed'];

    /**
     * Agregat omzet & COGS per tanggal order, dipakai untuk moving average margin trend.
     * Return: [['order_date' => 'Y-m-d', 'omzet' => float, 'total_cogs' => float, 'qty' => float], ...]
     */
    public function dailyMargin(?string $from = null, ?string $to = null, ?string $status = null, ?int $categoryId = null): array
    {
        $builder = $this->db->table('sales_order_items soi')
            ->select("
                so.order_date,
                SUM(soi.subtotal_final) as omzet,
                SUM(soi.cogs_destination * soi.qty) as total_cogs,
                SUM(soi.qty) as qty
            ")
            ->join('sales_orders so', 'so.id = soi.sales_order_id')
            ->where('so.deleted_at', null)
            ->groupBy('so.order_date')
            ->orderBy('so.order_date', 'ASC');

        $this->applyStatusFilter($builder, $status);
        $this->applyDateFilter($builder, $from, $to);

        if ($categoryId) {
            $builder->join('products p', 'p.id = soi.product_id')
                ->where('p.category_id', $categoryId);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Agregat margin per product, dipakai untuk ranking barang margin rendah/tinggi.
     */
    public function productMargin(?string $from = null, ?string $to = null, ?string $status = null, ?int $categoryId = null): array
    {
        $builder = $this->db->table('sales_order_items soi')
            ->select("
                soi.product_id,
                MAX(soi.product_code_snapshot) as product_code,
                MAX(soi.product_name_snapshot) as product_name,
                MAX(soi.unit_snapshot) as unit,
                SUM(soi.qty) as total_qty,
                SUM(soi.subtotal_final) as omzet,
                SUM(soi.cogs_destination * soi.qty) as total_cogs,
                COUNT(DISTINCT soi.sales_order_id) as jumlah_order
            ")
            ->join('sales_orders so', 'so.id = soi.sales_order_id')
            ->where('so.deleted_at', null)
            ->groupBy('soi.product_id')
            ->orderBy('omzet', 'DESC');

        $this->applyStatusFilter($builder, $status);
        $this->applyDateFilter($builder, $from, $to);

        if ($categoryId) {
            $builder->join('products p', 'p.id = soi.product_id')
                ->where('p.category_id', $categoryId);
        }

        return $builder->get()->getResultArray();
    }

    protected function applyStatusFilter($builder, ?string $status): void
    {
        if ($status) {
            $builder->where('so.status', $status);
        } else {
            $builder->whereIn('so.status', $this->validStatuses);
        }
    }

    protected function applyDateFilter($builder, ?string $from, ?string $to): void
    {
        if ($from) {
            $builder->where('so.order_date >=', $from);
        }
        if ($to) {
            $builder->where('so.order_date <=', $to);
        }
    }
}
