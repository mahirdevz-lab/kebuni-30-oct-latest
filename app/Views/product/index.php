<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="section-header" id="formTitle"><i class="fa-solid fa-boxes-stacked me-1"></i> Tambah Product</div>
    <div class="card-body">
        <form id="productForm">
            <input type="hidden" name="id" id="product_id">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Product Code <span class="text-danger">*</span></label>
                    <input type="text" name="product_code" id="product_code" class="form-control" required placeholder="DB-001">
                    <div class="invalid-feedback" data-error-for="product_code"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                    <input type="text" name="product_name" id="product_name" class="form-control" required placeholder="Daun Bawang">
                    <div class="invalid-feedback" data-error-for="product_name"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" id="category_id" class="form-select">
                        <option value="">-- Tanpa Category --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit <span class="text-danger">*</span></label>
                    <select name="unit" id="unit" class="form-select" required>
                        <?php foreach (\App\Models\ProductModel::UNITS as $u): ?>
                            <option value="<?= $u ?>"><?= $u ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Shrinkage Type</label>
                    <select name="shrinkage_type" id="shrinkage_type" class="form-select">
                        <option value="PERCENTAGE">Percentage (%)</option>
                        <option value="FIXED">Fixed (Rp)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Shrinkage Value</label>
                    <input type="number" step="0.0001" min="0" name="shrinkage_value" id="shrinkage_value" class="form-control" placeholder="3 (artinya 3% jika Percentage)">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="button" class="btn btn-light px-4" id="btnReset">Bersihkan</button>
                <button type="submit" class="btn btn-primary px-4" id="btnSave">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Product</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama / code produk...">
            </div>
            <div class="col-md-3">
                <select id="categoryFilter" class="form-select">
                    <option value="">Semua Category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="statusFilter" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
            </div>
        </div>
        <div id="tableContainer"><?= $this->include('product/_table') ?></div>
    </div>
</div>

<!-- Modal Packing Recipe -->
<div class="modal fade" id="modalRecipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Packing Recipe - <span id="recipeProductName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="recipe_product_id">
                <p class="text-muted small">Tentukan packing material yang digunakan product ini beserta quantity/usage-nya. Total packing cost/Kg akan dihitung otomatis pada saat Price Period aktif (fase berikutnya).</p>
                <table class="table table-sm" id="recipeTable">
                    <thead>
                        <tr>
                            <th width="55%">Packing Material</th>
                            <th width="30%">Quantity / Usage</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="recipeTableBody"></tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRecipeRow">
                    <i class="fa-solid fa-plus me-1"></i> Tambah Packing Material
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSaveRecipe">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Recipe
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const ENDPOINT = {
    list:       "<?= base_url('admin/product') ?>",
    store:      "<?= base_url('admin/product/store') ?>",
    edit:       (id) => `<?= base_url('admin/product') ?>/${id}/edit`,
    update:     (id) => `<?= base_url('admin/product') ?>/${id}/update`,
    del:        (id) => `<?= base_url('admin/product') ?>/${id}/delete`,
    recipe:     (id) => `<?= base_url('admin/product') ?>/${id}/recipe`,
    saveRecipe: (id) => `<?= base_url('admin/product') ?>/${id}/recipe/save`,
};

let packingMaterialOptions = [];

function loadTable(page = 1) {
    $('#tableContainer').html(tableSkeleton(8));
    $.get(ENDPOINT.list, {
        search: $('#searchInput').val(),
        category_id: $('#categoryFilter').val(),
        status: $('#statusFilter').val(),
        page
    }, function (html) {
        $('#tableContainer').html(html);
    });
}

function resetForm() {
    $('#productForm')[0].reset();
    $('#product_id').val('');
    $('#formTitle').html('<i class="fa-solid fa-boxes-stacked me-1"></i> Tambah Product');
    $('#btnSave').text('Simpan');
    $('.invalid-feedback').text('');
    $('.is-invalid').removeClass('is-invalid');
    $('#tableContainer tbody tr').removeClass('table-active');
}

$(function () {
    let searchTimer;
    $('#searchInput').on('keyup', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadTable(1), 400);
    });
    $('#categoryFilter, #statusFilter').on('change', () => loadTable(1));

    $(document).on('click', '#tableContainer .pagination a', function (e) {
        e.preventDefault();
        const url = new URL(this.href);
        loadTable(url.searchParams.get('page') || 1);
    });

    $('#btnReset').on('click', resetForm);

    $('#productForm').on('submit', function (e) {
        e.preventDefault();
        const id = $('#product_id').val();
        const url = id ? ENDPOINT.update(id) : ENDPOINT.store;

        $('.invalid-feedback').text('');
        $('.is-invalid').removeClass('is-invalid');
        showLoading();

        $.post(url, $(this).serialize())
            .done(function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    resetForm();
                    loadTable();
                } else {
                    showToast(res.message || 'Data tidak valid.', 'error');
                    if (res.errors) {
                        Object.keys(res.errors).forEach(f => {
                            $(`[data-error-for="${f}"]`).text(res.errors[f]);
                            $(`#${f}`).addClass('is-invalid');
                        });
                    }
                }
            })
            .fail(() => showToast('Terjadi kesalahan pada server.', 'error'))
            .always(hideLoading);
    });

    $(document).on('click', '.btn-edit-product', function () {
        const id = $(this).data('id');
        // Highlight baris yang sedang diedit (c.4 PRD).
        $('#tableContainer tbody tr').removeClass('table-active');
        $(this).closest('tr').addClass('table-active');
        showLoading();
        $.get(ENDPOINT.edit(id), function (res) {
            if (res.success) {
                const d = res.data;
                $('#product_id').val(d.id);
                $('#product_code').val(d.product_code);
                $('#product_name').val(d.product_name);
                $('#category_id').val(d.category_id);
                $('#unit').val(d.unit);
                $('#shrinkage_type').val(d.shrinkage_type);
                $('#shrinkage_value').val(d.shrinkage_value);
                $('#notes').val(d.notes);
                $('#formTitle').html('<i class="fa-solid fa-pen me-1"></i> Edit Product');
                $('#btnSave').text('Update');
                $('html, body').animate({ scrollTop: 0 }, 300);
            }
        }).always(hideLoading);
    });

    $(document).on('click', '.btn-delete-product', function () {
        const id = $(this).data('id');
        confirmAction({
            title: 'Hapus Product',
            text: 'Data yang dihapus tidak dapat dikembalikan.',
            onConfirm: () => ajaxPost(ENDPOINT.del(id), {}, () => loadTable())
        });
    });

    // ------- PACKING RECIPE -------
    function buildRecipeRow(selectedId = '', qty = 1) {
        const options = packingMaterialOptions.map(p =>
            `<option value="${p.id}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${p.code} - ${p.name}</option>`
        ).join('');
        return `
            <tr>
                <td><select class="form-select form-select-sm recipe-material">${options}</select></td>
                <td><input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm recipe-qty" value="${qty}"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-recipe-row"><i class="fa-solid fa-trash"></i></button></td>
            </tr>
        `;
    }

    $(document).on('click', '.btn-recipe-product', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#recipe_product_id').val(id);
        $('#recipeProductName').text(name);

        showLoading();
        $.get(ENDPOINT.recipe(id), function (res) {
            if (res.success) {
                packingMaterialOptions = res.packingMaterials;
                $('#recipeTableBody').empty();
                window._recipeInitialCount = res.recipe.length;

                if (res.recipe.length === 0) {
                    $('#recipeTableBody').append(buildRecipeRow());
                } else {
                    res.recipe.forEach(r => {
                        $('#recipeTableBody').append(buildRecipeRow(r.packing_material_id, r.quantity_usage));
                    });
                }

                new bootstrap.Modal('#modalRecipe', { backdrop: 'static', keyboard: false }).show();
            }
        }).always(hideLoading);
    });

    $('#btnAddRecipeRow').on('click', function () {
        $('#recipeTableBody').append(buildRecipeRow());
    });

    $(document).on('click', '.btn-remove-recipe-row', function () {
        $(this).closest('tr').remove();
    });

    $('#btnSaveRecipe').on('click', function () {
        const productId = $('#recipe_product_id').val();
        const items = [];

        $('#recipeTableBody tr').each(function () {
            const materialId = $(this).find('.recipe-material').val();
            const qty = $(this).find('.recipe-qty').val();
            if (materialId && qty) {
                items.push({ packing_material_id: materialId, quantity_usage: qty });
            }
        });

        if (items.length === 0) {
            if (! window._recipeInitialCount) {
                showToast('Isi minimal 1 packing material atau tutup modal.', 'warning');
                return;
            }
            confirmAction({
                title: 'Hapus Recipe',
                text: 'Hapus seluruh packing recipe produk ini? Packing tidak akan otomatis terpakai lagi.',
                onConfirm: () => ajaxPost(ENDPOINT.saveRecipe(productId), { items: JSON.stringify([]) }, () => {
                    bootstrap.Modal.getInstance(document.getElementById('modalRecipe')).hide();
                }),
            });
            return;
        }

        ajaxPost(ENDPOINT.saveRecipe(productId), { items: JSON.stringify(items) }, () => {
            bootstrap.Modal.getInstance(document.getElementById('modalRecipe')).hide();
        });
    });
});
</script>
<?= $this->endSection() ?>

