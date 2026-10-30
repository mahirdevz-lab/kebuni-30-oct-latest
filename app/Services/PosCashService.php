<?php

namespace App\Services;

use App\Models\FinancialTransactionModel;
use App\Models\SettingModel;

/**
 * Setoran Kas Kasir ke bank & settlement QRIS (+ MDR) - PRD A4.
 *
 * Semuanya transfer internal / biaya, BUKAN omzet baru:
 * - Setoran      : Kas Kasir -> rekening tujuan (TRANSFER_OUT/IN, kategori "Pemindahan Dana").
 * - Settlement   : QRIS Clearing -> Bank sebesar dana bersih (TRANSFER_OUT/IN) + biaya MDR aktual (EXPENSE QRIS_MDR dari QRIS Clearing).
 * Transfer memakai reference_id NULL pada sisi keluar (pola FinanceService::recordTransfer) agar tidak bentrok dengan unique key ledger.
 */
class PosCashService
{
    private SettingModel $settings;

    public function __construct()
    {
        $this->settings = new SettingModel();
    }

    /** Kas yang tersedia di laci untuk disetor sekarang. */
    public function availableCash(): array
    {
        $days = new DailyClosingService();
        $open = $days->openDay();
        if ($open) {
            $s = $days->summary($open);

            return ['amount' => (float) $s['expected_cash'], 'timing' => 'DURING_DAY', 'day' => $open];
        }
        $last = $days->lastDay();
        $exp  = $days->expectedOpeningCash();

        return ['amount' => $exp ?? 0.0, 'timing' => 'AFTER_EOD', 'day' => $last];
    }

    public function deposit(float $amount, int $toAccountId, ?string $referenceNo, ?string $notes, ?int $userId, ?string $proofPath = null): array
    {
        $amount = round($amount, 2);
        $cashId = (int) $this->settings->value('pos_cash_account', '0');
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Nominal setoran harus lebih dari 0.'];
        }
        $db = db_connect();
        if ($toAccountId === $cashId || $toAccountId === (int) $this->settings->value('pos_qris_account', '0')
            || ! $db->table('cash_accounts')->where('id', $toAccountId)->where('is_active', 1)->countAllResults()) {
            return ['success' => false, 'message' => 'Pilih rekening tujuan setoran (mis. Bank Utama / Petty Cash).'];
        }

        $db->transBegin();
        try {
            // Kunci hari kas terakhir agar EOD/setoran paralel tidak saling mendahului.
            $days = new DailyClosingService();
            $open = $days->openDay();
            if ($open) {
                $db->query('SELECT id FROM pos_daily_closings WHERE id = ? FOR UPDATE', [$open['id']]);
            }
            $avail = $this->availableCash();
            if ($avail['day'] === null) {
                throw new StockException('Belum ada hari kas. Buka toko dulu.');
            }
            if ($amount > $avail['amount'] + 0.005) {
                throw new StockException('Nominal melebihi kas yang tersedia di laci (Rp ' . number_format($avail['amount'], 0, ',', '.') . ').');
            }
            $day  = $avail['day'];
            $biz  = $day['business_date'];
            $now  = date('Y-m-d H:i:s');

            $db->table('pos_cash_deposits')->insert([
                'deposit_no' => 'SET-TMP-' . bin2hex(random_bytes(6)), 'from_account_id' => $cashId, 'to_account_id' => $toAccountId,
                'amount' => $amount, 'daily_closing_id' => (int) $day['id'], 'timing' => $avail['timing'], 'business_date' => $biz,
                'reference_no' => $referenceNo ? mb_substr(trim($referenceNo), 0, 60) : null, 'notes' => $notes ? mb_substr(trim($notes), 0, 255) : null, 'proof_path' => $proofPath,
                'created_by' => $userId, 'created_at' => $now,
            ]);
            $id = (int) $db->insertID();
            $no = sprintf('SET-%s-%04d', date('Ymd'), $id);

            [$outId, $inId] = $this->transfer($cashId, $toAccountId, $amount, $biz, "Setoran kas kasir {$no}", $userId);
            $db->table('pos_cash_deposits')->where('id', $id)->update(['deposit_no' => $no, 'transfer_out_id' => $outId, 'transfer_in_id' => $inId]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Setoran gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Setoran gagal disimpan.'];
        }

        (new AuditLogService())->log('Setoran Kas Kasir', 'pos', 'POS_DEPOSIT', $id, "{$no} Rp " . number_format($amount, 0, ',', '.'));

        return ['success' => true, 'message' => "Setoran {$no} Rp " . number_format($amount, 0, ',', '.') . ' tercatat sebagai transfer internal (bukan omzet).', 'id' => $id];
    }

    /** Pembayaran QRIS terkonfirmasi yang belum disettle. Pembayaran dari penjualan yang sudah diretur dipisah (tangani manual). */
    public function unsettledQris(): array
    {
        $rows = db_connect()->query(
            "SELECT p.id, p.amount, p.reference_no, s.id AS sale_id, s.sale_no, s.sale_date,
                    (SELECT COUNT(*) FROM pos_returns r WHERE r.sale_id = s.id AND r.status = 'POSTED') AS n_returns
             FROM pos_payments p JOIN pos_sales s ON s.id = p.sale_id
             WHERE p.status = 'CONFIRMED' AND p.settlement_id IS NULL AND s.status = 'POSTED'
               AND ((p.method = 'QRIS' AND p.corrected_method IS NULL) OR p.corrected_method = 'QRIS')
             ORDER BY s.sale_date, p.id"
        )->getResultArray();

        return ['ok' => array_values(array_filter($rows, static fn ($r) => (int) $r['n_returns'] === 0)),
                'blocked' => array_values(array_filter($rows, static fn ($r) => (int) $r['n_returns'] > 0))];
    }

    /** @param list<int> $paymentIds */
    public function settleQris(array $paymentIds, float $netAmount, string $settlementDate, ?string $referenceNo, ?string $notes, int $bankAccountId, ?int $userId, ?string $proofPath = null): array
    {
        $paymentIds = array_values(array_unique(array_map('intval', $paymentIds)));
        $netAmount  = round($netAmount, 2);
        $ts         = strtotime($settlementDate);
        if (! $paymentIds) {
            return ['success' => false, 'message' => 'Pilih pembayaran QRIS yang disettle.'];
        }
        if (! $ts || $ts > strtotime('+1 day')) {
            return ['success' => false, 'message' => 'Tanggal settlement tidak valid.'];
        }
        $clearing = (int) $this->settings->value('pos_qris_account', '0');
        $db       = db_connect();
        if ($bankAccountId === $clearing || ! $db->table('cash_accounts')->where('id', $bankAccountId)->where('is_active', 1)->countAllResults()) {
            return ['success' => false, 'message' => 'Pilih rekening bank penerima settlement.'];
        }

        $db->transBegin();
        try {
            $rows = $db->query(
                'SELECT p.*, s.sale_no, (SELECT COUNT(*) FROM pos_returns r WHERE r.sale_id = s.id AND r.status = \'POSTED\') AS n_returns
                 FROM pos_payments p JOIN pos_sales s ON s.id = p.sale_id WHERE p.id IN (' . implode(',', $paymentIds) . ') FOR UPDATE'
            )->getResultArray();
            if (count($rows) !== count($paymentIds)) {
                throw new StockException('Sebagian pembayaran tidak ditemukan.');
            }
            $gross = 0.0;
            foreach ($rows as $r) {
                $isQris = ($r['method'] === 'QRIS' && ! $r['corrected_method']) || $r['corrected_method'] === 'QRIS';
                if ($r['status'] !== 'CONFIRMED' || $r['settlement_id'] || ! $isQris) {
                    throw new StockException("Pembayaran {$r['sale_no']} tidak dapat disettle (belum terkonfirmasi / sudah settle / bukan QRIS).");
                }
                if ((int) $r['n_returns'] > 0) {
                    throw new StockException("Penjualan {$r['sale_no']} sudah punya retur; settle manual lewat Keuangan.");
                }
                $gross += (float) $r['amount'];
            }
            $gross = round($gross, 2);
            if ($netAmount < 0 || $netAmount > $gross + 0.005) {
                throw new StockException('Dana bersih harus antara 0 dan total QRIS (Rp ' . number_format($gross, 0, ',', '.') . ').');
            }
            $mdr = round($gross - $netAmount, 2);
            $date = date('Y-m-d', $ts);
            $now  = date('Y-m-d H:i:s');

            $db->table('pos_qris_settlements')->insert([
                'settlement_no' => 'QS-TMP-' . bin2hex(random_bytes(6)), 'settlement_date' => $date, 'clearing_account_id' => $clearing,
                'bank_account_id' => $bankAccountId, 'gross_amount' => $gross, 'net_amount' => $netAmount, 'mdr_amount' => $mdr,
                'payment_count' => count($rows), 'reference_no' => $referenceNo ? mb_substr(trim($referenceNo), 0, 60) : null,
                'notes' => $notes ? mb_substr(trim($notes), 0, 255) : null, 'proof_path' => $proofPath, 'created_by' => $userId, 'created_at' => $now,
            ]);
            $sid = (int) $db->insertID();
            $no  = sprintf('QS-%s-%04d', date('Ymd', $ts), $sid);

            $outId = $inId = $mdrId = null;
            if ($netAmount > 0) {
                [$outId, $inId] = $this->transfer($clearing, $bankAccountId, $netAmount, $date, "Settlement QRIS {$no} (bersih)", $userId);
            }
            if ($mdr > 0) {
                $catId = $db->table('financial_categories')->where('name', 'Biaya MDR QRIS')->where('type', 'EXPENSE')->get()->getRow('id');
                $ft    = new FinancialTransactionModel();
                $mdrId = $ft->recordFromReference('QRIS_MDR', $sid, [
                    'transaction_date' => $date, 'type' => 'EXPENSE', 'category_id' => $catId ?: null, 'amount' => $mdr,
                    'description' => "Biaya MDR QRIS {$no}", 'cash_account_id' => $clearing, 'created_by' => $userId,
                ]);
                if (! $mdrId) {
                    throw new \RuntimeException('Gagal mencatat MDR: ' . implode('; ', $ft->errors()));
                }
            }
            $db->table('pos_qris_settlements')->where('id', $sid)->update(['settlement_no' => $no, 'transfer_out_id' => $outId, 'transfer_in_id' => $inId, 'mdr_txn_id' => $mdrId]);
            $db->table('pos_payments')->whereIn('id', $paymentIds)->update(['settlement_id' => $sid, 'updated_at' => $now]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Settlement QRIS gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Settlement gagal disimpan.'];
        }

        (new AuditLogService())->log('Settlement QRIS', 'pos', 'POS_QRIS_SETTLEMENT', $sid, "{$no} bruto Rp " . number_format($gross, 0, ',', '.') . ", bersih Rp " . number_format($netAmount, 0, ',', '.') . ", MDR Rp " . number_format($mdr, 0, ',', '.'));

        return ['success' => true, 'message' => "Settlement {$no}: bersih Rp " . number_format($netAmount, 0, ',', '.') . ' masuk bank, MDR Rp ' . number_format($mdr, 0, ',', '.') . ' dicatat sebagai biaya.', 'id' => $sid];
    }

    /** @return array{0:int,1:int} id transaksi keluar & masuk */
    private function transfer(int $from, int $to, float $amount, string $date, string $desc, ?int $userId): array
    {
        $db    = db_connect();
        $ft    = new FinancialTransactionModel();
        $catId = $db->table('financial_categories')->where('name', 'Pemindahan Dana')->get()->getRow('id');
        $ft->insert(['transaction_date' => $date, 'type' => 'EXPENSE', 'category_id' => $catId ?: null, 'amount' => $amount, 'description' => $desc,
            'reference_type' => 'TRANSFER_OUT', 'reference_id' => null, 'cash_account_id' => $from, 'created_by' => $userId]);
        $outId = (int) $ft->getInsertID();
        $ft->insert(['transaction_date' => $date, 'type' => 'INCOME', 'category_id' => $catId ?: null, 'amount' => $amount, 'description' => $desc,
            'reference_type' => 'TRANSFER_IN', 'reference_id' => $outId, 'cash_account_id' => $to, 'created_by' => $userId]);
        $inId = (int) $ft->getInsertID();
        if (! $outId || ! $inId) {
            throw new \RuntimeException('Gagal mencatat pemindahan dana: ' . implode('; ', $ft->errors()));
        }

        return [$outId, $inId];
    }
}
