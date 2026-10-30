# Back Office Management System

Aplikasi Back Office berbasis **CodeIgniter 4** + Bootstrap 5 + jQuery.

## PERUBAHAN ARSITEKTUR: PO / Taking Order & Pricing Engine

Modul **Master Barang (Product/Barang lama)** dan **Transaksi** yang sebelumnya dibangun
(Fase 2 & Fase 3 versi awal) telah **digantikan sepenuhnya** oleh sistem **Pricing Engine**
yang jauh lebih komprehensif: Price Period historis/immutable, perhitungan COGS otomatis
(purchase price + packing cost + shrinkage), transport cost per destination, pricing tier
berbasis volume, payment term dengan bunga majemuk, hingga price snapshot pada PO.

### Status: PRICING ENGINE - FASE 1: Master Data Foundation (selesai)

Master data yang dibangun pada tahap ini (murni definisi/konfigurasi, **tidak menyimpan
harga aktif permanen** — harga akan disimpan di Price Period pada fase berikutnya):

- **Product Category** — CRUD sederhana, proteksi tidak bisa dihapus jika masih dipakai product
- **Products** — Product Code (unique), Product Name, Category, Unit, Shrinkage Type
  (Percentage/Fixed) & Value, Status, Notes. Dilengkapi **Packing Recipe** (assign banyak
  packing material + quantity/usage per product, dynamic row, disimpan all-or-nothing
  lewat database transaction)
- **Packing Material** — Code (unique), Name, Unit, Capacity (Kg, opsional jika material
  dihitung langsung per Kg tanpa basis kapasitas), Status, Notes. Proteksi tidak bisa
  dihapus jika masih dipakai di recipe product manapun
- **Destination** — Master tujuan pengiriman (Jakarta, BLT, dst), proteksi tidak bisa
  dihapus jika masih dipakai transport route
- **Transport Route** — Origin (teks bebas) → Destination, dengan default basis kalkulasi
  (**Capacity** atau **Actual Volume**, sesuai spesifikasi section 11)

Formula-formula dasar (packing cost/kg, shrinkage cost, transport cost/kg) sudah disiapkan
sebagai method reusable di masing-masing Service (`PackingMaterialService::calculateCostPerKg()`,
`ProductService::calculateShrinkageCost()`, `TransportRouteService::calculateCostPerKg()`),
siap dipanggil ulang oleh Pricing Engine di fase Price Period & Taking Order.

### Status: PRICING ENGINE - FASE 2: Price Period (selesai)

- **Price Period** (header): kode digenerate otomatis (`PP-YYYY-MM-DD`), default rentang 7 hari
  (editable), lifecycle **DRAFT → PUBLISHED → CLOSED** (tidak bisa mundur), notes
- **Lock rule sesuai spesifikasi**: DRAFT bebas diedit; PUBLISHED masih bisa diedit **selama
  belum dipakai transaksi** (titik integrasi `PricePeriodService::isUsedByTransaction()` sudah
  disiapkan — begitu modul Taking Order/PO dibangun, cukup isi query di situ); CLOSED tidak
  pernah bisa diedit. Delete hanya untuk status Draft.
- **Product Price** per periode: Purchase Price + override Shrinkage % (opsional, jika kosong
  pakai shrinkage master product), dengan preview kalkulasi Shrinkage Cost real-time di browser
- **Packing Price** per periode: Price, Capacity (snapshot, editable), Cost/Kg **otomatis**
  (`price / capacity`), dengan opsi **manual override** cost/kg untuk material dengan formula khusus
- **Transport Price** per periode: Total Transport Cost, Volume (Kg), Basis (Capacity/Actual),
  Cost/Kg otomatis (`total cost / volume`)
- **Duplicate as Revision**: menyalin seluruh Product/Packing/Transport Price ke Price Period
  baru (status Draft) tanpa mengubah periode asal — sesuai contoh spesifikasi PP-001 → PP-002
- Semua baris harga disimpan **all-or-nothing** lewat database transaction (replace pattern,
  sama seperti Packing Recipe di Fase 1)
- `PricePeriodModel::findActiveForDate()` & `hasOverlap()` sudah disiapkan untuk dipakai
  Taking Order (auto-select periode berdasarkan tanggal transaksi, dan validasi overlap)

### Status: PRICING ENGINE - FASE 3: Pricing Tier + Payment Term + Price Calculation Engine (selesai)

- **Pricing Tier**: markup berdasarkan range qty (Min/Max Qty, kosongkan Max untuk tier tanpa
  batas atas seperti "5000+"), configurable (tidak hardcode), dengan Start/End Date opsional
  agar historical, dan validasi **anti-overlap** antar tier aktif
- **Payment Term**: Cash / Tempo dengan Days & Daily Interest % (configurable), dipakai untuk
  **compound interest**: `Final Price = Cash Price × (1 + Daily Interest)^Days`
- **Price Calculation Engine** (`PriceCalculationService`) — inti dari fitur ini, murni kalkulasi
  (tidak menyimpan apapun), mengikuti urutan WAJIB sesuai spesifikasi section 24:
  Purchase Price → Packing Cost/Kg (dari recipe product) → Shrinkage Cost → COGS Sukabumi →
  Transport Cost/Kg (by transport route/destination) → COGS Destination → Pricing Tier →
  Markup → Cash Price → Payment Term → Compound Interest → Final Price → Subtotal → Profit
  - `calculateItem()` untuk 1 baris produk, `calculateOrder()` untuk banyak item + summary
    (Total Qty, Total COGS, Total Cash Value, Total Interest, Grand Total, Estimated Profit,
    Estimated Profit %, Markup Weighted Average) sesuai section 25-26
  - Mendukung **manual price override** dengan warning otomatis jika di bawah harga rekomendasi
    sistem (section 27); profit selalu dihitung dari COGS actual, bukan estimasi
  - Titik integrasi Taking Order (`findActiveForDate`, `hasOverlap` di Price Period) sudah
    tersedia dari Fase 2 untuk auto-select periode berdasarkan tanggal order
- **Price Simulator**: halaman uji end-to-end untuk Engine ini sebelum modul Taking Order/PO
  dibangun — pilih Price Period + Payment Term, input banyak item (Product, Qty, Destination,
  Manual Price opsional), lihat breakdown lengkap per item (tabel sesuai section 23) + summary
  cards, semua dihitung server-side lewat AJAX (bukan JavaScript, sesuai prinsip "Frontend
  hanya untuk UI/UX/Preview")

### Fase Pricing Engine berikutnya (belum dibangun)

- Fase 4: Taking Order / PO (UI sederhana untuk Sales, price snapshot, manual override, approval workflow)
- Fase 5: Dashboard Pricing, Price History, Audit Log, Report (Sales/Profit/Margin Analysis), Export PDF/Excel

## Status: FASE 2 - Master Data (DIGANTIKAN, lihat bagian Pricing Engine di atas)

Ditambahkan pada tahap ini:

- **Account**: CRUD lengkap (form registrasi + tabel dalam 1 halaman), search, filter status,
  pagination AJAX, detail modal, toggle status, delete dengan modal konfirmasi
- **Supplier**: CRUD lengkap dengan pola sama seperti Account, plus proteksi
  "tidak bisa dihapus jika masih memiliki barang" (data integrity)
- **Barang (Product)**:
  - Halaman listing terpisah dengan filter Supplier & Status
  - Halaman registrasi dengan **dynamic row form** (tambah/hapus baris via jQuery)
  - **Bulk upload Excel** dengan flow: Upload → Validate → Parse → Preview →
    tampilkan baris error → Confirm Import → Database Transaction → Insert
    (tidak langsung insert tanpa preview, sesuai spesifikasi)
  - Download template Excel
  - Edit barang via modal
- **FileUploadService**: reusable, file selalu disimpan dengan nama random (bukan nama asli),
  validasi extension/MIME/size terpusat — siap dipakai ulang untuk dokumen transaksi & foto absensi
- **ProductImportService**: parsing & validasi Excel terpisah dari proses insert (PhpSpreadsheet)
- Composer dependency `phpoffice/phpspreadsheet` ditambahkan ke `composer.json`

## Status: FASE 1 - Fondasi (selesai)

Yang sudah dibangun pada tahap ini:

- Struktur project CodeIgniter 4 (MVC, Service Layer)
- Konfigurasi `.env` (database & app config, tidak hardcode)
- Layout: `main.php`, `auth.php`, `blank.php`
- Partials reusable: navbar, sidebar (permission-based menu, active-state),
  breadcrumb, flash_message (toast), modal_confirm, loading, pagination
- Autentikasi: login/logout, session, `AuthService`
- Role & Permission: Super Admin, Admin, Finance, HR — dengan `AuthFilter` & `RoleFilter`
  (permission dicek lewat filter, tidak diulang manual di controller)
- Migration: users, roles, permissions, role_permissions, activity_logs,
  accounts, suppliers, products, company_locations, company_profiles,
  transactions, transaction_items, transaction_documents,
  attendances, financial_categories, financial_transactions, receivables, payables
- Seeder: RolePermissionSeeder, UserSeeder (Super Admin default)
- Routing lengkap (grup admin protected + role filter per permission)
- CSS/JS dasar sesuai referensi UI (dominan putih, primary biru, card border tipis)

## Setup

1. `composer install`
2. Copy `.env` sudah disediakan — sesuaikan kredensial database Anda
   (`database.default.hostname`, `.database`, `.username`, `.password`)
3. Buat database `backoffice_db` (atau sesuai nama di `.env`)
4. Jalankan migration:
   ```
   php spark migrate
   ```
5. Jalankan seeder:
   ```
   php spark db:seed RolePermissionSeeder
   php spark db:seed UserSeeder
   ```
6. Jalankan server:
   ```
   php spark serve
   ```
7. Login dengan:
   - Username: `superadmin`
   - Password: `ChangeMe123!`
   - **Segera ganti password setelah login pertama.**

## Struktur Modul Lain Berikutnya (belum dibangun)

- Fase 4: Absensi publik (camera, GPS, geofence)
- Fase 5: Keuangan (ledger, income/expense, piutang/hutang, dashboard real-time)
- Fase 6: Reporting & Company Profile

(Lihat bagian "PERUBAHAN ARSITEKTUR: PO / Taking Order & Pricing Engine" di atas
untuk roadmap modul Pricing Engine yang menggantikan Master Barang & Transaksi lama.)

## Prinsip Arsitektur

- Controller tipis — delegasi ke Service
- Business logic di Service (`AuthService`, dan Service lain akan menyusul:
  `AccountService`, `TransactionService`, `AttendanceService`, `FinanceService`, dst.)
- Tidak ada query database di View
- Semua nominal uang & waktu penting (absensi, transaksi) ditentukan SERVER, bukan JavaScript
- CSRF & XSS protection aktif secara default (bawaan CodeIgniter 4)
