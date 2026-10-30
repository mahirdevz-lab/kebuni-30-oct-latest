<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Master data Aset Kantor (ATK, peralatan, dll).
 */
class OfficeAssetModel extends Model
{
    protected $table            = 'office_assets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['name', 'qty', 'price', 'purchase_date', 'status', 'notes'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    /**
     * Daftar aset dengan filter pencarian & status.
     */
    public function listAssets(?string $search = null, ?string $status = null): array
    {
        $builder = $this->orderBy('name', 'ASC');

        if ($search) {
            $builder->like('name', $search);
        }

        if ($status) {
            $builder->where('status', $status);
        }

        return $builder->findAll();
    }

    /**
     * Valuasi: total qty & total value (qty × price).
     * Hanya hitung status AKTIF kecuali diminta lain.
     */
    public function valuation(?string $status = 'AKTIF'): array
    {
        $builder = $this->select('COALESCE(SUM(qty), 0) AS total_qty, COALESCE(SUM(qty * price), 0) AS total_value');

        if ($status) {
            $builder->where('status', $status);
        }

        $row = $builder->first();

        return [
            'total_qty'   => (float) ($row['total_qty'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    /**
     * Total belanja kategori "Aset Kantor" dari transaksi keuangan.
     */
    public function financeTotal(?string $from = null, ?string $to = null): array
    {
        $db = db_connect();
        $builder = $db->table('financial_transactions ft')
            ->select('COALESCE(SUM(ft.amount), 0) AS total, COUNT(*) AS n_transaksi')
            ->join('financial_categories c', 'c.id = ft.category_id', 'left')
            ->where('ft.type', 'EXPENSE')
            ->where('LOWER(TRIM(c.name))', 'aset kantor');

        if ($from) {
            $builder->where('ft.transaction_date >=', $from);
        }

        if ($to) {
            $builder->where('ft.transaction_date <=', $to);
        }

        $row = $builder->get()->getRowArray();

        return [
            'total'        => (float) ($row['total'] ?? 0),
            'n_transaksi'  => (int) ($row['n_transaksi'] ?? 0),
        ];
    }

    /**
     * Rincian transaksi keuangan kategori "Aset Kantor".
     */
    public function financeDetails(?string $from = null, ?string $to = null): array
    {
        $db = db_connect();
        $builder = $db->table('financial_transactions ft')
            ->select('ft.transaction_date, ft.description, ft.amount, ca.name AS account_name')
            ->join('financial_categories c', 'c.id = ft.category_id', 'left')
            ->join('cash_accounts ca', 'ca.id = ft.cash_account_id', 'left')
            ->where('ft.type', 'EXPENSE')
            ->where('LOWER(TRIM(c.name))', 'aset kantor')
            ->orderBy('ft.transaction_date', 'DESC')
            ->orderBy('ft.id', 'DESC');

        if ($from) {
            $builder->where('ft.transaction_date >=', $from);
        }

        if ($to) {
            $builder->where('ft.transaction_date <=', $to);
        }

        return $builder->get()->getResultArray();
    }
}
