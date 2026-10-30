<?php $auth = new \App\Services\AuthService(); $currentUser = $auth->user(); ?>
<nav class="topbar navbar navbar-expand">
    <div class="topbar-left">
        <button class="btn btn-link sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

    <div class="topbar-right ms-auto d-flex align-items-center gap-2">
        <?php if ($auth->hasPermission('price_period.view')): ?>
            <a href="<?= base_url('admin/sales-order/create') ?>" class="btn btn-primary btn-sm d-none d-md-inline-flex align-items-center" title="Buat Taking Order baru (2 klik dari mana saja)">
                <i class="fa-solid fa-plus me-1"></i> Taking Order
            </a>
        <?php endif ?>
        <button class="icon-btn" id="themeToggle" type="button" title="Ganti tema terang/gelap" aria-label="Ganti tema">
            <i class="fa-solid fa-moon"></i>
        </button>

        <div class="dropdown">
            <button class="icon-btn" id="notifBtn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Notifikasi">
                <i class="fa-regular fa-bell"></i>
                <span class="badge-dot d-none" id="notifCount">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end notif-menu" id="notifMenu">
                <div class="px-3 py-2 fw-semibold border-bottom">Notifikasi</div>
                <div id="notifList"><div class="p-3 text-muted small">Memuat...</div></div>
            </div>
        </div>

        <div class="dropdown ms-2">
            <a class="user-dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                <span class="avatar-circle"><?= esc(strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1))) ?></span>
                <span class="d-none d-md-inline lh-sm">
                    <?= esc($currentUser['full_name'] ?? '-') ?>
                    <small class="d-block text-muted"><?= esc($currentUser['role_name'] ?? '-') ?></small>
                </span>
                <i class="fa-solid fa-chevron-down small text-muted"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= base_url('admin/profile') ?>"><i class="fa-solid fa-user me-2"></i>Profil Saya</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="<?= base_url('logout') ?>" method="post" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>
