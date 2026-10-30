<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Kode</th>
                <th>Nama</th>
                <th>Periode</th>
                <th>Status</th>
                <th width="80">Detail</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($periods)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <p class="mb-0">Belum ada Price Period.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php $badgeMap = ['DRAFT' => 'bg-secondary', 'PUBLISHED' => 'bg-success', 'CLOSED' => 'bg-dark']; ?>
                <?php foreach ($periods as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><span class="badge bg-light text-dark border"><?= esc($row['period_code']) ?></span></td>
                        <td class="fw-semibold"><?= esc($row['name']) ?></td>
                        <td><?= tgl_id($row['start_date']) ?> &mdash; <?= tgl_id($row['end_date']) ?></td>
                        <td><span class="badge <?= $badgeMap[$row['status']] ?>"><?= esc($row['status']) ?></span></td>
                        <td>
                            <a href="<?= base_url('admin/price-period/' . $row['id']) ?>" class="btn btn-sm btn-outline-primary" title="Detail">
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>

<?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>
