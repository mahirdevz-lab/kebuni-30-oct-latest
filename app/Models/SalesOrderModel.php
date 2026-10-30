<?php

namespace App\Models;

use CodeIgniter\Model;

class SalesOrderModel extends Model
{
    protected $table            = 'sales_orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $allowedFields = [
        'order_no', 'order_date', 'account_id', 'sales_id', 'price_period_id', 'payment_term_id', 'status', 'notes',
        'labour_counts_snapshot', 'total_qty', 'total_labour_cost', 'labour_cost_per_kg', 'total_cogs',
        'grand_total', 'grand_total_cash', 'estimated_profit', 'estimated_profit_percent',
        'commission_percent', 'commission_amount', 'commission_paid_amount', 'commission_status',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * List / monitoring Sales Order.
     *
     * Dipakai oleh:
     * SalesOrderService::paginate()
     */
    public function scopeList(?string $search = null, ?string $status = null, ?string $from = null, ?string $to = null)
    {
        $builder = $this->select('sales_orders.*, accounts.nama_account')
            ->join('accounts', 'accounts.id = sales_orders.account_id')
            ->orderBy('sales_orders.order_date', 'DESC')
            ->orderBy('sales_orders.id', 'DESC');
 
        if ($search) {
            $builder->groupStart()
                ->like('sales_orders.order_no', $search)
                ->orLike('accounts.nama_account', $search)
                ->groupEnd();
        }
 
        if ($status) {
            $builder->where('sales_orders.status', $status);
        }
 
        if ($from) {
            $builder->where('sales_orders.order_date >=', $from);
        }
 
        if ($to) {
            $builder->where('sales_orders.order_date <=', $to);
        }
 
        return $builder;
    }

    /**
     * Ambil Sales Order beserta relasi header.
     *
     * Dipakai oleh:
     * SalesOrderService::findWithRelations()
     */
    public function findWithRelations(int $id): ?array
    {
        return $this->select('sales_orders.*, accounts.nama_account, accounts.alamat as account_alamat, accounts.nomor_telepon as account_telepon,
                payment_terms.name as payment_term_name, payment_terms.days as payment_term_days, payment_terms.daily_interest_percent,
                price_periods.period_code, price_periods.name as price_period_name, sales.name as sales_name')
            ->join('accounts', 'accounts.id = sales_orders.account_id')
            ->join('payment_terms', 'payment_terms.id = sales_orders.payment_term_id')
            ->join('price_periods', 'price_periods.id = sales_orders.price_period_id')
            ->join('sales', 'sales.id = sales_orders.sales_id', 'left')
            ->where('sales_orders.id', $id)
            ->first();
    }

    /**
     * Generate nomor PO.
     *
     * Format:
     * PO-YYYYMM-0001
     */
    public function generateOrderNo(): string
    {
        $prefix = 'PO-' . date('Ym') . '-';

        $last = $this
            ->withDeleted()
            ->like('order_no', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->first();

        if (! $last) {
            return $prefix . '0001';
        }

        $lastNumber = (int) substr($last['order_no'], -4);

        return $prefix . str_pad(
            $lastNumber + 1,
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Cari berdasarkan nomor PO.
     */
    public function findByOrderNo(string $orderNo): ?array
    {
        return $this
            ->where('order_no', $orderNo)
            ->first();
    }

    /**
     * Ambil PO berdasarkan account.
     */
    public function getByAccount(int $accountId): array
    {
        return $this
            ->where('account_id', $accountId)
            ->orderBy('order_date', 'DESC')
            ->findAll();
    }

    /**
     * Filter berdasarkan status.
     */
    public function byStatus(string $status)
    {
        return $this->where('status', $status);
    }

    /**
     * Update status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, [
            'status' => $status,
        ]);
    }

    /**
     * Ambil labour snapshot sebagai array.
     */
    public function getLabourSnapshot(int $id): array
    {
        $order = $this->find($id);

        if (! $order || empty($order['labour_counts_snapshot'])) {
            return [];
        }

        return json_decode(
            $order['labour_counts_snapshot'],
            true
        ) ?: [];
    }

    /**
     * Simpan labour snapshot.
     */
    public function setLabourSnapshot(
        int $id,
        array $labourCounts
    ): bool {
        return $this->update($id, [
            'labour_counts_snapshot' => json_encode(
                $labourCounts,
                JSON_UNESCAPED_UNICODE
            ),
        ]);
    }

    /**
     * Total nilai PO berdasarkan status.
     */
    public function getTotalByStatus(string $status): float
    {
        $result = $this
            ->selectSum('grand_total', 'total')
            ->where('status', $status)
            ->first();

        return (float) ($result['total'] ?? 0);
    }
}
