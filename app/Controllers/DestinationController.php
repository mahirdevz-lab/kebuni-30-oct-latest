<?php

namespace App\Controllers;

use App\Models\DestinationModel;
use App\Services\DestinationService;

class DestinationController extends BaseController
{
    protected DestinationService $service;

    public function __construct()
    {
        $this->service = new DestinationService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $destinations = $this->service->paginate($search, $status);

        $data = [
            'title'       => 'Destination',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Destination'],
            ],
            'destinations' => $destinations,
            'pager'        => $this->service->pager(),
            'search'       => $search,
            'status'       => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('destination/_table', $data);
        }

        return view('destination/index', $data);
    }

    public function store()
    {
        $rules = (new DestinationModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->create($this->request->getPost(['name', 'code', 'notes']));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $destination = $this->service->find($id);

        if (! $destination) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $destination]);
    }

    public function update(int $id)
    {
        $model = new DestinationModel();
        $rules = $model->getValidationRules();
        $rules['name'] = str_replace('{id}', (string) $id, $rules['name']);

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->update($id, $this->request->getPost(['name', 'code', 'notes']));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }
}
