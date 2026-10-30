<?php

namespace App\Controllers;

use App\Services\FinalReportService;
use App\Services\ReportService;

class ReportController extends BaseController
{
    protected ReportService $service;

    public function __construct()
    {
        $this->service = new ReportService();
    }

    private function range(): array
    {
        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to') ?: date('Y-m-t');

        return [$from, $to];
    }

    /** Kirim CSV (UTF-8 BOM agar terbaca benar di Excel). */
    private function csv(string $filename, array $header, array $lines)
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header);
        foreach ($lines as $line) {
            fputcsv($out, $line);
        }
        rewind($out);
        $body = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($body);
    }

    public function transaction()
    {
        [$from, $to] = $this->range();
        $status      = $this->request->getGet('status') ?: null;
        $accountId   = (int) $this->request->getGet('account_id') ?: null;

        $report = $this->service->transaction($from, $to, $status, $accountId);

        if ($this->request->getGet('export')) {
            return $this->csv(
                "report-transaksi_{$from}_{$to}.csv",
                ['No PO', 'Tanggal', 'Customer', 'Payment Term', 'Status', 'Qty (Kg)', 'Omzet', 'COGS', 'Est. Profit', 'Komisi', 'Laba Bersih'],
                array_map(static fn ($r) => [
                    $r['order_no'], $r['order_date'], $r['nama_account'], $r['payment_term'], $r['status'],
                    $r['total_qty'], $r['grand_total'], $r['total_cogs'], $r['estimated_profit'], $r['commission_amount'] ?? 0, (float) $r['estimated_profit'] - (float) ($r['commission_amount'] ?? 0),
                ], $report['rows'])
            );
        }

        return view('report/transaction', [
            'title'       => 'Report Transaksi',
            'breadcrumbs' => [['label' => 'Report'], ['label' => 'Transaksi']],
            'report'      => $report,
            'from'        => $from,
            'to'          => $to,
            'status'      => $status,
            'account_id'  => $accountId,
            'accounts'    => $this->service->accountOptions(),
        ]);
    }

    public function attendance()
    {
        [$from, $to] = $this->range();
        $search      = $this->request->getGet('search') ?: null;
        $loc         = $this->request->getGet('location_status') ?: null;

        $report = $this->service->attendance($from, $to, $search, $loc);

        if ($this->request->getGet('export') === 'recap') {
            return $this->csv(
                "rekap-absensi_{$from}_{$to}.csv",
                ['Nama', 'Hari Hadir', 'Terlambat', 'Absen Pulang', 'Di Luar Area'],
                array_map(static fn ($r) => [$r['nama'], $r['days_present'], $r['late_count'], $r['checkout_count'], $r['outside_count']], $report['recap'])
            );
        }

        if ($this->request->getGet('export')) {
            return $this->csv(
                "report-absensi_{$from}_{$to}.csv",
                ['Nama', 'Tanggal', 'Jam', 'Jenis', 'Status Lokasi', 'Lokasi', 'Jarak (m)', 'Terlambat'],
                array_map(static fn ($r) => [
                    $r['nama'], $r['attendance_date'], $r['attendance_time'],
                    $r['attendance_type'] === 'CHECK_IN' ? 'Masuk' : 'Pulang',
                    $r['location_status'], $r['location_name'] ?? '', $r['distance_from_office'] ?? '',
                    $r['is_late'] ? 'Ya' : 'Tidak',
                ], $report['rows'])
            );
        }

        return view('report/attendance', [
            'title'       => 'Report Absensi',
            'breadcrumbs' => [['label' => 'Report'], ['label' => 'Absensi']],
            'report'      => $report,
            'from'        => $from,
            'to'          => $to,
            'search'      => $search,
            'loc'         => $loc,
        ]);
    }

    public function finance()
    {
        [$from, $to] = $this->range();
        $type        = $this->request->getGet('type') ?: null;

        $report = $this->service->finance($from, $to, $type);

        if ($this->request->getGet('export')) {
            return $this->csv(
                "report-keuangan_{$from}_{$to}.csv",
                ['Tanggal', 'Jenis', 'Kategori', 'Keterangan', 'Sumber', 'Customer/Supplier', 'Jumlah'],
                array_map(static fn ($r) => [
                    $r['transaction_date'], $r['type'] === 'INCOME' ? 'Pemasukan' : 'Pengeluaran',
                    $r['category_name'] ?? '', $r['description'] ?? '', $r['reference_type'],
                    $r['nama_account'] ?? $r['nama_supplier'] ?? '', $r['amount'],
                ], $report['rows'])
            );
        }

        return view('report/finance', [
            'title'       => 'Report Keuangan',
            'breadcrumbs' => [['label' => 'Report'], ['label' => 'Keuangan']],
            'report'      => $report,
            'from'        => $from,
            'to'          => $to,
            'type'        => $type,
        ]);
    }

    /**
     * Final Report per Price Period — ringkasan, per produk
     * (avg jual aktual vs rekomendasi), piutang, dan stok opname.
     */
    public function final()
    {
        $svc      = new FinalReportService();
        $periodId = (int) ($this->request->getGet('period_id') ?? 0);
        $orderId  = (int) ($this->request->getGet('order_id') ?? 0) ?: null;

        $periods = $svc->periodOptions();
        if ($periodId <= 0 && $periods !== []) {
            $periodId = (int) $periods[0]['id'];
        }

        $report = $periodId > 0 ? $svc->build($periodId, $orderId) : null;

        if ($this->request->getGet('export') && $report) {
            return $this->csv(
                "final-report_period-{$periodId}" . ($orderId ? "_order-{$orderId}" : '') . '.csv',
                ['Kode', 'Produk', 'Qty', 'Satuan', 'Avg Rekomendasi', 'Avg Aktual', 'Selisih', 'Diskon %', 'Omzet', 'COGS', 'Profit', 'Margin %'],
                array_map(static fn ($r) => [
                    $r['code'], $r['name'], $r['qty'], $r['unit'],
                    $r['avg_recommended'], $r['avg_actual'],
                    $r['avg_recommended'] - $r['avg_actual'], $r['discount_percent'],
                    $r['omzet'], $r['cogs'], $r['profit'], $r['margin_percent'],
                ], $report['products'])
            );
        }

        return view('report/final', [
            'title'       => 'Final Report',
            'breadcrumbs' => [['label' => 'Report'], ['label' => 'Final Report']],
            'periods'     => $periods,
            'period_id'   => $periodId,
            'order_id'    => $orderId,
            'orders'      => $periodId > 0 ? $svc->orderOptions($periodId) : [],
            'report'      => $report,
            'products'    => $svc->productOptions(),
        ]);
    }

    /** Simpan stok opname per periode (AJAX). */
    public function saveOpname()
    {
        $periodId = (int) $this->request->getPost('period_id');
        $rows     = $this->request->getPost('rows');

        if ($periodId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Price Period wajib dipilih.'])->setStatusCode(422);
        }

        $rows = is_string($rows) ? json_decode($rows, true) : $rows;
        if (! is_array($rows)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data opname tidak valid.'])->setStatusCode(422);
        }

        (new FinalReportService())->saveOpname($periodId, $rows, session()->get('user_id'));

        return $this->response->setJSON(['success' => true, 'message' => 'Stok opname tersimpan.']);
    }
}

