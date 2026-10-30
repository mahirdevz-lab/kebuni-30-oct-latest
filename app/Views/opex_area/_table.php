<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Code</th>
                <th>Nama</th>
                <th>Tautan Destination</th>
                <th>Status</th>
                <th width="100">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($areas)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <p class="mb-0">Belum ada OPEX area.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php foreach ($areas as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><span class="badge bg-light text-dark border"><?= esc($row['code']) ?></span></td>
                        <td class="fw-semibold"><?= esc($row['name']) ?></td>
                        <td><?= $row['destination_name'] ? esc($row['destination_name']) : '<span class="text-muted">Cost Center</span>' ?></td>
                        <td>
                            <span class="badge <?= $row['status'] === 'aktif' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= esc(ucfirst($row['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button aria-label="Edit" class="btn btn-sm btn-outline-primary btn-edit-area" data-id="<?= $row['id'] ?>" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button aria-label="Hapus" class="btn btn-sm btn-outline-danger btn-delete-area" data-id="<?= $row['id'] ?>" title="Hapus">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>

<?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>
