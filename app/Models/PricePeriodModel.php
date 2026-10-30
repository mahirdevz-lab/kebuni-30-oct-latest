<?php

namespace App\Models;

use CodeIgniter\Model;

class PricePeriodModel extends Model
{
    protected $table            = 'price_periods';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;

    protected $allowedFields = [
        'period_code', 'name', 'start_date', 'end_date', 'status', 'notes', 'labour_budget', 'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public const STATUSES = ['DRAFT', 'PUBLISHED', 'CLOSED'];

    protected $validationRules = [
        'name'       => 'required|min_length[3]|max_length[150]',
        'start_date' => 'required|valid_date[Y-m-d]',
        'end_date'   => 'required|valid_date[Y-m-d]',
        'status'     => 'permit_empty|in_list[DRAFT,PUBLISHED,CLOSED]',
    ];

    public function scopeList(?string $search = null, ?string $status = null)
    {
        $builder = $this->orderBy('start_date', 'DESC');

        if ($search) {
            $builder->groupStart()
                ->like('period_code', $search)
                ->orLike('name', $search)
                ->groupEnd();
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder;
    }

    /**
     * Generate kode periode otomatis: PP-YYYY-MM-DD (berdasarkan start_date).
     * Jika sudah ada kode yang sama pada tanggal tsb, tambahkan suffix urut.
     */
    public function generatePeriodCode(string $startDate): string
    {
        $base = 'PP-' . $startDate;
        $code = $base;
        $i    = 1;

        while ($this->where('period_code', $code)->first()) {
            $i++;
            $code = $base . '-' . $i;
        }

        return $code;
    }

    /**
     * Cari Price Period PUBLISHED yang aktif mencakup tanggal tertentu.
     * Dipakai oleh Taking Order (fase berikutnya) untuk auto-select periode
     * berdasarkan tanggal transaksi.
     */
    public function findActiveForDate(string $date): ?array
    {
        return $this->where('status', 'PUBLISHED')
            ->where('start_date <=', $date)
            ->where('end_date >=', $date)
            ->orderBy('start_date', 'DESC')
            ->first();
    }

    /**
     * Cek overlap tanggal dengan Price Period lain yang berstatus PUBLISHED,
     * dipakai untuk validasi agar tidak ada dua periode aktif yang bertabrakan.
     */
    public function hasOverlap(string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        $builder = $this->where('status', 'PUBLISHED')
            ->where('start_date <=', $endDate)
            ->where('end_date >=', $startDate);

        if ($excludeId) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }
}

