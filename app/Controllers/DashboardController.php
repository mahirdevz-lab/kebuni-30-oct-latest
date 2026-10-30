<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DashboardService;

class DashboardController extends BaseController
{
    public function index()
    {
        $auth = new AuthService();

        $data = (new DashboardService())->summary(
            $auth->hasPermission('finance.view'),
            $auth->hasPermission('attendance.view'),
            $auth->hasPermission('setting.view')
        );

        return view('dashboard/index', $data + [
            'title'       => 'Ringkasan',
            'breadcrumbs' => [['label' => 'Dashboard'], ['label' => 'Ringkasan']],
            'user'        => $auth->user(),
        ]);
    }
}
