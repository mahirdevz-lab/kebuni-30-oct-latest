<?php

namespace App\Filters;

use App\Services\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Memastikan hanya user yang sudah login dapat mengakses route Back Office.
 * Route absensi publik (/absensi) TIDAK menggunakan filter ini.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = new AuthService();

        if (! $auth->check()) {
            if ($request->isAJAX()) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON(['success' => false, 'message' => 'Sesi Anda telah berakhir. Silakan login kembali.']);
            }

            session()->setFlashdata('error', 'Silakan login terlebih dahulu.');

            return redirect()->to('/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada aksi setelah request.
    }
}
