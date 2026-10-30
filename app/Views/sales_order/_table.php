<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th width="40">No</th><th>No. PO</th><th>Tanggal</th><th>Customer</th>
                <th>Total Qty</th><th>Grand Total</th><th>Status</th><th width="80">Detail</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Belum ada Sales Order.</p></div></td></tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php $badgeMap = ['Draft'=>'bg-secondary','Submitted'=>'bg-info text-dark','Approved'=>'bg-primary','Processing'=>'bg-warning text-dark','Completed'=>'bg-success','Cancelled'=>'bg-danger']; ?>
                <?php foreach ($orders as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><span class="badge bg-light text-dark border"><?= esc($row['order_no']) ?></span></td>
                        <td><?= tgl_id($row['order_date']) ?></td>
                        <td class="fw-semibold"><?= esc($row['nama_account']) ?></td>
                        <td><?= number_format((float) $row['total_qty'], 0, ',', '.') ?> Kg</td>
                        <td>Rp <?= number_format((float) $row['grand_total'], 0, ',', '.') ?></td>
                        <td><span class="badge <?= $badgeMap[$row['status']] ?? 'bg-secondary' ?>"><?= esc($row['status']) ?></span></td>
                        <td><a href="<?= base_url('admin/sales-order/' . $row['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-arrow-right"></i></a></td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>
<?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>
