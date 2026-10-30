<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Nama Account</th>
                <th>Kota</th>
                <th>Telepon</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Status</th>
                <th width="130">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($accounts)): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <p class="mb-0">Belum ada data account.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php foreach ($accounts as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-semibold"><?= esc($row['nama_account']) ?></td>
                        <td><?= esc($row['kota'] ?? '-') ?></td>
                        <td><?= esc($row['nomor_telepon'] ?? '-') ?></td>
                        <td><?= esc($row['email'] ?? '-') ?></td>
                        <td><?= esc($row['contact_person'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $row['status'] === 'aktif' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= esc(ucfirst($row['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button aria-label="Detail" class="btn btn-sm btn-outline-primary btn-detail-account" data-id="<?= $row['id'] ?>" title="Detail">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button aria-label="Edit" class="btn btn-sm btn-outline-primary btn-edit-account" data-id="<?= $row['id'] ?>" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button aria-label="Toggle Status" class="btn btn-sm btn-outline-secondary btn-toggle-account" data-id="<?= $row['id'] ?>" title="Toggle Status">
                                    <i class="fa-solid fa-toggle-on"></i>
                                </button>
                                <button aria-label="Hapus" class="btn btn-sm btn-outline-danger btn-delete-account" data-id="<?= $row['id'] ?>" title="Hapus">
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
