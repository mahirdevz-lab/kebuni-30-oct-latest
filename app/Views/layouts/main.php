<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>try { const th = localStorage.getItem('bo-theme'); if (th) document.documentElement.setAttribute('data-bs-theme', th); } catch (e) {}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= isset($title) ? esc($title) . ' - ' : '' ?>Kebuni Back Office</title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4=" crossorigin="anonymous"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
    <?= $this->renderSection('styles') ?>
</head>
<body>
<?= $this->include('partials/training_banner') ?>

<div class="wrapper">
    <?= $this->include('partials/sidebar') ?>

    <div class="main-content">
        <?= $this->include('partials/navbar') ?>

        <div class="content-body">
            <?= $this->include('partials/breadcrumb') ?>
            <?= $this->include('partials/flash_message') ?>

            <?= $this->renderSection('content') ?>
        </div>
    </div>
</div>

<?= $this->include('partials/modal_confirm') ?>
<?= $this->include('partials/loading') ?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100"></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

<script>
    // CSRF token global, dipakai setiap request AJAX (POST/PUT/DELETE)
    const CSRF_TOKEN_NAME = "<?= csrf_token() ?>";
    let CSRF_HASH = "<?= csrf_hash() ?>";
    const BASE_URL = "<?= base_url() ?>";

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': CSRF_HASH }
    });
</script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script src="<?= base_url('assets/js/app-ui.js') ?>"></script>

<?= $this->renderSection('scripts') ?>

<!-- Modal konfirmasi reusable (dipakai confirmAction() di app.js) -->
<div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfirmTitle">Konfirmasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="modalConfirmText">Apakah Anda yakin?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="modalConfirmBtn">Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>
