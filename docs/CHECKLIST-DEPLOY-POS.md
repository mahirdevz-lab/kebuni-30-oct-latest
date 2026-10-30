# Checklist Deploy & Go-Live — Kebuni Mart POS

Disusun 2026-10-23. Centang tiap butir; jangan lanjut ke tahap berikutnya sebelum tahap sebelumnya selesai.
Saya (Claude) hanya bekerja di salinan lokal — langkah di server produksi dijalankan oleh tim Anda.

## 0. Sebelum menyentuh server
- [ ] **Backup database produksi** (mysqldump) dan simpan di luar server. Catat nama file & waktunya.
- [ ] **Backup folder aplikasi produksi** (kode + `.env` + `writable/uploads`).
- [ ] Jadikan project repo git (`git init`, commit) agar ada jejak & bisa rollback. *(Folder lokal saat ini bukan repo git.)*
- [ ] Pastikan server memakai **PHP 8.2+** (`php -v`) dengan ekstensi intl, mbstring, mysqli, gd/fileinfo.
- [ ] Siapkan jendela deploy saat toko **tutup** (setelah EOD), bukan saat jam jualan.

## 1. Upload kode
- [ ] Upload file proyek **tanpa**: `.env` lokal, `writable/backups/` (dump DB berisi hash password), `writable/session|cache|logs|debugbar`, `.claude/`.
- [ ] `.env` produksi: `CI_ENVIRONMENT = production`, `app.baseURL = 'https://<domain>/'` (**HTTPS wajib** — PIN supervisor & sesi), kredensial DB produksi.
  > Catatan: `.env` lokal saat ini berisi `app.baseURL = 'http://localhost:8080/'` — jangan ikut ter-upload.
- [ ] Folder `writable/` dapat ditulis oleh web server (uploads foto Broken Stock di `writable/uploads/broken_stock/`, bukti setoran/QRIS di `writable/uploads/pos_proofs/`).
- [ ] Web root mengarah ke folder `public/`.

## 2. Database
- [ ] `php spark migrate:status` — pastikan hanya migration baru yang tertunda.
- [ ] `php spark migrate` — menjalankan **13 migration** (`2026-10-23-000001 … 000012` dan `2026-10-24-000001`):
  perbaikan enum keuangan (+ 10 baris "Pemindahan Dana" lama otomatis diperbaiki), tabel POS/stok/ledger, EOD & opname, Broken Stock,
  rekonsiliasi opname, retur & koreksi, stok awal, `stock_opnames.stok_aktual` nullable (0 → NULL), setoran & settlement QRIS,
  batas EOD, diskon & PIN, pelunasan tagihan, retur ke supplier & promo terjadwal (+ izin `stock.supplier_return` untuk admin-inventori, supervisor-toko, admin).
- [ ] Cek hasil: tabel `pos_sales`, `stock_ledger`, `stock_locations` ada; `cash_accounts` memuat **Kas Kasir** dan **QRIS Clearing**;
  `settings` memuat `pos_cash_account`, `pos_qris_account`, `pos_location_id`, dll.
- [ ] **Perhatikan:** migration `…000008` mengubah stok aktual opname lama yang bernilai 0 menjadi "belum dihitung". Isi ulang stok aktual
  periode 116 di Final Report dari hitung fisik (0 bila habis) — lihat `docs/AUDIT-FINAL-REPORT.md`.

## 3. Keamanan
- [ ] **CSRF aktif** (`app/Config/Filters.php` global `csrf`; `Security.php`: `regenerate=false`, `expires=28800`). Uji: POST tanpa token → 403.
- [ ] HTTPS aktif & `forcehttps` berjalan (tidak ada redirect loop).
- [ ] Ganti password akun default; nonaktifkan akun yang tidak dipakai (`php spark staff:list`).
- [ ] Hapus semua file dump/backup dari web root.

## 4. Akun staf (jalankan di server; password tampil sekali)
```bash
php spark staff:create kasir.toko "Nama Kasir" kasir
php spark staff:create spv.toko "Nama Supervisor" supervisor-toko
php spark staff:create inv.toko "Nama Admin Inventori" admin-inventori   # opsional
php spark staff:list
```
- [ ] Catat password dari terminal dan serahkan langsung; minta diganti di menu **Profil** saat login pertama.
- [ ] Supervisor login → **POS → PIN Supervisor** → setel PIN pribadi (4–6 digit, bukan 1234/1111).
- [ ] Login uji tiap peran: kasir **tidak** melihat menu Retur/Setoran/Katalog; supervisor melihatnya.

## 5. Data master toko
- [ ] **Katalog & Harga**: tandai produk "Jual di POS", isi **harga retail**, barcode, stok minimum.
- [ ] **Setting** (Super Admin): nama toko, batas selisih opname (5% / Rp50.000), batas *high risk* shrinkage, kebijakan biaya bonus supplier
  (**menunggu keputusan Keuangan** — default "dibagi ke seluruh unit").
- [ ] **Supplier** terisi (untuk penerimaan barang).
- [ ] Rekening kas: Bank Utama, Petty Cash, Kas Kasir, QRIS Clearing aktif (menu Keuangan → Kelola Rekening).

## 5b. Stok awal go-live
- [ ] Toko tutup → **blind count** seluruh barang → menu **Stok → Stok Awal**: buat draft (cut-off = waktu hitung), isi qty & biaya/unit,
  periksa, lalu **Admin memposting**. (Jangan memakai angka Final Report.)
- [ ] Cek *Saldo Stok* sesuai hitungan fisik.

## 6. Uji asap di produksi (dengan transaksi kecil, lalu void/retur)
- [ ] Buka toko (kas awal) → checkout tunai → struk → WhatsApp terbuka dengan pesan terisi.
- [ ] Checkout QRIS → status menunggu → verifikasi → masuk QRIS Clearing.
- [ ] Klik "Selesaikan Transaksi" dua kali cepat → hanya **satu** transaksi.
- [ ] Diskon dengan PIN supervisor; override stok dengan PIN.
- [ ] Void (toko masih buka) dan retur; cek kas & stok kembali.
- [ ] EOD → kas seharusnya = fisik; opname dari HP (layout nyaman di ponsel kasir); posting.
- [ ] Setoran ke bank + settlement QRIS (MDR) tercatat sebagai transfer/biaya, **bukan omzet**.
- [ ] Laporan: *Penjualan POS*, *Buku Stok*, *Laporan Penyusutan*, *Analisa Laba & Produk* konsisten dengan transaksi uji.
- [ ] **Bersihkan transaksi uji** sesuai kebijakan (data POS tidak boleh dihapus — gunakan void/retur; atau uji di staging dahulu).

## 6b. Mode latihan (opsional, di komputer lokal/staging — jangan di produksi)
- [ ] `php spark training:setup` membuat DB `<db>_training` berisi produk & akun contoh (password/PIN tampil sekali); `php spark training:serve` membuka `http://localhost:8092`.
- [ ] Bilah merah **MODE LATIHAN** harus tampak di semua halaman; DB produksi tidak berubah. Ulangi `--reset` untuk mengembalikan data contoh.
- [ ] Variabel `KEBUNI_DB_OVERRIDE` hanya diterima untuk nama DB berakhiran `_training`; jangan menyetelnya di server produksi.

## 7. Operasional hari pertama
- [ ] Cetak/bagikan `docs/PANDUAN-OPERASIONAL-POS.md` ke kasir & supervisor; latihan 30 menit (checkout, QRIS, EOD, opname di HP).
- [ ] Supervisor hadir saat toko buka & tutup pada 3 hari pertama.
- [ ] Hari ke-1 malam: EOD + opname + review selisih bersama.
- [ ] Hari ke-3: cocokkan laporan POS dengan Keuangan (penjualan, kas, QRIS Clearing, setoran).

## 8. Rollback
- [ ] Simpan nama backup DB pra-deploy. Rollback = restore DB + kode lama (migration punya `down()` bila perlu mundur sebagian).
- [ ] Jika CSRF menimbulkan kegagalan tak terduga di modul lama: laporkan halaman & aksi yang gagal (403) — jangan matikan CSRF sebelum
  diperiksa; solusi biasanya menambah token pada halaman tersebut.

## Belum tersedia / diketahui
- Mode **offline** & aplikasi desktop/tablet (Fase 3) — saat internet putus kasir mencatat di kertas.
- **Payment gateway** otomatis (QRIS tetap manual — keputusan owner).
- Perbaikan Final Report F2–F10 menunggu keputusan owner/Keuangan.
- Bukti foto setoran/settlement, pengingat jatuh tempo piutang POS, batas kredit pelanggan.
- Smoke test belum disimpan sebagai test otomatis di repo (versi lokal menjalankan TRUNCATE tabel POS — hanya untuk DB uji).
