<?php

namespace App\Controllers;

use App\Models\OpexAreaModel;
use App\Services\DestinationService;
use App\Services\OpexAreaService;

class OpexAreaController extends BaseController
{
    protected OpexAreaService $service;
    protected DestinationService $destinationService;

    public function __construct()
    {
        $this->service            = new OpexAreaService();
        $this->destinationService = new DestinationService();
    }

    public function index()
    {
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $areas = $this->service->paginate($search, $status);

        $data = [
            'title'       => 'OPEX Area',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'OPEX Area'],
            ],
            'areas'        => $areas,
            'pager'        => $this->service->pager(),
            'search'       => $search,
            'status'       => $status,
            'destinations' => $this->destinationService->activeOptions(),
        ];

        if ($this->request->isAJAX()) {
            return view('opex_area/_table', $data);
        }

        return view('opex_area/index', $data);
    }

    public function store()
    {
        $rules = (new OpexAreaModel())->getValidationRules();

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->create($this->request->getPost(['code', 'name', 'destination_id', 'notes']));

        return $this->response->setJSON($result);
    }

    public function edit(int $id)
    {
        $area = $this->service->find($id);

        if (! $area) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.'])->setStatusCode(404);
        }

        return $this->response->setJSON(['success' => true, 'data' => $area]);
    }

    public function update(int $id)
    {
        $model = new OpexAreaModel();
        $rules = $model->getValidationRules();
        $rules['code'] = str_replace('{id}', (string) $id, $rules['code']);

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $result = $this->service->update($id, $this->request->getPost(['code', 'name', 'destination_id', 'notes']));

        return $this->response->setJSON($result);
    }

    public function delete(int $id)
    {
        return $this->response->setJSON($this->service->delete($id));
    }
}
