<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<form id="quotationForm">
    <!-- TOP SECTION: PARAMETER UTAMA & TENAGA KERJA (KULI) -->
    <div class="card mb-4">
        <div class="section-header"><i class="fa-solid fa-sliders me-1"></i> 1. Parameter Utama &amp; Tenaga Kerja (Kuli)</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Price Period <span class="text-danger">*</span></label>
                    <select name="price_period_id" id="price_period_id" class="form-select" required>
                        <option value="">-- Pilih Period --</option>
                        <?php foreach ($periods as $period): ?>
                            <option value="<?= $period['id'] ?>"><?= $period['name'] ?? $period['period_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Payment Term <span class="text-danger">*</span></label>
                    <select name="payment_term_id" id="payment_term_id" class="form-select" required>
                        <option value="">-- Pilih Payment Term --</option>
                        <?php foreach ($paymentTerms as $term): ?>
                            <option value="<?= $term['id'] ?>"><?= $term['name'] ?> (<?= $term['days'] ?> hari)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- DYNAMIC LABOUR RATES CONTAINER (DARI PRICE SIMULATOR) -->
                <div class="col-md-6">
                    <div class="row g-3" id="labourInputsContainer">
                        <div class="col-12 text-muted small py-2">
                            <em>Silakan pilih <strong>Price Period</strong> terlebih dahulu.</em>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MIDDLE SECTION: ITEM ORDER / PRODUK & PACKING MULTIPLE -->
    <div class="card mb-4">
        <div class="section-header d-flex justify-content-between align-items-center">
<span><i class="fa-solid fa-boxes-stacked me-1"></i> 2. Item Order / Produk</span>
            <button type="button" class="btn btn-sm btn-primary" id="btnAddItem">
                <i class="fa-solid fa-plus me-1"></i> Tambah Produk
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 17%">Produk <span class="text-danger">*</span></th>
                            <th style="width: 14%">Transport (bisa &gt;1)</th>
                            <th style="width: 16%;">Jenis Packing (Bisa > 1)</th>
                                <th style="width: 12%">Opex (pilih)</th>
                            <th style="width: 10%">Qty (Kg) <span class="text-danger">*</span></th>
                            <th style="width: 12%">Manual Price (Rp/Kg)</th>
                            <th style="width: 5%" class="text-center" title="Bebankan labour budget">Kuli</th>
                            <th style="width: 10%">Subtotal (Est.)</th>
                            <th style="width: 5%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="itemRows">
                        <!-- Rows injection by JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="button" id="btnCalculate" class="btn btn-primary px-4">
                <i class="fa-solid fa-calculator me-1"></i> Hitung Simulasi
            </button>
        </div>
    </div>
</form>

<!-- BOTTOM SECTION: HASIL SIMULASI PENAWARAN HARGA -->
<div id="resultSection" style="display: none;">
    <div class="row">
        <!-- RINGKASAN SIMULASI -->
        <div class="col-md-4 mb-4">
            <div class="kpi-card h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-receipt me-1 text-primary"></i> Ringkasan Simulasi</h5>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Volume:</span>
                        <strong id="resTotalQty">0 Kg</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Biaya Kuli:</span>
                        <strong id="resTotalLabour">Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-muted small">
                        <span>(Beban Kuli per Kg):</span>
                        <span id="resLabourPerKg">Rp 0 / Kg</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total COGS:</span>
                        <strong class="text-danger" id="resTotalCogs">Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Grand Total Sales:</span>
                        <strong class="text-success fs-5" id="resGrandTotal">Rp 0</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Estimasi Profit:</span>
                        <strong class="text-primary fs-5" id="resProfit">Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Margin Profit (%):</span>
                        <span class="badge bg-info fs-6" id="resProfitPercent">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- DETAIL BREAKDOWN COGS & MARGIN -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="section-header"><i class="fa-solid fa-table-list me-1"></i> Detail Rincian COGS & Margin Per Item</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Produk</th>
                                    <th>Detail Packing</th>
                                    <th>COGS SK /Kg</th>
                                    <th>OPEX /Kg</th>
                                    <th>Transport /Kg</th>
                                    <th>Packing /Kg</th>
                                    <th>COGS Dest /Kg</th>
                                    <th>Harga Rekomendasi</th>
                                    <th>Harga Jual /Kg</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="resultBreakdownRows">
                                <!-- Dynamic calc lines -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const productsData = <?= json_encode($products ?? []) ?>;
    const routesData = <?= json_encode($routes ?? []) ?>;
    const packingsData = <?= json_encode($packings ?? []) ?>;

    let rowCounter = 0;

    $(document).ready(function() {
        addNewRow();

        // Trigger saat Period berubah untuk ambil Tarif Kuli
        $('#price_period_id').on('change', function() {
            const periodId = $(this).val();
            fetchLabourRates(periodId);
        });

        $('#btnAddItem').on('click', function() {
            addNewRow();
        });

        $(document).on('click', '.btn-remove-row', function() {
            if ($('#itemRows tr').length > 1) {
                $(this).closest('tr').remove();
            } else {
                showToast('Minimal harus ada 1 item produk.', 'warning');
            }
        });

        $('#btnCalculate').on('click', function() {
            calculateSimulation();
        });
    });

    // 1. Fetch Dynamic Labour Rates

let opexData = [];
let periodTransports = null; // null = belum difilter, pakai routesData
let periodPackings = null;   // null = belum difilter, pakai packingsData
let labourBudget = 0;

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
    $.get("<?= base_url('admin/price-simulator/components-by-period') ?>", { price_period_id: periodId }, function (res) {
        $c.empty();
        if (res && res.success) {
            opexData = res.opex || [];
            labourBudget = parseFloat(res.labour_budget || 0);
            periodTransports = res.transports || [];
            periodPackings = res.packings || [];
            refreshTransportPacking();
            $c.html(`
                <div class="col-md-6"><label class="form-label small text-muted">Labour Budget Periode</label>
                <div class="fw-semibold">${fmtRp(labourBudget, 2)}</div>
                <div class="form-text">Dibagi otomatis ke item yg dichecklist Kuli, proporsional qty.</div></div>
                <div class="col-md-6"><label class="form-label small text-muted">Opex tersedia</label>
                <div class="fw-semibold">${opexData.length} area</div>
                <div class="form-text">Pilih manual per item di kolom Opex.</div></div>
            `);
        } else {
            $c.html('<div class="col-12 text-danger small py-2">Gagal memuat komponen periode.</div>');
        }
        refreshOpexPickers();
        $('.opex-chip').each(function () {
            const id = String($(this).data('id'));
            const o = opexData.find(x => String(x.opex_area_id) === id);
            if (o) { $(this).contents().first().replaceWith(`${o.area_code} - ${o.area_name} `); }
        });
    }).fail(function () {
        $c.html('<div class="col-12 text-danger small py-2">Gagal memuat komponen periode.</div>');
        refreshOpexPickers();
    });
}

function refreshOpexPickers() {
    $('#itemRows .item-row, #itemsTableBody tr').each(function () {
        const $sel = $(this).find('.opex-select');
        if (!$sel.length) return;
        const picked = $(this).find('.opex-picked').data('ids') || [];
        buildOpexOptions($sel, picked);
    });
}

function buildOpexOptions($sel, selectedIds) {
    const sel = (selectedIds || []).map(String);
    let html = '<option value="">-- Pilih Opex --</option>';
    opexData.forEach(o => {
        const id = String(o.opex_area_id);
        if (sel.includes(id)) return;
        html += `<option value="${id}">${o.area_code} - ${o.area_name} (${fmtRp(parseFloat(o.opex_cost_per_kg || 0), 2)}/kg)</option>`;
    });
    $sel.html(html);
}

function opexPickerHtml() {
    return `
        <div class="opex-picked mb-1" data-ids='[]'></div>
        <div class="input-group input-group-sm">
            <select class="form-select form-select-sm opex-select"><option value="">-- Pilih Opex --</option></select>
            <button type="button" class="btn btn-outline-primary opex-add" title="Tambah Opex"><i class="fa-solid fa-plus"></i></button>
        </div>`;
}

function transportChecksHtml(routesData, namePrefix) {
    if (!routesData || !routesData.length) return '<span class="text-muted small">-</span>';
    return routesData.map(r => `
        <div class="form-check form-check-inline me-2 mb-1">
            <input class="form-check-input transport-check" type="checkbox" value="${r.id}" id="${namePrefix}_${r.id}">
            <label class="form-check-label small" for="${namePrefix}_${r.id}">${r.origin} &rarr; ${r.destination_name}</label>
        </div>`).join('');
}

    function fetchLabourRates(periodId) { fetchPeriodComponents(periodId); }

    // 2. Tambah Baris Input Produk & Packing Multiple

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
    });

    function addNewRow() {
        rowCounter++;
        
        let productOptions = '<option value="">-- Pilih Produk --</option>';
        productsData.forEach(p => {
            productOptions += `<option value="${p.id}">${p.name || p.product_name}</option>`;
        });


        const rowHtml = `
            <tr id="row_${rowCounter}" class="item-row">
                <td>
                    <select class="form-select item-product" required>
                        ${productOptions}
                    </select>
                </td>
                <td>
                    <div class="transport-checks">${transportChecksHtml((typeof periodTransports !== 'undefined' && periodTransports !== null) ? periodTransports : routesData, 'tr_' + rowCounter)}</div>
                </td>
                <td>
                    ${PackingChecklist.innerHtmlInline((typeof periodPackings !== 'undefined' && periodPackings !== null) ? periodPackings : packingsData)}
                </td>
                <td>
                    ${opexPickerHtml()}
                </td>
                <td>
                    <input type="number" step="0.01" min="0.1" class="form-control item-qty" placeholder="0" required>
                </td>
                <td>
                    <input type="number" step="0.01" min="0" class="form-control item-manual-price" placeholder="Auto (Tier)">
                </td>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input item-labour" title="Bebankan labour budget">
                </td>
                <td class="text-end fw-bold row-subtotal">
                    Rp 0
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#itemRows').append(rowHtml);
        PackingChecklist.refreshAll();
        refreshOpexPickers();
    }

    // 3. Kalkulasi Simulasi via AJAX
    function calculateSimulation() {
        const pricePeriodId = $('#price_period_id').val();
        const paymentTermId = $('#payment_term_id').val();

        if (!pricePeriodId || !paymentTermId) {
            showToast('Silakan pilih Price Period dan Payment Term terlebih dahulu.', 'warning');
            return;
        }

        // Kumpulkan data jumlah kuli
        let items = [];
        let isValid = true;

        $('.item-row').each(function() {
            const productId = $(this).find('.item-product').val();
            const selectedPackings = $(this).find('.packing-check:checked').map(function () { return $(this).val(); }).get();
            const qty = $(this).find('.item-qty').val();
            const manualPrice = $(this).find('.item-manual-price').val();

            if (!productId || !qty || qty <= 0) {
                isValid = false;
                return false;
            }

            items.push({
                product_id: productId,
                transport_route_id: routeId ? routeId : null,
                packing_ids: selectedPackings,
                qty: parseFloat(qty),
                manual_price: manualPrice !== '' ? parseFloat(manualPrice) : null,
                via_sukabumi_warehouse: true
            });
        });

        if (!isValid || items.length === 0) {
            showToast('Mohon lengkapi pilihan Produk dan Quantity pada tiap baris.', 'warning');
            return;
        }

        $.ajax({
            url: '<?= base_url('admin/price-simulator/calculate') ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                price_period_id: pricePeriodId,
                payment_term_id: paymentTermId,
                
                items: JSON.stringify(items)
            },
            beforeSend: function() {
                $('#btnCalculate').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Menghitung...');
            },
            success: function(response) {
                $('#btnCalculate').prop('disabled', false).html('<i class="fa-solid fa-calculator me-1"></i> Hitung Simulasi');
                if (response.success) {
                    renderResults(response);
                } else {
                    showToast(response.message || 'Gagal melakukan kalkulasi.', 'error');
                }
            },
            error: function(xhr) {
                $('#btnCalculate').prop('disabled', false).html('<i class="fa-solid fa-calculator me-1"></i> Hitung Simulasi');
                const err = xhr.responseJSON;
                showToast(err && err.message ? err.message : 'Terjadi kesalahan sistem.', 'error');
            }
        });
    }

    // 4. Render Hasil Simulasi dan Detail Breakdown Packing (Accordion)
    function renderResults(data) {
        const summary = data.summary;
        const lines = data.lines;

        // Render Summary Top Card
        $('#resTotalQty').text(fmtNum(summary.total_qty, 2) + ' Kg');
        $('#resTotalLabour').text('Rp ' + fmtNum(summary.total_labour_cost, 2));
        $('#resLabourPerKg').text('Rp ' + fmtNum(summary.labour_cost_per_kg, 2) + ' / Kg');
        $('#resTotalCogs').text('Rp ' + fmtNum(summary.total_cogs, 2));
        $('#resGrandTotal').text('Rp ' + fmtNum(summary.grand_total, 2));
        $('#resProfit').text('Rp ' + fmtNum(summary.estimated_profit, 2));
        $('#resProfitPercent').text(summary.estimated_profit_percent + '%');

        // Render Breakdown Rows
        let breakdownHtml = '';

        lines.forEach((line, index) => {
            if (line.errors && line.errors.length > 0) {
                breakdownHtml += `
                    <tr class="table-danger">
                        <td colspan="10">Error pada Item ${index + 1}: ${line.errors.join(', ')}</td>
                    </tr>
                `;
            } else {
                if (line.warnings && line.warnings.length > 0) {
                    breakdownHtml += `
                        <tr class="table-warning">
                            <td colspan="10"><i class="fa-solid fa-triangle-exclamation me-1"></i> Item ${index + 1}: ${line.warnings.join(' ')}</td>
                        </tr>
                    `;
                }
                const isBelow = line.is_below_recommended ? 'text-danger fw-bold' : '';
                
                $(`#itemRows tr:eq(${index})`).find('.row-subtotal').text('Rp ' + fmtNum(line.subtotal_final, 2));

                let packingDetailRows = '';
                if (line.packing_breakdown && line.packing_breakdown.length > 0) {
                    line.packing_breakdown.forEach(p => {
                        packingDetailRows += `
                            <tr>
                                <td class="ps-4">
                                    <i class="fa-solid fa-box text-muted me-1"></i> ${p.packing_name}
                                    ${p.is_custom ? '<span class="badge bg-info text-dark ms-1">Custom</span>' : ''}
                                </td>
                                <td class="fw-bold text-primary">${fmtNum(p.qty_pcs, 2)} ${p.unit || 'Pcs'}</td>
                                <td>${p.capacity_kg ? fmtNum(p.capacity_kg, 2) + ' Kg/pcs' : '-'}</td>
                                <td>Rp ${fmtNum(p.price_per_unit, 2)} / ${p.unit || 'Pcs'}</td>
                                <td class="fw-bold">Rp ${fmtNum(p.total_cost, 2)}</td>
                                <td>Rp ${fmtNum(p.cost_per_kg_product, 2)} / Kg</td>
                            </tr>
                        `;
                    });
                } else {
                    packingDetailRows = `<tr><td colspan="6" class="text-muted ps-4">Tidak ada packing yang digunakan.</td></tr>`;
                }

                const collapseId = `packingDetail_${index}`;
                const totalPcsText = line.total_packing_pcs ? `${fmtNum(line.total_packing_pcs, 2)} Pcs` : '0 Pcs';

                breakdownHtml += `
                    <tr>
                        <td><strong>${line.product.name}</strong></td>
                        <td>
                            <button class="btn btn-xs btn-outline-primary py-0 px-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                                <i class="fa-solid fa-boxes me-1"></i> ${totalPcsText} <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.75rem;"></i>
                            </button>
                        </td>
                        <td>Rp ${fmtNum(line.cogs_sk, 2)}</td>
                        <td>Rp ${fmtNum((line.opex_ho || 0) + (line.opex_sukabumi || 0) + (line.opex_destination || 0), 2)}</td>
                        <td>Rp ${fmtNum(line.transport_cost, 2)}</td>
                        <td>Rp ${fmtNum(line.packing_cost, 2)}</td>
                        <td>Rp ${fmtNum(line.cogs_destination, 2)}</td>
                        <td>Rp ${fmtNum(line.recommended_price, 2)}</td>
                        <td class="${isBelow}">Rp ${fmtNum(line.selling_price, 2)}</td>
                        <td class="fw-bold">Rp ${fmtNum(line.subtotal_final, 2)}</td>
                    </tr>
                    
                    <tr class="p-0 border-0">
                        <td colspan="10" class="p-0 border-0">
                            <div class="collapse bg-light p-3 border-bottom" id="${collapseId}">
                                <h6 class="fw-bold text-secondary mb-2" style="font-size: 0.85rem;">
                                    <i class="fa-solid fa-info-circle me-1"></i> Rincian Penggunaan Packing:
                                </h6>
                                <table class="table table-sm table-bordered bg-white mb-0 small">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Material Packing</th>
                                            <th>Penggunaan (Pcs)</th>
                                            <th>Kapasitas Material</th>
                                            <th>Harga / Unit</th>
                                            <th>Total Biaya Packing</th>
                                            <th>Beban Cost / Kg</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${packingDetailRows}
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                `;
            }
        });

        $('#resultBreakdownRows').html(breakdownHtml);
        $('#resultSection').slideDown();
    }

</script>

<?= $this->endSection() ?>