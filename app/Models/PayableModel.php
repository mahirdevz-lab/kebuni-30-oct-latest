<?php

namespace App\Models;

use CodeIgniter\Model;

class PayableModel extends Model
{
    protected $table         = 'payables';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'supplier_id', 'transaction_id', 'invoice_number', 'transaction_date',
        'due_date', 'total_amount', 'paid_amount', 'remaining_amount', 'status',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'supplier_id'      => 'required|integer',
        'invoice_number'   => 'required|max_length[50]|is_unique[payables.invoice_number,id,{id}]',
        'transaction_date' => 'required|valid_date',
        'total_amount'     => 'required|decimal|greater_than[0]',
    ];

    protected $validationMessages = [
        'invoice_number' => ['is_unique' => 'Nomor invoice sudah dipakai.'],
    ];

    public function scopeList(?string $search = null, ?string $status = null)
    {
        $this->syncOverdue();

        $builder = $this->select('payables.*, suppliers.nama_supplier')
            ->join('suppliers', 'suppliers.id = payables.supplier_id')
            ->orderBy('payables.due_date', 'ASC');

        if ($search) {
            $builder->groupStart()
                ->like('payables.invoice_number', $search)
                ->orLike('suppliers.nama_supplier', $search)
                ->groupEnd();
        }

        if ($status) {
            $builder->where('payables.status', $status);
        }

        return $builder;
    }

    public function create(array $data): array
    {
        $data['due_date']         = $data['due_date'] ?: null;
        $data['paid_amount']      = 0;
        $data['remaining_amount'] = (float) $data['total_amount'];
        $data['status']           = 'UNPAID';

        if (! $this->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->errors()];
        }

        return ['success' => true, 'message' => 'Hutang berhasil dicatat.', 'id' => $this->getInsertID()];
    }

    /**
     * Catat pembayaran (bisa cicil/partial) terhadap 1 hutang.
     */
    public function applyPayment(int $id, float $amount): array
    {
        $payable = $this->find($id);

        if (! $payable) {
            return ['success' => false, 'message' => 'Hutang tidak ditemukan.'];
        }

        if ($payable['status'] === 'PAID') {
            return ['success' => false, 'message' => 'Hutang ini sudah lunas.'];
        }

        if ($amount <= 0 || $amount > (float) $payable['remaining_amount']) {
            return ['success' => false, 'message' => 'Jumlah pembayaran tidak valid (melebihi sisa hutang).'];
        }

        $paidAmount      = (float) $payable['paid_amount'] + $amount;
        $remainingAmount = (float) $payable['total_amount'] - $paidAmount;
        $status          = $remainingAmount <= 0 ? 'PAID' : 'PARTIAL';

        $this->update($id, [
            'paid_amount'      => $paidAmount,
            'remaining_amount' => max($remainingAmount, 0),
            'status'           => $status,
        ]);

        return ['success' => true, 'message' => 'Pembayaran berhasil dicatat.', 'payable' => $this->find($id)];
    }

    public function totalOutstanding(): float
    {
        $row = $this->selectSum('remaining_amount', 'total')
            ->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
            ->first();

        return (float) ($row['total'] ?? 0);
    }

    public function syncOverdue(): void
    {
        $this->db->table('payables')
            ->whereIn('status', ['UNPAID', 'PARTIAL'])
            ->where('due_date <', date('Y-m-d'))
            ->update(['status' => 'OVERDUE']);
    }
}
