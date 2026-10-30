<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table         = 'activity_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'action', 'module', 'reference_type', 'reference_id', 'description', 'ip_address', 'user_agent'];
    protected $useTimestamps = true;
    protected $updatedField  = '';

    public function scopeList(?string $search = null, ?string $module = null, ?string $from = null, ?string $to = null)
    {
        $builder = $this->select('activity_logs.*, users.full_name, users.username')
            ->join('users', 'users.id = activity_logs.user_id', 'left')
            ->orderBy('activity_logs.id', 'DESC');

        if ($search) {
            $builder->groupStart()
                ->like('activity_logs.action', $search)
                ->orLike('activity_logs.description', $search)
                ->orLike('users.username', $search)
                ->groupEnd();
        }
        if ($module) {
            $builder->where('activity_logs.module', $module);
        }
        if ($from) {
            $builder->where('activity_logs.created_at >=', $from . ' 00:00:00');
        }
        if ($to) {
            $builder->where('activity_logs.created_at <=', $to . ' 23:59:59');
        }

        return $builder;
    }

    public function modules(): array
    {
        return array_column($this->select('module')->distinct()->orderBy('module')->findAll(), 'module');
    }

    public function getByReference(string $referenceType, int $referenceId): array
    {
        return $this->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }
}
