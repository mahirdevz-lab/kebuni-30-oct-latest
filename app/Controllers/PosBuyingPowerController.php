<?php

namespace App\Controllers;

use App\Services\PosBuyingPowerService;

/** Laporan Buying Power (basket size) & frekuensi pembelian per produk. */
class PosBuyingPowerController extends BaseController
{
    public function index()
    {
        $valid = static fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $from  = $this->request->getGet('from');
        $to    = $this->request->getGet('to');
        $from  = $valid($from) ? $from : date('Y-m-01');
        $to    = $valid($to) ? $to : date('Y-m-d');
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        if (strtotime($to) - strtotime($from) > 366 * 86400) {
            return $this->response->setStatusCode(422)->setBody('Rentang laporan maksimal 1 tahun.');
        }
        $r = (new PosBuyingPowerService())->build($from, $to);

        if ($this->request->getGet('format') === 'csv') {
            $h = fopen('php://temp', 'w+');
            fwrite($h, "\xEF\xBB\xBF");
            if ($this->request->getGet('type') === 'daily') {
                fputcsv($h, ['Tanggal', 'NoI (jumlah invoice)', 'Sales', 'Buying power (Sales/NoI)'], ';');
                foreach ($r['daily'] as $d) {
                    fputcsv($h, [$d['date'], $d['noi'], $d['sales'], $d['buying_power']], ';');
                }
                $name = 'harian';
            } else {
                fputcsv($h, ['Kode', 'Produk', 'Satuan', 'NoI (jumlah invoice yang memuat artikel)', 'Penetrasi %', 'Total qty', 'Qty gratis', 'Total sales', 'Rata-rata qty/invoice', 'Rata-rata sales/invoice', 'Basket size invoice yang memuat produk'], ';');
                foreach ($r['products'] as $p) {
                    fputcsv($h, [$p['code'], $p['name'], $p['unit'], $p['noi'], $p['penetration'], $p['qty'], $p['free_qty'], $p['sales'], $p['avg_qty'], $p['avg_sales'], $p['basket']], ';');
                }
                $name = 'produk';
            }
            rewind($h);
            $csv = stream_get_contents($h);
            fclose($h);

            return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', "attachment; filename=\"buying-power-{$name}-{$from}-{$to}.csv\"")->setBody($csv);
        }

        if ($this->request->getGet('print')) {
            return view('pos/buying_power_print', ['r' => $r, 'from' => $from, 'to' => $to, 'store' => (new \App\Models\SettingModel())->value('pos_store_name', 'Kebuni Mart')]);
        }

        return view('pos/buying_power', [
            'title' => 'Buying Power', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Buying Power']],
            'r' => $r, 'from' => $from, 'to' => $to,
        ]);
    }
}
