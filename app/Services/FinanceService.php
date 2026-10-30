<?php

namespace App\Services;

use App\Models\FinancialCategoryModel;
use App\Models\FinancialTransactionModel;
use App\Models\PayableModel;
use App\Models\PaymentTermModel;
use App\Models\ReceivableModel;
use App\Models\SupplierModel;

/**
 * FinanceService ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â pusat pencatatan arus kas (ledger), piutang & hutang.
 *
 * Prinsip akuntansi yang dipakai (CASH BASIS, sesuai skala aplikasi ini):
 * - Income baru dicatat di ledger (financial_transactions) pada saat UANG BENAR-BENAR DITERIMA.
 * - Sales Order dengan Payment Term Cash (days = 0) yang Completed -> langsung dicatat sebagai
 *   income (reference_type SALE).
 * - Sales Order dengan Payment Term kredit (days > 0) yang Completed -> TIDAK langsung income,
 *   melainkan membuat baris Piutang (receivables). Income baru dicatat saat piutang tsb dibayar
 *   (reference_type RECEIVABLE_PAYMENT), sebesar uang yang benar-benar masuk.
 * - Prinsip yang sama berlaku simetris untuk Hutang (payables) & PAYABLE_PAYMENT di sisi expense.
 * Ini mencegah pendapatan/beban tercatat dobel (sekali saat piutang dibuat, sekali lagi saat dibayar).
 */
class FinanceService
{
    protected FinancialTransactionModel $transactionModel;
    protected FinancialCategoryModel $categoryModel;
    protected ReceivableModel $receivableModel;
    protected PayableModel $payableModel;
    protected PaymentTermModel $paymentTermModel;
    protected SupplierModel $supplierModel;
    protected AuditLogService $audit;

    public function __construct()
    {
        $this->transactionModel = new FinancialTransactionModel();
        $this->categoryModel    = new FinancialCategoryModel();
        $this->receivableModel  = new ReceivableModel();
        $this->payableModel     = new PayableModel();
        $this->paymentTermModel = new PaymentTermModel();
        $this->supplierModel    = new SupplierModel();
        $this->audit            = new AuditLogService();
    }

    // =========================================================
    // INTEGRASI DARI TAKING ORDER / SALES ORDER
    // =========================================================

    /**
     * Dipanggil oleh SalesOrderService::complete() setelah status PO diubah menjadi Completed.
     * $order = row dari sales_orders (harus sudah berstatus Completed).
     */
    public function recordFromCompletedSalesOrder(array $order): array
    {
        $paymentTerm = $this->paymentTermModel->find($order['payment_term_id']);
        $termDays    = $paymentTerm ? (int) $paymentTerm['days'] : 0;

        if ($termDays <= 0) {
            // Payment Term Cash -> uang diterima langsung saat order selesai.
            $db = db_connect();
            $db->transStart();

            $transactionId = $this->transactionModel->recordFromReference('SALE', (int) $order['id'], [
                'transaction_date' => $order['order_date'],
                'type'             => 'INCOME',
                'category_id'      => $this->firstCategoryId('Penjualan', 'INCOME'),
                'amount'           => (float) $order['grand_total'],
                'description'      => 'Penjualan tunai - PO ' . $order['order_no'],
                'account_id'       => (int) $order['account_id'],
                'created_by'       => session()->get('user_id'),
            ]);

            $db->transComplete();

            return ['success' => true, 'message' => 'Transaksi income tercatat (cash).', 'transaction_id' => $transactionId];
        }

        // Payment Term kredit -> catat sebagai Piutang, income menyusul saat dibayar.
        $receivable = $this->receivableModel->createFromSalesOrder($order, $termDays);

        return ['success' => true, 'message' => 'Piutang berhasil dibuat.', 'receivable' => $receivable];
    }

    /** Nomor urut pembayaran berikutnya untuk 1 piutang/hutang (cicilan ke-N), agar tiap cicilan tercatat di ledger. */
    private function nextPaymentSeq(string $referenceType, int $referenceId): int
    {
        return $this->transactionModel
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->countAllResults() + 1;
    }

    private function firstCategoryId(string $name, string $type): ?int
    {
        $row = $this->categoryModel->where('name', $name)->where('type', $type)->first();

        return $row['id'] ?? null;
    }

    // =========================================================
    // DASHBOARD
    // =========================================================

    public function dashboardSummary(?string $from = null, ?string $to = null): array
    {
        $from = $from ?: date('Y-m-01');
        $to   = $to ?: date('Y-m-t');

        $totalIncome  = $this->transactionModel->sumByTypeBetween('INCOME', $from, $to);
        $totalExpense = $this->transactionModel->sumByTypeBetween('EXPENSE', $from, $to);

        return [
            'from'                  => $from,
            'to'                    => $to,
            'total_income'          => $totalIncome,
            'total_expense'         => $totalExpense,
            'net_cash_flow'         => $totalIncome - $totalExpense,
            'total_receivable'      => $this->receivableModel->totalOutstanding(),
            'total_payable'         => $this->payableModel->totalOutstanding(),
        ];
    }

    public function cashFlowChart(?string $from = null, ?string $to = null): array
    {
        $from = $from ?: date('Y-m-d', strtotime('-5 months', strtotime(date('Y-m-01'))));
        $to   = $to ?: date('Y-m-t');

        return $this->transactionModel->monthlyCashFlow($from, $to);
    }

    // =========================================================
    // MANUAL INCOME / EXPENSE
    // =========================================================

    public function paginateTransactions(string $type, ?string $search, ?string $from, ?string $to, ?int $categoryId, ?int $cashAccountId = null, int $perPage = 10)
    {
        return $this->transactionModel->scopeList($type, $search, $from, $to, $categoryId, $cashAccountId)->paginate($perPage);
    }

    public function pager()
    {
        return $this->transactionModel->pager;
    }

    public function categoryOptions(string $type): array
    {
        return $this->categoryModel->optionsByType($type);
    }

    public function recordManualIncome(array $data, $proofFile = null): array
    {
        $data['type']           = 'INCOME';
        $data['reference_type'] = 'MANUAL_INCOME';
        $data['reference_id']   = null;
        $data['created_by']     = session()->get('user_id');

        // Upload bukti (opsional)
        if ($proofFile && $proofFile->isValid() && ! $proofFile->hasMoved()) {
            $ext = strtolower($proofFile->getExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
                return ['success' => false, 'message' => 'Format bukti harus JPG/PNG/WebP/PDF.'];
            }
            if ($proofFile->getSize() > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => 'Ukuran bukti maksimal 5MB.'];
            }
            $dir = 'uploads/expense_proofs/';
            if (! is_dir(WRITEPATH . $dir)) {
                mkdir(WRITEPATH . $dir, 0755, true);
            }
            $fileName = 'income_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $proofFile->move(WRITEPATH . $dir, $fileName);
            $data['attachment_path'] = $dir . $fileName;
        }

        if (! $this->transactionModel->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->transactionModel->errors()];
        }

        $id = $this->transactionModel->getInsertID();
        $this->audit->log('Catat Pemasukan', 'finance', 'FINANCIAL_TRANSACTION', (int) $id, 'Rp ' . number_format((float) $data['amount'], 0, ',', '.') . ' - ' . ($data['description'] ?? ''));

        return ['success' => true, 'message' => 'Pemasukan berhasil dicatat.', 'id' => $id];
    }

    public function recordManualExpense(array $data, $proofFile = null): array
    {
        // Cek apakah ini Pemindahan Dana (transfer antar rekening)
        $toAccountId = $data['to_account_id'] ?? null;
        unset($data['to_account_id']);

        $isTransfer = false;
        if (! empty($data['category_id'])) {
            $cat = $this->categoryModel->find($data['category_id']);
            $isTransfer = $cat && strtolower(trim($cat['name'])) === 'pemindahan dana';
        }

        if ($isTransfer && $toAccountId && (int) $toAccountId !== (int) ($data['cash_account_id'] ?? 0)) {
            return $this->recordTransfer($data, (int) $toAccountId, $proofFile);
        }

        $data['type']           = 'EXPENSE';
        $data['reference_type'] = 'MANUAL_EXPENSE';
        $data['reference_id']   = null;
        $data['created_by']     = session()->get('user_id');

        // Upload bukti (opsional)
        if ($proofFile && $proofFile->isValid() && ! $proofFile->hasMoved()) {
            $ext = strtolower($proofFile->getExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
                return ['success' => false, 'message' => 'Format bukti harus JPG/PNG/WebP/PDF.'];
            }
            if ($proofFile->getSize() > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => 'Ukuran bukti maksimal 5MB.'];
            }
            $fileName = 'expense_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $proofFile->move(WRITEPATH . 'uploads/expense_proofs', $fileName);
            $data['attachment_path'] = 'uploads/expense_proofs/' . $fileName;
        }

        if (! $this->transactionModel->insert($data)) {
            return ['success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->transactionModel->errors()];
        }

        $id = $this->transactionModel->getInsertID();
        $this->audit->log('Catat Pengeluaran', 'finance', 'FINANCIAL_TRANSACTION', (int) $id, 'Rp ' . number_format((float) $data['amount'], 0, ',', '.') . ' - ' . ($data['description'] ?? ''));

        return ['success' => true, 'message' => 'Pengeluaran (operasional) berhasil dicatat.', 'id' => $id];
    }

    /**
     * Pemindahan dana antar rekening: buat 2 transaksi (keluar dari sumber, masuk ke tujuan).
     */
    public function recordTransfer(array $data, int $toAccountId, $proofFile = null): array
    {
        $amount      = (float) ($data['amount'] ?? 0);
        $fromAccount = (int) ($data['cash_account_id'] ?? 0);

        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Jumlah harus lebih dari 0.'];
        }
        if ($fromAccount === $toAccountId) {
            return ['success' => false, 'message' => 'Rekening sumber dan tujuan tidak boleh sama.'];
        }

        $db = db_connect();
        $db->transStart();

        // Upload bukti (opsional, dipakai kedua transaksi)
        $attachmentPath = null;
        if ($proofFile && $proofFile->isValid() && ! $proofFile->hasMoved()) {
            $ext = strtolower($proofFile->getExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
                return ['success' => false, 'message' => 'Format bukti harus JPG/PNG/WebP/PDF.'];
            }
            if ($proofFile->getSize() > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => 'Ukuran bukti maksimal 5MB.'];
            }
            $dir = 'uploads/expense_proofs/';
            if (! is_dir(WRITEPATH . $dir)) {
                mkdir(WRITEPATH . $dir, 0755, true);
            }
            $fileName = 'transfer_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $proofFile->move(WRITEPATH . $dir, $fileName);
            $attachmentPath = $dir . $fileName;
        }

        $base = [
            'transaction_date' => $data['transaction_date'] ?? date('Y-m-d'),
            'category_id'      => $data['category_id'] ?? null,
            'amount'           => $amount,
            'description'      => $data['description'] ?? '',
            'proof_label'      => $data['proof_label'] ?? null,
            'attachment_path'  => $attachmentPath,
            'created_by'       => session()->get('user_id'),
        ];

        // 1. Keluar dari rekening sumber
        $out = $base + [
            'type'            => 'EXPENSE',
            'reference_type'  => 'TRANSFER_OUT',
            'reference_id'    => null,
            'cash_account_id' => $fromAccount,
        ];
        if (! $this->transactionModel->insert($out)) {
            $db->transRollback();
            return ['success' => false, 'message' => 'Gagal mencatat transfer keluar.'];
        }
        $outId = $this->transactionModel->getInsertID();

        // 2. Masuk ke rekening tujuan
        $in = $base + [
            'type'            => 'INCOME',
            'reference_type'  => 'TRANSFER_IN',
            'reference_id'    => $outId,
            'cash_account_id' => $toAccountId,
        ];
        if (! $this->transactionModel->insert($in)) {
            $db->transRollback();
            return ['success' => false, 'message' => 'Gagal mencatat transfer masuk.'];
        }

        $db->transComplete();

        $this->audit->log('Pemindahan Dana', 'finance', 'FINANCIAL_TRANSACTION', (int) $outId, 'Rp ' . number_format($amount, 0, ',', '.'));

        return ['success' => true, 'message' => 'Pemindahan dana berhasil dicatat.', 'id' => $outId];
    }

    // =========================================================
    // RECEIVABLE (PIUTANG)
    // =========================================================

    public function paginateReceivables(?string $search, ?string $status, int $perPage = 10)
    {
        return $this->receivableModel->scopeList($search, $status)->paginate($perPage);
    }

    public function receivablePager()
    {
        return $this->receivableModel->pager;
    }

    public function payReceivable(int $id, float $amount, string $date, ?string $notes = null, ?int $cashAccountId = null): array
    {
        $receivable = $this->receivableModel->find($id);
        if (! $receivable) {
            return ['success' => false, 'message' => 'Piutang tidak ditemukan.'];
        }

        $db = db_connect();
        $db->transStart();

        $result = $this->receivableModel->applyPayment($id, $amount);

        if ($result['success']) {
            // Rekening tujuan: dari pilihan user, default Bank Utama
            $bankUtamaId = $cashAccountId ?: (int) ($this->db->table('cash_accounts')->select('id')->where('name', 'Bank Utama')->get()->getRow()->id ?? 1);

            $transactionId = $this->transactionModel->recordFromReference('RECEIVABLE_PAYMENT', $id, [
                'transaction_date' => $date,
                'type'             => 'INCOME',
                'category_id'      => $this->firstCategoryId('Pembayaran Piutang', 'INCOME'),
                'amount'           => $amount,
                'description'      => 'Pembayaran piutang - Invoice ' . $receivable['invoice_number'] . ($notes ? " ({$notes})" : ''),
                'account_id'       => (int) $receivable['account_id'],
                'cash_account_id'  => $bankUtamaId,
                'created_by'       => session()->get('user_id'),
            ], $this->nextPaymentSeq('RECEIVABLE_PAYMENT', $id));

            $this->receivableModel->update($id, ['transaction_id' => $transactionId]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal mencatat pembayaran piutang.'];
        }

        if ($result['success']) {
            $this->audit->log('Bayar Piutang', 'finance', 'RECEIVABLE', $id, 'Invoice ' . $receivable['invoice_number'] . ' Rp ' . number_format($amount, 0, ',', '.'));
        }

        return $result;
    }

    // =========================================================
    // PAYABLE (HUTANG)
    // =========================================================

    public function paginatePayables(?string $search, ?string $status, int $perPage = 10)
    {
        return $this->payableModel->scopeList($search, $status)->paginate($perPage);
    }

    public function payablePager()
    {
        return $this->payableModel->pager;
    }

    public function supplierOptions(): array
    {
        return $this->supplierModel->activeOptions();
    }

    public function createPayable(array $data): array
    {
        $result = $this->payableModel->create($data);
        if ($result['success']) {
            $this->audit->log('Tambah Hutang', 'finance', 'PAYABLE', (int) $result['id'], 'Invoice ' . ($data['invoice_number'] ?? '') . ' Rp ' . number_format((float) ($data['total_amount'] ?? 0), 0, ',', '.'));
        }

        return $result;
    }

    public function payPayable(int $id, float $amount, string $date, ?string $notes = null): array
    {
        $payable = $this->payableModel->find($id);
        if (! $payable) {
            return ['success' => false, 'message' => 'Hutang tidak ditemukan.'];
        }

        $db = db_connect();
        $db->transStart();

        $result = $this->payableModel->applyPayment($id, $amount);

        if ($result['success']) {
            $transactionId = $this->transactionModel->recordFromReference('PAYABLE_PAYMENT', $id, [
                'transaction_date' => $date,
                'type'             => 'EXPENSE',
                'category_id'      => $this->firstCategoryId('Pembayaran Hutang', 'EXPENSE'),
                'amount'           => $amount,
                'description'      => 'Pembayaran hutang - Invoice ' . $payable['invoice_number'] . ($notes ? " ({$notes})" : ''),
                'supplier_id'      => (int) $payable['supplier_id'],
                'created_by'       => session()->get('user_id'),
            ], $this->nextPaymentSeq('PAYABLE_PAYMENT', $id));

            $this->payableModel->update($id, ['transaction_id' => $transactionId]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal mencatat pembayaran hutang.'];
        }

        if ($result['success']) {
            $this->audit->log('Bayar Hutang', 'finance', 'PAYABLE', $id, 'Invoice ' . $payable['invoice_number'] . ' Rp ' . number_format($amount, 0, ',', '.'));
        }

        return $result;
    }

    // =========================================================
    // REPORT
    // =========================================================

    public function report(?string $from = null, ?string $to = null): array
    {
        $from = $from ?: date('Y-m-01');
        $to   = $to ?: date('Y-m-t');

        return [
            'from'             => $from,
            'to'               => $to,
            'total_income'     => $this->transactionModel->sumByTypeBetween('INCOME', $from, $to),
            'total_expense'    => $this->transactionModel->sumByTypeBetween('EXPENSE', $from, $to),
            'total_receivable' => $this->receivableModel->totalOutstanding(),
            'total_payable'    => $this->payableModel->totalOutstanding(),
            'monthly'          => $this->transactionModel->monthlyCashFlow($from, $to),
        ];
    }
}

