# Smoke test sementara (CLI) — Kebuni Mart POS

Ini **bukan** test otomatis resmi. Ini skrip CLI yang dipakai selama pengembangan untuk menguji alur POS. Disimpan sebagai `*.php.txt`
supaya **tidak** ikut ter-load/ter-jalankan oleh aplikasi (CodeIgniter hanya memuat command dari `app/Commands/*.php`).

## ⚠️ PERINGATAN
- Suite lama (`PosSmokeTest`, `Broken…`, `Reconcile…`, `Return…`, `Opening…`, `Cash…`, `Discount…`, `Balance…`) **menjalankan `TRUNCATE`** pada tabel POS/stok
  dan menghapus baris `financial_transactions` dengan `id > 137`, serta membaca supplier/pelanggan/produk dari database yang sedang aktif.
  **JANGAN pernah dijalankan di database produksi/hosting.** Pakai database lokal yang boleh dikosongkan.
- Dua suite baru (`FeaturesSmokeTest`, `TransferSmokeTest`) **menolak berjalan** kecuali nama database berakhiran `_training`, dan tidak melakukan TRUNCATE.
  Itu pola yang dianjurkan untuk test baru.

## Cara menjalankan
1. Salin file ke `app/Commands/` dengan nama `.php` (mis. `FeaturesSmokeTest.php.txt` → `app/Commands/FeaturesSmokeTest.php`).
2. Jalankan dengan PHP 8.2 (PHP di PATH Windows bisa 7.4): `C:\xampp-php-82\php\php.exe spark <nama-command>`.
3. **Hapus kembali** file dari `app/Commands/` setelah selesai (jangan ikut di-upload ke hosting).

Untuk suite berbasis database latihan:
```
php spark training:setup --reset                                  # buat DB <db>_training + data contoh
KEBUNI_DB_OVERRIDE=<db>_training php spark pos:features-smoke      # jalankan di DB latihan
KEBUNI_DB_OVERRIDE=<db>_training php spark pos:transfer-smoke
php spark training:serve 8092                                      # (opsional) uji browser di http://localhost:8092
```
Jika file `.env` tidak ada, beri kredensial DB lewat variabel lingkungan `database.default.hostname/username/password/database/DBDriver`.
Hapus DB latihan setelah selesai: `DROP DATABASE <db>_training`.

## Daftar suite
| Command | File | Mencakup | Catatan |
|---|---|---|---|
| `pos:smoke` | PosSmokeTest | Fondasi POS: ledger, moving average, checkout, QRIS pending, idempotensi, void, EOD, opname dasar | TRUNCATE |
| `pos:broken-smoke` | BrokenSmokeTest | Broken Stock (lapor/setujui/tolak), laporan penyusutan | TRUNCATE |
| `pos:reconcile-smoke` | ReconcileSmokeTest | Opname: blind count, ronde hitung ulang, konfirmasi, posting, unknown shrinkage | TRUNCATE |
| `pos:return-smoke` | ReturnSmokeTest | Retur pelanggan (layak jual/rusak), koreksi metode bayar | TRUNCATE |
| `pos:opening-smoke` | OpeningSmokeTest | Stok awal (OPENING) | TRUNCATE. **1 assertion usang**: "Biaya default = harga beli price period" — memang sudah tidak berlaku (default biaya sekarang 0/isi manual); gagalnya 1 uji itu wajar |
| `pos:cash-smoke` | CashSmokeTest | Setoran Kas Kasir, settlement QRIS + MDR, kas awal seharusnya | TRUNCATE |
| `pos:discount-smoke` | DiscountSmokeTest | Diskon manual, PIN supervisor (kunci 5×), override stok | TRUNCATE |
| `pos:balance-smoke` | BalanceSmokeTest | Selisih kas awal, pelunasan sisa tagihan | TRUNCATE |
| `pos:features-smoke` | FeaturesSmokeTest | Promo (BOGO/tier), pengecualian, saran pesan ulang, retur supplier, bukti setoran/QRIS, dashboard (angka bersih setelah retur) | Hanya DB `*_training` |
| `pos:transfer-smoke` | TransferSmokeTest | Metode bayar Transfer → Bank Utama (verifikasi, tolak, pelunasan, koreksi, void, laporan) | Hanya DB `*_training` |
| `pos:f1-test` | F1FixTest | Perbaikan F1 Final Report (stok aktual kosong ≠ susut); memulihkan data `stock_opnames` periode 116 seperti semula | Menyentuh data B2B stock_opnames — jangan di produksi |

Hasil terakhir yang diketahui (26 Okt 2026): semua lulus kecuali 1 assertion usang di `pos:opening-smoke` (di atas).

## Saran untuk agent berikutnya
Ubah suite ini menjadi test otomatis resmi (PHPUnit CI4) yang berjalan di DB uji terpisah (mis. `*_training` / `*_test`), tanpa TRUNCATE di DB aplikasi,
lalu `git init` repositori. Ini sudah disepakati sebagai pekerjaan tertunda ("no 1 nanti" dari owner).
