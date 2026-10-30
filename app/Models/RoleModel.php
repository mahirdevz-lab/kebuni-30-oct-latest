<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table         = 'roles';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'slug', 'description'];
    protected $useTimestamps = true;

    /**
     * Ambil semua permission slug milik sebuah role.
     */
    public function getPermissionSlugs(int $roleId): array
    {
        $rows = $this->db->table('role_permissions rp')
            ->select('p.slug')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->get()
            ->getResultArray();

        return array_column($rows, 'slug');
    }
}
