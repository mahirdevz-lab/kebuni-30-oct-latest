# Panduan Operasional Kebuni Mart POS (singkat)

Untuk kasir, supervisor toko, dan admin. Semua menu ada di grup **Kebuni Mart POS** (sidebar) dan menu **Stok**.
Satu kasir bertugas dari toko buka sampai tutup (tanpa pergantian shift).

## Peran & hak akses

| Peran | Bisa |
|---|---|
| **Kasir** | Checkout, lihat penjualan sendiri, terima pelunasan tagihan, buka/tutup kas (EOD), hitung opname dari HP, lapor Broken Stock |
| **Supervisor Toko** | Semua kasir + void, retur, koreksi metode bayar, verifikasi QRIS, diskon & override stok (login atau PIN), setoran ke bank, harga/markdown, setujui Broken Stock, rekonsiliasi & posting opname |
| **Admin Inventori** | Penerimaan barang, stok awal (draft), harga retail, opname (hitung), Broken Stock (lapor) |
| **Finance** | Lihat penjualan/stok/analisa, verifikasi QRIS, setoran & settlement QRIS, koreksi metode bayar |
| **Admin / Super Admin** | Semuanya (posting stok awal, setting, user) |

Hak akses ditegakkan di server — tombol yang tersembunyi bukan satu-satunya pengaman.

## A. Alur harian

### 1. Buka toko (kasir) — menu *Buka / Tutup Kas*
1. Hitung uang di laci, isi **Kas awal**. Layar menampilkan *kas awal seharusnya* (kas fisik EOD kemarin ± setoran sesudahnya).
2. Jika berbeda, **wajib** menulis penjelasan di catatan. Selisih tercatat dan terlihat di riwayat.
3. Klik **Buka Toko**. Checkout tidak bisa dilakukan sebelum toko dibuka.

### 2. Berjualan — menu *Kasir*
1. Scan barcode / ketik kode atau nama produk, Enter. Ubah **Qty** (boleh desimal untuk timbangan, mis. 2,5).
2. **Gratis** = barang gratis promo (Buy 1 Get 1): harga Rp0, stok tetap berkurang.
3. Pilih **pelanggan** (cari nomor telepon; "Pelanggan baru" bila belum ada). Wajib bila pembayaran **kurang** dari total (sisa tagihan).
4. Pilih **Tunai** (isi uang diterima → kembalian) atau **QRIS** (status *menunggu verifikasi*, **belum lunas** sampai dikonfirmasi).
5. **Selesaikan Transaksi**. Tombol terkunci setelah diklik; klik ganda tidak membuat transaksi ganda.
6. Struk: buka *Penjualan POS* → nomor transaksi → **Kirim Struk via WhatsApp** (kasir menekan kirim sendiri) atau Cetak.

**Stok kurang?** Sistem menolak. Jangan paksa — panggil supervisor. Supervisor boleh **override** (alasan wajib, masuk laporan pengecualian).

**Diskon manual?** Isi nilai (% atau Rp) + alasan, lalu **Terapkan**; supervisor memasukkan **PIN** (atau login sebagai supervisor).

### 3. Verifikasi QRIS (supervisor/finance)
Buka struk transaksi → **Konfirmasi** setelah dana terlihat di aplikasi/mutasi QRIS, atau **Tolak**. Verifikasi semua QRIS *sebelum EOD*.

### 4. Tutup toko / EOD (kasir) — *Buka / Tutup Kas*
1. Pastikan tidak ada QRIS yang masih menunggu verifikasi.
2. Hitung uang di laci → isi **Kas fisik**. Lihat selisih (fisik − seharusnya); jelaskan bila ada.
3. **EOD — Tutup Kas Harian**. Checkout terkunci; void tidak bisa lagi (pakai Retur).
4. Setoran ke bank (supervisor): menu **Setoran & QRIS** → nominal & rekening tujuan. Setoran bukan omzet.

### 5. Stock opname malam hari (setelah EOD)
Urutan: **EOD → hitung fisik → input di HP → rekonsiliasi → hitung ulang selisih besar → posting**.
1. Supervisor: *Stock Opname* → **Mulai** (waktu mulai = cut-off; checkout & penerimaan terkunci).
2. Petugas hitung **tanpa melihat saldo sistem** (*blind count*): buka di HP, ketik jumlah per produk, Enter. Barang yang ada fisik tapi tidak ada di daftar → *Ada barang fisik yang tidak ada di daftar?*
3. Semua produk terisi (isi **0** bila habis) → **Submit**.
4. Supervisor: lihat selisih. Selisih besar (≥ 5% atau ≥ Rp50.000) **wajib dihitung ulang** (blind). Jika masih beda, **konfirmasi** penyebab + catatan.
5. **Posting**: fisik menjadi saldo buku; selisih kurang = *unknown shrinkage*.
Sebelum memulai opname, setujui/tolak semua laporan Broken Stock yang masih draft.

## B. Kejadian lain

| Situasi | Tindakan |
|---|---|
| Salah input / batal, **toko masih buka** | Supervisor: struk → **Void** (alasan wajib) |
| Pelanggan retur barang (kapan pun, termasuk setelah EOD) | Supervisor: struk → **Retur Barang** → pilih barang, kondisi (layak jual / rusak), refund tunai (toko buka) atau transfer |
| Bayar QRIS tapi tercatat tunai (atau sebaliknya) | Supervisor/Finance: struk → **Koreksi ke QRIS/Tunai** (sekali per pembayaran) |
| Barang rusak/busuk **sebelum dibuang** | Menu *Broken Stock* → lapor (foto dari HP) → supervisor setujui. Stok berkurang setelah disetujui |
| Pelanggan membayar sisa tagihan | *Piutang Pelanggan* atau struk → **Pelunasan sisa tagihan** (tunai butuh toko buka) |
| Barang masuk dari supplier (+ bonus) | *Penerimaan Barang* (kuantitas reguler & bonus dipisah) |
| Harga/markdown | *Katalog & Harga* (supervisor/admin inventori). Markdown wajib alasan & periode |
| Dana QRIS masuk bank | *Setoran & QRIS* → pilih transaksi → isi dana bersih → MDR dihitung otomatis; foto/PDF bukti setoran/settlement boleh dilampirkan (opsional) |
| Barang dikembalikan ke supplier (rusak/busuk/salah kirim) | Menu *Retur ke Supplier* (supervisor/admin inventori) → pilih supplier, alasan, produk & qty. Stok langsung berkurang; nilai nota kredit/refund hanya dicatat |
| Promo "beli X gratis Y" atau harga grosir | *Promo Terjadwal* (supervisor/admin inventori): pilih produk, aturan, periode. Berlaku otomatis di kasir (lencana hijau & pratinjau gratis); batalkan bila salah. Transaksi lama tidak berubah |
| Kapan harus memesan ulang | *Saran Pesan Ulang* → daftar produk habis/menipis + jumlah saran + teks pesanan siap kirim WhatsApp ke supplier |
| Ingin tahu kondisi toko sekarang | *Kondisi Toko* → penjualan hari ini, kas, QRIS pending, piutang, stok kritis, tren 7 hari |
| Meninjau kejadian tidak biasa | *Pengecualian* (supervisor) → override stok, diskon, void, retur, selisih kas, Broken Stock per tanggal/jenis; perhatikan pola berulang pada kasir yang sama |
| Melatih karyawan baru tanpa risiko | Mode latihan (`php spark training:setup` lalu `training:serve`) — data contoh terpisah dari data toko, bilah merah **MODE LATIHAN** |
| Lupa PIN supervisor / terkunci (salah 5×) | Tunggu 15 menit, atau setel ulang di *PIN Supervisor* (butuh password akun) |

## C. Aturan emas
1. **Jangan menjual melebihi stok.** Selisih stok diselesaikan lewat penerimaan/opname, bukan menjual minus.
2. **QRIS belum lunas** sebelum diverifikasi.
3. **Setoran, settlement QRIS, dan transfer bukan omzet.**
4. **Data tidak dihapus** — koreksi lewat void/retur/reversal; semuanya tercatat siapa & kapan.
5. **PIN bersifat pribadi.** Jangan dibagikan atau ditulis di tempat umum.
6. Opname dilakukan **setelah EOD**, hitung **tanpa melihat saldo sistem**.
7. Bila koneksi internet putus: **jangan** mencatat manual ganda ke sistem sebelum memeriksa daftar *Penjualan POS* (mungkin transaksi sudah tersimpan). Mode offline belum tersedia — catat di kertas lalu input setelah pulih.

## D. Bantuan cepat

| Pesan layar | Artinya |
|---|---|
| "Toko belum dibuka atau sudah EOD" | Buka kas harian dulu |
| "Stok ... tidak mencukupi" | Panggil supervisor (override) atau catat penerimaan yang belum diinput |
| "Stock opname sedang berlangsung" | Checkout/penerimaan terkunci sampai hasil hitung disubmit |
| "Diskon manual membutuhkan otorisasi supervisor" | Gunakan *Otorisasi supervisor (PIN)* |
| "Kas awal berbeda dari kas seharusnya" | Isi catatan penjelasan selisih |
| 403 setelah lama membuka halaman | Sesi/token kedaluwarsa → muat ulang halaman (F5) lalu ulangi |
