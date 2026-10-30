# Audit Rumus Saldo Buku & Penyusutan — Final Report

Tanggal audit: 2026-10-23 · Sumber: `app/Services/FinalReportService.php` (`stockReport()`), view `app/Views/report/final.php`,
tabel `stock_opnames`, `sales_orders`/`sales_order_items`, `price_period_products`, DB lokal `kebuni_22_oct`.
Permintaan: PRD §9 "Audit wajib laporan lama" — audit rumus Saldo Buku & penyusutan di Final Report sebelum POS aktif.

> Audit ini **hanya membaca** data dan kode. Tidak ada perubahan pada Final Report maupun datanya.

## 1. Rumus yang berlaku di kode

| Item | Rumus di kode | Catatan |
|---|---|---|
| Saldo buku | `stok_awal − terjual` | Kolom `pembelian` **tidak** dijumlahkan ("hanya catatan jumlah order"). Docblock fungsi masih menulis `stok_awal + pembelian − terjual` → **tidak konsisten**. |
| Terjual | Σ qty item SO berstatus Submitted / Approved / Processing / Completed pada price period | SO yang belum dikirim (Submitted/Approved) sudah mengurangi stok. |
| Shrinkage qty | `stok_aktual − stok_buku` (negatif = susut) | Tanda kebalikan dari PRD FR-10 (`buku − fisik`). |
| Shrinkage nilai | `shrink_qty × buy_price` | |
| buy_price | 1) **daftar COGS manual hard-coded** (dari Excel 18-10-2026, dicocokkan dengan nama produk); 2) rata-rata `cogs_destination` SO periode; 3) `purchase_price` + susut | |
| Laba/rugi qty | `stok_aktual + shrink_qty` = `2 × aktual − buku` | Disebut "sesuai rumus Excel". |
| Produk yang tampil | Hanya produk yang **terjual** pada periode | Produk yang diopname tapi tidak terjual tidak muncul. |
| "Sudah diopname" | Baris `stock_opnames` ada | Tidak membedakan stok aktual 0 karena memang habis vs belum diinput. |

## 2. Hasil cek data

Periode yang punya data opname: **hanya 116 "Pengiriman 2 Belitung"** (13 produk). Periode 107–114 punya penjualan tetapi tanpa opname.

Periode 116 (hasil menjalankan `stockReport(116)` apa adanya):

| Produk | Stok awal | Pembelian | Terjual | Saldo buku (kode) | Stok aktual | Shrinkage Rp (ditampilkan) |
|---|---:|---:|---:|---:|---:|---:|
| Bawang Brebes | 480 | 480 | 479 | 1 | 0 | −27.531 |
| Kentang A | 1.020 | 1.020 | 921,4 | 98,6 | 0 | −1.462.731 |
| Cabe Merah Keriting | 250 | 250 | 225 | 25 | 0 | −1.292.450 |
| Cabe Rawit Merah | 210 | 210 | 156 | 54 | 0 | −3.991.572 |
| **Kol** | 930 | 930 | 953,6 | **−23,6** | 0 | +196.753 |
| Tomat Merah | 490 | 490 | 447 | 43 | 0 | −431.376 |
| Jahe Gajah | 285 | 285 | 275 | 10 | 0 | −150.880 |
| Kunyit | 255 | 255 | 255 | 0 | 0 | 0 |
| Jagung | 300 | 300 | 150 | 150 | 0 | −1.695.000 |
| Labu Siam | 150 | 150 | 147,5 | 2,5 | 0 | −26.483 |
| Sawi Putih | 220 | 220 | 111 | 109 | 0 | −1.193.223 |
| **Kentang C** | 52 | 52 | 60 | **−8** | 0 | +104.056 |
| Timun | 50 | 50 | 50 | 0 | 0 | 0 |
| **Total** | | | | | | **−9.970.436** |

## 3. Temuan

| # | Tingkat | Temuan | Dampak |
|---|---|---|---|
| F1 | **Kritis** | Semua `stok_aktual` periode 116 bernilai **0** (diinput 22-10-2026 08:01 bersama stok awal), tetapi laporan menganggapnya sudah diopname. | KPI "Nilai Shrinkage" Final Report periode 116 menampilkan **−Rp9.970.436** yang kemungkinan besar **bukan susut nyata** (mis. Jagung 150 kg & Sawi 109 kg dianggap hilang). |
| F2 | **Tinggi** | **Saldo buku negatif**: Kol −23,6 kg, Kentang C −8 kg (terjual > stok awal). | Ada barang keluar tanpa catatan masuk: penerimaan/bonus belum dicatat, selisih timbang, atau salah input stok awal. Harus diselidiki sebelum dipakai sebagai angka awal. |
| F3 | **Tinggi** | `pembelian` = `stok_awal` di **13/13** baris; rumus mengabaikan `pembelian`. "Stok awal" pada praktiknya = jumlah barang masuk periode ini, **tanpa sisa periode sebelumnya**. | Tidak ada kesinambungan antar periode (sisa periode lalu tidak terbawa). Bila suatu saat rumus diubah ke `awal + pembelian − terjual`, data lama akan terhitung dobel. |
| F4 | **Tinggi** | Harga valuasi **hard-coded** (daftar COGS manual Oktober) dipakai untuk **semua periode** berdasar nama produk. | Periode lama dinilai dengan harga Oktober — mis. Cabe Merah Keriting periode 114: dipakai Rp51.698/kg, padahal COGS periode itu ±Rp26.731/kg (≈2×). |
| F5 | Sedang | Valuasi memakai **COGS destination** (sudah termasuk transport/packing/opex pengiriman), bukan biaya persediaan (harga beli + biaya masuk). | Nilai persediaan & susut di gudang tercatat lebih tinggi dari biaya perolehannya (mis. Kol Rp8.337 vs harga beli Rp4.500). |
| F6 | Sedang | Rumus Laba/rugi qty = `aktual + shrinkage` = `2×aktual − buku` tidak punya arti akuntansi yang jelas. | Contoh: buku 100, aktual 95 → 90 kg; dengan aktual 0 → −buku. Perlu konfirmasi maksud kolom di Excel. |
| F7 | Sedang | Hanya produk **terjual** yang dihitung. | Produk yang ada stoknya tetapi tidak laku (sering paling berisiko busuk) tidak pernah muncul sebagai susut. |
| F8 | Rendah | Penjualan berstatus Submitted/Approved (belum dikirim) sudah mengurangi saldo buku. | Saldo buku di tengah periode bisa lebih rendah dari fisik. |
| F9 | Rendah | Periode 107–114 tidak punya opname; saldo buku = −terjual (mis. Bawang Garut −1.670 kg di periode 114). | Tidak bisa direkonsiliasi; hanya catatan sejarah. |
| F10 | Info | Konvensi tanda susut berbeda dengan PRD & modul POS (POS: `selisih = buku − fisik`, positif = susut). | Hati-hati saat membandingkan dua laporan. |

## 4. Keterkaitan dengan POS Kebuni Mart

- Final Report mengukur stok **distribusi B2B per price period** (pengiriman Belitung), bukan stok **toko Kebuni Mart**.
  Modul POS memakai stock ledger sendiri (`stock_ledger`, lokasi `TOKO`) sehingga rumus di atas **tidak dipakai** oleh POS.
- Karena F1–F4, angka Final Report **tidak disarankan** dijadikan stok awal toko. **Stok awal POS diambil dari hitung fisik
  di toko saat go-live** (layar *Stok Awal*), dengan biaya per unit diisi manual dari nota/hitungan (tidak lagi diambil dari price period, per 2026-10-25)
  yang memuat produk tersebut — dapat diubah sebelum diposting.

## 5. Rekomendasi sebelum go-live

1. **(Kritis)** Input stok aktual periode 116 atau hapus baris opname yang belum dihitung; ubah laporan agar baris dengan aktual
   kosong/belum diinput tidak dihitung sebagai susut (perlu kolom/flag "sudah dihitung").
2. Selidiki saldo negatif Kol & Kentang C (penerimaan/bonus yang belum tercatat, selisih timbang).
3. Putuskan arti kolom `stok_awal` vs `pembelian`; tambahkan sisa periode sebelumnya sebagai stok awal, lalu pakai rumus PRD
   `stok_awal + pembelian − terjual` dengan migrasi data agar tidak dobel.
4. Ganti daftar harga hard-coded dengan harga per periode (purchase_price + biaya masuk), disetujui Keuangan (moving average sesuai
   keputusan owner A1).
5. Konfirmasi rumus "Laba/rugi qty" ke pembuat Excel; sertakan produk yang tidak terjual dalam laporan stok.
6. Untuk toko: lakukan **blind count go-live**, posting lewat layar *Stok Awal*, lalu operasional berjalan di stock ledger POS.

Perbaikan Final Report di atas **belum dikerjakan** — perlu keputusan owner/Keuangan karena mengubah angka historis yang sudah dipakai,
**kecuali F1** (lihat di bawah).

## 6. Tindak lanjut

### F1 — diperbaiki 2026-10-23

- Migration `2026-10-23-000008_MakeOpnameStokAktualNullable`: `stock_opnames.stok_aktual` kini boleh NULL (= belum dihitung).
  Ke-13 baris periode 116 yang bernilai 0 (diinput bersama stok awal, belum pernah dihitung) diubah menjadi NULL.
  Backup sebelum perubahan: `writable/backups/kebuni_22_oct_pre_f1_fix_2026-10-23.sql`.
- `StockOpnameModel::replaceForPeriod()`: input kosong disimpan NULL; `0` tetap 0 (barang memang habis).
- `FinalReportService::stockReport()`: susut hanya dihitung untuk baris yang stok aktualnya sudah diinput; tambahan `n_pending`.
- `report/final.php`: kolom stok aktual kosong ber-placeholder "belum", tombol simpan mengirim kosong sebagai NULL (bukan 0), KPI
  "Nilai Shrinkage" menyebut jumlah produk yang belum dihitung, catatan di bawah tabel menjelaskan aturan kosong vs 0.
- Hasil: KPI periode 116 berubah dari **−Rp9.970.436** menjadi **Rp0 (13 produk belum dihitung)**. Diuji: isi Jagung 140 & Kunyit 0
  → hanya keduanya dihitung, susut −Rp113.000 (Jagung 10 kg × Rp11.300); data dikembalikan seperti semula setelah uji.
- **Tindakan user:** isi stok aktual periode 116 (hasil hitung fisik) di Final Report; isi **0** bila barang memang habis.

Temuan lain (F2–F10) tetap terbuka dan menunggu keputusan.
