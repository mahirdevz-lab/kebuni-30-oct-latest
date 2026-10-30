<?php

namespace App\Services;

use App\Models\ActivityLogModel;

/** Mencatat siapa melakukan apa (tabel activity_logs). Kegagalan log tidak boleh menggagalkan aksi utama. */
class AuditLogService
{
    public function log(string $action, string $module, ?string $referenceType = null, ?int $referenceId = null, ?string $description = null): void
    {
        try {
            $request = service('request');

            (new ActivityLogModel())->insert([
                'user_id'        => session()->get('user_id') ?: null,
                'action'         => mb_substr($action, 0, 100),
                'module'         => mb_substr($module, 0, 50),
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'description'    => $description,
                'ip_address'     => method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
                'user_agent'     => method_exists($request, 'getUserAgent') ? mb_substr((string) $request->getUserAgent(), 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Audit log gagal: ' . $e->getMessage());
        }
    }
}
