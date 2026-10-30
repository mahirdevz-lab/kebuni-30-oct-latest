<?php

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\AuthService;
use App\Services\PosCashService;

/** Setoran Kas Kasir ke bank & settlement QRIS (+ MDR). */
class PosCashController extends BaseController
{
    private PosCashService $cash;

    public function __construct()
    {
        $this->cash = new PosCashService();
    }

    public function index()
    {
        $db       = db_connect();
        $set      = new SettingModel();
        $cashId   = (int) $set->value('pos_cash_account', '0');
        $clearing = (int) $set->value('pos_qris_account', '0');
        $auth     = new AuthService();

        $accounts = $db->table('cash_accounts')->where('is_active', 1)->whereNotIn('id', [$cashId, $clearing])->orderBy('id')->get()->getResultArray();
        $balance  = static fn (int $id) => (float) db_connect()->query(
            "SELECT COALESCE(SUM(CASE WHEN type='INCOME' THEN amount ELSE -amount END),0) s FROM financial_transactions WHERE cash_account_id = ?", [$id]
        )->getRow('s');

        return view('pos/cash', [
            'title' => 'Setoran & Settlement QRIS', 'breadcrumbs' => [['label' => 'POS'], ['label' => 'Setoran & Settlement']],
            'accounts' => $accounts, 'avail' => $this->cash->availableCash(), 'qris' => $this->cash->unsettledQris(),
            'clearingBalance' => $balance($clearing),
            'deposits' => $db->table('pos_cash_deposits d')->select('d.*, a.name AS to_name, u.full_name AS user_name')
                ->join('cash_accounts a', 'a.id = d.to_account_id', 'left')->join('users u', 'u.id = d.created_by', 'left')->orderBy('d.id', 'DESC')->limit(30)->get()->getResultArray(),
            'settlements' => $db->table('pos_qris_settlements s')->select('s.*, a.name AS bank_name, u.full_name AS user_name')
                ->join('cash_accounts a', 'a.id = s.bank_account_id', 'left')->join('users u', 'u.id = s.created_by', 'left')->orderBy('s.id', 'DESC')->limit(30)->get()->getResultArray(),
            'canDeposit' => $auth->hasPermission('pos.cash_deposit'), 'canSettle' => $auth->hasPermission('pos.qris_settle'),
        ]);
    }

    /** Simpan bukti (foto/PDF) di luar folder publik; null bila tidak ada, atau [error] bila file ditolak. */
    private function saveProof(): array
    {
        $file = $this->request->getFile('proof');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        $up = (new \App\Services\FileUploadService())->upload($file, WRITEPATH . 'uploads/pos_proofs/', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5120);
        if (! $up['success']) {
            return [null, $up['message']];
        }

        return ['uploads/pos_proofs/' . $up['fileName'], null];
    }

    /** GET pos/cash/proof/(deposit|settlement)/(:num) — bukti hanya untuk yang berwenang. */
    public function proof(string $type, int $id)
    {
        $table = $type === 'deposit' ? 'pos_cash_deposits' : 'pos_qris_settlements';
        $row   = db_connect()->table($table)->select('proof_path')->where('id', $id)->get()->getRowArray();
        $path  = $row && $row['proof_path'] ? WRITEPATH . $row['proof_path'] : null;
        if (! $path || ! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->setHeader('Content-Type', mime_content_type($path) ?: 'application/octet-stream')
            ->setHeader('Cache-Control', 'private, max-age=86400')->setBody(file_get_contents($path));
    }

    public function deposit()
    {
        [$proof, $err] = $this->saveProof();
        if ($err) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $err]);
        }
        $res = $this->cash->deposit((float) $this->request->getPost('amount'), (int) $this->request->getPost('to_account_id'),
            $this->request->getPost('reference_no'), $this->request->getPost('notes'), (int) session()->get('user_id'), $proof);
        if (! $res['success'] && $proof) {
            @unlink(WRITEPATH . $proof);
        }

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }

    public function settle()
    {
        [$proof, $err] = $this->saveProof();
        if ($err) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $err]);
        }
        $res = $this->cash->settleQris((array) $this->request->getPost('payment_ids'), (float) $this->request->getPost('net_amount'),
            (string) ($this->request->getPost('settlement_date') ?: date('Y-m-d')), $this->request->getPost('reference_no'), $this->request->getPost('notes'),
            (int) $this->request->getPost('bank_account_id'), (int) session()->get('user_id'), $proof);
        if (! $res['success'] && $proof) {
            @unlink(WRITEPATH . $proof);
        }

        return $this->response->setStatusCode($res['success'] ? 200 : 422)->setJSON($res);
    }
}
