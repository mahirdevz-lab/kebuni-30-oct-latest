<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr><th width="50">No</th><th>Nama</th><th>Status</th><th width="100">Action</th></tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Belum ada kategori.</p></div></td></tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php foreach ($categories as $row): ?>
                    <tr>
                        <td class="num"><?= $no++ ?></td>
                        <td class="fw-semibold"><?= esc($row['name']) ?></td>
                        <td><?= $this->include('partials/status_badge', ['status' => $row['status']]) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <button aria-label="Edit" class="btn btn-sm btn-outline-primary btn-edit" data-id="<?= $row['id'] ?>" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <button aria-label="Hapus" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= $row['id'] ?>" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>

<?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>
