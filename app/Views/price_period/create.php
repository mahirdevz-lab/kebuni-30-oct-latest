<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card" style="max-width: 640px;">
    <div class="section-header"><i class="fa-solid fa-calendar-plus me-1"></i> Buat Price Period Baru</div>
    <div class="card-body">
        <div class="alert alert-info border-0 small">
            <i class="fa-solid fa-circle-info me-1"></i>
            Kode Price Period akan digenerate otomatis. Default periode 7 hari, tetapi tanggal dapat diubah.
            Setelah dibuat (status Draft), Anda dapat mengisi Product Price, Packing Price, dan Transport Price
            pada halaman detail sebelum melakukan <strong>Publish</strong>.
        </div>

        <form id="periodForm">
            <div class="mb-3">
                <label class="form-label">Nama Price Period <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control" placeholder="Periode 11-17 Agustus 2026" required>
                <div class="invalid-feedback" data-error-for="name"></div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $defaultStart ?>" required>
                    <div class="invalid-feedback" data-error-for="start_date"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Date <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $defaultEnd ?>" required>
                    <div class="invalid-feedback" data-error-for="end_date"></div>
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
            </div>

            <div class="d-flex gap-2">
                <a href="<?= base_url('admin/price-period') ?>" class="btn btn-light px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Auto-adjust End Date +6 hari dari Start Date jika user mengubah Start Date
$('#start_date').on('change', function () {
    const start = new Date(this.value);
    if (! isNaN(start)) {
        const end = new Date(start);
        end.setDate(end.getDate() + 6);
        $('#end_date').val(end.toISOString().slice(0, 10));
    }
});

$('#periodForm').on('submit', function (e) {
    e.preventDefault();
    $('.invalid-feedback').text('');
    $('.is-invalid').removeClass('is-invalid');
    showLoading();

    $.post("<?= base_url('admin/price-period/store') ?>", $(this).serialize())
        .done(function (res) {
            if (res.success) {
                showToast(res.message, 'success');
                setTimeout(() => window.location.href = "<?= base_url('admin/price-period') ?>/" + res.id, 600);
            } else {
                showToast(res.message || 'Data tidak valid.', 'error');
                if (res.errors) {
                    Object.keys(res.errors).forEach(f => {
                        $(`[data-error-for="${f}"]`).text(res.errors[f]);
                        $(`#${f}`).addClass('is-invalid');
                    });
                }
            }
        })
        .fail(() => showToast('Terjadi kesalahan pada server.', 'error'))
        .always(hideLoading);
});
</script>
<?= $this->endSection() ?>
