<?php

namespace App\Controllers;

use App\Services\FinanceService;

class FinanceController extends BaseController
{
    protected FinanceService $service;

    public function __construct()
    {
        // Proteksi: hanya Admin / Super Admin (role_id 1 & 2)
        if (! \App\Helpers\AdminGuard::isAdmin()) {
            if (service('request')->isAJAX()) {
                service('response')->setJSON(['success' => false, 'message' => 'Akses ditolak. Hanya admin.'])->setStatusCode(403)->send();
                exit;
            }
            throw new \CodeIgniter\Router\Exceptions\RedirectException(base_url('admin/dashboard'));
        }
        $this->service = new FinanceService();
    }

    // ---------------- DASHBOARD ----------------

    public function dashboard()
    {
        $data = [
            'title'       => 'Dashboard Keuangan',
            'breadcrumbs' => [
                ['label' => 'Keuangan'],
                ['label' => 'Dashboard'],
            ],
        ];

        return view('finance/dashboard', $data);
    }

    public function dashboardSummary()
    {
        $from = $this->request->getGet('from');
        $to   = $this->request->getGet('to');

        return $this->response->setJSON(['success' => true] + $this->service->dashboardSummary($from, $to));
    }

    public function cashFlowChart()
    {
        $from = $this->request->getGet('from');
        $to   = $this->request->getGet('to');

        return $this->response->setJSON(['success' => true, 'series' => $this->service->cashFlowChart($from, $to)]);
    }

    // ---------------- INCOME ----------------

    public function income()
    {
        $search     = $this->request->getGet('search');
        $from       = $this->request->getGet('from');
        $to         = $this->request->getGet('to');
        $categoryId = $this->request->getGet('category_id') ?: null;
        $cashAccountId = $this->request->getGet('cash_account_id') ?: null;

        $transactions = $this->service->paginateTransactions('INCOME', $search, $from, $to, (int) $categoryId ?: null, (int) $cashAccountId ?: null);

        $data = [
            'title'       => 'Pemasukan',
            'breadcrumbs' => [
                ['label' => 'Keuangan'],
                ['label' => 'Pemasukan'],
            ],
            'transactions' => $transactions,
            'pager'        => $this->service->pager(),
            'categories'   => $this->service->categoryOptions('INCOME'),
            'cashAccounts' => (new \App\Models\CashAccountModel())->getActive(),
            'balances'     => (new \App\Models\CashAccountModel())->getBalances(),
            'search'       => $search,
            'from'         => $from,
            'to'           => $to,
            'category_id'  => $categoryId,
        ];

        if ($this->request->isAJAX()) {
            return view('finance/_income_table', $data);
        }

        return view('finance/income', $data);
    }

    public function storeIncome()
    {
        $result = $this->service->recordManualIncome($this->request->getPost([
            'transaction_date', 'category_id', 'cash_account_id', 'amount', 'description', 'proof_label',
        ]), $this->request->getFile('proof'));

        return $this->response->setJSON($result);
    }

    public function incomeProof(int $id)
    {
        $row = (new \App\Models\FinancialTransactionModel())->find($id);

        if (! $row || empty($row['attachment_path'])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $path = WRITEPATH . $row['attachment_path'];

        if (! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null);
    }

    // ---------------- EXPENSE (termasuk Operasional) ----------------

    public function expense()
    {
        $search        = $this->request->getGet('search');
        $from          = $this->request->getGet('from');
        $to            = $this->request->getGet('to');
        $categoryId    = $this->request->getGet('category_id') ?: null;
        $cashAccountId = $this->request->getGet('cash_account_id') ?: null;

        $transactions = $this->service->paginateTransactions('EXPENSE', $search, $from, $to, (int) $categoryId ?: null, (int) $cashAccountId ?: null);

        $data = [
            'title'       => 'Pengeluaran',
            'breadcrumbs' => [
                ['label' => 'Keuangan'],
                ['label' => 'Pengeluaran'],
            ],
            'transactions' => $transactions,
            'pager'        => $this->service->pager(),
            'categories'   => $this->service->categoryOptions('EXPENSE'),
            'cashAccounts' => (new \App\Models\CashAccountModel())->getActive(),
            'balances'     => (new \App\Models\CashAccountModel())->getBalances(),
            'search'          => $search,
            'from'            => $from,
            'to'              => $to,
            'category_id'     => $categoryId,
            'cash_account_id' => $cashAccountId,
        ];

        if ($this->request->isAJAX()) {
            return view('finance/_expense_table', $data);
        }

        return view('finance/expense', $data);
    }

    public function storeExpense()
    {
        $result = $this->service->recordManualExpense($this->request->getPost([
            'transaction_date', 'category_id', 'cash_account_id', 'to_account_id', 'amount', 'description', 'proof_label',
        ]), $this->request->getFile('proof'));

        return $this->response->setJSON($result);
    }

    /**
     * Download bukti pengeluaran.
     * GET admin/finance/expense-proof/(:num)
     */
    public function expenseProof(int $id)
    {
        $row = (new \App\Models\FinancialTransactionModel())->find($id);

        if (! $row || empty($row['attachment_path'])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $path = WRITEPATH . $row['attachment_path'];

        if (! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null);
    }

    // ---------------- CASH ACCOUNTS (Kelola Rekening) ----------------

    public function accounts()
    {
        $model = new \App\Models\CashAccountModel();

        $data = [
            'title'       => 'Kelola Rekening',
            'breadcrumbs' => [
                ['label' => 'Keuangan'],
                ['label' => 'Kelola Rekening'],
            ],
            'accounts' => $model->getBalances(),
        ];

        return view('finance/accounts', $data);
    }

    public function storeAccount()
    {
        $model = new \App\Models\CashAccountModel();

        $data = [
            'name'            => $this->request->getPost('name'),
            'type'            => $this->request->getPost('type'),
            'initial_balance' => (float) $this->request->getPost('initial_balance'),
            'is_active'       => 1,
            'notes'           => $this->request->getPost('notes'),
        ];

        if (! $model->insert($data)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan.', 'errors' => $model->errors()]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Rekening berhasil ditambahkan.']);
    }

    public function updateAccountBalance(int $id)
    {
        $model = new \App\Models\CashAccountModel();

        $balance = (float) $this->request->getPost('initial_balance');

        if (! $model->updateBalance($id, $balance)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal update saldo.']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Saldo awal berhasil diupdate.']);
    }

    public function toggleAccount(int $id)
    {
        $model   = new \App\Models\CashAccountModel();
        $account = $model->find($id);

        if (! $account) {
            return $this->response->setJSON(['success' => false, 'message' => 'Rekening tidak ditemukan.']);
        }

        $model->update($id, ['is_active' => $account['is_active'] ? 0 : 1]);

        return $this->response->setJSON(['success' => true, 'message' => 'Status rekening diupdate.']);
    }

    // ---------------- BUKU BESAR ----------------

    public function ledger()
    {
        $db = db_connect();
        $year = (int) ($this->request->getGet('year') ?: date('Y'));

        // Ringkasan total (transfer dikecualikan)
        $summary = $db->table('financial_transactions ft')
            ->select("COALESCE(SUM(CASE WHEN ft.type = 'INCOME' THEN ft.amount ELSE 0 END), 0) AS pemasukan, COALESCE(SUM(CASE WHEN ft.type = 'EXPENSE' THEN ft.amount ELSE 0 END), 0) AS pengeluaran")
            ->join('financial_categories c', 'c.id = ft.category_id', 'left')
            ->where('YEAR(ft.transaction_date)', $year)
            ->whereNotIn('ft.reference_type', ['TRANSFER_OUT', 'TRANSFER_IN'])
            ->where('LOWER(TRIM(COALESCE(c.name, ""))) !=', 'pemindahan dana')
            ->get()->getRowArray();

        // Modal awal = total initial_balance semua rekening
        $modalAwal = (float) ($db->table('cash_accounts')->selectSum('initial_balance')->get()->getRow()->initial_balance ?? 0);

        $pemasukan   = (float) ($summary['pemasukan'] ?? 0);
        $pengeluaran = (float) ($summary['pengeluaran'] ?? 0);
        $keuntungan  = $pemasukan - $pengeluaran;

        // Pending = piutang outstanding
        $pending = (float) ($db->table('receivables')->selectSum('remaining_amount')->get()->getRow()->remaining_amount ?? 0);

        $kasSekarang = $modalAwal + $pemasukan - $pengeluaran;

        // Per bulan
        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $row = $db->table('financial_transactions ft')
                ->select("COALESCE(SUM(CASE WHEN ft.type = 'INCOME' THEN ft.amount ELSE 0 END), 0) AS pemasukan, COALESCE(SUM(CASE WHEN ft.type = 'EXPENSE' THEN ft.amount ELSE 0 END), 0) AS pengeluaran, COUNT(*) AS n_transaksi")
                ->join('financial_categories c', 'c.id = ft.category_id', 'left')
                ->where('YEAR(ft.transaction_date)', $year)
                ->where('MONTH(ft.transaction_date)', $m)
                ->whereNotIn('ft.reference_type', ['TRANSFER_OUT', 'TRANSFER_IN'])
                ->where('LOWER(TRIM(COALESCE(c.name, ""))) !=', 'pemindahan dana')
                ->get()->getRowArray();

            // Operasional = pengeluaran kategori operasional (asumsi category mengandung 'operasional')
            $ops = $db->table('financial_transactions ft')
                ->select("COALESCE(SUM(ft.amount), 0) AS total")
                ->join('financial_categories c', 'c.id = ft.category_id', 'left')
                ->where('ft.type', 'EXPENSE')
                ->where('YEAR(ft.transaction_date)', $year)
                ->where('MONTH(ft.transaction_date)', $m)
                ->like('c.name', 'operasional')
                ->get()->getRow()->total ?? 0;

            // Pending per bulan = piutang dibuat bulan itu yang belum lunas
            $pend = $db->table('receivables')
                ->select("COUNT(*) AS jml, COALESCE(SUM(remaining_amount), 0) AS total")
                ->where('YEAR(created_at)', $year)
                ->where('MONTH(created_at)', $m)
                ->where('remaining_amount >', 0)
                ->get()->getRowArray();

            $monthly[] = [
                'bulan'       => $m,
                'nama_bulan'  => $this->namaBulan($m),
                'pemasukan'   => (float) ($row['pemasukan'] ?? 0),
                'pengeluaran' => (float) ($row['pengeluaran'] ?? 0),
                'operasional' => (float) $ops,
                'n_transaksi' => (int) ($row['n_transaksi'] ?? 0),
                'jml_pending' => (int) ($pend['jml'] ?? 0),
                'total_pending' => (float) ($pend['total'] ?? 0),
            ];
        }

        // Daftar tahun tersedia
        $years = $db->table('financial_transactions')
            ->select('DISTINCT YEAR(transaction_date) AS y')
            ->orderBy('y', 'DESC')
            ->get()->getResultArray();
        $years = array_column($years, 'y');
        if (! in_array((int) date('Y'), $years)) $years[] = (int) date('Y');
        rsort($years);

        $data = [
            'title'       => 'Buku Besar Keuangan',
            'breadcrumbs' => [
                ['label' => 'Keuangan'],
                ['label' => 'Buku Besar'],
            ],
            'year'        => $year,
            'years'       => $years,
            'modalAwal'   => $modalAwal,
            'pemasukan'   => $pemasukan,
            'pengeluaran' => $pengeluaran,
            'keuntungan'  => $keuntungan,
            'pending'     => $pending,
            'kasSekarang' => $kasSekarang,
            'monthly'     => $monthly,
            'accounts'    => (new \App\Models\CashAccountModel())->getBalances(),
        ];

        return view('finance/ledger', $data);
    }

    protected function namaBulan(int $m): string
    {
        $nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $nama[$m] ?? '';
    }

    // ---------------- RECEIVABLE (PIUTANG dari Taking Order) ----------------

    public function receivable()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $receivables = $this->service->paginateReceivables($search, $status);

        $data = [
            'title'       => 'Piutang',
            'breadcrumbs' => [
                ['label' => 'Keuangan'],
                ['label' => 'Piutang'],
            ],
            'receivables' => $receivables,
            'pager'       => $this->service->receivablePager(),
            'search'      => $search,
            'status'      => $status,
            'cashAccounts' => (new \App\Models\CashAccountModel())->getActive(),
        ];

        if ($this->request->isAJAX()) {
            return view('finance/_receivable_table', $data);
        }

        return view('finance/receivable', $data);
    }

    public function payReceivable(int $id)
    {
        $amount = (float) $this->request->getPost('amount');
        $date   = $this->request->getPost('date') ?: date('Y-m-d');
        $notes  = $this->request->getPost('notes');
        $cashAccountId = $this->request->getPost('cash_account_id') ?: null;

        return $this->response->setJSON($this->service->payReceivable($id, $amount, $date, $notes, $cashAccountId));
    }

    // ---------------- PAYABLE (HUTANG - operasional/supplier) ----------------

    public function payable()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $payables = $this->service->paginatePayables($search, $status);

        $data = [
            'title'       => 'Hutang',
            'breadcrumbs' => [
                ['label' => 'Keuangan'],
                ['label' => 'Hutang'],
            ],
            'payables'  => $payables,
            'pager'     => $this->service->payablePager(),
            'search'    => $search,
            'status'    => $status,
            'suppliers' => $this->service->supplierOptions(),
        ];

        if ($this->request->isAJAX()) {
            return view('finance/_payable_table', $data);
        }

        return view('finance/payable', $data);
    }

    public function storePayable()
    {
        $result = $this->service->createPayable($this->request->getPost([
            'supplier_id', 'invoice_number', 'transaction_date', 'due_date', 'total_amount',
        ]));

        return $this->response->setJSON($result);
    }

    public function payPayable(int $id)
    {
        $amount = (float) $this->request->getPost('amount');
        $date   = $this->request->getPost('date') ?: date('Y-m-d');
        $notes  = $this->request->getPost('notes');

        return $this->response->setJSON($this->service->payPayable($id, $amount, $date, $notes));
    }

    // ---------------- REPORT ----------------

    public function report()
    {
        $from = $this->request->getGet('from');
        $to   = $this->request->getGet('to');

        $data = [
            'title'       => 'Laporan Keuangan',
            'breadcrumbs' => [
                ['label' => 'Keuangan'],
                ['label' => 'Laporan Keuangan'],
            ],
            'report' => $this->service->report($from, $to),
        ];

        return view('finance/report', $data);
    }
}

