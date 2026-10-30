# Kebuni Mart POS — Catatan Progress

Sumber kebutuhan: `PRD_Kebuni_Mart_Hybrid_POS (1).docx` (v1.0 + Appendix A keputusan owner).
Lingkup pekerjaan saat ini: **Fase 1 — POS online + ledger** (keputusan user, 2026-10-22).
Dokumen ini dicatat berurutan; entri terbaru ada di bagian bawah (sebelum tabel "Rencana langkah").

> **Untuk agent AI / developer baru: mulai dari [`HANDOVER-AI-AGENT.md`](HANDOVER-AI-AGENT.md)** — ringkasan arsitektur, aturan bisnis, katalog fitur (menu → controller → service → izin), skema DB, cara menguji, cara deploy, dan daftar pekerjaan tertunda. Skrip smoke test sementara ada di [`smoke-tests/`](smoke-tests/README.md).

---

## Log

### 2026-10-22 — Sesi 1: Audit skema & konvensi MIS (selesai)

**Cara menjalankan CLI:** `php` di PATH adalah 7.4 (tidak bisa menjalankan `spark`). Gunakan
`C:\xampp-php-82\php\php.exe spark ...` (PHP 8.2.12). MySQL: `C:\xampp-php-82\mysql\bin\mysql -uroot kebuni_22_oct`.

**Stack & konvensi yang ditemukan**
- CodeIgniter 4.7.5-dev, Bootstrap 5 + jQuery, DB `kebuni_22_oct` (MySQL, root tanpa password — DB lokal).
- Pola kode: `Controller → Service (app/Services) → Model (app/Models)`. Audit lewat `AuditLogService` / tabel `activity_logs`.
- Izin: `RoleFilter` (`'filter' => 'role:slug.view'`), permission di tabel `permissions`/`role_permissions`, di-seed oleh `app/Database/Seeds/RolePermissionSeeder.php`. Role: 1 super-admin, 2 admin, 3 finance, 4 hr. **Belum ada role Kasir / Supervisor Toko.**
- Sidebar data-driven di `app/Views/partials/sidebar.php`; route di `app/Config/Routes.php` (group `admin`, filter `auth`).
- Migration terakhir: `2026-09-28-000003`; 16 migration sudah jalan (batch 1–6).

**Temuan penting (gap terhadap PRD)**
1. **MIS yang ada adalah sistem B2B/trading (Pricing Engine, Price Period, Sales Order), bukan retail.**
   `products` hanya punya kode, nama, kategori, unit, shrinkage — **tidak ada harga retail, SKU/barcode, batas stok minimum, konversi satuan.**
   Satuan di `ProductModel::UNITS` = Kg, Pcs, Liter, Box, Unit, Dus, Peti. 54 produk aktif/ada.
2. **Tidak ada stock ledger.** `stock_opnames` yang ada terikat `price_period_id` (stok_awal/pembelian/stok_aktual per periode untuk Final Report), bukan mutasi per transaksi. PRD FR-07 butuh tabel baru; tabel lama jangan diubah (dipakai Final Report).
3. **Keuangan = cash basis, 2 arah saja** (`financial_transactions.type` INCOME/EXPENSE) dengan unique key idempotensi `(reference_type, reference_id, type, reference_seq)`.
   - `reference_type` ENUM hanya: `SALE, MANUAL_INCOME, MANUAL_EXPENSE, RECEIVABLE_PAYMENT, PAYABLE_PAYMENT`.
   - `FinancialTransactionModel` sudah memvalidasi `TRANSFER_OUT/TRANSFER_IN`, dan query laporan sudah mengecualikannya — **tetapi ENUM di DB belum memuat nilai itu** (migration belum ada / drift antara model dan skema). Harus diperbaiki sebelum transfer internal (setoran kasir → bank) bisa dicatat.
   - Penjualan POS harus memakai `reference_type` baru (mis. `POS_SALE`), jangan `SALE` (milik Sales Order, `reference_id` = id `sales_orders`) agar tidak bentrok pada unique key.
4. **`cash_accounts`** sudah ada: `Bank Utama` (BANK, saldo awal Rp500.000.000) dan `Petty Cash` (CASH). Saldo = initial + Σ INCOME − Σ EXPENSE via `financial_transactions.cash_account_id`.
   Perlu ditambah: **Kas Kasir** (CASH) dan **QRIS Clearing** (tipe baru, mis. `CLEARING`). Cocok dengan PRD Appendix A.
5. **`accounts` = pelanggan B2B** (nama_account, nomor_telepon nullable, tanpa tipe akun). PRD FR-03: nama & telepon wajib, pencarian by telepon. Perlu kolom tipe (mis. `account_type` retail/B2B) atau tetap pakai tabel yang sama dengan telepon unik untuk pelanggan retail — **keputusan desain, lihat di bawah.**
6. **`receivables`** terikat `account_id` + `transaction_id` (→ financial_transactions), `invoice_number`; bisa dipakai untuk piutang POS (tetapi PRD Appendix A: metode awal hanya Tunai & QRIS, piutang ditunda).
7. Audit rumus Saldo Buku di Final Report (PRD §9 "Audit wajib") **belum dilakukan** — `FinalReportService` / `stock_opnames` perlu dibaca sebelum go-live. Ditandai sebagai pekerjaan Fase 0 yang tersisa; tidak memblokir pembuatan skema baru.
8. `.env` memakai `CI_ENVIRONMENT = production` dan `baseURL = https://mis.kebuni.id/` walau DB-nya lokal. Jangan menjalankan migration terhadap server produksi; hanya DB lokal `kebuni_22_oct`.

**Keputusan desain sementara (akan dikonfirmasi lewat implementasi)**
- Tabel baru berprefix `pos_` / `stock_` agar terpisah dari modul B2B.
- Harga retail: tabel `pos_product_prices` (versi + `effective_from`) + `pos_markdowns`, tidak mengubah `products`.
- Lokasi: tabel `stock_locations` (seed 1 lokasi "Toko Kebuni Mart"), `location_id` di ledger → siap multi-lokasi.
- Stok negatif ditolak default; override hanya supervisor + alasan + tercatat di tabel pengecualian.
- Moving average dihitung di `stock_ledger` (kolom qty/unit_cost/avg_cost/total_value sesudah mutasi), snapshot HPP disimpan di baris penjualan.
- Bonus supplier (`BONUS`) dan gratis promo (`PROMO_FREE`) = jenis mutasi berbeda.
- Hak akses: tambah role Kasir & Supervisor Toko + permission `pos.*`, `stock.*`.

### 2026-10-22 — Sesi 1: Migration skema POS (selesai & sudah dijalankan di DB lokal)

- **Backup DB sebelum migrate:** `writable/backups/kebuni_22_oct_pre_pos_2026-10-22.sql` (mysqldump penuh).
- **Migration `2026-10-23-000001_FixFinanceReferenceTypesForPos`** — memperbaiki bug yang sudah ada: ENUM
  `financial_transactions.reference_type` tidak memuat `TRANSFER_OUT/TRANSFER_IN` padahal dipakai kode, sehingga
  10 baris "Pemindahan Dana" tersimpan dengan `reference_type=`. Setelah migration: 5 `TRANSFER_OUT` + 5 `TRANSFER_IN`
  (diperbaiki dari kategori "Pemindahan Dana"). Ditambah tipe `POS_PAYMENT, POS_REFUND, QRIS_MDR, QRIS_SETTLEMENT`
  dan kategori expense "Biaya MDR QRIS", "Refund Penjualan POS".
- **Migration `2026-10-23-000002_CreatePosFoundation`** — tabel baru: `stock_locations`, `pos_product_prices`,
  `pos_markdowns`, `goods_receipts`, `goods_receipt_items`, `stock_balances`, `stock_ledger`, `pos_sales`,
  `pos_sale_items`, `pos_payments`, `pos_stock_overrides`. Kolom tambahan: `products.barcode/is_pos/min_stock`,
  `accounts.customer_type` (B2B|RETAIL).
- **Seed:** lokasi `TOKO`; akun kas `Kas Kasir` (id 3, CASH) & `QRIS Clearing` (id 4, CLEARING); settings
  `pos_cash_account, pos_qris_account, pos_location_id, bonus_cost_policy=allocate, pos_store_name, pos_store_whatsapp_footer`;
  permission `pos.view/sell/void/override/verify_payment/price`, `stock.view/receive`; role baru `kasir`, `supervisor-toko`,
  `admin-inventori` (admin & finance diberi izin yang relevan).
- **Idempotensi:** `pos_sales.client_uuid` UNIQUE; `stock_ledger` UNIQUE `(reference_type, reference_id, reference_line, movement_type)`.
- **Catatan:** `stock_balances` adalah baris saldo berjalan untuk penguncian (`SELECT … FOR UPDATE`); selalu diubah hanya oleh
  service ledger dalam transaksi yang sama dengan insert `stock_ledger` (tidak ada perubahan saldo langsung).

### 2026-10-22 — Sesi 1: Service layer (selesai, lulus smoke test)

File baru (semua di `app/Services/`):
- `StockLedgerService` — satu-satunya pintu mutasi stok. Moving average, `SELECT … FOR UPDATE` pada `stock_balances`, idempotensi via
  UNIQUE ledger, `reverse()` (mutasi lawan + baris asli `REVERSED`), tolak saldo negatif kecuali `allow_negative`. Wajib dalam transaksi.
- `StockException` — error bisnis yang aman ditampilkan ke kasir.
- `GoodsReceiptService` — penerimaan reguler + bonus supplier (mutasi `PURCHASE` & `BONUS` terpisah); kebijakan biaya bonus dari setting
  `bonus_cost_policy` (`allocate`/`zero`).
- `PosPricingService` — harga retail berversi, markdown terkontrol (harga normal, promo, alasan, periode, penyetuju).
- `PosSaleService` — `checkout()` (idempotent via `client_uuid`; tunai → INCOME `POS_PAYMENT` ke Kas Kasir sebesar nilai yang dibebankan;
  QRIS → `PENDING_VERIFICATION`, INCOME ke QRIS Clearing hanya setelah `verifyQris()`; BOGO lewat `free_qty` → mutasi `PROMO_FREE`,
  harga Rp0 tapi HPP tetap diakui; override stok hanya `can_override` + alasan, tercatat di `pos_stock_overrides`), `void()` (reversal ledger +
  `POS_REFUND` kas).
- `FinancialTransactionModel`: aturan validasi `reference_type` diperluas (POS_PAYMENT, POS_REFUND, QRIS_MDR, QRIS_SETTLEMENT).

**Smoke test (CLI, 25 pemeriksaan, semua lulus):** penerimaan 100+10 → stok 110, avg Rp9.090,91 (contoh PRD A2); tunai Rp30.000 dengan
tendered Rp50.000 → kembalian Rp20.000 dan kas +Rp30.000; retry UUID sama tidak menggandakan stok/kas; stok kurang ditolak tanpa sisa baris;
override supervisor tanpa alasan ditolak; BOGO; QRIS pending → verifikasi → masuk QRIS Clearing, verifikasi ulang ditolak; bayar kurang tanpa
pelanggan ditolak; markdown tidak mengubah `list_price` tercatat; void membalik stok & kas, void ulang ditolak; saldo = Σ ledger.
Skrip uji disimpan di scratchpad (bukan di `app/`, karena menjalankan TRUNCATE tabel POS) dan data uji sudah dibersihkan.

**Batasan yang disengaja / belum:** diskon manual per transaksi belum ada (harga turun hanya lewat markdown); sisa tagihan (bayar sebagian)
hanya tercatat di `pos_sales.balance_due`, pelunasannya belum ada (Piutang di luar metode awal PRD); override stok memakai izin pengguna yang login
(belum ada alur PIN supervisor di kasir); `unit` produk tidak punya konversi.

### 2026-10-23 — Keputusan user: Stock opname dilakukan SETELAH toko tutup

Permintaan user: "Proses stock opname setelah toko tutup supaya clear proses cut off datanya."
Desain yang akan diimplementasikan (menggantikan opsi "opname sambil berjualan" di PRD A3):
- Supervisor membuka **sesi opname** setelah toko tutup → waktu buka sesi = **cut-off**; saldo buku per produk di-snapshot saat itu.
- Selama sesi berstatus terbuka (`COUNTING`/`SUBMITTED`), **checkout POS dan penerimaan barang ditolak di backend** → tidak ada mutasi setelah cut-off
  yang bisa mengaburkan selisih.
- Rumus PRD FR-10: `Selisih = Stok Buku saat cut-off − Stok Fisik` (positif = fisik kurang = unknown shrinkage; negatif = fisik lebih, harus diperiksa).
- Adjustment (`OPNAME_ADJUST` di ledger) **hanya diposting setelah disetujui**; sesudah posting/pembatalan sesi, POS terbuka kembali.

### 2026-10-23 — Keputusan user: urutan EOD → hitung fisik → input dari HP

Permintaan user: "Kalau sudah selesai proses EOD, lakukan proses counting manual fisik barang, setelah itu input di system
(kalau bisa dioperasionalkan pakai HP berarti setelah counting langsung input)."
Alur yang diimplementasikan:
1. **EOD / Tutup Kas Harian** (PRD A4): kasir/supervisor menutup hari → sistem menghitung kas seharusnya (saldo awal laci + tunai − refund),
   kas fisik diinput, selisih terlihat, QRIS belum settle ditampilkan. Setelah EOD, **checkout ditolak** sampai hari berikutnya dibuka.
2. **Opname hanya bisa dibuka setelah EOD hari itu** → cut-off = waktu buka sesi opname; saldo buku di-snapshot.
3. **Halaman hitung fisik ramah HP**: daftar produk besar & bisa dicari/scan, input per produk langsung tersimpan (autosave per baris),
   jadi petugas mengetik hasil hitungan tepat setelah menghitung tiap barang.
4. Submit → supervisor review selisih → setujui → `OPNAME_ADJUST` diposting ke ledger.

### 2026-10-23 — Controller, route, menu (selesai)

- `PosController` (kasir, cari produk/pelanggan, pelanggan baru retail dengan cek duplikat telepon, checkout JSON, daftar penjualan — kasir hanya
  melihat transaksinya sendiri, struk + link WhatsApp `wa.me` terisi, void, verifikasi QRIS).
- `PosPriceController` (produk dijual di POS, barcode, stok minimum, harga retail berversi, markdown/batal markdown).
- `StockController` (saldo stok, penerimaan barang + bonus, buku stok harian/rentang + cetak, riwayat ledger per produk).
- Route grup `admin/pos/*` & `admin/stock/*` dengan `RoleFilter` per aksi (`pos.sell`, `pos.view`, `pos.void`, `pos.verify_payment`, `pos.price`,
  `stock.view`, `stock.receive`). Menu sidebar baru "Kebuni Mart POS".

### 2026-10-23 — View Fase 1 (selesai)

`app/Views/pos/`: `checkout.php` (kasir: scan/cari produk, qty desimal, kolom "Gratis" untuk BOGO, pelanggan by telepon + pelanggan baru,
tunai dengan tombol nominal cepat & kembalian, QRIS dengan peringatan "belum lunas sampai diverifikasi", override supervisor, tombol checkout
terkunci + UUID tetap sama saat retry), `sales.php` (ringkasan omzet / penerimaan / HPP terpisah), `receipt.php` (struk, cetak, tombol WhatsApp,
verifikasi QRIS, void dengan alasan), `prices.php` (katalog, harga, markdown).
`app/Views/stock/`: `index.php` (saldo + nilai persediaan), `receipts.php` / `receipt_detail.php` (penerimaan reguler + bonus),
`book.php` / `book_print.php` (buku stok harian/mingguan/bulanan/rentang + kartu stok per produk dengan link ke transaksi sumber).

### 2026-10-23 — EOD + Stock Opname setelah tutup toko (selesai)

- **Migration `2026-10-23-000003_CreateDailyClosingAndOpname`**: `pos_daily_closings`, `stock_opname_sessions`, `stock_opname_lines`,
  kolom `pos_sales.daily_closing_id`; permission `pos.close_day`, `stock.opname`, `stock.opname_approve` (kasir: close_day + opname;
  supervisor-toko & admin: semua; admin-inventori: opname).
- `DailyClosingService` + `DailyClosingController` + `pos/day.php`: buka toko (kas awal laci) → EOD (kas seharusnya = kas awal + mutasi bersih
  Kas Kasir hari itu; kas fisik; selisih; QRIS terkonfirmasi & pending). Hanya satu hari OPEN; checkout wajib hari OPEN; **void hanya selama
  hari kas penjualan masih OPEN** (setelah EOD perlu proses retur/koreksi — belum dibuat).
- `StockOpnameService` + `StockOpnameController` + `stock/opname_index.php`, `opname_count.php` (**mobile-first**: kartu per produk, input
  angka besar, autosave per produk, Enter → produk berikutnya, cari/scan, filter belum/sudah dihitung, progres; koma desimal diterima;
  petugas tanpa hak approve **tidak melihat saldo buku** = blind count), `opname_review.php` (selisih, unknown shrinkage, fisik lebih,
  setujui / hitung ulang / batalkan).
- Penguncian di backend: checkout & penerimaan barang ditolak selama sesi `COUNTING`; buka toko ditolak selama `COUNTING`;
  opname hanya bisa dibuka setelah EOD; adjustment `OPNAME_ADJUST` diposting saat disetujui dengan `effective_at` = cut-off.
- `StockController::book()` kini menghitung saldo awal dari Σ mutasi (bukan `balance_after`) supaya benar walau adjustment opname
  diposting setelah ada penjualan berikutnya.
- **Bug yang ditemukan & diperbaiki saat uji:** ringkasan EOD memakai `$day + [...]` sehingga angka berjalan tidak menimpa kolom null →
  diganti `array_merge`.

### 2026-10-23 — Pengujian (selesai)

- **Smoke test CLI: 42/42 lulus** (semua skenario Fase 1 di atas + buka toko wajib, EOD kas seharusnya Rp130.000 & selisih −Rp1.000,
  checkout/void ditolak setelah EOD, opname ditolak saat toko buka, penerimaan & buka toko ditolak saat opname dihitung, submit ditolak
  bila belum lengkap, approve memposting adjustment & tidak bisa diulang, saldo = Σ ledger).
- **Uji UI di browser (server lokal port 8091, akun uji sementara):** buka toko → penerimaan 100+10 kg via form → kasir (scan kode + Enter,
  qty 2,5 kg, BOGO, **klik checkout dua kali cepat → hanya 1 transaksi**) → struk & link WhatsApp → EOD (seharusnya Rp134.000, selisih 0) →
  opname di **viewport HP 375px** (ketik 20 ⏎ 8 ⏎ 106,5 ⏎, progres 3/3, tersimpan) → checkout ditolak backend selama hitung → submit →
  review → setujui → kartu stok menampilkan "Adjustment opname" dengan referensi `STOCK_OPNAME#1`.
- Perbaikan dari uji UI: kolom Qty kasir terlalu sempit; stok tampil "110,000 Kg" (ambigu) → kini "110"; teks WhatsApp memakai koma
  desimal dan menampilkan "Tunai diterima" sesuai struk.
- **Catatan lingkungan:** `.env` memakai `app.baseURL = https://mis.kebuni.id/`, sehingga server dev biasa (`spark serve`) me-redirect ke
  domain produksi. Uji lokal memakai router sementara yang memaksa baseURL `http://localhost:8091/`. Tidak ada aksi yang dilakukan di produksi.
- Semua data uji, akun uji, dan konfigurasi server sementara sudah dihapus; tabel POS kosong; `financial_transactions` kembali 104 baris.

### 2026-10-23 — Broken Stock (known shrinkage) & Laporan Penyusutan (selesai)

Permintaan user: "Lanjut kerjakan Broken Stock (known shrinkage) dan laporan shrinkage, catat di progress log."

- **Backup DB:** `writable/backups/kebuni_22_oct_pre_broken_2026-10-23.sql`.
- **Migration `2026-10-23-000004_CreateBrokenStock`:** tabel `stock_broken_reports` (no laporan, produk, qty, satuan, kode alasan
  BUSUK/RUSAK/KADALUARSA/HAMA/LAINNYA, keterangan, foto, estimasi biaya saat lapor, nilai aktual saat posting, status
  DRAFT/POSTED/REJECTED/CANCELLED, pelapor, penyetuju, waktu, `ledger_id`). Permission `stock.broken` (kasir, admin-inventori,
  supervisor-toko, admin) dan `stock.broken_approve` (supervisor-toko, admin).
- **`BrokenStockService`** (PRD FR-09):
  - Draft **tidak** mengurangi stok. Disetujui → mutasi `BROKEN` (keluar, biaya moving average) di `stock_ledger`; nilai resmi dari ledger.
  - Ditolak (wajib alasan) / dibatalkan pelapor sendiri → stok tidak berubah, tetap tercatat di riwayat.
  - Lapor melebihi stok buku boleh (dengan peringatan), tetapi **persetujuan ditolak** bila stok buku tidak cukup → arahkan cek penerimaan/opname.
  - Persetujuan ditolak selama opname `COUNTING` (cut-off tetap bersih).
- **Aturan baru di opname:** sesi opname **tidak bisa dibuka selama masih ada draft Broken Stock** — barang yang sudah dibuang tetapi belum
  disetujui akan terbaca sebagai unknown shrinkage. Broken Stock yang disetujui sebelum cut-off sudah mengurangi saldo buku, sehingga
  tidak dihitung dua kali (PRD FR-09, risiko "Broken Stock dihitung dua kali").
- **`ShrinkageReportService`** (PRD FR-11): known (Broken Stock disetujui) vs unknown (opname fisik kurang) vs fisik lebih (dicatat terpisah,
  tidak mengurangi penyusutan); per produk (qty, nilai, HPP terjual, rasio = penyusutan ÷ (HPP terjual + penyusutan)); per alasan;
  draft yang belum disetujui ditampilkan sebagai estimasi. Periode berdasarkan waktu efektif mutasi (opname = cut-off).
- **UI:** `stock/broken.php` (form lapor ramah HP: cari/scan produk, qty dengan koma desimal, chip alasan, foto langsung dari kamera
  `capture="environment"`, pratinjau; daftar kartu dengan thumbnail foto + zoom, tab Menunggu/Disetujui/Ditolak/Semua, tombol
  Setujui/Tolak/Batalkan), `stock/shrinkage.php` + `shrinkage_print.php` (ringkasan, per produk, per alasan, detail Broken Stock dengan link
  foto, detail selisih opname). Menu: "Broken Stock", "Laporan Penyusutan".
- **Foto** disimpan di `writable/uploads/broken_stock/` (di luar folder publik, nama acak, JPG/PNG/WEBP ≤ 5 MB) dan hanya bisa dibuka
  lewat route yang butuh login + izin; foto dihapus bila laporan gagal disimpan.

**Pengujian**
- Smoke test CLI Broken Stock **25/25 lulus**, termasuk contoh PRD FR-11: buku 120 → Broken Stock 5 disetujui → 115 → fisik 110 →
  unknown shrinkage 5 (known Rp50.000 + unknown Rp50.000 = Rp100.000). Regresi smoke test POS tetap **42/42 lulus**.
- Uji browser: lapor dari viewport HP 375px dengan foto (file dari "kamera") → draft tersimpan + foto tersimpan → akses foto tanpa login
  ditolak (302) → setujui → stok 120 → 115 → penjualan 10 kg + EOD + opname (fisik 100 & 51) → laporan penyusutan menampilkan known
  Rp50.000, unknown Rp50.000, rasio 50%, fisik lebih Rp5.000; versi cetak sesuai.
- Semua data uji, akun uji, foto uji, dan konfigurasi server sementara sudah dihapus (tabel POS & stok kosong, `financial_transactions` 104).

**Catatan keputusan**
- Penyetuju boleh orang yang sama dengan pelapor (toko kecil, satu supervisor); keduanya tetap tercatat di laporan & audit log.
  Bisa diperketat bila diinginkan.
- Broken Stock tidak membuat jurnal keuangan (beban penyusutan) — nilai tercatat di persediaan/ledger; pemetaan ke akun beban menunggu audit
  struktur akuntansi MIS (PRD FR-05).

### 2026-10-23 — Prosedur opname & analisa dari owner (Sajidan Kowsoleea, WhatsApp 00.21)

Ringkasan arahan:
1. Counting & input qty adalah **blind check**.
2. Setelah **semua** data counting diinput dan tidak ada yang terlewat → baru **rekonsiliasi** book stock vs hasil counting.
3. Selisih qty besar → **cek ulang fisik** (kemungkinan salah input, salah hitung, stok terlewat, double counting, belum dihitung).
4. Setelah yakin hasil counting sesuai fisik & kelengkapan assortment → **posting** counting fisik menjadi book stock.
5. Selisih otomatis tercatat sebagai **unknown shrinkage**; terlihat dampaknya ke **laba rugi** (termasuk known shrinkage / broken stock),
   kontribusi known, unknown, dan total shrinkage terhadap keuangan — idealnya real-time.
6. Analisa produk **slow/fast moving, high/low risk** → **matriks keputusan**: pertahankan, delete, genjot (tren sales positif),
   turunkan volume order (shrinkage tinggi). "Bisnis by data, bukan by feeling."

Rencana implementasi (keputusan desain):
- **Blind penuh:** saldo buku tidak tampil di layar hitung untuk siapa pun (termasuk supervisor) selama tahap hitung/hitung ulang;
  hasil hitung sebelumnya juga tidak ditampilkan saat hitung ulang.
- **Kelengkapan assortment:** petugas bisa menambah produk yang ditemukan fisik tetapi tidak ada di daftar sesi.
- **Riwayat hitung** per produk per putaran (hitung ke-1, ke-2, …).
- **Selisih besar** = |selisih| ≥ X% dari buku **atau** |nilai| ≥ Rp Y (setting `opname_variance_pct` default 5%,
  `opname_variance_value` default Rp50.000), atau buku 0 tetapi fisik ada.
- **Gerbang posting:** setiap baris selisih besar **wajib dihitung ulang minimal sekali**; jika setelah hitung ulang masih besar,
  supervisor wajib **konfirmasi** dengan kategori penyebab + catatan. Posting ditolak sampai semua terpenuhi.
- Hitung ulang hanya boleh bila **belum ada mutasi stok setelah cut-off** untuk produk tsb (jika toko sudah buka & berjualan, angka fisik
  sudah berubah → buat sesi baru).
- **Halaman Dampak Laba Rugi & Matriks Produk:** penjualan, HPP, laba kotor, known, unknown, total shrinkage, laba setelah shrinkage
  (+ % terhadap penjualan); per produk: kecepatan jual (fast/slow), risiko shrinkage (high/low), tren penjualan vs periode sebelumnya,
  margin → rekomendasi aksi.

### 2026-10-23 — Implementasi prosedur owner (selesai)

- **Backup DB:** `writable/backups/kebuni_22_oct_pre_reconcile_2026-10-23.sql`.
- **Migration `2026-10-23-000005_AddOpnameReconciliation`:** tabel `stock_opname_counts` (riwayat hitung per produk per putaran);
  kolom baru di `stock_opname_lines` (`count_round` = berapa kali produk dihitung, `added_during_count`, `recount_requested`,
  `variance_flag`, `resolution`, `resolution_reason/note`, `resolved_by/at`) dan `stock_opname_sessions.count_round` (putaran sesi);
  setting `opname_variance_pct` (5), `opname_variance_value` (50000), `shrinkage_high_risk_pct` (5).
- **`StockOpnameService` ditulis ulang** sesuai prosedur: blind count → submit (semua wajib terisi) → rekonsiliasi otomatis menandai
  selisih besar → `requestRecount()` per produk (putaran baru, blind, hanya produk terpilih yang bisa diisi; checkout tetap terkunci)
  → `confirmLine()` (penyebab: hilang, susut alami, rusak tak dilapor, salah catat transaksi, penerimaan belum dicatat, lainnya + catatan
  wajib) → `approve()` hanya bila semua selisih besar sudah dihitung ulang ≥1x dan yang masih besar sudah dikonfirmasi.
  `addLine()` untuk barang fisik yang tidak ada di daftar (kelengkapan assortment). Hitung ulang/tambah produk ditolak bila produk
  sudah punya mutasi setelah cut-off.
- **Blind penuh:** controller tidak lagi mengirim saldo buku/selisih/hasil putaran sebelumnya ke layar hitung (termasuk untuk supervisor);
  respons simpan hitungan juga tidak memuat selisih.
- **UI:** `opname_count.php` (label "Hitung ke-1"/"HITUNG ULANG ke-N", hanya produk yang diminta, tombol "Ada barang fisik yang tidak ada
  di daftar?"), `opname_review.php` (rekonsiliasi: urut berdasarkan yang perlu tindakan, status Wajib hitung ulang / Masih selisih —
  konfirmasi / Dikonfirmasi, riwayat hitung #1, #2 …, centang & "Minta Hitung Ulang", modal konfirmasi penyebab, tombol Posting terkunci
  sampai beres), `opname_index.php` (langkah prosedur baru). Ambang selisih bisa diubah di **Setting** (Super Admin), sekaligus kebijakan
  biaya bonus supplier.
- **Analisa Laba & Produk** (`ProfitShrinkageService`, `AnalysisController`, `pos/analysis.php`, menu POS, izin `finance.view`):
  - KPI: penjualan, laba kotor, total penyusutan (% penjualan & % laba kotor), laba setelah penyusutan.
  - Laba rugi ringkas: penjualan − HPP − biaya barang gratis promo = laba kotor − known − unknown + selisih lebih = laba setelah penyusutan,
    masing-masing dengan % penjualan.
  - Kontribusi penyusutan: bar bertumpuk known vs unknown (palet tervalidasi untuk buta warna, mode terang & gelap).
  - Matriks produk (scatter): X = penjualan, Y = % penyusutan; garis batas fast moving (ABC: pembentuk 80% penjualan) & high risk (setting);
    label nama produk; tooltip.
  - Tabel keputusan per produk: porsi, qty/hari, tren vs periode sebelumnya (naik/turun/stabil/baru), laba kotor, known, unknown, % susut,
    laba setelah susut, kelas, rekomendasi: **Genjot, Pertahankan, Perbaiki handling, Stok minimal, Turunkan volume, Kandidat delete**.
  - Peringatan bila belum ada opname yang diposting / ada Broken Stock draft / periode < 28 hari.

**Pengujian**
- Smoke test rekonsiliasi **26/26 lulus** (blind response, submit wajib lengkap, deteksi selisih besar, posting & konfirmasi ditolak sebelum
  hitung ulang, putaran 2 hanya produk terpilih, checkout terkunci, tambah produk ditemukan, riwayat 70→72, konfirmasi wajib catatan,
  posting → saldo buku = fisik, hitung ulang ditolak setelah ada mutasi pasca cut-off, angka laba rugi & rekomendasi sesuai perhitungan manual).
  Regresi: Broken Stock **25/25**, POS **42/42** (smoke test lama disesuaikan dengan gerbang hitung ulang).
- **Bug ditemukan & diperbaiki:** produk yang baru ditambahkan di putaran 2 sempat dianggap "sudah dihitung ulang" → `count_round` kini
  = jumlah hitungan nyata dari riwayat. Pesan submit kini membedakan "perlu dicek ulang" vs "perlu konfirmasi".
- Uji browser: rekonsiliasi (desktop), hitung ulang di viewport HP (hanya produk diminta, tanpa angka buku/sebelumnya), konfirmasi & posting,
  halaman Analisa (angka cocok, mode gelap ikut berganti), Setting (simpan & validasi). Perbaikan tampilan: angka laba rugi tidak terpotong,
  garis batas fast moving tidak jatuh di tepi, titik 100% tidak terpotong, deskripsi rekomendasi dipindah ke tooltip + legenda.
- Semua data uji & akun uji dihapus; setting kembali ke nilai semula.

**Catatan**
- Unknown shrinkage baru masuk laba rugi setelah opname diposting; laba "real-time" untuk hari-hari setelah opname terakhir belum memuat
  susut yang belum terukur (ditampilkan sebagai peringatan di halaman).
- Laba setelah penyusutan belum dikurangi OPEX/gaji/sewa dari modul Keuangan.

### 2026-10-23 — Keputusan user: ambang selisih besar tetap 5% / Rp50.000; lanjut retur/koreksi setelah EOD

Desain (PRD FR-02, Business Rules "transaksi diposting tidak dihapus; koreksi lewat reversal/transaksi koreksi"):
- **Void** tetap hanya untuk pembatalan penuh selama hari kas penjualan masih OPEN. **Setelah EOD** (atau kapan pun untuk
  sebagian barang) dipakai **Retur pelanggan** — penjualan asli tidak diubah, retur adalah dokumen baru.
- **Retur:** pilih barang & qty (≤ terjual − sudah diretur), kondisi **Layak jual** (stok masuk kembali, `CUSTOMER_RETURN` dengan biaya
  pokok asli) atau **Rusak** (stok masuk lalu otomatis Broken Stock disetujui → known shrinkage), alasan wajib.
  Nilai retur = qty × harga yang benar-benar dibayar (barang gratis promo bernilai Rp0).
- **Refund uang:** Tunai dari **Kas Kasir** (wajib ada hari kas OPEN, masuk EOD hari itu sebagai refund) atau Transfer dari rekening
  yang dipilih (mis. Bank Utama). Refund maksimal = yang sudah dibayar − refund sebelumnya; sisanya mengurangi sisa tagihan.
  Tipe ledger keuangan baru `POS_RETURN_REFUND`.
- **Koreksi metode bayar** (mis. tercatat tunai padahal QRIS): dicatat sebagai pemindahan dana antar akun (Kas Kasir ↔ QRIS Clearing)
  pada **tanggal bisnis penjualan asli**; baris pembayaran asli tidak diubah, hanya diberi tanda koreksi; snapshot EOD hari itu tetap,
  koreksi terlihat di riwayat.
- **Koreksi barang/qty salah** = retur barang yang salah + transaksi baru yang benar (keduanya punya jejak).
- Izin: `pos.return` (supervisor-toko, admin), `pos.payment_correct` (supervisor-toko, admin, finance). Retur ditolak selama opname dihitung.
- Laporan: penjualan & laba rugi dikurangi retur (nilai & HPP), EOD menghitung refund retur tunai.

### 2026-10-23 — Retur pelanggan & koreksi metode bayar (selesai)

- **Backup DB:** `writable/backups/kebuni_22_oct_pre_returns_2026-10-23.sql`.
- **Migration `2026-10-23-000006_CreatePosReturns`:** `pos_returns`, `pos_return_items`, `pos_payment_corrections` (1 koreksi per
  pembayaran), kolom `pos_payments.corrected_method`, tipe keuangan `POS_RETURN_REFUND`, permission `pos.return` (supervisor-toko, admin)
  & `pos.payment_correct` (supervisor-toko, admin, finance).
- **`PosReturnService`:** `create()` (validasi sisa qty per baris, nilai = harga yang dibayar, barang gratis promo Rp0; stok
  `CUSTOMER_RETURN` dengan biaya pokok asli; kondisi rusak → otomatis Broken Stock POSTED + mutasi `BROKEN`; refund tunai Kas Kasir
  hanya saat hari kas OPEN & tercatat di tanggal bisnis hari itu, atau transfer dari rekening dipilih; refund ≤ uang diterima − refund
  sebelumnya, sisanya mengurangi sisa tagihan; ditolak bila ada QRIS pending atau opname sedang dihitung) dan `correctPayment()`
  (Tunai ↔ QRIS lewat TRANSFER_OUT/IN kategori "Pemindahan Dana" pada tanggal bisnis penjualan asli; snapshot EOD tidak berubah).
- **Void** kini juga ditolak bila penjualan sudah punya retur (mencegah refund ganda).
- **Laporan disesuaikan:** EOD menghitung refund retur tunai; Analisa Laba memakai penjualan bersih setelah retur & HPP dikurangi biaya
  pokok barang retur; Laporan Penyusutan memakai HPP bersih; Penjualan POS menampilkan total retur periode; Buku Stok menautkan
  referensi `POS_RETURN` & `BROKEN_STOCK`.
- **UI:** detail penjualan (kartu Retur + tombol *Retur Barang*, pesan "sudah EOD — gunakan Retur", tombol *Koreksi ke QRIS/Tunai*
  per pembayaran + badge koreksi), `pos/return_form.php` (qty per baris dengan tombol "semua sisa", kondisi layak jual/rusak, alasan,
  tunai/transfer + rekening, ringkasan nilai/refund/pengurang tagihan), `pos/return_show.php` (nota retur + WhatsApp),
  `pos/returns.php` (daftar retur & koreksi). Menu "Retur & Koreksi".

**Pengujian**
- Smoke test retur **25/25 lulus**: refund tunai saat toko tutup ditolak; retur setelah EOD via transfer Bank Utama (stok +2, bank −30.000);
  qty berlebih / QRIS pending / alasan kosong ditolak; retur rusak dengan refund tunai di hari berikutnya (Kas Kasir −45.000 pada tanggal
  bisnis hari itu, stok netto tetap, Broken Stock otomatis Rp30.000, EOD refund 45.000 & kas seharusnya 155.000); retur barang gratis
  promo bernilai 0 tanpa refund; retur penjualan belum lunas (refund 20.000, sisa tagihan 0 → lunas); void ditolak setelah ada retur;
  koreksi Tunai→QRIS pada tanggal bisnis asli tanpa mengubah snapshot EOD, koreksi kedua & koreksi QRIS pending ditolak, tidak masuk
  laporan pemasukan/pengeluaran; laba rugi penjualan bersih 285.000 − 150.000 = 135.000; saldo = Σ ledger.
  Regresi: POS **42/42**, Broken Stock **25/25**, Rekonsiliasi **26/26**.
- Uji browser: detail penjualan sudah EOD → form retur (2,5 kg rusak + 1 gratis promo, default transfer karena toko tutup) → nota retur;
  koreksi metode bayar → badge koreksi & tombol hilang; daftar Retur & Koreksi. Perbaikan kecil: tombol koreksi tidak terpotong.
- Semua data uji & akun uji dihapus; `financial_transactions` kembali 104 baris (id terakhir 137).

**Catatan**
- Retur barang gratis promo: sistem tidak memaksa barang gratis ikut dikembalikan bila barang berbayarnya diretur (kebijakan toko,
  bisa diperketat).
- Rekening sumber refund transfer bisa dipilih termasuk QRIS Clearing (untuk refund lewat penyedia QRIS).

### 2026-10-23 — Audit Final Report (Fase 0) & layar Stok Awal (selesai)

Permintaan user: "Kerjakan layar input stok awal (mutasi OPENING) dan audit rumus Final Report sebelum go-live, catat di progress log."

**Audit Final Report** — laporan lengkap: [`docs/AUDIT-FINAL-REPORT.md`](AUDIT-FINAL-REPORT.md). Audit hanya membaca (kode & data
tidak diubah). Ringkasan temuan:
- **Kritis:** periode 116 menampilkan susut **−Rp9.970.436** padahal semua `stok_aktual` masih 0 (belum diinput) — baris opname
  dianggap sudah dihitung.
- **Tinggi:** saldo buku negatif (Kol −23,6 kg, Kentang C −8 kg); `pembelian` = `stok_awal` di 13/13 baris dan rumus mengabaikan
  `pembelian` (docblock & PRD bilang `awal + pembelian − terjual`) → tidak ada kesinambungan antar periode; harga valuasi **hard-coded**
  (Excel 18-10-2026) dipakai untuk semua periode (mis. Cabe Merah Keriting periode 114 dinilai ±2× COGS-nya).
- **Sedang:** valuasi pakai COGS destination (termasuk ongkos kirim), rumus "Laba/rugi qty" = `2×aktual − buku`, produk yang tidak terjual
  tidak ikut dihitung susutnya. **Rendah:** SO yang belum dikirim sudah mengurangi stok; periode 107–114 tanpa opname.
- **Kesimpulan:** angka Final Report tidak dipakai sebagai stok awal toko. Perbaikan Final Report menunggu keputusan owner/Keuangan
  (mengubah angka historis).

**Stok Awal (mutasi OPENING)**
- **Backup DB:** `writable/backups/kebuni_22_oct_pre_opening_2026-10-23.sql`.
- **Migration `2026-10-23-000007_CreateStockOpenings`:** `stock_openings` (no dokumen, cut-off, status DRAFT/POSTED/CANCELLED),
  `stock_opening_lines` (qty, biaya/unit, sumber harga, catatan, `ledger_id`); permission `stock.opening` (admin-inventori, admin) &
  `stock.opening_post` (admin).
- **`StockOpeningService`:** draft (cut-off tidak boleh di masa depan, satu draft aktif), simpan baris (replace; tolak dobel/negatif; koma
  desimal diterima), posting (tolak bila opname sedang dihitung, biaya 0, atau produk sudah punya mutasi apa pun → koreksi berikutnya lewat
  opname; mutasi `OPENING` dengan `effective_at` = cut-off; baris qty 0 diabaikan; produk otomatis ditandai dijual di POS), batal draft.
  Biaya default = `purchase_price` price period terbaru yang memuat produk; bila diubah tercatat "Diisi manual".
- **UI:** menu Stok → **Stok Awal** (`stock/opening_index.php`: langkah go-live + catatan audit; `stock/opening_form.php`: tambah produk
  dengan pencarian yang mengutamakan awalan nama, "+ Semua produk POS"/"+ Semua produk aktif", qty & biaya per baris, total nilai,
  simpan draft / simpan & posting, tampilan dokumen terposting dengan link kartu stok). Buku Stok menautkan referensi `STOCK_OPENING`.

**Pengujian**
- Smoke test stok awal **19/19 lulus** (cut-off masa depan ditolak, draft kedua ditolak, biaya default dari PP-2026-09-19, dobel/negatif
  ditolak, biaya 0 ditolak, posting 2 produk nilai Rp1.245.000, saldo & avg cost benar, `effective_at` = cut-off, qty 0 tidak diposting,
  produk ditandai POS, dokumen terposting terkunci, produk yang sudah bermutasi ditolak, posting ditolak saat opname dihitung, HPP penjualan
  berikutnya = biaya stok awal, saldo = Σ ledger). Regresi: POS 42/42, Broken Stock 25/25, Rekonsiliasi 26/26, Retur 25/25.
- Uji browser: buat draft → tambah Daun Bawang/Bawang Brebes/Kol → isi qty & biaya → posting → dokumen POSTED (Rp853.500) → kartu stok
  menampilkan "Stok awal". Perbaikan dari uji: pencarian "kol" sempat memilih Brokoli; sumber harga kini "Diisi manual" bila biaya diubah.
- Semua data uji & akun uji dihapus.

### 2026-10-23 — Perbaikan temuan kritis F1 Final Report (selesai)

Permintaan user: "Perbaiki temuan kritis F1 di Final Report: baris opname yang stok aktualnya belum diinput jangan dihitung sebagai susut."
- **Backup DB:** `writable/backups/kebuni_22_oct_pre_f1_fix_2026-10-23.sql`.
- **Migration `2026-10-23-000008_MakeOpnameStokAktualNullable`:** `stock_opnames.stok_aktual` NULL-able (NULL = belum dihitung);
  13 baris periode 116 yang bernilai 0 (belum pernah dihitung) → NULL. Tabel `stock_opnames` sebelumnya tidak punya migration di repo
  (dibuat di luar migration); perubahan ini lewat `ALTER TABLE`.
- **Kode:** `StockOpnameModel::replaceForPeriod()` (kosong → NULL, 0 tetap 0), `FinalReportService::stockReport()` (susut hanya untuk
  baris yang sudah diinput; `n_pending`), `report/final.php` (input kosong ber-placeholder "belum", tombol simpan mengirim kosong sebagai
  NULL, KPI menyebut jumlah produk belum dihitung, catatan aturan kosong vs 0).
- **Hasil:** KPI "Nilai Shrinkage" periode 116 dari **−Rp9.970.436** → **Rp0 (13 produk belum dihitung)**; nilai saldo buku tetap tampil
  di kolom Saldo Buku.
- **Pengujian:** uji CLI 6/6 lulus (susut 0 & 13 belum; Jagung buku tetap 150; simpan 140 / 0 / kosong tersimpan benar; hanya 2 baris
  terhitung, susut −Rp113.000; data dikembalikan persis). Uji browser: KPI, 13 input kosong "belum", kolom susut "—", total susut Rp0.
  Akun uji dihapus.
- **Tindakan berikutnya untuk user:** isi stok aktual periode 116 dari hitung fisik (0 bila habis). F2–F10 tetap menunggu keputusan;
  detail di `docs/AUDIT-FINAL-REPORT.md` §6.

### 2026-10-23 — Keputusan user: payment gateway belakangan

"Payment gateway belakangan aja, sekarang manual dulu." → QRIS tetap **manual** (kasir pilih QRIS → status menunggu verifikasi →
supervisor/keuangan konfirmasi dari mutasi QRIS). Integrasi gateway otomatis tetap di daftar pengembangan lanjutan (PRD §5.2).

### 2026-10-23 — User: "lanjut lagi" → setoran Kas Kasir & settlement QRIS (desain)

PRD A4: setoran kasir ke bank = transfer internal (bukan omzet); settlement QRIS = transfer QRIS Clearing → Bank Utama + MDR aktual sebagai biaya.
- **Setoran Kas Kasir** (ke Bank Utama/Petty Cash): pemindahan dana kategori "Pemindahan Dana" (tidak masuk pemasukan/pengeluaran).
  Dicatat ke hari kas: bila toko buka → masuk mutasi hari itu (kas seharusnya EOD berkurang); bila sudah EOD → menempel ke hari kas
  terakhir (setelah tutup) sehingga tidak dihitung dua kali di hari berikutnya. Nominal tidak boleh melebihi kas yang tersedia di laci.
- **Kontrol kas awal:** saat buka toko, sistem menghitung **kas awal seharusnya** = kas fisik EOD terakhir ± mutasi Kas Kasir sesudah
  EOD (mis. setoran); selisih dengan kas awal yang diinput ditampilkan & disimpan.
- **Settlement QRIS:** pilih pembayaran QRIS terkonfirmasi yang belum disettle → nilai bruto; input dana bersih yang masuk bank →
  MDR = bruto − bersih. Pencatatan: QRIS Clearing → Bank (bersih, tipe `QRIS_SETTLEMENT`, kategori Pemindahan Dana) + biaya MDR
  (tipe `QRIS_MDR`, kategori "Biaya MDR QRIS", akun QRIS Clearing). Pembayaran ditandai sudah settle (tidak bisa disettle dua kali).
- Payment gateway tetap belakangan (keputusan user); QRIS manual.

### 2026-10-23 — Setoran Kas Kasir, settlement QRIS + MDR, kontrol kas awal (selesai)

- **Backup DB:** `writable/backups/kebuni_22_oct_pre_deposit_2026-10-23.sql`. Catatan: MySQL XAMPP lokal sempat mati (bukan karena perubahan
  kode); dinyalakan kembali dengan `mysqld --defaults-file=mysql/bin/my.ini --standalone`, data utuh.
- **Migration `…000009_CreateCashDepositsAndQrisSettlements`:** `pos_cash_deposits`, `pos_qris_settlements`, `pos_payments.settlement_id`,
  `pos_daily_closings.expected_opening_cash`; permission `pos.cash_deposit` (supervisor-toko, admin, finance) & `pos.qris_settle` (admin, finance).
  **`…000010_AddFtBoundaryToDailyClosings`:** `pos_daily_closings.ft_max_id` = batas id transaksi keuangan saat EOD (perbandingan waktu
  terbukti rapuh bila transaksi & EOD di detik yang sama).
- **`PosCashService`:** `deposit()` (Kas Kasir → rekening tujuan sebagai TRANSFER_OUT/IN "Pemindahan Dana" — bukan omzet; saat toko buka
  mengurangi kas seharusnya EOD, setelah EOD dicatat ke hari kas terakhir tanpa mengubah snapshot EOD; ditolak bila melebihi kas laci,
  tujuan = Kas Kasir/QRIS Clearing, atau belum ada hari kas), `unsettledQris()`, `settleQris()` (pilih pembayaran QRIS terkonfirmasi →
  dana bersih masuk bank + **MDR = bruto − bersih** sebagai EXPENSE `QRIS_MDR` kategori "Biaya MDR QRIS" dari QRIS Clearing; satu pembayaran
  hanya sekali; pembayaran dari penjualan yang sudah diretur diblokir → settle manual lewat Keuangan).
- **Kontrol kas awal:** `DailyClosingService::expectedOpeningCash()` = kas fisik EOD terakhir ± mutasi Kas Kasir sesudahnya (mis. setoran
  malam); disimpan di `expected_opening_cash` saat buka toko untuk dibandingkan dengan kas awal yang diinput.
- **UI:** menu POS → **Setoran & QRIS** (`pos/cash.php`): kas tersedia di laci, form setoran, daftar QRIS belum settle dengan hitung
  bruto/MDR otomatis, riwayat setoran & settlement.
- **Pengujian:** smoke test CLI **33/33** (setoran saat buka & setelah EOD, kas tersedia, kas awal seharusnya, bruto 100.000/bersih
  99.300/MDR 700, settle ulang & retur diblokir, saldo QRIS Clearing, laporan tidak terpengaruh) + regresi POS 42, Broken 25, Rekonsiliasi 26,
  Retur 25, Stok Awal 19 lulus; uji browser (setoran Rp150.000, settlement 2 trx). Data uji & akun uji dihapus.
- **Belum:** tampilan selisih kas awal (input vs seharusnya) di layar Buka Toko; bukti foto setoran/settlement (kolom sudah ada, belum ada upload).

### 2026-10-23 — User: diskon manual + PIN supervisor di layar kasir (desain)

- **Diskon manual per transaksi** (persen atau nominal, alasan wajib) — hanya dengan otorisasi supervisor: pengguna yang login punya izin
  `pos.discount` (supervisor-toko, admin) **atau** supervisor memasukkan **PIN** di layar kasir. Diskon dialokasikan proporsional ke baris
  berbayar (kolom `pos_sale_items.discount`) sehingga laporan per produk, retur (refund = harga yang benar-benar dibayar), dan laba
  rugi otomatis memakai nilai bersih. Diskon < subtotal (untuk gratis gunakan "Gratis" promo). HPP tidak berubah.
- **PIN supervisor:** tiap supervisor/admin menyetel PIN 4–6 digit sendiri (butuh password akun; disimpan sebagai hash). Di kasir: pilih nama
  supervisor + PIN → mengotorisasi **diskon** dan **override stok** untuk transaksi itu; kasir tetap yang tercatat sebagai pembuat transaksi,
  supervisor tercatat sebagai penyetuju (`pos_sales.discount_approved_by`, `pos_stock_overrides.supervisor_id`). 5× salah → PIN terkunci 15 menit.
- PIN diverifikasi di backend sebelum transaksi database (agar hitungan gagal tidak ikut di-rollback); semua penggunaan masuk audit log.

### 2026-10-23 — Diskon manual & PIN supervisor di layar kasir (selesai)

- **Backup DB:** `writable/backups/kebuni_22_oct_pre_discount_2026-10-23.sql`.
- **Migration `…000011_AddDiscountAndSupervisorPin`:** `users.pin_hash/pin_failed/pin_locked_until`; `pos_sales.discount_type/value/reason/approved_by`;
  `pos_sale_items.discount`; permission `pos.discount` (supervisor-toko, admin).
- **`SupervisorAuthService`:** `setPin()` (4–6 digit, tolak PIN mudah ditebak 1234/1111/dst., wajib password akun, hanya akun berwenang), `verify()`
  (hash; salah 5× → terkunci 15 menit; semua salah/benar masuk audit log), `eligible()` (supervisor berPIN untuk dropdown kasir), `userCan()`.
- **Checkout (`PosSaleService`):** diskon persen/nominal + alasan wajib; otorisasi lewat izin pengguna login **atau** PIN supervisor. PIN
  diverifikasi **sebelum** transaksi DB (hitungan salah tidak ikut di-rollback). Diskon dialokasikan proporsional ke baris berbayar (sisa
  pembulatan ke baris terakhir) → `line_total` bersih sehingga laporan per produk, laba rugi, dan **retur (refund = harga yang dibayar)**
  otomatis benar; HPP tidak berubah. Diskon harus < subtotal (gratis → kolom "Gratis"). **Override stok** juga bisa lewat PIN; supervisor
  tercatat sebagai penyetuju di `pos_stock_overrides`, kasir tetap pembuat transaksi.
- **UI:** layar kasir — blok *Diskon manual* (%/Rp, alasan, badge nilai diskon, total ikut berubah), *Override stok*, tombol *Otorisasi supervisor (PIN)*
  + modal pilih supervisor & PIN (PIN hanya di memori halaman); menu **PIN Supervisor** (`pos/pin.php`); struk & teks WhatsApp menampilkan
  Subtotal/Diskon/Total (baris item tampil harga kotor sehingga jumlahnya cocok).
- **Pengujian:** smoke test CLI **37/37** (PIN: kasir tak berwenang, format, mudah ditebak, password salah, hash, kunci 5× & buka kunci;
  checkout: diskon tanpa/salah PIN ditolak, alasan wajib, 100%/≥subtotal ditolak, 10% → total 36.000 & alokasi 2.000/2.000, penyetuju vs kasir,
  HPP tetap, nominal Rp1.000, supervisor login tanpa PIN, override stok tanpa PIN ditolak / dengan PIN lolos, retur baris berdiskon refund 18.000)
  + regresi POS 42, Broken 25, Rekonsiliasi 26, Retur 25, Stok Awal 19, Kas 33 semua lulus. Uji browser: kasir (role Kasir) → diskon 10% →
  PIN salah (4 percobaan tersisa) → PIN benar → total 72.000 → checkout → struk & WhatsApp → audit log (PIN salah, otorisasi, checkout);
  halaman PIN Supervisor. Data & akun uji dihapus.
- **Catatan:** PIN dikirim ke server saat checkout (HTTPS wajib di produksi); `pin_failed` dihitung per supervisor, bukan per perangkat.

### 2026-10-23 — Sisa Fase 2: selisih kas awal & pelunasan sisa tagihan (selesai)

Permintaan user: "Kerjakan sisa Fase 2: selisih kas awal di layar Buka Toko dan pelunasan sisa tagihan pelanggan; catat di progress log."
- **Backup DB:** `writable/backups/kebuni_22_oct_pre_balance_2026-10-23.sql`. **Migration `…000012_AddBalancePaymentColumns`:** `pos_payments.client_uuid`
  (UNIQUE, idempotensi) & `is_balance`.
- **Selisih kas awal (Buka Toko):** layar menampilkan **kas awal seharusnya** (kas fisik EOD terakhir ± setoran/mutasi sesudahnya), selisih
  dengan hitungan laci muncul langsung saat mengetik; bila berbeda, **catatan wajib** (ditolak di backend bila kosong). Tersimpan di
  `expected_opening_cash`; ditampilkan sebagai badge di hari berjalan dan kolom **Selisih buka** di riwayat kas harian; masuk audit log.
  Hitungan fisik tidak di-prefill agar kasir benar-benar menghitung.
- **Pelunasan sisa tagihan** (`PosSaleService::addPayment()`): pembayaran susulan atas penjualan POS yang belum lunas (pelanggan wajib sejak checkout).
  Tunai → Kas Kasir, wajib toko buka, masuk EOD hari itu (kembalian dihitung; yang diterima maksimal sisa); QRIS → menunggu verifikasi
  seperti checkout (sisa baru berkurang setelah dikonfirmasi; nominal dibatasi sisa dikurangi QRIS pending); klik ganda aman lewat `client_uuid`;
  ditolak bila sudah lunas / dibatalkan / opname sedang dihitung. Status penjualan: PARTIAL → PENDING_VERIFICATION → PAID.
- **UI:** kartu *Pelunasan sisa tagihan* di detail penjualan (tunai/QRIS, nominal, sisa/kembalian live), halaman **Piutang Pelanggan**
  (`pos/receivables`: total sisa, per pelanggan, umur piutang, QRIS pending, tombol Terima Pembayaran), struk & WhatsApp memberi label
  "Pelunasan tunai/QRIS".
- **Pengujian:** smoke test CLI **26/26** (kas awal: tanpa pembanding boleh, selisih tanpa catatan ditolak, dengan catatan tersimpan; pelunasan:
  validasi, 25.000 → sisa 35.000, klik ganda, QRIS pending tidak mengurangi sisa & tidak masuk kas, tunai berlebih hanya diterima sebesar sisa-bebas,
  verifikasi QRIS → PAID, penjualan lunas ditolak, EOD menghitung pelunasan tunai, retur setelah pelunasan refund penuh) + regresi POS 42, Broken 25,
  Rekonsiliasi 26, Retur 25, Stok Awal 19, Kas 33, Diskon 37 lulus. Uji browser: Buka Toko (pembanding Rp140.000, selisih −10.000 ditolak tanpa catatan,
  diterima dengan catatan), daftar piutang, pelunasan Rp100.000 dengan klik ganda → satu pembayaran, lunas, kembalian Rp40.000. Data/akun uji dihapus.
- **Catatan:** piutang POS terpisah dari modul Piutang Keuangan (B2B); belum ada pengingat jatuh tempo otomatis / batas kredit pelanggan.

### 2026-10-23 — Persiapan go-live (selesai): CSRF aktif, perintah akun staf, panduan & checklist

Permintaan user: "Siapkan go-live: aktifkan CSRF dan uji ulang, buat akun kasir/supervisor, panduan operasional singkat, dan checklist deploy."
- **CSRF diaktifkan:** `app/Config/Filters.php` (global `csrf`), `Security.php` (`regenerate=false` agar token halaman dipakai berulang oleh AJAX;
  `expires` 7200 → 28800 detik/8 jam), `layouts/blank.php` (halaman absensi publik kini mengirim token via `$.ajaxSetup`; sebelumnya tidak).
  Backup konfigurasi lama: `writable/backups/*.pre_csrf`. Audit: tidak ada `fetch()`/XHR mentah di view; form POST (login, logout) sudah
  memakai `csrf_field()`; modul sosmed sudah memakai token sendiri.
- **Uji ulang CSRF:** POST tanpa token (login, `/absensi/submit`, `/admin/pos/checkout`) → **403**; login & logout lewat form → OK;
  dua checkout JSON berturut-turut → OK (token tidak basi); pelanggan baru, tambah/hapus supplier (modul lama) → OK; absensi publik dengan
  token → sampai validasi (422), tanpa token → 403. Smoke test CLI tidak terpengaruh (tidak lewat HTTP).
- **Akun staf:** perintah CLI baru `php spark staff:create <username> "<Nama>" <role> [email]` (role: kasir, supervisor-toko, admin-inventori,
  finance, admin; password acak 12 karakter tampil **sekali** di terminal, tidak disimpan; tercatat di audit log) dan `php spark staff:list`
  (akun, role, status PIN). **Akun nyata tidak saya buat** — dibuat di server oleh tim agar password tidak melalui saya/chat.
  Supervisor menyetel PIN sendiri di menu POS → PIN Supervisor.
- **Dokumen:** [`PANDUAN-OPERASIONAL-POS.md`](PANDUAN-OPERASIONAL-POS.md) (peran & hak akses, alur harian buka→jual→QRIS→EOD→setoran→opname,
  kejadian khusus, aturan emas, bantuan cepat) dan [`CHECKLIST-DEPLOY-POS.md`](CHECKLIST-DEPLOY-POS.md) (backup, upload, 12 migration, keamanan,
  akun, data master, stok awal, uji asap, hari pertama, rollback, daftar yang belum tersedia).
- `.gitignore`: `/writable/backups/*` ditambahkan (dump DB berisi hash password).
- **Catatan temuan:** `.env` lokal kini berisi `app.baseURL = 'http://localhost:8080/'` (sebelumnya domain produksi) — bukan dari perubahan saya;
  pastikan `.env` produksi memakai `https://…` dan tidak ikut ter-upload. Data & akun uji dihapus; jumlah user kembali 6.

### 2026-10-23 — Fase 3 (hybrid offline): rancangan arsitektur ditulis (tanpa kode)

Permintaan user: "Mulai rencanakan Fase 3 (hybrid offline): tulis rancangan arsitektur, pilihan teknologi, dan tahapan, tanpa kode dulu."
Dokumen: [`FASE-3-RANCANGAN-OFFLINE.md`](FASE-3-RANCANGAN-OFFLINE.md). Ringkasan keputusan rancangan (**menunggu persetujuan owner**):
- **Arsitektur disarankan — "Store Node":** server di toko (CI4 yang sama + MariaDB lokal) menjadi **satu-satunya penulis** stok/kas toko; perangkat kasir
  hanyalah klien browser/PWA lewat LAN. Pusat menerima replikasi satu arah (*transactional outbox* → HTTPS, idempoten, berurutan per node) dan mengirim
  data master. Alasan: satu toko & satu lokasi stok → tidak ada split-brain; putus internet tidak berdampak ke POS. Opsi lain (pusat primer + antrean
  perangkat; failover dua penulis) dinilai kurang aman/kompleks.
- **Teknologi:** CI4 + MariaDB di PC toko (bukan SQLite: kode memakai row-lock/`ON DUPLICATE KEY` MySQL), HTTPS REST untuk sinkron (bukan replikasi DB),
  tabel `sync_outbox`/`sync_inbox` + Spark command terjadwal (tanpa broker), PWA/IndexedDB hanya untuk **Mode Darurat** opsional, Electron belakangan
  bila perlu, HTTPS di LAN lewat domain toko/CA lokal (syarat Service Worker & kamera).
- **Mode:** normal; internet putus (POS tetap jalan, sinkron tertunda); perangkat terputus dari server toko (Mode Darurat opsional: tunai saja, batas risiko,
  status `LOCAL → STORE_ACCEPTED/PENDING/CONFLICT`, tanpa klaim stok global akurat); server toko mati (pulihkan dari backup + replika pusat).
- **Kepemilikan data** per tabel (produk/akun dari pusat; harga retail, flag POS, transaksi dari toko; pelanggan dua arah dengan merge nomor telepon).
- **Tahapan & estimasi kasar:** 3.0 keputusan & kesiapan (test otomatis, git, perangkat, HTTPS) 3–5 hr · 3.1 Store Node mandiri 6–9 · 3.2 outbox & push
  12–18 · 3.3 pull master & akun 6–8 · 3.4 UX/operasional sinkron 4–6 · 3.5 Mode Darurat (opsional) 8–12 · 3.6 shell desktop (opsional) 4–6 ·
  3.7 pengerasan & DR 4–6 person-day; total inti ≈ 35–52, dengan opsional ≈ 47–70. Setelah 3.1 saja POS sudah kebal putus internet.
- **Temuan kesiapan kode:** nomor dokumen berbasis id lokal perlu prefix node; kode MySQL-spesifik; `accounts` dipakai bersama B2B; smoke test (±200 cek)
  masih di folder sementara, **harus dijadikan test otomatis di repo sebelum refactor**; sinkron jam (NTP) di server toko.
- **8 keputusan dibutuhkan dari owner** (bagian 11 dokumen): laptop kedua satu LAN?, perangkat server toko + UPS + perawat, domain/HTTPS, perlu Mode Darurat &
  batas risikonya, proyeksi keuangan per transaksi vs harian, akun yang direplikasi ke toko, rencana payment gateway, keputusan Keuangan yang menggantung.
- **Rencana uji:** 10 skenario kritis (cabut internet, kirim ulang batch, daya putus saat checkout, dua kasir stok terakhir, cabut WiFi perangkat,
  celah urutan event, restore dari backup, jam bergeser, merge pelanggan, EOD+opname saat offline).
- Tidak ada perubahan kode/DB pada langkah ini.

### 2026-10-23 — Jawaban owner atas keputusan Fase 3

- **WiFi** untuk laptop kedua (kemungkinan) → server toko disarankan berkabel; prosedur kertas untuk gangguan WiFi.
- **Pusat = shared hosting** → rancangan sisi pusat disesuaikan (cron, endpoint sinkron ringan & berbatch kecil, tanpa proses latar panjang); **server toko tetap butuh PC sendiri di toko**.
- **Mode Darurat tidak diperlukan** → tahap 3.5 dihapus dari lingkup; estimasi inti ≈ 35–52 person-day.
- **Proyeksi keuangan:** per transaksi **dan** rekap harian (rekap diturunkan dari event; tidak ada kerja sinkron tambahan).
- **Akun yang direplikasi:** usulan bawaan = kasir, supervisor toko, admin inventori + 2–3 Super Admin pilihan owner (menunggu konfirmasi).
- **Gateway:** Midtrans/DOKU nanti; sekarang manual. Webhook akan masuk ke pusat, toko mengambil statusnya lewat sinkron.
- Domain/HTTPS dijelaskan ke owner; tidak memblokir (diputuskan di 3.0). Rincian: `FASE-3-RANCANGAN-OFFLINE.md` §14.
- **Masih terbuka:** PC server toko (ada/perawat/UPS), konfirmasi akun yang disalin, keputusan Keuangan.

### 2026-10-23 — Keputusan user: lingkup hanya POS

User: "biarin aja kalo bukan pos, scope lu bahas pos aja". Temuan Final Report **F2–F10** (modul B2B, bukan POS) **dibiarkan** — tidak dikerjakan dan tidak menunggu keputusan lagi; F1 sudah diperbaiki karena menyesatkan. Pekerjaan berikutnya hanya yang terkait Kebuni Mart POS/stok/kas toko.

### 2026-10-23 — User: "2-8 gas aja, no 1 nanti" → tujuh fitur tambahan (selesai)

Daftar usulan nomor 2–8 dikerjakan; nomor 1 (test otomatis di repo + `git init`) **ditunda** atas permintaan user. Migration baru: `2026-10-24-000001_CreateSupplierReturnsAndPromos` (sudah dijalankan).

| Fitur | Isi | Menu / lokasi |
|---|---|---|
| **2. Kondisi Toko** | Dashboard hari ini: status buka/tutup & EOD terakhir, penjualan, rata-rata struk, laba kotor, diskon/void, kas laci (buku), QRIS belum disetor, piutang, grafik 7 hari, metode bayar, terlaris, daftar "Perlu perhatian" (QRIS pending, pengecualian, stok kritis, Broken Stock menunggu, opname berjalan), stok perlu perhatian | POS → Kondisi Toko (`admin/pos/today`, izin `pos.view`) · `PosDashboardService` |
| **3. Pengecualian** | Satu daftar kejadian di luar alur normal per rentang tanggal & jenis: override stok, diskon manual, void, pembayaran ditolak/dikoreksi, retur, selisih kas (buka & tutup), Broken Stock diposting/ditolak — lengkap dengan kasir, penyetuju, nilai, tautan ke dokumen | POS → Pengecualian (`admin/pos/exceptions`, izin `pos.void`/`pos.verify_payment`) · `PosExceptionService` |
| **4. Bukti setoran/QRIS** | Foto/PDF bukti (jpg/png/webp/pdf ≤ 5 MB) opsional saat setoran Kas Kasir dan settlement QRIS; disimpan di `writable/uploads/pos_proofs/`, dibuka lewat route berizin (bukan folder publik) | POS → Setoran & QRIS |
| **5. Saran pesan ulang** | Per produk POS: stok, minimum, rata-rata jual/hari (dari ledger SALE+PROMO_FREE−retur, 14 hari), hari cukup, status HABIS/KRITIS/MENIPIS/AMAN, saran qty (= hari cukup × rata-rata − stok, naik per 0,5), supplier terakhir; teks pesanan per supplier + tombol WhatsApp. Pengaturan `reorder_cover_days` (7) & `reorder_lookback_days` (14) di Pengaturan | Stok → Saran Pesan Ulang (`admin/stock/reorder`) |
| **6. Retur ke supplier** | Mutasi `SUPPLIER_RETURN` pada biaya rata-rata; tolak stok negatif, produk ganda, melebihi penerimaan yang dirujuk, dan saat opname sedang menghitung; alasan wajib; nilai nota kredit/refund hanya informasi (jurnal hutang menunggu keputusan Keuangan); nomor `RS-YYYYMMDD-nnnnn` | Stok → Retur ke Supplier; izin baru `stock.supplier_return` |
| **7. Promo terjadwal** | BOGO "beli X gratis Y" (maks. kelipatan opsional) dan harga bertingkat (qty ≥ N → harga promo, wajib di bawah harga normal). Diterapkan **di server** saat checkout; baris penjualan menyimpan `promo_id`; BOGO memakai mutasi `PROMO_FREE` (HPP tetap diakui); promo sejenis tumpang tindih ditolak; bisa dibatalkan (transaksi lama tak berubah). Kasir melihat lencana promo di pencarian & pratinjau harga/gratis di keranjang | POS → Promo Terjadwal (`admin/pos/promos`, izin `pos.price`) |
| **8. Mode latihan** | `php spark training:setup [--reset]` membuat DB terpisah `<db>_training` (struktur 74 tabel + data master) berisi 8 produk contoh bergaris harga & stok awal serta 3 akun (kasir/supervisor/admin; password & PIN acak, tampil sekali). `php spark training:serve [port]` menjalankan aplikasi di `localhost:8092` yang memakai DB latihan; bilah merah **MODE LATIHAN** + lencana "LATIHAN" di semua halaman. DB asli tidak tersentuh: penimpaan koneksi hanya lewat variabel `KEBUNI_DB_OVERRIDE` dan hanya diterima bila nama DB berakhiran `_training` (`app/Config/Database.php`); `training:seed` menolak jalan di DB lain | CLI: `TrainingSetup/Seed/Serve` |

**Pengujian**
- `pos:features-smoke` (sementara, dijalankan **di DB latihan**, tanpa menyentuh DB asli): **semua lulus** — promo (validasi, tier, BOGO maks. kelipatan, `promo_id`, HPP, retur memakai harga tier, pembatalan, jadwal masa depan), pengecualian (override/diskon/void/retur + filter), reorder, retur supplier (validasi + ledger + nilai), bukti setoran/QRIS, dashboard (angka = DB).
- Regresi 8 suite lama di DB asli (POS 42, Broken 25, Reconcile 26, Return 25, Opening 19, Cash 33, Discount 37, Balance 26): **semua lulus**.
- Browser (DB latihan): dashboard (grafik tampil), pengecualian, buat promo lewat UI (AJAX + CSRF), pratinjau kasir "gratis promo +2" & subtotal benar, unggah bukti setoran (PNG diterima; `.php` dan 6 MB ditolak; berkas dibuka via route berizin), retur supplier lewat UI; tanpa overflow horizontal di lebar 375 px.
- Bug yang ditemukan & diperbaiki saat uji: (a) `SupplierReturnService` error bila `settlement` tidak dikirim; (b) dashboard salah menghitung karena `pos_sales.sale_date` bertipe datetime (dibandingkan dengan tanggal); (c) label diskon persen tampil "nominal" (kode tipe `PCT`); (d) bilah latihan sticky menutupi header → dibuat non-sticky + lencana pojok.
- Pembersihan: DB latihan dihapus, berkas uji bukti dihapus, DB asli diverifikasi bersih (tabel POS kosong, transaksi keuangan maks. id 137, 6 pengguna, tanpa `is_pos`).

**Catatan**
- Nomor dokumen (RS-, SET-, dll.) diturunkan dari id; validasi yang gagal setelah baris header dibuat (mis. qty melebihi stok) memakai satu nomor id sehingga ada loncatan nomor. Tidak memengaruhi saldo; bila auditor mempermasalahkan, validasi dapat dipindah sebelum insert.
- Promo mengubah **harga satuan** pada baris penjualan; diskon manual dialokasikan ke baris NORMAL seperti sebelumnya (tidak ada diskon di atas baris gratis).
- Harga bertingkat dan BOGO pada produk yang sama dapat aktif bersamaan (jenis berbeda); markdown tetap didahulukan oleh `PosPricingService` sebagai harga dasar.

### 2026-10-24 — Temuan hosting: biaya penerimaan salah input → pengaman form + skrip bersih

Di hosting, biaya rata-rata stok terlalu kecil (mis. Mangga Rp221/kg, Sawi Putih Rp1.333/kg) sehingga HPP, laba kotor, dan estimasi Broken Stock menyesatkan. **Penyebab:** penerimaan GR-20261023-00001 diisi dengan harga per kg pada kolom *Total biaya baris* (mis. 13.334 untuk 10 kg, seharusnya 133.340). Rumus HPP/laba kotor/moving average **benar**; yang kurang adalah pengaman input.

Perbaikan kode:
- Penerimaan Barang: kolom diberi label "Total biaya baris — bukan per unit", tampil otomatis "= Rp … / unit (acuan beli Rp …)" dengan peringatan bila selisih > 50% dari harga beli periode terakhir; posting meminta konfirmasi bila ada baris janggal.
- Server menolak baris reguler dengan biaya ≤ 0 (`GoodsReceiptService`); baris hanya-bonus tetap boleh Rp0.
- Detail penerimaan: kolom baru "Biaya / unit".
- Stok Awal: sel biaya ditandai kuning dan konfirmasi posting menyebut produk yang biayanya jauh dari acuan.
- Uji: validasi service (3/3), form di browser (peringatan & konfirmasi), regresi 8 suite lulus; DB latihan dihapus.

Data: skrip `docs/sql-bersihkan-data-uji-hosting.sql` (dibuat dari dump hosting 24 Okt: 1 penerimaan/22 baris, 23 ledger, 1 penjualan QRIS, 1 hari kas OPEN, 1 transaksi keuangan id 138) menghapus data uji dan mempertahankan produk, harga retail, 2 Broken Stock DRAFT, dan seluruh data B2B. Setelah dijalankan, penerimaan diinput ulang dengan total = qty × harga per unit.

### 2026-10-25 — Laporan penjualan harian (cetak/PDF + CSV)

Permintaan user: data penjualan per hari belum bisa ditarik / dijadikan PDF. Ditambahkan di *Penjualan POS* (mengikuti filter tanggal): **Laporan harian (cetak / PDF)** — ringkasan per hari (transaksi, omzet, diskon, HPP, laba kotor, margin %, tunai, QRIS, sisa tagihan, void, retur), penjualan per produk, dan rincian transaksi; plus **CSV ringkasan harian** dan **CSV rincian transaksi** (UTF-8, pemisah `;`, bisa dibuka di Excel). Route `admin/pos/sales/report` (izin `pos.view`; kasir hanya mendapat transaksinya sendiri; rentang maks. 1 tahun). File: `PosSalesReportService`, `PosController::salesReport`, `pos/sales_report_print.php`.
Uji (DB latihan): angka sama dengan dashboard (omzet 222.300, laba 19.500); tunai 150.300 + QRIS 72.000 = omzet; void tidak dihitung omzet; CSV header & nama file benar; akun kasir berfungsi. Dua bug ditemukan saat uji dan diperbaiki sebelum selesai (salinan array pada closure; kembalian terhitung ganda). Retur ditampilkan terpisah dan belum dikurangkan dari omzet.

### 2026-10-25 — Stok Awal: lepas dari Price Period + umpan balik tombol

Temuan user di layar Stok Awal: (1) tombol "+ Semua produk POS" terasa tidak aktif; (2) biaya per unit masih terisi dari **Price Period** (modul B2B, mis. "Harga beli PP-2026-07-21") padahal POS harus berdiri sendiri.
- (1) Tombol memang hanya menambah produk POS yang **belum punya mutasi stok** — produk yang sudah punya penerimaan/ledger tidak bisa diberi stok awal, jadi bila semuanya sudah bermutasi tidak ada yang ditambahkan dan tidak ada pesan. Sekarang selalu ada notifikasi: jumlah yang ditambahkan, atau alasan tidak ada yang ditambahkan (berikut jumlah produk yang sudah bermutasi).
- (2) Biaya default dari Price Period **dihapus**: `StockOpeningService::defaultCost()` mengembalikan 0 (isi manual dari nota/hitungan). Acuan peringatan kewajaran di Penerimaan Barang kini memakai **biaya per unit pembelian POS terakhir** dari ledger, bukan Price Period. Tidak ada lagi kode POS yang membaca `price_period*` (hanya modul B2B).
- Uji: browser (tombol POS/aktif menambah, klik ulang → pesan alasan, biaya kosong), suite Stok Awal: 1 uji lama yang memeriksa "biaya default = Price Period" sengaja tidak berlaku lagi (selebihnya lulus). DB latihan dihapus.

### 2026-10-25 — Metode bayar baru: Transfer ke Bank Utama

Permintaan user: selain tunai dan QRIS, tambah **transfer ke rekening (Bank Utama)**. Keputusan desain: Transfer berperilaku seperti QRIS — status "menunggu verifikasi" (supervisor/finance mengecek mutasi bank), tetapi setelah dikonfirmasi uang **langsung masuk ke Bank Utama** (tanpa QRIS Clearing, tanpa MDR, tanpa settlement; tidak ikut hitungan kas laci/EOD tunai dan tidak perlu disetor).
- Migration `2026-10-25-000001_AddTransferPaymentMethod`: enum `pos_payments.method/corrected_method` dan `pos_payment_corrections.from/to_method` + `TRANSFER`; setting `pos_transfer_account` (rekening "Bank Utama"; fallback otomatis ke nama itu bila setting kosong). Untuk hosting: `docs/sql-tambah-transfer.sql`.
- Kode: `PosSaleService` (konstanta `METHODS`, `label()`, `accountFor()`; checkout, pelunasan sisa tagihan, verifikasi/tolak), `PosReturnService` (koreksi metode ke/dari Transfer, pesan retur), `DailyClosingService` (ringkasan EOD menghitung QRIS+Transfer menunggu verifikasi), laporan penjualan (kolom Transfer di cetak & CSV), view kasir/struk/piutang/retur/EOD/dashboard.
- Uji: `pos:transfer-smoke` di DB latihan **21/21 lulus** (checkout pending, verifikasi → Bank Utama +, Kas Kasir tetap, tolak, pelunasan lewat transfer, koreksi Tunai→Transfer memindahkan dana, void merefund dari Bank, tidak masuk settlement QRIS, laporan); regresi 7 dari 8 suite lulus (1 uji lama "biaya default = Price Period" memang sudah tidak berlaku); browser: radio Transfer di kasir, checkout, konfirmasi di struk, pilihan koreksi. DB latihan dihapus.

### 2026-10-26 — Omzet dilaporkan bersih setelah retur

Pertanyaan user: retur seharusnya tidak masuk omzet (uang kembali, barang kembali). Benar — sebelumnya kartu "Penjualan (omzet)" di Penjualan POS dan Kondisi Toko menampilkan angka kotor (retur hanya disebut di baris kecil), dan laba kotor tidak dikurangi retur; Analisa Laba sudah benar. Sekarang seragam: **omzet bersih = omzet kotor − nilai retur** dan **HPP bersih = HPP − biaya pokok asli barang yang diretur**, pada tanggal retur (barang retur yang rusak tetap muncul sebagai known shrinkage).
- Penjualan POS: kartu "Penjualan bersih (omzet − retur)" + rincian kotor/retur, kartu "HPP bersih" + laba kotor.
- Kondisi Toko: penjualan bersih, laba kotor bersih, grafik 7 hari bersih. Juga memperbaiki "Metode bayar hari ini" yang mengurangi kembalian dua kali (kolom `amount` sudah bersih).
- Laporan harian & CSV: kolom Omzet kotor, Retur, Omzet bersih, HPP bersih, Laba kotor, Margin (dari omzet bersih). Retur tidak ditampilkan pada laporan per kasir.
- Uji (DB latihan): omzet 222.300 − retur 6.000 = 216.300; HPP 202.800 − 5.800 = 197.000; laba 19.300; laporan = dashboard; suite fitur & transfer lulus.

### 2026-10-26 — Laporan Buying Power (basket size)

Permintaan user: data buying power = NOI (Number of Invoice) dan Sales, plus per produk "dibeli berapa kali dan total qty-nya" (contoh Mangga). Rumus yang dipakai: **Buying power = Sales ÷ NOI** (rata-rata belanja per invoice; di pesan user tertulis NOI ÷ Sales, itu terbalik).
- Menu baru **POS → Buying Power** (`admin/pos/buying-power`, izin `finance.view`; `PosBuyingPowerService`, `PosBuyingPowerController`, `pos/buying_power.php`). Filter tanggal + pintasan (bulan ini/lalu/30/7 hari).
- Ringkasan: NOI, Sales (omzet kotor, void tidak dihitung, retur belum dikurangkan), buying power, isi keranjang rata-rata (jenis & qty per invoice); grafik buying power & NOI per hari.
- Per produk: **Dibeli (jumlah invoice yang memuat produk)**, penetrasi (% dari NOI), total qty (+ qty gratis), total sales, rata-rata qty/invoice, rata-rata sales/invoice, dan **basket size** (rata-rata total invoice yang berisi produk itu). Pencarian produk di tabel; CSV produk dan CSV harian.
- Uji (DB latihan): 6 invoice, sales 222.300, buying power 37.050; Beras 2 invoice/qty 2; Kopi 2 invoice/qty 11 + 3 gratis — cocok hitungan manual; halaman & CSV dicek di browser. Bug kecil saat uji: alias `lines` adalah kata cadangan MySQL (diganti).

Tambahan (26 Okt): tombol **Cetak / PDF** di halaman Buying Power (`?print=1` → `pos/buying_power_print.php`, Cetak / Simpan PDF dari browser): ringkasan, tabel per hari, dan tabel per produk (dibeli, penetrasi, qty, gratis, sales, rata-rata, basket size) + catatan rumus. Isi dicek di DB latihan; DB dihapus.

Perbaikan tampilan cetak (26 Okt, permintaan "lebih proper"): `buying_power_print.php` dirombak — A4 **landscape**, kop dengan logo & nama toko, kartu ringkasan (buying power disorot), grafik batang buying power per hari (SVG, tanpa JS), dua bagan peringkat (paling sering dibeli & penjualan terbesar), tabel rincian dengan peringkat, zebra, baris total, header berulang antar halaman, nomor halaman "Halaman x dari y" (didukung Chrome/Edge baru), catatan membaca laporan & definisi, dan kolom tanda tangan (dibuat/diperiksa/disetujui). Tidak memakai library PDF (tidak ada di proyek); PDF dibuat lewat Cetak → Save as PDF.

Istilah (masukan owner 26 Okt 22.07): kolom "Dibeli (invoice)" diganti **NoI**, dan **NoI by article tinggi = most wanted item**. Diterapkan di layar, cetak/PDF, dan CSV (penulisan "NoI"); tabel produk kini diurutkan **NoI tertinggi** (lalu sales), tiga teratas diberi lencana "Most wanted", bagan peringkat di cetak berjudul "Most wanted item — NoI by article tertinggi", dan catatan laporan menjelaskan artinya.

### 2026-10-27 — Hourly Sales Report (golden time & karakter belanja)

Permintaan owner: hourly sales report untuk mengetahui golden time dan karakter belanja konsumen. Menu baru **POS → Hourly Sales** (`admin/pos/hourly`, izin `finance.view`; `PosHourlySalesService`, `PosHourlyController`, `pos/hourly.php`, `pos/hourly_print.php`).
- Filter periode + hari (semua / hari kerja / akhir pekan / satu hari). Basis: transaksi Diposting per jam transaksi; void tidak dihitung; sales kotor sebelum retur.
- Kartu: NoI, Sales, Buying power, **Golden time** (jam dengan sales tertinggi) + **golden window** (3 jam berurutan terkuat) + jam tersepi dalam jam operasional.
- Grafik sales (batang, golden time oranye) & NoI (garis) per jam; **heatmap hari × jam**; tabel detail per jam (rata-rata NoI per hari aktif).
- **Karakter belanja per bagian hari** (dini 00–04, pagi 05–10, siang 11–14, sore 15–17, malam 18–23): NoI, sales, % sales, buying power, qty/invoice, 3 artikel paling dicari (NoI), dengan kesimpulan otomatis ("ramai & belanja besar", "ramai tapi belanja kecil", "sepi & belanja kecil", dst. dibanding rata-rata).
- Cetak/PDF landscape (kop logo, kartu, grafik SVG, heatmap, tanda tangan) + CSV per jam dan per bagian hari.
- Uji (DB latihan, 6 invoice disebar ke jam berbeda + 1 void di jam 17.30): per jam cocok hitungan manual (jam 18 = Rp136.800, 61,5%; void tidak ikut), golden window 16–19 = 77,5%, filter weekend/hari tunggal benar; layar, heatmap, grafik, dan halaman cetak dicek di browser.
- Panduan PDF diperbarui dengan menu Buying Power & Hourly Sales.

---

## Rencana langkah (status)

| # | Langkah | Status |
|---|---------|--------|
| 1 | Audit skema & konvensi MIS | ✅ Selesai |
| 2 | Desain model data & migration (backup DB dulu) | ✅ Selesai (sudah di-migrate) |
| 3 | Model + service ledger (moving average, idempotensi, penolakan stok negatif) | ✅ Selesai (lulus smoke test) |
| 4 | Controller, route, permission/role Kasir & Supervisor | ✅ Selesai |
| 5 | View: checkout, struk WhatsApp, penerimaan barang/bonus, buku stok | ✅ Selesai |
| 6 | EOD (tutup kas harian) + stock opname setelah tutup toko, input dari HP | ✅ Selesai (permintaan user, dari Fase 2) |
| 7 | Uji alur (CLI 42/42 + browser desktop & HP) | ✅ Selesai |
| 8 | Broken Stock (known shrinkage) + Laporan Penyusutan | ✅ Selesai (CLI 25/25, regresi 42/42, browser) |
| 9 | Prosedur owner: blind count → rekonsiliasi → hitung ulang → konfirmasi → posting; Analisa Laba & matriks produk | ✅ Selesai (CLI 26/26, regresi 25/25 & 42/42, browser) |
| 10 | Retur pelanggan (termasuk setelah EOD) & koreksi metode bayar | ✅ Selesai (CLI 25/25, regresi 42/42 & 25/25 & 26/26, browser) |
| 11 | Audit rumus Final Report (Fase 0) + layar Stok Awal (OPENING) | ✅ Selesai (audit di docs/AUDIT-FINAL-REPORT.md; CLI 19/19, semua regresi lulus, browser) |
| 12 | Perbaikan F1 Final Report (stok aktual kosong ≠ susut) | ✅ Selesai (CLI 6/6, browser); F2–F10 menunggu keputusan |
| 13 | Setoran Kas Kasir + settlement QRIS/MDR + kas awal seharusnya | ✅ Selesai (CLI 33/33, regresi lulus, browser) |
| 14 | Diskon manual dengan izin + PIN supervisor di layar kasir (diskon & override stok) | ✅ Selesai (CLI 37/37, regresi lulus, browser) |
| 15 | Selisih kas awal di Buka Toko + pelunasan sisa tagihan pelanggan (**Fase 2 tuntas**) | ✅ Selesai (CLI 26/26, regresi lulus, browser) |
| 16 | Persiapan go-live: CSRF aktif & teruji, perintah akun staf, panduan operasional, checklist deploy | ✅ Selesai (siap dieksekusi tim; deploy produksi oleh tim) |
| 17 | Fase 3 hybrid offline — rancangan arsitektur, teknologi, tahapan (tanpa kode) | 📝 Rancangan selesai; 7 dari 8 butir dijawab owner (sisa: PC server toko, konfirmasi akun, keputusan Keuangan) |
| 18 | Dashboard Kondisi Toko + halaman Pengecualian supervisor | ✅ Selesai (smoke di DB latihan, browser) |
| 19 | Bukti foto setoran/settlement QRIS + Saran Pesan Ulang (reorder) | ✅ Selesai |
| 20 | Retur ke supplier (`SUPPLIER_RETURN`) + promo terjadwal BOGO/harga bertingkat | ✅ Selesai (regresi 8 suite lulus) |
| 21 | Mode latihan (DB `_training` terpisah, `training:setup/serve`) | ✅ Selesai |
| 22 | Metode bayar **Transfer** ke Bank Utama (verifikasi manual, koreksi, void, laporan) + `sql-tambah-transfer.sql` | ✅ Selesai (21/21 uji, regresi lulus) — belum diunggah ke hosting |
| 23 | Omzet & laba **bersih setelah retur** di Penjualan POS, Kondisi Toko, laporan harian | ✅ Selesai — belum diunggah |
| 24 | Laporan penjualan harian (cetak/PDF + CSV) | ✅ Selesai — belum diunggah |
| 25 | Pengaman input biaya: Penerimaan Barang (per-unit, peringatan, tolak biaya 0), Stok Awal lepas dari Price Period, umpan balik tombol | ✅ Selesai — belum diunggah |
| 26 | **Buying Power** (Sales÷NoI, NoI by article/most wanted) + cetak PDF + CSV | ✅ Selesai — belum diunggah |
| 27 | **Hourly Sales** (golden time, heatmap, karakter belanja) + cetak PDF + CSV | ✅ Selesai — belum diunggah |
| 28 | Dokumen serah terima agent (`HANDOVER-AI-AGENT.md`) + arsip smoke test (`docs/smoke-tests/`) | ✅ Selesai |

## Belum dikerjakan
- **Hosting (tindakan owner, bukan kode):** jalankan `sql-bersihkan-data-uji-hosting.sql` → `sql-tambah-transfer.sql`; unggah file terbaru (daftar per fitur ada di entri masing-masing); input ulang penerimaan barang dengan total baris = qty × harga/unit; baru setujui 2 Broken Stock DRAFT.
- **Usulan belum diputuskan:** pengaman retur bila alasan "rusak" tetapi kondisi "Layak jual"; opname sebagian/bergilir; samakan tampilan cetak laporan penjualan harian dengan Buying Power/Hourly; Buying Power/Hourly versi omzet bersih setelah retur.
- **Fase 2:** ✅ tuntas (tidak ada sisa pekerjaan fungsional).
- **Fase 0 sisa:** ~~perbaikan Final Report F2–F10~~ (di luar lingkup POS — dibiarkan, keputusan user 2026-10-23); input stok aktual periode 116; input stok awal toko lewat blind
  count go-live (layar sudah ada); harga retail produk di Katalog.
- **Fase 3:** implementasi sesuai `FASE-3-RANCANGAN-OFFLINE.md` setelah keputusan owner. **Fase 4:** pilot.
- Smoke test belum menjadi test otomatis di repo (versi sementara menjalankan TRUNCATE tabel POS, jadi tidak disimpan di `app/`). **Ditunda atas permintaan user ("no 1 nanti")**: test otomatis + `git init`; sekarang lebih mudah karena `KEBUNI_DB_OVERRIDE` + DB `_training` bisa dipakai sebagai DB uji tanpa menyentuh DB asli.

## Pertanyaan terbuka dari PRD (A6) yang memengaruhi kode
1. Bonus supplier ikut menurunkan rata-rata biaya? → saya buat **kebijakan dapat dikonfigurasi** (`settings`: `bonus_cost_policy` = `allocate`/`zero`), default `allocate` sampai Keuangan memutuskan.
2. Jumlah provider QRIS → satu akun QRIS Clearing dulu.
3. Laptop kedua (LAN) → di luar Fase 1.
4. Override stok nol oleh supervisor → diimplementasikan (default sesuai rekomendasi PRD).
