<?php

namespace App\Controllers;

use App\Services\AccountService;
use App\Services\PaymentTermService;
use App\Services\PricePeriodService;
use App\Services\ProductService;
use App\Services\SalesOrderService;
use App\Services\TransportRouteService;
use App\Services\PackingMaterialService;

class SalesOrderController extends BaseController
{
    protected SalesOrderService $service;
    protected AccountService $accountService;
    protected TransportRouteService $routeService;
    protected PaymentTermService $paymentTermService;
    protected PricePeriodService $periodService;
    protected ProductService $productService;
    protected PackingMaterialService $packingService;

    public function __construct()
    {
        $this->service            = new SalesOrderService();
        $this->accountService     = new AccountService();
        $this->routeService       = new TransportRouteService();
        $this->paymentTermService = new PaymentTermService();
        $this->periodService      = new PricePeriodService();
        $this->productService     = new ProductService();
        $this->packingService     = new PackingMaterialService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');
        $from   = $this->request->getGet('from');
        $to     = $this->request->getGet('to');

        $orders = $this->service->paginate($search, $status, $from, $to);

        $data = [
            'title'       => 'Monitoring PO',
            'breadcrumbs' => [
                ['label' => 'Sales'],
                ['label' => 'Monitoring PO'],
            ],
            'orders' => $orders,
            'pager'  => $this->service->pager(),
            'search' => $search,
            'status' => $status,
            'from'   => $from,
            'to'     => $to,
        ];

        if ($this->request->isAJAX()) {
            return view('sales_order/_table', $data);
        }

        return view('sales_order/index', $data);
    }

    protected function formOptions(): array
    {
        return [
            // dropdown() eksplisit: tanpa pager (BUG-5 audit pagination).
            'accounts'     => $this->accountService->dropdown('aktif', 1000),
            'periods'      => $this->periodService->dropdown('PUBLISHED', 200),
            'routes'       => $this->routeService->activeOptions(),
            'paymentTerms' => $this->paymentTermService->activeOptions(),
            'products'     => $this->productService->activeOptions(),
            'packings'     => $this->packingService->activeOptions(),
            'salesList'    => (new \App\Services\SalesService())->activeOptions(),
        ];
    }

    public function create()
    {
        $data = array_merge($this->formOptions(), [
            'title'       => 'Taking Order Baru',
            'breadcrumbs' => [
                ['label' => 'Sales'],
                ['label' => 'Taking Order', 'url' => base_url('admin/sales-order/create')],
                ['label' => 'Baru'],
            ],
            'order' => null,
            'items' => [],
            'today' => date('Y-m-d'),
        ]);

        return view('sales_order/form', $data);
    }

    public function edit(int $id)
    {
        $order = $this->service->find($id);

        if (! $order) {
            return redirect()->to('/admin/sales-order')->with('error', 'Sales Order tidak ditemukan.');
        }

        if (! $this->service->isEditable($order)) {
            return redirect()->to('/admin/sales-order/' . $id)->with('error', 'Sales Order hanya dapat diedit selama status Draft.');
        }

        $data = array_merge($this->formOptions(), [
            'title'       => 'Edit Taking Order',
            'breadcrumbs' => [
                ['label' => 'Sales'],
                ['label' => 'Taking Order'],
                ['label' => 'Edit'],
            ],
            'order' => $order,
            'items' => $this->service->getItems($id),
            'today' => date('Y-m-d'),
        ]);

        return view('sales_order/form', $data);
    }

    /**
     * AJAX: preview kalkulasi (tanpa simpan), dipakai form sebelum "Simpan sebagai Draft".
     */
    public function calculate()
    {
        $pricePeriodId = (int) $this->request->getPost('price_period_id');
        $paymentTermId = (int) $this->request->getPost('payment_term_id');
        $labourCounts  = $this->collectLabourCounts();
        $items         = $this->collectItems();

        if (! $pricePeriodId || ! $paymentTermId || empty($items)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Price Period, Payment Term, dan minimal 1 item wajib diisi.'])->setStatusCode(422);
        }

        foreach ($items as &$item) {
            $item['price_period_id'] = $pricePeriodId;
        }
        unset($item);

        $engine = new \App\Services\PriceCalculationService();
        $result = $engine->calculateOrder($items, $paymentTermId, $labourCounts);

        return $this->response->setJSON(['success' => true] + $result);
    }

    public function store()
    {
        $result = $this->service->saveDraft(null, $this->collectHeader(), $this->collectItems(), $this->collectLabourCounts());

        return $this->response->setJSON($result);
    }

    public function update(int $id)
    {
        $result = $this->service->saveDraft($id, $this->collectHeader(), $this->collectItems(), $this->collectLabourCounts());

        return $this->response->setJSON($result);
    }

    protected function collectHeader(): array
    {
        return [
            'order_date'         => $this->request->getPost('order_date'),
            'account_id'         => $this->request->getPost('account_id'),
            'sales_id'      => $this->request->getPost('sales_id') ?: null,
            'commission_percent' => $this->request->getPost('commission_percent'),
            'payment_term_id'    => $this->request->getPost('payment_term_id'),
            'price_period_id'    => $this->request->getPost('price_period_id'),
            'notes'              => $this->request->getPost('notes'),
        ];
    }

    protected function collectItems(): array
    {
        return json_decode($this->request->getPost('items') ?? '[]', true) ?: [];
    }

    protected function collectLabourCounts(): array
    {
        return json_decode($this->request->getPost('labour_counts') ?? '{}', true) ?: [];
    }

    public function detail(int $id)
    {
        $order = $this->service->findWithRelations($id);

        if (! $order) {
            return redirect()->to('/admin/sales-order')->with('error', 'Sales Order tidak ditemukan.');
        }

        $data = [
            'title'       => $order['order_no'],
            'breadcrumbs' => [
                ['label' => 'Sales'],
                ['label' => 'Monitoring PO', 'url' => base_url('admin/sales-order')],
                ['label' => $order['order_no']],
            ],
            'order'      => $order,
            'items'      => $this->service->getItems($id),
            'editable'   => $this->service->isEditable($order),
            'auditTrail' => $this->service->getAuditTrail($id),
        ];

        return view('sales_order/detail', $data);
    }

    public function print(int $id)
    {
        $order = $this->service->findWithRelations($id);

        if (! $order) {
            return redirect()->to('/admin/sales-order')->with('error', 'Sales Order tidak ditemukan.');
        }

        // --- Info pembayaran: piutang (kredit) atau tunai (SALE) ---
        $grandTotal = (float) $order['grand_total'];
        $receivable = (new \App\Models\ReceivableModel())->findByInvoice((string) $order['order_no']);
        $txModel    = new \App\Models\FinancialTransactionModel();

        $payments  = [];
        $totalPaid = 0.0;
        $remaining = $grandTotal;
        $payStatus = 'BELUM ADA PEMBAYARAN';
        $statusCode = null;
        $dueDate   = null;

        if ($receivable) {
            $dueDate = $receivable['due_date'] ?? null;

            // Status efektif: UNPAID/PARTIAL yang sudah lewat due_date = OVERDUE
            // (syncOverdue() di model hanya jalan saat halaman Piutang dibuka)
            $statusCode = strtoupper((string) $receivable['status']);
            if (in_array($statusCode, ['UNPAID', 'PARTIAL'], true) && $dueDate && $dueDate < date('Y-m-d')) {
                $statusCode = 'OVERDUE';
            }

            $rows = $txModel->where('reference_type', 'RECEIVABLE_PAYMENT')
                ->where('reference_id', (int) $receivable['id'])
                ->orderBy('transaction_date', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll();

            foreach ($rows as $r) {
                $note = trim(str_replace('Pembayaran piutang - Invoice ' . $order['order_no'], '', (string) ($r['description'] ?? '')));
                $note = trim($note, " \t\n\r\0\x0B-()");
                $payments[] = [
                    'date'   => $r['transaction_date'],
                    'amount' => (float) $r['amount'],
                    'note'   => $note !== '' ? $note : 'Pembayaran piutang',
                ];
            }

            // paid/remaining dari record piutang = sumber paling otoritatif
            $totalPaid = (float) $receivable['paid_amount'];
            $remaining = (float) $receivable['remaining_amount'];
            $payStatus = match ($statusCode) {
                'PAID'    => 'LUNAS',
                'PARTIAL' => 'DIBAYAR SEBAGIAN',
                'OVERDUE' => $totalPaid > 0 ? 'DIBAYAR SEBAGIAN — LEWAT JATUH TEMPO' : 'BELUM LUNAS — LEWAT JATUH TEMPO',
                default   => $totalPaid > 0 ? 'DIBAYAR SEBAGIAN' : 'BELUM LUNAS',
            };
        } else {
            // Order tunai: pembayaran tercatat sebagai transaksi SALE saat order Completed
            $sale = $txModel->where('reference_type', 'SALE')
                ->where('reference_id', $id)
                ->first();

            if ($sale) {
                $payments[] = [
                    'date'   => $sale['transaction_date'],
                    'amount' => (float) $sale['amount'],
                    'note'   => 'Pembayaran tunai',
                ];
                $totalPaid = (float) $sale['amount'];
                $remaining = max(0, $grandTotal - $totalPaid);
                $payStatus = $remaining <= 0 ? 'LUNAS (TUNAI)' : 'DIBAYAR SEBAGIAN';
                $statusCode = $remaining <= 0 ? 'PAID' : 'PARTIAL';
            }
        }

        $payment = [
            'receivable' => $receivable,
            'payments'   => $payments,
            'total_paid' => $totalPaid,
            'remaining'  => $remaining,
            'status'     => $payStatus,
            'status_code' => $statusCode,
            'due_date'   => $dueDate,
            'is_paid'    => $remaining <= 0 && $totalPaid > 0,
        ];

        return view('sales_order/print', ['order' => $order, 'items' => $this->service->getItems($id), 'payment' => $payment]);
    }

    public function submit(int $id)
    {
        return $this->response->setJSON($this->service->submit($id));
    }

    public function approve(int $id)
    {
        return $this->response->setJSON($this->service->approve($id));
    }

    public function process(int $id)
    {
        return $this->response->setJSON($this->service->process($id));
    }

    public function complete(int $id)
    {
        return $this->response->setJSON($this->service->complete($id));
    }

    public function cancel(int $id)
    {
        return $this->response->setJSON($this->service->cancel($id, $this->request->getPost('reason')));
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }

    /**
     * Komponen (transport/packing/opex/labour) yang tersedia untuk satu price period.
     * GET admin/sales-order/components-by-period?price_period_id=123
     */
    public function componentsByPeriod()
    {
        $pricePeriodId = (int) $this->request->getGet('price_period_id');

        if (! $pricePeriodId) {
            return $this->response->setJSON(['success' => false, 'message' => 'price_period_id wajib diisi.'])->setStatusCode(422);
        }

        $opexModel  = new \App\Models\PricePeriodOpexAreaModel();
        $period     = (new \App\Models\PricePeriodModel())->find($pricePeriodId);
        $transports = (new \App\Models\PricePeriodTransportModel())->getByPeriod($pricePeriodId);
        $packings   = (new \App\Models\PricePeriodPackingModel())->getByPeriod($pricePeriodId);

        return $this->response->setJSON([
            'success'       => true,
            'opex'          => $opexModel->optionsByPeriod($pricePeriodId),
            'labour_budget' => (float) ($period['labour_budget'] ?? 0),
            'transports'    => array_map(static fn (array $t) => [
                'id'               => (int) $t['transport_route_id'],
                'origin'           => $t['origin'] ?? '',
                'destination_name' => $t['destination_name'] ?? '',
                'cost_per_kg'      => (float) ($t['cost_per_kg'] ?? 0),
            ], $transports),
            'packings'      => array_map(static fn (array $p) => [
                'id'             => (int) $p['packing_material_id'],
                'name'           => $p['name'] ?? '',
                'code'           => $p['code'] ?? '',
                'price_per_unit' => (float) ($p['price_per_unit'] ?? $p['price'] ?? 0),
            ], $packings),
        ]);
    }

    /**
     * Catat pembayaran komisi (cicilan / lunas).
     * POST admin/sales-order/{id}/commission-pay
     */
    public function payCommission(int $id)
    {
        $result = $this->service->payCommission(
            $id,
            (float) $this->request->getPost('amount'),
            $this->request->getPost('payment_date') ?: date('Y-m-d'),
            $this->request->getPost('notes'),
            $this->request->getFile('proof')
        );

        return $this->response->setJSON($result);
    }

    /**
     * Serve bukti transfer komisi.
     * GET admin/sales-order/commission-proof/(:num)
     */
    public function commissionProof(int $paymentId)
    {
        $payment = (new \App\Models\CommissionPaymentModel())->find($paymentId);

        if (! $payment || empty($payment['proof_path'])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $path = WRITEPATH . $payment['proof_path'];

        if (! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null);
    }

    /**
     * Riwayat pembayaran komisi satu order.
     * GET admin/sales-order/{id}/commission-history
     */
    public function commissionHistory(int $id)
    {
        $payments = (new \App\Models\CommissionPaymentModel())->getByOrder($id);
        $order    = $this->service->find($id);

        return $this->response->setJSON([
            'success'  => true,
            'payments' => $payments,
            'total'     => (float) ($order['commission_amount'] ?? 0),
            'paid'      => (float) ($order['commission_paid_amount'] ?? 0),
            'status'    => $order['commission_status'] ?? 'UNPAID',
        ]);
    }
}
