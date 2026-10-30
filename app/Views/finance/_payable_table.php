<?php $badgeMap = ['UNPAID' => 'bg-secondary', 'PARTIAL' => 'bg-warning text-dark', 'PAID' => 'bg-success', 'OVERDUE' => 'bg-danger']; ?>
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Invoice</th>
                <th>Supplier</th>
                <th>Tgl Transaksi</th>
                <th>Jatuh Tempo</th>
                <th class="text-end">Total</th>
                <th class="text-end">Terbayar</th>
                <th class="text-end">Sisa</th>
                <th>Status</th>
                <th width="90">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payables)): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <p class="mb-0">Belum ada hutang.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php foreach ($payables as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><span class="badge bg-light text-dark border"><?= esc($row['invoice_number']) ?></span></td>
                        <td class="fw-semibold"><?= esc($row['nama_supplier']) ?></td>
                        <td><?= tgl_id($row['transaction_date']) ?></td>
                        <td><?= $row['due_date'] ? tgl_id($row['due_date']) : '-' ?></td>
                        <td class="text-end">Rp <?= number_format((float) $row['total_amount'], 0, ',', '.') ?></td>
                        <td class="text-end">Rp <?= number_format((float) $row['paid_amount'], 0, ',', '.') ?></td>
                        <td class="text-end fw-semibold">Rp <?= number_format((float) $row['remaining_amount'], 0, ',', '.') ?></td>
                        <td><span class="badge <?= $badgeMap[$row['status']] ?? 'bg-secondary' ?>"><?= esc($row['status']) ?></span></td>
                        <td>
                            <?php if ($row['status'] !== 'PAID'): ?>
                            <button class="btn btn-sm btn-outline-primary btn-pay-payable"
                                data-id="<?= $row['id'] ?>"
                                data-invoice="<?= esc($row['invoice_number']) ?>"
                                data-remaining="Rp <?= number_format((float) $row['remaining_amount'], 0, ',', '.') ?>"
                                data-remaining-raw="<?= $row['remaining_amount'] ?>">
                                <i class="fa-solid fa-money-bill-wave"></i> Bayar
                            </button>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>

<?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>
