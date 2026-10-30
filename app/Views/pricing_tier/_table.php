<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr><th width="40">No</th><th>Nama</th><th>Range Qty</th><th>Markup</th><th>Periode</th><th>Status</th><th width="100">Action</th></tr>
        </thead>
        <tbody>
            <?php if (empty($tiers)): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Belum ada pricing tier.</p></div></td></tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php foreach ($tiers as $row): ?>
                    <tr>
                        <td class="num"><?= $no++ ?></td>
                        <td class="fw-semibold"><?= esc($row['name']) ?></td>
                        <td class="num"><?= number_format((float) $row['min_qty'], 0) ?> &ndash; <?= $row['max_qty'] !== null ? number_format((float) $row['max_qty'], 0) : '&infin;' ?> Kg</td>
                        <td><span class="badge bg-light text-dark border num"><?= number_format((float) $row['markup_percent'], 2) ?>%</span></td>
                        <td class="small text-muted">
                            <?= $row['start_date'] ? tgl_id($row['start_date']) : '-' ?>
                            &mdash;
                            <?= $row['end_date'] ? tgl_id($row['end_date']) : '-' ?>
                        </td>
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
