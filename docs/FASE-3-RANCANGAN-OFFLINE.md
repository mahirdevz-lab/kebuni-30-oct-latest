# Fase 3 — Rancangan Hybrid Offline (Kebuni Mart POS)

Status: **rancangan untuk keputusan** (belum ada kode). Disusun 2026-10-23 dari PRD §6 (Architecture & Offline Strategy), Appendix A
(laptop kedua, satu lokasi toko, QRIS manual) dan kondisi kode Fase 1–2 saat ini.
Estimasi usaha di bawah adalah **perkiraan kasar** untuk perencanaan, bukan komitmen.

---

## 1. Tujuan & batasan (dari PRD)

**Harus tercapai (PRD §3, §11, A5):**
1. POS tetap beroperasi saat **internet pusat putus** tetapi **LAN toko aktif**.
2. Transaksi yang sudah diterima server toko **bertahan setelah restart** perangkat/server.
3. Transaksi pending punya **status sinkronisasi jelas** dan tersinkron **tanpa duplikasi** (ID unik, idempotency key, acknowledgement).
4. Beberapa kasir/perangkat bertransaksi **tanpa stok negatif akibat race condition** (server toko mengatur urutan mutasi stok).
5. Saat perangkat terputus dari server toko, **keterbatasan stok global ditampilkan** dan transaksi diberi status pending.
6. Laptop kedua (installer desktop) terhubung ke server toko lewat LAN.

**Prinsip yang tidak boleh dilanggar (PRD §6.2, A3):**
- Jika semua perangkat terputus satu sama lain, **tidak ada jaminan stok global akurat**. Jangan mengklaim sebaliknya; tandai
  *pending reconciliation* dan terapkan batas risiko.
- Kredensial database pusat tidak boleh ada di aplikasi kasir.
- Stok resmi hanya berubah lewat ledger; transaksi diposting tidak dihapus (sudah terpenuhi di Fase 1–2).

## 2. Apa yang sudah siap (modal dari Fase 1–2)

| Kebutuhan offline | Sudah ada |
|---|---|
| Idempotensi transaksi | `pos_sales.client_uuid` UNIQUE; `pos_payments.client_uuid`; UNIQUE ledger `(reference_type, reference_id, reference_line, movement_type)` |
| Urutan & anti-race stok | `stock_balances` dikunci `FOR UPDATE`, satu pintu `StockLedgerService` |
| Status sinkronisasi | `pos_sales.sync_status` enum: LOCAL, STORE_ACCEPTED, SYNCED, PENDING, CONFLICT, CANCELLED (sudah sesuai PRD §7 FR-14; baru terisi `SYNCED`) |
| Konsistensi harian | EOD + batas id transaksi (`ft_max_id`), snapshot, opname bercut-off |
| Koreksi tanpa menghapus | void/retur/reversal, audit log |
| Verifikasi | smoke test CLI (±200 pemeriksaan) — **belum tersimpan di repo** |

**Asumsi kode yang akan mengganjal (harus diubah):**
- Nomor dokumen berbasis id lokal (`POS-YYYYMMDD-<id>`, `GR-…`, `RT-…`) → bentrok bila lebih dari satu node menulis. Perlu **prefix node**.
- Banyak SQL khusus MySQL/MariaDB (`FOR UPDATE`, `LOCK IN SHARE MODE`, `INSERT IGNORE`, `ON DUPLICATE KEY`, `GROUP_CONCAT`) → mengarah ke **MariaDB lokal**, bukan SQLite.
- Tabel `accounts` (pelanggan) bersama dengan B2B pusat; id auto-increment pusat.
- `settings`, `cash_accounts`, `users` hidup di satu DB; perlu aturan **kepemilikan data** (bagian 5).
- Waktu transaksi memakai jam server; perlu **sinkron jam (NTP)** di server toko.

## 3. Opsi arsitektur

| | A. Pusat primer + antrean di perangkat | **B. Server toko primer (disarankan)** | C. Pusat primer + server toko cadangan (failover) |
|---|---|---|---|
| Siapa penulis stok/kas | Pusat; perangkat offline menumpuk lokal | **Server toko** (satu penulis untuk lokasi toko) | Pusat saat online, server toko saat offline |
| Internet putus | Kasir hanya mode pending, stok tidak terkoordinasi | **Tidak ada dampak ke POS** | POS pindah ke server toko (failover) |
| Risiko split-brain | Tinggi (banyak perangkat, tanpa koordinator) | **Tidak ada** (satu penulis) | Tinggi (dua penulis bergantian, rekonsiliasi rumit) |
| Perubahan kode | Besar di sisi klien (JS/IndexedDB), logika stok ganda | **Sedang**: kode yang sama dijalankan di PC toko + sinkronisasi | Terbesar |
| Sesuai PRD A3/§6 | Tidak (stok global tak terjamin) | **Ya** | Ya, tetapi kompleks |
| Laporan di pusat | Real-time | **Tertunda** beberapa detik–menit (replikasi), tertunda lama saat internet putus | Real-time saat online |

**Rekomendasi: Opsi B — "Store Node".** Karena hanya **satu toko dan satu lokasi stok**, menjadikan server toko satu-satunya penulis
data POS menghilangkan masalah konsistensi terbesar. Pusat (mis.kebuni.id) menerima replikasi satu arah untuk data POS dan
mengirim data master. Perangkat kasir hanyalah **klien browser** ke server toko melalui LAN.

```
                          INTERNET (boleh putus)
             ┌───────────────────────────────────────────┐
             │                                           │
   ┌─────────▼──────────┐   push event (outbox)   ┌──────┴────────────┐
   │  MIS PUSAT (HQ)    │◄────────────────────────│   STORE NODE      │
   │  CI4 + MySQL       │   pull master data      │  PC toko (Windows)│
   │  laporan/keuangan  │────────────────────────►│  CI4 + MariaDB    │
   │  B2B, Final Report │   (HTTPS, token node)   │  = sumber resmi   │
   └────────────────────┘                         │  stok/kas toko    │
                                                  └──────▲───────▲────┘
                                                         │ LAN   │ LAN
                                         ┌───────────────┘       └──────────────┐
                                  ┌──────┴───────┐                      ┌───────┴──────┐
                                  │ Laptop kasir │                      │ Laptop/HP/   │
                                  │ (browser/PWA)│                      │ tablet       │
                                  └──────────────┘                      └──────────────┘
```

## 4. Mode operasi & status

| Mode | Kondisi | Perilaku |
|---|---|---|
| **1 Normal** | LAN ok, internet ok | Kasir → Store Node. Sinkron ke pusat hampir real-time. |
| **2 Internet putus** | LAN ok, internet mati | **POS jalan normal.** Antrean sinkron menumpuk; banner "Sinkron tertunda N event, terakhir pukul …". Dashboard pusat tertinggal. |
| **3 Perangkat terputus dari Store Node** | LAN/WiFi bermasalah di satu perangkat | Perangkat masuk **Mode Darurat** (opsional, bagian 7): hanya penjualan **tunai**, batas jumlah/nilai, tanpa diskon/override/QRIS-verifikasi, nomor seri darurat per perangkat, tersimpan di perangkat dengan status `LOCAL`. Setelah tersambung → dikirim ke Store Node → `STORE_ACCEPTED` atau `PENDING`/`CONFLICT` bila stok tak cukup/harga berubah. Banner jelas: *stok tidak terkoordinasi*. |
| **4 Store Node mati** | PC toko rusak/mati listrik | Penjualan darurat per perangkat (Mode 3) sampai node dipulihkan dari backup + replika pusat (bagian 9). |

**Pemetaan status sinkronisasi (FR-14):**
`LOCAL` (hanya di perangkat) → `STORE_ACCEPTED` (diposting di Store Node) → `SYNCED` (di-ack pusat).
`PENDING` = perlu rekonsiliasi (mis. dari Mode Darurat atau menunggu verifikasi), `CONFLICT` = gagal diterapkan (perlu tindakan supervisor), `CANCELLED` = void/reversal.

## 5. Kepemilikan data (satu penulis per data)

| Data | Penulis (otoritas) | Arah | Catatan |
|---|---|---|---|
| Produk (nama, kategori, satuan), supplier | **Pusat** | pusat → toko | `products` dipakai juga oleh B2B |
| Flag POS: `is_pos`, `barcode`, `min_stock` | **Toko** | toko → pusat | Kolom milik toko, tidak ditimpa pull |
| Harga retail & markdown | **Toko** (supervisor/admin inventori) | toko → pusat | Pusat read-only |
| Pengguna, role, izin, hash password | **Pusat** | pusat → toko | Hanya akun yang berhak masuk toko (kasir, supervisor, admin inventori, finance, super admin yang dipilih) |
| PIN supervisor (hash) | **Pusat/toko** (setel sekali) | dua arah, versi terbaru menang | Penghitung salah-PIN & kunci **lokal per node** |
| Pelanggan retail (`accounts`) | **Toko** untuk yang dibuat di POS; **pusat** untuk B2B | dua arah | Duplikat telepon → aturan merge (bagian 6.4) |
| Penjualan, pembayaran, retur, ledger stok, hari kas/EOD, opname, Broken Stock, setoran, settlement, stok awal | **Toko** | toko → pusat | Tidak pernah ditulis pusat |
| Akun kas (Kas Kasir, QRIS Clearing, Bank) & setting POS | **Pusat** definisi; saldo transaksi POS dari toko | pusat → toko (definisi) | |
| Transaksi keuangan POS (`financial_transactions`) | **Toko** menghasilkan, **pusat** memproyeksikan | toko → pusat | Lihat 6.3 |

## 6. Rancangan sinkronisasi

### 6.1 Toko → pusat: *transactional outbox*
- Setiap transaksi bisnis di Store Node menulis baris `sync_outbox` **dalam transaksi DB yang sama** dengan datanya
  (atomic: tidak ada data tanpa event, tidak ada event tanpa data).
- Kolom inti: `event_uuid` (unik global), `node_id`, `node_seq` (urutan menaik per node), `entity` (sale, payment, ledger, closing, …),
  `payload` JSON (snapshot lengkap termasuk biaya pokok & harga saat transaksi), `occurred_at`, `status`, `attempts`, `next_retry_at`.
- **Worker** (`php spark sync:push`, dijadwalkan Windows Task Scheduler tiap menit; juga dipicu setelah EOD) mengirim batch berurutan
  `node_seq` lewat HTTPS ke pusat dengan token node; **retry dengan exponential backoff**; hanya menandai `acked` setelah pusat membalas ack
  per `event_uuid`.
- **Pusat** (`sync_inbox`, `event_uuid` UNIQUE) menerapkan event **idempoten & berurutan per node**; bila ada celah `node_seq`, pusat menahan
  event setelahnya dan memicu peringatan. Pengiriman ulang event yang sama tidak mengubah apa pun (PRD: tidak ada penjualan/mutasi ganda).

### 6.2 Pusat → toko: data master
Kursor perubahan (`updated_at` + penanda hapus/tombstone) per tabel master; `php spark sync:pull` mengambil perubahan sejak kursor terakhir.
Perubahan kolom milik toko tidak ditimpa. Pull yang gagal tidak menghentikan POS (memakai data terakhir).

### 6.3 Proyeksi di pusat
Pusat **tidak** meniru tabel toko apa adanya (hindari pemetaan FK/id yang rapuh). Event disimpan lengkap (`hq_pos_events`) lalu
diproyeksikan ke **model baca** untuk laporan: penjualan, ledger stok, kas, shrinkage, serta baris `financial_transactions` agar dashboard
Keuangan yang ada tetap benar (kunci idempotensi = `event_uuid`). Pemetaan id memakai `(node_id, id_asal)`.
Keputusan terbuka: proyeksikan **per transaksi** (jejak audit penuh, disarankan) atau **ringkasan per hari** (lebih ringan).

### 6.4 Pelanggan & konflik
- Pelanggan baru dari POS dikirim dengan UUID; pusat mencocokkan **nomor telepon ternormalisasi**. Jika cocok dengan pelanggan yang ada →
  pakai yang ada (tabel pemetaan), tidak membuat duplikat. Konflik nama berbeda → masuk antrean tinjauan, tidak menimpa diam-diam.
- Kebijakan konflik umum: *append-only* untuk transaksi (tidak pernah ada konflik penulisan karena satu penulis); *last-writer-wins dengan
  versi* hanya untuk data master dua arah yang kecil (PIN, pelanggan).

### 6.5 Jaminan yang diberikan / tidak
Diberikan: tidak ada duplikasi, urutan stok per toko benar, data tidak hilang saat restart/putus internet, bukti ack per event.
**Tidak** diberikan: stok "real-time global" di pusat saat internet putus (pusat tertinggal), dan stok akurat lintas perangkat saat
perangkat terputus dari Store Node (Mode 3).

## 7. Mode Darurat perangkat (opsional — keputusan owner)

Hanya dibangun bila owner menganggap risiko "LAN/WiFi toko atau Store Node bermasalah" nyata. Isi:
- **PWA** (Service Worker + IndexedDB): menyimpan katalog, harga, dan daftar pelanggan terakhir (disalin saat online).
- Penjualan **tunai saja**; **tanpa** diskon, override stok, retur, void; batas per perangkat (mis. maks. N transaksi / Rp X per hari — nilai diputuskan owner).
- Nomor seri darurat per perangkat (`DRT-<perangkat>-<urut>`) + UUID; struk menandai "DARURAT — belum diverifikasi stok".
- Saat tersambung: dikirim ke Store Node dengan idempotency key → diposting; bila stok tidak cukup/harga berbeda → `PENDING`/`CONFLICT`
  dan masuk **Kotak Konflik** supervisor (terima & catat pengecualian / koreksi lewat retur). Biaya pokok memakai moving average saat diposting.
- Tidak menyimpan data pelanggan sensitif lebih dari yang diperlukan; penyimpanan lokal dibersihkan setelah ack.
- **Perlu HTTPS** (Service Worker/kamera mensyaratkan konteks aman) — lihat bagian 8.

## 8. Pilihan teknologi

| Lapisan | Pilihan | Alasan / catatan |
|---|---|---|
| Store Node — aplikasi | **CodeIgniter 4 yang sama** (PHP 8.2) | Seluruh service ledger/EOD/opname yang sudah diuji dipakai ulang; tidak menulis ulang logika bisnis. Mode node dipilih lewat `.env` (`app.nodeRole = store`, `app.nodeId`, `app.nodePrefix`). |
| Store Node — database | **MariaDB/MySQL lokal** | Kode memakai fitur khusus MySQL (row lock, `ON DUPLICATE KEY`). **SQLite (usulan PRD §6.3) tidak disarankan** untuk server toko: perlu menulis ulang lapisan penguncian & query. SQLite tetap relevan untuk penyimpanan di perangkat (IndexedDB/SQLite WASM) pada Mode Darurat. |
| Store Node — pembungkus | Paket pemasang (XAMPP/Laragon portabel atau **Docker Desktop**) + Task Scheduler; layanan auto-start | Pilih berdasarkan kemampuan staf IT toko (bagian 11). |
| Klien kasir | **Browser/PWA** ke Store Node via LAN | Satu basis kode untuk PC, laptop kedua, tablet, HP. |
| Aplikasi desktop (installer laptop kedua, PRD A1) | **Pintasan PWA/kiosk-browser dulu**; Electron **belakangan bila perlu** | Electron menambah kerja (update, signing) tanpa manfaat untuk logika; baru perlu bila butuh cetak struk langsung/printer termal, auto-start kios, atau pembatasan sistem. |
| Transport sinkron | **HTTPS REST** + token node (HMAC/Bearer), batch, ack per event | Sederhana, bisa diaudit, mudah di-retry; bukan replikasi DB (Galera/MySQL replication terlalu rapuh untuk link tidak stabil). |
| Antrean | Tabel `sync_outbox`/`sync_inbox` + Spark command terjadwal | Tanpa broker tambahan (RabbitMQ/Redis) — cukup untuk volume 1 toko. |
| HTTPS di LAN | **Domain toko** (mis. `pos.kebuni.id` → IP LAN) + sertifikat DNS-01, atau CA lokal (mkcert) terpasang di perangkat | Syarat Service Worker, kamera (opname/Broken Stock), dan keamanan PIN. |
| Cadangan | `mysqldump` malam ke disk eksternal + unggah terenkripsi ke pusat | Tambahan terhadap replika pusat. |
| Keamanan perangkat | BitLocker di PC toko, akun Windows terpisah, firewall hanya LAN | Server toko menyimpan hash password staf. |

## 9. Pemulihan bencana & operasional
- **Pusat memegang replika lengkap** (semua event) → Store Node baru dapat di-*bootstrap*: restore backup terakhir + `sync:pull` snapshot +
  event setelah backup. Latihan pemulihan wajib sebelum go-live Fase 3.
- **Server cadangan (opsional)**: PC kedua "warm standby" yang menerima dump malam + snapshot; pengalihan manual (bukan otomatis, untuk menghindari split-brain).
- **UPS** untuk Store Node + router/switch; tanpa UPS, risiko korupsi data & event hilang meningkat.
- **Pemantauan**: halaman *Status Sinkron* (antrean, event gagal, umur event tertua, selisih jumlah/nilai per hari toko vs pusat),
  peringatan bila tak ada sinkron > X jam; laporan **rekonsiliasi harian** otomatis (jumlah transaksi, omzet, kas, saldo stok).
- **Waktu**: NTP aktif di Store Node; pusat menyimpan `occurred_at` toko dan `received_at` pusat.

## 10. Tahapan, keluaran, dan estimasi kasar

| Tahap | Pekerjaan | Keluaran / kriteria selesai | Estimasi* |
|---|---|---|---|
| **3.0 Keputusan & kesiapan** | Putuskan pertanyaan bagian 11; jadikan **smoke test menjadi test otomatis di repo** (DB uji terpisah); `git init`; spesifikasi perangkat keras; domain & HTTPS | Dokumen keputusan; suite test hijau di CI/lokal; PC toko & jaringan siap | 3–5 hari |
| **3.1 Store Node mandiri** | Mode node di `.env`; prefix nomor dokumen per node; paket instalasi + auto-start + backup malam + halaman kesehatan; akses LAN HTTPS; banner status | POS **tetap jalan penuh tanpa internet**; restart PC tidak kehilangan data; installer dapat dijalankan staf | 6–9 hari |
| **3.2 Outbox & push ke pusat** | `sync_outbox`, worker, API `sync/push`, `sync_inbox`, proyeksi di pusat (penjualan, ledger, kas, keuangan), laporan rekonsiliasi | Tidak ada duplikasi di uji retry/putus; angka pusat = angka toko per hari; status `STORE_ACCEPTED → SYNCED` terisi | 12–18 hari |
| **3.3 Pull data master & akun** | Kursor perubahan, kepemilikan kolom, replikasi user/role/izin/PIN, pelanggan dua arah + merge telepon | Akun baru di pusat bisa login di toko tanpa internet setelah sinkron; tidak ada duplikat pelanggan | 6–8 hari |
| **3.4 UX & operasional sinkron** | Banner/status, halaman Log Sinkron, retry manual, Kotak Konflik, peringatan, panduan operasional offline | Supervisor dapat menyelesaikan konflik tanpa bantuan teknis | 4–6 hari |
| **3.5 Mode Darurat perangkat** *(opsional)* | PWA, IndexedDB, antrean darurat, batas risiko, kirim ulang idempoten | Uji cabut WiFi di tengah checkout: transaksi tidak hilang/ganda, tercatat `LOCAL → STORE_ACCEPTED/PENDING` | 8–12 hari |
| **3.6 Shell desktop** *(opsional)* | Pintasan kios/Electron untuk laptop kedua (cetak, auto-start) | Installer laptop kedua sesuai PRD A1 | 4–6 hari |
| **3.7 Pengerasan & DR** | Latihan restore, uji mati listrik, uji partisi jaringan, uji beban (±3 kasir), jam/NTP, pemantauan | Semua skenario kritis lulus (bagian 12) | 4–6 hari |

\* person-day kasar, **belum** termasuk waktu tunggu keputusan, pengadaan perangkat, atau pelatihan. Total inti (3.0–3.4 + 3.7) ≈ **35–52**;
dengan 3.5–3.6 ≈ **47–70** person-day.

**Urutan yang disarankan:** 3.0 → 3.1 → 3.2 → 3.3 → 3.4 → 3.7, lalu **Fase 4 pilot**. 3.5/3.6 hanya jika diputuskan perlu.
Catatan: setelah 3.1 saja, toko sudah kebal terhadap putus internet (data masih berada di toko); 3.2 diperlukan agar pusat/Keuangan melihat data.

## 11. Keputusan yang dibutuhkan dari owner

1. **Laptop kedua** berada di toko yang sama dan tersambung LAN (asumsi rancangan ini)? Atau perlu bertransaksi di lokasi berbeda? *(PRD A6.3 masih terbuka — jika lokasi berbeda, arsitektur berubah menjadi multi-node.)*
2. **Perangkat server toko**: PC/mini-PC khusus yang menyala selama toko buka (rekomendasi: bukan PC kasir utama), RAM ≥ 8 GB, SSD, **UPS**. Siapa yang merawat (update, backup, restart)?
3. **Domain & HTTPS** untuk alamat toko (mis. `pos.kebuni.id` dengan DNS ke IP LAN) — ada yang mengelola DNS/sertifikat?
4. **Mode Darurat (3.5)**: perlu? Jika ya, batas risiko per perangkat (jumlah transaksi / total Rp) dan kebijakan transaksi yang stoknya tidak cukup saat disinkronkan.
5. **Proyeksi keuangan di pusat**: per transaksi (audit penuh) atau ringkasan harian?
6. **Akun mana** yang boleh punya hash password di server toko (hanya staf toko + admin tertentu)?
7. **Payment gateway** (ditunda): bila nanti diaktifkan, notifikasi gateway harus masuk ke Store Node atau pusat? (memengaruhi desain; tidak memblokir Fase 3.)
8. Keputusan Keuangan yang masih menggantung (kebijakan biaya bonus supplier; F2–F10 Final Report) tidak memblokir Fase 3 tetapi memengaruhi proyeksi pusat.

## 12. Rencana uji (skenario kritis)

| # | Skenario | Hasil yang diharapkan |
|---|---|---|
| 1 | Cabut internet 2 jam saat jualan normal | POS normal; antrean bertambah; setelah pulih semua event ter-ack, angka pusat = toko |
| 2 | Kirim ulang batch yang sama berkali-kali / ack hilang | Tidak ada penjualan/mutasi/kas ganda |
| 3 | Matikan Store Node di tengah checkout (daya putus) | Transaksi atomik: sepenuhnya tersimpan atau tidak sama sekali; klien mengulang aman (UUID sama) |
| 4 | Dua kasir menjual stok terakhir bersamaan | Satu berhasil, satu ditolak (tidak minus) |
| 5 | Cabut WiFi satu perangkat saat checkout *(3.5)* | Transaksi tidak hilang/ganda; status `LOCAL` → diproses setelah tersambung |
| 6 | Event datang tidak berurutan / ada celah `node_seq` | Pusat menahan & memberi peringatan, tidak menerapkan setengah-setengah |
| 7 | Restore Store Node dari backup + replika pusat | Saldo stok & kas sama dengan sebelum mati |
| 8 | Jam Store Node bergeser | Terdeteksi (peringatan NTP); urutan tetap oleh `node_seq` |
| 9 | Pelanggan dibuat di pusat dan di toko dengan telepon sama | Digabung sesuai aturan; tidak duplikat |
| 10 | EOD + opname saat internet putus | Berjalan penuh di toko; sinkron setelah pulih |

## 13. Risiko utama
| Risiko | Mitigasi |
|---|---|
| PC toko menjadi titik tunggal kegagalan | UPS, backup malam, replika di pusat, latihan restore, (opsional) server cadangan manual |
| Replikasi tertinggal lama saat internet putus panjang | Banner & peringatan; laporan pusat diberi label "data sampai pukul …" |
| Kebingungan data ganda (pusat vs toko) | Aturan kepemilikan data tunggal (bagian 5); pusat read-only untuk data POS |
| Menumpuknya kompleksitas bila Mode Darurat dibangun | Jadikan tahap terpisah & opsional dengan batas risiko ketat |
| Hash password di PC toko | BitLocker, hanya akun berhak, firewall LAN |
| Kebijakan akuntansi belum final | Proyeksi pusat mempertahankan event asli sehingga bisa diproyeksikan ulang |

---

## 14. Keputusan owner (2026-10-23) & dampaknya pada rancangan

| # | Jawaban owner | Dampak / tindak lanjut |
|---|---|---|
| 1 | Laptop kedua **kemungkinan lewat WiFi** (toko yang sama) | Asumsi LAN toko tetap berlaku (WiFi toko yang sama = satu jaringan). **Server toko disarankan berkabel LAN** (bukan WiFi) dengan IP tetap/DHCP reservation; router berkualitas; kasir/laptop kedua boleh WiFi (pita 5 GHz). Karena WiFi kurang stabil dan Mode Darurat tidak dibangun (no. 4), tambahkan **prosedur kertas** untuk gangguan WiFi di panduan operasional. Perlu dikonfirmasi: "kemungkinan" → pastikan benar satu toko, satu WiFi. |
| 2 | **Pusat = shared hosting** (mis.kebuni.id) | Memengaruhi sisi pusat saja; **server toko tetap butuh PC sendiri di toko** (shared hosting tidak terjangkau lewat LAN dan bergantung pada internet — justru yang ingin dihindari). Batasan hosting yang dirancang: tidak ada proses latar panjang → pemrosesan lewat **cron** (cPanel), endpoint `sync/push` **ringan & berbatch kecil** (±50 event/permintaan, selesai < 30 dtk), proyeksi dijalankan per batch, tidak ada WebSocket/port khusus. Cek di 3.0: PHP 8.2, cron per menit, batas waktu/memori eksekusi, ukuran database, HTTPS, akses SSH/`php spark`. |
| 3 | Bertanya arti "Domain & HTTPS" | Dijelaskan ke owner: alamat toko berhuruf (mis. `pos.kebuni.id`) beralamat HTTPS agar kamera & PIN aman di WiFi. **Tidak memblokir** — diputuskan di 3.0 (opsi: subdomain milik owner + sertifikat, atau sertifikat lokal terpasang di tiap perangkat). Catatan: karena Mode Darurat (PWA) tidak dibangun, HTTPS tidak lagi wajib untuk Service Worker; tetap **disarankan** karena PIN/sesi melewati WiFi. |
| 4 | **Mode Darurat tidak perlu** | **Tahap 3.5 dihapus dari lingkup.** Saat perangkat terputus dari server toko, kasir memakai prosedur kertas lalu menginput setelah pulih (tanpa penjualan lokal di perangkat). Estimasi total inti ≈ **35–52 person-day**. Mode 3 pada bagian 4 menjadi "tidak didukung — prosedur kertas". |
| 5 | "22 nya bisa?" (dua-duanya: per transaksi **dan** ringkasan harian) | **Bisa.** Pusat menyimpan **event per transaksi** (audit penuh); **ringkasan harian dibuat dari event** itu (tampilan/laporan), tanpa pekerjaan sinkron tambahan. Keputusan: simpan per transaksi + sediakan rekap harian. |
| 6 | Bertanya "akun apa maksudnya" | Dijelaskan: yang dimaksud adalah **akun login pengguna** yang disalin ke server toko supaya staf bisa login saat internet putus (berikut hash password). **Usulan bawaan:** kasir, supervisor toko, admin inventori, ditambah **2–3 Super Admin pilihan owner**; akun lain tidak disalin. Menunggu konfirmasi owner. |
| 7 | Gateway nanti **Midtrans/DOKU**, sekarang manual | QRIS tetap manual. Saat gateway diaktifkan: **notifikasi (webhook) masuk ke pusat** (butuh alamat publik; shared hosting cocok), lalu server toko mengambil status lewat sinkron (`pull`) — Store Node tidak perlu dibuka ke internet. Tidak memblokir Fase 3. |

**Masih terbuka:** (a) PC server toko — apakah sudah ada / siapa merawat / UPS; (b) konfirmasi no. 1 & no. 6; (c) keputusan Keuangan yang menggantung (bonus supplier, F2–F10).
