<?php

namespace App\Controllers;

use App\Models\SettingModel;

class SettingController extends BaseController
{
    public function index()
    {
        return view('setting/index', [
            'title'       => 'Setting',
            'breadcrumbs' => [['label' => 'System'], ['label' => 'Setting']],
            'settings'    => (new SettingModel())->allValues(),
        ]);
    }

    public function update()
    {
        $rules = [
            'company_name'          => 'required|min_length[2]|max_length[100]',
            'attendance_late_after' => 'required|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]',
            'opname_variance_pct'     => 'required|decimal|greater_than_equal_to[0]',
            'opname_variance_value'   => 'required|decimal|greater_than_equal_to[0]',
            'shrinkage_high_risk_pct' => 'required|decimal|greater_than_equal_to[0]',
            'reorder_cover_days'      => 'required|integer|greater_than[0]|less_than_equal_to[60]',
            'reorder_lookback_days'   => 'required|integer|greater_than[0]|less_than_equal_to[90]',
            'bonus_cost_policy'       => 'required|in_list[allocate,zero]',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false, 'message' => 'Data tidak valid.', 'errors' => $this->validator->getErrors(),
            ])->setStatusCode(422);
        }

        $model = new SettingModel();
        $keys = ['company_name', 'attendance_late_after', 'opname_variance_pct', 'opname_variance_value', 'shrinkage_high_risk_pct', 'reorder_cover_days', 'reorder_lookback_days', 'bonus_cost_policy'];
        foreach ($keys as $key) {
            $model->put($key, trim((string) $this->request->getPost($key)));
        }

        (new \App\Services\AuditLogService())->log('Ubah Setting', 'setting', null, null, implode(', ', array_map(fn ($k) => $k . '=' . trim((string) $this->request->getPost($k)), $keys)));

        return $this->response->setJSON(['success' => true, 'message' => 'Setting berhasil disimpan.']);
    }
}
