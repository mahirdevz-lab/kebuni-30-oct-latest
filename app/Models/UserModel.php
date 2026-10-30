<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'role_id', 'full_name', 'username', 'email', 'password_hash',
        'remember_token', 'status', 'last_login_at', 'last_login_ip',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'id'        => 'permit_empty|integer',
        'full_name' => 'required|min_length[3]|max_length[150]',
        'username'  => 'required|alpha_numeric|min_length[4]|max_length[100]|is_unique[users.username,id,{id}]',
        'email'     => 'required|valid_email|max_length[150]|is_unique[users.email,id,{id}]',
        'role_id'   => 'required|integer',
    ];

    protected $validationMessages = [
        'username' => [
            'is_unique' => 'Username sudah digunakan.',
        ],
        'email' => [
            'is_unique' => 'Email sudah terdaftar.',
        ],
    ];

    public function scopeList(?string $search = null, ?int $roleId = null, ?string $status = null)
    {
        $builder = $this->select('users.*, roles.name as role_name, roles.slug as role_slug')
            ->join('roles', 'roles.id = users.role_id')
            ->orderBy('users.created_at', 'DESC');

        if ($search) {
            $builder->groupStart()
                ->like('users.full_name', $search)
                ->orLike('users.username', $search)
                ->orLike('users.email', $search)
                ->groupEnd();
        }

        if ($roleId) {
            $builder->where('users.role_id', $roleId);
        }

        if ($status) {
            $builder->where('users.status', $status);
        }

        return $builder;
    }

    /**
     * Cari user aktif berdasarkan username atau email, beserta role & permission-nya.
     */
    public function findActiveByLogin(string $login): ?array
    {
        return $this->where('status', 'active')
            ->groupStart()
                ->where('username', $login)
                ->orWhere('email', $login)
            ->groupEnd()
            ->first();
    }
}
