<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Nama Supplier</th>
                <th>Kota</th>
                <th>Telepon</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Status</th>
                <th width="110">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($suppliers)): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <p class="mb-0">Belum ada data supplier.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php foreach ($suppliers as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-semibold"><?= esc($row['nama_supplier']) ?></td>
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
                                <button aria-label="Edit" class="btn btn-sm btn-outline-primary btn-edit-supplier" data-id="<?= $row['id'] ?>" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button aria-label="Toggle Status" class="btn btn-sm btn-outline-secondary btn-toggle-supplier" data-id="<?= $row['id'] ?>" title="Toggle Status">
                                    <i class="fa-solid fa-toggle-on"></i>
                                </button>
                                <button aria-label="Hapus" class="btn btn-sm btn-outline-danger btn-delete-supplier" data-id="<?= $row['id'] ?>" title="Hapus">
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
