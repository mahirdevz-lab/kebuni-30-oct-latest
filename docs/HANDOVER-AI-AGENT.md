# HANDOVER — Kebuni Mart POS & Inventori (untuk agent AI / developer berikutnya)

Terakhir diperbarui: **27 Oktober 2026**. Dokumen ini adalah titik masuk. Log kronologis lengkap ada di [`POS-PROGRESS.md`](POS-PROGRESS.md);
panduan pengguna di [`PANDUAN-OPERASIONAL-POS.md`](PANDUAN-OPERASIONAL-POS.md) dan [`PANDUAN-UPDATE-KEBUNI-MART-POS.pdf`](PANDUAN-UPDATE-KEBUNI-MART-POS.pdf).

---

## 1. Konteks & tujuan
- **Aplikasi**: MIS/Back Office perusahaan trading (modul B2B: Sales Order, Price Period, Pricing Engine, Keuangan, Absensi, Sosmed, dst.) yang **ditambah modul Kebuni Mart POS & Inventori** (toko retail).
- **Sumber kebutuhan**: PRD "PRD_Kebuni_Mart_Hybrid_POS" (docx, dari owner). Dikerjakan bertahap: Fase 1 (POS online + ledger stok) dan Fase 2 (kontrol kas/QRIS/diskon/retur dst.) **selesai**; Fase 3 (offline Store Node) baru **rancangan**.
- **Pengguna**: owner (Sajidan Kowsoleea), supervisor toko, kasir (1 kasir per hari, tanpa shift), admin inventori, finance. Bahasa komunikasi & UI: **Bahasa Indonesia**.
- **Aturan lingkup dari owner**: *"scope lu bahas POS aja"* — modul B2B/Final Report (temuan F2–F10, mis. saldo negatif Kol/Kentang C) **sengaja dibiarkan**. Jangan memperbaikinya kecuali diminta.
- **Aturan kerja dari owner**: setiap langkah **dicatat di `docs/POS-PROGRESS.md`**; semua perubahan diuji dan data uji dibersihkan; jawaban ringkas (owner sering minta "bahasa singkatnya").

## 2. Stack & lingkungan
| Hal | Nilai |
|---|---|
| Framework | CodeIgniter **4.7** (Controller → Service → Model), PHP **8.2** |
| DB | MySQL/MariaDB. Lokal: `kebuni_22_oct`. Hosting (shared hosting): `u232246668_kebuni` (phpMyAdmin, kemungkinan **tanpa SSH**) |
| Lokal | XAMPP: `C:\xampp-php-82\htdocs\kebuni-22-oct`. **PHP di PATH = 7.4 → pakai `C:\xampp-php-82\php\php.exe`**. MySQL: `C:\xampp-php-82\mysql\bin` (start manual: `mysqld.exe --defaults-file=mysql\bin\my.ini --standalone`) |
| Frontend | Bootstrap 5, jQuery, Chart.js 4 (CDN), Font Awesome; AJAX memakai helper `ajaxPost`, `showToast`, `confirmAction`, `formatRupiah` (di layout) |
| Git | **Belum ada repo git** (ditunda atas permintaan owner). Deploy = unggah file manual / zip |
| `.env` | Berisi baseURL produksi `https://mis.kebuni.id/` + `CI_ENVIRONMENT=production` tetapi DB lokal. **Jangan menjalankan `spark serve` apa adanya** (redirect ke domain produksi). Gunakan `php spark training:serve` (router paksa localhost). File `.env` kadang dipindahkan pemilik saat membuat zip; jangan dibuat ulang/ditimpa |

## 3. Arsitektur & prinsip yang WAJIB dijaga
1. **Satu otoritas stok: `StockLedgerService::post()`** — semua perubahan stok lewat mutasi di `stock_ledger` (moving average, `stock_balances` dikunci `FOR UPDATE`, idempoten via unique key). Tidak ada edit saldo manual. Koreksi = mutasi baru / `reverse()`. Tipe mutasi: PURCHASE, SALE, PROMO_FREE, CUSTOMER_RETURN, BROKEN, OPENING, OPNAME_ADJUST, SUPPLIER_RETURN, dst.
2. **Keuangan POS lewat `financial_transactions`** (`reference_type` + `reference_id` + `reference_seq` unik). Setoran/settlement/koreksi metode = **transfer internal (TRANSFER_OUT/IN), bukan omzet**.
3. **Idempotensi**: `client_uuid` pada penjualan & pelunasan (klik ganda tidak menggandakan).
4. **Data tidak dihapus**: void (hari kas masih OPEN), retur (setelah EOD), reversal. Semua tindakan sensitif masuk `AuditLogService`.
5. **Hak akses di server**: filter `role:slug1,slug2` (salah satu izin cukup) di `app/Config/Routes.php`; sidebar (`app/Views/partials/sidebar.php`) hanya menyembunyikan.
6. **CSRF aktif global** (header `X-CSRF-TOKEN` lewat `$.ajaxSetup`, `regenerate=false`). Form/AJAX baru harus mengikuti pola yang ada.
7. **PIN supervisor** (`SupervisorAuthService`): diverifikasi **sebelum** transaksi DB; salah 5× → kunci 15 menit; PIN tidak pernah ke storage browser.
8. **Mode latihan**: koneksi DB hanya bisa ditimpa lewat env `KEBUNI_DB_OVERRIDE` dan **hanya bila nama berakhiran `_training`** (`app/Config/Database.php`).

## 4. Aturan bisnis penting (jangan diubah tanpa keputusan owner)
- Toko dibuka dengan **kas awal** (bandingkan dengan "kas awal seharusnya" = kas fisik EOD terakhir ± mutasi Kas Kasir sesudahnya; selisih **wajib catatan**). Checkout hanya saat toko buka. **EOD** mengunci checkout; void hanya selama hari kas OPEN.
- **Stok tidak boleh negatif** kecuali **override supervisor** (alasan wajib; masuk laporan Pengecualian).
- **Diskon manual** butuh izin supervisor (login atau PIN), alasan wajib, <100%, dialokasikan ke baris NORMAL.
- **Metode bayar**: Tunai (langsung lunas, ke Kas Kasir) · **QRIS** (menunggu verifikasi → masuk *QRIS Clearing*; disetor ke bank lewat *Settlement* + MDR) · **Transfer** (menunggu verifikasi → langsung ke rekening *Bank Utama*, tanpa clearing/MDR; setting `pos_transfer_account`). Pembayaran kurang → sisa tagihan butuh pelanggan; pelunasan susulan tunai butuh toko buka.
- **Opname**: dilakukan **setelah EOD**; *blind count* (petugas tak melihat saldo); semua produk POS wajib terisi (0 bila habis); selisih ≥ 5% **atau** ≥ Rp50.000 wajib hitung ulang; lalu konfirmasi & posting. Selisih kurang = *unknown shrinkage*; **Broken Stock** (susut diketahui, foto + persetujuan) harus diselesaikan sebelum opname. Penerimaan/retur supplier **terkunci selama opname berjalan**.
- **Stok awal (OPENING)**: sekali saat go-live per produk; produk yang sudah punya mutasi tidak bisa. Biaya per unit **diisi manual** (tidak lagi dari Price Period).
- **Penerimaan barang**: kolom biaya = **TOTAL baris** (qty × harga/unit), bukan per unit; ada hitungan "per unit" + peringatan bila menyimpang >50% dari pembelian POS terakhir; biaya reguler 0 ditolak; baris bonus-saja boleh Rp0. Kebijakan biaya bonus `bonus_cost_policy` (`allocate` default / `zero`).
- **Retur pelanggan**: refund = harga yang benar-benar dibayar; kondisi barang per item **Layak jual** (masuk stok) atau **Rusak** (masuk lalu otomatis Broken Stock). Alasan retur hanya catatan.
- **Omzet bersih = omzet kotor − retur; HPP bersih = HPP − biaya pokok barang yang diretur**, pada tanggal retur (dipakai di Penjualan POS, Kondisi Toko, Laporan harian, Analisa Laba). **Pengecualian**: laporan *Buying Power* & *Hourly Sales* memakai **sales kotor** (per invoice, void tidak dihitung).
- **Promo** diterapkan **di server** saat checkout (preview di layar hanya gambaran): BOGO (mutasi PROMO_FREE, HPP tetap diakui) dan harga bertingkat (harus < harga normal); promo sejenis tumpang tindih ditolak.
- **Buying power = Sales ÷ NoI** (NoI = Number of Invoice). **NoI by article tinggi = most wanted item** (istilah owner).

## 5. Katalog fitur (menu → kode)
Rute berada di grup `admin/` (lihat `app/Config/Routes.php`). Controller di `app/Controllers`, service di `app/Services`, view di `app/Views/pos|stock`.

| Menu (sidebar) | URL `admin/…` | Controller → Service | Izin | Fungsi |
|---|---|---|---|---|
| Kondisi Toko | `pos/today` | PosDashboardController → PosDashboardService | pos.view | Dashboard harian: status toko/EOD, penjualan **bersih**, laba kotor, kas, QRIS pending, piutang, tren 7 hari, "Perlu perhatian", stok kritis |
| Buka / Tutup Kas | `pos/day` | DailyClosingController → DailyClosingService | pos.close_day | Buka toko (kas awal + selisih), EOD (kas fisik vs seharusnya) |
| Kasir | `pos` (+`pos/products`, `customers`, `checkout`, `verify-supervisor`) | PosController → PosSaleService, PosPricingService, PromoService, SupervisorAuthService | pos.sell | Checkout tunai/QRIS/Transfer, gratis manual, diskon, override stok, pratinjau promo |
| Penjualan POS | `pos/sales`, `pos/sales/(id)` struk, `…/void`, `…/pay`, `payments/(id)/verify`, **`pos/sales/report`** | PosController → PosSaleService, PosSalesReportService | pos.view (+pos.void, pos.verify_payment) | Daftar & struk (WhatsApp/cetak), void, verifikasi QRIS/Transfer, pelunasan, **laporan harian cetak/PDF + CSV** |
| Piutang Pelanggan | `pos/receivables` | PosController | pos.view | Daftar sisa tagihan |
| Pengecualian | `pos/exceptions` | PosExceptionController → PosExceptionService | pos.void \| pos.verify_payment | Override, diskon, void, pembayaran ditolak/dikoreksi, retur, selisih kas, Broken Stock |
| PIN Supervisor | `pos/pin` | PosController → SupervisorAuthService | pos.discount \| pos.override | Setel PIN pribadi |
| Retur & Koreksi | `pos/returns`, `pos/sales/(id)/return`, `pos/payments/(id)/correct` | PosReturnController → PosReturnService | pos.return, pos.payment_correct | Retur barang, koreksi metode bayar (Tunai/QRIS/Transfer) |
| Setoran & QRIS | `pos/cash` (+`cash/proof/…`) | PosCashController → PosCashService | pos.cash_deposit, pos.qris_settle | Setoran Kas Kasir→bank, settlement QRIS + MDR, bukti foto/PDF (`writable/uploads/pos_proofs/`) |
| Katalog & Harga | `pos/prices` | PosPriceController → PosPricingService | pos.price | Produk POS, harga retail berjangka, barcode, stok minimum, markdown |
| Promo Terjadwal | `pos/promos` | PosPromoController → PromoService | pos.price | BOGO & harga bertingkat berjadwal |
| Stok Awal | `stock/opening` | StockOpeningController → StockOpeningService | stock.opening, stock.opening_post | Draft → posting stok awal |
| Saran Pesan Ulang | `stock/reorder` | ReorderController → ReorderService | stock.view | Status HABIS/KRITIS/MENIPIS/AMAN, saran qty, teks pesanan WhatsApp |
| Retur ke Supplier | `stock/supplier-returns` | SupplierReturnController → SupplierReturnService | stock.supplier_return | Mutasi SUPPLIER_RETURN, nilai nota kredit informasi saja |
| Penerimaan Barang | `stock/receipts` | StockController → GoodsReceiptService | stock.receive | Barang masuk reguler+bonus, biaya per baris |
| Saldo Stok / Buku Stok | `stock`, `stock/book` | StockController | stock.view | Saldo (qty, biaya rata-rata, nilai) & seluruh mutasi |
| Stock Opname | `stock/opname` | StockOpnameController → StockOpnameService | stock.opname, stock.opname_approve | Hitung buta dari HP → rekonsiliasi → hitung ulang → posting |
| Broken Stock | `stock/broken` | BrokenStockController → BrokenStockService | stock.broken, stock.broken_approve | Susut diketahui (foto, persetujuan) |
| Laporan Penyusutan | `stock/shrinkage` | StockController → ShrinkageReportService | stock.view | Known vs unknown shrinkage, high risk |
| Analisa Laba & Produk | `pos/analysis` | AnalysisController → ProfitShrinkageService | finance.view | Laba setelah susut (neto retur), matriks cepat/lambat × margin |
| Buying Power | `pos/buying-power` (+`?print=1`, `?format=csv&type=product|daily`) | PosBuyingPowerController → PosBuyingPowerService | finance.view | Sales÷NoI, NoI & basket size per artikel, most wanted |
| Hourly Sales | `pos/hourly` (+`?print=1`, `?format=csv&type=hourly|daypart`) | PosHourlyController → PosHourlySalesService | finance.view | Golden time/window, heatmap hari×jam, karakter belanja per bagian hari |

**CLI (`app/Commands`)**: `staff:create <username> "<Nama>" <role> [email]` (password acak tampil sekali), `staff:list`, `training:setup [--reset]`, `training:seed` (internal), `training:serve [port]` (default 8092).

**Role**: `super-admin`, `admin`, `finance`, `hr`, `kasir`, `supervisor-toko`, `admin-inventori` (izin per role dibuat oleh migration; `super-admin` memiliki semuanya).

**Settings (tabel `settings`)**: `pos_cash_account`, `pos_qris_account`, `pos_transfer_account`, `pos_location_id`, `pos_store_name`, `pos_store_whatsapp_footer`, `bonus_cost_policy`, `opname_variance_pct` (5), `opname_variance_value` (50000), `shrinkage_high_risk_pct` (5), `reorder_cover_days` (7), `reorder_lookback_days` (14), `company_name`, `attendance_late_after`.

## 6. Skema database (migration POS, berurutan)
| Migration | Isi |
|---|---|
| 2026-10-23-000001 FixFinanceReferenceTypesForPos | Enum `financial_transactions.reference_type` + perbaikan 10 baris "Pemindahan Dana" lama |
| …000002 CreatePosFoundation | `stock_locations`, `pos_product_prices`, `pos_markdowns`, `goods_receipts(+_items)`, `stock_balances`, `stock_ledger`, `pos_sales`, `pos_sale_items`, `pos_payments`, `pos_stock_overrides`; kolom POS di `products`/`accounts`; akun Kas Kasir & QRIS Clearing; role/izin awal; settings |
| …000003 CreateDailyClosingAndOpname | `pos_daily_closings`, `stock_opname_sessions/lines` |
| …000004 CreateBrokenStock | `stock_broken_reports` |
| …000005 AddOpnameReconciliation | `stock_opname_counts`, ronde hitung, ambang selisih |
| …000006 CreatePosReturns | `pos_returns`, `pos_return_items`, `pos_payment_corrections` |
| …000007 CreateStockOpenings | `stock_openings`, `stock_opening_lines` |
| …000008 MakeOpnameStokAktualNullable | `stock_opnames.stok_aktual` nullable (0 lama → NULL = "belum dihitung") |
| …000009 CreateCashDepositsAndQrisSettlements | `pos_cash_deposits`, `pos_qris_settlements` |
| …000010 AddFtBoundaryToDailyClosings | `ft_max_id` batas EOD |
| …000011 AddDiscountAndSupervisorPin | kolom diskon, `users.pin_hash/pin_failed/pin_locked_until` |
| …000012 AddBalancePaymentColumns | `pos_payments.client_uuid/is_balance` (pelunasan) |
| 2026-10-24-000001 CreateSupplierReturnsAndPromos | `supplier_returns(+_items)`, `pos_promos`, `pos_sale_items.promo_id`, izin `stock.supplier_return`, settings reorder |
| 2026-10-25-000001 AddTransferPaymentMethod | enum metode + `TRANSFER`, setting `pos_transfer_account` |

**Gotcha data**: `pos_sales.sale_date` bertipe **DATETIME** (bandingkan dengan `DATE(sale_date)` atau rentang jam); `pos_payments.amount` sudah **bersih** (kembalian ada di `change_amount`, jangan dikurangkan lagi); `lines` adalah kata cadangan MySQL; nomor dokumen diturunkan dari id sehingga rollback validasi bisa meninggalkan loncatan nomor.

## 7. Cara kerja & pengujian (untuk agent)
1. Edit kode → `php -l` (hanya mengecek PHP, **tidak** mengecek JavaScript di view; uji di browser).
2. **Uji di DB latihan**, bukan DB kerja: `php spark training:setup --reset` → jalankan smoke test dengan `KEBUNI_DB_OVERRIDE=<db>_training` → uji UI dengan `php spark training:serve 8092` (akun latihan: password & PIN acak tampil sekali; boleh set password uji langsung di DB latihan). Hapus DB latihan setelah selesai.
3. Skrip smoke test tersimpan di [`docs/smoke-tests/`](smoke-tests/README.md) (ekstensi `.php.txt`, **bukan** test resmi; suite lama menjalankan TRUNCATE → jangan di produksi). Regresi terakhir: semua lulus kecuali 1 assertion usang di `pos:opening-smoke`.
4. Browser testing: pane browser bawaan + login ke port 8092; ukuran HP 375 px diperiksa untuk halaman baru; hapus sisa data uji & file upload uji.
5. Windows/Git-Bash: hindari heredoc dengan tanda kutip/backslash rumit untuk patch PHP — tulis skrip patch ke file lalu jalankan; `sed` memakan backslash pada nama kelas (pakai PHP untuk edit string ber-backslash).
6. Setiap selesai: catat di `docs/POS-PROGRESS.md` (entri bertanggal + tabel status), bersihkan data uji, daftar file yang harus diunggah ke hosting.

## 8. Deploy ke hosting (shared hosting, phpMyAdmin)
- Urutan SQL: **backup → `sql-semua-perubahan-pos.sql`** (hanya untuk DB pra-POS, sekali) **→ `sql-bersihkan-data-uji-hosting.sql`** (hapus data uji; sudah dibuat dari dump hosting 24 Okt) **→ `sql-tambah-transfer.sql`** (metode Transfer). Setelah itu unggah kode terbaru; jangan menimpa `.env`.
- Daftar file yang berubah per fitur ada di akhir jawaban tiap fitur dan di `POS-PROGRESS.md`. Checklist lengkap: [`CHECKLIST-DEPLOY-POS.md`](CHECKLIST-DEPLOY-POS.md).
- Folder `writable/` harus bisa ditulis (foto Broken Stock `writable/uploads/broken_stock/`, bukti `writable/uploads/pos_proofs/`).
- **Status hosting saat dokumen ini ditulis**: sebagian kode terbaru (form penerimaan, laporan harian, Transfer, retur bersih, Buying Power, Hourly Sales) **belum diunggah**; data hosting masih berisi data uji penerimaan dengan biaya salah (biaya rata-rata terlalu kecil) → jalankan skrip pembersih lalu input ulang penerimaan (total baris = qty × harga/unit).

## 9. Fitur yang BELUM ada / tertunda / keterbatasan
- **Fase 3 offline (Store Node)**: hanya rancangan ([`FASE-3-RANCANGAN-OFFLINE.md`](FASE-3-RANCANGAN-OFFLINE.md)); mode darurat dibuang; koneksi toko Wi-Fi; server pusat shared hosting.
- **Test otomatis resmi + `git init`**: ditunda owner ("no 1 nanti").
- **Payment gateway QRIS (Midtrans/DOKU)**: ditunda; verifikasi QRIS/Transfer masih manual oleh supervisor/finance.
- **Jurnal akuntansi** untuk nota kredit/refund supplier: nilai hanya informasi (menunggu keputusan Keuangan). Kebijakan biaya bonus supplier juga menunggu Keuangan.
- **Opname sebagian/bergilir (cycle count)**: belum ada; opname menghitung semua produk POS.
- **Laporan Buying Power/Hourly** memakai sales kotor (retur belum dikurangkan); laporan lain sudah bersih.
- **Pengaman retur**: belum ada pengaman bila alasan "rusak" tetapi kondisi "Layak jual" (usulan: otomatis set kondisi atau beri peringatan) — belum diminta/diputuskan.
- Laporan harian per kasir tidak menampilkan retur. Satu uji lama (`pos:opening-smoke`) perlu disesuaikan.
- Modul B2B/Final Report temuan **F2–F10** sengaja tidak disentuh (lingkup).

## 10. Riwayat keputusan owner yang memengaruhi desain
- Opname **setelah EOD** (cut-off bersih), hitung fisik dulu lalu input di HP; prosedur blind count/rekonsiliasi/hitung ulang/posting dari owner.
- Ambang selisih besar tetap 5% / Rp50.000. Matriks cepat/lambat × untung tinggi/rendah × risiko ("by data").
- Satu kasir per hari, tanpa shift; supervisor memberi diskon/override lewat PIN di layar kasir.
- QRIS/Transfer **manual dulu**; payment gateway belakangan. Hosting = shared hosting (cron-based sync nanti).
- Istilah laporan: **NoI** (Number of Invoice), **buying power/basket size = Sales ÷ NoI**, **most wanted item**, **golden time**.

## 11. Prompt singkat untuk agent baru (salin)
> Baca `docs/HANDOVER-AI-AGENT.md`, lalu `docs/POS-PROGRESS.md` (entri terbaru di atas bagian "Rencana langkah"). Proyek: CodeIgniter 4.7 + MariaDB, modul Kebuni Mart POS. Pakai `C:\xampp-php-82\php\php.exe`. Jangan sentuh modul B2B. Uji hanya di DB `*_training` (`php spark training:setup --reset`, `KEBUNI_DB_OVERRIDE`). Jangan menjalankan smoke test lama di produksi (TRUNCATE). Catat semua langkah di `docs/POS-PROGRESS.md`, bersihkan data uji, dan sebut file yang harus diunggah ke hosting. Komunikasi Bahasa Indonesia, ringkas.
