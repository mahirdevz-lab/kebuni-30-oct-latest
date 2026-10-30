<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\BrokenStockService;
use App\Services\DailyClosingService;
use App\Services\FileUploadService;

/** Laporan Broken Stock: lapor (bisa dari HP + foto), setujui/tolak, lihat foto. */
class BrokenStockController extends BaseController
{
    private BrokenStockService $broken;

    public function __construct()
    {
        $this->broken = new BrokenStockService();
    }

    public function index()
    {
        $status = $this->request->getGet('status') ?: 'DRAFT';
        $loc    = (new DailyClosingService())->locationId();
        $auth   = new AuthService();

        $b = db_connect()->table('stock_broken_reports r')
            ->select('r.*, p.product_code, p.product_name, ur.full_name AS reporter, ua.full_name AS approver')
            ->join('products p', 'p.id = r.product_id')
            ->join('users ur', 'ur.id = r.reported_by', 'left')->join('users ua', 'ua.id = r.approved_by', 'left')
            ->where('r.location_id', $loc);
        if ($status !== 'ALL') {
            $b->where('r.status', $status);
        }

        return view('stock/broken', [
            'title' => 'Broken Stock', 'breadcrumbs' => [['label' => 'Stok'], ['label' => 'Broken Stock']],
            'rows' => $b->orderBy('r.id', 'DESC')->limit(200)->get()->getResultArray(),
            'status' => $status, 'reasons' => BrokenStockService::REASONS,
            'products' => db_connect()->table('products')->select('id, product_code, barcode, product_name, unit')
                ->where('deleted_at', null)->where('status', 'aktif')->where('is_pos', 1)->orderBy('product_name')->get()->getResultArray(),
            'canApprove' => $auth->hasPermission('stock.broken_approve'),
            'userId' => (int) session()->get('user_id'),
            'draftCount' => $this->broken->draftCount($loc),
        ]);
    }

    public function store()
    {
        $photoPath = null;
        $file      = $this->request->getFile('photo');
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $up = (new FileUploadService())->upload($file, WRITEPATH . 'uploads/broken_stock/', ['jpg', 'jpeg', 'png', 'webp'], 5120);
            if (! $up['success']) {
                return $this->response->setStatusCode(422)->setJSON($up);
            }
            $photoPath = 'uploads/broken_stock/' . $up['fileName'];
        }

        $res = $this->broken->create($this->request->getPost(['product_id', 'qty', 'reason_code', 'reason']), $photoPath, (int) session()->get('user_id'));
        if (! $res['success'] && $photoPath) {
            @unlink(WRITEPATH . $photoPath); // jangan tinggalkan foto yatim bila laporan gagal
        }

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function approve(int $id)
    {
        $res = $this->broken->approve($id, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function reject(int $id)
    {
        $res = $this->broken->reject($id, (string) $this->request->getPost('reason'), (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function cancel(int $id)
    {
        $res = $this->broken->cancel($id, (int) session()->get('user_id'));

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    /** Foto bukti ditampilkan inline (bukan diunduh) agar bisa dilihat langsung saat review. */
    public function photo(int $id)
    {
        $row = db_connect()->table('stock_broken_reports')->select('photo_path')->where('id', $id)->get()->getRowArray();
        $path = $row && $row['photo_path'] ? WRITEPATH . $row['photo_path'] : null;
        if (! $path || ! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', mime_content_type($path) ?: 'image/jpeg')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody(file_get_contents($path));
    }
}
