<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ------------------------------------------------------------------
// PUBLIC ROUTES
// ------------------------------------------------------------------
$routes->get('/', 'AuthController::showLogin');
$routes->get('/login', 'AuthController::showLogin');
$routes->post('/login', 'AuthController::login');
$routes->post('/logout', 'AuthController::logout');

// Halaman absensi PUBLIC - tanpa login (lihat spesifikasi modul Absensi)
$routes->get('/absensi', 'AttendanceController::showForm');
$routes->post('/absensi/submit', 'AttendanceController::submit');

// ------------------------------------------------------------------
// BACK OFFICE (PROTECTED) ROUTES
// ------------------------------------------------------------------
$routes->group('admin', ['filter' => 'auth'], static function ($routes) {

    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('dashboard/margin', 'MarginDashboardController::index');
    $routes->get('dashboard/margin/trend', 'MarginDashboardController::trend');
    $routes->get('dashboard/margin/ranking', 'MarginDashboardController::ranking');
    
    $routes->get('profile', 'ProfileController::index');
    $routes->post('profile/update-password', 'ProfileController::updatePassword');

    // MASTER DATA
    $routes->group('account', ['filter' => 'role:account.view'], static function ($routes) {
        $routes->get('/', 'AccountController::index');
        $routes->post('store', 'AccountController::store', ['filter' => 'role:account.create']);
        $routes->get('(:num)/edit', 'AccountController::edit/$1', ['filter' => 'role:account.update']);
        $routes->post('(:num)/update', 'AccountController::update/$1', ['filter' => 'role:account.update']);
        $routes->post('(:num)/delete', 'AccountController::delete/$1', ['filter' => 'role:account.delete']);
        $routes->post('(:num)/toggle-status', 'AccountController::toggleStatus/$1', ['filter' => 'role:account.update']);
        $routes->get('(:num)/detail', 'AccountController::detail/$1');
    });
    
    $routes->group('sales-master', ['filter' => 'role:sales.view'], static function ($routes) {
        $routes->get('/', 'SalesController::index');
        $routes->post('store', 'SalesController::store', ['filter' => 'role:sales.create']);
        $routes->get('(:num)/edit', 'SalesController::edit/$1', ['filter' => 'role:sales.update']);
        $routes->post('(:num)/update', 'SalesController::update/$1', ['filter' => 'role:sales.update']);
        $routes->post('(:num)/delete', 'SalesController::delete/$1', ['filter' => 'role:sales.delete']);
    });

    $routes->group('sales-order', static function ($routes) {

        // Monitoring PO
        $routes->get('/', 'SalesOrderController::index');

        // Create / Taking Order
        $routes->get('create', 'SalesOrderController::create');

        // Edit
        $routes->get('(:num)/edit', 'SalesOrderController::edit/$1');

        // Komponen (transport/packing/opex/labour) per price period -- HARUS di atas (:num)
        $routes->get('components-by-period', 'SalesOrderController::componentsByPeriod');

        // Detail
        $routes->get('(:num)', 'SalesOrderController::detail/$1');
        $routes->get('(:num)/print', 'SalesOrderController::print/$1');

        // AJAX preview kalkulasi
        $routes->post('calculate', 'SalesOrderController::calculate');

        // Simpan Draft Baru
        $routes->post('store', 'SalesOrderController::store');

        // Update Draft
        $routes->post('(:num)/update', 'SalesOrderController::update/$1');

        // Workflow
        $routes->post('(:num)/submit', 'SalesOrderController::submit/$1');
        $routes->post('(:num)/approve', 'SalesOrderController::approve/$1');
        $routes->post('(:num)/process', 'SalesOrderController::process/$1');
        $routes->post('(:num)/complete', 'SalesOrderController::complete/$1');
        $routes->post('(:num)/cancel', 'SalesOrderController::cancel/$1');

        // Delete
        $routes->post('(:num)/delete', 'SalesOrderController::delete/$1');

        // Komisi sales (hutang + cicilan)
        $routes->post('(:num)/commission-pay', 'SalesOrderController::payCommission/$1');
        $routes->get('(:num)/commission-history', 'SalesOrderController::commissionHistory/$1');
        $routes->get('commission-proof/(:num)', 'SalesOrderController::commissionProof/$1');
    });

    $routes->group('supplier', ['filter' => 'role:supplier.view'], static function ($routes) {
        $routes->get('/', 'SupplierController::index');
        $routes->post('store', 'SupplierController::store', ['filter' => 'role:supplier.create']);
        $routes->get('(:num)/edit', 'SupplierController::edit/$1', ['filter' => 'role:supplier.update']);
        $routes->post('(:num)/update', 'SupplierController::update/$1', ['filter' => 'role:supplier.update']);
        $routes->post('(:num)/delete', 'SupplierController::delete/$1', ['filter' => 'role:supplier.delete']);
        $routes->post('(:num)/toggle-status', 'SupplierController::toggleStatus/$1', ['filter' => 'role:supplier.update']);
    });

    // Deklarasikan route spesifik di luar/sebelum group
    $routes->get('labour-rates/by-period', 'LabourRateController::getByPeriod');

    // LABOUR RATES (Di dalam group 'admin')
    $routes->group('labour-rates', ['filter' => 'role:product.view'], static function ($routes) {
        $routes->get('/', 'LabourRateController::index');
        $routes->get('by-period', 'LabourRateController::getByPeriod'); // Endpoint AJAX
        $routes->post('store', 'LabourRateController::store', ['filter' => 'role:product.create']);
        $routes->post('(:num)/update', 'LabourRateController::update/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/delete', 'LabourRateController::delete/$1', ['filter' => 'role:product.delete']);
    });

    // PRICING ENGINE - PRICING TIER, PAYMENT TERM, SIMULATOR
    $routes->group('pricing-tier', ['filter' => 'role:pricing_tier.view'], static function ($routes) {
        $routes->get('/', 'PricingTierController::index');
        $routes->post('store', 'PricingTierController::store', ['filter' => 'role:pricing_tier.create']);
        $routes->get('(:num)/edit', 'PricingTierController::edit/$1', ['filter' => 'role:pricing_tier.update']);
        $routes->post('(:num)/update', 'PricingTierController::update/$1', ['filter' => 'role:pricing_tier.update']);
        $routes->post('(:num)/delete', 'PricingTierController::delete/$1', ['filter' => 'role:pricing_tier.delete']);
    });

    $routes->group('payment-term', ['filter' => 'role:payment_term.view'], static function ($routes) {
        $routes->get('/', 'PaymentTermController::index');
        $routes->post('store', 'PaymentTermController::store', ['filter' => 'role:payment_term.create']);
        $routes->get('(:num)/edit', 'PaymentTermController::edit/$1', ['filter' => 'role:payment_term.update']);
        $routes->post('(:num)/update', 'PaymentTermController::update/$1', ['filter' => 'role:payment_term.update']);
        $routes->post('(:num)/delete', 'PaymentTermController::delete/$1', ['filter' => 'role:payment_term.delete']);
    });

    $routes->group('price-simulator', ['filter' => 'role:price_period.view'], static function ($routes) {
        $routes->get('/', 'PriceSimulatorController::index');
        $routes->post('calculate', 'PriceSimulatorController::calculate');
        $routes->get('components-by-period', 'PriceSimulatorController::componentsByPeriod');
    });

    // PRICING ENGINE - PRICE PERIOD
    $routes->group('price-period', ['filter' => 'role:price_period.view'], static function ($routes) {
        $routes->get('/', 'PricePeriodController::index');
        $routes->get('create', 'PricePeriodController::create', ['filter' => 'role:price_period.create']);
        $routes->post('store', 'PricePeriodController::store', ['filter' => 'role:price_period.create']);
        $routes->get('(:num)', 'PricePeriodController::detail/$1');
        $routes->post('(:num)/update-header', 'PricePeriodController::updateHeader/$1', ['filter' => 'role:price_period.update']);
        $routes->post('(:num)/publish', 'PricePeriodController::publish/$1', ['filter' => 'role:price_period.update']);
        $routes->post('(:num)/close', 'PricePeriodController::close/$1', ['filter' => 'role:price_period.update']);
        $routes->post('(:num)/delete', 'PricePeriodController::delete/$1', ['filter' => 'role:price_period.delete']);
        $routes->post('(:num)/duplicate', 'PricePeriodController::duplicate/$1', ['filter' => 'role:price_period.create']);

        $routes->get('(:num)/labour-rates', 'PricePeriodController::LabourRates/$1');
        $routes->post('(:num)/labour-rates/save', 'PricePeriodController::saveLabourRates/$1');
        
        $routes->get('(:num)/product-prices', 'PricePeriodController::productPrices/$1');
        $routes->post('(:num)/product-prices/save', 'PricePeriodController::saveProductPrices/$1', ['filter' => 'role:price_period.update']);

        $routes->get('(:num)/packing-prices', 'PricePeriodController::packingPrices/$1');
        $routes->post('(:num)/packing-prices/save', 'PricePeriodController::savePackingPrices/$1', ['filter' => 'role:price_period.update']);

        $routes->get('(:num)/transport-prices', 'PricePeriodController::transportPrices/$1');
        $routes->post('(:num)/transport-prices/save', 'PricePeriodController::saveTransportPrices/$1', ['filter' => 'role:price_period.update']);

        $routes->get('(:num)/opex', 'PricePeriodController::opex/$1');
        $routes->post('(:num)/opex/save', 'PricePeriodController::saveOpex/$1', ['filter' => 'role:price_period.update']);
    });
    $routes->group('product-category', ['filter' => 'role:product.view'], static function ($routes) {
        $routes->get('/', 'ProductCategoryController::index');
        $routes->post('store', 'ProductCategoryController::store', ['filter' => 'role:product.create']);
        $routes->get('(:num)/edit', 'ProductCategoryController::edit/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/update', 'ProductCategoryController::update/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/delete', 'ProductCategoryController::delete/$1', ['filter' => 'role:product.delete']);
    });

    $routes->group('product', ['filter' => 'role:product.view'], static function ($routes) {
        $routes->get('/', 'ProductController::index');
        $routes->post('store', 'ProductController::store', ['filter' => 'role:product.create']);
        $routes->get('(:num)/edit', 'ProductController::edit/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/update', 'ProductController::update/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/delete', 'ProductController::delete/$1', ['filter' => 'role:product.delete']);
        $routes->get('(:num)/recipe', 'ProductController::recipe/$1');
        $routes->post('(:num)/recipe/save', 'ProductController::saveRecipe/$1', ['filter' => 'role:product.update']);
    });

    $routes->group('packing-material', ['filter' => 'role:product.view'], static function ($routes) {
        $routes->get('/', 'PackingMaterialController::index');
        $routes->post('store', 'PackingMaterialController::store', ['filter' => 'role:product.create']);
        $routes->get('(:num)/edit', 'PackingMaterialController::edit/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/update', 'PackingMaterialController::update/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/delete', 'PackingMaterialController::delete/$1', ['filter' => 'role:product.delete']);
    });

    $routes->group('destination', ['filter' => 'role:product.view'], static function ($routes) {
        $routes->get('/', 'DestinationController::index');
        $routes->post('store', 'DestinationController::store', ['filter' => 'role:product.create']);
        $routes->get('(:num)/edit', 'DestinationController::edit/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/update', 'DestinationController::update/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/delete', 'DestinationController::delete/$1', ['filter' => 'role:product.delete']);
    });

    $routes->group('transport-route', ['filter' => 'role:product.view'], static function ($routes) {
        $routes->get('/', 'TransportRouteController::index');
        $routes->post('store', 'TransportRouteController::store', ['filter' => 'role:product.create']);
        $routes->get('(:num)/edit', 'TransportRouteController::edit/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/update', 'TransportRouteController::update/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/delete', 'TransportRouteController::delete/$1', ['filter' => 'role:product.delete']);
    });

    $routes->group('opex-category', ['filter' => 'role:product.view'], static function ($routes) {
        $routes->get('/', 'OpexCategoryController::index');
        $routes->post('store', 'OpexCategoryController::store', ['filter' => 'role:product.create']);
        $routes->get('(:num)/edit', 'OpexCategoryController::edit/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/update', 'OpexCategoryController::update/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/delete', 'OpexCategoryController::delete/$1', ['filter' => 'role:product.delete']);
    });

    $routes->group('opex-area', ['filter' => 'role:product.view'], static function ($routes) {
        $routes->get('/', 'OpexAreaController::index');
        $routes->post('store', 'OpexAreaController::store', ['filter' => 'role:product.create']);
        $routes->get('(:num)/edit', 'OpexAreaController::edit/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/update', 'OpexAreaController::update/$1', ['filter' => 'role:product.update']);
        $routes->post('(:num)/delete', 'OpexAreaController::delete/$1', ['filter' => 'role:product.delete']);
    });

    // TRANSAKSI (akan digantikan modul Sales Order / Taking Order pada fase Pricing Engine)


    // ABSENSI (monitoring, khusus back office)
    $routes->group('attendance', ['filter' => 'role:attendance.view'], static function ($routes) {
        $routes->get('/', 'AttendanceMonitorController::index');
        $routes->get('recap', 'AttendanceMonitorController::recap');
        $routes->get('summary', 'AttendanceMonitorController::summary');
        $routes->get('photo/(:num)', 'AttendanceMonitorController::photo/$1');
        $routes->get('locations', 'AttendanceMonitorController::locations');
        $routes->post('locations/store', 'AttendanceMonitorController::saveLocation', ['filter' => 'role:attendance.create']);
        $routes->post('locations/(:num)/update', 'AttendanceMonitorController::saveLocation/$1', ['filter' => 'role:attendance.update']);
        $routes->post('locations/(:num)/toggle', 'AttendanceMonitorController::toggleLocation/$1', ['filter' => 'role:attendance.update']);
        $routes->post('locations/(:num)/delete', 'AttendanceMonitorController::deleteLocation/$1', ['filter' => 'role:attendance.delete']);
    });

    // KEUANGAN
    $routes->group('finance', ['filter' => 'role:finance.view'], static function ($routes) {
        $routes->get('dashboard', 'FinanceController::dashboard');
        $routes->get('dashboard/summary', 'FinanceController::dashboardSummary');
        $routes->get('chart/cash-flow', 'FinanceController::cashFlowChart');

        $routes->get('income', 'FinanceController::income');
        $routes->post('income/store', 'FinanceController::storeIncome', ['filter' => 'role:finance.create']);

        $routes->get('expense', 'FinanceController::expense');
        $routes->post('expense/store', 'FinanceController::storeExpense', ['filter' => 'role:finance.create']);
        $routes->get('expense-proof/(:num)', 'FinanceController::expenseProof/$1', ['filter' => 'role:finance.view']);
        $routes->get('income-proof/(:num)', 'FinanceController::incomeProof/$1', ['filter' => 'role:finance.view']);
        $routes->get('ledger', 'FinanceController::ledger', ['filter' => 'role:finance.view']);
        $routes->get('accounts', 'FinanceController::accounts', ['filter' => 'role:finance.view']);
        $routes->post('accounts/store', 'FinanceController::storeAccount', ['filter' => 'role:finance.create']);
        $routes->post('accounts/(:num)/balance', 'FinanceController::updateAccountBalance/$1', ['filter' => 'role:finance.update']);
        $routes->post('accounts/(:num)/toggle', 'FinanceController::toggleAccount/$1', ['filter' => 'role:finance.update']);

        $routes->get('receivable', 'FinanceController::receivable');
        $routes->post('receivable/(:num)/pay', 'FinanceController::payReceivable/$1', ['filter' => 'role:finance.update']);

        $routes->get('payable', 'FinanceController::payable');
        $routes->post('payable/store', 'FinanceController::storePayable', ['filter' => 'role:finance.create']);
        $routes->post('payable/(:num)/pay', 'FinanceController::payPayable/$1', ['filter' => 'role:finance.update']);

        $routes->get('report', 'FinanceController::report');
    });

    // SOCIAL MEDIA (konten tim)
    $routes->group('sosmed', static function ($routes) {
        $routes->get('/', 'SosmedController::index');
        $routes->get('dashboard', 'SosmedController::dashboard');
        $routes->get('timeline', 'SosmedController::timeline');
        $routes->get('report', 'SosmedController::report');
        $routes->get('targets', 'SosmedController::targets');
        $routes->post('targets/save', 'SosmedController::saveTarget');
        $routes->post('targets/(:num)/delete', 'SosmedController::deleteTarget/$1');
        $routes->post('store', 'SosmedController::store');
        $routes->get('(:num)', 'SosmedController::show/$1');
        $routes->get('(:num)/video', 'SosmedController::video/$1');
        $routes->post('(:num)/comment', 'SosmedController::comment/$1');
        $routes->post('(:num)/review', 'SosmedController::review/$1');
        $routes->post('(:num)/resubmit', 'SosmedController::resubmit/$1');
        $routes->post('(:num)/publish', 'SosmedController::publish/$1');
        $routes->post('(:num)/metrics', 'SosmedController::saveMetrics/$1');
    });

    // OFFICE ASSET (master data)
    $routes->group('office-asset', static function ($routes) {
        $routes->get('/', 'OfficeAssetController::index');
        $routes->post('store', 'OfficeAssetController::store');
        $routes->post('(:num)/update', 'OfficeAssetController::update/$1');
        $routes->post('(:num)/delete', 'OfficeAssetController::delete/$1');
    });

    // REPORT
    $routes->group('report', ['filter' => 'role:report.view'], static function ($routes) {
        $routes->get('transaction', 'ReportController::transaction');
        $routes->get('attendance', 'ReportController::attendance');
        $routes->get('finance', 'ReportController::finance');
        $routes->get('final', 'ReportController::final');
        $routes->post('final/opname', 'ReportController::saveOpname');
        $routes->get('office-asset', 'OfficeAssetController::report');
    });

    // SYSTEM
    $routes->group('user', ['filter' => 'role:user.view'], static function ($routes) {
        $routes->get('/', 'UserController::index');
        $routes->get('create', 'UserController::create', ['filter' => 'role:user.create']);
        $routes->post('store', 'UserController::store', ['filter' => 'role:user.create']);
        $routes->get('(:num)/edit', 'UserController::edit/$1', ['filter' => 'role:user.update']);
        $routes->post('(:num)/update', 'UserController::update/$1', ['filter' => 'role:user.update']);
        $routes->post('(:num)/delete', 'UserController::delete/$1', ['filter' => 'role:user.delete']);
    });

    $routes->group('setting', ['filter' => 'role:setting.view'], static function ($routes) {
        $routes->get('/', 'SettingController::index');
        $routes->post('update', 'SettingController::update', ['filter' => 'role:setting.update']);
    });

    $routes->get('audit-log', 'AuditLogController::index', ['filter' => 'role:setting.view']);
    $routes->get('notifications', 'NotificationController::index');

    // ------------------------------------------------------------------
    // KEBUNI MART POS & STOK (hak akses ditegakkan di sini, bukan hanya di UI)
    // ------------------------------------------------------------------
    $routes->group('pos', static function ($routes) {
        $routes->get('/', 'PosController::index', ['filter' => 'role:pos.sell']);
        $routes->get('products', 'PosController::products', ['filter' => 'role:pos.sell']);
        $routes->get('customers', 'PosController::customers', ['filter' => 'role:pos.sell']);
        $routes->post('customers/store', 'PosController::storeCustomer', ['filter' => 'role:pos.sell']);
        $routes->post('checkout', 'PosController::checkout', ['filter' => 'role:pos.sell']);
        $routes->post('verify-supervisor', 'PosController::verifySupervisor', ['filter' => 'role:pos.sell']);
        $routes->get('pin', 'PosController::pin', ['filter' => 'role:pos.discount,pos.override']);
        $routes->post('pin', 'PosController::savePin', ['filter' => 'role:pos.discount,pos.override']);

        $routes->get('today', 'PosDashboardController::index', ['filter' => 'role:pos.view']);
        $routes->get('exceptions', 'PosExceptionController::index', ['filter' => 'role:pos.void,pos.verify_payment']);
        $routes->get('sales', 'PosController::sales', ['filter' => 'role:pos.view']);
        $routes->get('sales/report', 'PosController::salesReport', ['filter' => 'role:pos.view']);
        $routes->get('receivables', 'PosController::receivables', ['filter' => 'role:pos.view']);
        $routes->post('sales/(:num)/pay', 'PosController::payBalance/$1', ['filter' => 'role:pos.sell']);
        $routes->get('sales/(:num)', 'PosController::receipt/$1', ['filter' => 'role:pos.view']);
        $routes->post('sales/(:num)/void', 'PosController::void/$1', ['filter' => 'role:pos.void']);
        $routes->post('payments/(:num)/verify', 'PosController::verifyPayment/$1', ['filter' => 'role:pos.verify_payment']);

        // Retur pelanggan & koreksi metode bayar (berlaku juga setelah EOD)
        $routes->get('returns', 'PosReturnController::index', ['filter' => 'role:pos.return,pos.payment_correct,pos.view']);
        $routes->get('returns/(:num)', 'PosReturnController::show/$1', ['filter' => 'role:pos.return,pos.payment_correct,pos.view']);
        $routes->get('sales/(:num)/return', 'PosReturnController::form/$1', ['filter' => 'role:pos.return']);
        $routes->post('sales/(:num)/return', 'PosReturnController::store/$1', ['filter' => 'role:pos.return']);
        $routes->post('payments/(:num)/correct', 'PosReturnController::correctPayment/$1', ['filter' => 'role:pos.payment_correct']);

        // Analisa laba rugi & penyusutan (data keuangan -> finance.view)
        $routes->get('analysis', 'AnalysisController::index', ['filter' => 'role:finance.view']);
        $routes->get('buying-power', 'PosBuyingPowerController::index', ['filter' => 'role:finance.view']);
        $routes->get('hourly', 'PosHourlyController::index', ['filter' => 'role:finance.view']);

        // Setoran kas ke bank & settlement QRIS (transfer internal, bukan omzet)
        $routes->get('cash', 'PosCashController::index', ['filter' => 'role:pos.cash_deposit,pos.qris_settle']);
        $routes->get('cash/proof/(:segment)/(:num)', 'PosCashController::proof/$1/$2', ['filter' => 'role:pos.cash_deposit,pos.qris_settle']);
        $routes->post('cash/deposit', 'PosCashController::deposit', ['filter' => 'role:pos.cash_deposit']);
        $routes->post('cash/settle', 'PosCashController::settle', ['filter' => 'role:pos.qris_settle']);

        // Buka toko & EOD (tutup kas harian)
        $routes->get('day', 'DailyClosingController::index', ['filter' => 'role:pos.close_day']);
        $routes->post('day/open', 'DailyClosingController::open', ['filter' => 'role:pos.close_day']);
        $routes->post('day/(:num)/close', 'DailyClosingController::close/$1', ['filter' => 'role:pos.close_day']);

        $routes->group('promos', ['filter' => 'role:pos.price'], static function ($routes) {
            $routes->get('/', 'PosPromoController::index');
            $routes->post('store', 'PosPromoController::store');
            $routes->post('(:num)/cancel', 'PosPromoController::cancel/$1');
        });

        $routes->group('prices', ['filter' => 'role:pos.price'], static function ($routes) {
            $routes->get('/', 'PosPriceController::index');
            $routes->post('markdown/(:num)/cancel', 'PosPriceController::cancelMarkdown/$1');
            $routes->post('(:num)/save', 'PosPriceController::save/$1');
            $routes->post('(:num)/markdown', 'PosPriceController::markdown/$1');
        });
    });

    // Stock opname setelah EOD (petugas hitung tidak harus punya stock.view)
    $routes->group('stock/opname', static function ($routes) {
        $routes->get('/', 'StockOpnameController::index', ['filter' => 'role:stock.opname,stock.opname_approve']);
        $routes->get('(:num)', 'StockOpnameController::show/$1', ['filter' => 'role:stock.opname,stock.opname_approve']);
        $routes->post('open', 'StockOpnameController::open', ['filter' => 'role:stock.opname_approve']);
        $routes->post('(:num)/count', 'StockOpnameController::count/$1', ['filter' => 'role:stock.opname']);
        $routes->post('(:num)/submit', 'StockOpnameController::submit/$1', ['filter' => 'role:stock.opname']);
        $routes->post('(:num)/add-line', 'StockOpnameController::addLine/$1', ['filter' => 'role:stock.opname']);
        $routes->post('(:num)/recount', 'StockOpnameController::recount/$1', ['filter' => 'role:stock.opname_approve']);
        $routes->post('(:num)/confirm', 'StockOpnameController::confirm/$1', ['filter' => 'role:stock.opname_approve']);
        $routes->post('(:num)/approve', 'StockOpnameController::approve/$1', ['filter' => 'role:stock.opname_approve']);
        $routes->post('(:num)/cancel', 'StockOpnameController::cancel/$1', ['filter' => 'role:stock.opname_approve']);
    });

    // Retur ke supplier
    $routes->group('stock/supplier-returns', ['filter' => 'role:stock.supplier_return'], static function ($routes) {
        $routes->get('/', 'SupplierReturnController::index');
        $routes->get('(:num)', 'SupplierReturnController::show/$1');
        $routes->post('store', 'SupplierReturnController::store');
    });

    // Stok awal toko (go-live)
    $routes->group('stock/opening', static function ($routes) {
        $routes->get('/', 'StockOpeningController::index', ['filter' => 'role:stock.opening,stock.opening_post']);
        $routes->get('(:num)', 'StockOpeningController::show/$1', ['filter' => 'role:stock.opening,stock.opening_post']);
        $routes->post('create', 'StockOpeningController::create', ['filter' => 'role:stock.opening']);
        $routes->post('(:num)/save', 'StockOpeningController::save/$1', ['filter' => 'role:stock.opening']);
        $routes->post('(:num)/cancel', 'StockOpeningController::cancel/$1', ['filter' => 'role:stock.opening']);
        $routes->post('(:num)/post', 'StockOpeningController::post/$1', ['filter' => 'role:stock.opening_post']);
    });

    // Broken Stock (pelapor tidak harus punya stock.view; mis. kasir melapor dari HP)
    $routes->group('stock/broken', static function ($routes) {
        $routes->get('/', 'BrokenStockController::index', ['filter' => 'role:stock.broken,stock.broken_approve']);
        $routes->get('(:num)/photo', 'BrokenStockController::photo/$1', ['filter' => 'role:stock.broken,stock.broken_approve,stock.view']);
        $routes->post('store', 'BrokenStockController::store', ['filter' => 'role:stock.broken']);
        $routes->post('(:num)/cancel', 'BrokenStockController::cancel/$1', ['filter' => 'role:stock.broken']);
        $routes->post('(:num)/approve', 'BrokenStockController::approve/$1', ['filter' => 'role:stock.broken_approve']);
        $routes->post('(:num)/reject', 'BrokenStockController::reject/$1', ['filter' => 'role:stock.broken_approve']);
    });

    $routes->group('stock', ['filter' => 'role:stock.view'], static function ($routes) {
        $routes->get('/', 'StockController::index');
        $routes->get('shrinkage', 'StockController::shrinkage');
        $routes->get('reorder', 'ReorderController::index');
        $routes->get('book', 'StockController::book');
        $routes->get('receipts', 'StockController::receipts');
        $routes->get('receipts/(:num)', 'StockController::receiptDetail/$1');
        $routes->post('receipts/store', 'StockController::storeReceipt', ['filter' => 'role:stock.receive']);
    });
});

