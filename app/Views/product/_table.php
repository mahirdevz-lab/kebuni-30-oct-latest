<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Code</th>
                <th>Nama Product</th>
                <th>Category</th>
                <th>Unit</th>
                <th>Shrinkage</th>
                <th>Status</th>
                <th width="140">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <p class="mb-0">Belum ada data product.</p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1; ?>
                <?php foreach ($products as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><span class="badge bg-light text-dark border"><?= esc($row['product_code']) ?></span></td>
                        <td class="fw-semibold"><?= esc($row['product_name']) ?>
                            <?php if (! in_array((int) $row['id'], $recipeProductIds ?? [], true)): ?>
                                <span class="badge bg-warning text-dark ms-1" title="Produk ini belum punya packing recipe">tanpa recipe</span>
                            <?php endif ?>
                        </td>
                        <td><?= esc($row['category_name'] ?? '-') ?></td>
                        <td><?= esc($row['unit']) ?></td>
                        <td>
                            <?= $row['shrinkage_type'] === 'PERCENTAGE'
                                ? number_format((float) $row['shrinkage_value'], 2) . '%'
                                : 'Rp ' . number_format((float) $row['shrinkage_value'], 0, ',', '.') ?>
                        </td>
                        <td>
                            <span class="badge <?= $row['status'] === 'aktif' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= esc(ucfirst($row['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php $hasRecipe = in_array((int) $row['id'], $recipeProductIds ?? [], true); ?>
                                <button aria-label="<?= $hasRecipe ? 'Packing Recipe' : 'Belum ada packing recipe — klik untuk menambahkan' ?>" class="btn btn-sm btn-outline-secondary btn-recipe-product" data-id="<?= $row['id'] ?>" data-name="<?= esc($row['product_name']) ?>" title="<?= $hasRecipe ? 'Packing Recipe' : 'Belum ada packing recipe — klik untuk menambahkan' ?>">
                                    <i class="fa-solid fa-box-open <?= $hasRecipe ? '' : 'opacity-25' ?>"></i>
                                </button>
                                <button aria-label="Edit" class="btn btn-sm btn-outline-primary btn-edit-product" data-id="<?= $row['id'] ?>" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button aria-label="Hapus" class="btn btn-sm btn-outline-danger btn-delete-product" data-id="<?= $row['id'] ?>" title="Hapus">
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
