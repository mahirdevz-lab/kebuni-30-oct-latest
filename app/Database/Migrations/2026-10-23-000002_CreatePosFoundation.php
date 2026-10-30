<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kebuni Mart POS - Fase 1 (POS online + stock ledger).
 * Lihat docs/POS-PROGRESS.md untuk keputusan desain.
 *
 * Konvensi: qty DECIMAL(14,3) (produk timbang), uang DECIMAL(18,2), biaya satuan DECIMAL(18,4).
 */
class CreatePosFoundation extends Migration
{
    private const ID    = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    private const BIGID = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true];

    private function fk(string $type = 'INT', bool $null = false): array
    {
        return ['type' => $type, 'constraint' => $type === 'INT' ? 11 : 20, 'unsigned' => true, 'null' => $null];
    }

    private function qty(bool $null = false, $default = null): array
    {
        $f = ['type' => 'DECIMAL', 'constraint' => '14,3', 'null' => $null];
        if ($default !== null) {
            $f['default'] = $default;
        }
        return $f;
    }

    private function money(bool $null = false, $default = null, string $c = '18,2'): array
    {
        $f = ['type' => 'DECIMAL', 'constraint' => $c, 'null' => $null];
        if ($default !== null) {
            $f['default'] = $default;
        }
        return $f;
    }

    private const TS = [
        'created_at' => ['type' => 'DATETIME', 'null' => true],
        'updated_at' => ['type' => 'DATETIME', 'null' => true],
    ];

    public function up()
    {
        // ---------------------------------------------------------------- lokasi stok
        $this->forge->addField([
            'id'     => self::ID,
            'code'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'name'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'status' => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
        ] + self::TS);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('stock_locations');

        // ---------------------------------------------------------------- perluasan master yang ada (kolom nullable / default aman)
        $this->forge->addColumn('products', [
            'barcode'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'product_code'],
            'is_pos'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'status'],
            'min_stock' => ['type' => 'DECIMAL', 'constraint' => '14,3', 'null' => true, 'after' => 'is_pos'],
        ]);
        $this->db->query('ALTER TABLE products ADD UNIQUE KEY products_barcode_unique (barcode)');

        $this->forge->addColumn('accounts', [
            'customer_type' => ['type' => 'ENUM', 'constraint' => ['B2B', 'RETAIL'], 'default' => 'B2B', 'after' => 'nama_account'],
        ]);
        $this->db->query('ALTER TABLE accounts ADD KEY accounts_phone_idx (nomor_telepon)');

        // ---------------------------------------------------------------- harga retail (versi + waktu berlaku) & markdown
        $this->forge->addField([
            'id'             => self::ID,
            'product_id'     => $this->fk(),
            'retail_price'   => $this->money(),
            'effective_from' => ['type' => 'DATETIME'],
            'created_by'     => $this->fk('INT', true),
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['product_id', 'effective_from']);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_product_prices');

        $this->forge->addField([
            'id'           => self::ID,
            'product_id'   => $this->fk(),
            'normal_price' => $this->money(),
            'promo_price'  => $this->money(),
            'reason'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'start_at'     => ['type' => 'DATETIME'],
            'end_at'       => ['type' => 'DATETIME'],
            'status'       => ['type' => 'ENUM', 'constraint' => ['ACTIVE', 'CANCELLED'], 'default' => 'ACTIVE'],
            'approved_by'  => $this->fk('INT', true),
            'created_by'   => $this->fk('INT', true),
        ] + self::TS);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['product_id', 'start_at', 'end_at']);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_markdowns');

        // ---------------------------------------------------------------- penerimaan barang
        $this->forge->addField([
            'id'           => self::ID,
            'receipt_no'   => ['type' => 'VARCHAR', 'constraint' => 30],
            'supplier_id'  => $this->fk('INT', true),
            'location_id'  => $this->fk(),
            'receipt_date' => ['type' => 'DATE'],
            'reference_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'notes'        => ['type' => 'TEXT', 'null' => true],
            'status'       => ['type' => 'ENUM', 'constraint' => ['POSTED', 'REVERSED'], 'default' => 'POSTED'],
            'created_by'   => $this->fk('INT', true),
        ] + self::TS);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('receipt_no');
        $this->forge->addKey('receipt_date');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('goods_receipts');

        $this->forge->addField([
            'id'          => self::ID,
            'receipt_id'  => $this->fk(),
            'product_id'  => $this->fk(),
            'qty_regular' => $this->qty(false, 0),
            'qty_bonus'   => $this->qty(false, 0),      // bonus supplier, dipisah dari gratis promo pelanggan
            'line_cost'   => $this->money(false, 0),    // total biaya pembelian baris (Rp0 bila hanya bonus)
            'notes'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('receipt_id');
        $this->forge->addForeignKey('receipt_id', 'goods_receipts', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('goods_receipt_items');

        // ---------------------------------------------------------------- saldo berjalan (kunci baris untuk concurrency) & ledger
        $this->forge->addField([
            'location_id' => $this->fk(),
            'product_id'  => $this->fk(),
            'qty'         => $this->qty(false, 0),
            'avg_cost'    => $this->money(false, 0, '18,4'),
            'value'       => $this->money(false, 0),
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey(['location_id', 'product_id'], true);
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('stock_balances');

        $this->forge->addField([
            'id'              => self::BIGID,
            'location_id'     => $this->fk(),
            'product_id'      => $this->fk(),
            'movement_type'   => ['type' => 'ENUM', 'constraint' => [
                'OPENING', 'PURCHASE', 'BONUS', 'SALE', 'PROMO_FREE', 'CUSTOMER_RETURN', 'SUPPLIER_RETURN',
                'BROKEN', 'OPNAME_ADJUST', 'TRANSFER_IN', 'TRANSFER_OUT', 'REVERSAL',
            ]],
            'qty_in'          => $this->qty(false, 0),
            'qty_out'         => $this->qty(false, 0),
            'balance_before'  => $this->qty(),
            'balance_after'   => $this->qty(),
            'unit_cost'       => $this->money(false, 0, '18,4'),  // biaya satuan mutasi (snapshot)
            'total_cost'      => $this->money(false, 0),          // nilai mutasi (snapshot)
            'avg_cost_after'  => $this->money(false, 0, '18,4'),
            'value_after'     => $this->money(),
            'effective_at'    => ['type' => 'DATETIME'],
            'posted_at'       => ['type' => 'DATETIME'],
            'reference_type'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'reference_id'    => $this->fk('BIGINT'),
            'reference_line'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'reason'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'user_id'         => $this->fk('INT', true),
            'status'          => ['type' => 'ENUM', 'constraint' => ['POSTED', 'REVERSED'], 'default' => 'POSTED'],
            'reversal_of_id'  => $this->fk('BIGINT', true),
        ]);
        $this->forge->addKey('id', true);
        // Idempotensi: satu baris sumber hanya boleh menghasilkan satu mutasi per jenis.
        $this->forge->addUniqueKey(['reference_type', 'reference_id', 'reference_line', 'movement_type'], 'stock_ledger_idem');
        $this->forge->addKey(['product_id', 'location_id', 'effective_at']);
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('stock_ledger');

        // ---------------------------------------------------------------- penjualan POS
        $this->forge->addField([
            'id'             => self::BIGID,
            'sale_no'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'client_uuid'    => ['type' => 'CHAR', 'constraint' => 36],  // idempotency key dari klien
            'sale_date'      => ['type' => 'DATETIME'],
            'location_id'    => $this->fk(),
            'customer_id'    => $this->fk('INT', true),
            'cashier_id'     => $this->fk('INT', true),
            'subtotal'       => $this->money(false, 0),
            'discount_total' => $this->money(false, 0),
            'grand_total'    => $this->money(false, 0),
            'paid_total'     => $this->money(false, 0),    // pembayaran terkonfirmasi
            'balance_due'    => $this->money(false, 0),
            'change_amount'  => $this->money(false, 0),
            'cogs_total'     => $this->money(false, 0),    // snapshot HPP moving average
            'payment_status' => ['type' => 'ENUM', 'constraint' => ['PAID', 'PARTIAL', 'UNPAID', 'PENDING_VERIFICATION'], 'default' => 'UNPAID'],
            'sync_status'    => ['type' => 'ENUM', 'constraint' => ['LOCAL', 'STORE_ACCEPTED', 'SYNCED', 'PENDING', 'CONFLICT', 'CANCELLED'], 'default' => 'SYNCED'],
            'status'         => ['type' => 'ENUM', 'constraint' => ['POSTED', 'VOIDED'], 'default' => 'POSTED'],
            'void_reason'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'voided_by'      => $this->fk('INT', true),
            'voided_at'      => ['type' => 'DATETIME', 'null' => true],
            'notes'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ] + self::TS);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sale_no');
        $this->forge->addUniqueKey('client_uuid');
        $this->forge->addKey('sale_date');
        $this->forge->addForeignKey('location_id', 'stock_locations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('customer_id', 'accounts', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('cashier_id', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('pos_sales');

        $this->forge->addField([
            'id'          => self::BIGID,
            'sale_id'     => $this->fk('BIGINT'),
            'line_no'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'product_id'  => $this->fk(),
            'line_type'   => ['type' => 'ENUM', 'constraint' => ['NORMAL', 'PROMO_FREE'], 'default' => 'NORMAL'],
            'promo_for_line' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true], // line_no barang berbayar yang memicu BOGO
            'qty'         => $this->qty(),
            'unit'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'list_price'  => $this->money(),                 // harga normal saat transaksi
            'unit_price'  => $this->money(),                 // harga yang dipakai (setelah markdown)
            'markdown_id' => $this->fk('INT', true),
            'line_total'  => $this->money(),
            'unit_cost'   => $this->money(false, 0, '18,4'), // snapshot moving average
            'cogs'        => $this->money(false, 0),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['sale_id', 'line_no']);
        $this->forge->addForeignKey('sale_id', 'pos_sales', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_sale_items');

        $this->forge->addField([
            'id'                       => self::BIGID,
            'sale_id'                  => $this->fk('BIGINT'),
            'method'                   => ['type' => 'ENUM', 'constraint' => ['CASH', 'QRIS']],
            'amount'                   => $this->money(),    // nilai yang dibebankan ke transaksi
            'tendered'                 => $this->money(false, 0),
            'change_amount'            => $this->money(false, 0),
            'status'                   => ['type' => 'ENUM', 'constraint' => ['CONFIRMED', 'PENDING_VERIFICATION', 'REJECTED', 'VOIDED'], 'default' => 'CONFIRMED'],
            'cash_account_id'          => $this->fk('INT', true),
            'reference_no'             => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'verified_by'              => $this->fk('INT', true),
            'verified_at'              => ['type' => 'DATETIME', 'null' => true],
            'financial_transaction_id' => $this->fk('BIGINT', true),
            'created_by'               => $this->fk('INT', true),
        ] + self::TS);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sale_id');
        $this->forge->addForeignKey('sale_id', 'pos_sales', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_payments');

        // ---------------------------------------------------------------- laporan pengecualian override stok
        $this->forge->addField([
            'id'            => self::BIGID,
            'sale_id'       => $this->fk('BIGINT', true),
            'product_id'    => $this->fk(),
            'requested_qty' => $this->qty(),
            'available_qty' => $this->qty(),
            'reason'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'supervisor_id' => $this->fk('INT'),
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('supervisor_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('pos_stock_overrides');

        $this->seed();
    }

    private function seed(): void
    {
        $now = date('Y-m-d H:i:s');

        // Lokasi tunggal tahap awal
        $this->db->table('stock_locations')->insert(['code' => 'TOKO', 'name' => 'Toko Kebuni Mart', 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now]);

        // Akun kas POS (cash_accounts.type bebas)
        foreach ([['Kas Kasir', 'CASH'], ['QRIS Clearing', 'CLEARING']] as [$name, $type]) {
            if (! $this->db->table('cash_accounts')->where('name', $name)->countAllResults()) {
                $this->db->table('cash_accounts')->insert([
                    'name' => $name, 'type' => $type, 'initial_balance' => 0, 'is_active' => 1,
                    'notes' => 'Akun POS Kebuni Mart', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Kategori income penjualan POS memakai kategori "Penjualan" yang sudah ada.

        // Settings
        foreach ([
            'pos_cash_account'        => (string) $this->db->table('cash_accounts')->where('name', 'Kas Kasir')->get()->getRow('id'),
            'pos_qris_account'        => (string) $this->db->table('cash_accounts')->where('name', 'QRIS Clearing')->get()->getRow('id'),
            'pos_location_id'         => (string) $this->db->table('stock_locations')->where('code', 'TOKO')->get()->getRow('id'),
            'bonus_cost_policy'       => 'allocate', // allocate | zero -- menunggu keputusan Keuangan (PRD A6.1)
            'pos_store_name'          => 'Kebuni Mart',
            'pos_store_whatsapp_footer' => 'Terima kasih sudah berbelanja di Kebuni Mart.',
        ] as $key => $value) {
            if (! $this->db->table('settings')->where('setting_key', $key)->countAllResults()) {
                $this->db->table('settings')->insert(['setting_key' => $key, 'setting_value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // Permissions
        $perms = [
            'pos.view'           => 'Lihat Penjualan POS',
            'pos.sell'           => 'Checkout POS',
            'pos.void'           => 'Batalkan Penjualan POS',
            'pos.override'       => 'Override Stok Negatif',
            'pos.verify_payment' => 'Verifikasi Pembayaran QRIS',
            'pos.price'          => 'Kelola Harga Retail & Markdown',
            'stock.view'         => 'Lihat Stok & Buku Stok',
            'stock.receive'      => 'Penerimaan Barang',
        ];
        foreach ($perms as $slug => $name) {
            $this->db->table('permissions')->insert([
                'name' => $name, 'slug' => $slug, 'module' => explode('.', $slug)[0], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $permId = fn (string $slug) => (int) $this->db->table('permissions')->where('slug', $slug)->get()->getRow('id');

        // Roles baru
        $roles = [
            'kasir'             => ['Kasir', 'Checkout & melihat transaksi sendiri', ['pos.view', 'pos.sell']],
            'supervisor-toko'   => ['Supervisor Toko', 'Void, override stok, verifikasi pembayaran, markdown', ['pos.view', 'pos.sell', 'pos.void', 'pos.override', 'pos.verify_payment', 'pos.price', 'stock.view']],
            'admin-inventori'   => ['Admin Inventori', 'Penerimaan barang & buku stok', ['stock.view', 'stock.receive', 'pos.price']],
        ];
        foreach ($roles as $slug => [$name, $desc, $slugs]) {
            $this->db->table('roles')->insert(['name' => $name, 'slug' => $slug, 'description' => $desc, 'created_at' => $now, 'updated_at' => $now]);
            $roleId = (int) $this->db->insertID();
            foreach ($slugs as $s) {
                $this->db->table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permId($s)]);
            }
        }

        // Admin (role 2) dan Finance (role 3) pada role existing
        $adminId   = (int) $this->db->table('roles')->where('slug', 'admin')->get()->getRow('id');
        $financeId = (int) $this->db->table('roles')->where('slug', 'finance')->get()->getRow('id');
        foreach (array_keys($perms) as $s) {
            $this->db->table('role_permissions')->insert(['role_id' => $adminId, 'permission_id' => $permId($s)]);
        }
        foreach (['pos.view', 'pos.verify_payment', 'stock.view'] as $s) {
            $this->db->table('role_permissions')->insert(['role_id' => $financeId, 'permission_id' => $permId($s)]);
        }
    }

    public function down()
    {
        foreach (['pos_stock_overrides', 'pos_payments', 'pos_sale_items', 'pos_sales', 'stock_ledger', 'stock_balances',
                  'goods_receipt_items', 'goods_receipts', 'pos_markdowns', 'pos_product_prices'] as $t) {
            $this->forge->dropTable($t, true);
        }

        $this->db->query('ALTER TABLE accounts DROP KEY accounts_phone_idx');
        $this->forge->dropColumn('accounts', 'customer_type');
        $this->db->query('ALTER TABLE products DROP KEY products_barcode_unique');
        $this->forge->dropColumn('products', ['barcode', 'is_pos', 'min_stock']);
        $this->forge->dropTable('stock_locations', true);

        $slugs = ['pos.view', 'pos.sell', 'pos.void', 'pos.override', 'pos.verify_payment', 'pos.price', 'stock.view', 'stock.receive'];
        $this->db->table('permissions')->whereIn('slug', $slugs)->delete(); // role_permissions ikut terhapus (CASCADE)
        $this->db->table('roles')->whereIn('slug', ['kasir', 'supervisor-toko', 'admin-inventori'])->delete();
        $this->db->table('settings')->whereIn('setting_key', ['pos_cash_account', 'pos_qris_account', 'pos_location_id', 'bonus_cost_policy', 'pos_store_name', 'pos_store_whatsapp_footer'])->delete();
        $this->db->table('cash_accounts')->whereIn('name', ['Kas Kasir', 'QRIS Clearing'])->delete();
    }
}
