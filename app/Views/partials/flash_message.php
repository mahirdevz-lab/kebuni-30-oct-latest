<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
    <?php foreach (['success' => 'check-circle', 'error' => 'circle-exclamation', 'warning' => 'triangle-exclamation', 'info' => 'circle-info'] as $type => $icon): ?>
        <?php if ($message = session()->getFlashdata($type)): ?>
            <div class="toast align-items-center text-bg-<?= $type === 'error' ? 'danger' : $type ?> border-0 show" role="alert" data-bs-autohide="true" data-bs-delay="4000">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fa-solid fa-<?= $icon ?> me-2"></i><?= esc($message) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Tutup notifikasi" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif ?>
    <?php endforeach ?>
</div>
