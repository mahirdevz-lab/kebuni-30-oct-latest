<?php

namespace App\Services;

use App\Models\LabourRateModel;
use App\Models\PricePeriodLabourRateModel;

class LabourRateService
{
    protected LabourRateModel $labourRateModel;
    protected PricePeriodLabourRateModel $periodLabourRateModel;

    public function __construct()
    {
        $this->labourRateModel       = new LabourRateModel();
        $this->periodLabourRateModel = new PricePeriodLabourRateModel();
    }

    public function paginate(?string $search, ?string $gender = null, ?string $status = null, int $perPage = 10)
    {
        $builder = $this->labourRateModel;

        if ($search) {
            $builder->like('name', $search);
        }

        if ($gender) {
            $builder->where('gender', $gender);
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder->paginate($perPage);
    }

    public function pager()
    {
        return $this->labourRateModel->pager;
    }

    public function find(int $id): ?array
    {
        return $this->labourRateModel->find($id);
    }

    public function activeOptions(): array
    {
        if (method_exists($this->labourRateModel, 'activeOptions')) {
            return $this->labourRateModel->activeOptions();
        }

        return $this->labourRateModel->where('status', 'aktif')->findAll();
    }

    public function create(array $data): array
    {
        $data['status'] = $data['status'] ?? 'aktif';

        if (! $this->labourRateModel->insert($data)) {
            return [
                'success' => false, 
                'message' => 'Data tidak valid.', 
                'errors'  => $this->labourRateModel->errors()
            ];
        }

        return [
            'success' => true, 
            'message' => 'Labour rate berhasil ditambahkan.', 
            'id'      => $this->labourRateModel->getInsertID()
        ];
    }

    public function update(int $id, array $data): array
    {
        if (! $this->labourRateModel->find($id)) {
            return ['success' => false, 'message' => 'Labour rate tidak ditemukan.'];
        }

        if (! $this->labourRateModel->update($id, $data)) {
            return [
                'success' => false, 
                'message' => 'Data tidak valid.', 
                'errors'  => $this->labourRateModel->errors()
            ];
        }

        return ['success' => true, 'message' => 'Labour rate berhasil diperbarui.'];
    }

    public function delete(int $id): array
    {
        if (! $this->labourRateModel->find($id)) {
            return ['success' => false, 'message' => 'Labour rate tidak ditemukan.'];
        }

        $this->labourRateModel->delete($id);

        return ['success' => true, 'message' => 'Labour rate berhasil dihapus.'];
    }

    // ---------------- PRICE PERIOD LABOUR RATES ----------------

    /**
     * Ambil tarif kuli berdasarkan ID Price Period
     */
    public function getRatesByPeriod(int $pricePeriodId): array
    {
        return $this->periodLabourRateModel
            ->select('price_period_labour_rates.*, labour_rates.name as labour_name, labour_rates.gender')
            ->join('labour_rates', 'labour_rates.id = price_period_labour_rates.labour_rate_id')
            ->where('price_period_labour_rates.price_period_id', $pricePeriodId)
            ->findAll();
    }

    /**
     * Simpan / Replace tarif kuli untuk suatu Price Period
     * @param array $rates [['labour_rate_id' => int, 'rate_per_person' => float], ...]
     */
    public function savePeriodRates(int $pricePeriodId, array $rates): array
    {
        // Hapus rate lama untuk periode ini
        $this->periodLabourRateModel->where('price_period_id', $pricePeriodId)->delete();

        $insertBatch = [];
        foreach ($rates as $item) {
            if (! empty($item['labour_rate_id']) && isset($item['rate_per_person'])) {
                $insertBatch[] = [
                    'price_period_id' => $pricePeriodId,
                    'labour_rate_id'  => (int) $item['labour_rate_id'],
                    'rate_per_person' => (float) $item['rate_per_person'],
                ];
            }
        }

        if (! empty($insertBatch)) {
            $this->periodLabourRateModel->insertBatch($insertBatch);
        }

        return ['success' => true, 'message' => 'Tarif kuli periode berhasil disimpan.'];
    }
}