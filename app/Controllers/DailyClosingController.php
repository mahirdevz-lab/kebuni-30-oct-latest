<?php

namespace App\Controllers;

use App\Services\DailyClosingService;
use App\Services\StockOpnameService;

/** Buka toko (kas awal) & EOD / tutup kas harian. */
class DailyClosingController extends BaseController
{
    private DailyClosingService $days;

    public function __construct()
    {
        $this->days = new DailyClosingService();
    }

    public function index()
    {
        $open    = $this->days->openDay();
        $history = db_connect()->table('pos_daily_closings d')
            ->select('d.*, uo.full_name AS opened_name, uc.full_name AS closed_name')
            ->join('users uo', 'uo.id = d.opened_by', 'left')->join('users uc', 'uc.id = d.closed_by', 'left')
            ->where('d.location_id', $this->days->locationId())->orderBy('d.business_date', 'DESC')->limit(30)->get()->getResultArray();

        return view('pos/day', [
            'title'       => 'Buka / Tutup Kas Harian',
            'breadcrumbs' => [['label' => 'POS'], ['label' => 'Kas Harian (EOD)']],
            'open'        => $open ? $this->days->summary($open) : null,
            'history'     => $history,
            'expectedOpening' => $this->days->expectedOpeningCash(),
            'opname'      => (new StockOpnameService())->activeSession($this->days->locationId()),
        ]);
    }

    public function open()
    {
        $res = $this->days->open((float) $this->request->getPost('opening_cash'), (int) session()->get('user_id'), trim((string) $this->request->getPost('notes')) ?: null);

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function close(int $id)
    {
        $physical = $this->request->getPost('physical_cash');
        if ($physical === null || $physical === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Kas fisik wajib diisi (hasil hitung laci).']);
        }
        $res = $this->days->close($id, (float) $physical, (int) session()->get('user_id'), trim((string) $this->request->getPost('notes')) ?: null);

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }
}
