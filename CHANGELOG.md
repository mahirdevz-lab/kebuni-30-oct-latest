# Changelog

Semua perubahan penting pada **Backoffice Kebuni Fresh Mart (MIS)** didokumentasikan di sini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versi mengikuti [Semantic Versioning](https://semver.org/lang/id/).
Entri juga tercermin di riwayat commit (commit konvensional `feat:` / `fix:` / `chore:`).

## [Unreleased]

### Added
- UI upgrade bertahap T1–T6 sesuai `docs/PRD-BACKOFFICE-UI.md` (branch `ui-upgrade`):
  - **T1 — Fondasi design system & pagination**: template pager `kebuni_full` (tombol Awal/Akhir + info "Menampilkan X–Y dari Z data"), `perPage` default 10, partial `pager_footer` & `status_badge`, helper JS `fmtNum`/`fmtRp`/`fmtDate`, CSS pagination + skeleton.
  - **T2 — Bug pagination & konsolidasi**: 4 modul (destination, payment_term, pricing_tier, product_category) kini berpaginasi + search AJAX dengan `_table.php` ala product; `dropdown()` eksplisit tanpa pager untuk dropdown form (ganti `paginate()` palsu); `perPage` diseragamkan 10 (audit log tetap 20, eksplisit).
  - **T3 — Shell & komponen global**: tombol "+ Taking Order" di topbar (mengikuti permission), skeleton loading untuk refresh tabel, judul browser "Kebuni Back Office" + favicon, tanggal Indonesia via helper `tgl_id()`, ikon KPI finance, link "Lihat semua" di kartu dashboard, notasi ikon `fa-solid` seragam.
  - **T4 — Modernisasi taking_order & price_simulator**: header legacy → `section-header`, `alert()` → toast, komponen dropdown-checklist packing shared (payload identik), switch "Via Gudang Sukabumi", ringkasan gaya kpi-card, hapus formatter lokal.
  - **T5 — Polish transaksi**: sales_order form — tombol Hitung Preview disabled sampai syarat terpenuhi, produk ketik-untuk-cari (datalist), indikator data basi + banner hitung ulang, badge `bg-danger` untuk harga di bawah rekomendasi; product — semantik warna aksi, indikator & badge "tanpa recipe", highlight baris edit, modal recipe static-backdrop.
  - **T6 — Rollout & QA**: tanggal Indonesia di semua tabel (`tgl_id`), QA statis (0 `alert()`, 0 `fas fa-`, 0 formatter lokal, pager via partial).
- **Refresh in-place penuh — 0 `window.location.reload()` tersisa** (PRD AC-6, PRD §8):
  - Helper global `refreshInPlace(sel, url, params, page)` (list: skeleton → fetch
    partial → restore scroll) dan `refreshFragment(sel, url, done)` (detail: fetch
    URL yang sama, `DOMParser`, ganti isi kartu, tanpa endpoint baru).
  - Modul `sales_master` & `attendance/locations` kini AJAX penuh mengikuti pola
    golden `destination`: partial baru `sales_master/_table.php` +
    `attendance/_locations_table.php` (pola `_table` + `pager_footer` + `status_badge`),
    controller bercabang `isAJAX`, query search via `scopeList()` di model
    (`SalesModel`, `CompanyLocationModel`), pagination `pager->paginate()` +
    template `kebuni_full`.
  - Halaman detail `sales_order` & `price_period`: seluruh aksi lifecycle (submit/
    approve/process/complete/cancel/delete, publish, close, simpan header) kini
    refresh kartu terdampak in-place (`#headerCard`, `#tableItems`, `#cardAudit`,
    wadah tab) tanpa reload penuh — posisi scroll dipertahankan.
- **Pass aksesibilitas (PRD T6)**: `aria-label` pada semua tombol ikon-saja
  (17 `_table.php`, report, template JS `btn-remove-*`), tombol edit kini
  `btn-outline-primary` (semantik warna konsisten — 14 lokasi `btn-outline-warning`
  dihapus), `autofocus` pada field pertama form product & sales order,
  focus ring `:focus-visible` seragam (btn, nav-link, page-link, dropdown-item)
  di `app.css`.
- Helper PHP global `tgl_id()` di `app/Common.php` — format tanggal Bahasa Indonesia via ICU.
- Komponen JS `PackingChecklist` di `public/assets/js/app.js` — dropdown-checklist packing reusable.

### Changed
- `app/Config/Pager.php`: `default_full` → `App\Views\pager\kebuni_full`, `perPage` 20 → 10.
- `app/Services/AttendanceService.php`: `perPage` default 15 → 10.
- `app/Services/PricePeriodService.php`, `app/Services/AccountService.php`: tambah `dropdown()` (limit eksplisit, tanpa pager).
- `app/Models/ProductPackingRecipeModel.php`: tambah `productIdsWithRecipe()`; `app/Services/ProductService.php`: tambah `recipeProductIds()`.
- Semua tabel memakai partial `pager_footer` (info baris + template `kebuni_full`); `partials/pagination.php` kini mendelegasikan ke `pager_footer`.
  - **Tahap 2 lanjutan (PRD §7 Tahap 2 / AC-2)**: `pager_footer` di-rollout ke **16 tabel
    sisanya** (account, attendance, audit_log, finance ×4, opex_area, opex_category,
    packing_material, price_period, product, sales_order, supplier, transport_route,
    user) — sebelumnya hanya 4 modul T2 yang memakainya; kini seluruh list menampilkan
    info "Menampilkan X–Y dari Z data" + First/Last (BUG-4/6/7, 0 sisa `$pager->links()`
    mentah di `*_table.php`).
  - **Dropdown form seragam cap 500 tanpa pager** (BUG-5, keputusan terkunci):
    `PriceSimulatorController` (period 100 → 500) dan `SalesOrderController`
    (accounts 1000 → 500, periods 200 → 500) memakai default `dropdown()` =
    `limit(500)->findAll()`; `grep paginate(null)` = 0 sisa di seluruh codebase.
- Versioning control: repository Git privat `kebuni-backoffice` (GitHub, owner `mahirdevz-lab`).
- `.gitignore` & `.gitattributes` — pengecualian `vendor/`, `.env`, backup, log, cache runtime.
- `.env.example` — template konfigurasi environment tanpa kredensial.
- Baseline metrik UI backoffice (PRD-BACKOFFICE-UI §7 Tahap 0). **Catatan OQ-4: staf
  tidak tersedia untuk uji tugas — angka M-1/M-3 adalah ESTIMASI Bima (ditandai sebagai
  estimasi, untuk perbandingan kasar); M-2 dihitung dari struktur sidebar
  `partials/sidebar.php` (grup "Pricing & Order" collapsible + tombol "Taking Order Baru"
  di `sales_order/index.php`):**
  - M-1 Waktu input 1 sales order 3 item: ± 12 menit (estimasi). Target: turun ≥ 25%.
  - M-3 Error rate input (submit gagal validasi / data salah): ± 10 dari 100 transaksi (estimasi). Target: turun ≥ 30%.
  - M-2 Klik login → form Taking Order baru: 3 klik + scroll (buka grup → Sales Order →
    "Taking Order Baru"). Target: ≤ 2 klik (pintasan topbar, Tahap 3).
- Branch kerja `ui-upgrade` dari `main`; dokumen PRD UI digabung ke `docs/PRD-BACKOFFICE-UI.md` (main, `ae2cea2`).

### Fixed
- **Typo URL lifecycle `sales_order/detail`**: `BASE` didefinisikan
  `"…/sales-order/ID/ " + ORDER_ID` (spasi setelah garis miring) sehingga semua
  aksi lifecycle memanggil `…/sales-order/1/ 1/submit` → 404. Diperbaiki menjadi
  satu interpolasi `…/ID` + `/submit`.
- **Fungsi `formatRupiah()` lokal di `dashboard/margin.php` dihapus** (sisa T4):
  view mendefinisikan ulang formatter padahal `app.js` sudah punya alias global
  `formatRupiah` (→ `fmtRp`) — berpotensi bentrok duplikasi definisi. Semua format
  kini lewat helper global `fmtRp`/`fmtNum`/`fmtDate` di `app.js`, konsisten
  dengan keputusan T4 (grep `function format*` lokal di `app/Views` = 0).
- **Fungsi `formatCompact()` lokal di `dashboard/margin.php` dihapus** (sisa T6):
  duplikat formatter untuk label axis ApexCharts — dipindah ke `app.js` sebagai
  `fmtCompact()` (alias `formatCompact` dipertahankan agar panggilan lama tetap
  jalan). grep `function format*` di `app/Views` kini benar-benar nol.
- **Sidebar mobile tidak bisa ditutup** (drawer terbuka lalu mentok — tidak ada
  tombol minimize/hamburger yang bisa di-tap). Penyebabnya kombinasi: (1) tombol
  toggle hanya ada di topbar yang tersembunyi di belakang sidebar ketika drawer
  terbuka (z-index topbar 1020 < sidebar 1050), (2) "overlay" gelap lama hanyalah
  `box-shadow` CSS yang tidak bisa diklik. Sekarang: tombol **X** di dalam sidebar,
  **backdrop** nyata yang bisa di-tap untuk menutup, auto-close saat menu dipilih,
  reset state saat rotate/resize, dan box-shadow 100vmax diganti backdrop elemen.
  diverifikasi 17/17 assertion Playwright (mobile 390×844 + desktop 1280×800,
  fixture memakai CSS/JS asli app).
- **Cache busting asset** (`app.css`, `app.js`): URL kini menyertakan
  `?v=<filemtime>` di semua layout (`main`, `auth`, `blank`) — perubahan CSS/JS
  langsung terlihat di HP tanpa perlu clear cache browser.
- **Timezone aplikasi: UTC → WIB (`Asia/Jakarta`)** di `app/Config/App.php`
  (`$appTimezone`). Sebelumnya semua pencatatan waktu memakai `date()` PHP yang
  mengikuti UTC, sehingga absensi tercatat **7 jam lebih lambat** (bug: "Absensi
  masuk berhasil dicatat pukul 06.28" padahal 13:28 WIB), begitu pula `created_at`
  di seluruh tabel. Konsekuensinya: indikator telat `is_late` ikut meleset (absensi
  pagi hari tak terdeteksi telat karena jamnya malah 01:00 WIB dini hari).
  **Data lama yang tersimpan UTC sudah dimigrasi +7 jam** (backup SQL di
  `scratch/kbtest/backup_kebuni_pre_tz_migration.sql`); 2 record absensi
  dikoreksi manual (kondisi awal sudah ter-migrasi). Kolom `date` bisnis
  (`order_date`, `start_date`/`end_date`) tidak disentuh karena berasal dari input
  user, bukan `date()` PHP. MySQL server sudah SYSTEM WIB — tidak ada konflik
  `NOW()`.
- **Pagination error 500 di 4 modul** (`pricing_tier`, `destination`, `payment_term`,
  `product_category`): partial `app/Views/partials/pager_footer.php` memanggil
  `$pager->getPerPageStart()` / `$pager->getPerPageEnd()` yang **tidak ada** di
  `CodeIgniter\Pager\Pager` v4.7.5 → `Error: Call to undefined method` dan halaman
  menampilkan "We seem to have hit a snag". Range baris kini dihitung dari method yang
  valid (`getCurrentPage()`, `getPerPage()`, `getTotal()`). Diverifikasi unit test render
  partial dengan class `Pager` asli (6 kasus: halaman awal/tengah/akhir, pas 1 halaman,
  data kosong, `$pager` tidak diset).

### Known issues (audit backlog — detail di task Bima/Dimas)
- Bug/error pagination backoffice sudah dituntaskan T1–T6 (audit §5 nol); sisa backlog
  non-fungsional ada di dokumen `docs/IMPROVE-PAGINATION-BACKOFFICE.md`.

## [baseline-20261004] - 2026-10-04

### Added
- Snapshot pertama seluruh source code backoffice Kebuni (CodeIgniter 4.7, PHP 8.2):
  - `app/` — Controllers (28), Models (30+), Services, Views (28 modul), Config.
  - `system/` — core framework CI4.
  - `public/` — entry point `index.php`, `.htaccess`, aset.
  - `writable/` — keeper `index.html` saja (cache/logs/session/uploads di-ignore).
  - `composer.json` + `composer.lock` — `vendor/` di-exclude, restore via `composer install`.

### Security
- `.env` asli (berisi kredensial DB `kebuni`) TIDAK ikut ter-commit; hanya `.env.example`.
- File backup/arsip (`*.zip`, `*.sql`, `*.tar.gz`, `*.bak`) di-exclude dari repo.

[Unreleased]: https://github.com/mahirdevz-lab/kebuni-backoffice/compare/baseline-20261004...HEAD
[baseline-20261004]: https://github.com/mahirdevz-lab/kebuni-backoffice/releases/tag/baseline-20261004
