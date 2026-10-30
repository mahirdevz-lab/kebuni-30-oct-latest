<?php

namespace App\Controllers;

use App\Services\PosExceptionService;

class PosExceptionController extends BaseController
{
    public function index()
    {
        $valid = static fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $from  = $this->request->getGet('from');
        $to    = $this->request->getGet('to');
        $from  = $valid($from) ? $from : date('Y-m-d', strtotime('-6 days'));
        $to    = $valid($to) ? $to : date('Y-m-d');
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        $type = strtoupper((string) $this->request->getGet('type'));

        $res = (new PosExceptionService())->build($from, $to, $type ?: null);

        return view('pos/exceptions', [
            'title' => 'Pengecualian POS', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Pengecualian']],
            'rows' => $res['rows'], 'counts' => $res['counts'], 'from' => $from, 'to' => $to, 'type' => $type,
        ]);
    }
}
