<?php
/**
 * Sidebar navigation (data-driven).
 * - Item hanya tampil jika user punya permission terkait.
 * - Permission khusus 'admin.only' = hanya Super Admin (role 1) & Admin (role 2).
 * - Grup bisa dilipat; grup yang berisi halaman aktif otomatis terbuka.
 * Format item: [label, icon, path, permission, exact?]
 */
$auth       = new \App\Services\AuthService();
$currentUri = trim(uri_string(), '/');

// Cek apakah user adalah Super Admin (role_id 1 saja)
$isAdmin = static function (): bool {
    $roleId = session()->get('role_id');
    if (! $roleId) {
        $userId = session()->get('user_id');
        if ($userId) {
            $row = db_connect()->table('users')->select('role_id')->where('id', $userId)->get()->getRowArray();
            $roleId = $row['role_id'] ?? null;
        }
    }
    return (int) $roleId === 1;
};

$can = static function (?string $slug) use ($auth, $isAdmin): bool {
    if ($slug === null) {
        return true;
    }
    if ($slug === 'admin.only') {
        return $isAdmin();
    }
    return $auth->hasPermission($slug);
};
$isActive   = static function (string $path, bool $exact = false) use ($currentUri): bool {
    return $currentUri === $path || (! $exact && str_starts_with($currentUri, $path . '/'));
};

$menu = [
    'Utama' => [
        ['Ringkasan',       'fa-house',         'admin/dashboard',        null, true],
        ['Margin Analysis', 'fa-chart-pie',     'admin/dashboard/margin', null, false],

    ],

    'Social Media' => [
    ['Dashboard KPI', 'fa-gauge-high', 'admin/sosmed/dashboard', null],
    ['Konten', 'fa-photo-film', 'admin/sosmed', null],
    ['Timeline', 'fa-timeline', 'admin/sosmed/timeline', null],
    ['Report Bulanan', 'fa-file-lines', 'admin/sosmed/report', null],
    ['Pengaturan Target', 'fa-bullseye', 'admin/sosmed/targets', 'admin.only'],
],


    'Master Data' => [
        ['Customer / Account', 'fa-address-book',       'admin/account',          'account.view'],
        ['Supplier',           'fa-truck-field',        'admin/supplier',         'supplier.view'],
        ['Sales',              'fa-user-tie',           'admin/sales-master',     'sales.view'],
        ['Product Category',   'fa-tags',               'admin/product-category', 'product.view'],
        ['Products',           'fa-boxes-stacked',      'admin/product',          'product.view'],
        ['Packing Material',   'fa-box',                'admin/packing-material', 'product.view'],
        ['Destination',        'fa-location-dot',       'admin/destination',      'product.view'],
        ['Transport Route',    'fa-truck',              'admin/transport-route',  'product.view'],
        ['Kategori OPEX',      'fa-money-bill-wave',    'admin/opex-category',    'product.view'],
        ['OPEX Area',          'fa-map-location-dot',   'admin/opex-area',        'product.view'],
        ['Aset Kantor',        'fa-briefcase',          'admin/office-asset',     null],
    ],
    'Pricing & Order' => [
        ['Price Period',    'fa-calendar-days',       'admin/price-period',    'price_period.view'],
        ['Pricing Tier',    'fa-layer-group',         'admin/pricing-tier',    'pricing_tier.view'],
        ['Payment Term',    'fa-hand-holding-dollar', 'admin/payment-term',    'payment_term.view'],
        ['Price Simulator', 'fa-calculator',          'admin/price-simulator', 'price_period.view'],
        ['Sales Order',     'fa-cart-shopping',       'admin/sales-order',     'price_period.view'],
    ],
    'Kebuni Mart POS' => [
        ['Kondisi Toko',        'fa-gauge-high',      'admin/pos/today',          'pos.view'],
        ['Buka / Tutup Kas',    'fa-store',           'admin/pos/day',            'pos.close_day'],
        ['Kasir',               'fa-cash-register',   'admin/pos',                'pos.sell', true],
        ['Penjualan POS',       'fa-receipt',         'admin/pos/sales',          'pos.view'],
        ['Piutang Pelanggan',   'fa-hand-holding-dollar', 'admin/pos/receivables', 'pos.view'],
        ['Pengecualian',        'fa-triangle-exclamation', 'admin/pos/exceptions',  'pos.void'],
        ['PIN Supervisor',      'fa-key',             'admin/pos/pin',            'pos.discount'],
        ['Retur & Koreksi',     'fa-rotate-left',     'admin/pos/returns',        'pos.return'],
        ['Setoran & QRIS',      'fa-building-columns', 'admin/pos/cash',          'pos.cash_deposit'],
        ['Katalog & Harga',     'fa-tags',            'admin/pos/prices',         'pos.price'],
        ['Promo Terjadwal',     'fa-bullhorn',        'admin/pos/promos',         'pos.price'],
        ['Stok Awal',           'fa-flag-checkered',  'admin/stock/opening',      'stock.opening'],
        ['Saran Pesan Ulang',   'fa-cart-arrow-down', 'admin/stock/reorder',      'stock.view'],
        ['Retur ke Supplier',   'fa-truck-arrow-right', 'admin/stock/supplier-returns', 'stock.supplier_return'],
        ['Penerimaan Barang',   'fa-truck-ramp-box',  'admin/stock/receipts',     'stock.view'],
        ['Saldo Stok',          'fa-warehouse',       'admin/stock',              'stock.view', true],
        ['Buku Stok',           'fa-book-open',       'admin/stock/book',         'stock.view'],
        ['Stock Opname',        'fa-clipboard-check', 'admin/stock/opname',       'stock.opname'],
        ['Broken Stock',        'fa-heart-crack',     'admin/stock/broken',       'stock.broken'],
        ['Laporan Penyusutan',  'fa-chart-column',    'admin/stock/shrinkage',    'stock.view'],
        ['Analisa Laba & Produk', 'fa-chart-pie',     'admin/pos/analysis',       'finance.view'],
        ['Buying Power',        'fa-basket-shopping', 'admin/pos/buying-power',   'finance.view'],
        ['Hourly Sales',        'fa-clock',           'admin/pos/hourly',         'finance.view'],
    ],
    'Absensi' => [
        ['Halaman Absensi',   'fa-camera',             'absensi',                    'attendance.view', false, '_blank'],
        ['Monitoring Absensi', 'fa-clock',              'admin/attendance',           'attendance.view', true],
        ['Rekap Absensi',      'fa-table-list',         'admin/attendance/recap',     'attendance.view'],
        ['Lokasi Kantor',      'fa-location-crosshairs', 'admin/attendance/locations', 'attendance.view'],
    ],
    'Keuangan' => [
        ['Dashboard Keuangan', 'fa-chart-line',            'admin/finance/dashboard',  'admin.only'],
        ['Buku Besar',         'fa-book',                  'admin/finance/ledger',     'admin.only'],
        ['Kelola Rekening',    'fa-wallet',                'admin/finance/accounts',   'admin.only'],
        ['Pemasukan',          'fa-arrow-trend-up',        'admin/finance/income',     'admin.only'],
        ['Pengeluaran',        'fa-arrow-trend-down',      'admin/finance/expense',    'admin.only'],
        ['Hutang',             'fa-file-invoice-dollar',   'admin/finance/payable',    'admin.only'],
        ['Piutang',            'fa-hand-holding-dollar',   'admin/finance/receivable', 'admin.only'],
        ['Laporan Keuangan',   'fa-file-invoice',          'admin/finance/report',     'admin.only'],
    ],
    'Report' => [
        ['Report Transaksi', 'fa-file-lines', 'admin/report/transaction', 'report.view'],
        ['Report Absensi',   'fa-file-lines', 'admin/report/attendance',  'report.view'],
        ['Report Keuangan',  'fa-file-lines', 'admin/report/finance',     'report.view'],
        ['Final Report',     'fa-file-contract', 'admin/report/final',      'report.view'],
        ['Report Aset Kantor', 'fa-briefcase', 'admin/report/office-asset', 'report.view'],
    ],
    'System' => [
        ['User',      'fa-users-gear',      'admin/user',      'admin.only'],
        ['Setting',   'fa-gears',           'admin/setting',   'admin.only'],
        ['Audit Log', 'fa-clipboard-list',  'admin/audit-log', 'admin.only'],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fa-solid fa-building"></i>
        <span class="brand-text"><?= esc((new \App\Models\SettingModel())->value('company_name', 'Back Office')) ?></span>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $group => $items): ?>
            <?php
            $visible = array_values(array_filter($items, static fn ($i) => $can($i[3] ?? null)));
            if (empty($visible)) {
                continue;
            }
            $open = false;
            foreach ($visible as $i) {
                if (($i[5] ?? null) === null && $isActive($i[2], $i[4] ?? false)) {
                    $open = true;
                }
            }
            $gid = 'grp-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($group));
            ?>
            <button type="button" class="nav-group-toggle" data-bs-toggle="collapse" data-bs-target="#<?= $gid ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>">
                <span><?= esc($group) ?></span> <i class="fa-solid fa-chevron-down chev"></i>
            </button>
            <ul class="nav flex-column collapse <?= $open ? 'show' : '' ?>" id="<?= $gid ?>" data-group="<?= esc($group) ?>">
                <?php foreach ($visible as $i): ?>
                    <?php
                    [$label, $icon, $path] = $i;
                    $target = $i[5] ?? null;
                    $active = $target === null && $isActive($path, $i[4] ?? false);
                    ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= base_url($path) ?>" <?= $target ? 'target="' . $target . '"' : '' ?>>
                            <i class="fa-solid <?= $icon ?>"></i> <span><?= esc($label) ?></span>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endforeach ?>
    </nav>
</aside>
