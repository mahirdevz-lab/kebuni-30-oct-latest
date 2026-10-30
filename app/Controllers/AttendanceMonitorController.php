<?php

namespace App\Controllers;

use App\Models\CompanyLocationModel;
use App\Services\AttendanceService;

class AttendanceMonitorController extends BaseController
{
    protected AttendanceService $service;

    public function __construct()
    {
        $this->service = new AttendanceService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $from   = $this->request->getGet('from') ?: date('Y-m-d');
        $to     = $this->request->getGet('to') ?: date('Y-m-d');
        $type   = $this->request->getGet('type');
        $loc    = $this->request->getGet('location_status');

        $data = [
            'title'       => 'Monitoring Absensi',
            'breadcrumbs' => [['label' => 'Absensi'], ['label' => 'Monitoring']],
            'rows'        => $this->service->paginate($search, $from, $to, $type, $loc),
            'pager'       => $this->service->pager(),
            'summary'     => $this->service->summaryForDate(),
            'search'      => $search,
            'from'        => $from,
            'to'          => $to,
            'type'        => $type,
            'loc'         => $loc,
        ];

        if ($this->request->isAJAX()) {
            return view('attendance/_table', $data);
        }

        return view('attendance/monitor', $data);
    }

    public function summary()
    {
        return $this->response->setJSON(['success' => true] + $this->service->summaryForDate($this->request->getGet('date')));
    }

    public function recap()
    {
        $from   = $this->request->getGet('from') ?: date('Y-m-01');
        $to     = $this->request->getGet('to') ?: date('Y-m-t');
        $search = $this->request->getGet('search');

        return view('attendance/recap', [
            'title'       => 'Rekap Absensi',
            'breadcrumbs' => [['label' => 'Absensi'], ['label' => 'Rekap']],
            'rows'        => $this->service->recap($from, $to, $search),
            'from'        => $from,
            'to'          => $to,
            'search'      => $search,
        ]);
    }

    /** Foto absensi bersifat privat: hanya bisa dilihat user back office yang login. */
    public function photo(int $id)
    {
        $row = $this->service->find($id);
        $path = $row ? realpath(WRITEPATH . 'uploads/' . $row['photo_path']) : false;
        $base = realpath(WRITEPATH . 'uploads');

        if (! $path || ! $base || strncmp($path, $base, strlen($base)) !== 0 || ! is_file($path)) {
            return $this->response->setStatusCode(404, 'Foto tidak ditemukan');
        }

        return $this->response
            ->setHeader('Content-Type', mime_content_type($path) ?: 'image/jpeg')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody(file_get_contents($path));
    }

    // ---------------- LOKASI KANTOR (geofence) ----------------

    public function locations()
    {
        $search = $this->request->getGet('search');

        $data = [
            'title'       => 'Lokasi Kantor',
            'breadcrumbs' => [['label' => 'Absensi'], ['label' => 'Lokasi Kantor']],
            'locations'   => $this->service->paginateLocations($search),
            'pager'       => $this->service->locationsPager(),
            'search'      => $search,
        ];

        if ($this->request->isAJAX()) {
            return view('attendance/_locations_table', $data);
        }

        return view('attendance/locations', $data);
    }

    public function saveLocation(?int $id = null)
    {
        $rules = (new CompanyLocationModel())->getValidationRules();
        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        return $this->response->setJSON($this->service->saveLocation(
            $id,
            $this->request->getPost(['name', 'latitude', 'longitude', 'radius_meter'])
        ));
    }

    public function toggleLocation(int $id)
    {
        return $this->response->setJSON($this->service->toggleLocation($id));
    }

    public function deleteLocation(int $id)
    {
        return $this->response->setJSON($this->service->deleteLocation($id));
    }
}
