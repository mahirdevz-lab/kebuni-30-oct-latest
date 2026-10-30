<?php

namespace App\Services;

use App\Models\FinancialTransactionModel;
use App\Models\SettingModel;

/**
 * Checkout POS online (PRD FR-02/04/05, A2, A3).
 *
 * Satu transaksi database mencakup: header + item penjualan, mutasi stok (ledger, moving average),
 * pembayaran, dan pencatatan kas. Klik/retry ganda dengan client_uuid yang sama mengembalikan
 * penjualan yang sudah ada (tanpa mutasi tambahan).
 *
 * Tunai   -> CONFIRMED, INCOME ke akun Kas Kasir sebesar nilai yang dibebankan (bukan uang diterima).
 * QRIS    -> PENDING_VERIFICATION; INCOME ke QRIS Clearing baru dicatat saat diverifikasi supervisor/keuangan.
 */
class PosSaleService
{
    private SettingModel $settings;

    public function __construct(
        private ?StockLedgerService $ledger = null,
        private ?PosPricingService $pricing = null,
        private ?AuditLogService $audit = null,
    ) {
        $this->ledger  ??= new StockLedgerService();
        $this->pricing ??= new PosPricingService();
        $this->audit   ??= new AuditLogService();
        $this->settings = new SettingModel();
    }

    /**
     * @param array{
     *   client_uuid:string, customer_id?:?int, notes?:?string,
     *   items:list<array{product_id:int, qty:float, free_qty?:float}>,
     *   payments:list<array{method:string, tendered?:float, amount?:float, reference_no?:?string}>,
     *   override_reason?:?string
     * } $in
     * @param array{user_id:?int, can_override:bool} $actor
     */
    public function checkout(array $in, array $actor): array
    {
        $uuid = trim((string) ($in['client_uuid'] ?? ''));
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
            return ['success' => false, 'message' => 'ID transaksi tidak valid.'];
        }

        $db = db_connect();

        $dup = $db->table('pos_sales')->where('client_uuid', $uuid)->get()->getRowArray();
        if ($dup) {
            return $this->resultFor($dup, true);
        }

        // --- Validasi & penggabungan item (sebelum transaksi)
        $lines = [];
        foreach ($in['items'] ?? [] as $it) {
            $pid  = (int) ($it['product_id'] ?? 0);
            $qty  = round((float) ($it['qty'] ?? 0), 3);
            $free = round((float) ($it['free_qty'] ?? 0), 3);
            if ($pid <= 0 || $qty <= 0 || $free < 0) {
                return ['success' => false, 'message' => 'Item keranjang tidak valid.'];
            }
            $lines[] = ['product_id' => $pid, 'qty' => $qty, 'free_qty' => $free];
        }
        if (! $lines) {
            return ['success' => false, 'message' => 'Keranjang kosong.'];
        }

        $customerId = ! empty($in['customer_id']) ? (int) $in['customer_id'] : null;
        $locationId = (int) $this->settings->value('pos_location_id', '1');
        $now        = date('Y-m-d H:i:s');

        // --- Diskon manual & otorisasi supervisor (PIN diverifikasi SEBELUM transaksi agar hitungan gagal tidak ikut di-rollback)
        $discIn = $in['discount'] ?? null;
        $discType = null;
        $discValue = 0.0;
        $discReason = '';
        if (is_array($discIn) && (float) ($discIn['value'] ?? 0) > 0) {
            $discType   = strtoupper((string) ($discIn['type'] ?? '')) === 'AMT' ? 'AMT' : 'PCT';
            $discValue  = round((float) $discIn['value'], 2);
            $discReason = trim((string) ($discIn['reason'] ?? ''));
            if ($discReason === '') {
                return ['success' => false, 'message' => 'Alasan diskon wajib diisi.'];
            }
            if ($discType === 'PCT' && $discValue >= 100) {
                return ['success' => false, 'message' => 'Diskon persen harus di bawah 100%. Untuk gratis gunakan kolom "Gratis".'];
            }
        }
        $overrideReason = trim((string) ($in['override_reason'] ?? ''));
        $canDiscount    = ! empty($actor['can_discount']);
        $canOverride    = ! empty($actor['can_override']);
        $approverId     = (int) ($actor['user_id'] ?? 0) ?: null;
        $sup            = is_array($in['supervisor'] ?? null) ? $in['supervisor'] : null;
        if ($sup && ($discType || $overrideReason !== '') && ! ($canDiscount && $canOverride)) {
            $sa    = new SupervisorAuthService();
            $need  = $discType ? 'pos.discount' : 'pos.override';
            $check = $sa->verify((int) ($sup['id'] ?? 0), (string) ($sup['pin'] ?? ''), $need, $actor['user_id'] ?? null);
            if (! $check['success']) {
                return ['success' => false, 'message' => $check['message']];
            }
            // Satu supervisor yang sama dipakai untuk diskon & override; cek kemampuan lain tanpa menghitung salah PIN.
            if ($discType && ! $canDiscount) {
                $canDiscount = true;
            }
            if ($overrideReason !== '' && ! $canOverride) {
                if (! $sa->userCan($check['user']['id'], 'pos.override')) {
                    return ['success' => false, 'message' => 'Supervisor ini tidak berwenang melakukan override stok.'];
                }
                $canOverride = true;
            }
            $approverId = $check['user']['id'];
            (new AuditLogService())->log('Otorisasi PIN Supervisor', 'pos', 'USER', $approverId, ($discType ? 'diskon ' : '') . ($overrideReason !== '' ? 'override stok ' : '') . 'di sesi kasir user #' . ($actor['user_id'] ?? '?'));
        }
        if ($discType && ! $canDiscount) {
            return ['success' => false, 'message' => 'Diskon manual membutuhkan otorisasi supervisor (login supervisor atau PIN).'];
        }

        $db->transBegin();
        try {
            // Hari kas harus OPEN (EOD belum dilakukan) dan tidak ada opname yang sedang dihitung.
            // LOCK IN SHARE MODE: checkout paralel tetap jalan, tetapi EOD (FOR UPDATE) menunggu checkout yang sedang berlangsung.
            $day = $db->query("SELECT * FROM pos_daily_closings WHERE location_id = ? AND status = 'OPEN' ORDER BY id DESC LIMIT 1 LOCK IN SHARE MODE", [$locationId])->getRowArray();
            if (! $day) {
                throw new StockException('Toko belum dibuka atau sudah EOD. Buka kas harian terlebih dahulu.');
            }
            if ((new StockOpnameService($this->ledger))->isCounting($locationId)) {
                throw new StockException('Stock opname sedang berlangsung. Checkout dikunci sampai hasil hitung disubmit.');
            }
            $bizDate = $day['business_date'];

            // Produk (hanya yang aktif & dijual di POS)
            $ids      = array_values(array_unique(array_column($lines, 'product_id')));
            $products = [];
            foreach ($db->table('products')->whereIn('id', $ids)->where('deleted_at', null)->get()->getResultArray() as $p) {
                $products[(int) $p['id']] = $p;
            }

            $subtotal  = 0.0;
            $items     = [];
            $promoSvc  = new PromoService();
            $promoMap  = $promoSvc->activeFor(array_column($lines, 'product_id'), $now);
            foreach ($lines as $l) {
                $p = $products[$l['product_id']] ?? null;
                if (! $p || $p['status'] !== 'aktif' || ! (int) $p['is_pos']) {
                    throw new StockException('Produk tidak tersedia untuk dijual di POS.', $l['product_id']);
                }
                $price = $this->pricing->priceFor($l['product_id'], $now);
                if (! $price) {
                    throw new StockException('Produk "' . $p['product_name'] . '" belum memiliki harga retail.', $l['product_id']);
                }
                // Promo terjadwal (otomatis): harga bertingkat menggantikan harga jual bila lebih murah; BOGO menambah barang gratis.
                $ev = $promoSvc->evaluate($promoMap[$l['product_id']] ?? null, $l['qty'], (float) $price['unit_price']);
                if ($ev['price_promo_id']) {
                    $price['unit_price'] = $ev['unit_price'];
                    $price['markdown_id'] = null;
                    $price['promo_id']    = $ev['price_promo_id'];
                }
                $lineTotal = round($l['qty'] * $price['unit_price'], 2);
                $subtotal += $lineTotal;
                $items[]   = $l + ['product' => $p, 'price' => $price, 'line_total' => $lineTotal, 'promo_free' => $ev['free_qty'], 'promo_free_id' => $ev['free_promo_id']];
            }
            $subtotal   = round($subtotal, 2);
            $discount   = 0.0;
            if ($discType) {
                $discount = $discType === 'PCT' ? round($subtotal * $discValue / 100, 2) : $discValue;
                if ($discount <= 0 || $discount >= $subtotal) {
                    throw new StockException('Diskon harus lebih dari 0 dan lebih kecil dari subtotal (Rp ' . number_format($subtotal, 0, ',', '.') . ').');
                }
                // Alokasi proporsional ke baris berbayar; sisa pembulatan ke baris terakhir.
                $left = $discount;
                $last = array_key_last($items);
                foreach ($items as $k => &$it) {
                    $d = $k === $last ? $left : round($discount * $it['line_total'] / $subtotal, 2);
                    $d = min($d, $it['line_total']);
                    $it['discount']   = $d;
                    $it['line_total'] = round($it['line_total'] - $d, 2);
                    $left = round($left - $d, 2);
                }
                unset($it);
            }
            $grandTotal = round($subtotal - $discount, 2);

            // --- Pembayaran divalidasi dulu supaya gagal sebelum stok diubah
            $payRows = [];
            $applied = 0.0;
            foreach ($in['payments'] ?? [] as $pay) {
                $method = strtoupper((string) ($pay['method'] ?? ''));
                if (! in_array($method, self::METHODS, true)) {
                    throw new StockException('Metode pembayaran tidak dikenal.');
                }
                $remaining = round($grandTotal - $applied, 2);
                if ($remaining <= 0) {
                    break;
                }
                if ($method === 'CASH') {
                    $tendered = round((float) ($pay['tendered'] ?? 0), 2);
                    if ($tendered <= 0) {
                        throw new StockException('Nominal tunai yang diterima wajib diisi.');
                    }
                    $amount = min($tendered, $remaining);
                    $payRows[] = ['method' => 'CASH', 'amount' => $amount, 'tendered' => $tendered, 'change' => round($tendered - $amount, 2), 'ref' => null];
                } else {
                    $amount = min(round((float) ($pay['amount'] ?? $remaining), 2), $remaining);
                    if ($amount <= 0) {
                        throw new StockException('Nominal ' . self::label($method) . ' tidak valid.');
                    }
                    $payRows[] = ['method' => $method, 'amount' => $amount, 'tendered' => $amount, 'change' => 0.0, 'ref' => $pay['reference_no'] ?? null];
                }
                $applied = round($applied + $amount, 2);
            }
            if (! $payRows) {
                throw new StockException('Pembayaran belum diisi.');
            }
            $due = round($grandTotal - $applied, 2);
            if ($due > 0 && ! $customerId) {
                throw new StockException('Pembayaran kurang dari total. Pilih pelanggan untuk mencatat sisa tagihan.');
            }

            // --- Header (nomor final setelah id diketahui)
            $db->table('pos_sales')->insert([
                'sale_no' => 'TMP-' . bin2hex(random_bytes(8)), 'client_uuid' => $uuid, 'sale_date' => $now,
                'location_id' => $locationId, 'daily_closing_id' => (int) $day['id'], 'customer_id' => $customerId, 'cashier_id' => $actor['user_id'],
                'subtotal' => $subtotal, 'discount_total' => $discount, 'discount_type' => $discType, 'discount_value' => $discType ? $discValue : null,
                'discount_reason' => $discType ? mb_substr($discReason, 0, 255) : null, 'discount_approved_by' => $discType ? $approverId : null, 'grand_total' => $grandTotal,
                'paid_total' => 0, 'balance_due' => $grandTotal, 'change_amount' => 0, 'cogs_total' => 0,
                'payment_status' => 'UNPAID', 'sync_status' => 'SYNCED', 'status' => 'POSTED',
                'notes' => $in['notes'] ?? null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $saleId = (int) $db->insertID();
            $saleNo = sprintf('POS-%s-%06d', date('Ymd'), $saleId);
            $db->table('pos_sales')->where('id', $saleId)->update(['sale_no' => $saleNo]);

            // --- Stok: kunci per produk berurutan, tolak stok negatif kecuali override sah
            usort($items, static fn ($a, $b) => $a['product_id'] <=> $b['product_id']);
            $cogsTotal      = 0.0;
            $lineNo         = 0;
            $itemRows       = [];

            foreach ($items as $it) {
                $pid       = $it['product_id'];
                $totalOut  = round($it['qty'] + $it['free_qty'] + $it['promo_free'], 3);
                $bal       = $this->ledger->balance($pid, $locationId);
                $available = (float) $bal['qty'];
                $needsOverride = $totalOut > $available;

                if ($needsOverride) {
                    if (! $canOverride || $overrideReason === '') {
                        throw new StockException(
                            'Stok "' . $it['product']['product_name'] . '" tidak mencukupi (tersedia ' . rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.') . ' ' . $it['product']['unit'] . '). Hubungi supervisor.',
                            $pid, $available, $totalOut
                        );
                    }
                    $db->table('pos_stock_overrides')->insert([
                        'sale_id' => $saleId, 'product_id' => $pid, 'requested_qty' => $totalOut, 'available_qty' => $available,
                        'reason' => $overrideReason, 'supervisor_id' => $approverId, 'created_at' => $now,
                    ]);
                }

                $lineNo++;
                $paidLineNo = $lineNo;
                $sale = $this->ledger->post([
                    'product_id' => $pid, 'location_id' => $locationId, 'movement_type' => 'SALE', 'qty' => $it['qty'],
                    'reference_type' => 'POS_SALE', 'reference_id' => $saleId, 'reference_line' => $paidLineNo,
                    'reason' => 'Penjualan ' . $saleNo, 'user_id' => $actor['user_id'], 'effective_at' => $now,
                    'allow_negative' => $needsOverride,
                ]);
                $cogsTotal += $sale['total_cost'];
                $itemRows[] = [
                    'sale_id' => $saleId, 'line_no' => $paidLineNo, 'product_id' => $pid, 'line_type' => 'NORMAL', 'promo_for_line' => null,
                    'qty' => $it['qty'], 'unit' => $it['product']['unit'], 'list_price' => $it['price']['list_price'],
                    'unit_price' => $it['price']['unit_price'], 'markdown_id' => $it['price']['markdown_id'], 'promo_id' => $it['price']['promo_id'] ?? null,
                    'discount' => $it['discount'] ?? 0, 'line_total' => $it['line_total'], 'unit_cost' => $sale['unit_cost'], 'cogs' => $sale['total_cost'],
                ];

                if ($it['free_qty'] > 0) {
                    // Buy 1 Get 1 / gratis promo: harga jual Rp0, biaya persediaan tetap diakui.
                    $lineNo++;
                    $free = $this->ledger->post([
                        'product_id' => $pid, 'location_id' => $locationId, 'movement_type' => 'PROMO_FREE', 'qty' => $it['free_qty'],
                        'reference_type' => 'POS_SALE', 'reference_id' => $saleId, 'reference_line' => $lineNo,
                        'reason' => 'Gratis promo ' . $saleNo, 'user_id' => $actor['user_id'], 'effective_at' => $now,
                        'allow_negative' => $needsOverride,
                    ]);
                    $cogsTotal += $free['total_cost'];
                    $itemRows[] = [
                        'sale_id' => $saleId, 'line_no' => $lineNo, 'product_id' => $pid, 'line_type' => 'PROMO_FREE', 'promo_for_line' => $paidLineNo,
                        'qty' => $it['free_qty'], 'unit' => $it['product']['unit'], 'list_price' => $it['price']['list_price'],
                        'unit_price' => 0, 'markdown_id' => null, 'promo_id' => null, 'discount' => 0, 'line_total' => 0,
                        'unit_cost' => $free['unit_cost'], 'cogs' => $free['total_cost'],
                    ];
                }

                if ($it['promo_free'] > 0) {
                    // BOGO otomatis dari promo terjadwal: tercatat dengan promo_id (terpisah dari gratis manual kasir).
                    $lineNo++;
                    $pf = $this->ledger->post([
                        'product_id' => $pid, 'location_id' => $locationId, 'movement_type' => 'PROMO_FREE', 'qty' => $it['promo_free'],
                        'reference_type' => 'POS_SALE', 'reference_id' => $saleId, 'reference_line' => $lineNo,
                        'reason' => 'Promo BOGO ' . $saleNo, 'user_id' => $actor['user_id'], 'effective_at' => $now,
                        'allow_negative' => $needsOverride,
                    ]);
                    $cogsTotal += $pf['total_cost'];
                    $itemRows[] = [
                        'sale_id' => $saleId, 'line_no' => $lineNo, 'product_id' => $pid, 'line_type' => 'PROMO_FREE', 'promo_for_line' => $paidLineNo,
                        'qty' => $it['promo_free'], 'unit' => $it['product']['unit'], 'list_price' => $it['price']['list_price'],
                        'unit_price' => 0, 'markdown_id' => null, 'promo_id' => $it['promo_free_id'], 'discount' => 0, 'line_total' => 0,
                        'unit_cost' => $pf['unit_cost'], 'cogs' => $pf['total_cost'],
                    ];
                }
            }
            $db->table('pos_sale_items')->insertBatch($itemRows);

            // --- Pembayaran & kas
            $cashAcc = (int) $this->settings->value('pos_cash_account', '0');
            $paidConfirmed = 0.0;
            $changeTotal   = 0.0;
            $hasPending    = false;
            $ftModel       = new FinancialTransactionModel();

            foreach ($payRows as $p) {
                $isCash = $p['method'] === 'CASH';
                $db->table('pos_payments')->insert([
                    'sale_id' => $saleId, 'method' => $p['method'], 'amount' => $p['amount'],
                    'tendered' => $p['tendered'], 'change_amount' => $p['change'],
                    'status' => $isCash ? 'CONFIRMED' : 'PENDING_VERIFICATION',
                    'cash_account_id' => $this->accountFor($p['method']), 'reference_no' => $p['ref'],
                    'created_by' => $actor['user_id'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                $payId = (int) $db->insertID();

                if ($isCash) {
                    $ftId = $this->recordIncome($ftModel, $payId, $p['amount'], $cashAcc, "Penjualan POS {$saleNo} (tunai)", $customerId, $actor['user_id'], $bizDate);
                    $db->table('pos_payments')->where('id', $payId)->update(['financial_transaction_id' => $ftId]);
                    $paidConfirmed += $p['amount'];
                    $changeTotal   += $p['change'];
                } else {
                    $hasPending = true;
                }
            }

            $status = $paidConfirmed >= $grandTotal ? 'PAID' : ($hasPending ? 'PENDING_VERIFICATION' : ($paidConfirmed > 0 ? 'PARTIAL' : 'UNPAID'));
            $db->table('pos_sales')->where('id', $saleId)->update([
                'paid_total' => $paidConfirmed, 'balance_due' => round($grandTotal - $paidConfirmed, 2),
                'change_amount' => $changeTotal, 'cogs_total' => round($cogsTotal, 2), 'payment_status' => $status,
            ]);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            // Klik ganda paralel: transaksi kedua kalah di UNIQUE client_uuid -> kembalikan penjualan pemenang.
            $dup = $db->table('pos_sales')->where('client_uuid', $uuid)->get()->getRowArray();
            if ($dup) {
                return $this->resultFor($dup, true);
            }
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Checkout POS gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Checkout gagal disimpan. Tidak ada stok/kas yang berubah.'];
        }

        $this->audit->log('Checkout POS', 'pos', 'POS_SALE', $saleId, "{$saleNo} Rp " . number_format($grandTotal, 0, ',', '.'));

        return $this->resultFor($db->table('pos_sales')->where('id', $saleId)->get()->getRowArray(), false);
    }

    /**
     * Pelunasan sisa tagihan pelanggan (pembayaran susulan atas penjualan yang sudah ada).
     * Tunai: wajib toko buka (masuk Kas Kasir & EOD hari itu). QRIS: menunggu verifikasi seperti checkout.
     * Idempoten lewat client_uuid.
     *
     * @return array{success:bool, message:string, duplicate?:bool, balance_due?:float, payment_status?:string, change_amount?:float}
     */
    public function addPayment(int $saleId, string $clientUuid, string $method, float $amount, ?string $referenceNo, ?int $userId): array
    {
        $method = strtoupper($method);
        $amount = round($amount, 2);
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $clientUuid)) {
            return ['success' => false, 'message' => 'ID pembayaran tidak valid.'];
        }
        if (! in_array($method, self::METHODS, true) || $amount <= 0) {
            return ['success' => false, 'message' => 'Isi metode dan nominal pembayaran.'];
        }
        $db  = db_connect();
        $dup = $db->table('pos_payments')->where('client_uuid', $clientUuid)->get()->getRowArray();
        if ($dup) {
            $s = $db->table('pos_sales')->where('id', $dup['sale_id'])->get()->getRowArray();

            return ['success' => true, 'duplicate' => true, 'message' => 'Pembayaran ini sudah tersimpan sebelumnya (tidak dibuat ganda).', 'balance_due' => (float) $s['balance_due'], 'payment_status' => $s['payment_status']];
        }

        $db->transBegin();
        try {
            $sale = $db->query('SELECT * FROM pos_sales WHERE id = ? FOR UPDATE', [$saleId])->getRowArray();
            if (! $sale || $sale['status'] !== 'POSTED') {
                throw new StockException('Penjualan tidak ditemukan atau sudah dibatalkan.');
            }
            if ((float) $sale['balance_due'] <= 0) {
                throw new StockException('Penjualan ini sudah lunas.');
            }
            $pending = (float) $db->query("SELECT COALESCE(SUM(amount),0) s FROM pos_payments WHERE sale_id = ? AND status = 'PENDING_VERIFICATION'", [$saleId])->getRow('s');
            $open    = round((float) $sale['balance_due'] - $pending, 2);
            if ($open <= 0) {
                throw new StockException('Sisa tagihan sudah tertutup oleh pembayaran digital (QRIS/transfer) yang menunggu verifikasi.');
            }
            $applied = min($amount, $open);
            $now     = date('Y-m-d H:i:s');
            $cashAcc = (int) $this->settings->value('pos_cash_account', '0');
            $bizDate = date('Y-m-d');
            $tendered = $amount;
            $change   = $method === 'CASH' ? round($amount - $applied, 2) : 0.0;

            if ($method === 'CASH') {
                $day = $db->query("SELECT * FROM pos_daily_closings WHERE location_id = ? AND status = 'OPEN' ORDER BY id DESC LIMIT 1 LOCK IN SHARE MODE", [(int) $sale['location_id']])->getRowArray();
                if (! $day) {
                    throw new StockException('Pembayaran tunai butuh toko dalam keadaan buka. Buka toko dulu atau pilih QRIS/Transfer.');
                }
                $bizDate = $day['business_date'];
            } else {
                $tendered = $applied;
            }
            if ((new StockOpnameService($this->ledger))->isCounting((int) $sale['location_id'])) {
                throw new StockException('Stock opname sedang berlangsung. Pembayaran dicatat setelah hasil hitung disubmit.');
            }

            $db->table('pos_payments')->insert([
                'sale_id' => $saleId, 'client_uuid' => $clientUuid, 'is_balance' => 1, 'method' => $method, 'amount' => $applied,
                'tendered' => $tendered, 'change_amount' => $change, 'status' => $method === 'CASH' ? 'CONFIRMED' : 'PENDING_VERIFICATION',
                'cash_account_id' => $this->accountFor($method), 'reference_no' => $referenceNo ? mb_substr(trim($referenceNo), 0, 60) : null,
                'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $payId = (int) $db->insertID();

            $paid = (float) $sale['paid_total'];
            if ($method === 'CASH') {
                $ftId = $this->recordIncome(new FinancialTransactionModel(), $payId, $applied, $cashAcc, "Pelunasan {$sale['sale_no']} (tunai)",
                    $sale['customer_id'] ? (int) $sale['customer_id'] : null, $userId, $bizDate);
                $db->table('pos_payments')->where('id', $payId)->update(['financial_transaction_id' => $ftId]);
                $paid = round($paid + $applied, 2);
            }
            $total  = (float) $sale['grand_total'];
            $due    = round($total - $paid, 2);
            $hasPend = $method !== 'CASH' || $pending > 0;
            $status = $paid >= $total ? 'PAID' : ($hasPend ? 'PENDING_VERIFICATION' : ($paid > 0 ? 'PARTIAL' : 'UNPAID'));
            $db->table('pos_sales')->where('id', $saleId)->update(['paid_total' => $paid, 'balance_due' => max(0, $due), 'payment_status' => $status, 'updated_at' => $now]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $dup = $db->table('pos_payments')->where('client_uuid', $clientUuid)->get()->getRowArray();
            if ($dup) {
                return ['success' => true, 'duplicate' => true, 'message' => 'Pembayaran ini sudah tersimpan sebelumnya (tidak dibuat ganda).'];
            }
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Pelunasan gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Pembayaran gagal disimpan.'];
        }

        $this->audit->log('Pelunasan Tagihan POS', 'pos', 'POS_PAYMENT', $payId, "{$sale['sale_no']} {$method} Rp " . number_format($applied, 0, ',', '.'));

        return ['success' => true, 'duplicate' => false, 'balance_due' => max(0, $due), 'payment_status' => $status, 'change_amount' => $change,
            'message' => $method === 'CASH'
                ? 'Pembayaran Rp ' . number_format($applied, 0, ',', '.') . ' diterima.' . ($due > 0 ? ' Sisa tagihan Rp ' . number_format($due, 0, ',', '.') . '.' : ' Tagihan lunas.')
                : 'Pembayaran ' . self::label($method) . ' Rp ' . number_format($applied, 0, ',', '.') . ' tercatat; menunggu verifikasi supervisor/keuangan.',
        ];
    }
    /** Verifikasi pembayaran QRIS/Transfer (supervisor/keuangan): QRIS -> QRIS Clearing; Transfer -> rekening bank tujuan (Bank Utama). */
    public function verifyQris(int $paymentId, bool $approve, ?int $userId): array
    {
        $db = db_connect();
        $db->transBegin();
        try {
            $pay = $db->query('SELECT * FROM pos_payments WHERE id = ? FOR UPDATE', [$paymentId])->getRowArray();
            if (! $pay || ! in_array($pay['method'], ['QRIS', 'TRANSFER'], true)) {
                throw new StockException('Pembayaran QRIS/Transfer tidak ditemukan.');
            }
            if ($pay['status'] !== 'PENDING_VERIFICATION') {
                throw new StockException('Pembayaran ini sudah diproses (' . $pay['status'] . ').');
            }
            $sale = $db->query('SELECT * FROM pos_sales WHERE id = ? FOR UPDATE', [$pay['sale_id']])->getRowArray();
            if ($sale['status'] !== 'POSTED') {
                throw new StockException('Penjualan sudah dibatalkan.');
            }

            $now = date('Y-m-d H:i:s');
            if ($approve) {
                $ftId = $this->recordIncome(new FinancialTransactionModel(), $paymentId, (float) $pay['amount'], (int) $pay['cash_account_id'],
                    "Penjualan POS {$sale['sale_no']} (" . self::label($pay['method']) . ')', $sale['customer_id'] ? (int) $sale['customer_id'] : null, $userId);
                $db->table('pos_payments')->where('id', $paymentId)->update([
                    'status' => 'CONFIRMED', 'verified_by' => $userId, 'verified_at' => $now, 'financial_transaction_id' => $ftId, 'updated_at' => $now,
                ]);
            } else {
                $db->table('pos_payments')->where('id', $paymentId)->update([
                    'status' => 'REJECTED', 'verified_by' => $userId, 'verified_at' => $now, 'updated_at' => $now,
                ]);
            }

            $paid    = (float) $db->query("SELECT COALESCE(SUM(amount),0) s FROM pos_payments WHERE sale_id = ? AND status = 'CONFIRMED'", [$sale['id']])->getRow('s');
            $pending = (int) $db->table('pos_payments')->where('sale_id', $sale['id'])->where('status', 'PENDING_VERIFICATION')->countAllResults();
            $total   = (float) $sale['grand_total'];
            $status  = $paid >= $total ? 'PAID' : ($pending ? 'PENDING_VERIFICATION' : ($paid > 0 ? 'PARTIAL' : 'UNPAID'));
            $db->table('pos_sales')->where('id', $sale['id'])->update([
                'paid_total' => $paid, 'balance_due' => round($total - $paid, 2), 'payment_status' => $status, 'updated_at' => $now,
            ]);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Verifikasi QRIS gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Verifikasi gagal disimpan.'];
        }

        $lbl = self::label($pay['method']);
        $this->audit->log($approve ? "Verifikasi {$lbl}" : "Tolak {$lbl}", 'pos', 'POS_PAYMENT', $paymentId, "Pembayaran {$lbl} " . ($approve ? 'dikonfirmasi' : 'ditolak'));

        return ['success' => true, 'message' => "Pembayaran {$lbl} " . ($approve ? 'dikonfirmasi.' : 'ditolak.')];
    }

    /** Pembatalan penjualan (supervisor): reversal stok + refund kas untuk pembayaran yang sudah terkonfirmasi. */
    public function void(int $saleId, string $reason, ?int $userId): array
    {
        if (trim($reason) === '') {
            return ['success' => false, 'message' => 'Alasan pembatalan wajib diisi.'];
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $sale = $db->query('SELECT * FROM pos_sales WHERE id = ? FOR UPDATE', [$saleId])->getRowArray();
            if (! $sale) {
                throw new StockException('Penjualan tidak ditemukan.');
            }
            if ($sale['status'] === 'VOIDED') {
                throw new StockException('Penjualan sudah dibatalkan.');
            }
            // Void membalik seluruh penjualan & pembayaran; bila sudah ada retur, itu akan menggandakan refund.
            if ($db->table('pos_returns')->where('sale_id', $saleId)->where('status', 'POSTED')->countAllResults()) {
                throw new StockException('Penjualan ini sudah punya retur. Gunakan Retur Barang untuk sisa barang, bukan Void.');
            }
            // Kas hari yang sudah EOD tidak boleh berubah diam-diam: void hanya selama hari kas penjualan masih OPEN.
            $day = $sale['daily_closing_id']
                ? $db->query('SELECT * FROM pos_daily_closings WHERE id = ? LOCK IN SHARE MODE', [$sale['daily_closing_id']])->getRowArray()
                : null;
            if (! $day || $day['status'] !== 'OPEN') {
                throw new StockException('Hari kas penjualan ini sudah ditutup (EOD). Pembatalan setelah EOD perlu proses retur/koreksi terpisah.');
            }

            $now = date('Y-m-d H:i:s');
            $ledgerRows = $db->table('stock_ledger')->where('reference_type', 'POS_SALE')->where('reference_id', $saleId)
                ->whereIn('movement_type', ['SALE', 'PROMO_FREE'])->orderBy('product_id')->get()->getResultArray();
            foreach ($ledgerRows as $r) {
                $this->ledger->reverse((int) $r['id'], "Void {$sale['sale_no']}: {$reason}", $userId);
            }

            $ftModel = new FinancialTransactionModel();
            $pays    = $db->query("SELECT * FROM pos_payments WHERE sale_id = ? FOR UPDATE", [$saleId])->getResultArray();
            $catId   = $db->table('financial_categories')->where('name', 'Refund Penjualan POS')->where('type', 'EXPENSE')->get()->getRow('id');
            foreach ($pays as $p) {
                if ($p['status'] === 'CONFIRMED') {
                    $ftModel->recordFromReference('POS_REFUND', (int) $p['id'], [
                        'transaction_date' => $day['business_date'], 'type' => 'EXPENSE', 'category_id' => $catId ?: null,
                        'amount' => (float) $p['amount'], 'description' => "Void POS {$sale['sale_no']}: {$reason}",
                        'cash_account_id' => (int) $p['cash_account_id'], 'created_by' => $userId,
                    ]);
                }
                if (in_array($p['status'], ['CONFIRMED', 'PENDING_VERIFICATION'], true)) {
                    $db->table('pos_payments')->where('id', $p['id'])->update(['status' => 'VOIDED', 'updated_at' => $now]);
                }
            }

            $db->table('pos_sales')->where('id', $saleId)->update([
                'status' => 'VOIDED', 'sync_status' => 'CANCELLED', 'void_reason' => $reason, 'voided_by' => $userId, 'voided_at' => $now, 'updated_at' => $now,
            ]);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof StockException) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
            log_message('error', 'Void POS gagal: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Pembatalan gagal disimpan.'];
        }

        $this->audit->log('Void POS', 'pos', 'POS_SALE', $saleId, $reason);

        return ['success' => true, 'message' => 'Penjualan dibatalkan; stok dan kas dikoreksi dengan reversal.'];
    }

    /** Metode bayar yang didukung. CASH langsung lunas; QRIS & TRANSFER menunggu verifikasi. */
    public const METHODS = ['CASH', 'QRIS', 'TRANSFER'];

    public static function label(string $method): string
    {
        return ['CASH' => 'Tunai', 'QRIS' => 'QRIS', 'TRANSFER' => 'Transfer'][$method] ?? $method;
    }

    /** Rekening kas/bank tujuan per metode: Tunai -> Kas Kasir, QRIS -> QRIS Clearing, Transfer -> Bank Utama (setting pos_transfer_account). */
    public function accountFor(string $method): int
    {
        $key = ['CASH' => 'pos_cash_account', 'QRIS' => 'pos_qris_account', 'TRANSFER' => 'pos_transfer_account'][$method] ?? 'pos_cash_account';
        $id  = (int) $this->settings->value($key, '0');
        if ($method === 'TRANSFER' && ! $id) {
            $id = (int) db_connect()->table('cash_accounts')->where('name', 'Bank Utama')->get()->getRow('id');
        }
        if (! $id) {
            throw new StockException('Rekening tujuan ' . self::label($method) . ' belum diatur.');
        }

        return $id;
    }

    private function recordIncome(FinancialTransactionModel $ft, int $paymentId, float $amount, int $cashAccountId, string $desc, ?int $customerId, ?int $userId, ?string $date = null): int
    {
        $catId = db_connect()->table('financial_categories')->where('name', 'Penjualan')->where('type', 'INCOME')->get()->getRow('id');
        $id    = $ft->recordFromReference('POS_PAYMENT', $paymentId, [
            'transaction_date' => $date ?: date('Y-m-d'), 'type' => 'INCOME', 'category_id' => $catId ?: null,
            'amount' => $amount, 'description' => $desc, 'cash_account_id' => $cashAccountId,
            'account_id' => $customerId, 'created_by' => $userId,
        ]);
        if (! $id) {
            throw new \RuntimeException('Gagal mencatat kas: ' . implode('; ', $ft->errors()));
        }

        return $id;
    }

    private function resultFor(array $sale, bool $duplicate): array
    {
        return [
            'success' => true, 'duplicate' => $duplicate, 'id' => (int) $sale['id'], 'sale_no' => $sale['sale_no'],
            'grand_total' => (float) $sale['grand_total'], 'paid_total' => (float) $sale['paid_total'],
            'change_amount' => (float) $sale['change_amount'], 'balance_due' => (float) $sale['balance_due'],
            'payment_status' => $sale['payment_status'],
            'message' => $duplicate ? 'Transaksi ini sudah tersimpan sebelumnya (tidak dibuat ganda).' : 'Transaksi ' . $sale['sale_no'] . ' berhasil.',
        ];
    }
}
