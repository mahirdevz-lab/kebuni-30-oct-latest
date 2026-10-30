<?php

namespace App\Models;

use CodeIgniter\Model;

class ReceivableModel extends Model
{
    protected $table         = 'receivables';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'account_id', 'transaction_id', 'invoice_number', 'transaction_date',
        'due_date', 'total_amount', 'paid_amount', 'remaining_amount', 'status',
    ];
    protected $useTimestamps = true;

    public function scopeList(?string $search = null, ?string $status = null)
    {
        $this->syncOverdue();

        $builder = $this->select('receivables.*, accounts.nama_account')
            ->join('accounts', 'accounts.id = receivables.account_id')
            ->orderBy('receivables.due_date', 'ASC');

        if ($search) {
            $builder->groupStart()
                ->like('receivables.invoice_number', $search)
                ->orLike('accounts.nama_account', $search)
                ->groupEnd();
        }

        if ($status) {
            $builder->where('receivables.status', $status);
        }

        return $builder;
    }

    public function findByInvoice(string $invoiceNumber): ?array
    {
        return $this->where('invoice_number', $invoiceNumber)->first();
    }

    /**
     * Buat piutang baru dari Sales Order yang menggunakan Payment Term kredit (days > 0).
     * Idempotent terhadap invoice_number (= order_no) agar re-run/complete ganda tidak dobel.
     */
    public function createFromSalesOrder(array $order, int $termDays): array
    {
        $existing = $this->findByInvoice($order['order_no']);
        if ($existing) {
            return $existing;
        }

        $totalAmount = (float) $order['grand_total'];

        $this->insert([
            'account_id'       => (int) $order['account_id'],
            'invoice_number'   => $order['order_no'],
            'transaction_date' => $order['order_date'],
            'due_date'         => date('Y-m-d', strtotime($order['order_date'] . " +{$termDays} days")),
            'total_amount'     => $totalAmount,
            'paid_amount'      => 0,
            'remaining_amount' => $totalAmount,
            'status'           => 'UNPAID',
        ]);

        return $this->find($this->getInsertID());
    }

    /**
     * Catat pembayaran (bisa cicil/partial) terhadap 1 piutang.
     * Return: ['success' => bool, 'message' => string, 'receivable' => array|null]
     */
    public function applyPayment(int $id, float $amount): array
    {
        $receivable = $this->find($id);

        if (! $receivable) {
            return ['success' => false, 'message' => 'Piutang tidak ditemukan.'];
        }

        if ($receivable['status'] === 'PAID') {
            return ['success' => false, 'message' => 'Piutang ini sudah lunas.'];
        }

        if ($amount <= 0 || $amount > (float) $receivable['remaining_amount']) {
            return ['success' => false, 'message' => 'Jumlah pembayaran tidak valid (melebihi sisa piutang).'];
        }

        $paidAmount      = (float) $receivable['paid_amount'] + $amount;
        $remainingAmount = (float) $receivable['total_amount'] - $paidAmount;
        $status          = $remainingAmount <= 0 ? 'PAID' : 'PARTIAL';

        $this->update($id, [
            'paid_amount'      => $paidAmount,
            'remaining_amount' => max($remainingAmount, 0),
            'status'           => $status,
        ]);

        return ['success' => true, 'message' => 'Pembayaran berhasil dicatat.', 'receivable' => $this->find($id)];
    }

    public function totalOutstanding(): float
    {
        $row = $this->selectSum('remaining_amount', 'total')
            ->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
            ->first();

        return (float) ($row['total'] ?? 0);
    }

    /**
     * Sinkronkan status UNPAID/PARTIAL yang sudah lewat due_date menjadi OVERDUE.
     * Dipanggil setiap kali listing diakses (bukan cron), cukup untuk skala aplikasi ini.
     */
    public function syncOverdue(): void
    {
        $this->db->table('receivables')
            ->whereIn('status', ['UNPAID', 'PARTIAL'])
            ->where('due_date <', date('Y-m-d'))
            ->update(['status' => 'OVERDUE']);
    }
}
