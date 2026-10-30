<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AuthService;

class ProfileController extends BaseController
{
    protected AuthService $authService;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userModel   = new UserModel();
    }

    public function index()
    {
        $currentUser = $this->authService->user();
        $user        = $this->userModel->find($currentUser['id']);

        $data = [
            'title'       => 'Profil Saya',
            'breadcrumbs' => [
                ['label' => 'Profil Saya'],
            ],
            'user' => $user,
        ];

        return view('profile/index', $data);
    }

    public function updatePassword()
    {
        $rules = [
            'current_password' => 'required',
            'new_password'     => 'required|min_length[8]',
            'confirm_password' => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data tidak valid.',
                'errors'  => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $currentUser = $this->authService->user();
        $user        = $this->userModel->find($currentUser['id']);

        if (! password_verify($this->request->getPost('current_password'), $user['password_hash'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Password saat ini salah.'])->setStatusCode(422);
        }

        $this->userModel->update($user['id'], [
            'password_hash' => password_hash($this->request->getPost('new_password'), PASSWORD_DEFAULT),
        ]);

        return $this->response->setJSON(['success' => true, 'message' => 'Password berhasil diperbarui.']);
    }
}
