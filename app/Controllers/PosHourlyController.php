<?php

namespace App\Controllers;

use App\Services\PosHourlySalesService;

/** Hourly sales report: golden time & karakter belanja konsumen. */
class PosHourlyController extends BaseController
{
    public function index()
    {
        $valid = static fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $from  = $this->request->getGet('from');
        $to    = $this->request->getGet('to');
        $from  = $valid($from) ? $from : date('Y-m-d', strtotime('-29 days'));
        $to    = $valid($to) ? $to : date('Y-m-d');
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        if (strtotime($to) - strtotime($from) > 366 * 86400) {
            return $this->response->setStatusCode(422)->setBody('Rentang laporan maksimal 1 tahun.');
        }
        $day = (string) $this->request->getGet('day');
        $day = in_array($day, ['WEEKDAY', 'WEEKEND', '0', '1', '2', '3', '4', '5', '6'], true) ? $day : 'ALL';

        $r = (new PosHourlySalesService())->build($from, $to, $day);

        if ($this->request->getGet('format') === 'csv') {
            $h = fopen('php://temp', 'w+');
            fwrite($h, "\xEF\xBB\xBF");
            if ($this->request->getGet('type') === 'daypart') {
                fputcsv($h, ['Bagian hari', 'Jam', 'NoI', 'Sales', '% sales', 'Buying power', 'Qty per invoice', 'Artikel paling dicari (NoI)'], ';');
                foreach ($r['dayparts'] as $d) {
                    fputcsv($h, [$d['label'], $d['range'], $d['noi'], $d['sales'], $d['share'], $d['buying_power'], $d['qty_per_invoice'], implode(', ', array_column($d['top'], 'name'))], ';');
                }
                $name = 'bagian-hari';
            } else {
                fputcsv($h, ['Jam', 'NoI', 'Rata-rata NoI per hari aktif', 'Sales', '% sales', 'Buying power', 'Total qty'], ';');
                foreach ($r['hours'] as $x) {
                    fputcsv($h, [sprintf('%02d.00-%02d.59', $x['hour'], $x['hour']), $x['noi'], $x['avg_noi_day'], $x['sales'], $x['share'], $x['buying_power'], $x['qty']], ';');
                }
                $name = 'per-jam';
            }
            rewind($h);
            $csv = stream_get_contents($h);
            fclose($h);

            return $this->response->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', "attachment; filename=\"hourly-sales-{$name}-{$from}-{$to}.csv\"")->setBody($csv);
        }

        $data = ['r' => $r, 'from' => $from, 'to' => $to, 'day' => $day];
        if ($this->request->getGet('print')) {
            return view('pos/hourly_print', $data + ['store' => (new \App\Models\SettingModel())->value('pos_store_name', 'Kebuni Mart')]);
        }

        return view('pos/hourly', $data + ['title' => 'Hourly Sales', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Hourly Sales']]]);
    }
}
