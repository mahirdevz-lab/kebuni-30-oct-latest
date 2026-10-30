<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$badge = ['DRAFT' => ['warning', 'Draft'], 'POSTED' => ['success', 'Diposting'], 'CANCELLED' => ['secondary', 'Dibatalkan']];
$canCreate = (new \App\Services\AuthService())->hasPermission('stock.opening');
?>
<div class="card mb-3">
    <div class="card-body">
        <h6 class="mb-2">Langkah go-live toko</h6>
        <ol class="small mb-3">
            <li>Toko belum berjualan (atau tutup). Lakukan <strong>hitung fisik semua barang</strong> di toko (blind count).</li>
            <li>Buat draft stok awal dengan <strong>cut-off</strong> = waktu hitung, isi qty hasil hitung dan biaya per unit
                (default: harga beli price period terakhir — periksa & sesuaikan).</li>
            <li>Admin memeriksa lalu <strong>posting</strong>: setiap produk masuk ke buku stok sebagai mutasi <em>Stok awal</em>.</li>
            <li>Setelah itu stok hanya berubah lewat penerimaan, penjualan, retur, Broken Stock, dan stock opname.</li>
        </ol>
        <div class="alert alert-info small mb-3">
            Stok awal toko <strong>tidak</strong> diambil dari Final Report — hasil audit menunjukkan angka di sana belum dapat dipakai
            (stok aktual belum diinput, saldo buku negatif, harga hard-coded). Lihat <code>docs/AUDIT-FINAL-REPORT.md</code>.
            Produk yang sudah punya mutasi (<?= (int) $withStock ?> produk) tidak bisa diberi stok awal lagi.
        </div>
        <?php if ($draft): ?>
            <a href="<?= base_url('admin/stock/opening/' . $draft['id']) ?>" class="btn btn-primary"><i class="fa-solid fa-pen me-1"></i> Lanjutkan draft <?= esc($draft['opening_no']) ?></a>
        <?php elseif ($canCreate): ?>
            <div class="row g-2 align-items-end">
                <div class="col-sm-4"><label class="form-label mb-1">Cut-off (waktu hitung fisik) *</label><input type="datetime-local" id="soAt" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" max="<?= date('Y-m-d\TH:i') ?>"></div>
                <div class="col-sm-5"><label class="form-label mb-1">Catatan</label><input type="text" id="soNotes" class="form-control" maxlength="255" placeholder="Mis. hitung fisik go-live oleh tim toko"></div>
                <div class="col-sm-3"><button class="btn btn-primary w-100" id="btnCreate"><i class="fa-solid fa-plus me-1"></i> Buat Draft</button></div>
            </div>
        <?php endif ?>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Dokumen stok awal</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>No</th><th>Cut-off</th><th>Status</th><th class="text-end">Produk</th><th class="text-end">Nilai</th><th>Dibuat</th><th>Diposting</th></tr></thead>
            <tbody>
            <?php if (! $docs): ?><tr><td colspan="7" class="text-center text-muted py-3">Belum ada.</td></tr><?php endif ?>
            <?php foreach ($docs as $d): ?>
                <tr>
                    <td><a href="<?= base_url('admin/stock/opening/' . $d['id']) ?>"><?= esc($d['opening_no']) ?></a></td>
                    <td><?= date('d/m/Y H:i', strtotime($d['effective_at'])) ?></td>
                    <td><span class="badge text-bg-<?= $badge[$d['status']][0] ?>"><?= $badge[$d['status']][1] ?></span></td>
                    <td class="text-end"><?= (int) $d['n_lines'] ?></td>
                    <td class="text-end"><?= number_format((float) $d['total_value'], 0, ',', '.') ?></td>
                    <td class="small"><?= esc($d['created_name'] ?? '-') ?></td>
                    <td class="small"><?= $d['posted_at'] ? esc($d['posted_name'] ?? '-') . ' ' . date('d/m/Y H:i', strtotime($d['posted_at'])) : '-' ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('#btnCreate').on('click', () => ajaxPost(BASE_URL + 'admin/stock/opening/create', { effective_at: $('#soAt').val(), notes: $('#soNotes').val() },
    res => location.href = BASE_URL + 'admin/stock/opening/' + res.id));
</script>
<?= $this->endSection() ?>
