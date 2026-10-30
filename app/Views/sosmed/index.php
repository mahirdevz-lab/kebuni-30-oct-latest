<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3 mb-4">
    <?php foreach (['SUBMITTED' => 'Menunggu Review', 'NEEDS_REVISION' => 'Perlu Revisi', 'APPROVED' => 'Disetujui', 'REJECTED' => 'Ditolak', 'PUBLISHED' => 'Terbit'] as $st => $label): ?>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="badge bg-<?= $statusColors[$st] ?> fs-6"><?= $countByStatus[$st] ?? 0 ?></span>
                <div class="text-muted small"><?= $label ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="section-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-photo-film me-1"></i> Daftar Konten</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSubmit">
            <i class="fa-solid fa-plus me-1"></i> Submit Konten
        </button>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari judul / pembuat..." value="<?= esc($search ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select id="statusFilter" class="form-select">
                    <option value="">Semua Status</option>
                    <?php foreach ($statusLabels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($status ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="typeFilter" class="form-select">
                    <option value="">Semua Tipe</option>
                    <option value="FEED" <?= ($type ?? '') === 'FEED' ? 'selected' : '' ?>>Feeds</option>
                    <option value="VIDEO" <?= ($type ?? '') === 'VIDEO' ? 'selected' : '' ?>>Video</option>
                </select>
            </div>
        </div>
        <div id="tableContainer"><?= $this->include('sosmed/_table') ?></div>
    </div>
</div>

<!-- Modal Submit Konten -->
<div class="modal fade" id="modalSubmit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Konten Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="submitForm" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Judul Konten <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="255" placeholder="cth: Promo Sayur Fresh Minggu Ini">
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Tipe Konten <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="type" id="typeFeed" value="FEED" checked>
                            <label class="btn btn-outline-primary" for="typeFeed"><i class="fa-solid fa-image me-1"></i> Feeds</label>
                            <input type="radio" class="btn-check" name="type" id="typeVideo" value="VIDEO">
                            <label class="btn btn-outline-primary" for="typeVideo"><i class="fa-solid fa-video me-1"></i> Video</label>
                        </div>
                    </div>
                    <div class="mb-3" id="canvaField">
                        <label class="form-label">Link Canva <span class="text-danger">*</span></label>
                        <input type="url" name="canva_link" class="form-control" placeholder="https://canva.com/design/...">
                    </div>
                    <div class="mb-3 d-none" id="videoField">
                        <label class="form-label">Upload Video <span class="text-danger">*</span></label>
                        <input type="file" name="video" class="form-control" accept=".mp4,.mov,.webm">
                        <div class="form-text">MP4/MOV/WebM, maksimal 100MB.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Draft Caption</label>
                        <textarea name="caption_draft" class="form-control" rows="3" placeholder="Opsional"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitContent">Submit untuk Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const BASE = "<?= base_url('admin/sosmed') ?>";

function loadTable() {
    $.get(BASE, { search: $('#searchInput').val(), status: $('#statusFilter').val(), type: $('#typeFilter').val() }, function (html) {
        $('#tableContainer').html(html);
    });
}

$(function () {
    let timer;
    $('#searchInput').on('keyup', function () {
        clearTimeout(timer);
        timer = setTimeout(loadTable, 400);
    });
    $('#statusFilter, #typeFilter').on('change', loadTable);

    $('input[name=type]').on('change', function () {
        const isFeed = $(this).val() === 'FEED';
        $('#canvaField').toggleClass('d-none', !isFeed);
        $('#videoField').toggleClass('d-none', isFeed);
    });

    $('#submitForm').on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const $btn = $('#btnSubmitContent').prop('disabled', true);

        $.ajax({
            url: BASE + '/store',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    bootstrap.Modal.getInstance($('#modalSubmit')).hide();
                    $('#submitForm')[0].reset();
                    loadTable();
                } else {
                    showToast(res.message, 'error');
                }
            },
            error: function () { showToast('Gagal submit.', 'error'); },
            complete: function () { $btn.prop('disabled', false); }
        });
    });
});
</script>
<?= $this->endSection() ?>
