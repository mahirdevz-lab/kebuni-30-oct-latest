<?php

namespace App\Filters;

use App\Services\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filter berbasis permission (bukan hanya login).
 *
 * Dipasang di routes dengan argumen slug permission, contoh:
 *   'filter' => 'role:account.view'
 *
 * Dengan demikian pengecekan permission TIDAK perlu diulang manual
 * di setiap controller/method.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = new AuthService();

        if (! $auth->check()) {
            return redirect()->to('/login');
        }

        // Tidak ada permission spesifik yang diminta -> cukup login.
        if (empty($arguments)) {
            return;
        }

        foreach ($arguments as $permissionSlug) {
            if ($auth->hasPermission($permissionSlug)) {
                return; // Salah satu permission terpenuhi.
            }
        }

        if ($request->isAJAX()) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['success' => false, 'message' => 'Anda tidak memiliki akses untuk melakukan aksi ini.']);
        }

        return service('response')
            ->setStatusCode(403)
            ->setBody(view('errors/html/error_403'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak ada aksi setelah request.
    }
}
