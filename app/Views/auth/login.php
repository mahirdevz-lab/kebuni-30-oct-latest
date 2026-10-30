<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="auth-wrapper d-flex align-items-center justify-content-center min-vh-100">
    <div class="card auth-card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="fa-solid fa-building text-primary" style="font-size: 2.5rem;"></i>
                <h4 class="mt-3 mb-0">Back Office Management System</h4>
                <p class="text-muted small">Silakan login untuk melanjutkan</p>
            </div>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger py-2 small"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif ?>

            <?php if ($errors = session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger py-2 small">
                    <?php foreach ($errors as $error): ?>
                        <div><?= esc($error) ?></div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <form action="<?= base_url('login') ?>" method="post">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label">Username atau Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                        <input type="text" name="login" class="form-control" value="<?= old('login') ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                    <label class="form-check-label small" for="remember">Ingat saya</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-right-to-bracket me-1"></i> Login
                </button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
