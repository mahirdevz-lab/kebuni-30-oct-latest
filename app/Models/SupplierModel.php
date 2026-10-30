<?php

namespace App\Models;

use CodeIgniter\Model;

class SupplierModel extends Model
{
    protected $table            = 'suppliers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'nama_supplier', 'alamat', 'kota', 'nomor_telepon',
        'email', 'contact_person', 'status',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'nama_supplier' => 'required|min_length[3]|max_length[150]',
        'alamat'        => 'permit_empty|max_length[1000]',
        'kota'          => 'permit_empty|max_length[100]',
        'nomor_telepon' => 'permit_empty|regex_match[/^[0-9+\-\s()]{6,30}$/]',
        'email'         => 'permit_empty|valid_email|max_length[150]',
        'contact_person'=> 'permit_empty|max_length[100]',
        'status'        => 'permit_empty|in_list[aktif,nonaktif]',
    ];

    protected $validationMessages = [
        'nomor_telepon' => [
            'regex_match' => 'Format nomor telepon tidak valid.',
        ],
    ];

    public function scopeList(?string $search = null, ?string $status = null)
    {
        $builder = $this->orderBy('created_at', 'DESC');

        if ($search) {
            $builder->groupStart()
                ->like('nama_supplier', $search)
                ->orLike('kota', $search)
                ->orLike('email', $search)
                ->orLike('contact_person', $search)
                ->groupEnd();
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder;
    }

    /**
     * Dropdown supplier aktif, dipakai di form Barang & Transaksi.
     */
    public function activeOptions(): array
    {
        return $this->select('id, nama_supplier')
            ->where('status', 'aktif')
            ->orderBy('nama_supplier', 'ASC')
            ->findAll();
    }
}
