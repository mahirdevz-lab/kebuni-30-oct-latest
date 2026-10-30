<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Services\AccountService;

class AccountController extends BaseController
{
    protected AccountService $accountService;

    public function __construct()
    {
        $this->accountService = new AccountService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $accounts = $this->accountService->paginate($search, $status);

        $data = [
            'title'       => 'Account',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Account'],
            ],
            'accounts' => $accounts,
            'pager'    => $this->accountService->pager(),
            'search'   => $search,
            'status'   => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('account/_table', $data);
        }

        return view('account/index', $data);
    }

    public function store()
    {
        $rules = (new AccountModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data tidak valid.',
                'errors'  => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->accountService->create($this->request->getPost([
            'nama_account', 'alamat', 'kota', 'nomor_telepon', 'email', 'contact_person',
        ]));

        return $this->response->setJSON($result);
    }

    public function detail(int $id)
    {
        $account = $this->accountService->find($id);

        if (! $account) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $account]);
    }

    public function edit(int $id)
    {
        return $this->detail($id);
    }

    public function update(int $id)
    {
        $model = new AccountModel();
        $rules = $model->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data tidak valid.',
                'errors'  => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->accountService->update($id, $this->request->getPost([
            'nama_account', 'alamat', 'kota', 'nomor_telepon', 'email', 'contact_person',
        ]));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->accountService->delete($id));
    }

    public function toggleStatus(int $id)
    {
        return $this->response->setJSON($this->accountService->toggleStatus($id));
    }
}
