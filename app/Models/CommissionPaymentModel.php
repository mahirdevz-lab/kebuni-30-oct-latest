<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Riwayat pembayaran komisi sales per order (cicilan / lunas).
 */
class CommissionPaymentModel extends Model
{
    protected $table            = 'commission_payments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'sales_order_id', 'amount', 'payment_date', 'notes', 'proof_path', 'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Riwayat pembayaran untuk satu order, terbaru dulu.
     */
    public function getByOrder(int $orderId): array
    {
        return $this->where('sales_order_id', $orderId)
            ->orderBy('payment_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Total dibayar untuk satu order.
     */
    public function totalPaid(int $orderId): float
    {
        $row = $this->selectSum('amount', 'total')
            ->where('sales_order_id', $orderId)
            ->get()
            ->getRowArray();

        return (float) ($row['total'] ?? 0);
    }
}
