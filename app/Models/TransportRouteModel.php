<?php

namespace App\Models;

use CodeIgniter\Model;

class TransportRouteModel extends Model
{
    protected $table            = 'transport_routes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;

    protected $allowedFields = ['origin', 'destination_id', 'default_basis', 'status', 'notes'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'origin'         => 'required|min_length[2]|max_length[100]',
        'destination_id' => 'required|integer',
        'default_basis'  => 'permit_empty|in_list[CAPACITY,ACTUAL]',
        'status'         => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    public function scopeList(?string $search = null, ?string $status = null)
    {
        $builder = $this->select('transport_routes.*, destinations.name as destination_name')
            ->join('destinations', 'destinations.id = transport_routes.destination_id')
            ->orderBy('transport_routes.created_at', 'DESC');

        if ($search) {
            $builder->groupStart()
                ->like('transport_routes.origin', $search)
                ->orLike('destinations.name', $search)
                ->groupEnd();
        }

        if ($status) {
            $builder->where('transport_routes.status', $status);
        }

        return $builder;
    }

    public function activeOptions(): array
    {
        return $this->select('transport_routes.id, transport_routes.origin, transport_routes.default_basis, destinations.name as destination_name')
            ->join('destinations', 'destinations.id = transport_routes.destination_id')
            ->where('transport_routes.status', 'aktif')
            ->orderBy('destinations.name', 'ASC')
            ->findAll();
    }
}
