# IMPROVE STYLE PAGINATION — BACKOFFICE KEBUNI

Dokumen perbaikan style pagination + daftar bug/error pagination di backoffice (MIS).
Audit statis kode oleh Bima, 2026-10-04 (commit `ec68ec9`, repo `kebuni-backoffice`).

Stack: CodeIgniter 4.7 · Bootstrap 5.3.3 · jQuery · `app/Views` server-render + partial `_table.php` via AJAX (`$.get` → `$('#tableContainer').html(...)`).

---

## 1. Kondisi saat ini (audit)

### 1.1 Implementasi

| Komponen | Lokasi | Kondisi |
|---|---|---|
| Config pager | `app/Config/Pager.php` | `$perPage = 20`; **tidak ada template kustom** — masih pakai `default_full` CI4 |
| Template CI4 | `system/Pager/Views/default_full.php` | markup `<nav><ul class="pagination">` mentah, **tidak di-override** |
| CSS | `public/assets/css/app.css` | **TIDAK ADA** aturan `.pagination` / `.page-item` / `.page-link` (grep: 0 hasil) |
| Render link | 17 partial `_table.php` | `<?= $pager->links() ?>` (tanpa argumen template/group) |

Konsekuensi: pagination pakai style Bootstrap default (kotak putih, border abu) —
tidak match dengan kartu/kontrol lain di `app.css` (rounded, warna aksen, dsb).

### 1.2 PerPage tidak konsisten

| Sumber | Nilai | Catatan |
|---|---|---|
| `app/Config/Pager.php` → `$perPage` | 20 | hampir tidak pernah aktif (semua Service memaksa argumen) |
| 14 method Service | 10 | default parameter `int $perPage = 10` |
| `AttendanceService::paginate()` | 15 | satu-satunya penyimpang |
| `AuditLogController.php:17` | 20 | literal `->paginate(20)` |
| `PriceSimulatorController.php:37` | 100 | dropdown period |
| `SalesOrderController.php:67-68` | 1000, 200 | dropdown (harusnya `<select>` server-side / limit data) |

`$perPage = 20` di config praktis **dead setting** — hampir semua dipaksa 10.

### 1.3 Modul TANPA pagination (bug nyata, load semua baris)

Keempatnya memanggil `$this->service->all()` di controller → 1 SELECT tanpa LIMIT:

| Modul | Controller | View | Risiko |
|---|---|---|---|
| Destination | `DestinationController.php:25` `->all()` | `destination/index.php` | parah — tabel logistik bisa ratusan |
| Payment Term | `PaymentTermController.php:26` `->all()` | `payment_term/index.php` | sedang |
| Pricing Tier | `PricingTierController.php:26` `->all()` | `pricing_tier/index.php` | sedang |
| Product Category | `ProductCategoryController.php:26` `->all()` | `product_category/index.php` | sedang |

---

## 2. Bug & error pagination (prioritas diperbaiki)

> Status: `CONFIRMED` = sudah kubuktikan dari kode; `SUSPECT` = perlu repro di runtime/SQL.

### BUG-1 · 4 modul tidak punya pagination sama sekali — CONFIRMED
Lihat §1.3. Tabel dirender penuh (`foreach` semua baris). Untuk data master yang tumbuh
(destination, kategori produk), halaman bisa lambat & tidak ada navigasi halaman.
**Fix:** ganti `->all()` → `scopeList(...)->paginate(10)`, tambah `pager()` ke data view,
pasang `<?= $pager->links() ?>` di bawah tabel, dan samakan dengan pola `product/index.php`
(partial `_table.php` + `loadTable(page)` AJAX).

### BUG-2 · Numbering `No` tidak reset saat filter mengubah total — CONFIRMED
Pattern di hampir semua `_table.php`:
```php
<?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
```
Rumus ini benar **hanya jika `per_page` tetap**. `AttendanceService` punya `perPage=15`
dan `AuditLogController` memakai `20`, sementara config default 20 — di modul yang
me-render `links()` tanpa group eksplisit, `getPerPage()` bisa mengembalikan nilai dari
group `default` yang berbeda dari yang dipakai query → **nomor urut meleset**.
**Fix:** satu kanal perPage per modul (lihat §3.3) atau pakai `($pager->getCurrentPage()-1)*$rows_per_request+$i`.

### BUG-3 · 4 modul tanpa search/filter — CONFIRMED
`destination/index.php`, `payment_term/index.php`, `pricing_tier/index.php`,
`product_category/index.php` tidak punya `#searchInput` (0 hasil grep `fetch|axios|.ajax`
dan `#searchInput`). Begitu data > 1 halaman (setelah BUG-1 diperbaiki), user **tidak bisa
cari** dan hanya bisa klik halaman.
**Fix:** tambahkan `scopeList($search)` + input search AJAX `debounce 400ms → loadTable(1)`
(salin pola `product/index.php:165`).

### BUG-4 · Link pagination default CI4 tidak bawa query string filter — CONFIRMED
`system/Pager/Pager.php:276` hanya `addQuery(pageSelector, page)`; **filter (`search`,
`status`, `category_id`, dsb.) tidak ikut di-preserve** ke URL `?page=N`. `app/Config/Pager.php`
bahkan tidak punya property `usePageQueryString` (default `false` di CI4) → link pakai
**URI segment** (`admin/product/2`) sementara `loadTable()` AJAX pakai **query string**
(`?page=2`).
**Fix (kompatibel dengan AJAX-handler `product/index.php:169` yang membaca
`url.searchParams.get('page')`):**
```php
// app/Config/Pager.php
public array $templates = [
    'default_full'   => 'App\Views\pager\kebuni_full',   // override template
    'default_simple' => 'CodeIgniter\Pager\Views\default_simple',
    'default_head'   => 'CodeIgniter\Pager\Views\default_head',
];
public bool $usePageQueryString = true;   // link ?page=N, bukan /admin/{seg}/{page}
```
Setelah `usePageQueryString = true`, `$uri->addQuery()` akan meng-append ke query string
yang ada sehingga filter ikut terbawa. **Verifikasi manual wajib** per modul (suspect
interaksi: `loadTable` re-GET `admin/product` dengan `search`+`page`, link yang di-klik
dari partial — harus tetap menampilkan data terfilter).

### BUG-5 · `PriceSimulatorController` & `SalesOrderController` "memaksa" pagination untuk dropdown — CONFIRMED (kode)
```php
PriceSimulatorController.php:37  ->paginate(null, null, 100);
SalesOrderController.php:67      ->paginate(null, 'aktif', 1000);
SalesOrderController.php:68      ->paginate(null, 'PUBLISHED', 200);
```
Ini **bukan** pagination sungguhan (tidak ada `pager` di view) — hanya cara paksa `limit`.
**Fix:** ganti ke method `->dropdown()` / `->allActive()` yang eksplisit `->limit(500)->findAll()`
(+ status aktif/PUBLISHED), jangan melibatkan pager.

### BUG-6 · `default_full` CI4: no «First»/«Last», hanya ellipsis — CONFIRMED (framework)
Template `CodeIgniter\Pager\Views\default_full` hanya punya Prev/Next + angka ± beberapa.
Untuk audit log & sales order yang bisa punya puluhan halaman, navigasi lambat.
**Fix:** override template (lihat §3.1) dengan tombol «First»/«Last» + `?page=1`.
**Catatan:** pola `loadTable(url.searchParams.get('page') || 1)` di `product/index.php:172`
hanya mengirim **angka halaman**, tidak query string filter → konsisten dengan BUG-4
(ingat kirim `search` dsb. juga di `loadTable` — sudah benar di `product/index.php:144-147`,
salin ke modul lain).

### BUG-7 · Tidak ada indikator "menampilkan X–Y dari Z data" — CONFIRMED
Semua `_table.php` hanya `<?= $pager->links() ?>` tanpa info `showing`.
**Fix:** di template kustom tambahkan baris `Menampilkan {first}–{last} dari {total} data`.

---

## 3. Perbaikan style pagination (rekomendasi konkret)

### 3.1 Template kustom `app/Views/pager/kebuni_full.php`
- Kelas `pagination pagination-sm mb-0` (rapi di baris footer tabel).
- Tombol «First» `?page=1`, Prev, angka, Next, «Last».
- Item aktif: `bg-primary text-white border-primary` (match aksen `app.css`).
- Non-aktif: `disabled`, warna redup (`opacity-50`).
- Selalu `?page=N` (query string), agar cocok dengan handler AJAX `loadTable`.

Daftarkan di `app/Config/Pager.php`:
```php
'default_full' => 'App\Views\pager\kebuni_full',
```

### 3.2 CSS di `public/assets/css/app.css`
```css
/* ---- Pagination Kebuni ---- */
.pagination { gap: .25rem; }
.pagination .page-link {
    border: 1px solid var(--bs-border-color);
    border-radius: .5rem;
    min-width: 2.25rem;
    text-align: center;
    color: #495057;
    transition: background-color .15s ease, color .15s ease;
}
.pagination .page-link:hover { background-color: #f1f5f9; color: #14532d; }
.pagination .page-item.active .page-link {
    background-color: #16a34a; /* hijau Kebuni */
    border-color: #16a34a;
    color: #fff;
    box-shadow: 0 1px 2px rgba(22,163,74,.25);
}
.pagination .page-item.disabled .page-link { opacity: .5; }
.pagination .page-item.disabled .page-link:hover { background-color: transparent; }
/* Info baris: "Menampilkan 1–10 dari 57 data" */
.pager-info { font-size: .85rem; color: #6b7280; }
```

### 3.3 Konsolidasi perPage
- **Jadikan `app/Config/Pager.php::$perPage = 10`** sebagai nilai resmi (match mayoritas Service).
- Ubah `AttendanceService` `15` → `10`, `AuditLogController` `20` → **biarkan 20** (audit log
  butuh densitas tinggi — set eksplisit & sebutkan alasannya).
- Dropdown (PriceSimulator/SalesOrder): lihat BUG-5.

### 3.4 Seragamkan wrapper partial
17 file `_table.php` yang punya `<?= $pager->links() ?>` wajib pakai satu markup ini
(cek konsistensi: `d-flex justify-content-end mt-3` sudah dipakai di semua — pertahankan):
```php
<div class="d-flex justify-content-between align-items-center mt-3">
    <div class="pager-info">
        Menampilkan <?= $pager->getPerPage() * ($pager->getCurrentPage()-1) + 1 ?>–
        <?= min($pager->getPerPage() * $pager->getCurrentPage(), $totalRows ?? 0) ?> dari
        <?= $pager->getPageCount() * $pager->getPerPage() /* atau total eksak dari query */ ?> data
    </div>
    <?= $pager->links() ?>
</div>
```
> Pakai `$pager->hasMorePages()` / kirim variabel `totalRows` eksak dari Service
> (lebih akurat daripada `getPageCount()` saat halaman terakhir tidak penuh).

---

## 4. Penerapan bertahap (urutan dikerjakan)

1. **Perbaiki BUG-1 & BUG-3** (Destination, PaymentTerm, PricingTier, ProductCategory):
   tambah pagination + search — salin pola `product/index.php` + `product/_table.php`.
2. **Terapkan §3.1 + §3.2 + `usePageQueryString=true`** (BUG-4, BUG-6, BUG-7):
   template `kebuni_full.php` + CSS + `app/Config/Pager.php`.
3. **Verifikasi interaksi filter↔halaman** per modul (suspect BUG-4) — QA manual.
4. **BUG-2 & §3.3**: rapikan perPage & numbering (`No`), termasuk `AttendanceService`.
5. **BUG-5**: ganti `paginate()` dipaksa-limit → method dropdown eksplisit.

---

## 5. Definition of Done

- [ ] 4 modul §1.3 punya pagination + search (BUG-1, BUG-3).
- [ ] `?page=N` link, filter tidak hilang saat pindah halaman (BUG-4, verifikasi manual).
- [ ] Template `kebuni_full.php` aktif: «First»/«Last», style hijau Kebuni (§3.1–3.2).
- [ ] Tampil "Menampilkan X–Y dari Z data" di semua 17+ list (BUG-7).
- [ ] `No` baris selalu `1..N` di setiap halaman, di semua modul (BUG-2).
- [ ] Dropdown PriceSimulator/SalesOrder tidak lagi `paginate()` palsu (BUG-5).
- [ ] Semua perubahan lolos smoke-test: buka tiap menu di `https://mis.kebuni.id`,
      klik halaman 2, filter, lalu halaman lain — data tidak reset/lepas filter.

---

## 6. Referensi file yang perlu disentuh

| File | Aksi |
|---|---|
| `app/Config/Pager.php` | override template + `usePageQueryString` + `perPage = 10` |
| `public/assets/css/app.css` | tambah blok `/* ---- Pagination Kebuni ---- */` |
| `app/Views/pager/kebuni_full.php` | **BARU** — template kustom |
| `app/Views/{destination,payment_term,pricing_tier,product_category}/index.php` | tambah search + `tableContainer` AJAX |
| `app/Views/{destination,payment_term,pricing_tier,product_category}/_table.php` | **BARU** — partial tabel + pager |
| `app/Controllers/{Destination,PaymentTerm,PricingTier,ProductCategory}Controller.php` | `->all()` → `paginate()` + `pager()` |
| `app/Services/*Service.php` | samakan `perPage`, tambah `totalRows()` untuk info baris |
| `app/Views/*/_table.php` (17 file) | seragamkan wrapper + info baris |
| `app/Controllers/AuditLogController.php` | `paginate(20)` — dokumentasikan alasan |
| `app/Controllers/PriceSimulatorController.php`, `SalesOrderController.php` | ganti `paginate()` palsu → `->limit()` eksplisit |

---

*Dokumen ini melengkapi task Git & versioning (repo `kebuni-backoffice`). Eksekusi perbaikan
kode = task terpisah (Bima/Dimas), jangan dicampur dengan commit baseline.*
