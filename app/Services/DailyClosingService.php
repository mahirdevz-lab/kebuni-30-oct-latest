<?php

namespace App\Services;

use App\Models\SettingModel;

/**
 * Buka & tutup kas harian (EOD) tanpa pergantian shift (PRD A4).
 *
 * - Hanya boleh ada satu hari kas berstatus OPEN per lokasi; checkout memerlukan hari OPEN.
 * - Kas seharusnya = kas awal laci + mutasi bersih akun Kas Kasir pada tanggal bisnis tsb
 *   (tunai penjualan, refund, setoran/pindah dana, mutasi lain). Setoran ke bank adalah transfer, bukan omzet.
 * - Setelah CLOSED angka EOD di-snapshot dan checkout terkunci; stock opname baru boleh dibuka.
 */
class DailyClosingService
{
    private SettingModel $settings;

    public function __construct()
    {
        $this->settings = new SettingModel();
    }

    public function locationId(): int
    {
        return (int) $this->settings->value('pos_location_id', '1');
    }

    /** Hari kas yang sedang OPEN (atau null). */
    public function openDay(): ?array
    {
        return db_connect()->table('pos_daily_closings')->where('location_id', $this->locationId())->where('status', 'OPEN')
            ->orderBy('id', 'DESC')->get(1)->getRowArray() ?: null;
    }

    public function lastDay(): ?array
    {
        return db_connect()->table('pos_daily_closings')->where('location_id', $this->locationId())
            ->orderBy('business_date', 'DESC')->get(1)->getRowArray() ?: null;
    }

    /**
     * Kas awal yang seharusnya ada di laci saat buka toko = kas fisik EOD terakhir + mutasi bersih Kas Kasir
     * yang tercatat setelah EOD tersebut (mis. setoran ke bank). Null bila belum pernah ada EOD.
     */
    public function expectedOpeningCash(): ?float
    {
        $last = db_connect()->table('pos_daily_closings')->where('location_id', $this->locationId())->where('status', 'CLOSED')
            ->orderBy('closed_at', 'DESC')->get(1)->getRowArray();
        if (! $last) {
            return null;
        }
        $cashAcc = (int) $this->settings->value('pos_cash_account', '0');
        $net     = (float) db_connect()->query(
            "SELECT COALESCE(SUM(CASE WHEN type = 'INCOME' THEN amount ELSE -amount END), 0) s
             FROM financial_transactions WHERE cash_account_id = ? AND id > ?",
            [$cashAcc, (int) $last['ft_max_id']]
        )->getRow('s');

        return round((float) $last['physical_cash'] + $net, 2);
    }

    public function open(float $openingCash, ?int $userId, ?string $notes = null): array
    {
        if ($openingCash < 0) {
            return ['success' => false, 'message' => 'Kas awal tidak boleh negatif.'];
        }
        $db  = db_connect();
        $loc = $this->locationId();
        $today = date('Y-m-d');

        if ($this->openDay()) {
            return ['success' => false, 'message' => 'Masih ada hari kas yang belum ditutup (EOD). Tutup dulu sebelum membuka hari baru.'];
        }
        if ($db->table('stock_opname_sessions')->where('location_id', $loc)->where('status', 'COUNTING')->countAllResults()) {
            return ['success' => false, 'message' => 'Stock opname masih dalam proses hitung. Submit atau batalkan opname sebelum toko dibuka.'];
        }
        if ($db->table('pos_daily_closings')->where('location_id', $loc)->where('business_date', $today)->countAllResults()) {
            return ['success' => false, 'message' => 'Hari ini sudah pernah dibuka dan ditutup (EOD). Pembukaan ulang belum didukung.'];
        }

        $expected = $this->expectedOpeningCash();
        if ($expected !== null && abs(round($openingCash, 2) - $expected) > 0.5 && trim((string) $notes) === '') {
            return ['success' => false, 'message' => 'Kas awal berbeda dari kas seharusnya (Rp ' . number_format($expected, 0, ',', '.') . '). Jelaskan selisihnya di kolom catatan.'];
        }

        $now = date('Y-m-d H:i:s');
        $db->table('pos_daily_closings')->insert([
            'location_id' => $loc, 'business_date' => $today, 'status' => 'OPEN', 'opening_cash' => round($openingCash, 2),
            'expected_opening_cash' => $expected,
            'opened_by' => $userId, 'opened_at' => $now, 'notes' => $notes, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        (new AuditLogService())->log('Buka Kas Harian', 'pos', 'POS_DAY', $id, 'Kas awal Rp ' . number_format($openingCash, 0, ',', '.') . ($expected !== null ? ', seharusnya Rp ' . number_format($expected, 0, ',', '.') . ', selisih Rp ' . number_format(round($openingCash, 2) - $expected, 0, ',', '.') : ''));

        return ['success' => true, 'message' => 'Toko dibuka. Kas awal tercatat.', 'id' => $id];
    }

    /** Ringkasan berjalan (atau snapshot jika sudah CLOSED) untuk satu hari kas. */
    public function summary(array $day): array
    {
        if ($day['status'] === 'CLOSED') {
            return $day;
        }

        $db      = db_connect();
        $cashAcc = (int) $this->settings->value('pos_cash_account', '0');
        $date    = $day['business_date'];

        $sum = static function (string $type, array $refs, bool $in) use ($db, $cashAcc, $date): float {
            $b = $db->table('financial_transactions')->selectSum('amount', 's')
                ->where('cash_account_id', $cashAcc)->where('transaction_date', $date)->where('type', $type);
            $in ? $b->whereIn('reference_type', $refs) : $b->whereNotIn('reference_type', $refs);

            return (float) ($b->get()->getRow('s') ?? 0);
        };

        $cashSales  = $sum('INCOME', ['POS_PAYMENT'], true);
        $refunds    = $sum('EXPENSE', ['POS_REFUND', 'POS_RETURN_REFUND'], true);
        $transfers  = $sum('INCOME', ['TRANSFER_IN'], true) - $sum('EXPENSE', ['TRANSFER_OUT'], true);
        $other      = $sum('INCOME', ['POS_PAYMENT', 'TRANSFER_IN'], false) - $sum('EXPENSE', ['POS_REFUND', 'POS_RETURN_REFUND', 'TRANSFER_OUT'], false);
        $expected   = round((float) $day['opening_cash'] + $cashSales - $refunds + $transfers + $other, 2);

        $qris = $db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN p.status = 'CONFIRMED' THEN p.amount END), 0) AS confirmed,
                COALESCE(SUM(CASE WHEN p.status = 'PENDING_VERIFICATION' THEN p.amount END), 0) AS pending,
                COUNT(CASE WHEN p.status = 'PENDING_VERIFICATION' THEN 1 END) AS pending_count
             FROM pos_payments p JOIN pos_sales s ON s.id = p.sale_id
             WHERE p.method IN ('QRIS','TRANSFER') AND s.daily_closing_id = ? AND s.status = 'POSTED'",
            [$day['id']]
        )->getRowArray();

        $sales = $db->query("SELECT COUNT(*) c, COALESCE(SUM(grand_total),0) t FROM pos_sales WHERE daily_closing_id = ? AND status = 'POSTED'", [$day['id']])->getRowArray();

        // array_merge (bukan +): kolom snapshot di $day bernilai null dan harus ditimpa angka berjalan.
        return array_merge($day, [
            'cash_sales' => $cashSales, 'cash_refunds' => $refunds, 'cash_transfers' => $transfers, 'cash_other' => $other,
            'expected_cash' => $expected, 'qris_confirmed' => (float) $qris['confirmed'], 'qris_pending' => (float) $qris['pending'],
            'qris_pending_count' => (int) $qris['pending_count'], 'sales_count' => (int) $sales['c'], 'sales_total' => (float) $sales['t'],
        ]);
    }

    public function close(int $dayId, float $physicalCash, ?int $userId, ?string $notes = null): array
    {
        if ($physicalCash < 0) {
            return ['success' => false, 'message' => 'Kas fisik tidak boleh negatif.'];
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $day = $db->query('SELECT * FROM pos_daily_closings WHERE id = ? FOR UPDATE', [$dayId])->getRowArray();
            if (! $day || $day['status'] !== 'OPEN') {
                throw new StockException('Hari kas tidak ditemukan atau sudah ditutup.');
            }
            $s    = $this->summary($day);
            $diff = round($physicalCash - $s['expected_cash'], 2);
            $now  = date('Y-m-d H:i:s');

            $db->table('pos_daily_closings')->where('id', $dayId)->update([
                'status' => 'CLOSED', 'cash_sales' => $s['cash_sales'], 'cash_refunds' => $s['cash_refunds'],
                'cash_transfers' => $s['cash_transfers'], 'cash_other' => $s['cash_other'], 'expected_cash' => $s['expected_cash'],
                'physical_cash' => round($physicalCash, 2), 'difference' => $diff, 'qris_confirmed' => $s['qris_confirmed'],
                'qris_pending' => $s['qris_pending'], 'qris_pending_count' => $s['qris_pending_count'],
                'sales_count' => $s['sales_count'], 'sales_total' => $s['sales_total'],
                'closed_by' => $userId, 'closed_at' => $now,
                'ft_max_id' => (int) $db->query('SELECT COALESCE(MAX(id),0) m FROM financial_transactions')->getRow('m'), 'notes' => $notes ?: $day['notes'], 'updated_at' => $now,
            ]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'EOD gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Tutup kas gagal disimpan.'];
        }

        (new AuditLogService())->log('Tutup Kas Harian (EOD)', 'pos', 'POS_DAY', $dayId,
            'Seharusnya Rp ' . number_format($s['expected_cash'], 0, ',', '.') . ', fisik Rp ' . number_format($physicalCash, 0, ',', '.') . ', selisih Rp ' . number_format($diff, 0, ',', '.'));

        return ['success' => true, 'message' => 'EOD selesai. Checkout dikunci; stock opname dapat dimulai.', 'difference' => $diff];
    }
}
