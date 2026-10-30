<?php

namespace App\Models;

use CodeIgniter\Model;

class OpexAreaModel extends Model
{
    protected $table         = 'opex_areas';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['code', 'name', 'destination_id', 'status', 'notes'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'id'             => 'permit_empty|integer',
        'code'           => 'required|max_length[30]|is_unique[opex_areas.code,id,{id}]',
        'name'           => 'required|min_length[2]|max_length[100]',
        'destination_id' => 'permit_empty|integer',
        'status'         => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    protected $validationMessages = [
        'code' => ['is_unique' => 'Code sudah digunakan.'],
    ];

    public function scopeList(?string $search = null, ?string $status = null)
    {
        $builder = $this->select('opex_areas.*, destinations.name as destination_name')
            ->join('destinations', 'destinations.id = opex_areas.destination_id', 'left')
            ->orderBy('opex_areas.created_at', 'DESC');

        if ($search) {
            $builder->groupStart()
                ->like('opex_areas.name', $search)
                ->orLike('opex_areas.code', $search)
                ->groupEnd();
        }

        if ($status) {
            $builder->where('opex_areas.status', $status);
        }

        return $builder;
    }

    public function activeOptions(): array
    {
        return $this->select('id, code, name, destination_id')
            ->where('status', 'aktif')
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
