<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\OfficeAssetModel;

/**
 * Master Data Aset Kantor + Report.
 */
class OfficeAssetController extends BaseController
{
    // ---------------- MASTER DATA ----------------

    public function index()
    {
        $model  = new OfficeAssetModel();
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status') ?: null;

        $data = [
            'title'       => 'Aset Kantor',
            'breadcrumbs' => [
                ['label' => 'Master Data'],
                ['label' => 'Aset Kantor'],
            ],
            'assets'     => $model->listAssets($search, $status),
            'valuation'  => $model->valuation('AKTIF'),
            'search'     => $search,
            'status'     => $status,
        ];

        if ($this->request->isAJAX()) {
            return view('office_asset/_table', $data);
        }

        return view('office_asset/index', $data);
    }

    public function store()
    {
        $model = new OfficeAssetModel();

        $data = [
            'name'          => trim((string) $this->request->getPost('name')),
            'qty'           => (float) $this->request->getPost('qty'),
            'price'         => (float) $this->request->getPost('price'),
            'purchase_date' => $this->request->getPost('purchase_date') ?: null,
            'status'        => $this->request->getPost('status') ?: 'AKTIF',
            'notes'         => $this->request->getPost('notes'),
        ];

        if ($data['name'] === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Nama barang wajib diisi.']);
        }

        if ($data['qty'] <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Qty harus lebih dari 0.']);
        }

        if (! $model->insert($data)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan.', 'errors' => $model->errors()]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Aset berhasil ditambahkan.']);
    }

    public function update(int $id)
    {
        $model = new OfficeAssetModel();
        $asset = $model->find($id);

        if (! $asset) {
            return $this->response->setJSON(['success' => false, 'message' => 'Aset tidak ditemukan.']);
        }

        $data = [
            'name'          => trim((string) $this->request->getPost('name')),
            'qty'           => (float) $this->request->getPost('qty'),
            'price'         => (float) $this->request->getPost('price'),
            'purchase_date' => $this->request->getPost('purchase_date') ?: null,
            'status'        => $this->request->getPost('status') ?: 'AKTIF',
            'notes'         => $this->request->getPost('notes'),
        ];

        if ($data['name'] === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Nama barang wajib diisi.']);
        }

        if (! $model->update($id, $data)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan.', 'errors' => $model->errors()]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Aset berhasil diupdate.']);
    }

    public function delete(int $id)
    {
        $model = new OfficeAssetModel();

        if (! $model->find($id)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Aset tidak ditemukan.']);
        }

        $model->delete($id);

        return $this->response->setJSON(['success' => true, 'message' => 'Aset berhasil dihapus.']);
    }

    // ---------------- REPORT ----------------

    public function report()
    {
        $model = new OfficeAssetModel();
        $from = $this->request->getGet('from') ?: null;
        $to   = $this->request->getGet('to') ?: null;

        // 1. Valuasi dari master data
        $valuationAktif = $model->valuation('AKTIF');
        $valuationAll   = $model->valuation(null);

        // 2. Penarikan dari data keuangan kategori "Aset Kantor"
        $finance       = $model->financeTotal($from, $to);
        $financeDetail = $model->financeDetails($from, $to);

        $data = [
            'title'       => 'Report Aset Kantor',
            'breadcrumbs' => [
                ['label' => 'Report'],
                ['label' => 'Aset Kantor'],
            ],
            'valuationAktif' => $valuationAktif,
            'valuationAll'   => $valuationAll,
            'finance'        => $finance,
            'financeDetail'  => $financeDetail,
            'from'           => $from,
            'to'             => $to,
            // Selisih: valuasi master vs realisasi keuangan
            'selisih'        => $valuationAktif['total_value'] - $finance['total'],
        ];

        return view('office_asset/report', $data);
    }
}
