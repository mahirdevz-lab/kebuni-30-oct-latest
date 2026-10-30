<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Nama Barang</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Harga</th>
                <th class="text-end">Total Harga</th>
                <th>Tgl Pembelian</th>
                <th>Status</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($assets as $a): ?>
            <tr id="row-<?= $a['id'] ?>"
                data-name="<?= esc($a['name']) ?>"
                data-qty="<?= esc($a['qty']) ?>"
                data-price="<?= esc($a['price']) ?>"
                data-date="<?= esc($a['purchase_date'] ?? '') ?>"
                data-status="<?= esc($a['status']) ?>"
                data-notes="<?= esc($a['notes'] ?? '') ?>">
                <td><?= $no++ ?></td>
                <td class="fw-semibold"><?= esc($a['name']) ?></td>
                <td class="text-end"><?= rtrim(rtrim(number_format($a['qty'], 2, ',', '.'), '0'), ',') ?></td>
                <td class="text-end">Rp <?= number_format($a['price'], 0, ',', '.') ?></td>
                <td class="text-end fw-bold">Rp <?= number_format($a['qty'] * $a['price'], 0, ',', '.') ?></td>
                <td><?= $a['purchase_date'] ? date('d M Y', strtotime($a['purchase_date'])) : '-' ?></td>
                <td>
                    <?php if ($a['status'] === 'AKTIF'): ?>
                        <span class="badge bg-success">AKTIF</span>
                    <?php elseif ($a['status'] === 'RUSAK'): ?>
                        <span class="badge bg-warning text-dark">RUSAK</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">HILANG</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-outline-primary" onclick="editAsset(<?= $a['id'] ?>)" title="Edit">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAsset(<?= $a['id'] ?>, '<?= esc(addslashes($a['name'])) ?>')" title="Hapus">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($assets)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data aset.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="table-light fw-bold">
                <td colspan="2">TOTAL</td>
                <td class="text-end"><?= number_format($valuation['total_qty'], 0, ',', '.') ?></td>
                <td></td>
                <td class="text-end">Rp <?= number_format($valuation['total_value'], 0, ',', '.') ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
</div>
