<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Keterangan</th>
                <th>Tanda Bukti</th>
                <th>Rekening</th>
                <th>Sumber</th>
                <th class="text-end">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <p class="mb-0">Belum ada pengeluaran.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php $refLabels = ['MANUAL_EXPENSE' => 'Manual', 'PAYABLE_PAYMENT' => 'Bayar Hutang', 'TRANSFER_OUT' => 'Transfer Keluar', 'TRANSFER_IN' => 'Transfer Masuk']; ?>
                <?php foreach ($transactions as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= tgl_id($row['transaction_date']) ?></td>
                        <td><?= $row['category_name'] ? esc($row['category_name']) : '<span class="text-muted">-</span>' ?></td>
                        <td><?= esc($row['description'] ?? '-') ?></td>
                        <td>
                            <?php if (! empty($row['proof_label'])): ?>
                                <span class="badge bg-info"><?= esc($row['proof_label']) ?></span>
                            <?php endif; ?>
                            <?php if (! empty($row['attachment_path'])): ?>
                                <a href="<?= base_url('admin/finance/expense-proof/' . $row['id']) ?>" target="_blank" class="badge bg-primary text-decoration-none" title="Lihat bukti"><i class="fa-solid fa-paperclip"></i></a>
                            <?php endif; ?>
                            <?php if (empty($row['proof_label']) && empty($row['attachment_path'])): ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($row['account_name'] ?? '-') ?></td>
                        <td><span class="badge bg-light text-dark border"><?= esc($refLabels[$row['reference_type']] ?? $row['reference_type']) ?></span></td>
                        <td class="text-end fw-semibold text-danger">Rp <?= number_format((float) $row['amount'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-end mt-3">
    <?= $pager->links() ?>
</div>

