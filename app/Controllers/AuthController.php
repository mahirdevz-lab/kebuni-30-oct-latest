<?php

namespace App\Controllers;

use App\Services\AuthService;

class AuthController extends BaseController
{
    protected AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function showLogin()
    {
        if ($this->authService->check()) {
            return redirect()->to('/admin/dashboard');
        }

        return view('auth/login', ['title' => 'Login']);
    }

    public function login()
    {
        $rules = [
            'login'    => 'required|min_length[3]',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $user = $this->authService->attempt(
            $this->request->getPost('login'),
            $this->request->getPost('password'),
            $this->request
        );

        if (! $user) {
            return redirect()->back()->withInput()
                ->with('error', 'Username/Email atau password salah.');
        }

        return redirect()->to('/admin/dashboard')
            ->with('success', 'Selamat datang kembali, ' . $user['full_name'] . '!');
    }

    public function logout()
    {
        $this->authService->logout();

        return redirect()->to('/login')->with('success', 'Anda telah logout.');
    }
}
