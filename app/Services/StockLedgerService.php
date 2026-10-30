<?php

namespace App\Services;

use RuntimeException;

/**
 * Satu-satunya pintu perubahan stok POS (PRD FR-07 / Business Rules).
 *
 * - Saldo hanya berubah lewat post() / reverse(); setiap perubahan = 1 baris stock_ledger.
 * - Valuasi moving average: mutasi masuk menambah nilai, mutasi keluar memakai avg_cost saat itu.
 * - Baris stock_balances dikunci (SELECT ... FOR UPDATE) sehingga dua kasir tidak dapat menjual stok yang sama.
 * - Idempotent: kombinasi (reference_type, reference_id, reference_line, movement_type) hanya boleh sekali;
 *   pemanggilan ulang mengembalikan mutasi yang sudah ada tanpa mengubah saldo.
 *
 * Wajib dipanggil di dalam transaksi database milik pemanggil.
 */
class StockLedgerService
{
    public const IN_TYPES  = ['OPENING', 'PURCHASE', 'BONUS', 'CUSTOMER_RETURN', 'TRANSFER_IN'];
    public const OUT_TYPES = ['SALE', 'PROMO_FREE', 'SUPPLIER_RETURN', 'BROKEN', 'TRANSFER_OUT'];
    // OPNAME_ADJUST dan REVERSAL arahnya ditentukan pemanggil (parameter 'direction').

    private const QTY_SCALE = 3;

    /**
     * @param array{
     *   product_id:int, location_id:int, movement_type:string, qty:float,
     *   direction?:string, unit_cost?:float, total_cost?:float,
     *   reference_type:string, reference_id:int, reference_line?:int,
     *   reason?:?string, user_id?:?int, effective_at?:string, allow_negative?:bool
     * } $m
     * @return array{id:int, duplicate:bool, qty:float, unit_cost:float, total_cost:float, balance_before:float, balance_after:float}
     */
    public function post(array $m): array
    {
        $db = db_connect();
        if ($db->transDepth < 1) {
            throw new RuntimeException('StockLedgerService::post harus dipanggil di dalam transaksi database.');
        }

        $type = $m['movement_type'];
        $qty  = round((float) $m['qty'], self::QTY_SCALE);
        if ($qty <= 0) {
            throw new StockException('Kuantitas mutasi harus lebih besar dari 0.');
        }

        $direction = $m['direction'] ?? (in_array($type, self::IN_TYPES, true) ? 'IN' : (in_array($type, self::OUT_TYPES, true) ? 'OUT' : null));
        if (! in_array($direction, ['IN', 'OUT'], true)) {
            throw new RuntimeException("Arah mutasi untuk {$type} harus ditentukan.");
        }

        $productId  = (int) $m['product_id'];
        $locationId = (int) $m['location_id'];
        $refLine    = (int) ($m['reference_line'] ?? 0);

        // Idempotensi
        $existing = $db->table('stock_ledger')
            ->where('reference_type', $m['reference_type'])->where('reference_id', $m['reference_id'])
            ->where('reference_line', $refLine)->where('movement_type', $type)
            ->get()->getRowArray();
        if ($existing) {
            return [
                'id' => (int) $existing['id'], 'duplicate' => true,
                'qty' => $existing['qty_in'] > 0 ? (float) $existing['qty_in'] : (float) $existing['qty_out'],
                'unit_cost' => (float) $existing['unit_cost'], 'total_cost' => (float) $existing['total_cost'],
                'balance_before' => (float) $existing['balance_before'], 'balance_after' => (float) $existing['balance_after'],
            ];
        }

        $bal = $this->lockBalance($productId, $locationId);
        $before = (float) $bal['qty'];
        $value  = (float) $bal['value'];
        $avg    = (float) $bal['avg_cost'];

        if ($direction === 'IN') {
            $totalCost = array_key_exists('total_cost', $m)
                ? round((float) $m['total_cost'], 2)
                : round($qty * (float) ($m['unit_cost'] ?? 0), 2);
            $unitCost  = $qty > 0 ? round($totalCost / $qty, 4) : 0.0;
            $after     = round($before + $qty, self::QTY_SCALE);
            $newValue  = round($value + $totalCost, 2);
        } else {
            $after = round($before - $qty, self::QTY_SCALE);
            if ($after < 0 && empty($m['allow_negative'])) {
                throw new StockException('Stok tidak mencukupi.', $productId, $before, $qty);
            }
            // Biaya keluar memakai moving average; bila reversal, pemanggil boleh menyuplai total_cost asli.
            $unitCost  = array_key_exists('unit_cost', $m) && ($m['movement_type'] === 'REVERSAL' || $m['movement_type'] === 'OPNAME_ADJUST')
                ? (float) $m['unit_cost'] : $avg;
            $totalCost = round($qty * $unitCost, 2);
            $newValue  = round($value - $totalCost, 2);
            if ($after <= 0) {
                $newValue = 0.0; // buang sisa pembulatan saat stok habis
            }
        }

        $newAvg = $after > 0 ? round($newValue / $after, 4) : ($direction === 'OUT' ? $avg : 0.0);
        $now    = date('Y-m-d H:i:s');

        $db->table('stock_ledger')->insert([
            'location_id'    => $locationId,
            'product_id'     => $productId,
            'movement_type'  => $type,
            'qty_in'         => $direction === 'IN' ? $qty : 0,
            'qty_out'        => $direction === 'OUT' ? $qty : 0,
            'balance_before' => $before,
            'balance_after'  => $after,
            'unit_cost'      => $unitCost,
            'total_cost'     => $totalCost,
            'avg_cost_after' => $newAvg,
            'value_after'    => $newValue,
            'effective_at'   => $m['effective_at'] ?? $now,
            'posted_at'      => $now,
            'reference_type' => $m['reference_type'],
            'reference_id'   => $m['reference_id'],
            'reference_line' => $refLine,
            'reason'         => $m['reason'] ?? null,
            'user_id'        => $m['user_id'] ?? (session()->get('user_id') ?: null),
            'status'         => 'POSTED',
            'reversal_of_id' => $m['reversal_of_id'] ?? null,
        ]);
        $ledgerId = (int) $db->insertID();

        $db->table('stock_balances')
            ->where('location_id', $locationId)->where('product_id', $productId)
            ->update(['qty' => $after, 'avg_cost' => $newAvg, 'value' => $newValue, 'updated_at' => $now]);

        return [
            'id' => $ledgerId, 'duplicate' => false, 'qty' => $qty, 'unit_cost' => $unitCost,
            'total_cost' => $totalCost, 'balance_before' => $before, 'balance_after' => $after,
        ];
    }

    /**
     * Membatalkan satu mutasi dengan mutasi lawan (REVERSAL); baris asli ditandai REVERSED.
     * Mutasi keluar yang dibalik masuk kembali dengan biaya aslinya, sehingga HPP historis tetap konsisten.
     */
    public function reverse(int $ledgerId, string $reason, ?int $userId = null): ?array
    {
        $db  = db_connect();
        $row = $db->query('SELECT * FROM stock_ledger WHERE id = ? FOR UPDATE', [$ledgerId])->getRowArray();
        if (! $row || $row['status'] === 'REVERSED' || $row['movement_type'] === 'REVERSAL') {
            return null;
        }

        $wasIn = (float) $row['qty_in'] > 0;
        $qty   = $wasIn ? (float) $row['qty_in'] : (float) $row['qty_out'];

        $payload = [
            'product_id'     => (int) $row['product_id'],
            'location_id'    => (int) $row['location_id'],
            'movement_type'  => 'REVERSAL',
            'direction'      => $wasIn ? 'OUT' : 'IN',
            'qty'            => $qty,
            'unit_cost'      => (float) $row['unit_cost'],
            'reference_type' => 'LEDGER',
            'reference_id'   => $ledgerId,
            'reason'         => $reason,
            'user_id'        => $userId,
            'reversal_of_id' => $ledgerId,
            'allow_negative' => false,
        ];
        if (! $wasIn) {
            $payload['total_cost'] = (float) $row['total_cost']; // nilai asli dikembalikan penuh
        }

        $res = $this->post($payload);

        $db->table('stock_ledger')->where('id', $ledgerId)->update(['status' => 'REVERSED']);

        return $res;
    }

    /** Saldo saat ini (tanpa mengunci). */
    public function balance(int $productId, int $locationId): array
    {
        $row = db_connect()->table('stock_balances')->where('product_id', $productId)->where('location_id', $locationId)->get()->getRowArray();

        return $row ?: ['qty' => 0, 'avg_cost' => 0, 'value' => 0];
    }

    private function lockBalance(int $productId, int $locationId): array
    {
        $db = db_connect();
        $db->query('INSERT IGNORE INTO stock_balances (location_id, product_id, qty, avg_cost, value, updated_at) VALUES (?, ?, 0, 0, 0, NOW())', [$locationId, $productId]);

        return $db->query('SELECT * FROM stock_balances WHERE location_id = ? AND product_id = ? FOR UPDATE', [$locationId, $productId])->getRowArray();
    }
}
