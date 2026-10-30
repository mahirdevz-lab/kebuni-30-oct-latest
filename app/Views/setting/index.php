<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card" style="max-width:720px">
    <div class="section-header"><i class="fa-solid fa-gears me-1"></i> Pengaturan Aplikasi</div>
    <div class="card-body">
        <form id="settingForm">
            <div class="mb-3">
                <label class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                <input type="text" name="company_name" class="form-control" maxlength="100" required value="<?= esc($settings['company_name'] ?? '') ?>">
                <div class="form-text">Ditampilkan di sidebar.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Batas Jam Masuk (Absensi) <span class="text-danger">*</span></label>
                <input type="time" name="attendance_late_after" class="form-control" required value="<?= esc(substr($settings['attendance_late_after'] ?? '08:00', 0, 5)) ?>">
                <div class="form-text">Absen masuk setelah jam ini dianggap terlambat.</div>
            </div>

            <h6 class="mt-4 mb-2"><i class="fa-solid fa-store me-1"></i> Kebuni Mart — Stok & Opname</h6>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label">Selisih besar: % dari saldo buku</label>
                    <div class="input-group"><input type="number" name="opname_variance_pct" class="form-control" min="0" step="0.1" required value="<?= esc($settings['opname_variance_pct'] ?? '5') ?>"><span class="input-group-text">%</span></div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Selisih besar: nilai minimal</label>
                    <div class="input-group"><span class="input-group-text">Rp</span><input type="number" name="opname_variance_value" class="form-control" min="0" step="1000" required value="<?= esc($settings['opname_variance_value'] ?? '50000') ?>"></div>
                </div>
                <div class="col-12 form-text mt-1">Baris opname dengan selisih ≥ % <em>atau</em> ≥ nilai ini wajib dihitung ulang sebelum posting.</div>
                <div class="col-sm-6">
                    <label class="form-label">Produk "high risk" bila penyusutan ≥</label>
                    <div class="input-group"><input type="number" name="shrinkage_high_risk_pct" class="form-control" min="0" step="0.1" required value="<?= esc($settings['shrinkage_high_risk_pct'] ?? '5') ?>"><span class="input-group-text">%</span></div>
                    <div class="form-text">dari persediaan keluar (HPP terjual + penyusutan).</div>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Stok cukup untuk (hari)</label>
                    <input type="number" name="reorder_cover_days" class="form-control" min="1" max="60" required value="<?= esc($settings['reorder_cover_days'] ?? '7') ?>">
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Rata-rata jual dari (hari)</label>
                    <input type="number" name="reorder_lookback_days" class="form-control" min="1" max="90" required value="<?= esc($settings['reorder_lookback_days'] ?? '14') ?>">
                </div>
                <div class="col-12 form-text mt-0">Saran pesan ulang = (hari cukup × rata-rata jual per hari) − stok sekarang.</div>
                <div class="col-sm-6">
                    <label class="form-label">Biaya bonus supplier</label>
                    <select name="bonus_cost_policy" class="form-select">
                        <option value="allocate" <?= ($settings['bonus_cost_policy'] ?? 'allocate') === 'allocate' ? 'selected' : '' ?>>Dibagi ke seluruh unit (reguler + bonus)</option>
                        <option value="zero" <?= ($settings['bonus_cost_policy'] ?? '') === 'zero' ? 'selected' : '' ?>>Bonus bernilai Rp0</option>
                    </select>
                    <div class="form-text">Menunggu keputusan Keuangan (PRD A6). Berlaku untuk penerimaan berikutnya.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Simpan</button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$('#settingForm').on('submit', function (e) {
    e.preventDefault();
    ajaxPost("<?= base_url('admin/setting/update') ?>", $(this).serialize());
});
</script>
<?= $this->endSection() ?>
