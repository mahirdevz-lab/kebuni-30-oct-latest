<?php

namespace App\Controllers;

use App\Models\ActivityLogModel;

class AuditLogController extends BaseController
{
    public function index()
    {
        $search = $this->request->getGet('search');
        $module = $this->request->getGet('module');
        $from   = $this->request->getGet('from');
        $to     = $this->request->getGet('to');

        $model = new ActivityLogModel();
        // perPage 20 disengaja: audit log butuh densitas tinggi (banyak baris per halaman).
        // Di-set eksplisit agar tidak ikut default Config\Pager (10) — audit pagination §3.3.
        $rows  = $model->scopeList($search, $module, $from, $to)->paginate(20);

        $data = [
            'title'       => 'Audit Log',
            'breadcrumbs' => [['label' => 'System'], ['label' => 'Audit Log']],
            'rows'        => $rows,
            'pager'       => $model->pager,
            'modules'     => $model->modules(),
            'search'      => $search,
            'module'      => $module,
            'from'        => $from,
            'to'          => $to,
        ];

        if ($this->request->isAJAX()) {
            return view('audit_log/_table', $data);
        }

        return view('audit_log/index', $data);
    }
}
