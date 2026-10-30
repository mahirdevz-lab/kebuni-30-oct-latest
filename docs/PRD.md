# PRD — Toko Online Kebuni Fresh Mart

| | |
|---|---|
| **Judul** | Product Requirements Document — Toko Online Kebuni Fresh Mart |
| **Versi** | 1.0 |
| **Tanggal** | 4 Oktober 2026 |
| **Penulis** | Bima (Full-Stack Developer / Tech Lead) & Dimas (Frontend Developer & QA) |
| **Status** | **DRAFT — menunggu persetujuan bos** |
| **Repo** | github.com/mahirdevz-lab/kebuni-backoffice (private) — backoffice CodeIgniter 4 yang sudah jalan |
| **Sumber** | Digabung dari `PRD-INTI.md` (Bima) + `PRD-FRONTEND.md` (Dimas) |
| **Lingkup** | Proyek **toko online Kebuni** yang terhubung ke backoffice yang sudah ada — bukan proyek baru terpisah. |

**Struktur dokumen:**

- **Bagian I — PRD Inti (Bima):** latar belakang, tujuan, lingkup, FR-01..FR-34, NFR-01..NFR-12, arsitektur & kontrak API, model data & alur, milestone F0–F4, risiko.
- **Bagian II — Frontend, UX & QA (Dimas):** visi UX, peta halaman, UX-01..UX-12, standar responsif/aksesibilitas, design system, alur checkout + edge case E1–E9, kriteria QA (AC), rencana testing, kontrak data frontend↔backend.
- **Lampiran:** peta silang ID antar bagian, rekonsiliasi/koreksi saat penggabungan, keputusan terbuka yang memengaruhi frontend.
- **Pertanyaan untuk Bos:** gabungan kedua dokumen (Q1–Q12), siap dikirim.

## Daftar Isi

**Bagian I — PRD Inti (Bima)**

1. Latar Belakang & Masalah yang Diselesaikan
2. Tujuan & Sasaran Bisnis (Terukur) — G-01..G-06
3. Lingkup (Scope) — in-scope / out-of-scope
4. Pengguna & Peran
5. Kebutuhan Fungsional (FR-01..FR-34) — prioritas MoSCoW
6. Kebutuhan Non-Fungsional (NFR-01..NFR-12)
7. Arsitektur & Integrasi (termasuk kontrak API internal + kesepakatan format)
8. Model Data & Alur Utama (termasuk daftar status pesanan kanonik)
9. Milestone & Estimasi Fase (F0–F4)
10. Risiko & Ketergantungan (R-01..R-09)

**Bagian II — Frontend, UX & QA (Dimas)**

11. Visi Pengalaman Pengguna & Prinsip Desain
12. Peta Halaman & Navigasi
13. Kebutuhan Fungsional Frontend (UX-01..UX-12)
14. Standar Responsif, Performa (CWV) & Aksesibilitas
15. Design System Minimal
16. Alur Checkout Lengkap (happy path + edge case E1–E9)
17. Kriteria Penerimaan (Given/When/Then) — siap dipakai QA
18. Rencana Testing & Daftar Bug yang Dicegah
19. Kontrak Data Frontend ↔ Backend

**Lampiran**

- Lampiran A — Peta silang ID (FR/NFR ↔ UX ↔ AC ↔ edge case)
- Lampiran B — Rekonsiliasi & koreksi saat penggabungan
- Lampiran C — Keputusan terbuka yang memengaruhi frontend

**Penutup**

- Pertanyaan untuk Bos (Q1–Q12, gabungan kedua dokumen)

---

---

# Bagian I — PRD Inti (Bima)

## 1. Latar Belakang & Masalah yang Diselesaikan

Kebuni Fresh Mart menjual sayur & buah segar (harga berubah tiap hari, stok cepat rusak/layu). Saat ini:

1. **Tidak ada kanal jual online.** Transaksi lewat WhatsApp/manual; pesanan dicatat ulang tangan di backoffice (MIS `mis.kebuni.id`), rawan salah ketik dan dobel.
2. **Harga harian tidak terpublikasi.** Backoffice sudah punya siklus **Price Period** (harga berlaku per periode + publish/close), tapi pelanggan tidak melihatnya — admin harus balas chat satu-satu menanyakan harga hari ini.
3. **Stok segar tidak real-time.** Sayur buah disusutkan oleh penyusutan alami (`shrinkage_type`/`shrinkage_value` sudah ada di master produk) — tanpa kanal online, stok "sisa hari ini" tidak pernah diketahui pembeli, berujung oversold atau barang layu tak terjual.
4. **Jam operasional terbatas.** Belanja segar paling ramai pagi sebelum jam buka; tanpa web, pesanan pagi harus menunggu admin online.
5. **Data pelanggan tidak terkumpul.** Tidak ada riwayat belanja per orang, padahal itu bahan promo & langganan (katering/warung) nantinya.

**Masalah inti yang diselesaikan proyek ini:** satu kanal pesan-otomatis untuk pelanggan yang membaca & menulis **satu sumber data yang sama** dengan backoffice — harga dari Price Period, stok dari gudang, pesanan masuk sebagai Sales Order.

## 2. Tujuan & Sasaran Bisnis (Terukur)

| ID | Tujuan | Ukuran keberhasilan | Target |
|----|--------|---------------------|--------|
| G-01 | Kanal jual online aktif | Web menampilkan katalog harga hari ini | Fase 1 (MVP), bulan ke-1 |
| G-02 | Kurangi kerja manual catat pesanan | % pesanan masuk otomatis sebagai Sales Order tanpa input ulang | ≥ 80% dalam 3 bulan setelah rilis |
| G-03 | Perluas jam order | Pesanan masuk di luar jam operasional | ≥ 30% total pesanan |
| G-04 | Turunnya barang rusak/layu tak terjual | % susut (waste) penjualan harian | turun ≥ 15% dalam 3 bulan (dasar: laporan stok/backoffice sekarang) |
| G-05 | Pendapatan dari kanal baru | GMV toko online per bulan | mulai tercatat bulan ke-2, naik bertahap |
| G-06 | Basis pelanggan terdaftar | akun pembeli aktif berulang (belanja ≥ 2x/bulan) | 100 akun dalam 3 bulan pertama |

> Angka baseline G-02..G-06 perlu data awal dari bos (dasar perbandingan) — masuk ke daftar pertanyaan terbuka.

## 3. Lingkup (Scope)

### In-scope (proyek toko online ini)

- Katalog produk segar (sayur/buah/bahan pokok) dengan **harga aktif per hari** dari backoffice.
- Keranjang + checkout pelanggan (akun pembeli).
- Pembuatan **Sales Order** otomatis dari pesanan web → antrian approve di backoffice (workflow `draft → submit → approve → process → complete` yang sudah ada).
- Pengurangan & pemotongan stok/penyusutan saat pesanan diproses.
- Pembayaran: minimal transfer manual + konfirmasi admin; slot integrasi payment gateway (lihat Q2).
- Pengiriman: jadwal & area antar (memakai master **Transport Route / Destination** yang sudah ada); slot COD (Q3).
- Notifikasi status pesanan (WhatsApp / email — lihat Q7).
- Halaman riwayat pesanan pembeli.
- Laporan ringkas toko online di backoffice (pesanan, penjualan, stok) memakai modul Report/Dashboard yang sudah ada.

### Out-of-scope (tidak dikerjakan di proyek ini)

- Perubahan total / rewrite backoffice MIS (fase ini hanya **menambah** API & modul pendukung).
- Aplikasi mobile native (web responsif dulu).
- Sistem pengadaan (purchase), akuntansi penuh, payroll — sudah di backoffice, tidak disentuh.
- Marketplace multi-penjual / jual produk pihak ketiga.
- Sistem langganan (subscription) sayur berkala — kandidat fase lanjutan.
- Integrasi kurir ojek online penuh (auto-dispatch) — fase lanjutan (Q4).
- Multi-cabang/multi-gudang (saat ini satu operasi).

## 4. Pengguna & Peran

| Peran | Siapa | Kebutuhan utama di proyek ini |
|-------|-------|-------------------------------|
| **Customer (pembeli)** | Pelanggan rumah tangga, warung, katering | Lihat harga hari ini, pesan, bayar, lacak pengiriman |
| **Admin Backoffice** | Owner/bos & admin MIS | Setujui pesanan masuk, atur harga (Price Period), stok, atur area/jadwal antar, laporan |
| **Staf Gudang/Picking** | Staf operasional | Lihat daftar pick per pesanan, catat stok terpakai/susut |
| **Staf Kurir** | Pengantar | Daftar antar hari ini, tandai terkirim |
| **Tech Lead (Bima)** | Developer | API integrasi, integrasi pembayaran, keamanan |
| **Frontend (Dimas)** | Developer | UI toko online (detail di Bagian II dokumen ini) |

> Backoffice sudah punya RBAC per izin (`role:product.view`, `role:sales.create`, dst. via filter route) — peran baru cukup **memakai izin yang ada**, tidak membangun sistem izin baru.

## 5. Kebutuhan Fungsional (FR) — prioritas MoSCoW

**Keterangan:** `M` Must (MVP), `S` Should, `C` Could, `W` Won't (fase ini).

### Katalog & harga

| ID | Kebutuhan | P |
|----|-----------|---|
| FR-01 | Katalog menampilkan produk aktif + **harga yang sedang berlaku** dari Price Period terbit di backoffice (sumber tunggal, bukan input ulang) | M |
| FR-02 | Kategori produk, pencarian, filter (sayur/buah/pokok, satuan kg/ikat/pcs) | M |
| FR-03 | Penanda stok: tersedia / menipis / habis hari ini — dari saldo stok backoffice | M |
| FR-04 | Foto produk, deskripsi, asal/sumber (lihat Q9) | S |
| FR-05 | **Harga berubah otomatis** mengikuti publish Price Period; harga lama tidak berlaku setelah periode ditutup | M |
| FR-06 | Penanda "menjelang tutup"/diskon barang siap layu untuk memangkas waste | C (Q5) |

### Keranjang & checkout

| ID | Kebutuhan | P |
|----|-----------|---|
| FR-07 | Keranjang belanja (sesi tamu) + simpan keranjang untuk akun login | M |
| FR-08 | Checkout wajib/pilih login akun pembeli (keputusan: Q6) | M |
| FR-09 | Pilihan alamat & slot pengiriman (area dari master Destination/Transport Route; kuota per slot dibatasi) | M |
| FR-10 | Validasi stok saat checkout: item melebihi stok tersedia ditolak/dikurangi, pesan jelas | M |
| FR-11 | Ringkasan ongkir per area/jarak | M |
| FR-12 | Kode promo/voucher sederhana (persen/nominal) | C |

### Pesanan & integrasi backoffice

| ID | Kebutuhan | P |
|----|-----------|---|
| FR-13 | Checkout menghasilkan **Sales Order** di backoffice (order_no, akun pelanggan, price_period_id, payment_term_id) lewat API internal | M |
| FR-14 | Status pesanan pelanggan mengikuti **daftar status kanonik** (lihat §8): `menunggu_bayar → dikonfirmasi → disiapkan → diantar → selesai`, plus `dibatalkan` — dipetakan ke workflow SO backoffice yang sudah ada | M |
| FR-15 | Admin approve/cancel pesanan dari backoffice (memakai tombol approve/process/complete yang sudah ada + antrean khusus pesanan web) | M |
| FR-16 | Riwayat pesanan pembeli (detail item, total, status, lampiran bukti bayar) | M |
| FR-17 | Batalkan pesanan sendiri oleh pembeli hanya sebelum status **disiapkan** (pemrosesan admin dimulai) | S |
| FR-18 | Catatan pesanan (contoh: "tomat matang, jangan terlalu matang") | S |

### Pembayaran

| ID | Kebutuhan | P |
|----|-----------|---|
| FR-19 | Metode bayar transfer bank manual + upload bukti, konfirmasi admin; pesanan tak dibayar lewat batas waktu otomatis dibatalkan + reserve stok dilepas (batas: Lampiran B butir 4; keputusan di Q2) | M (Q2 bisa menaikkan) |
| FR-20 | Integrasi payment gateway otomatis (QRIS/VA/e-wallet) — **tergantung keputusan Q2** | S/W |
| FR-21 | COD / bayar di tempat saat kurir antar — **tergantung keputusan Q3** | S/W |
| FR-22 | Notifikasi tagihan/pengingat bayar otomatis | S |

### Stok & penyusutan

| ID | Kebutuhan | P |
|----|-----------|---|
| FR-23 | Penahanan stok (reserve) saat pesanan dibuat; dilepas saat batal/selesai | M |
| FR-24 | Pemotongan stok nyata saat picking/diproses; **susut harian** (shrinkage) tercatat otomatis/sesuai aturan backoffice | M |
| FR-25 | Stok hanya diedit admin/gudang lewat backoffice — web hanya membaca | M |
| FR-26 | Penyesuaian stok manual (stock opname ringan) oleh admin | S |

### Pengiriman

| ID | Kebutuhan | P |
|----|-----------|---|
| FR-27 | Area & jadwal antar dikelola di backoffice (Transport Route), web menampilkan slot yang tersedia | M |
| FR-28 | Daftar antar harian per kurir + tombol "selesai diantar" | M |
| FR-29 | Lacak status pesanan real-time di halaman pembeli (polling sederhana) | S |
| FR-30 | Integrasi ojek online (GoSend/GrabExpress) untuk instan | W (Q4, fase lanjutan) |

### Laporan & notifikasi

| ID | Kebutuhan | P |
|----|-----------|---|
| FR-31 | Laporan penjualan toko online (hari/minggu/bulan, per kategori) di backoffice | M |
| FR-32 | Laporan stok & susut harian | M |
| FR-33 | Notifikasi WhatsApp/email saat status berubah (pembeli) & pesanan baru (admin) | S (Q7) |
| FR-34 | Dashboard ringkas: pesanan hari ini, omzet, item menipis | S |

## 6. Kebutuhan Non-Fungsional (NFR)

| ID | Kategori | Kebutuhan | Target |
|----|----------|-----------|--------|
| NFR-01 | Performa | Halaman katalog & checkout responsif | FCP & LCP ≤ 2,5 dtk pada jaringan 4G; INP ≤ 200 ms; CLS ≤ 0,1; API balas < 500 ms (p95 — sama dengan TTFB API ≤ 500 ms, Bagian II §14) |
| NFR-02 | Skalabilitas | Tahan lonjakan pagi | ≥ 100 pengunjung bersamaan tanpa error (di luar traffic flash-sale) |
| NFR-03 | Keamanan | Autentikasi & otorisasi | Password hashing, sesi aman, RBAC memakai izin backoffice yang ada; API internal wajib token/secret, tidak terbuka publik |
| NFR-04 | Keamanan | Proteksi umum | HTTPS wajib, anti SQL-injection/XSS (escaping + prepared statement CI4), rate-limit login & checkout, validasi upload bukti bayar (tipe+ukuran) |
| NFR-05 | Keamanan | Data sensitif | Data kartu/pembayaran tak disimpan di server (memakai halaman gateway); audit log memakai modul AuditLog yang ada |
| NFR-06 | Ketersediaan | Uptime | ≥ 99% di jam operasional (06.00–20.00); maintenance window di luar jam itu |
| NFR-07 | Responsif | Mobile-first | ≥ 60% trafik dari HP (komitmen) — asumsi perencanaan desain frontend > 80% (Bagian II §11); semua halaman jalan di layar 360px ke atas, Chrome/Safari terbaru |
| NFR-08 | Backup | Data | Backup DB otomatis harian (retensi ≥ 14 hari) + uji restore minimal bulanan |
| NFR-09 | Integritas data | Konsistensi harga/stok | Web tidak pernah menyimpan harga sendiri — baca dari Price Period; stok sumber tunggal di DB backoffice |
| NFR-10 | Keterlacakan | Audit | Setiap pesanan/status pembayaran tercatat siapa-kapan (aktivitas log sudah ada) |
| NFR-11 | Aksesibilitas | WCAG dasar | Kontras teks cukup, label form, bisa dinavigasi keyboard |
| NFR-12 | Observabilitas | Pemantauan | Log error terpusat + notifikasi ke admin saat API/gateway gagal |

## 7. Arsitektur & Integrasi

### Prinsip

**Satu aplikasi backoffice, satu database, dua wajah.** Toko online bukan sistem terpisah yang disinkronkan — ia *membaca dan menulis data yang sama* dengan MIS.

### Pilihan arsitektur (rekomendasi Bima)

```
                       ┌─────────────────────────────────────┐
 Pengguna (HP/desktop) │   TOKO ONLINE (front office)        │
 ────────────────────► │   kebuni.id                         │
                       │   - Halaman publik: katalog, harga  │
                       │   - Aplikasi web customer (SPA/MPA) │
                       └──────────────┬──────────────────────┘
                                      │  API internal (token/secret,
                                      │  HTTPS, rate-limit)
                       ┌──────────────▼──────────────────────┐
 Admin / Staf / Kurir  │   BACKOFFICE CodeIgniter 4          │
 ────────────────────► │   mis.kebuni.id  (repo yang sudah    │
                       │   ada: mahirdevz-lab/kebuni-backoffice)
                       │   - Modul lama utuh (RBAC, SO,      │
                       │     Price Period, Margin, Laporan)  │
                       │   - + modul baru: API toko online,  │
                       │     antrean pesanan web, picking    │
                       └──────────────┬──────────────────────┘
                                      │  mysqli (satu skema)
                       ┌──────────────▼──────────────────────┐
                       │   MySQL (DB tunggal)                │
                       │   products, price_periods,          │
                       │   sales_orders, stock, customers…   │
                       └─────────────────────────────────────┘
```

**Kenapa bukan microservice/DB terpisah:** stok segar & harga harian harus konsisten; sinkronisasi dua DB justru menambah jeda & titik gagal. Satu DB = konsistensi transaksional (reserve stok + buat SO dalam satu transaksi).

### Kontrak integrasi (API internal backoffice)

Endpoint di bawah `app/Controllers` backoffice, di grup route terpisah dengan filter autentikasi token (bukan sesi admin):

| Endpoint | Fungsi | Dipakai oleh |
|----------|--------|--------------|
| `GET /api/catalog/products?date=` | daftar produk aktif + harga periode aktif + sisa stok | web katalog |
| `GET /api/catalog/products/{code}` | detail 1 produk | web detail |
| `POST /api/orders` | buat pesanan → insert `sales_orders` + `sales_order_items`, reserve stok (transaksi) | checkout |
| `GET /api/orders/{no}` | status pesanan | halaman pembeli |
| `GET /api/customer/orders` | riwayat pesanan pelanggan | halaman riwayat |
| `POST /api/orders/{no}/cancel` | batal (bila masih draft/menunggu bayar) | pembeli |
| `GET /api/shipping/slots` | area + slot antar tersedia | checkout |
| `POST /api/payments/{no}/proof` | upload bukti bayar transfer | pembeli |
| *(webhook)* `POST /api/payments/callback` | callback payment gateway (Q2) | gateway |

#### Endpoint tambahan (dilengkapi saat penggabungan — permintaan frontend, Bagian II §19)

| Endpoint | Fungsi | Dipakai oleh |
|----------|--------|--------------|
| `POST /api/auth/request-otp` / `POST /api/auth/verify-otp` | daftar/login pembeli via no. HP + OTP (jalur password tetap disiapkan — Q6) | login/daftar |
| `GET/PUT /api/customer/profile` | profil pembeli | halaman akun |
| `GET/POST/PUT/DELETE /api/customer/addresses` (+ flag alamat utama) | CRUD alamat pembeli | akun & checkout |
| `GET /api/catalog/categories` | daftar kategori & sub-kategori ter-order | menu & filter |
| `POST /api/cart/validate` | validasi keranjang: kirim `[{produk_id, qty}]` → per item: `ok / stok_kurang(sisa) / habis / harga_berubah(harga_baru) / produk_nonaktif` | keranjang & pra-checkout |
| `GET /api/shipping/estimate` | input alamat/zona + waktu → slot tersedia, ongkir, jam cutoff, flag `dalam_zona` | checkout |
| `GET /api/shipping/zones` | daftar zona yang dilayani | halaman alamat |

Catatan pada `POST /api/orders`:

- Wajib menerima header **`Idempotency-Key`** dari frontend — pesanan tidak tercipta dobel saat klik ganda / retry (edge case E6, E7).
- `GET /api/orders/{no}` dipakai frontend sebagai **inquiry** setelah submit saat jaringan putus, sebelum mengizinkan submit ulang (E7).
- Harga dikunci pada saat pesanan dibuat; bila harga server berbeda dari yang ditampilkan, respons menyertakan harga baru dan pesanan hanya dibuat setelah konfirmasi eksplisit (E4).
- **Batas waktu bayar:** pesanan `menunggu_bayar` yang lewat batas (usulan 1 jam — dikunci di F0 lewat Q2) otomatis dibatalkan oleh backend dan reserve stok dilepas (E5).

#### Kesepakatan format & error (mengikat — diadopsi dari Bagian II §19.7)

1. **Format:** JSON REST; tanggal/waktu ISO 8601 dengan offset `+07:00` (Asia/Jakarta); uang integer rupiah (tanpa desimal); field snake_case.
2. **Paginasi standar:** `{data: [...], meta: {page, per_page, total}}` di semua endpoint list (konsisten dengan pagination backoffice yang sudah ada).
3. **Error standar:** kode machine-readable (`STOK_HABIS`, `HARGA_BERUBAH`, `DI_LUAR_ZONA`, dst.) + pesan Indonesia siap-tampil — frontend menampilkan pesan server apa adanya untuk error domain.
4. **Single source of truth:** status pesanan, stok, dan harga HANYA dari backend; frontend tidak pernah menghitung sendiri.
5. **Keamanan:** semua endpoint pembeli butuh sesi kecuali katalog & validasi keranjang guest; kredensial tidak pernah disimpan di localStorage. Token API internal (web↔backoffice) berbeda dari sesi login pembeli — dua mekanisme terpisah.

### Teknologi

| Lapisan | Pilihan | Alasan |
|---------|---------|--------|
| Backoffice & API | **PHP 8.2 + CodeIgniter 4** (sudah ada, XAMPP) | tanpa stack baru, tim sudah paham |
| Database | **MySQL/MariaDB** (satu skema, driver `MySQLi`) | konsistensi data tunggal |
| Frontend toko | pilihan Dimas — kandidat: Blade CI4 + Alpine/htmx, atau SPA ringan (Vite + Vue/React) di subdomain/`public/` terpisah | detail di Bagian II dokumen ini |
| Hosting | mengikuti hosting `kebuni.id` saat ini (Q8) | — |
| Pembayaran | manual transfer dulu; gateway opsional Midtrans/Xendit (QRIS) | Q2 |
| Notifikasi WA | Fonnte/API WA gateway (perlu keputusan Q7) | sudah dipakai di otomasi lain |
| Repo | tetap **kebuni-backoffice** (folder `app/` + `public/` web) atau `kebuni-web` terpisah — Q1 |

## 8. Model Data & Alur Utama

### Tabel yang sudah ada (backoffice — jangan diduplikasi)

| Tabel | Peran |
|-------|-------|
| `products` (+ `product_code`, `unit`, `shrinkage_type/value`) | master produk segar & aturan susut |
| `product_categories` | kategori |
| `price_periods` (+ `price_period_products`) | siklus harga; `publish`/`close` → sumber harga web |
| `pricing_tiers` | harga per tingkat pelanggan (mis. grosir/warung) |
| `sales_orders` (`order_no`, `price_period_id`, `payment_term_id`, `status`, `grand_total`, `total_cogs`, `estimated_profit`…) | **pesanan web masuk sebagai SO** |
| `sales_order_items` | item pesanan |
| `payment_terms` | termin bayar |
| `transport_routes` / `destinations` | area & jadwal antar |
| `packing_materials`, `product_packing_recipes` | kemasan per item |
| `users`, `roles`, `activity_log`, `audit` | akun staf, RBAC, audit |

### Tabel baru (fase proyek)

| Tabel | Peran |
|-------|-------|
| `customers` | akun pembeli (nama, HP, alamat, tier → `pricing_tier_id`) |
| `customer_addresses` | daftar alamat |
| `web_orders` (view/tabel ringan) | mapping pesanan web ↔ `sales_orders.id` + status pelanggan + slot antar + ongkir |
| `stock_movements` (bila belum ada) | mutasi stok: masuk, reserve, pick, susut |
| `payment_proofs` | bukti bayar transfer / info transaksi gateway |
| `api_tokens` (atau secret di config) | token autentikasi API internal web ↔ backoffice |

#### Status pesanan: satu daftar, dua tampilan

Satu daftar status kanonik dipakai di seluruh sistem (API, backoffice, dan label UI di Bagian II):

| Status (kanonik/API) | Label UI pembeli (Bagian II) | Pemetaan workflow SO backoffice |
|----------------------|------------------------------|--------------------------------|
| `menunggu_bayar` | Menunggu Pembayaran | draft/submit (menunggu konfirmasi bayar) |
| `dikonfirmasi` | Dikonfirmasi | approve |
| `disiapkan` | Disiapkan | process (picking gudang) |
| `diantar` | Diantar | process → pengiriman (kurir) |
| `selesai` | Selesai | complete |
| `dibatalkan` | Dibatalkan | cancel |

Aturan: pembeli boleh membatalkan sendiri hanya sebelum `disiapkan` (FR-17); setelah itu hanya admin (alur batal §8). Label UI SELALU dari server — frontend tidak menerjemahkan status sendiri (Bagian II §18.3 butir 11).

### Alur pesanan (utama)

```
Pilih barang → Keranjang → Checkout (login/tamu)
   │  validasi harga periode aktif + stok tersedia
   ▼
[API POST /orders] ──transaksi DB──► insert sales_orders (status: menunggu_bayar/draft)
   │                                 + sales_order_items
   │                                 + RESERVE stok (stock_movements: reserve)
   ▼
Pembeli bayar (transfer+upload bukti / gateway QRIS)
   ▼
Admin cek & APPROVE di backoffice (approve → process)
   ▼
Gudang PICK: stok reserve → terpakai; susut tercatat (shrinkage_value)
   ▼
Kurir ANTAR (daftar harian) → tandai selesai
   ▼
SO complete → laporan penjualan & margin ikut terhitung (modul lama)
```

**Batal:** belum diproses → stok reserve dilepas otomatis; setelah diproses → hanya admin yang boleh batal (konfirmasi ke pembeli).

### Alur stok (ringkas)

```
Stok tersedia = stok fisik − reserved
pesanan dibuat   : reserved ↑   (stok tersedia ↓, belum dipotong fisik)
picking/diproses : reserved ↓ + fisik ↓ (terpakai)
susut harian     : fisik ↓ otomatis per aturan shrinkage produk
pembatalan       : reserved ↓ (stok kembali tersedia)
```

Aturan susut & periode harga tetap dihitung **backoffice** — web tidak membangun logika harga/stok sendiri (NFR-09).

## 9. Milestone & Estimasi Fase

| Fase | Isi | Keluaran | Estimasi |
|------|-----|----------|----------|
| **F0 — Persiapan** | Keputusan Q1–Q12 dijawab bos, kontrak API final, struktur repo, desain DB baru (customers, web_orders, dll.) | API contract + skema DB | ~1 minggu |
| **F1 — MVP (Must)** | Katalog + harga dari Price Period, keranjang, checkout, buat SO + reserve stok, pembayaran transfer manual + konfirmasi admin, daftar antar, laporan dasar | Toko online bisa menerima pesanan sungguhan | 3–4 minggu |
| **F2 — Operasional** | Status notifikasi (WA/email), riwayat pesanan, lacak pengiriman, pick list gudang, laporan lengkap + susut | Operasi harian lancar tanpa catatan manual | 2–3 minggu |
| **F3 — Optimasi** | Payment gateway QRIS (bila Q2 = ya), diskon barang layu, promo, pricing tier grosir di web, dashboard admin | Waste ↓, konversi ↑ | 2–3 minggu |
| **F4 — Lanjutan (Won't now)** | App mobile PWA/native, langganan berkala, ojek online instan (Q4), multi-cabang | — | di luar komitmen |

**Total MVP (F0+F1): ± 4–5 minggu** dengan 2 developer (Bima backend/API, Dimas frontend) — asumsi keputusan bos cepat di F0.

## 10. Risiko & Ketergantungan

| ID | Risiko/Dampak | Kemungkinan | Mitigasi |
|----|---------------|-------------|----------|
| R-01 | **Keputusan bos (Q1–Q12) mundur** → desain berubah di tengah jalan | Tinggi | F0 khusus mengunci keputusan; PRD ini sudah menyiapkan opsi tiap pertanyaan |
| R-02 | Harga/stok tidak sinkron (oversold) saat lonjakan pagi | Sedang | Reserve stok dalam transaksi DB tunggal; baca harga dari Price Period saja; uji beban NFR-02 |
| R-03 | Web mengubah data backoffice & merusak laporan margin lama | Sedang | Web hanya menulis lewat API terstruktur (SO + stok), tidak UPDATE tabel bebas; QA regresion modul lama |
| R-04 | Susut/layu membuat stok terlihat tersedia padahal tak layak jual | Sedang | Aturan shrinkage dipakai ulang untuk "sisa layak jual"; FR-06 diskon akhir hari |
| R-05 | Integrasi payment gateway tertunda (akun merchant, verifikasi) | Sedang | F1 pakai transfer manual dulu; gateway di F3 |
| R-06 | Kode backoffice lokal `C:\xampp` jadi sumber kerja — risiko bentrok/kehilangan | Sedang | Semua kerja lewat git di repo kebuni-backoffice; branch per fase; jangan edit langsung di XAMPP |
| R-07 | Satu developer backend (Bima) jadi titik tunggal | Sedang | Dokumentasi API & kontrak di repo; knowledge transfer ke Dimas |
| R-08 | Performa hosting `kebuni.id` tidak sanggup dua aplikasi | Rendah | Cek kapasitas (Q8) di F0; pisah resource bila perlu |
| R-09 | Ketergantungan eksternal: domain & hosting aktif, WA gateway (Fonnte) hidup, akun merchant payment | Rendah | Fallback: notifikasi email dulu; bayar manual tanpa gateway tetap jalan |

# Bagian II — Frontend, UX & QA (Dimas)

## 11. Visi Pengalaman Pengguna & Prinsip Desain

**Visi:** Pembeli Kebuni selesai memesan sayur & buah segar dari HP dalam waktu kurang dari 3 menit, dengan yakin penuh bahwa barang yang datang segar dan stok yang terlihat adalah stok yang benar-benar ada.

**Konteks nyata yang membentuk desain:**
- Mayoritas (estimasi >80%) pembeli datang dari HP, koneksi seluler yang tidak selalu stabil.
- Produk segar = stok berubah cepat, kadang habis mendadak; harga bisa berubah per hari (panen/musim).
- Pembeli adalah pengguna awam: Ibu rumah tangga, karyawan yang pesan untuk kebutuhan mingguan. Mereka bukan pengguna power — UI yang bertele-tele = keranjang ditinggalkan.

**Prinsip desain (urutan prioritas, jaga saat ada konflik):**

| # | Prinsip | Arti konkret |
|---|---------|--------------|
| P1 | Mobile-first | Semua layar didesain mulai dari viewport 360px, lalu dinaikkan ke tablet/desktop. Tombol utama mudah dicapai jempol (bawah layar). |
| P2 | Cepat di atas cantik | Halaman katalog harus terasa instan di 3G. Gambar produk di-resize/lazy-load. Tanpa animasi yang menahan interaksi. |
| P3 | Jujur soal stok & harga | Ketersediaan dan harga yang tampil adalah data terkini; kalau tidak pasti, tampilkan peringatan ("stok menipis", "harga hari ini"). Tidak ada kejutan di checkout. |
| P4 | Sedikit langkah, sedikit ketikan | Alur dari buka katalog → pesanan terkirim maksimal 5 langkah. Form sesingkat mungkin; data yang bisa diingat (alamat, no. HP) diingat. |
| P5 | Bahasa pembeli, bukan bahasa sistem | "Keranjang", "Pesan", "Diantar hari ini" — bukan "item", "submit", "order status 2". |
| P6 | Gagal dengan sopan | Saat jaringan/stok/pembayaran gagal, pengguna selalu tahu apa yang terjadi dan apa pilihan berikutnya. Tidak ada layar mati atau spinner tanpa batas. |

---

## 12. Peta Halaman & Navigasi

Struktur navigasi: bottom navigation bar di HP (Beranda · Katalog · Keranjang · Pesanan · Akun), sidebar/topbar di desktop. URL dirancang shareable (link WA ke produk harus langsung ke produk).

### 12.1 Halaman Utama (Beranda) — `/`
- **Tujuan:** Membawa pengguna ke produk yang diinginkan secepat mungkin + menonjolkan yang segar hari ini.
- **Fitur wajib:**
  - Pencarian di bagian atas (langsung fokus ketik).
  - Kategori cepat: icon Sayuran / Buah / Paket Hemat / Promo Hari Ini.
  - Section "Panen Segar Hari Ini" (produk yang stoknya masuk hari ini).
  - Section "Segera Habis / Stok Menipis" (dorong pembelian, jujur soal stok).
  - Banner pengumuman operasional (jam cut-off pemesanan, jadwal antar).

### 12.2 Katalog per Kategori — `/kategori/{sayuran|buah|paket}`
- **Tujuan:** Menelusuri semua produk dalam satu kategori dengan cepat.
- **Fitur wajib:**
  - Grid kartu produk (gambar, nama, satuan — ikat/kg/ikat —, harga hari ini, badge status stok).
  - Filter: sub-kategori, rentang harga, ketersediaan.
  - Sortir: paling laris, harga terendah/tertinggi, terbaru.
  - Indikator loading skeleton; infinite scroll atau tombol "muat lagi".
  - Status stok realtime pada kartu: Tersedia (hijau) / Menipis (kuning, "sisa N") / Habis (abu, tombol dinonaktifkan).

### 12.3 Detail Produk — `/produk/{slug}`
- **Tujuan:** Memberi keyakinan penuh soal kesegaran, harga, dan jumlah sebelum masuk keranjang.
- **Fitur wajib:**
  - Foto produk (bisa >1), nama, deskripsi singkat (asal panen bila ada).
  - Harga hari ini + keterangan "harga diperbarui {tanggal}". Bila harga berubah tiap hari, ini wajib ditampilkan.
  - Pemilih jumlah + satuan (0,25 kg / 0,5 kg / 1 kg, atau per ikat) dengan validasi minimal.
  - Status stok realtime + waktu terakhir diperbarui.
  - Tombol "Tambah ke Keranjang" (sticky di bawah pada HP).
  - Estimasi pengiriman (lihat UX-04) untuk alamat tersimpan/lokasi umum.
  - Produk terkait (satu baris, scroll horizontal).

### 12.4 Keranjang — `/keranjang`
- **Tujuan:** Review pesanan & kuantitas sebelum checkout tanpa friksi.
- **Fitur wajib:**
  - Daftar item: gambar, nama, jumlah (bisa diedit), harga satuan terkini, subtotal.
  - **Validasi ulang stok & harga terkini saat halaman dibuka** — item yang stoknya turun/habis atau harganya berubah ditandai dengan penjelasan + pilihan (ubah jumlah / hapus / terima harga baru).
  - Ringkasan: subtotal, ongkir estimasi, total.
  - Tombol "Lanjut ke Checkout" (nonaktif bila ada item bermasalah yang belum diselesaikan).
  - Keranjang tersimpan per perangkat (localStorage) dan tersinkron ke akun saat login.

### 12.5 Checkout — `/checkout`
- **Tujuan:** Mengubah keranjang menjadi pesanan dengan data minimal dan kegagalan minimal.
- **Fitur wajib:** (alur lengkap di §16)
  - Ringkasan item final (setelah validasi ulang server).
  - Form alamat pengiriman + pilihan slot/jadwal antar.
  - Pilihan metode pembayaran.
  - Rincian biaya final: subtotal, ongkir, total — tanpa biaya tersembunyi.

### 12.6 Riwayat Pesanan — `/pesanan`
- **Tujuan:** Pengguna memantau status & memesan ulang.
- **Fitur wajib:**
  - Daftar pesanan: nomor, tanggal, total, status (dengan timeline: Menunggu Pembayaran → Dikonfirmasi → Disiapkan → Diantar → Selesai / Dibatalkan — daftar status kanonik ada di Bagian I §8).
  - Detail pesanan: item, alamat, metode bayar, status pembayaran, kurir/estimasi tiba bila ada.
  - Tombol "Pesan Lagi" (salin item ke keranjang, dengan validasi stok ulang).
  - Notifikasi status terbaru di halaman ini (dan push/WA bila tersedia — lihat UX-05).

### 12.7 Akun — `/akun`
- **Tujuan:** Kelola data diri, alamat, dan sesi.
- **Fitur wajib:**
  - Profil: nama, no. HP (identitas utama login), email opsional.
  - Manajemen alamat (bisa >1, satu alamat utama) + pin lokasi bila perlu.
  - Logout; (opsional fase lanjut) riwayat pembayaran & preferensi notifikasi.

### 12.8 Halaman pendukung
- Login/daftar (no. HP + OTP atau email+password — mengikuti keputusan PRD inti).
- Konfirmasi pesanan (`/pesanan/{no}` setelah submit) dengan nomor pesanan yang mudah disalin.
- 404 & error pages yang ramah + tombol kembali ke beranda.
- Kebijakan/FAQ singkat (jam antar, kebijakan barang kurang segar → penggantian).

---

## 13. Kebutuhan Fungsional Frontend (ber-ID + Prioritas)

Prioritas: **M**ust (MVP) / **S**hould / **C**ould.

| ID | Kebutuhan | Prioritas |
|----|-----------|-----------|
| UX-01 | **Pencarian & filter produk**: pencarian instan (debounce ≥250ms) pada nama produk, hasil termasuk produk stok habis (ditandai, tidak disembunyikan); filter sub-kategori, harga, ketersediaan; sortir. | M |
| UX-02 | **Ketersediaan stok realtime**: kartu produk, detail, dan keranjang menampilkan status stok terkini (Tersedia/Menipis/Habis + jumlah bila menipis); pembaruan saat halaman dibuka + polling/refetch saat halaman katalog aktif (interval ≥30 dtk); perubahan di keranjang divalidasi ulang sebelum checkout. | M |
| UX-03 | **Harga harian**: harga yang tampil selalu harga efektif hari ini dengan label tanggal pembaruan; bila harga berubah setelah item masuk keranjang, keranjang menandai perubahan dan meminta konfirmasi sebelum checkout; harga final dikunci pada saat pesanan dibuat (tidak berubah setelah itu). | M |
| UX-04 | **Estimasi pengiriman**: berdasarkan alamat (atau lokasi umum bila belum login) + jam cutoff operasional, frontend menampilkan estimasi (mis. "Hari ini 14.00–16.00" atau "Besok pagi") di detail produk, keranjang, dan checkout; estimasi selalu disertai sumbernya (zona/jadwal), bukan angka ajaib. | M |
| UX-05 | **Notifikasi status pesanan**: halaman riwayat pesanan selalu memperlihatkan status terkini; perubahan status penting (dikonfirmasi, diantar, selesai, dibatalkan) memicu notifikasi di channel yang tersedia (in-app wajib; WhatsApp/email mengikuti kapabilitas backend). | S |
| UX-06 | **Keranjang persisten & sinkron**: keranjang bertahan di perangkat (localStorage) tanpa login; saat login, keranjang perangkat digabung dengan keranjang akun (item sama → jumlah ditambah, bukan diduplikasi). | M |
| UX-07 | **Checkout singkat**: maksimal form: alamat (pilih dari tersimpan / isi baru), catatan opsional, metode bayar. Tidak ada field yang bisa diturunkan dari data yang ada. | M |
| UX-08 | **Pesan ulang 1-klik**: dari detail pesanan selesai, tombol "Pesan Lagi" memindahkan item (yang stoknya masih ada) ke keranjang dan melaporkan item yang tidak bisa dipesan ulang. | S |
| UX-09 | **Handling offline / jaringan lemah**: deteksi offline → banner + keranjang tetap bisa dilihat; aksi yang gagal karena jaringan bisa diulang tanpa kehilangan data form. | S |
| UX-10 | **Gambar produk efisien**: thumbnail responsif (srcset/WebP), lazy-load di bawah lipatan; halaman katalog tetap terbaca saat gambar gagal dimuat (placeholder + nama tetap tampil). | S |
| UX-11 | **Admin-friendly error state**: semua data yang gagal dimuat menampilkan pesan spesifik + tombol coba lagi; tidak pernah menampilkan data basi tanpa tanda. | S |
| UX-12 | **Akses cepat kategori via URL**: setiap kategori/produk punya URL stabil & shareable untuk dibagikan via WhatsApp. | S |

---

## 14. Standar Responsif & Aksesibilitas

### 14.1 Breakpoint
| Nama | Lebar | Perilaku utama |
|------|-------|----------------|
| sm (HP) | 360–639px | Desain dasar. Bottom nav, 2 kolom grid katalog, tombol sticky bawah. |
| md (tablet) | 640–1023px | Grid 3–4 kolom, bottom nav → topbar. |
| lg (desktop) | 1024px+ | Grid 4–6 kolom, konten max-width 1200px, sidebar navigasi. |

Semua layout diverifikasi pada 360×640 (ukuran HP terkecil yang wajar) dan 390×844 (iPhone umum).

### 14.2 Target performa (Core Web Vitals, p75 perangkat seluler)
| Metrik | Target |
|--------|--------|
| LCP | ≤ 2,5 dtk |
| INP | ≤ 200 ms |
| CLS | ≤ 0,1 |
| Berat halaman katalog (halaman pertama) | ≤ 1 MB termasuk gambar above-the-fold |
| TTFB API katalog | ≤ 500 ms (tanggung jawab bersama backend) |

Pengukuran: Lighthouse CI di setiap rilis + PageSpeed Insights pada domain produksi.

### 14.3 Aksesibilitas (baseline WCAG 2.1 AA pada elemen kunci)
- Kontras teks minimal 4.5:1; status stok tidak hanya dibedakan warna (ada teks/ikon: "Habis", "Sisa 3").
- Semua kontrol bisa dioperasikan keyboard; urutan fokus logis; focus visible.
- Form punya label nyata (bukan placeholder-saja); error form ditautkan ke field (aria-describedby).
- Gambar produk punya alt text = nama produk.
- Area sentuh minimal 44×44px.
- Bahasa halaman `lang="id"`.

---

## 15. Design System Minimal (konkret)

> Tujuan: cukup detail untuk dipakai langsung saat koding, cukup kecil untuk dijaga 2 developer.

### 15.1 Warna
| Token | Nilai | Pemakaian |
|-------|-------|-----------|
| `--color-primary` | `#2E7D32` (hijau segar) | Tombol utama, link, aksen aktif |
| `--color-primary-dark` | `#1B5E20` | Hover/pressed, header |
| `--color-accent` | `#F9A825` (kuning panen) | Badge promo, badge "Menipis" |
| `--color-bg` | `#FAFAF7` | Latar halaman |
| `--color-surface` | `#FFFFFF` | Kartu, form |
| `--color-text` | `#212121` | Teks utama |
| `--color-text-muted` | `#757575` | Teks sekunder, label |
| `--color-danger` | `#C62828` | Error, "Habis", hapus |
| `--color-success` | `#2E7D32` (sama dengan primary) | Status sukses |
| `--color-border` | `#E0E0E0` | Garis pemisah, input border |

### 15.2 Tipografi
- **Font:** "Inter" (atau fallback system-ui) — satu keluarga untuk semuanya, hemat loading.
- **Skala:** H1 24px/32 bold · H2 20px/28 bold · H3 16px/24 semibold · Body 14px/20 regular · Caption 12px/16 · Harga 16px semibold (warna primary-dark).
- Angka harga selalu format Indonesia: `Rp 15.000`, satuan `per kg`, `per ikat`.

### 15.3 Komponen inti
- **Tombol:** 3 varian (primary = solid hijau, teks putih; secondary = outline hijau; text/ghost) + state disabled (opacity 40%, cursor not-allowed). Tinggi 44px (HP). Loading state = spinner menggantikan label, tombol tidak bisa diklik ganda.
- **Kartu produk (ProductCard):** rasio gambar 1:1, badge stok kanan-atas, nama maks 2 baris (ellipsis), harga + satuan, tombol tambah cepat (ikon +) — di HP seluruh kartu ter-tap menuju detail, tombol + punya stopPropagation.
- **Form field:** label di atas input, placeholder contoh (bukan petunjuk), border 1px → 2px primary saat fokus, pesan error merah di bawah + ikon. No. HP dengan prefix +62 otomatis.
- **Status stok badge:** Hijau "Tersedia" · Kuning "Sisa {N}" · Abu "Habis" (dengan teks, bukan warna saja).
- **Skeleton loader:** bentuk sama dengan konten asli (kartu/teks) untuk mencegah CLS.
- **Toast/snackbar:** bawah layar (atas bottom nav), auto-dismiss 4 dtk, untuk konfirmasi aksi ("Ditambahkan ke keranjang").
- **Dialog konfirmasi:** untuk aksi merusak (hapus item, batalkan pesanan) — selalu ada tombol aksi eksplisit, bukan sekadar OK.

Aturan penulisan: semua teks UI Bahasa Indonesia; tanggal format `4 Okt 2026`; tidak ada teks Inggris campur di label.

---

## 16. Alur Checkout Lengkap (step-by-step + edge case)

### 16.1 Alur bahagia (happy path)
1. **Entry:** pengguna dari katalog/detail menekan "Tambah ke Keranjang" → toast konfirmasi, badge jumlah keranjang terupdate.
2. **Keranjang:** buka `/keranjang` → frontend memanggil endpoint validasi (stok + harga terkini, lihat §19). Semua item OK → ringkasan subtotal + estimasi ongkir. Ada masalah → item ditandai dan pengguna menyelesaikannya dulu.
3. **Checkout — langkah 1 Akun/Identitas:** bila belum login → minta login/daftar (guest checkout mengikuti keputusan PRD inti; UI menyiapkan kedua jalur).
4. **Checkout — langkah 2 Alamat & jadwal:** pilih alamat tersimpan atau isi baru (nama penerima, no. HP, alamat, patokan); tampilkan estimasi pengiriman final berdasar jadwal & cutoff (UX-04).
5. **Checkout — langkah 3 Pembayaran:** pilih metode (sesuai kapabilitas fase: transfer/QRIS/COD — lihat PRD inti); tampilkan rincian final: subtotal + ongkir = total.
6. **Submit:** frontend memanggil endpoint `create-order` (server melakukan validasi & reservasi stok FINAL). Tombol berubah jadi loading dan tidak bisa diklik ganda.
7. **Sukses:** redirect ke halaman konfirmasi pesanan: nomor pesanan (bisa disalin), ringkasan, instruksi pembayaran bila transfer/QRIS, tombol "Lihat status pesanan".

### 16.2 Edge case & aturan penanganan (wajib diimplementasikan + dites)

| # | Skenario | Perilaku wajib |
|---|----------|----------------|
| E1 | **Stok habis saat checkout** (validasi create-order menolak item) | Server merespons daftar item gagal + sisa stok. Frontend kembali ke langkah keranjang dengan item bermasalah ditandai merah, sisa stok ditampilkan, pilihan: kurangi jumlah / hapus item. Pesanan TIDAK dibuat sebagian. |
| E2 | **Stok berkurang di keranjang sebelum checkout** (polling/validasi mendeteksi) | Item ditandai kuning "stok berubah: sisa N", tombol lanjut terkunci sampai pengguna menyesuaikan. |
| E3 | **Produk susut / kualitas turun** (admin menandai/menghapus produk di backoffice) | Produk yang di-nonaktifkan menghilang dari katalog; bila ada di keranjang → ditandai "tidak tersedia lagi" + tombol hapus; tidak memblokir item lain. |
| E4 | **Harga berubah antara keranjang dan submit** | Validasi create-order memakai harga server. Bila berbeda dari yang ditampilkan, response menyertakan harga baru → frontend menampilkan konfirmasi eksplisit "harga berubah, total baru Rp X — lanjutkan?" sebelum submit ulang. Tidak pernah submit diam-diam dengan harga lain. |
| E5 | **Pembayaran gagal / kedaluwarsa** | Halaman pesanan menampilkan status "menunggu pembayaran" + tombol "Bayar lagi" (ganti metode diperbolehkan) sampai batas waktu (mis. 1 jam). Setelah lewat, pesanan otomatis dibatalkan oleh backend dan stok dilepas; frontend menampilkan status "Dibatalkan — pembayaran tidak diterima" + tombol pesan ulang. |
| E6 | **Klik ganda / submit ulang saat submit** | Tombol terkunci saat request berjalan + idempotency key dari frontend agar tidak tercipta 2 pesanan. |
| E7 | **Jaringan putus saat submit** | Jika response tidak diterima, frontend TIDAK menganggap gagal — cek status pesanan via endpoint inquiry sebelum mengizinkan submit ulang (mencegah pesanan ganda). |
| E8 | **Keranjang kosong saat membuka /checkout** | Redirect ke katalog dengan pesan ramah, bukan error. |
| E9 | **Alamat di luar zona pengiriman** | Pesan jelas + tetap bisa simpan alamat, tapi tidak bisa lanjut ke pembayaran; tampilkan cakupan zona. |

---

## 17. Kriteria Penerimaan (Given/When/Then) — siap dipakai QA

### 17.1 Katalog & stok realtime (UX-01, UX-02)
- **AC-KAT-1:** Given produk "Bayam" stok 0, When saya membuka kategori Sayuran, Then kartu Bayam tampil dengan badge "Habis" dan tombol tambah nonaktif.
- **AC-KAT-2:** Given produk "Tomat" stok 3, When saya membuka katalog, Then badge "Sisa 3" (kuning) tampil dan teksnya terbaca oleh screen reader.
- **AC-KAT-3:** Given halaman katalog terbuka >30 detik dan admin mengubah stok Tomat jadi 0 di backoffice, When polling berjalan, Then status kartu berubah menjadi "Habis" tanpa reload halaman.

### 17.2 Pencarian (UX-01)
- **AC-SRC-1:** Given saya mengetik "baya", When debounce 250ms lewat, Then hasil memuat "Bayam" (pencarian toleran).
- **AC-SRC-2:** Given pencarian "kangkung" yang stoknya habis, When hasil tampil, Then Kangkung tetap muncul dengan badge "Habis".

### 17.3 Harga harian (UX-03)
- **AC-HRG-1:** Given admin mengubah harga Wortel dari Rp 15.000 ke Rp 17.500 hari ini, When saya membuka detail Wortel, Then harga Rp 17.500 tampil dengan label "harga diperbarui {tanggal hari ini}".
- **AC-HRG-2:** Given Wortel (Rp 15.000) sudah di keranjang lalu harga naik ke Rp 17.500, When saya membuka keranjang, Then item ditandai "harga berubah" dan saya harus konfirmasi sebelum bisa checkout.
- **AC-HRG-3:** Given pesanan sudah dibuat, When harga berubah esok harinya, Then detail pesanan saya masih menampilkan harga saat pesanan dibuat.

### 17.4 Keranjang (UX-06)
- **AC-KRG-1:** Given saya menambah 2 produk tanpa login lalu menutup browser, When saya membuka situs lagi, Then kedua produk masih ada di keranjang.
- **AC-KRG-2:** Given keranjang perangkat berisi Bayam×1 dan keranjang akun berisi Bayam×2, When saya login, Then keranjang menampilkan Bayam×3 (digabung, bukan duplikat).
- **AC-KRG-3:** Given satu item di keranjang sudah tidak tersedia, When saya melihat keranjang, Then tombol "Lanjut ke Checkout" terkunci dan hanya item bermasalah yang diminta diselesaikan.

### 17.5 Estimasi pengiriman (UX-04)
- **AC-KRM-1:** Given jam 10.00 (sebelum cutoff), When saya buka checkout, Then estimasi "Hari ini {slot}" tampil.
- **AC-KRM-2:** Given jam 15.30 (lewat cutoff), When saya buka checkout, Then estimasi "Besok {slot}" tampil.

### 17.6 Checkout & edge case (§16)
- **AC-CKO-1 (E1):** Given stok Bayam tinggal 1 dan keranjang berisi Bayam×3, When saya submit checkout, Then saya diarahkan kembali ke keranjang dengan Bayam ditandai "sisa 1" dan TIDAK ada pesanan terbentuk.
- **AC-CKO-2 (E5):** Given pesanan menunggu pembayaran transfer melewati batas waktu, When saya membuka halaman pesanan, Then status "Dibatalkan — pembayaran tidak diterima" tampil beserta tombol "Pesan Lagi".
- **AC-CKO-3 (E6):** Given saya menekan "Buat Pesanan" 2× cepat, When request selesai, Then hanya SATU pesanan yang terbentuk (verifikasi di backoffice/MIS).
- **AC-CKO-4 (E4):** Given harga berubah di server saat saya submit, When response diterima, Then dialog "harga berubah, total baru" muncul dan pesanan hanya dibuat setelah saya menekan lanjut.
- **AC-CKO-5 (E9):** Given alamat saya di luar zona antar, When saya mencoba lanjut ke pembayaran, Then tombol terkunci dengan penjelasan zona pengiriman.

### 17.7 Notifikasi & riwayat (UX-05)
- **AC-NOF-1:** Given pesanan saya berubah status jadi "Diantar", When saya membuka halaman Pesanan, Then status terbaru terlihat tanpa refresh manual (atau setelah refresh — channel push/WA sesuai kapabilitas).
- **AC-ORD-1:** Given pesanan status "Selesai", When saya menekan "Pesan Lagi", Then item yang stoknya masih ada masuk keranjang dan item yang habis dilaporkan dalam daftar "tidak bisa dipesan".

### 17.8 Responsif & performa
- **AC-RSP-1:** Given viewport 360px, When saya membuka semua halaman utama, Then tidak ada scroll horizontal dan semua tombol utama terjangkau jempol.
- **AC-PERF-1:** Given Lighthouse mobile pada beranda & katalog, When audit dijalankan, Then LCP ≤ 2,5 dtk, CLS ≤ 0,1, INP ≤ 200 ms.

---

## 18. Rencana Testing Frontend & Daftar Bug Umum yang Dicegah

### 18.1 Lapisan testing
| Lapisan | Alat (usulan) | Cakupan | Kapan |
|---------|---------------|---------|-------|
| Unit | Vitest / Jest | Util: format harga/tanggal, logika gabung keranjang, reducer keranjang, validasi form | Setiap PR |
| Komponen | Testing Library | ProductCard (state stok), form field (error state), tombol (disabled/loading) | Setiap PR |
| E2E | Playwright (mobile viewport 390×844) | Alur kritis: cari→tambah→keranjang→checkout→buat pesanan; pesan ulang; edge case E1–E9 | Sebelum rilis |
| Visual/manual di HP nyata | Android murah (Chrome) + iPhone (Safari) milik tim | Semua halaman utama; checklist AC-RSP & performa terasa | Sebelum rilis |
| Performa | Lighthouse CI | CWV pada beranda & katalog | Setiap rilis |
| Uji lintas-backend | Kontrak §19 + mock server | Frontend tetap lulus bila backend nolak item/kembalikan harga baru | CI |

### 18.2 Skenario E2E wajib (minimum)
1. Guest: telusuri kategori → filter → detail → tambah 2 produk → keranjang valid → checkout (alamat baru) → pesanan terbentuk → nomor pesanan tampil.
2. Stok habis saat submit (E1) — divalidasi pesanan tidak terbentuk.
3. Klik ganda submit (E6) — satu pesanan.
4. Harga berubah saat submit (E4) — dialog konfirmasi muncul.
5. Login: keranjang perangkat + akun tergabung benar.
6. Pesan ulang dari pesanan selesai.
7. Offline mode: buka keranjang tanpa jaringan → data terlihat, aksi menampilkan pesan offline.

### 18.3 Daftar bug umum yang harus dicegah (checklist QA)
1. Tombol submit bisa diklik ganda → pesanan/aksi ganda. (wajib: loading lock + idempotency)
2. Harga di frontend ≠ harga di server (cache basi) → selalu tampilkan sumber waktu & validasi ulang di checkout.
3. Layout rusak di 360px (scroll horizontal, teks terpotong tanpa ellipsis).
4. Keranjang hilang saat refresh / saat login.
5. Stok yang tampil basi (tidak pernah refetch) → false hope di checkout.
6. Form error tidak jelas (hanya border merah tanpa teks) / data form hilang setelah error.
7. Gambar besar memperlambat katalog (belum lazy-load/WebP) — LCP melonjak.
8. Duplikasi item saat gabung keranjang (harus menjumlah, bukan dua baris).
9. Rupiah diformat salah (Rp15.000,5 / 15000) — satu fungsi formatter, satu sumber kebenaran.
10. Bottom nav menutupi konten/tombol sticky (padding-bottom aman).
11. Status pesanan tidak sinkron dengan backoffice (frontend asumsi sendiri) — status SELALU dari server.
12. Zona waktu: tanggal "harga diperbarui"/cutoff memakai waktu server vs waktu perangkat tidak konsisten — sepakati Asia/Jakarta di kontrak API.

---

## 19. Integrasi dengan PRD Inti (Bima) — Kontrak Data yang Dibutuhkan Frontend

Frontend memperlakukan backend sebagai sumber kebenaran tunggal. Endpoint berikut yang dibutuhkan (penamaan final menyusul kesepakatan dengan PRD inti; konsep yang mengikat):

### 19.1 Autentikasi & akun
| Kebutuhan | Kontrak yang dibutuhkan |
|-----------|------------------------|
| Login/daftar pembeli | Endpoint login + sesi token; identitas utama no. HP (atau email — sesuai keputusan PRD inti). Response memuat profil dasar. |
| Profil & alamat | CRUD alamat (list/create/update/delete + flag alamat utama); GET/PUT profil. |

### 19.2 Katalog
| Kebutuhan | Kontrak yang dibutuhkan |
|-----------|------------------------|
| Daftar produk per kategori (UX-01) | GET produk: parameter `kategori`, `sub_kategori`, `q` (cari), `sort`, `min_harga`/`max_harga`, `tersedia`, paginasi (page/per_page). Item memuat: id, slug, nama, deskripsi, foto(url), satuan, harga_hari_ini, tanggal_update_harga, status_stok (tersedia/menipis/habis), sisa_stok (bila menipis). |
| Kategori & sub-kategori | GET daftar kategori (untuk menu/filter, ter-order). |
| Detail produk (UX-03) | GET produk by slug/id — field sama + produk terkait + riwayat singkat harga bila ada. |

### 19.3 Stok & harga realtime (UX-02, UX-03)
| Kebutuhan | Kontrak yang dibutuhkan |
|-----------|------------------------|
| Cek stok & harga terkini untuk keranjang | Endpoint validasi keranjang: kirim daftar `{produk_id, qty}` → respons per item: ok / stok_kurang(sisa N) / habis / harga_berubah(harga_baru) / produk_nonaktif. Ini endpoint paling kritis — dipanggil saat keranjang dibuka dan sebelum create-order. |
| Polling perubahan katalog | Bisa memakai endpoint list dengan `updated_since` (timestamp) agar polling ringan. |

### 19.4 Pesanan (checkout & riwayat)
| Kebutuhan | Kontrak yang dibutuhkan |
|-----------|------------------------|
| Buat pesanan | POST create-order: item[], alamat_id (atau alamat inline), metode_bayar, catatan, `idempotency_key`. Response sukses: nomor_pesanan, status, rincian harga TERKUNCI, instruksi pembayaran. Response gagal validasi: daftar item gagal + alasan + sisa stok + harga baru (mendukung E1/E4). |
| Cek status pesanan (untuk E7) | GET pesanan by nomor/id — dipakai frontend setelah submit ragu (jaringan putus) sebelum mengizinkan retry. |
| Riwayat & detail | GET list pesanan milik pembeli (paginasi, filter status) + GET detail (item, alamat, status timeline, status bayar, estimasi/kurir). |
| Batalkan pesanan | Endpoint batal (dengan aturan: hanya sebelum status `disiapkan` — lihat Bagian I §8) — frontend pakai untuk tombol "Batalkan". |
| Pesan ulang | Bisa diproses frontend (validasi ulang tiap item via §19.3), tidak butuh endpoint khusus — dikonfirmasi saat penggabungan (Lampiran B butir 11). |

### 19.5 Pengiriman (UX-04)
| Kebutuhan | Kontrak yang dibutuhkan |
|-----------|------------------------|
| Estimasi & ongkir | Endpoint estimasi: input alamat/zona + waktu sekarang → output slot yang tersedia ("hari ini 14.00–16.00" / "besok pagi"), ongkir, jam cutoff, dan flag `dalam_zona: bool` (mendukung E9). |
| Zona pengiriman | GET daftar zona yang dilayani (untuk pesan di halaman alamat). |

### 19.6 Notifikasi (UX-05)
| Kebutuhan | Kontrak yang dibutuhkan |
|-----------|------------------------|
| Status terkini | GET status pesanan cukup untuk in-app. Bila backend mendukung WA/email push, frontend hanya perlu tahu flag "notifikasi terkirim" (tidak menyimpan status notifikasi di sisi frontend). |

### 19.7 Kesepakatan teknis lintas tim (usulan — sudah disepakati dan diadopsi mengikat di Bagian I §7; lihat Lampiran B butir 3)
1. **Format:** JSON REST; tanggal/waktu ISO 8601 dengan offset `+07:00`; uang integer rupiah (tanpa desimal); field snake_case.
2. **Paginasi standar:** `{data: [...], meta: {page, per_page, total}}` di semua endpoint list (konsisten dengan pagination backoffice yang sudah ada).
3. **Error standar:** kode machine-readable (`STOK_HABIS`, `HARGA_BERUBAH`, `DI_LUAR_ZONA`, dst.) + pesan Indonesia siap-tampil — frontend menampilkan pesan server apa adanya untuk error domain.
4. **Single source of truth:** status pesanan, stok, dan harga HANYA dari backend; frontend tidak pernah menghitung sendiri.
5. **Keamanan:** semua endpoint pembeli butuh sesi kecuali katalog & validasi keranjang guest; kredensial tidak pernah di localStorage.

---

---

## Lampiran A — Peta Silang ID (FR/NFR ↔ UX ↔ AC ↔ edge case)

Dibuat saat penggabungan supaya ID dari kedua dokumen saling terlacak. Definisi resmi tetap di Bagian I §5–§6 dan Bagian II §13.

### A.1 Kebutuhan frontend ↔ kebutuhan inti ↔ kriteria QA

| UX | Kebutuhan frontend (ringkas) | Pasangan di Bagian I | Kriteria QA / endpoint |
|----|------------------------------|----------------------|------------------------|
| UX-01 | Pencarian instan, filter, sortir | FR-02, FR-03 | AC-KAT-1..3, AC-SRC-1..2 |
| UX-02 | Stok realtime (Tersedia/Menipis/Habis) | FR-03, FR-10, NFR-09 | AC-KAT-1..3; `POST /api/cart/validate` (§7) |
| UX-03 | Harga harian; harga terkunci saat pesanan dibuat | FR-01, FR-05, NFR-09 | AC-HRG-1..3 |
| UX-04 | Estimasi pengiriman + jam cutoff + zona | FR-09, FR-11, FR-27 | AC-KRM-1..2; `GET /api/shipping/estimate` (§7) |
| UX-05 | Notifikasi status pesanan | FR-14, FR-29, FR-33 | AC-NOF-1 |
| UX-06 | Keranjang persisten (localStorage) + gabung saat login | FR-07 | AC-KRG-1..3 |
| UX-07 | Checkout singkat (alamat, catatan, metode bayar) | FR-08, FR-09, FR-11, FR-19 | AC-CKO-1..5 |
| UX-08 | Pesan ulang 1-klik | FR-16 + `POST /api/cart/validate` | AC-ORD-1 |
| UX-09 | Offline / jaringan lemah | NFR-01 (didukung prinsip P6) | edge case E7; ceklis §18.3 |
| UX-10 | Gambar efisien (WebP, srcset, lazy-load) | NFR-01, NFR-07 | AC-PERF-1 |
| UX-11 | Error state jelas + tombol coba lagi | NFR-01, NFR-12 | ceklis §18.3 butir 6 & 11 |
| UX-12 | URL stabil/shareable per kategori & produk | FR-02 (didukung `slug` produk, §7) | ceklis §18.3; uji manual |

### A.2 Edge case checkout ↔ kebutuhan inti / kontrak API

| Edge case (Bagian II §16.2) | Didukung oleh |
|-----------------------------|----------------|
| E1 — stok habis saat submit | FR-10; `POST /api/cart/validate` + respons gagal create-order (§7) |
| E2 — stok berkurang di keranjang | FR-03, FR-10, UX-02 (polling `updated_since`) |
| E3 — produk di-nonaktifkan / susut | FR-01 (katalog hanya produk aktif), FR-03, FR-25 |
| E4 — harga berubah sebelum submit | FR-05, NFR-09, UX-03 |
| E5 — pembayaran kedaluwarsa | FR-19 + aturan batas bayar (§7 catatan create-order; Lampiran B butir 4) |
| E6 — klik ganda submit | `Idempotency-Key` pada `POST /api/orders` (§7) |
| E7 — jaringan putus saat submit | `GET /api/orders/{no}` sebagai inquiry (§7) |
| E8 — keranjang kosong saat buka /checkout | murni frontend (Bagian II §16.2) |
| E9 — alamat luar zona pengiriman | FR-09, FR-11; flag `dalam_zona` (§7) |

### A.3 FR inti tanpa kebutuhan layar pembeli langsung

FR-06, FR-12, FR-20, FR-21, FR-23, FR-24, FR-26, FR-30, FR-31, FR-32, FR-34 berjalan lewat backoffice/admin/gudang (Bagian I §4–§5), bukan di layar pembeli — tetap lingkup Bagian I. FR lain yang dikerjakan backend tetap berdampak ke UI dan sudah dipetakan di A.1/A.2.

---

## Lampiran B — Rekonsiliasi & Koreksi saat Penggabungan

Perbaikan yang dilakukan langsung di dokumen ini saat menggabungkan `PRD-INTI.md` + `PRD-FRONTEND.md` (sesuai instruksi kartu gabungan).

| # | Temuan (dari sumber) | Perbaikan yang diterapkan | Lokasi di dokumen ini |
|---|----------------------|---------------------------|------------------------|
| 1 | **Istilah status pesanan berbeda** — FR-14 memakai `menunggu_bayar → dibayar/dikonfirmasi → disiapkan → diantar → selesai`; Bagian II memakai timeline "Diterima → Dikemas → Diantar → Selesai". | Satu daftar status **kanonik** + kolom label UI + pemetaan ke workflow SO backoffice; label di Bagian II diseragamkan ("Dikonfirmasi", "Disiapkan"). | §8 "Status pesanan: satu daftar, dua tampilan"; §5 FR-14; Bagian II §12.6 |
| 2 | **Kontrak API belum lengkap** — Bagian II §19 meminta endpoint auth/akun, CRUD alamat, kategori, validasi keranjang, dan estimasi ongkir yang belum ada di tabel §7. | Endpoint ditambahkan & ditandai sebagai "ditambahkan saat penggabungan", termasuk `Idempotency-Key` di `POST /api/orders`, parameter `updated_since` untuk polling, detail produk berbasis `slug`, dan aturan batas bayar. | §7 "Endpoint tambahan" + catatan create-order |
| 3 | **Format & error beda gaya** — §7 (inti) hanya menyebut endpoint; §19.7 (frontend) mengusulkan format JSON, paginasi, kode error. | Usulan §19.7 diadopsi sebagai **kesepakatan mengikat**: JSON REST, ISO 8601 offset `+07:00` (Asia/Jakarta), uang integer rupiah, field snake_case, paginasi `{data, meta}`, kode error machine-readable. | §7 "Kesepakatan format & error"; ditandai di Bagian II §19.7 |
| 4 | **Batas waktu bayar belum ada** — E5 (Bagian II) butuh auto-cancel + lepas reserve; Bagian I hanya mengatur batal manual. | Aturan ditambahkan: lewat batas (usulan 1 jam) pesanan otomatis dibatalkan & reserve dilepas — dikunci di F0 bersama Q2. | §7 catatan create-order; Pertanyaan Q2 |
| 5 | **Target performa beda istilah** — NFR-01 memakai FCP; Bagian II §14 memakai LCP/INP/CLS. | Diharmoniskan: keduanya berlaku — FCP & LCP ≤ 2,5 dtk, INP ≤ 200 ms, CLS ≤ 0,1; "API p95 < 500 ms" = "TTFB API ≤ 500 ms". | NFR-01; Bagian II §14.2 |
| 6 | **Referensi ID salah di sumber frontend** — "Estimasi pengiriman (lihat UX-05)" seharusnya UX-04; "push/WA (lihat UX-06)" seharusnya UX-05. | Kedua rujukan diperbaiki. | Bagian II §12.3, §12.6 |
| 7 | **Rujukan pertanyaan tidak konsisten** — Bagian I menyebut "Q1–Q9" (F0 & R-01) padahal daftar berisi 12 pertanyaan. | Diseragamkan menjadi **Q1–Q12**. | §9 (F0), §10 (R-01) |
| 8 | **Istilah baku diseragamkan** — "reserve / penahanan / reservasi stok" dan "dikemas / disiapkan / diproses". | Baku: **reserve (penahanan) stok**; **disiapkan** = `process` di backoffice (dulu disebut "dikemas"). Batas batal pembeli = sebelum status "disiapkan" — FR-17 dan §19.4 kini selaras. | §8 (tabel status), Bagian II §19.4, istilah di seluruh dokumen |
| 9 | **Dua jenis token berpotensi tertukar** — token API internal web↔backoffice (NFR-03) vs sesi token pembeli (§19.1). | Dibedakan tegas: token internal (secret per-app, di server) ≠ sesi login pembeli; mekanisme terpisah. | §7, Bagian II §19.1 |
| 10 | **Estimasi trafik HP beda angka** — NFR-07 ≥ 60%; Bagian II §11 memakai asumsi > 80%. | Dicantumkan keduanya: ≥ 60% komitmen, > 80% asumsi perencanaan desain. | NFR-07; Bagian II §11 |
| 11 | **"Pesan ulang" tanpa endpoint baru?** — §19.4 meminta konfirmasi ke PRD inti. | Dikonfirmasi: pemrosesan frontend dengan validasi ulang via `POST /api/cart/validate`; tidak perlu endpoint khusus. | Bagian II §19.4; A.1 (UX-08) |
| 12 | **Daftar pertanyaan terpisah di dua dokumen** | Digabung jadi SATU section "Pertanyaan untuk Bos" (Q1–Q12) di akhir; butir frontend (guest checkout, metode bayar per fase, OTP vs password) disatukan ke Q2/Q3 dan Q6. | Bagian penutup "Pertanyaan untuk Bos"; Lampiran C |

---

## Lampiran C — Keputusan Terbuka yang Memengaruhi Frontend

Diambil dari lampiran PRD frontend (Dimas). Status: **diamankan sementara — frontend menyiapkan kedua jalur**; keputusan akhir mengikuti jawaban bos di bagian "Pertanyaan untuk Bos".

1. **Guest checkout vs wajib akun** — UI menyiapkan toggle "checkout tanpa akun" dan "wajib akun" (→ **Q6**).
2. **Metode bayar per fase** — langkah pembayaran dibuat modular: transfer manual / QRIS / COD, mengikuti kapabilitas backend per fase (→ **Q2 & Q3**).
3. **Login OTP WhatsApp vs password** — desain form menyiapkan keduanya (→ **Q6**).

Catatan: ketiganya dikodekan sebagai konfigurasi, bukan percabangan kode — F1 (MVP) tidak perlu menunggu seluruh jawaban.

---

## Pertanyaan untuk Bos

> Gabungan dari kedua dokumen: PRD inti (Bima, **Q1–Q12**) dan PRD frontend (Dimas — butir terpisahnya sudah disatukan ke soal yang relevan: **metode bayar per fase** → Q2 & Q3, **guest checkout & OTP vs password** → Q6). Rujukan "Q1–Q12" di seluruh PRD mengacu ke daftar ini. Bisa dijawab sambil jalan saat F0, tapi jawaban mengubah isi implementasi — **F1 (MVP) baru mulai setelah Q1, Q2, dan Q6 terjawab** (Q8 & Q11 menyusul sebelum slot antar diaktifkan).

**Q1 — Repo.** Toko online ditaruh di repo `kebuni-backoffice` (folder/app terpisah) atau buat repo baru `kebuni-web`?
*Rekomendasi Bima: repo sama dulu — satu codebase CI4, deploy gampang; pisah nanti kalau sudah ramai.*
Dampak: struktur folder, CI/CD, dan strategi deploy.

**Q2 — Pembayaran.** Otomatis (Midtrans/Xendit + QRIS/VA) atau transfer manual + upload bukti dulu? Termasuk dua butir frontend: (a) **metode per fase yang dipastikan** — transfer / QRIS / COD; (b) **batas waktu bayar** sebelum pesanan otomatis dibatalkan dan stok dilepas (usulan: 1 jam).
*Rekomendasi: manual dulu untuk MVP, gateway di F3; batas bayar 1 jam.*
Dampak frontend: langkah bayar dibuat modular, UI sudah menyiapkan ketiga metode.

**Q3 — COD.** Bayar di tempat lewat kurir sendiri boleh, atau wajib bayar di muka?
*Rekomendasi: boleh, batasi area tertentu.*

**Q4 — Pengiriman.** Kurir internal Kebuni saja, atau sekalian ojek online (GoSend/GrabExpress) untuk instan?
*Rekomendasi: internal dulu.*

**Q5 — Harga.** Berubah manual per hari oleh admin lewat Price Period (sudah ada di backoffice), atau perlu aturan otomatis (mis. diskon bertahap barang mendekati layu)?
*Rekomendasi: manual + diskon akhir hari opsional di F3.*

**Q6 — Login pembeli.** Wajib akun atau boleh **guest checkout**? Kalau wajib akun: **OTP WhatsApp atau password** (no. HP jadi identitas utama)?
*Rekomendasi: wajib HP + OTP — mudah diverifikasi saat kirim dan sebagai bahan langganan/katering.*
Dampak frontend: toggle guest↔wajib akun dan form OTP maupun password sudah disiapkan (Lampiran C).

**Q7 — Notifikasi.** Lewat WhatsApp (Fonnte/similar — perlu biaya & template) atau email dulu? Admin dapat notifikasi pesanan baru lewat apa?
*Dampak: UX-05 berjalan penuh dengan in-app + salah satu channel eksternal.*

**Q8 — Hosting & domain.** `kebuni.id` sekarang hosting di mana, cukup daya untuk menambah toko online + DB bersama? (perlu dicek di F0)

**Q9 — Foto & konten produk.** Siapa yang memotret/mengisi deskripsi, dan apakah asal barang (supplier/pasar mana) perlu tampil di halaman produk?

**Q10 — Baseline angka.** Data dasar untuk mengukur target G-02..G-06: rata-rata pesanan/hari, omzet bulanan, dan % susut saat ini?

**Q11 — Jangkauan antar.** Area mana saja yang dilayani & jam pengiriman (pagi/sore) supaya slot di checkout sesuai operasi nyata?
*Dampak: `GET /api/shipping/estimate`, flag `dalam_zona`, dan edge case E9.*

**Q12 — Pricing tier grosir.** Pembeli warung/katering perlu harga tier khusus langsung di web, atau cukup admin atur manual dulu?

### Ringkasan: mana yang mengunci F1 (MVP)

| Butir | Mengunci F1? | Yang terdampak |
|-------|--------------|----------------|
| Q1 Repo | Ya (F0) | struktur & deploy |
| Q2 Pembayaran + batas bayar | Ya (F0) | langkah checkout, E5, alur SO |
| Q6 Login/guest + OTP/password | Ya (F0) | alur checkout, auth API |
| Q8 Hosting | Ya (F0) | kapasitas & domain |
| Q11 Zona & jam antar | Ya (sebelum slot aktif) | estimasi kirim, E9 |
| Q7 Notifikasi | Sebagian | in-app tetap jalan tanpa WA/email |
| Q3, Q4, Q5, Q9, Q10, Q12 | Tidak | menyiapkan fase F2–F3 / penyempurnaan |
