# PRD — Upgrade Tampilan (UI/UX) Backoffice Kebuni Fresh Mart

| | |
|---|---|
| **Judul** | PRD Upgrade Tampilan Backoffice Kebuni (PRD-BACKOFFICE-UI) |
| **Versi** | 1.0 — DRAFT |
| **Tanggal** | 5 Oktober 2026 |
| **Penulis** | Kirana (Product Designer) |
| **Status** | Draft untuk direview bos → eksekusi oleh Bima (Full-Stack Dev) |
| **Repo** | github.com/mahirdevz-lab/kebuni-backoffice (private) — folder `C:\xampp-php-82\htdocs\system` |
| **Lingkup** | **Upgrade tampilan (UI/UX) backoffice MIS yang sudah jalan** — bukan rewrite aplikasi, bukan fitur baru |
| **Dokumen terkait** | `docs/PRD.md` (PRD inti + frontend toko online — tidak diubah dokumen ini), `docs/IMPROVE-PAGINATION-BACKOFFICE.md` (audit pagination Bima — jadi dasar quick win) |
| **Stack saat ini** | CodeIgniter 4.7 · Bootstrap 5.3.3 · jQuery 3.7.1 · Font Awesome 6.5.2 · Chart.js 4.4.9 · font Inter · server-render + partial `_table.php` via AJAX |

**Daftar Isi**

1. Latar Belakang & Masalah Tampilan Saat Ini
2. Tujuan & Ukuran Keberhasilan
3. Prinsip Desain & Design System
4. Redesign per Area Prioritas
5. Alur Pengguna Utama (Before → After)
6. Spesifikasi Responsif & Aksesibilitas
7. Rencana Implementasi Bertahap + Kriteria Penerimaan
8. Risiko & Hal yang TIDAK Diubah
9. Quick Win (bug dari audit pagination)
10. Open Question untuk Bos

---

## 1. Latar Belakang & Masalah Tampilan Saat Ini

Backoffice Kebuni (MIS) sudah berfungsi penuh dan dipakai harian untuk transaksi. Masalahnya **bukan fitur, tapi konsistensi dan kenyamanan tampilan**. Semua temuan di bawah ini diidentifikasi langsung dari kode view (`app/Views`) dan dari `docs/IMPROVE-PAGINATION-BACKOFFICE.md` — tidak ada yang dikarang.

### 1.1 Dua generasi gaya UI hidup berdampingan

Terdeteksi dua gaya kartu/header yang berbeda di aplikasi yang sama:

| Gaya | Ciri di kode | Dipakai di |
|---|---|---|
| **Gaya baru (mayoritas)** | `.section-header` transparan + border bawah, kartu tanpa shadow tebal (app.css baris 188–199) | product, sales_order, destination, user, finance, dst. |
| **Gaya lama (legacy SB-Admin-like)** | `.card-header.bg-primary.text-white` (header biru penuh), `.card.shadow`, `.border-left-primary` | `taking_order/index.php`, `price_simulator/index.php` |

Konsekuensi: staf yang pindah dari Sales Order ke Taking Order/Price Simulator merasa seperti pindah aplikasi.

### 1.2 Komponen interaksi tidak konsisten

- **Notifikasi error**: `product`, `sales_order`, `destination` memakai `showToast()` (toast Bootstrap). `taking_order/index.php` dan `price_simulator/index.php` masih memakai `alert()` bawaan browser (10 panggilan `alert(` terdeteksi, baris 181/311/354/376/382 di kedua file) — memblokir layar dan tidak bisa di-style.
- **Ikon aksi tabel tidak seragam secara semantik**: edit = `btn-outline-warning` (kuning), recipe = `btn-outline-info` (biru), hapus = `btn-outline-danger` (merah) di `product/_table.php` — warna aksi tidak bermakna konsisten antar modul.
- **Ikon Font Awesome dua notasi**: `fa-solid fa-*` (baru) vs `fas fa-*` (lama) campur di taking_order/price_simulator.
- **Loading**: hanya satu mekanisme global (overlay layar penuh `#globalLoadingOverlay` + spinner) — untuk AJAX tabel kecil pun seluruh layar tertutup overlay `rgba(15,23,42,.25)`; tidak ada skeleton row atau spinner lokal di konteks tabel/tombol.
- **Fungsi format angka duplikat**: `formatRupiah()` global (app.js), `formatRp()` lokal (sales_order/form.php), `formatNumber()` lokal (taking_order, price_simulator) — tiga implementasi untuk hal yang sama.

### 1.3 Pagination & tabel daftar (dasar: audit IMPROVE-PAGINATION-BACKOFFICE.md)

Semua terkonfirmasi di audit statis Bima (commit `ec68ec9`):

- **BUG-1**: 4 modul tanpa pagination sama sekali (`DestinationController`, `PaymentTermController`, `PricingTierController`, `ProductCategoryController` memanggil `->all()` — 1 SELECT tanpa LIMIT, tabel dirender penuh).
- **BUG-2**: nomor urut `No` bisa meleset karena rumus `($pager->getCurrentPage()-1)*$pager->getPerPage()+1` sensitif terhadap perPage yang tidak konsisten (config 20, Service umumnya 10, Attendance 15, AuditLog 20).
- **BUG-3**: 4 modul yang sama tidak punya kolom pencarian (`#searchInput` 0 hasil di `destination`, `payment_term`, `pricing_tier`, `product_category`) — begitu datanya banyak, user tidak bisa cari.
- **BUG-4**: link pagination default CI4 tidak membawa query string filter (`search`, `status`, tanggal) — pindah halaman = filter hilang; link juga pakai URI segment sementara handler AJAX baca `?page=N`.
- **BUG-6**: template `default_full` bawaan CI4 tidak punya tombol «First»/«Last» — menyulitkan modul berhalaman banyak (audit log, sales order).
- **BUG-7**: tidak ada teks "Menampilkan X–Y dari Z data" di salah satu dari 17+ list.
- **Visual**: CSS `app.css` tidak punya satu pun aturan `.pagination` (grep 0 hasil) — pagination tampil gaya default Bootstrap (kotak putih border abu), tidak match kartu/kontrol lain.

### 1.4 Detail kecil yang terasa "bukan buatan satu tim"

- **Tanggal berbahasa Inggris**: `dashboard/index.php` baris 16 memakai `date('l, d F Y')` → tampil "Monday, 05 October 2026" di aplikasi berbahasa Indonesia; tabel juga memakai `date('d M Y')` (bulan Inggris: "Oct").
- **Empty state ada tapi polos**: semua list memakai `.empty-state` (ikon + satu kalimat) — belum ada CTA ("Tambah data pertama") dan tidak dibedakan "belum ada data" vs "hasil filter kosong".
- **Judul browser generik**: "Back Office Management System" — tidak menyebut Kebuni.
- **`partials/pagination.php` tidak terpakai konsisten**: ia hanya membungkus `$pager->links()`, sementara 17 `_table.php` memanggil `$pager->links()` langsung — partial ini nyaris mati.
- **Sidebar 30 item dalam 7 grup collapsible**: semua grup default terlipat kecuali yang aktif; tidak ada pencarian menu, tidak ada pintasan ke aksi harian (Taking Order/Sales Order harus buka grup "Pricing & Order" dulu).
- **Emoji 👋** di sapaan dashboard — selera, tapi perlu diputuskan sekali di design system.

### 1.5 Yang sudah BAIK dan wajib dipertahankan (fondasi upgrade)

Supaya adil terhadap kondisi sekarang — pondasi sudah cukup modern:

- Design token CSS (`:root` variables) + dark mode penuh via `data-bs-theme` (tersimpan di localStorage).
- Layout shell modern: sidebar gelap sticky, topbar sticky, notifikasi polling tiap 120 detik, user dropdown.
- Pola CRUD AJAX yang benar di modul-modul baru (partial `_table.php` + `loadTable()` + debounce 400 ms + invalid-feedback per field).
- Helper global rapi: `showToast`, `confirmAction`, `ajaxPost`, `showLoading` (app.js).
- Print stylesheet untuk sales order (`@media print`).

**Kesimpulan masalah**: ini bukan masalah "tampilan jelek", tapi masalah **konsistensi antar modul** (2 generasi gaya, 3 format angka, 2 mekanisme notifikasi), **kelengkapan komponen data-dense** (pagination, info baris, state), dan **detail bahasa/lokal**. Upgrade = menyatukan ke satu design system, bukan mengganti kerangka.

---

## 2. Tujuan & Ukuran Keberhasilan

### Tujuan

1. Satu bahasa visual tunggal di seluruh 26 modul backoffice — staf tidak perlu "belajar ulang" tiap pindah menu.
2. Input transaksi harian (sales order, taking order, produk) lebih cepat dan lebih tahan salah.
3. Data-dense (tabel panjang, audit log) nyaman dinavigasi: cari, filter, pindah halaman tanpa kehilangan konteks.
4. Tampilan 100% berbahasa Indonesia yang konsisten (termasuk format tanggal & angka).

### Ukuran keberhasilan (terukur)

| ID | Metrik | Baseline (dari kode/audit) | Target | Cara ukur |
|---|---|---|---|---|
| M-1 | Waktu input 1 sales order 3 item (uji tugas staf) | diukur dulu sebelum upgrade (catat di fase 0) | turun ≥ 25% | stopwatch / screen record, 5 sesi |
| M-2 | Jumlah klik dari login → form Taking Order baru | saat ini: buka grup "Pricing & Order" → klik item = ≥ 3 klik + scroll | ≤ 2 klik (pintasan topbar) | hitung manual |
| M-3 | Error rate input (submit gagal validasi / data salah) | diukur dulu (fase 0) | turun ≥ 30% | log audit_log + pengamatan |
| M-4 | Konsistensi komponen | 2 gaya header, 2 mekanisme notifikasi, 3 fungsi format angka, 10 `alert()` | 1 gaya header, 1 mekanisme toast, 1 fungsi format, 0 `alert()` | grep kode (otomatis) |
| M-5 | Kelengkapan tabel daftar | 4 modul tanpa pagination+search; 0 dari 17 list punya info baris; 0 punya First/Last | 17+ list: pagination + search + info "X–Y dari Z" + First/Last | cek per modul |
| M-6 | Filter tersimpan saat pindah halaman | BUG-4: filter hilang | filter terbawa di semua modul berfilter | smoke test manual |
| M-7 | Bahasa antarmuka | tanggal & bulan Inggris di dashboard/tabel | 100% format Indonesia (`Intl`/`locale id`) | cek visual |

> Baseline M-1 dan M-3 wajib dicatat **sebelum** tahap pertama dikerjakan (5 menit per skenario, cukup sekali).

---

## 3. Prinsip Desain & Design System

### 3.1 Prinsip

1. **Satu sumber komponen.** Semua halaman memakai komponen dari design system ini; dilarang menambah gaya lokal baru (mis. header biru legacy).
2. **Server-render dulu, AJAX seperlunya.** Pertahankan pola CI4 view + partial `_table.php`; tidak memperkenalkan framework frontend baru (tetap Bootstrap 5 + jQuery).
3. **Desktop-first, data-dense.** Pemakai utama admin/staf di laptop/PC lebar; tablet adalah fallback, bukan target utama.
4. **Jangan pindahkan kerja ke user.** Setiap field yang bisa diisi otomatis (harga dari Price Period, dst.) tidak dibuat manual.
5. **State harus eksplisit.** Setiap tabel/area data punya 4 state: loading, empty (belum ada data), empty-filter (hasil pencarian kosong), error.
6. **Bahasa Indonesia penuh.** Label, tanggal, angka, pesan error — satu lokal (`id-ID`).

### 3.2 Design tokens (memperluas `:root` yang sudah ada di app.css)

Warna yang SUDAH terpasang dipertahankan sebagai dasar (bukan rebrand — lihat Open Question OQ-1):

| Token | Nilai kini | Peran |
|---|---|---|
| `--bo-primary` | `#4f46e5` (indigo) | aksen utama: tombol aksi, item menu aktif, link aktif pagination |
| `--bo-success` | `#16a34a` | status sukses/aktif, angka profit positif |
| `--bo-danger` | `#dc2626` | aksi merusak, profit negatif, badge overdue |
| `--bo-warning` | `#f59e0b` | peringatan, status menunggu |
| `--bo-info` (BARU) | `#0ea5e9` | status informasi/proses (badge Submitted/Processing) |
| `--bo-sidebar-*` | `#0f172a` family | sidebar gelap (tetap) |
| `--bo-radius` | `.75rem` | radius kartu; kontrol `.55rem` (tetap) |

Aturan warna tambahan:

- **Warna aksi tabel diganti semantik**: Lihat/detail = `btn-outline-secondary`, Edit = `btn-outline-primary`, Hapus = `btn-outline-danger`. Ikon-only button **wajib** punya `title` + `aria-label` (sudah ada sebagian — wajibkan semua).
- **Satu hijau saja**: `--bo-success #16a34a` untuk status sukses. Catatan: audit pagination menulis aksen pagination hijau Kebuni; secara design system yang benar adalah **indigo `--bo-primary`** untuk item aktif pagination (match tombol & menu aktif) — hijau tetap khusus status sukses. (Dikunci di sini supaya Bima tidak bingung; bila bos memilih rebrand hijau, tinggal ganti nilai `--bo-primary`.)

Tipografi:

- Tetap **Inter** (sudah di-load). Skala: judul halaman 1.35rem/700 (`.page-title`, sudah ada) · judul kartu .92rem/600 (`.section-header`) · isi tabel .865rem · label form .82rem/500 · helper/caption .78rem.
- Angka kolom uang/qty memakai `font-variant-numeric: tabular-nums` (BARU, class `.num`) agar kolom Rp sejajar.

Spacing & grid: tetap skala Bootstrap 4/8 (`g-3` antar kolom, `.content-body` padding 1.5rem, max-width 1600px — sudah ada). Tidak diubah.

### 3.3 Komponen inti

**a) Kartu & header** — SATU gaya: `.section-header` transparan (gaya baru). Header biru `.card-header.bg-primary.text-white` di taking_order & price_simulator **dihapus**, diganti section-header. Card legacy (`shadow`, `border-left-primary`) diganti `.card` standar.

**b) Toolbar list (pola wajib semua modul daftar)**

```
[ Card ]
  .section-header:  (ikon) Daftar X          [ + Tambah X ]  ← CTA di kanan header
  .card-body:
    .row.g-2:  [search #searchInput (debounce 400ms)] [filter 1] [filter 2] [reset]
    #tableContainer → partial _table.php
```

**c) Tabel** — pola `product/_table.php` sebagai standar: `.table.table-hover.align-middle` di dalam `.table-responsive`, thead uppercase kecil (sudah ada), kolom `No` (nomor berkelanjutan antar halaman — fix BUG-2), badge status, kolom aksi width tetap. Nama/code sebagai badge netral `.badge.bg-light.border` (pola sudah ada — standarkan).

**d) Badge status** — satu peta warna kanonik, didefinisikan SEKALI di `app/Views/partials/status_badge.php` (partial helper BARU) supaya tidak diduplikasi seperti sekarang di `sales_order/_table.php` dan `dashboard/index.php`:

| Status | Warna |
|---|---|
| Draft | `bg-secondary` |
| Submitted / Menunggu | `bg-info text-dark` |
| Approved | `bg-primary` |
| Processing | `bg-warning text-dark` |
| Completed / Aktif | `bg-success` |
| Cancelled / Nonaktif / Overdue | `bg-danger` |

**e) Pagination (komponen baru, dari audit §3)** — template kustom `app/Views/pager/kebuni_full.php`:

- «First» · Prev · angka (±2, ellipsis) · Next · «Last».
- Item aktif: latar `--bo-primary`, teks putih, radius .5rem; non-aktif redup.
- Link selalu `?page=N` (query string) → `usePageQueryString = true` di `app/Config/Pager.php` sehingga filter ikut terbawa (fix BUG-4, BUG-6).
- Footer tabel wajib pola (fix BUG-7): kiri `.pager-info` "Menampilkan 1–10 dari 57 data", kanan links — wrapper `d-flex justify-content-between align-items-center mt-3` (mengganti `justify-content-end` yang sekarang).

**f) Toast/notifikasi** — satu-satunya mekanisme: `showToast(msg, type)` yang sudah ada (success/error/warning/info, auto-hide 4 detik, container kanan-atas). **Semua `alert()` dihapus** dari taking_order & price_simulator, diganti showToast. Pesan error validasi tetap inline `invalid-feedback` per field (pola product sudah benar).

**g) Modal** — konfirmasi pakai `confirmAction()` + `#modalConfirm` yang sudah ada. Modal kerja (recipe dsb.) tetap Bootstrap modal radius token. Modal simpan tidak boleh ditutup saat request berjalan (tambah `data-bs-backdrop="static"` saat submit).

**h) State (wajib di semua area data):**

| State | Tampilan |
|---|---|
| Loading | request tabel: skeleton 3 baris abu di `#tableContainer` (BARU, ganti overlay layar penuh untuk refresh tabel); aksi simpan: tetap overlay global + tombol `disabled` + spinner di dalam tombol |
| Empty (belum ada data) | `.empty-state` + ikon + "Belum ada data X." + tombol CTA "Tambah X" |
| Empty filter | `.empty-state` + "Tidak ada hasil untuk pencarian '…'." + tombol "Hapus filter" |
| Error | toast error + baris tabel error inline (`table-danger`) — pola yang sudah ada di sales_order/form dipertahankan |

**i) Format & ikon** — helper global tunggal `fmtRp()` / `fmtNum()` / `fmtDate()` (id-ID, `Intl.NumberFormat`/`Intl.DateTimeFormat`) di `app.js`; hapus `formatRp`/`formatNumber`/`formatRp` lokal. Ikon Font Awesome notasi `fa-solid fa-*` saja (ubah `fas` → `fa-solid`).

---

## 4. Redesign per Area Prioritas

### 4a. Shell / Navigasi & Layout Global (layouts + partials)

Yang berubah:

1. **Pintasan aksi harian di topbar** (BARU): tombol "+ Taking Order" di sebelah kiri grup ikon topbar — memotong M-2 jadi ≤ 2 klik dari mana saja. Label berubah sesuai permission user.
2. **Judul browser & brand**: title jadi "Kebuni Back Office — {Halaman}"; sidebar-brand memakai `company_name` dari SettingModel (sudah begini) + favicon Kebuni (OQ-2).
3. **Breadcrumb**: pertahankan; tambah badge status halaman (mis. "Draft") bila konteks ada.
4. **Sidebar**: struktur 7 grup dipertahankan (jangan dirombak — staf sudah hafal). Penambahan kecil: (a) item aktif diberi indikator bar kiri 3px `--bo-primary`; (b) grup berbadge jumlah (mis. "Pricing & Order (5)"); (c) shortcut keyboard tidak dilakukan di fase ini (out of scope).
5. **Loading overlay**: dipertahankan untuk submit form, tapi refresh tabel AJAX pindah ke **skeleton in-place** (3 baris) supaya layout tidak lompat dan user tetap lihat header tabel.
6. **`partials/pagination.php`** dijadikan satu-satunya cara render footer pager (dengan info baris), dipakai semua `_table.php` — bukan lagi tiap file memanggil `$pager->links()` langsung.
7. Dark mode & toggle tema: **tidak diubah** (sudah bagus). Chart.js sudah re-render saat ganti tema — dipertahankan.

### 4b. Dashboard

Kondisi sekarang sudah 80% sesuai design system (kpi-card, section-header, chart). Perubahan:

1. Tanggal sapaan → format Indonesia ("Senin, 05 Oktober 2026") via `Intl.DateTimeFormat('id-ID', ...)` — fix temuan 1.4.
2. KPI finance (kas masuk/keluar/piutang/hutang) diberi ikon `.kpi-icon` seperti 4 KPI atasnya (sekarang 4 KPI bawah tanpa ikon — asimetri terlihat di kode baris 77–94).
3. Kartu "Alur Sales Order": badge kosong `&nbsp;` diganti badge berisi label singkat + count; tiap baris tetap link ke list terfilter.
4. List "Menunggu Approval", "Top Customer", "Aktivitas Terbaru": tambah link "Lihat semua →" di section-header (sekarang list mentok tanpa lanjutan).
5. Empty state dashboard diberi varian ramah (mis. "Tidak ada PO menunggu — semua beres ✓" sudah ada, standarkan ke komponen 3.3h).
6. Emoji 👋: diputuskan sekali — **dipertahankan** (ramah, tidak mengganggu). Tercatat di design system agar tidak diragukan lagi.

### 4c. Modul Transaksi Harian (paling sering dipakai staf)

**c.1 Sales Order — index + `_table.php`**

- Footer pager baru + info baris + First/Last (fix BUG-6, BUG-7).
- Filter tanggal dari/sampai diberi label visual "s/d" di antaranya (sekarang dua input date telanjang).
- Kolom aksi: ganti tombol arrow `btn-outline-primary` → link teks "Detail" + ikon (target sentuh lebih jelas).
- Badge status dari partial kanonik (3.3d).

**c.2 Sales Order — form (form.php)**

Form ini sudah paling dekat dengan target (toast, kpi summary, labour dinamis). Perbaikan UX:

1. **Urutan fokus**: Tanggal → Customer → Price Period → Payment Term → items. Tambah `autofocus` + Enter berpindah field wajar (standar browser, tanpa JS tambahan).
2. **Validasi lebih awal**: tombol "Hitung Preview" disabled sampai Price Period + Payment Term terpilih (sekarang klik baru muncul toast error).
3. **Tabel items**: kolom Packing `multiple size=2` (tinggi 64px) sulit dipakai — ganti jadi **dropdown multi via checkbox** (dropdown Bootstrap berisi checklist, tampil sebagai chip "2 packing" di sel). Ini perubahan komponen terbesar di form.
4. **Subtotal per baris** update otomatis setelah "Hitung Preview" (sudah ada) — tambahkan indikator "belum dihitung" (teks redup) bila user mengubah qty setelah kalkulasi, mencegah kirim data basi.
5. Ringkasan 6 kpi-card hasil kalkulasi: pertahankan; angka profit negatif sudah merah — benar.
6. Peringatan "Di bawah rekomendasi" (harga jual < recommended): naikkan dari teks kecil merah menjadi badge `bg-danger` di sel Harga Jual.

**c.3 Taking Order (index.php — halaman simulasi/quotation)**

Ini halaman paling menyimpang dari design system. Redesign:

1. Header biru legacy → `.section-header`; kartu `shadow`/`border-left-primary` → `.card` standar.
2. Semua `alert()` → `showToast()`; loading spinner tombol dipertahankan tapi tambah overlay global konsisten.
3. Packing multiple `size=2` tinggi 108px → komponen dropdown-checklist yang sama dengan c.2(3).
4. Checkbox "Via Gudang Sukabumi?" → **switch** Bootstrap dengan tooltip penjelasan (teks title yang sudah ada).
5. Ringkasan simulasi (kartu kiri) → format kpi-card + badge margin (konsisten sales_order form).
6. Notasi ikon `fas` → `fa-solid`; hapus fungsi `formatNumber` lokal → helper global.
7. Struktur konten dipertahankan persis (3 seksi: parameter → items → hasil) — **jangan ubah urutan logika bisnis**.

**c.4 Product (index + _table)**

Sudah menjadi **pola acuan (golden pattern)** — hanya dirapikan:

1. Tombol aksi tabel diganti semantik warna (3.3a): recipe `outline-info` → `outline-secondary`, edit `outline-warning` → `outline-primary`.
2. Tambah kolom indikator punya packing recipe (ikon kecil `fa-box-open` solid vs redup) — staf langsung tahu produk mana yang belum di-set recipe.
3. Form edit di atas + daftar di bawah dipertahankan (sudah efektif); tambah highlight baris yang sedang diedit (class `table-active`).
4. Setelah simpan, `loadTable()` tanpa reload — sudah benar; jadikan acuan untuk destination dll. yang masih `window.location.reload()`.

### 4d. Modul lain — pola umum (tanpa redesign satu-satu)

Semua modul master/list (user, supplier, account, packing_material, transport_route, opex_*, price_period, pricing_tier, payment_term, destination, product_category, finance, report, audit_log, attendance, setting, profile, auth):

1. Ikuti toolbar list standar (3.3b) + footer pager standar (3.3e).
2. 4 modul tanpa pagination/search (destination, payment_term, pricing_tier, product_category) disamakan ke pola `product` — termasuk partial `_table.php` baru dan AJAX `loadTable` (ini juga fix BUG-1 & BUG-3 — lihat Bagian 9).
3. Modul yang setelah simpan masih `window.location.reload()` diganti refresh tabel AJAX saja (posisi scroll & filter tidak hilang).
4. Form dua kolom (form kiri, tabel kanan — pola destination) boleh tetap untuk master sederhana; form atas + tabel bawah (pola product) untuk master dengan banyak field. Konsistensi antar halaman yang penting, bukan seragam paksa.
5. Halaman auth (`layouts/auth.php`): sudah punya gradient + kartu; tambah brand Kebuni + format error inline. Prioritas rendah.

---

## 5. Alur Pengguna Utama (Before → After)

### 5.1 Input produk baru

**Before (dari kode product/index.php):**
1. Menu Products (buka grup Master Data) → halaman terbuka, form di atas.
2. Isi code, nama, kategori, unit, shrinkage → Simpan.
3. Bila gagal validasi server → toast error + field merah (sudah oke).
4. Bila sukses → toast + form reset + tabel reload (posisi scroll tabel kembali ke atas halaman 1).
5. Untuk set recipe: cari baris produk di tabel → klik tombol kotak BIRU (outline-info) → modal.
6. Untuk edit: klik tombol KUNING — beda makna warna tiap tombol harus dihafal.

**After:**
1. Dari mana pun: tombol "+ Products" tidak wajib — staf tetap lewat menu (produk tidak seabrek aksesnya), tapi warna aksi kini seragam: Edit = biru primary, Recipe = netral, Hapus = merah, semua dengan tooltip.
2. Saat mengisi form, baris produk yang diedit ter-highlight di tabel bawah — tidak ada kebingungan "data ini sudah tersimpan belum".
3. Simpan → toast sukses, tabel refresh in-place, filter & halaman aktif **tetap**.
4. Produk tanpa packing recipe tampil dengan ikon kotak redup + badge "belum ada recipe" — bisa difilter.
5. Modal recipe memakai static-backdrop saat menyimpan (tidak bisa tertutup tidak sengaja).

**Perubahan klik untuk skenario umum "tambah produk lalu set recipe":** dari ~9 interaksi dengan cari-cari tombol → tetap 9 interaksi tapi zero-kebingungan warna + tanpa reload (waktu turun, bukan jumlah langkah — target metrik M-1/M-3).

### 5.2 Input Sales Order

**Before (dari kode sales_order/form.php):**
1. Dashboard → grup "Pricing & Order" → Sales Order → tombol "Taking Order Baru" (≥ 3 klik).
2. Isi header. Pilih Price Period → tarif kuli termuat.
3. Tambah baris item: pilih produk (dropdown panjang tanpa pencarian — cari produk = scroll), rute, **packing dengan multi-select kotak kecil 64px yang harus ctrl+klik**, qty, manual price.
4. Klik "Hitung Preview" → kalau Price Period/Payment Term belum dipilih → toast error (kehilangan satu klik + waktu).
5. Hasil muncul di bawah; ubah qty setelah hitung → subtotal per baris tidak berubah, tidak ada penanda datanya basi → risiko simpan dengan angka lama.
6. Simpan draft → redirect ke detail.

**After:**
1. **Topbar: tombol "+ Taking Order"** → form langsung dari mana saja (M-2: ≤ 2 klik).
2. "Hitung Preview" disabled + tooltip sampai syarat terpenuhi — tidak mungkin klik sia-sia.
3. Pemilihan produk: dropdown dengan **ketik-untuk-cari** (datalist/filter klien atas `productsData` yang sudah terkirim ke view — tanpa endpoint baru).
4. Packing: dropdown-checklist berchip ("Kardus 10kg, Plastik" sebagai 2 chip) — tidak lagi ctrl+klik di kotak 64px; di tablet pun bisa dipakai satu tangan.
5. Ubah qty/manual price setelah kalkulasi → subtotal baris kembali "—" redup + banner tipis "Ada perubahan — hitung ulang preview" di atas tombol simpan; simpan tetap boleh (draft), tapi peringatan tampil.
6. Harga jual di bawah rekomendasi → badge merah jelas di sel + ringkasan.
7. Simpan → redirect detail (tetap, sudah benar).

**Perubahan terukur:** klik masuk form 3+ → 1; satu sumber kegagalan (klik Hitung sia-sia) dihilangkan; risiko salah simpan angka basi ditutup.

---

## 6. Spesifikasi Responsif & Aksesibilitas

### 6.1 Responsif — desktop-first, fallback tablet

| Breakpoint | Perilaku |
|---|---|
| ≥ 1200px (utama) | sidebar 256px tetap; tabel penuh; form multi-kolom sesuai desain |
| 768–1199px (tablet/laptop kecil) | sidebar bisa di-collapse (toggle sudah ada); grid form turun ke kolom lebih sedikit (col-md sudah menangani sebagian besar) |
| ≤ 768px (fallback) | sidebar off-canvas + backdrop (sudah ada di app.css); semua tabel dalam `.table-responsive` (scroll horizontal) — **wajib** dipastikan di SEMUA tabel termasuk hasil kalkulasi taking_order; aksi tabel jadi ikon-only (teks disembunyikan `d-none d-md-inline`) |

Aturan tambahan:

- Form sales order & taking order di tablet: seksi item tabel boleh diubah jadi kartu-per-item bertumpuk (CSS only, `d-md-table` / `d-md-none` duplikat markup ringan) — diprioritaskan rendah, dikerjakan setelah desktop beres.
- Tidak ada target mobile-phone native — halaman absensi publik (`attendance-wrapper` max-width 460px) sudah menangani kasus ponsel dan **tidak disentuh**.
- Target uji: Chrome desktop (utama), tablet 768px (fallback smoke test). Tidak menambah dependensi.

### 6.2 Aksesibilitas (level dasar WCAG AA pragmatis)

1. **Kontras**: semua kombinasi token lolos AA 4.5:1 untuk teks; badge `bg-warning text-dark` dan `bg-info text-dark` dipertahankan justru karena alasan kontras. Verifikasi dark mode: teks muted `#94a3b8` di atas `#111a2e` lolos untuk teks sekunder.
2. **Fokus keyboard**: Bootstrap default `:focus-visible` dipertahankan; elemen interaktif kustom (dropdown-checklist packing, pagination) wajib bisa dioperasikan penuh via Tab/Enter.
3. **Label & nama**: setiap input punya `<label>` (sudah); setiap tombol ikon punya `title` + `aria-label` (sekarang hanya sebagian — dilengkapi serentak saat restyle tombol aksi).
4. **Loading**: spinner overlay punya `role="status"` + teks visually-hidden "Memuat..." (sudah ada — pertahankan di skeleton juga: `aria-busy="true"` pada container tabel saat memuat).
5. **Toast**: `role="alert"` (sudah ada dari komponen Bootstrap).
6. **Pagination**: bungkus `<nav aria-label="Navigasi halaman X">`.
7. Modal konfirmasi destruktif: tombol merah selalu di kanan + teks konsekuensi eksplisit (pola `confirmAction` sudah benar).
8. Jangan pakai warna sebagai satu-satunya penanda: profit negatif = merah **dan** tanda minus/paren dari format angka (sudah); status = warna badge **dan** teks label (sudah).

---

## 7. Rencana Implementasi Bertahap (untuk Bima) + Kriteria Penerimaan

Estimasi relatif: **S** = ≤ 1 sesi, **M** = 1–2 hari, **L** = ≥ 3 hari. Setiap tahap adalah satu commit/logika PR terpisah, bisa di-deploy mandiri, tidak ada tahap yang membuat aplikasi rusak bila berhenti di tengah.

### Tahap 0 — Baseline & persiapan (S)

Aktivitas: ukur baseline M-1 & M-2 & M-3 (uji tugas 5 menit bersama staf, catat di CHANGELOG); buat branch `ui-upgrade`.

**AC-0 (Given/When/Then):**
- Given baseline tercatat, When dokumen ini disetujui bos, Then tahap 1 boleh mulai.

### Tahap 1 — Fondasi design system & quick win pagination (M)

Aktivitas:
1. `app/Views/pager/kebuni_full.php` (BARU) + registrasi template + `usePageQueryString = true` + `$perPage = 10` (audit §3.1, §3.3, fix BUG-4/6).
2. Blok CSS pagination + `.pager-info` + `.num` (tabular-nums) di `app.css` (audit §3.2, dengan aksen `--bo-primary`).
3. Partial `partials/pager_footer.php` (BARU: info baris + links, fix BUG-7) + helper badge `partials/status_badge.php` (BARU).
4. Helper format global `fmtRp/fmtNum/fmtDate` di `app.js`.

**AC-1:**
- Given modul apa pun dengan `$pager->links()`, When template kustom aktif, Then pagination tampil gaya Kebuni (indigo aktif, First/Last ada) dan link berbentuk `?page=N`.
- Given user memfilter search + pindah halaman, When halaman berubah, Then filter tidak hilang (verifikasi manual per modul — suspect BUG-4).
- Given list 17 file `_table.php` dirombak ke `pager_footer`, Then setiap list menampilkan "Menampilkan X–Y dari Z data".
- Given halaman di-refresh dengan dark mode aktif, Then pagination & badge tetap benar di kedua tema.

### Tahap 2 — Bug pagination & konsolidasi perPage (M)

Aktivitas: BUG-1 + BUG-3 (4 modul dapat pagination + search, salin pola product), BUG-2 (numbering), BUG-5 (ganti paginate palsu di PriceSimulator/SalesOrderController jadi limit eksplisit), samakan perPage (audit §3.3, §4).

**AC-2:**
- Given tabel destination berisi > 10 baris, When halaman dibuka, Then hanya 10 baris + pager + search berfungsi (debounce 400 ms).
- Given AttendanceService & AuditLog, When daftar dirender, Then nomor `No` berurutan benar di setiap halaman (10 dan 20 per halaman masing-masing).
- Given dropdown PriceSimulator/SalesOrder, When dibuka, Then data lengkap ter-load tanpa melibatkan pager (grep: tidak ada `paginate(null ...` tersisa).

### Tahap 3 — Shell & komponen global (M)

Aktivitas: tombol pintasan "+ Taking Order" di topbar; skeleton loading tabel; `partials/pagination.php` dinaikkan jadi `pager_footer` resmi; title browser + favicon; timeline dashboard berbahasa Indonesia; KPI finance diberi ikon; "Lihat semua" di kartu dashboard; notasi ikon `fa-solid` seragam.

**AC-3:**
- Given user login dengan permission `price_period.view`, When topbar tampil, Then tombol pintasan terlihat dan 2 klik sampai form Taking Order.
- Given refresh tabel AJAX, When request berjalan, Then skeleton 3 baris tampil tanpa overlay layar penuh (overlay hanya untuk submit).
- Given dashboard dibuka kapan pun, Then hari/tanggal/bulan tampil Bahasa Indonesia.

### Tahap 4 — Modernisasi taking_order & price_simulator (M)

Aktivitas: ganti header legacy → section-header; semua `alert()` → `showToast`; komponen dropdown-checklist packing (dipakai kedua halaman + sales_order form); switch Via Sukabumi; ringkasan gaya kpi-card; hapus fungsi format lokal.

**AC-4:**
- Given kedua halaman dibuka, Then tidak ada `alert()` (grep 0), tidak ada `card-header.bg-primary` (grep 0), tidak ada fungsi `formatNumber` lokal (grep 0).
- Given user mengosongkan Price Period lalu klik Hitung, Then toast error tampil (bukan dialog browser).
- Given user memilih 2+ packing, Then chip terlihat di sel dan ikut terkirim benar (verifikasi payload sama seperti sebelumnya — regresi 0).

### Tahap 5 — Polish modul transaksi (sales_order form + product) (M)

Aktivitas: semua improvement c.2 & c.4 (validasi awal disabled button, indikator data basi, badge below-recommended, semantik warna aksi, highlight baris edit, indikator recipe, dropdown produk ketik-untuk-cari).

**AC-5:**
- Given Price Period/Payment Term belum dipilih, When form dibuka, Then tombol Hitung Preview disabled.
- Given kalkulasi sudah jalan lalu qty diubah, Then subtotal baris menandai "—" redup + banner "hitung ulang".
- Given produk di-edit, Then baris terkait di tabel mendapat `table-active`.
- Given seluruh alur 5.1 dan 5.2 dijalankan ulang, Then waktu ≤ baseline × 0.75 (metrik M-1) tanpa regresi fungsi.

### Tahap 6 — Rollout pola umum ke modul lain + QA menyeluruh (L)

Aktivitas: terapkan toolbar/pager standar ke seluruh modul sisanya; reload → refresh in-place; aksesibilitas pass (aria-label, nav pagination, focus); smoke test semua menu di `mis.kebuni.id` sesuai DoD audit; update CHANGELOG.

**AC-6:**
- Given setiap menu di sidebar, When dibuka satu per satu, Then tidak ada page blank, tidak ada error console, tabel memenuhi pola standar (checklist per modul di PR).
- Given audit pagination DoD (bagian 5 dokumen audit), Then semua checklist tercentang.
- Given pengujian keyboard (Tab saja), Then seluruh form product & sales order dapat diisi dan disimpan tanpa mouse.

### Urutan & ketergantungan

```
T0 → T1 → T2 → T3 → T4 → T5 → T6
      └────┴──── bila T1/T2 selesai, T4/T5 boleh paralel (beda file)
```

---

## 8. Risiko & Hal yang TIDAK Diubah

### Hal yang TIDAK diubah (garis merah)

1. **Alur kasir/MIS yang sudah jalan**: workflow status Sales Order (`draft → submit → approve → process → complete`), perhitungan COGS/tier/labour, harga dari Price Period — UI hanya mengganti kulit, bukan logika.
2. **Struktur menu sidebar** (7 grup, urutan item) — staf sudah hafal; hanya penambahan kecil.
3. **Arsitektur**: tetap CI4 server-render + jQuery + partial AJAX. Tidak ada React/Vue/build step.
4. **Dark mode, toast, confirm modal, helper app.js** yang sudah ada — dipertahankan, diperluas.
5. **Endpoint & kontrak AJAX**: semua URL, payload, dan respons tetap. Perubahan hanya di view/CSS/JS klien. (Pengecualian terkontrol: BUG-5 mengganti cara controller mem-fetch dropdown — perilaku output sama.)
6. **`docs/PRD.md`** dan aplikasi toko online yang direncanakan — dokumen ini tidak menyentuhnya; malah menyediakan design system yang bisa dipakai ulang frontend toko.
7. **Halaman absensi publik** (kamera/selfie) — jangan disentuh sama sekali.
8. **Print layout sales order** — dipertahankan; hanya dipastikan tidak rusak oleh perubahan CSS (regresi print = blocker).

### Risiko

| ID | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| R-1 | `usePageQueryString=true` mengubah bentuk URL halaman → bisa ganggu bookmark/link internal | sedang | audit route + verifikasi manual per modul (sudah dijadwalkan di AC-1); URL segment lama tetap valid untuk halaman 1 |
| R-2 | Mengganti multi-select packing berisiko salah payload (`packing_ids`) | tinggi — kena harga | komponen baru WAJIB mengirim payload identik; uji bandingkan payload lama vs baru (AC-4) |
| R-3 | Restyle menyentuh 20+ file view sekaligus → konflik dengan kerja Bima/Dimas lain di repo yang sama | sedang | urutan commit per tahap; koordinasi: kartu UI ini jalan setelah baseline pagination selesai; satu area = satu commit |
| R-4 | Pagination palsu (BUG-5) diubah → dropdown kehilangan data bila limit terlalu kecil | sedang | pakai `limit(500)` + status aktif sesuai audit; uji jumlah item dropdown sebelum/sesudah |
| R-5 | Skeleton loading menggantikan overlay → user mengira tabel kosong saat lambat | rendah | skeleton + `aria-busy` + minimal 200 ms tampil; overlay tetap untuk aksi simpan |
| R-6 | Perubahan warna aksi (kuning→biru untuk edit) membingungkan staf lama sesaat | rendah | sebut di CHANGELOG + tooltip; semua tombol tetap ada `title` |
| R-7 | Rebrand warna perusahaan dilakukan belakangan → token ganda | rendah | semua warna lewat token `--bo-*`; rebrand = ganti nilai token di satu tempat (lihat OQ-1) |

---

## 9. Quick Win (dari audit pagination — boleh langsung dieksekusi)

Item berikut adalah **bug terkonfirmasi** dari `docs/IMPROVE-PAGINATION-BACKOFFICE.md`, layak masuk sprint pertama karena kecil dan dampaknya langsung terasa:

1. BUG-4 + BUG-6 + BUG-7 + §3.1–3.2: template `kebuni_full.php`, CSS pagination, `usePageQueryString`, First/Last, info "Menampilkan X–Y dari Z". (← inti Tahap 1)
2. BUG-1 + BUG-3: 4 modul dapat pagination + search. (Tahap 2)
3. BUG-2: numbering `No` meleset. (Tahap 2)
4. BUG-5: hapus `paginate()` palsu untuk dropdown. (Tahap 2)
5. Ganti 10 `alert()` → toast (taking_order + price_simulator). (Tahap 4, < 1 jam kerja)
6. Tanggal dashboard & tabel → format Indonesia. (Tahap 3, < 1 jam)

---

## 10. Open Question untuk Bos

| ID | Pertanyaan | Default bila tidak dijawab |
|---|---|---|
| OQ-1 | **Warna brand.** Aksen aplikasi sekarang indigo `#4f46e5`; audit pagination menyebut "hijau Kebuni". Mau rebrand ke hijau (mis. `#16a34a`) sebagai warna utama, atau tetap indigo? | Tetap indigo (tidak ada kerja rebrand). Semua via token — ganti 1 nilai bila nanti diputuskan. |
| OQ-2 | **Logo/favicon Kebuni** — ada file logonya? Sekarang sidebar hanya ikon gedung generik dan judul "Back Office Management System". | Pakai teks "Kebuni Back Office" + favicon default. |
| OQ-3 | **Emoji 👋** di sapaan dashboard — dipertahankan atau dihapus? | Dipertahankan. |
| OQ-4 | Baseline metrik M-1/M-3: boleh minta 1 staf dialokasikan ±15 menit untuk uji tugas sebelum upgrade mulai? | Baseline dicatat dari estimasi Bima (kurang akurat, cukup untuk perbandingan kasar). |
| OQ-5 | Apakah pintasan "+ Taking Order" di topbar boleh tampil untuk semua role yang punya permission, atau khusus admin? | Semua yang punya permission (mengikuti aturan sidebar). |

---

*Disusun oleh Kirana — Product Designer, Kebuni Fresh Mart. Sumber: audit statis kode `app/Views` (5 Okt 2026) dan `docs/IMPROVE-PAGINATION-BACKOFFICE.md` (audit Bima, commit `ec68ec9`). Dokumen ini siap digabung/dieksekusi oleh Bima; tidak ada commit/push yang dilakukan dari kartu ini.*
