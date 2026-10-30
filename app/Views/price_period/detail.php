<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php $badgeMap = ['DRAFT' => 'bg-secondary', 'PUBLISHED' => 'bg-success', 'CLOSED' => 'bg-dark']; ?>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <span class="badge bg-light text-dark border mb-2"><?= esc($period['period_code']) ?></span>
                <span class="badge <?= $badgeMap[$period['status']] ?> mb-2"><?= esc($period['status']) ?></span>
                <h4 class="mb-1"><?= esc($period['name']) ?></h4>
                <p class="text-muted mb-0">
                    <i class="fa-solid fa-calendar-days me-1"></i>
                    <?= tgl_id($period['start_date']) ?> &mdash; <?= tgl_id($period['end_date']) ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if (! $editable): ?>
                    <span class="badge bg-warning text-dark align-self-center">
                        <i class="fa-solid fa-lock me-1"></i> Read-only
                    </span>
                <?php endif ?>
                <?php if ($editable): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnDeletePeriod">
                        <i class="fa-solid fa-trash me-1"></i> Delete
                    </button>
                <?php endif ?>
                <?php if ($period['status'] === 'DRAFT'): ?>
                    <button type="button" class="btn btn-sm btn-success" id="btnPublish">
                        <i class="fa-solid fa-check me-1"></i> Publish
                    </button>
                <?php elseif ($period['status'] === 'PUBLISHED'): ?>
                    <button type="button" class="btn btn-sm btn-dark" id="btnClose">
                        <i class="fa-solid fa-lock me-1"></i> Close
                    </button>
                <?php endif ?>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnDuplicate">
                    <i class="fa-solid fa-clone me-1"></i> Duplicate as Revision
                </button>
            </div>
        </div>

        <?php if ($editable): ?>
        <hr>
        <form id="headerForm" class="row g-3">
            <div class="col-md-5">
                <label class="form-label small text-muted">Nama</label>
                <input type="text" name="name" id="name" class="form-control form-control-sm" value="<?= esc($period['name']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="<?= $period['start_date'] ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="<?= $period['end_date'] ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Notes</label>
                <input type="text" name="notes" id="notes" class="form-control form-control-sm" value="<?= esc($period['notes'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Labour Budget (Rp)</label>
                <input type="number" min="0" step="any" name="labour_budget" id="labour_budget" class="form-control form-control-sm" value="<?= esc($period['labour_budget'] ?? 0) ?>">
                <div class="form-text">Anggaran kuli per periode, dibagi per order ke item yg dichecklist.</div>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary w-100">Save</button>
            </div>
        </form>
        <?php endif ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <ul class="nav nav-tabs" id="priceTabs">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabProduct">Product Price</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPacking">Packing Price</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTransport">Transport Price</button></li>
            <!-- TAB BARU: LABOUR RATE -->
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLabour">Labour Rate</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabOpex">OPEX</button></li>
        </ul>

        <div class="tab-content pt-4">
            <!-- PRODUCT PRICE -->
            <div class="tab-pane fade show active" id="tabProduct">
                <div class="table-responsive">
                    <table class="table table-sm" id="productPriceTable">
                        <thead>
                            <tr>
                                <th width="30%">Product</th>
                                <th width="18%">Purchase Price</th>
                                <th width="15%">Shrinkage % (override)</th>
                                <th width="15%">Shrinkage Cost (preview)</th>
                                <th width="17%">Notes</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody id="productPriceBody"></tbody>
                    </table>
                </div>
                <?php if ($editable): ?>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddProductRow">
                        <i class="fa-solid fa-plus me-1"></i> Tambah Baris
                    </button>
                    <button type="button" class="btn btn-sm btn-primary px-4" id="btnSaveProductPrices">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Product Price
                    </button>
                </div>
                <?php endif ?>
            </div>

            <!-- PACKING PRICE -->
            <div class="tab-pane fade" id="tabPacking">
                <div class="table-responsive">
                    <table class="table table-sm" id="packingPriceTable">
                        <thead>
                            <tr>
                                <th width="22%">Packing Material</th>
                                <th width="13%">Price</th>
                                <th width="13%">Capacity (Kg)</th>
                                <th width="10%">Override?</th>
                                <th width="15%">Cost / Kg</th>
                                <th width="20%">Notes</th>
                                <th width="7%"></th>
                            </tr>
                        </thead>
                        <tbody id="packingPriceBody"></tbody>
                    </table>
                </div>
                <?php if ($editable): ?>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddPackingRow">
                        <i class="fa-solid fa-plus me-1"></i> Tambah Baris
                    </button>
                    <button type="button" class="btn btn-sm btn-primary px-4" id="btnSavePackingPrices">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Packing Price
                    </button>
                </div>
                <?php endif ?>
            </div>

            <!-- TRANSPORT PRICE -->
            <div class="tab-pane fade" id="tabTransport">
                <div class="table-responsive">
                    <table class="table table-sm" id="transportPriceTable">
                        <thead>
                            <tr>
                                <th width="25%">Route</th>
                                <th width="18%">Total Transport Cost</th>
                                <th width="15%">Volume (Kg)</th>
                                <th width="13%">Basis</th>
                                <th width="14%">Cost / Kg</th>
                                <th width="10%">Notes</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody id="transportPriceBody"></tbody>
                    </table>
                </div>
                <?php if ($editable): ?>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddTransportRow">
                        <i class="fa-solid fa-plus me-1"></i> Tambah Baris
                    </button>
                    <button type="button" class="btn btn-sm btn-primary px-4" id="btnSaveTransportPrices">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Transport Price
                    </button>
                </div>
                <?php endif ?>
            </div>

            <!-- TAB PANE BARU: LABOUR RATE -->
            <div class="tab-pane fade" id="tabLabour">
            <form id="formLabourRate">
                <div class="row g-3" id="labourRateContainer">
                    <!-- JavaScript akan merender input kuli di sini -->
                </div>

                <?php if ($editable): ?>
                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="btn btn-sm btn-primary px-4" id="btnSaveLabourRates">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Tarif Labour
                    </button>
                </div>
                <?php endif ?>
            </form>
        </div>

            <!-- TAB PANE BARU: OPEX -->
            <div class="tab-pane fade" id="tabOpex">
                <p class="text-muted small">
                    OPEX (rent, utility, biaya tak terduga, gaji karyawan tetap, dll) di luar Labour &amp; Transport Cost.
                    <strong>HO</strong> berlaku untuk semua order, <strong>Sukabumi</strong> berlaku kecuali item ditandai "Direct dari Petani",
                    dan area lain (mis. Jakarta) berlaku otomatis saat order dikirim ke destination yang ditautkan.
                </p>
                <div id="opexAreasContainer"></div>
                <?php if ($editable): ?>
                <div class="d-flex justify-content-end mt-3">
                    <button type="button" class="btn btn-sm btn-primary px-4" id="btnSaveOpex">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan OPEX
                    </button>
                </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Duplicate -->
<div class="modal fade" id="modalDuplicate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Duplicate as New Revision</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Seluruh Product/Packing/Transport Price pada period ini akan disalin ke Price Period baru (status Draft). Period ini tidak akan berubah.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Start Date Baru</label>
                        <input type="date" id="dup_start_date" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Date Baru</label>
                        <input type="date" id="dup_end_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnConfirmDuplicate">Buat Revision</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const PERIOD_ID = <?= $period['id'] ?>;
const EDITABLE  = <?= $editable ? 'true' : 'false' ?>;

const ENDPOINT = {
    updateHeader:      `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/update-header`,
    publish:           `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/publish`,
    close:             `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/close`,
    del:               `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/delete`,
    duplicate:         `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/duplicate`,
    productPrices:     `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/product-prices`,
    saveProductPrices: `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/product-prices/save`,
    packingPrices:     `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/packing-prices`,
    savePackingPrices: `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/packing-prices/save`,
    transportPrices:     `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/transport-prices`,
    saveTransportPrices: `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/transport-prices/save`,
    labourRates:     `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/labour-rates`,
    saveLabourRates:    `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/labour-rates/save`,
    opex:      `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/opex`,
    saveOpex:  `<?= base_url('admin/price-period') ?>/${PERIOD_ID}/opex/save`,
};

let productOptions = [];
let packingOptions = [];
let routeOptions = [];
let opexCategoryOptions = [];

function disabledAttr() { return EDITABLE ? '' : 'disabled'; }

// =========================================================
// PRODUCT PRICE TAB
// =========================================================
function buildProductRow(row = {}) {
    const options = productOptions.map(p =>
        `<option value="${p.id}" data-shrinkage-type="${p.shrinkage_type}" data-shrinkage-value="${p.shrinkage_value}" ${String(p.id) === String(row.product_id) ? 'selected' : ''}>${p.product_code} - ${p.product_name}</option>`
    ).join('');

    return `
        <tr>
            <td>
                <select class="form-select form-select-sm pp-product" ${disabledAttr()}>${options}</select>
            </td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm pp-price" value="${row.purchase_price ?? ''}" ${disabledAttr()}></td>
            <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm pp-shrinkage" placeholder="default master" value="${row.shrinkage_percent ?? ''}" ${disabledAttr()}></td>
            <td><span class="pp-shrinkage-preview text-muted">-</span></td>
            <td><input type="text" class="form-control form-control-sm pp-notes" value="${row.notes ?? ''}" ${disabledAttr()}></td>
            <td class="text-center">${EDITABLE ? '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="fa-solid fa-trash"></i></button>' : ''}</td>
        </tr>
    `;
}

function recalcProductPreview($row) {
    const $select = $row.find('.pp-product');
    const price = parseFloat($row.find('.pp-price').val()) || 0;
    const overrideShrinkage = $row.find('.pp-shrinkage').val();
    const masterType = $select.find(':selected').data('shrinkage-type');
    const masterValue = parseFloat($select.find(':selected').data('shrinkage-value')) || 0;

    let cost = 0;
    if (overrideShrinkage !== '') {
        cost = price * (parseFloat(overrideShrinkage) / 100);
    } else if (masterType === 'PERCENTAGE') {
        cost = price * (masterValue / 100);
    } else {
        cost = masterValue;
    }

    $row.find('.pp-shrinkage-preview').text(formatRupiah(cost.toFixed(2)));
}

function loadProductPrices() {
    $.get(ENDPOINT.productPrices, function (res) {
        productOptions = res.products;
        $('#productPriceBody').empty();

        if (res.lines.length === 0) {
            $('#productPriceBody').append(buildProductRow());
        } else {
            res.lines.forEach(line => $('#productPriceBody').append(buildProductRow(line)));
        }

        $('#productPriceBody tr').each(function () { recalcProductPreview($(this)); });
    });
}

$(document).on('change input', '.pp-product, .pp-price, .pp-shrinkage', function () {
    recalcProductPreview($(this).closest('tr'));
});

$('#btnAddProductRow').on('click', () => $('#productPriceBody').append(buildProductRow()));

$(document).on('click', '#productPriceTable .btn-remove-row', function () {
    if ($('#productPriceBody tr').length === 1) { showToast('Minimal harus ada 1 baris.', 'warning'); return; }
    $(this).closest('tr').remove();
});

$('#btnSaveProductPrices').on('click', function () {
    const items = [];
    $('#productPriceBody tr').each(function () {
        const productId = $(this).find('.pp-product').val();
        const price = $(this).find('.pp-price').val();
        if (productId && price) {
            items.push({
                product_id: productId,
                purchase_price: price,
                shrinkage_percent: $(this).find('.pp-shrinkage').val(),
                notes: $(this).find('.pp-notes').val(),
            });
        }
    });
    if (items.length === 0) { showToast('Minimal harus ada 1 Product Price.', 'error'); return; }
    ajaxPost(ENDPOINT.saveProductPrices, { items: JSON.stringify(items) }, () => loadProductPrices());
});

// =========================================================
// LABOUR RATE TAB (ADDED)
// =========================================================
// 2. Buat fungsi untuk load data dari server ke Input HTML
function loadLabourRates() {
    $.get(ENDPOINT.labourRates, function (res) {
        if (res.success && res.rates && res.rates.length > 0) {
            let html = '';

            res.rates.forEach(function (item) {
                html += `
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tarif ${item.name} (Rp / Orang)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   class="form-control" 
                                   name="rates[${item.id}]" 
                                   value="${parseFloat(item.rate_per_person || 0)}">
                        </div>
                        <small class="text-muted">Tarif harian per orang untuk periode ini.</small>
                    </div>
                `;
            });

            $('#labourRateContainer').html(html);
        } else {
            $('#labourRateContainer').html(`
                <div class="col-12">
                    <p class="text-muted">Master data kuli / labour rate belum tersedia.</p>
                </div>
            `);
        }
    });
}
$('#btnSaveLabourRates').on('click', function () {
    const formData = $('#formLabourRate').serialize();

    ajaxPost(ENDPOINT.saveLabourRates, formData, function (res) {
        if (res.success) {
            showToast(res.message, 'success');
            loadLabourRates(); // Reload data jika diperlukan
        } else {
            showToast(res.message || 'Gagal menyimpan data.', 'error');
        }
    });
});

// =========================================================
// PACKING PRICE TAB
// =========================================================
function buildPackingRow(row = {}) {
    const options = packingOptions.map(p =>
        `<option value="${p.id}" data-capacity="${p.capacity_kg ?? ''}" ${String(p.id) === String(row.packing_material_id) ? 'selected' : ''}>${p.code} - ${p.name}</option>`
    ).join('');
    const isOverride = row.is_manual_override == 1;

    return `
        <tr>
            <td><select class="form-select form-select-sm pk-material" ${disabledAttr()}>${options}</select></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm pk-price" value="${row.price ?? ''}" ${disabledAttr()}></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm pk-capacity" value="${row.capacity_kg ?? ''}" ${disabledAttr()}></td>
            <td class="text-center"><input type="checkbox" class="form-check-input pk-override" ${isOverride ? 'checked' : ''} ${disabledAttr()}></td>
            <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm pk-cost" value="${row.cost_per_kg ?? ''}" ${isOverride ? '' : 'readonly'} ${disabledAttr()}></td>
            <td><input type="text" class="form-control form-control-sm pk-notes" value="${row.notes ?? ''}" ${disabledAttr()}></td>
            <td class="text-center">${EDITABLE ? '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="fa-solid fa-trash"></i></button>' : ''}</td>
        </tr>
    `;
}

function recalcPackingCost($row) {
    if ($row.find('.pk-override').is(':checked')) {
        $row.find('.pk-cost').prop('readonly', false);
        return;
    }
    $row.find('.pk-cost').prop('readonly', true);
    const price = parseFloat($row.find('.pk-price').val()) || 0;
    const capacity = parseFloat($row.find('.pk-capacity').val()) || 0;
    const cost = capacity > 0 ? (price / capacity) : price;
    $row.find('.pk-cost').val(cost.toFixed(4));
}

function loadPackingPrices() {
    $.get(ENDPOINT.packingPrices, function (res) {
        packingOptions = res.packingMaterials;
        $('#packingPriceBody').empty();

        if (res.lines.length === 0) {
            $('#packingPriceBody').append(buildPackingRow());
        } else {
            res.lines.forEach(line => $('#packingPriceBody').append(buildPackingRow(line)));
        }

        $('#packingPriceBody tr').each(function () { recalcPackingCost($(this)); });
    });
}

$(document).on('change', '.pk-material', function () {
    const $row = $(this).closest('tr');
    const capacity = $(this).find(':selected').data('capacity');
    if (capacity !== undefined && capacity !== '') {
        $row.find('.pk-capacity').val(capacity);
    }
    recalcPackingCost($row);
});
$(document).on('input change', '.pk-price, .pk-capacity, .pk-override', function () {
    recalcPackingCost($(this).closest('tr'));
});

$('#btnAddPackingRow').on('click', () => $('#packingPriceBody').append(buildPackingRow()));

$(document).on('click', '#packingPriceTable .btn-remove-row', function () {
    if ($('#packingPriceBody tr').length === 1) { showToast('Minimal harus ada 1 baris.', 'warning'); return; }
    $(this).closest('tr').remove();
});

$('#btnSavePackingPrices').on('click', function () {
    const items = [];
    $('#packingPriceBody tr').each(function () {
        const materialId = $(this).find('.pk-material').val();
        const price = $(this).find('.pk-price').val();
        if (materialId && price) {
            items.push({
                packing_material_id: materialId,
                price: price,
                capacity_kg: $(this).find('.pk-capacity').val(),
                is_manual_override: $(this).find('.pk-override').is(':checked'),
                cost_per_kg: $(this).find('.pk-cost').val(),
                notes: $(this).find('.pk-notes').val(),
            });
        }
    });
    if (items.length === 0) { showToast('Minimal harus ada 1 Packing Price.', 'error'); return; }
    ajaxPost(ENDPOINT.savePackingPrices, { items: JSON.stringify(items) }, () => loadPackingPrices());
});

// =========================================================
// TRANSPORT PRICE TAB
// =========================================================
function buildTransportRow(row = {}) {
    const options = routeOptions.map(r =>
        `<option value="${r.id}" data-basis="${r.default_basis}" ${String(r.id) === String(row.transport_route_id) ? 'selected' : ''}>${r.origin} &rarr; ${r.destination_name}</option>`
    ).join('');

    return `
        <tr>
            <td><select class="form-select form-select-sm tr-route" ${disabledAttr()}>${options}</select></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm tr-cost" value="${row.total_transport_cost ?? ''}" ${disabledAttr()}></td>
            <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm tr-volume" value="${row.volume_kg ?? ''}" ${disabledAttr()}></td>
            <td>
                <select class="form-select form-select-sm tr-basis" ${disabledAttr()}>
                    <option value="CAPACITY" ${row.basis === 'CAPACITY' || !row.basis ? 'selected' : ''}>Capacity</option>
                    <option value="ACTUAL" ${row.basis === 'ACTUAL' ? 'selected' : ''}>Actual</option>
                </select>
            </td>
            <td><span class="tr-cost-preview fw-semibold">-</span></td>
            <td><input type="text" class="form-control form-control-sm tr-notes" value="${row.notes ?? ''}" ${disabledAttr()}></td>
            <td class="text-center">${EDITABLE ? '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="fa-solid fa-trash"></i></button>' : ''}</td>
        </tr>
    `;
}

function recalcTransportPreview($row) {
    const cost = parseFloat($row.find('.tr-cost').val()) || 0;
    const volume = parseFloat($row.find('.tr-volume').val()) || 0;
    const perKg = volume > 0 ? (cost / volume) : 0;
    $row.find('.tr-cost-preview').text(perKg > 0 ? formatRupiah(perKg.toFixed(4)) + '/Kg' : '-');
}

function loadTransportPrices() {
    $.get(ENDPOINT.transportPrices, function (res) {
        routeOptions = res.routes;
        $('#transportPriceBody').empty();

        if (res.lines.length === 0) {
            $('#transportPriceBody').append(buildTransportRow());
        } else {
            res.lines.forEach(line => $('#transportPriceBody').append(buildTransportRow(line)));
        }

        $('#transportPriceBody tr').each(function () { recalcTransportPreview($(this)); });
    });
}

$(document).on('change', '.tr-route', function () {
    const $row = $(this).closest('tr');
    const basis = $(this).find(':selected').data('basis');
    if (basis) $row.find('.tr-basis').val(basis);
});
$(document).on('input', '.tr-cost, .tr-volume', function () {
    recalcTransportPreview($(this).closest('tr'));
});

$('#btnAddTransportRow').on('click', () => $('#transportPriceBody').append(buildTransportRow()));

$(document).on('click', '#transportPriceTable .btn-remove-row', function () {
    if ($('#transportPriceBody tr').length === 1) { showToast('Minimal harus ada 1 baris.', 'warning'); return; }
    $(this).closest('tr').remove();
});

$('#btnSaveTransportPrices').on('click', function () {
    const items = [];
    $('#transportPriceBody tr').each(function () {
        const routeId = $(this).find('.tr-route').val();
        const cost = $(this).find('.tr-cost').val();
        const volume = $(this).find('.tr-volume').val();
        if (routeId && cost && volume) {
            items.push({
                transport_route_id: routeId,
                total_transport_cost: cost,
                volume_kg: volume,
                basis: $(this).find('.tr-basis').val(),
                notes: $(this).find('.tr-notes').val(),
            });
        }
    });
    if (items.length === 0) { showToast('Minimal harus ada 1 Transport Price.', 'error'); return; }
    ajaxPost(ENDPOINT.saveTransportPrices, { items: JSON.stringify(items) }, () => loadTransportPrices());
});

// =========================================================
// OPEX TAB
// =========================================================
function buildOpexItemRow(item = {}) {
    const options = opexCategoryOptions.map(c =>
        `<option value="${c.id}" ${String(c.id) === String(item.opex_category_id) ? 'selected' : ''}>${c.name}</option>`
    ).join('');

    return `
        <tr>
            <td><select class="form-select form-select-sm opex-item-category" ${disabledAttr()}>${options}</select></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm opex-item-amount" value="${item.amount ?? ''}" ${disabledAttr()}></td>
            <td><input type="text" class="form-control form-control-sm opex-item-notes" value="${item.notes ?? ''}" ${disabledAttr()}></td>
            <td class="text-center">${EDITABLE ? '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-opex-item"><i class="fa-solid fa-trash"></i></button>' : ''}</td>
        </tr>
    `;
}

function buildOpexAreaCard(area) {
    const itemsHtml = (area.items && area.items.length > 0)
        ? area.items.map(item => buildOpexItemRow(item)).join('')
        : buildOpexItemRow();

    return `
        <div class="card mb-3 opex-area-card" data-opex-area-id="${area.id}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>${area.name} <span class="badge bg-light text-dark border">${area.code}</span></strong>
                <div class="text-end small">
                    <div>Total: <strong class="opex-area-total">Rp 0</strong></div>
                    <div class="text-muted">Rp/Kg: <span class="opex-area-cost-per-kg">Rp 0</span></div>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3 col-md-4">
                    <label class="form-label small text-muted">Estimasi Volume Periode Ini (Kg)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm opex-area-volume" value="${area.allocation_volume_kg ?? ''}" ${disabledAttr()}>
                </div>
                <table class="table table-sm opex-items-table">
                    <thead>
                        <tr>
                            <th width="35%">Kategori Biaya</th>
                            <th width="25%">Amount (Rp)</th>
                            <th width="30%">Notes</th>
                            <th width="10%"></th>
                        </tr>
                    </thead>
                    <tbody class="opex-items-body">${itemsHtml}</tbody>
                </table>
                ${EDITABLE ? `<button type="button" class="btn btn-sm btn-outline-primary btn-add-opex-item"><i class="fa-solid fa-plus me-1"></i> Tambah Kategori</button>` : ''}
            </div>
        </div>
    `;
}

function recalcOpexArea($card) {
    let total = 0;
    $card.find('.opex-item-amount').each(function () {
        total += parseFloat($(this).val()) || 0;
    });
    const volume = parseFloat($card.find('.opex-area-volume').val()) || 0;
    const perKg = volume > 0 ? (total / volume) : 0;

    $card.find('.opex-area-total').text(formatRupiah(total.toFixed(2)));
    $card.find('.opex-area-cost-per-kg').text(formatRupiah(perKg.toFixed(4)));
}

function loadOpex() {
    $.get(ENDPOINT.opex, function (res) {
        opexCategoryOptions = res.categories;

        const savedByAreaId = {};
        (res.areas || []).forEach(a => { savedByAreaId[a.opex_area_id] = a; });

        $('#opexAreasContainer').empty();

        if (! res.opexAreas || res.opexAreas.length === 0) {
            $('#opexAreasContainer').html('<p class="text-muted">Belum ada OPEX Area. Silakan tambahkan di menu Master Data &raquo; OPEX Area.</p>');
            return;
        }

        res.opexAreas.forEach(area => {
            const saved = savedByAreaId[area.id] || { id: area.id, code: area.code, name: area.name, items: [] };
            saved.id = area.id;
            saved.code = area.code;
            saved.name = area.name;
            $('#opexAreasContainer').append(buildOpexAreaCard(saved));
        });

        $('.opex-area-card').each(function () { recalcOpexArea($(this)); });
    });
}

$(document).on('input', '.opex-item-amount, .opex-area-volume', function () {
    recalcOpexArea($(this).closest('.opex-area-card'));
});

$(document).on('click', '.btn-add-opex-item', function () {
    $(this).closest('.card-body').find('.opex-items-body').append(buildOpexItemRow());
});

$(document).on('click', '.btn-remove-opex-item', function () {
    const $card = $(this).closest('.opex-area-card');
    if ($card.find('.opex-items-body tr').length === 1) { showToast('Minimal harus ada 1 baris kategori.', 'warning'); return; }
    $(this).closest('tr').remove();
    recalcOpexArea($card);
});

$('#btnSaveOpex').on('click', function () {
    const areas = [];
    $('.opex-area-card').each(function () {
        const $card = $(this);
        const items = [];
        $card.find('.opex-items-body tr').each(function () {
            const categoryId = $(this).find('.opex-item-category').val();
            const amount = $(this).find('.opex-item-amount').val();
            if (categoryId && amount) {
                items.push({
                    opex_category_id: categoryId,
                    amount: amount,
                    notes: $(this).find('.opex-item-notes').val(),
                });
            }
        });
        const volume = $card.find('.opex-area-volume').val();
        if (volume && items.length > 0) {
            areas.push({
                opex_area_id: $card.data('opex-area-id'),
                allocation_volume_kg: volume,
                items: items,
            });
        }
    });
    ajaxPost(ENDPOINT.saveOpex, { areas: JSON.stringify(areas) }, () => loadOpex());
});

// =========================================================
// HEADER & LIFECYCLE ACTIONS
// =========================================================
$('#headerForm').on('submit', function (e) {
    e.preventDefault();
    ajaxPost(ENDPOINT.updateHeader, $(this).serialize(), () => window.location.reload());
});

$('#btnPublish').on('click', function () {
    confirmAction({
        title: 'Publish Price Period',
        text: 'Setelah dipublish, Price Period ini siap dipakai untuk transaksi. Pastikan seluruh harga sudah benar.',
        onConfirm: () => ajaxPost(ENDPOINT.publish, {}, () => window.location.reload())
    });
});

$('#btnClose').on('click', function () {
    confirmAction({
        title: 'Close Price Period',
        text: 'Price Period yang di-close tidak dapat digunakan untuk transaksi baru.',
        onConfirm: () => ajaxPost(ENDPOINT.close, {}, () => window.location.reload())
    });
});

$('#btnDeletePeriod').on('click', function () {
    confirmAction({
        title: 'Hapus Price Period',
        text: 'Data yang dihapus tidak dapat dikembalikan.',
        onConfirm: () => ajaxPost(ENDPOINT.del, {}, () => window.location.href = "<?= base_url('admin/price-period') ?>")
    });
});

$('#btnDuplicate').on('click', function () {
    const start = new Date("<?= $period['end_date'] ?>");
    start.setDate(start.getDate() + 1);
    $('#dup_start_date').val(start.toISOString().slice(0, 10));
    const end = new Date(start);
    end.setDate(end.getDate() + 6);
    $('#dup_end_date').val(end.toISOString().slice(0, 10));
    new bootstrap.Modal('#modalDuplicate').show();
});

$('#btnConfirmDuplicate').on('click', function () {
    const startDate = $('#dup_start_date').val();
    const endDate = $('#dup_end_date').val();
    if (! startDate || ! endDate) { showToast('Tanggal wajib diisi.', 'error'); return; }

    showLoading();
    $.post(ENDPOINT.duplicate, { start_date: startDate, end_date: endDate })
        .done(function (res) {
            if (res.success) {
                showToast(res.message, 'success');
                setTimeout(() => window.location.href = "<?= base_url('admin/price-period') ?>/" + res.id, 600);
            } else {
                showToast(res.message || 'Gagal duplikasi.', 'error');
            }
        })
        .fail(() => showToast('Terjadi kesalahan pada server.', 'error'))
        .always(hideLoading);
});

// Initial load
loadProductPrices();
loadPackingPrices();
loadTransportPrices();
loadLabourRates();
loadOpex();
</script>
<?= $this->endSection() ?>
