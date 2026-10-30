<?php

namespace App\Models;

use CodeIgniter\Model;

class DestinationModel extends Model
{
    protected $table         = 'destinations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['code', 'name', 'status', 'notes'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'id'     => 'permit_empty|integer',
        'name'   => 'required|min_length[2]|max_length[100]|is_unique[destinations.name,id,{id}]',
        'code'   => 'permit_empty|max_length[30]',
        'status' => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    protected $validationMessages = [
        'name' => ['is_unique' => 'Destination dengan nama ini sudah ada.'],
    ];

    public function activeOptions(): array
    {
        return $this->select('id, code, name')->where('status', 'aktif')->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Scope daftar + filter untuk pagination (BUG-1/BUG-3 PRD-BACKOFFICE-UI T2).
     */
    public function scopeList(?string $search = null, ?string $status = null)
    {
        $builder = $this->orderBy('name', 'ASC');

        if ($search) {
            $builder->groupStart()
                ->like('name', $search)
                ->orLike('code', $search)
                ->groupEnd();
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder;
    }
}
