<?php

namespace App\Models;

use CodeIgniter\Model;

class CompanyLocationModel extends Model
{
    protected $table         = 'company_locations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'latitude', 'longitude', 'radius_meter', 'status'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'name'         => 'required|min_length[2]|max_length[100]',
        'latitude'     => 'required|decimal|greater_than_equal_to[-90]|less_than_equal_to[90]',
        'longitude'    => 'required|decimal|greater_than_equal_to[-180]|less_than_equal_to[180]',
        'radius_meter' => 'required|integer|greater_than[0]',
        'status'       => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    public function activeLocations(): array
    {
        return $this->where('status', 'aktif')->findAll();
    }

    /**
     * Scope daftar + pencarian (BUG-1/BUG-3 PRD-BACKOFFICE-UI T6).
     */
    public function scopeList(?string $search = null)
    {
        $builder = $this->orderBy('name', 'ASC');

        if ($search) {
            $builder->like('name', $search);
        }

        return $builder;
    }
}
