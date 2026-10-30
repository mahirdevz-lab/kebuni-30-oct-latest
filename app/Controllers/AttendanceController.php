<?php

namespace App\Controllers;

use App\Services\AttendanceService;

/** Halaman absensi PUBLIC (tanpa login). */
class AttendanceController extends BaseController
{
    public function showForm()
    {
        return view('attendance/form', ['title' => 'Absensi']);
    }

    public function submit()
    {
        $service = new AttendanceService();

        $result = $service->submit(
            $this->request->getPost(['nama', 'attendance_type', 'photo', 'latitude', 'longitude', 'accuracy', 'device_info']),
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        return $this->response->setJSON($result)->setStatusCode($result['success'] ? 200 : 422);
    }
}
