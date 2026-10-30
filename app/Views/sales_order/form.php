<!-- KEBUNI-FIX-20261016 -->
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<form id="orderForm">
    <div class="card mb-4">
        <div class="section-header"><i class="fa-solid fa-file-circle-plus me-1"></i> Informasi Taking Order</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Order <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" id="order_date" class="form-control" value="<?= $order['order_date'] ?? $today ?>" required autofocus>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                    <?php
                    $accountNamePrefill = '';
                    if (isset($order['account_id'])) {
                        foreach ($accounts as $acc) {
                            if ((string) $acc['id'] === (string) $order['account_id']) { $accountNamePrefill = $acc['nama_account'] ?? ''; break; }
                        }
                    }
                    ?>
                    <input type="text" id="account_search" class="form-control" list="accountDatalist" placeholder="Ketik untuk cari customer..." autocomplete="off" value="<?= esc($accountNamePrefill) ?>">
                    <input type="hidden" name="account_id" id="account_id" value="<?= esc($order['account_id'] ?? '') ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Price Period (Published) <span class="text-danger">*</span></label>
                    <select name="price_period_id" id="price_period_id" class="form-select" required>
                        <option value="">-- Pilih Price Period --</option>
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= isset($order) && $order['price_period_id'] == $p['id'] ? 'selected' : '' ?>><?= esc($p['period_code']) ?> - <?= esc($p['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Term <span class="text-danger">*</span></label>
                    <select name="payment_term_id" id="payment_term_id" class="form-select" required>
                        <option value="">-- Pilih Payment Term --</option>
                        <?php foreach ($paymentTerms as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= isset($order) && $order['payment_term_id'] == $t['id'] ? 'selected' : '' ?>><?= esc($t['name']) ?> (<?= (int) $t['days'] ?> hari)</option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sales (Closing)</label>
                    <select name="sales_id" id="sales_id" class="form-select">
                        <option value="">-- Tanpa Sales --</option>
                        <?php foreach ($salesList as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= isset($order) && ($order['sales_id'] ?? null) == $u['id'] ? 'selected' : '' ?>><?= esc($u['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Commission (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="commission_percent" id="commission_percent" class="form-control" value="<?= esc($order['commission_percent'] ?? '0') ?>" placeholder="0">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="w-100">
                        <label class="form-label small text-muted d-block mb-1">Est. Fee</label>
                        <div class="fw-semibold" id="commissionPreview">Rp 0</div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row g-3" id="labourInputsContainer">
                        <div class="col-12 text-muted small py-2"><em>Pilih Price Period untuk memuat Labour Budget.</em></div>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2"><?= esc($order['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="section-header d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-boxes-stacked me-1"></i> Item Order / Produk</span>
            <button type="button" class="btn btn-sm btn-light" id="btnAddItem"><i class="fa-solid fa-plus me-1"></i> Tambah Produk</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0" id="itemsTable">
                    <thead>
                        <tr>
                            <th width="16%">Produk</th>
                            <th width="16%">Transport (bisa &gt;1)</th>
                            <th width="18%">Packing (bisa &gt;1)</th>
                            <th width="14%">Opex (pilih)</th>
                            <th width="10%">Qty (Kg)</th>
                            <th width="10%">Manual Price</th>
                            <th width="5%" class="text-center" title="Bebankan labour budget ke item ini">Kuli</th>
                            <th width="5%" class="text-center" title="Cost of Fund 1% dari total COGS">COF</th>
                            <th width="8%">Subtotal (Est.)</th>
                            <th width="3%"></th>
                        </tr>
                    </thead>
                    <tbody id="itemRows"></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="button" class="btn btn-outline-primary" id="btnCalculate" disabled title="Pilih Price Period dan Payment Term terlebih dahulu">
                <i class="fa-solid fa-calculator me-1"></i> Hitung Preview
            </button>
        </div>
    </div>

    <div id="resultSection" style="display:none;" class="mb-4">
        <div class="row g-3 mb-3" id="summaryCards"></div>
        <div class="card">
            <div class="section-header"><i class="fa-solid fa-table me-1"></i> Detail Perhitungan per Item</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Product</th><th>COGS SK</th><th>Transport</th><th>Packing</th><th>Opex</th><th>Labour</th>
                                <th>COGS Dest</th><th>Tier</th><th>Rekomendasi</th><th>Harga Jual</th><th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="resultTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="staleBanner" class="alert alert-warning d-none py-2 small mb-3" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        Ada perubahan setelah kalkulasi terakhir — angka preview mungkin basi.
        <button type="button" class="btn btn-sm btn-warning ms-2" id="btnRecalc">
            <i class="fa-solid fa-rotate me-1"></i>Hitung ulang preview
        </button>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('admin/sales-order') ?>" class="btn btn-light px-4">Batal</a>
        <button type="submit" class="btn btn-primary px-4" id="btnSaveDraft">
            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan sebagai Draft
        </button>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const productsData = <?= json_encode($products) ?>;
const accountsData = <?= json_encode($accounts) ?>;
const routesData    = <?= json_encode($routes) ?>;
const packingsData  = <?= json_encode($packings) ?>;
const existingItems = <?= json_encode($items ?? []) ?>;

const ENDPOINT_CALCULATE = "<?= base_url('admin/sales-order/calculate') ?>";
const ENDPOINT_SAVE = "<?= isset($order) ? base_url('admin/sales-order/' . $order['id'] . '/update') : base_url('admin/sales-order/store') ?>";

let rowCounter = 0;
let lastCalcResult = null;

let opexData = [];
let periodTransports = null; // null = belum difilter, pakai routesData
let periodPackings = null;   // null = belum difilter, pakai packingsData       // OPEX area options utk Price Period aktif [{opex_area_id, area_code, area_name, opex_cost_per_kg}]
let labourBudget = 0;   // Labour budget Price Period aktif

function fetchPeriodComponents(periodId) {
    const $c = $('#labourInputsContainer');
    $c.empty();
    opexData = [];
    labourBudget = 0;
    periodTransports = null;
    periodPackings = null;
    if (! periodId) {
        $c.html('<div class="col-12 text-muted small py-2"><em>Pilih Price Period untuk memuat Labour Budget & Opex.</em></div>');
        refreshOpexPickers();
        return;
    }
    $c.html('<div class="col-12 text-muted small py-2"><i class="fa-solid fa-spinner fa-spin me-1"></i> Memuat komponen periode...</div>');

    $.get("<?= base_url('admin/sales-order/components-by-period') ?>", { price_period_id: periodId }, function (res) {
        $c.empty();
        if (res && res.success) {
            opexData = res.opex || [];
            labourBudget = parseFloat(res.labour_budget || 0);
            periodTransports = res.transports || [];
            periodPackings = res.packings || [];
            refreshTransportPacking();
            $c.html(`
                <div class="col-md-6">
                    <label class="form-label small text-muted">Labour Budget Periode</label>
                    <div class="fw-semibold">${fmtRp(labourBudget, 2)}</div>
                    <div class="form-text">Dibagi otomatis ke item yg dichecklist Kuli, proporsional qty.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">Opex tersedia</label>
                    <div class="fw-semibold">${opexData.length} area</div>
                    <div class="form-text">Pilih manual per item di kolom Opex.</div>
                </div>
            `);
        } else {
            $c.html('<div class="col-12 text-danger small py-2">Gagal memuat komponen periode.</div>');
        }
        refreshOpexPickers();
        // Perbarui label chip opex yg sudah terpilih (untuk mode edit)
        $('.opex-chip').each(function () {
            const id = String($(this).data('id'));
            const o = opexData.find(x => String(x.opex_area_id) === id);
            if (o) {
                $(this).contents().first().replaceWith(`${o.area_code} - ${o.area_name} `);
            }
        });
        markStale();
    }).fail(function () {
        $c.html('<div class="col-12 text-danger small py-2">Gagal memuat komponen periode.</div>');
        refreshOpexPickers();
    });
}


/** Rebuild checkbox transport & packing per baris sesuai harga di Price Period aktif. Centangan yg ada dipertahankan. */
function refreshTransportPacking() {
    const tData = periodTransports || [];
    const pData = periodPackings || [];
    const useTransportFilter = periodTransports !== null;
    const usePackingFilter = periodPackings !== null;

    $('.item-row').each(function () {
        const $row = $(this);

        // Transport
        const $tWrap = $row.find('.transport-checks');
        if ($tWrap.length) {
            const checked = $tWrap.find('.transport-check:checked').map(function () { return String($(this).val()); }).get();
            const list = useTransportFilter ? tData : (typeof routesData !== 'undefined' ? routesData : []);
            const rowId = $row.attr('id') || ('row_' + Math.random().toString(36).slice(2));
            $tWrap.html(list.map(r => {
                const c = checked.includes(String(r.id)) ? 'checked' : '';
                return `<div class="form-check form-check-inline me-2 mb-1">` +
                    `<input class="form-check-input transport-check" type="checkbox" value="${r.id}" ${c} id="tr_${rowId}_${r.id}">` +
                    `<label class="form-check-label small" for="tr_${rowId}_${r.id}">${r.origin} &rarr; ${r.destination_name}</label></div>`;
            }).join('') || '<span class="text-muted small">Tidak ada transport berharga di periode ini.</span>');
        }

        // Packing
        const $pCell = $row.find('.packing-inline').closest('td');
        if ($pCell.length && typeof PackingChecklist !== 'undefined') {
            const checked = $row.find('.packing-check:checked').map(function () { return String($(this).val()); }).get();
            const list = usePackingFilter ? pData : (typeof packingsData !== 'undefined' ? packingsData : []);
            // Jika filter aktif, buang centangan yg tidak ada di periode
            const validChecked = usePackingFilter ? checked.filter(id => list.some(p => String(p.id) === String(id))) : checked;
            $pCell.html(PackingChecklist.innerHtmlInline(list, validChecked));
        }
    });
    if (typeof PackingChecklist !== 'undefined' && PackingChecklist.refreshAll) PackingChecklist.refreshAll();
}

/** Update ulang dropdown Opex di semua baris (dipanggil setelah ganti periode). */
function refreshOpexPickers() {
    $('#itemRows .item-row').each(function () {
        const $sel = $(this).find('.opex-select');
        const picked = $(this).find('.opex-picked').data('ids') || [];
        buildOpexOptions($sel, picked);
    });
}

/** Isi <select> Opex dengan opsi periode aktif. */
function buildOpexOptions($sel, selectedIds) {
    const sel = (selectedIds || []).map(String);
    let html = '<option value="">-- Pilih Opex --</option>';
    opexData.forEach(o => {
        const id = String(o.opex_area_id);
        if (sel.includes(id)) return; // yg sudah dipilih tidak ditampilkan lagi
        html += `<option value="${id}">${o.area_code} - ${o.area_name} (${fmtRp(parseFloat(o.opex_cost_per_kg || 0), 2)}/kg)</option>`;
    });
    $sel.html(html);
}

function buildItemRow(existing = {}) {
    rowCounter++;

    // Produk: ketik-untuk-cari (datalist di atas productsData, tanpa endpoint baru).
    const prodLabel = existing.product_id ? productLabelById(existing.product_id) : '';

    // Transport: inline checkbox multi (0..N) — pola sama seperti packing
    const selectedTransportIds = (existing.transport_breakdown_json ? JSON.parse(existing.transport_breakdown_json).map(t => String(t.transport_route_id)) : (existing.transport_route_id ? [String(existing.transport_route_id)] : []));
    const tList = (periodTransports !== null) ? periodTransports : routesData;
    const transportChecks = tList.map(r => {
        const checked = selectedTransportIds.includes(String(r.id)) ? 'checked' : '';
        return `<div class="form-check form-check-inline me-2 mb-1">
            <input class="form-check-input transport-check" type="checkbox" value="${r.id}" ${checked} id="tr_${rowCounter}_${r.id}">
            <label class="form-check-label small" for="tr_${rowCounter}_${r.id}">${r.origin} &rarr; ${r.destination_name}</label>
        </div>`;
    }).join('');

    const selectedPackingIds = (existing.packing_breakdown_json ? JSON.parse(existing.packing_breakdown_json).map(p => String(p.packing_material_id)) : (existing.packing_ids || []).map(String));

    // Opex: picker manual (dropdown + tambah, chip yg bisa dihapus)
    const selectedOpexIds = (existing.opex_breakdown_json ? JSON.parse(existing.opex_breakdown_json).map(o => String(o.opex_area_id)) : (existing.opex_ids || []).map(String));
    const opexPickedHtml = selectedOpexIds.map(id => {
        const o = (opexData.find(x => String(x.opex_area_id) === String(id)) || {});
        const label = o.area_name ? `${o.area_code} - ${o.area_name}` : ('Opex #' + id);
        return `<span class="badge bg-info text-dark me-1 mb-1 opex-chip" data-id="${id}">${label} <a href="#" class="text-dark text-decoration-none opex-remove" data-id="${id}">&times;</a></span>`;
    }).join('');

    const labourChecked = existing.labour_checked || (existing.labour_cost > 0) ? 'checked' : '';
    const cofChecked = existing.id ? ((existing.cost_of_fund_checked || (existing.cost_of_fund > 0)) ? 'checked' : '') : 'checked';

    return `
        <tr id="row_${rowCounter}" class="item-row">
            <td>
                <input type="text" class="form-control form-control-sm item-product-search" list="productDatalist" placeholder="Ketik untuk cari produk..." value="${prodLabel.replace(/"/g, '&quot;')}" autocomplete="off">
                <input type="hidden" class="item-product" value="${existing.product_id ?? ''}">
            </td>
            <td><div class="transport-checks">${transportChecks || '<span class="text-muted small">-</span>'}</div></td>
            <td>${PackingChecklist.innerHtmlInline((periodPackings !== null) ? periodPackings : packingsData, selectedPackingIds)}</td>
            <td>
                <div class="opex-picked mb-1" data-ids='${JSON.stringify(selectedOpexIds)}'>${opexPickedHtml}</div>
                <div class="input-group input-group-sm">
                    <select class="form-select form-select-sm opex-select"><option value="">-- Pilih Opex --</option></select>
                    <button type="button" class="btn btn-outline-primary opex-add" title="Tambah Opex"><i class="fa-solid fa-plus"></i></button>
                </div>
            </td>
            <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm item-qty" value="${existing.qty ?? ''}" placeholder="500"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm item-manual-price" value="${existing.manual_price ?? ''}" placeholder="Auto"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input item-labour" ${labourChecked} title="Bebankan labour budget"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input item-cof" ${cofChecked} title="Cost of Fund 1%"></td>
            <td class="text-end fw-semibold row-subtotal">${existing.subtotal_final ? fmtRp(existing.subtotal_final, 2) : 'Rp 0'}</td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="fa-solid fa-trash"></i></button></td>
        </tr>
    `;
}

/** Label "KODE - Nama" untuk satu product id (dipakai prefill mode edit). */
function productLabelById(id) {
    const p = productsData.find(x => String(x.id) === String(id));
    return p ? `${p.product_code} - ${p.product_name}` : '';
}

/** Bangun <datalist> produk sekali untuk semua baris. */
function buildProductDatalist() {
    if ($('#productDatalist').length) return;
    const opts = productsData.map(p => {
        const label = `${p.product_code} - ${p.product_name}`.replace(/"/g, '&quot;');
        return `<option value="${label}">`;
    }).join('');
    $('body').append(`<datalist id="productDatalist">${opts}</datalist>`);
}

/** Bangun <datalist> customer + sinkron ke hidden account_id. */
function buildAccountDatalist() {
    if ($('#accountDatalist').length) return;
    const opts = accountsData.map(a => {
        const label = (a.nama_account || a.name || ('#' + a.id)).replace(/"/g, '&quot;');
        return `<option value="${label}" data-id="${a.id}">`;
    }).join('');
    $('body').append(`<datalist id="accountDatalist">${opts}</datalist>`);

    $(document).on('input', '#account_search', function () {
        const v = $(this).val().trim();
        const hit = accountsData.find(a => (a.nama_account || a.name) === v);
        $('#account_id').val(hit ? hit.id : '');
    });
    // Jika dikosongkan manual, reset hidden
    $(document).on('change', '#account_search', function () {
        if (! $(this).val().trim()) $('#account_id').val('');
    });
}

$(function () {
    buildProductDatalist();
    buildAccountDatalist();

    if (existingItems.length > 0) {
        // Warning kalau data breakdown tidak tersimpan (order lama / kolom belum ada pas save)
        const missingBreakdown = existingItems.some(it => !it.transport_breakdown_json || !it.opex_breakdown_json);
        if (missingBreakdown) {
            $('#itemRows').before('<div class="alert alert-warning mb-3"><i class="bi bi-exclamation-triangle"></i> <strong>Perhatian:</strong> Data pilihan Transport/Opex order ini tidak tersimpan lengkap (dibuat sebelum update). Silakan pilih ulang Transport & Opex per baris sebelum menyimpan.</div>');
        }
        existingItems.forEach(it => $('#itemRows').append(buildItemRow(it)));
    } else {
        $('#itemRows').append(buildItemRow());
    }
    PackingChecklist.refreshAll();
    refreshOpexPickers();
    updateCalculateBtn();

    // Tombol Hitung Preview aktif hanya bila syarat terpenuhi (c.2).
    function updateCalculateBtn() {
        const ok = $('#price_period_id').val() && $('#payment_term_id').val();
        $('#btnCalculate').prop('disabled', !ok);
    }
    $('#price_period_id, #payment_term_id').on('change', updateCalculateBtn);

    // Ketik-untuk-cari produk: samakan label datalist -> hidden .item-product.
    $(document).on('input', '.item-product-search', function () {
        const v = $(this).val().trim();
        const hit = productsData.find(p => `${p.product_code} - ${p.product_name}` === v);
        const $row = $(this).closest('tr');
        $row.find('.item-product').val(hit ? hit.id : '');
        markStale();
    });

    // Tandai data basi bila input berubah setelah kalkulasi (c.2).
    // Dibuat global (window) agar bisa dipanggil dari callback AJAX manapun.
    window.markStale = function () {
        if (!lastCalcResult) return;
        $('#itemRows .row-subtotal').html('<span class="text-muted">—</span>');
        $('#staleBanner').removeClass('d-none');
    };
    function markStale() { return window.markStale(); }
    $(document).on('input', '#itemRows .item-qty, #itemRows .item-manual-price', markStale);
    $(document).on('change', '#itemRows .transport-check, #itemRows .packing-check, #itemRows .item-labour, #itemRows .item-cof', markStale);
    // Auto hitung ulang saat COF di-check/uncheck dan preview sudah pernah dihitung
    $(document).on('change', '#itemRows .item-cof', function () {
        if (lastCalcResult && $('#price_period_id').val() && $('#payment_term_id').val()) {
            $('#btnCalculate').click();
        }
    });

    // Opex picker: tambah & hapus per baris
    $(document).on('change', '.opex-select', function () {
        $(this).closest('tr').find('.opex-add').click();
    });
    $(document).on('click', '.opex-add', function () {
        const $row = $(this).closest('tr');
        const $sel = $row.find('.opex-select');
        const id = $sel.val();
        if (! id) return;
        const o = opexData.find(x => String(x.opex_area_id) === String(id));
        const label = o ? `${o.area_code} - ${o.area_name}` : ('Opex #' + id);
        const $picked = $row.find('.opex-picked');
        const ids = $picked.data('ids') || [];
        ids.push(String(id));
        $picked.data('ids', ids);
        $picked.append(`<span class="badge bg-info text-dark me-1 mb-1 opex-chip" data-id="${id}">${label} <a href="#" class="text-dark text-decoration-none opex-remove" data-id="${id}">&times;</a></span>`);
        buildOpexOptions($sel, ids);
        markStale();
    });
    $(document).on('click', '.opex-remove', function (e) {
        e.preventDefault();
        const $row = $(this).closest('tr');
        const id = String($(this).data('id'));
        const $picked = $row.find('.opex-picked');
        const ids = ($picked.data('ids') || []).map(String).filter(x => x !== id);
        $picked.data('ids', ids);
        $(this).closest('.opex-chip').remove();
        buildOpexOptions($row.find('.opex-select'), ids);
        markStale();
    });

    if ($('#price_period_id').val()) {
        fetchPeriodComponents($('#price_period_id').val());
    }

    $('#price_period_id').on('change', function () { fetchPeriodComponents($(this).val()); });
    $('#btnAddItem').on('click', () => { $('#itemRows').append(buildItemRow()); PackingChecklist.refreshAll(); refreshOpexPickers(); });
    $('#commission_percent').on('input', function () {
        if (lastCalcResult) renderResult(lastCalcResult);
    });
    $(document).on('click', '.btn-remove-row', function () {
        if ($('#itemRows tr').length === 1) { showToast('Minimal harus ada 1 item.', 'warning'); return; }
        $(this).closest('tr').remove();
    });

    function collectPayload() {
        const items = [];
        let valid = true;
        $('.item-row').each(function () {
            const productId = $(this).find('.item-product').val();
            const qty = $(this).find('.item-qty').val();
            if (! productId || ! qty || qty <= 0) { valid = false; return; }
            items.push({
                product_id: productId,
                transport_ids: $(this).find('.transport-check:checked').map(function () { return $(this).val(); }).get(),
                packing_ids: $(this).find('.packing-check:checked').map(function () { return $(this).val(); }).get(),
                opex_ids: ($(this).find('.opex-picked').data('ids') || []).map(String),
                qty: parseFloat(qty),
                manual_price: $(this).find('.item-manual-price').val() || null,
                labour_checked: $(this).find('.item-labour').is(':checked'),
                cost_of_fund_checked: $(this).find('.item-cof').is(':checked'),
            });
        });

        return { valid, items };
    }

    $('#btnCalculate').on('click', function () {
        const pricePeriodId = $('#price_period_id').val();
        const paymentTermId = $('#payment_term_id').val();
        if (! pricePeriodId || ! paymentTermId) { showToast('Pilih Price Period dan Payment Term terlebih dahulu.', 'error'); return; }

        const { valid, items } = collectPayload();
        if (! valid || items.length === 0) { showToast('Lengkapi Produk dan Qty pada setiap baris.', 'error'); return; }

        showLoading();
        $.post(ENDPOINT_CALCULATE, {
            price_period_id: pricePeriodId,
            payment_term_id: paymentTermId,
            items: JSON.stringify(items),
        }).done(function (res) {
            if (! res.success) { showToast(res.message || 'Gagal menghitung.', 'error'); return; }
            renderResult(res);
            $('#staleBanner').addClass('d-none');
        }).fail(xhr => showToast(xhr.responseJSON?.message || 'Terjadi kesalahan pada server.', 'error'))
          .always(hideLoading);
    });

    function renderResult(res) {
        lastCalcResult = res;
        $('#resultSection').show();
        const s = res.summary;
        const commissionPct = parseFloat($('#commission_percent').val()) || 0;
        const commissionBase = parseFloat(s.commission_base) || 0;
        const commissionAmt = commissionBase * (commissionPct / 100);
        $('#commissionPreview').text(fmtRp(commissionAmt, 2));
        $('#summaryCards').html(`
            <div class="col-md-3"><div class="kpi-card"><div class="kpi-label">Total Qty</div><div class="kpi-value">${Number(s.total_qty).toLocaleString('id-ID')} Kg</div></div></div>
            <div class="col-md-3"><div class="kpi-card"><div class="kpi-label">Total Labour</div><div class="kpi-value">${fmtRp(s.total_labour_cost, 2)}</div></div></div>
            <div class="col-md-3"><div class="kpi-card"><div class="kpi-label">Grand Total</div><div class="kpi-value">${fmtRp(s.grand_total, 2)}</div></div></div>
            <div class="col-md-3"><div class="kpi-card"><div class="kpi-label">Est. Profit</div><div class="kpi-value ${s.estimated_profit >= 0 ? 'profit-positive' : 'profit-negative'}">${fmtRp(s.estimated_profit, 2)} (${s.estimated_profit_percent}%)</div></div></div>
            <div class="col-md-3"><div class="kpi-card"><div class="kpi-label">Sales Commission</div><div class="kpi-value">${fmtRp(commissionAmt, 2)}</div><div class="text-muted mt-1" style="font-size:.78rem;">(${commissionPct}% × base ${fmtRp(commissionBase, 2)})</div></div></div>
            <div class="col-md-3"><div class="kpi-card"><div class="kpi-label">Profit Setelah Komisi</div><div class="kpi-value ${(s.estimated_profit - commissionAmt) >= 0 ? 'profit-positive' : 'profit-negative'}">${fmtRp(s.estimated_profit - commissionAmt, 2)}</div></div></div>
        `);

        const rows = res.lines.map((line, i) => {
            if (line.errors && line.errors.length) {
                return `<tr class="table-danger"><td colspan="11"><i class="fa-solid fa-triangle-exclamation me-1"></i> Item ${i + 1}: ${line.errors.join(' ')}</td></tr>`;
            }
            $(`#itemRows tr:eq(${i})`).find('.row-subtotal').text(fmtRp(line.subtotal_final, 2));
            const warn = line.is_below_recommended ? ` <span class="badge bg-danger" title="Di bawah harga rekomendasi ${fmtRp(line.recommended_price, 2)}"><i class="fa-solid fa-triangle-exclamation"></i></span>` : '';
            const warnRow = (line.warnings && line.warnings.length) ? `<tr class="table-warning"><td colspan="11"><i class="fa-solid fa-triangle-exclamation me-1"></i> Item ${i + 1}: ${line.warnings.join(' ')}</td></tr>` : '';
            return `
                <tr>
                    <td class="fw-semibold">${line.product.name}<div class="small text-muted">${line.destination_label}</div></td>
                    <td>${fmtRp(line.cogs_sk, 2)}</td>
                    <td>${fmtRp(line.transport_cost, 2)}</td>
                    <td>${fmtRp(line.packing_cost, 2)}</td>
                    <td>${fmtRp(line.opex_cost, 2)}</td>
                    <td>${fmtRp(line.labour_cost, 2)}</td>
                    <td class="fw-semibold">${fmtRp(line.cogs_destination, 2)}</td>
                    <td><span class="badge bg-light text-dark border">${line.tier_name}</span></td>
                    <td>${fmtRp(line.recommended_price, 2)}</td>
                    <td>${fmtRp(line.selling_price, 2)}${warn}</td>
                    <td class="fw-semibold">${fmtRp(line.subtotal_final, 2)}</td>
                </tr>
                ${warnRow}
            `;
        }).join('');
        $('#resultTableBody').html(rows);
    }

    $('#orderForm').on('submit', function (e) {
        e.preventDefault();

        const { valid, items } = collectPayload();
        if (! valid || items.length === 0) { showToast('Lengkapi Produk dan Qty pada setiap baris.', 'error'); return; }
        if (! $('#price_period_id').val() || ! $('#payment_term_id').val() || ! $('#account_id').val()) {
            showToast('Customer, Price Period, dan Payment Term wajib diisi.', 'error');
            return;
        }

        const payload = {
            order_date: $('#order_date').val(),
            account_id: $('#account_id').val(),
            sales_id: $('#sales_id').val() || null,
            commission_percent: $('#commission_percent').val() || 0,
            price_period_id: $('#price_period_id').val(),
            payment_term_id: $('#payment_term_id').val(),
            notes: $('#notes').val(),
            items: JSON.stringify(items),
        };

        showLoading();
        $.post(ENDPOINT_SAVE, payload)
            .done(function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    setTimeout(() => window.location.href = "<?= base_url('admin/sales-order') ?>/" + res.id, 700);
                } else {
                    showToast(res.message || 'Gagal menyimpan.', 'error');
                }
            })
            .fail(xhr => showToast(xhr.responseJSON?.message || 'Terjadi kesalahan pada server.', 'error'))
            .always(hideLoading);
    });
});
</script>
<?= $this->endSection() ?>