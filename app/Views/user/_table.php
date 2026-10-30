<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr><th width="40">No</th><th>Nama</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th width="100">Action</th></tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Belum ada user.</p></div></td></tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-semibold"><?= esc($u['full_name']) ?><?= (int) $u['id'] === $currentId ? ' <span class="badge bg-info text-dark">Anda</span>' : '' ?></td>
                        <td><?= esc($u['username']) ?></td>
                        <td><?= esc($u['email']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= esc($u['role_name']) ?></span></td>
                        <td><span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= $u['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <td class="small text-muted"><?= $u['last_login_at'] ? tgl_id($u['last_login_at'], 'dd MMM yyyy HH.mm') : '-' ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="<?= base_url('admin/user/' . $u['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <?php if ((int) $u['id'] !== $currentId): ?>
                                    <button aria-label="Hapus" class="btn btn-sm btn-outline-danger btn-delete-user" data-id="<?= $u['id'] ?>" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                <?php endif ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>
<?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>
