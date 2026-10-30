<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Master rekening kas (Bank Utama, Petty Cash, dll).
 */
class CashAccountModel extends Model
{
    protected $table            = 'cash_accounts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['name', 'type', 'initial_balance', 'is_active', 'notes'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    /**
     * Daftar rekening aktif.
     */
    public function getActive(): array
    {
        return $this->where('is_active', 1)->orderBy('id')->findAll();
    }

    /**
     * Update saldo awal.
     */
    public function updateBalance(int $id, float $initialBalance): bool
    {
        return (bool) $this->update($id, ['initial_balance' => $initialBalance]);
    }

    /**
     * Saldo per rekening: initial + income - expense.
     */
    public function getBalances(): array
    {
        $accounts = $this->getActive();
        $db = db_connect();

        foreach ($accounts as &$acc) {
            $row = $db->table('financial_transactions')
                ->select("COALESCE(SUM(CASE WHEN type = 'INCOME' THEN amount ELSE 0 END), 0) AS total_in, COALESCE(SUM(CASE WHEN type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS total_out")
                ->where('cash_account_id', $acc['id'])
                ->get()->getRowArray();

            $acc['total_in']  = (float) ($row['total_in'] ?? 0);
            $acc['total_out'] = (float) ($row['total_out'] ?? 0);
            $acc['balance']   = (float) $acc['initial_balance'] + $acc['total_in'] - $acc['total_out'];
        }

        return $accounts;
    }
}
