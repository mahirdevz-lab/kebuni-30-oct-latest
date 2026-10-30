<?php

namespace App\Models;

use CodeIgniter\Model;

class FinancialTransactionModel extends Model
{
    protected $table         = 'financial_transactions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'transaction_date', 'type', 'category_id', 'amount', 'description',
        'reference_type', 'reference_id', 'reference_seq', 'account_id', 'supplier_id',
        'attachment_path', 'proof_label', 'cash_account_id', 'created_by',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'transaction_date' => 'required|valid_date',
        'type'             => 'required|in_list[INCOME,EXPENSE]',
        'amount'           => 'required|decimal|greater_than[0]',
        'reference_type'   => 'required|in_list[SALE,MANUAL_INCOME,MANUAL_EXPENSE,RECEIVABLE_PAYMENT,PAYABLE_PAYMENT,TRANSFER_OUT,TRANSFER_IN,POS_PAYMENT,POS_REFUND,POS_RETURN_REFUND,QRIS_MDR,QRIS_SETTLEMENT]',
    ];

    /**
     * Catat 1 baris ledger dari sebuah business event (SALE, RECEIVABLE_PAYMENT, dst).
     * Idempotent: karena ada unique key (reference_type, reference_id, type) di tabel,
     * event yang sama tidak akan pernah tercatat dobel walau method ini terpanggil ulang.
     */
    public function recordFromReference(string $referenceType, ?int $referenceId, array $data, int $seq = 0): ?int
    {
        if ($referenceId !== null) {
            $existing = $this->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('type', $data['type'])
                ->where('reference_seq', $seq)
                ->first();

            if ($existing) {
                return (int) $existing['id'];
            }
        }

        $this->insert($data + [
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'reference_seq'  => $seq,
        ]);

        return $this->getInsertID() ?: null;
    }

    public function scopeList(string $type, ?string $search = null, ?string $from = null, ?string $to = null, ?int $categoryId = null, ?int $cashAccountId = null)
    {
        $builder = $this->select('financial_transactions.*, financial_categories.name as category_name, cash_accounts.name as account_name')
            ->join('financial_categories', 'financial_categories.id = financial_transactions.category_id', 'left')
            ->join('cash_accounts', 'cash_accounts.id = financial_transactions.cash_account_id', 'left')
            ->where('financial_transactions.type', $type)
            ->groupStart()
                ->whereNotIn('financial_transactions.reference_type', ['TRANSFER_OUT', 'TRANSFER_IN'])
                ->orWhere('financial_transactions.reference_type IS NULL')
            ->groupEnd()
            ->where('LOWER(TRIM(COALESCE(financial_categories.name, ""))) !=', 'pemindahan dana')
            ->orderBy('financial_transactions.transaction_date', 'DESC')
            ->orderBy('financial_transactions.id', 'DESC');

        if ($search) {
            $builder->like('financial_transactions.description', $search);
        }

        if ($from) {
            $builder->where('financial_transactions.transaction_date >=', $from);
        }

        if ($to) {
            $builder->where('financial_transactions.transaction_date <=', $to);
        }

        if ($categoryId) {
            $builder->where('financial_transactions.category_id', $categoryId);
        }

        if ($cashAccountId) {
            $builder->where('financial_transactions.cash_account_id', $cashAccountId);
        }

        return $builder;
    }

    public function sumByTypeBetween(string $type, string $from, string $to): float
    {
        $row = $this->select('COALESCE(SUM(financial_transactions.amount), 0) as total')
            ->join('financial_categories', 'financial_categories.id = financial_transactions.category_id', 'left')
            ->where('financial_transactions.type', $type)
            ->whereNotIn('financial_transactions.reference_type', ['TRANSFER_OUT', 'TRANSFER_IN'])
            ->where('LOWER(TRIM(COALESCE(financial_categories.name, ""))) !=', 'pemindahan dana')
            ->where('financial_transactions.transaction_date >=', $from)
            ->where('financial_transactions.transaction_date <=', $to)
            ->first();

        return (float) ($row['total'] ?? 0);
    }

    /**
     * Series arus kas bulanan (income vs expense) untuk chart dashboard.
     * Return: [['month' => 'YYYY-MM', 'income' => float, 'expense' => float], ...]
     */
    public function monthlyCashFlow(string $from, string $to): array
    {
        $rows = $this->select("
                DATE_FORMAT(financial_transactions.transaction_date, '%Y-%m') as month,
                financial_transactions.type as type,
                SUM(financial_transactions.amount) as total
            ")
            ->join('financial_categories', 'financial_categories.id = financial_transactions.category_id', 'left')
            ->whereNotIn('financial_transactions.reference_type', ['TRANSFER_OUT', 'TRANSFER_IN'])
            ->where('LOWER(TRIM(COALESCE(financial_categories.name, ""))) !=', 'pemindahan dana')
            ->where('financial_transactions.transaction_date >=', $from)
            ->where('financial_transactions.transaction_date <=', $to)
            ->groupBy('month, type')
            ->orderBy('month', 'ASC')
            ->findAll();

        $series = [];
        foreach ($rows as $row) {
            $month = $row['month'];
            if (! isset($series[$month])) {
                $series[$month] = ['month' => $month, 'income' => 0.0, 'expense' => 0.0];
            }
            $series[$month][strtolower($row['type'])] = (float) $row['total'];
        }

        return array_values($series);
    }
}

