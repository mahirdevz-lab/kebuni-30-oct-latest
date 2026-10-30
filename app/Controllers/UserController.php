<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\UserService;

class UserController extends BaseController
{
    protected UserService $service;

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
        $this->service = new UserService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $roleId = (int) $this->request->getGet('role_id') ?: null;
        $status = $this->request->getGet('status');

        $data = [
            'title'       => 'User',
            'breadcrumbs' => [['label' => 'System'], ['label' => 'User']],
            'users'       => $this->service->paginate($search, $roleId, $status),
            'pager'       => $this->service->pager(),
            'roles'       => $this->service->roles(),
            'search'      => $search,
            'role_id'     => $roleId,
            'status'      => $status,
            'currentId'   => (int) session()->get('user_id'),
        ];

        if ($this->request->isAJAX()) {
            return view('user/_table', $data);
        }

        return view('user/index', $data);
    }

    public function create()
    {
        return view('user/form', [
            'title'       => 'Tambah User',
            'breadcrumbs' => [['label' => 'System'], ['label' => 'User', 'url' => base_url('admin/user')], ['label' => 'Tambah']],
            'user'        => null,
            'roles'       => $this->service->roles(),
        ]);
    }

    public function edit(int $id)
    {
        $user = $this->service->find($id);
        if (! $user) {
            return redirect()->to('/admin/user')->with('error', 'User tidak ditemukan.');
        }

        return view('user/form', [
            'title'       => 'Edit User',
            'breadcrumbs' => [['label' => 'System'], ['label' => 'User', 'url' => base_url('admin/user')], ['label' => 'Edit']],
            'user'        => $user,
            'roles'       => $this->service->roles(),
        ]);
    }

    public function store()
    {
        $rules = (new UserModel())->getValidationRules();
        $rules['password']         = 'required|min_length[8]';
        $rules['confirm_password'] = 'required|matches[password]';
        $rules['status']           = 'required|in_list[active,inactive]';

        if (! $this->validate($rules)) {
            return $this->invalid();
        }

        return $this->response->setJSON($this->service->create(
            $this->request->getPost(['full_name', 'username', 'email', 'role_id', 'status', 'password'])
        ));
    }

    public function update(int $id)
    {
        $rules = (new UserModel())->getValidationRules();
        $rules['username'] = str_replace('{id}', (string) $id, $rules['username']);
        $rules['email']    = str_replace('{id}', (string) $id, $rules['email']);
        $rules['password'] = 'permit_empty|min_length[8]';
        $rules['confirm_password'] = 'required_with[password]|matches[password]';
        $rules['status']   = 'required|in_list[active,inactive]';

        if (! $this->validate($rules)) {
            return $this->invalid();
        }

        return $this->response->setJSON($this->service->update(
            $id,
            $this->request->getPost(['full_name', 'username', 'email', 'role_id', 'status', 'password']),
            (int) session()->get('user_id')
        ));
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id, (int) session()->get('user_id')));
    }

    private function invalid()
    {
        return $this->response->setJSON([
            'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
        ])->setStatusCode(422);
    }
}
