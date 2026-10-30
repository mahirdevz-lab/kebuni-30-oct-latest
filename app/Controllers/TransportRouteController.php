<?php

namespace App\Controllers;

use App\Models\TransportRouteModel;
use App\Services\DestinationService;
use App\Services\TransportRouteService;

class TransportRouteController extends BaseController
{
    protected TransportRouteService $service;
    protected DestinationService $destinationService;

    public function __construct()
    {
        $this->service            = new TransportRouteService();
        $this->destinationService = new DestinationService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $routes = $this->service->paginate($search, $status);

        $data = [
            'title'       => 'Transport Route',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Transport Route'],
            ],
            'routes'       => $routes,
            'pager'        => $this->service->pager(),
            'search'       => $search,
            'status'       => $status,
            'destinations' => $this->destinationService->activeOptions(),
        ];

        if ($this->request->isAJAX()) {
            return view('transport_route/_table', $data);
        }

        return view('transport_route/index', $data);
    }

    public function store()
    {
        $rules = (new TransportRouteModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->create($this->request->getPost(['origin', 'destination_id', 'default_basis', 'notes']));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $route = $this->service->find($id);

        if (! $route) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $route]);
    }

    public function update(int $id)
    {
        $rules = (new TransportRouteModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->update($id, $this->request->getPost(['origin', 'destination_id', 'default_basis', 'notes']));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }
}
