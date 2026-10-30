<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <!-- KERANJANG -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="input-group input-group-lg">
                    <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                    <input type="text" id="productSearch" class="form-control" placeholder="Scan barcode / ketik kode atau nama produk, tekan Enter" autocomplete="off" autofocus>
                </div>
                <div id="searchResults" class="list-group mt-2 d-none" style="max-height: 280px; overflow:auto"></div>
            </div>
        </div>

        <div class="card">
            <div class="section-header"><i class="fa-solid fa-basket-shopping me-1"></i> Keranjang</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-end" style="width:120px">Harga</th>
                        <th style="min-width:130px">Qty</th>
                        <th style="width:110px" title="Barang gratis promo (Buy 1 Get 1): harga Rp0, stok tetap keluar">Gratis</th>
                        <th class="text-end" style="width:130px">Subtotal</th>
                        <th style="width:40px"></th>
                    </tr>
                    </thead>
                    <tbody id="cartBody">
                    <tr class="empty-row"><td colspan="6" class="text-center text-muted py-4">Keranjang kosong</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PEMBAYARAN -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label mb-1">Pelanggan <span class="text-muted small">(opsional untuk tunai lunas)</span></label>
                <div id="customerChosen" class="d-none d-flex justify-content-between align-items-center border rounded px-2 py-1 mb-1">
                    <span id="customerLabel"></span>
                    <button type="button" class="btn btn-sm btn-link text-danger" id="customerClear" title="Lepas pelanggan"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <input type="text" id="customerSearch" class="form-control" placeholder="Cari nomor telepon / nama" autocomplete="off">
                <div id="customerResults" class="list-group mt-1 d-none"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-bs-toggle="collapse" data-bs-target="#newCustomer"><i class="fa-solid fa-user-plus me-1"></i> Pelanggan baru</button>
                <div class="collapse mt-2" id="newCustomer">
                    <input type="text" id="ncName" class="form-control form-control-sm mb-1" placeholder="Nama *">
                    <input type="tel" id="ncPhone" class="form-control form-control-sm mb-1" placeholder="Nomor telepon / WhatsApp *">
                    <button type="button" class="btn btn-sm btn-primary" id="ncSave">Simpan pelanggan</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-muted">Total</span></div>
                <div class="display-6 fw-bold text-end mb-3" id="grandTotal">Rp 0</div>

                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="method" id="mCash" value="CASH" checked>
                    <label class="btn btn-outline-primary" for="mCash"><i class="fa-solid fa-money-bill-wave me-1"></i> Tunai</label>
                    <input type="radio" class="btn-check" name="method" id="mQris" value="QRIS">
                    <label class="btn btn-outline-primary" for="mQris"><i class="fa-solid fa-qrcode me-1"></i> QRIS</label>
                    <input type="radio" class="btn-check" name="method" id="mTransfer" value="TRANSFER">
                    <label class="btn btn-outline-primary" for="mTransfer"><i class="fa-solid fa-building-columns me-1"></i> Transfer</label>
                </div>

                <div id="cashBox">
                    <label class="form-label mb-1">Uang diterima</label>
                    <input type="number" id="tendered" class="form-control form-control-lg text-end" min="0" step="500" inputmode="numeric">
                    <div class="d-flex flex-wrap gap-1 mt-2" id="quickCash"></div>
                    <div class="d-flex justify-content-between mt-3">
                        <span id="changeLabel" class="text-muted">Kembalian</span>
                        <strong id="changeAmount" class="fs-5">Rp 0</strong>
                    </div>
                </div>
                <div id="qrisBox" class="d-none">
                    <div class="alert alert-warning small mb-2">
                        Pembayaran <span id="digitalName">QRIS</span> <strong>belum dianggap lunas</strong> sampai diverifikasi (bukti pembayaran/mutasi dicek supervisor/keuangan).<span id="transferNote" class="d-none"> Transfer masuk ke rekening <strong>Bank Utama</strong>.</span>
                    </div>
                    <label class="form-label mb-1">No. referensi / nama pengirim (opsional)</label>
                    <input type="text" id="qrisRef" class="form-control" maxlength="60">
                </div>

                <div class="mt-3 border-top pt-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label small mb-1"><i class="fa-solid fa-percent me-1"></i> Diskon manual</label>
                        <span id="discBadge" class="badge text-bg-warning d-none"></span>
                    </div>
                    <div class="input-group input-group-sm mb-1">
                        <select id="discType" class="form-select" style="max-width:90px"><option value="PCT">%</option><option value="AMT">Rp</option></select>
                        <input type="number" id="discValue" class="form-control text-end" min="0" step="any" placeholder="nilai diskon" inputmode="decimal">
                        <button type="button" class="btn btn-outline-warning" id="discApply">Terapkan</button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="discClear" title="Batalkan diskon"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <input type="text" id="discReason" class="form-control form-control-sm" placeholder="Alasan diskon (wajib)" maxlength="255">
                    <?php if (! $canDiscount): ?><div class="form-text">Butuh otorisasi supervisor (PIN).</div><?php endif ?>
                </div>

                <div class="mt-3 border-top pt-2">
                    <label class="form-label small mb-1 text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> Override stok</label>
                    <input type="text" id="overrideReason" class="form-control form-control-sm" placeholder="Alasan override bila stok buku tidak cukup" maxlength="255">
                    <div class="form-text">Hanya dipakai jika stok buku kurang. <?= $canOverride ? '' : 'Butuh otorisasi supervisor (PIN). ' ?>Masuk laporan pengecualian.</div>
                </div>

                <?php if (! ($canOverride && $canDiscount)): ?>
                    <div class="mt-2" id="supBox">
                        <button type="button" class="btn btn-sm btn-outline-dark w-100" id="btnSup"><i class="fa-solid fa-user-shield me-1"></i> Otorisasi supervisor (PIN)</button>
                        <div class="small text-success d-none" id="supOk"><i class="fa-solid fa-circle-check me-1"></i> Disetujui: <strong id="supName"></strong>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" id="supClear">lepas</button></div>
                    </div>
                <?php endif ?>

                <div id="checkoutError" class="alert alert-danger py-2 mt-3 d-none"></div>

                <button type="button" id="btnCheckout" class="btn btn-success btn-lg w-100 mt-3" disabled>
                    <i class="fa-solid fa-circle-check me-1"></i> Selesaikan Transaksi
                </button>
                <button type="button" id="btnReset" class="btn btn-link text-muted w-100 mt-1">Kosongkan keranjang</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal sukses -->
<div class="modal fade" id="doneModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fa-solid fa-circle-check text-success me-1"></i> Transaksi Berhasil</h5></div>
            <div class="modal-body">
                <div class="mb-1" id="doneNo"></div>
                <div class="d-flex justify-content-between"><span>Total</span><strong id="doneTotal"></strong></div>
                <div class="d-flex justify-content-between"><span>Dibayar terkonfirmasi</span><strong id="donePaid"></strong></div>
                <div class="d-flex justify-content-between fs-5"><span>Kembalian</span><strong id="doneChange"></strong></div>
                <div class="alert alert-warning py-2 mt-2 mb-0 d-none" id="donePending">Pembayaran QRIS/Transfer menunggu verifikasi.</div>
            </div>
            <div class="modal-footer">
                <a href="#" target="_blank" class="btn btn-outline-primary" id="doneReceipt"><i class="fa-solid fa-receipt me-1"></i> Struk & WhatsApp</a>
                <button type="button" class="btn btn-primary" id="doneNew"><i class="fa-solid fa-plus me-1"></i> Transaksi Baru</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="supModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title"><i class="fa-solid fa-user-shield me-1"></i> Otorisasi Supervisor</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <?php if (! $supervisors): ?>
                <div class="alert alert-warning small mb-0">Belum ada supervisor yang menyetel PIN. Supervisor menyetel PIN di menu <em>PIN Supervisor</em>.</div>
            <?php else: ?>
                <label class="form-label small mb-1">Supervisor</label>
                <select id="supId" class="form-select mb-2"><?php foreach ($supervisors as $sv): ?><option value="<?= $sv['id'] ?>"><?= esc($sv['full_name']) ?></option><?php endforeach ?></select>
                <label class="form-label small mb-1">PIN</label>
                <input type="password" id="supPin" class="form-control form-control-lg text-center" inputmode="numeric" maxlength="6" autocomplete="off" style="letter-spacing:.4em">
                <div class="text-danger small mt-2 d-none" id="supErr"></div>
            <?php endif ?>
        </div>
        <?php if ($supervisors): ?><div class="modal-footer"><button type="button" class="btn btn-dark w-100" id="supOkBtn">Verifikasi</button></div><?php endif ?>
    </div></div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const URL_BASE = BASE_URL + 'admin/pos';
    let cart = [];            // {id, code, name, unit, price, list_price, markdown, stock, qty, free}
    let customer = null;
    let sale = { uuid: newUuid(), submitting: false };

    function newUuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });
    }
    const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const num = v => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    // Kuantitas: tanpa nol di belakang koma agar "110" tidak tampil "110,000" (terbaca seratus sepuluh ribu).
    const fmtQty = v => Number(v || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 });
    const CAN_DISCOUNT = <?= $canDiscount ? 'true' : 'false' ?>, CAN_OVERRIDE = <?= $canOverride ? 'true' : 'false' ?>;
    let disc = null;   // {type, value, reason}
    let sup = null;    // {id, name, pin} - disimpan di memori saja, tidak pernah ke storage
    // Pratinjau promo terjadwal (server tetap yang menentukan harga & gratis final saat checkout).
    function eff(l) {
        let price = l.price, tier = false, free = 0;
        const pr = l.promo || {};
        if (pr.tier && l.qty + 0.0005 >= pr.tier.min && pr.tier.price < price) { price = pr.tier.price; tier = true; }
        if (pr.bogo && pr.bogo.buy > 0) {
            let sets = Math.floor((l.qty + 0.0005) / pr.bogo.buy);
            if (pr.bogo.max) sets = Math.min(sets, pr.bogo.max);
            free = Math.round(sets * pr.bogo.get * 1000) / 1000;
        }
        return { price, tier, free };
    }
    const total = () => cart.reduce((s, l) => s + Math.round(l.qty * eff(l).price * 100) / 100, 0);
    const discAmount = () => {
        if (!disc) return 0;
        const sub = total();
        return Math.min(disc.type === 'PCT' ? Math.round(sub * disc.value) / 100 : disc.value, Math.max(0, sub - 1));
    };
    const net = () => Math.round((total() - discAmount()) * 100) / 100;

    // ---- pencarian produk
    let st;
    $('#productSearch').on('input', function () {
        clearTimeout(st);
        const q = this.value.trim();
        if (!q) { $('#searchResults').addClass('d-none').empty(); return; }
        st = setTimeout(() => searchProducts(q, false), 200);
    }).on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); searchProducts(this.value.trim(), true); }
    });

    function searchProducts(q, autoAdd) {
        if (!q) return;
        $.get(URL_BASE + '/products', { q }).done(res => {
            const items = res.items || [];
            if (autoAdd && items.length) {
                // Enter: kode/barcode persis -> langsung masuk; selain itu ambil hasil pertama bila hanya satu.
                const exact = items.find(i => i.code.toLowerCase() === q.toLowerCase());
                if (exact || items.length === 1) { addToCart(exact || items[0]); $('#productSearch').val('').focus(); $('#searchResults').addClass('d-none').empty(); return; }
            }
            const $r = $('#searchResults').empty().toggleClass('d-none', !items.length);
            items.forEach(p => {
                $('<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between"></button>')
                    .html(`<span><strong>${esc(p.name)}</strong> <small class="text-muted">${esc(p.code)}</small>${p.markdown ? ' <span class="badge text-bg-danger">Promo</span>' : ''}${p.promo && p.promo.bogo ? ' <span class="badge text-bg-success">Beli ' + fmtQty(p.promo.bogo.buy) + ' gratis ' + fmtQty(p.promo.bogo.get) + '</span>' : ''}${p.promo && p.promo.tier ? ' <span class="badge text-bg-success">≥' + fmtQty(p.promo.tier.min) + ' → ' + fmtRp(p.promo.tier.price) + '</span>' : ''}</span>
                           <span>${fmtRp(p.price)}/${esc(p.unit)} <small class="text-muted ms-2">stok ${fmtQty(p.stock)}</small></span>`)
                    .on('click', () => { addToCart(p); $('#productSearch').val('').focus(); $r.addClass('d-none').empty(); })
                    .appendTo($r);
            });
            if (!items.length && autoAdd) showToast('Produk tidak ditemukan atau belum punya harga retail.', 'warning');
        });
    }

    function addToCart(p) {
        const ex = cart.find(l => l.id === p.id);
        if (ex) ex.qty = Math.round((ex.qty + (p.unit === 'Kg' ? 0.5 : 1)) * 1000) / 1000;
        else cart.push(Object.assign({}, p, { qty: p.unit === 'Kg' ? 0.5 : 1, free: 0 }));
        render();
    }

    function render() {
        const $b = $('#cartBody').empty();
        if (!cart.length) $b.append('<tr class="empty-row"><td colspan="6" class="text-center text-muted py-4">Keranjang kosong</td></tr>');
        cart.forEach((l, i) => {
            const e = eff(l);
            const over = (l.qty + l.free + e.free) > l.stock;
            $b.append(`<tr>
                <td>${esc(l.name)} <small class="text-muted">${esc(l.code)}</small>${l.markdown ? ' <span class="badge text-bg-danger">Promo</span>' : ''}
                    <div class="small ${over ? 'text-danger fw-semibold' : 'text-muted'}">stok ${fmtQty(l.stock)} ${esc(l.unit)}${over ? ' — tidak cukup' : ''}</div></td>
                <td class="text-end">${fmtRp(e.price)}${e.tier ? '<div class="small text-success">harga promo ≥ ' + fmtQty(l.promo.tier.min) + '</div>' : ''}${(l.markdown || e.tier) ? '<div class="small text-decoration-line-through text-muted">' + fmtRp(l.markdown ? l.list_price : l.price) + '</div>' : ''}</td>
                <td><div class="input-group input-group-sm flex-nowrap"><input type="number" class="form-control text-end qty" style="min-width:72px" data-i="${i}" value="${l.qty}" min="0.001" step="0.001" inputmode="decimal"><span class="input-group-text">${esc(l.unit)}</span></div>${e.free > 0 ? '<div class="small text-success"><i class="fa-solid fa-gift me-1"></i>gratis promo +' + fmtQty(e.free) + ' ' + esc(l.unit) + '</div>' : ''}</td>
                <td><input type="number" class="form-control form-control-sm text-end free" style="min-width:60px" data-i="${i}" value="${l.free}" min="0" step="0.001" inputmode="decimal"></td>
                <td class="text-end">${fmtRp(l.qty * e.price)}</td>
                <td><button type="button" class="btn btn-sm btn-link text-danger del" data-i="${i}"><i class="fa-solid fa-trash"></i></button></td>
            </tr>`);
        });
        refreshTotals();
    }

    $('#cartBody').on('change', '.qty', function () { const l = cart[$(this).data('i')]; l.qty = Math.round(num(this.value) * 1000) / 1000; if (l.qty <= 0) cart.splice($(this).data('i'), 1); render(); })
                  .on('change', '.free', function () { cart[$(this).data('i')].free = Math.max(0, Math.round(num(this.value) * 1000) / 1000); render(); })
                  .on('click', '.del', function () { cart.splice($(this).data('i'), 1); render(); });

    function method() { return $('input[name=method]:checked').val(); }

    function refreshTotals() {
        const t = net();
        $('#grandTotal').text(fmtRp(t));
        $('#discBadge').toggleClass('d-none', !disc).text(disc ? 'Diskon −' + fmtRp(discAmount()) : '');
        $('#discClear').toggleClass('d-none', !disc);
        const tendered = num($('#tendered').val());
        if (method() === 'CASH') {
            const diff = tendered - t;
            $('#changeLabel').text(diff >= 0 ? 'Kembalian' : 'Kurang bayar (sisa tagihan)');
            $('#changeAmount').text(fmtRp(Math.abs(diff))).toggleClass('text-danger', diff < 0);
        }
        const hasPay = method() !== 'CASH' || tendered > 0;
        $('#btnCheckout').prop('disabled', !cart.length || !hasPay || sale.submitting);

        const quick = [t, Math.ceil(t / 5000) * 5000, Math.ceil(t / 10000) * 10000, Math.ceil(t / 50000) * 50000, Math.ceil(t / 100000) * 100000];
        const $q = $('#quickCash').empty();
        [...new Set(quick.filter(v => v > 0))].forEach(v => $('<button type="button" class="btn btn-sm btn-outline-secondary"></button>').text(fmtNum(v)).on('click', () => { $('#tendered').val(v); refreshTotals(); }).appendTo($q));
    }

    $('#tendered').on('input', refreshTotals);
    $('input[name=method]').on('change', function () { $('#cashBox').toggleClass('d-none', method() !== 'CASH'); $('#qrisBox').toggleClass('d-none', method() === 'CASH'); $('#digitalName').text(method() === 'TRANSFER' ? 'Transfer' : 'QRIS'); $('#transferNote').toggleClass('d-none', method() !== 'TRANSFER'); refreshTotals(); });

    // ---- pelanggan
    let ct;
    $('#customerSearch').on('input', function () {
        clearTimeout(ct);
        const q = this.value.trim();
        if (q.length < 3) { $('#customerResults').addClass('d-none').empty(); return; }
        ct = setTimeout(() => $.get(URL_BASE + '/customers', { q }).done(res => {
            const $r = $('#customerResults').empty().toggleClass('d-none', !(res.items || []).length);
            (res.items || []).forEach(c => $('<button type="button" class="list-group-item list-group-item-action"></button>')
                .html(`${esc(c.nama_account)} <small class="text-muted">${esc(c.nomor_telepon || '-')}</small>`)
                .on('click', () => setCustomer(c)).appendTo($r));
        }), 250);
    });
    function setCustomer(c) {
        customer = { id: c.id, name: c.nama_account, phone: c.nomor_telepon };
        $('#customerLabel').text(customer.name + ' · ' + (customer.phone || '-'));
        $('#customerChosen').removeClass('d-none');
        $('#customerSearch').val('').addClass('d-none'); $('#customerResults').addClass('d-none').empty();
    }
    $('#customerClear').on('click', () => { customer = null; $('#customerChosen').addClass('d-none'); $('#customerSearch').removeClass('d-none'); });
    $('#ncSave').on('click', function () {
        $.post(URL_BASE + '/customers/store', { nama_account: $('#ncName').val(), nomor_telepon: $('#ncPhone').val() })
            .done(res => { showToast(res.message, res.existing ? 'info' : 'success'); setCustomer({ id: res.customer.id, nama_account: res.customer.nama_account, nomor_telepon: res.customer.nomor_telepon }); $('#newCustomer').collapse('hide'); $('#ncName,#ncPhone').val(''); })
            .fail(x => showToast(x.responseJSON?.message || 'Gagal menyimpan pelanggan.', 'error'));
    });

    // ---- checkout (tombol dikunci; UUID tetap sama untuk retry sehingga tidak pernah ganda)
    $('#btnCheckout').on('click', function () {
        if (sale.submitting) return;
        sale.submitting = true; refreshTotals();
        $('#checkoutError').addClass('d-none');
        const payload = {
            client_uuid: sale.uuid,
            customer_id: customer ? customer.id : null,
            items: cart.map(l => ({ product_id: l.id, qty: l.qty, free_qty: l.free })),
            payments: [method() === 'CASH' ? { method: 'CASH', tendered: num($('#tendered').val()) } : { method: method(), reference_no: $('#qrisRef').val() || null }],
            override_reason: $('#overrideReason').val() || null,
            discount: disc,
            supervisor: sup ? { id: sup.id, pin: sup.pin } : null,
        };
        $.ajax({ url: URL_BASE + '/checkout', method: 'POST', contentType: 'application/json', data: JSON.stringify(payload), dataType: 'json' })
            .done(res => {
                $('#doneNo').text(res.sale_no + (res.duplicate ? ' (sudah tersimpan sebelumnya)' : ''));
                $('#doneTotal').text(fmtRp(res.grand_total)); $('#donePaid').text(fmtRp(res.paid_total)); $('#doneChange').text(fmtRp(res.change_amount));
                $('#donePending').toggleClass('d-none', res.payment_status !== 'PENDING_VERIFICATION');
                $('#doneReceipt').attr('href', URL_BASE + '/sales/' + res.id);
                new bootstrap.Modal('#doneModal').show();
            })
            .fail(x => {
                sale.submitting = false;
                $('#checkoutError').removeClass('d-none').text(x.responseJSON?.message || 'Koneksi gagal. Periksa daftar penjualan sebelum mengulang; mengulang aman karena ID transaksi sama.');
                refreshTotals();
            });
    });
    $('#doneNew, #btnReset').on('click', function () {
        cart = []; customer = null; disc = null; sup = null; sale = { uuid: newUuid(), submitting: false };
        $('#tendered,#qrisRef,#overrideReason,#discValue,#discReason').val(''); $('#supOk').addClass('d-none'); $('#btnSup').removeClass('d-none'); $('#customerClear').trigger('click'); $('#checkoutError').addClass('d-none');
        bootstrap.Modal.getInstance(document.getElementById('doneModal'))?.hide();
        render(); $('#productSearch').focus();
    });

    // ---- otorisasi supervisor (PIN diperiksa awal untuk UX; keputusan final tetap di server saat checkout)
    let afterSup = null;
    function openSup(cb, ability) {
        afterSup = cb; sup = null;
        $('#supPin').val(''); $('#supErr').addClass('d-none').text('');
        $('#supModal').data('ability', ability);
        new bootstrap.Modal('#supModal').show();
        setTimeout(() => $('#supPin').focus(), 400);
    }
    function verifySup() {
        const pin = $('#supPin').val(), id = $('#supId').val();
        if (!/^\d{4,6}$/.test(pin)) { $('#supErr').removeClass('d-none').text('PIN 4–6 digit.'); return; }
        $.post(URL_BASE + '/verify-supervisor', { supervisor_id: id, pin, ability: $('#supModal').data('ability') })
            .done(res => {
                sup = { id: +id, name: res.name, pin };
                $('#supName').text(res.name); $('#supOk').removeClass('d-none'); $('#btnSup').addClass('d-none');
                bootstrap.Modal.getInstance(document.getElementById('supModal'))?.hide();
                if (afterSup) { const cb = afterSup; afterSup = null; cb(); }
            })
            .fail(x => $('#supErr').removeClass('d-none').text(x.responseJSON?.message || 'Verifikasi gagal.'));
    }
    $('#btnSup').on('click', () => openSup(null, $('#discValue').val() ? 'discount' : 'override'));
    $('#supOkBtn').on('click', verifySup);
    $('#supPin').on('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); verifySup(); } });
    $('#supClear').on('click', () => { sup = null; $('#supOk').addClass('d-none'); $('#btnSup').removeClass('d-none'); });

    function applyDiscount() {
        const value = num($('#discValue').val()), reason = $('#discReason').val().trim(), type = $('#discType').val();
        if (value <= 0) { showToast('Isi nilai diskon.', 'warning'); return; }
        if (!reason) { showToast('Alasan diskon wajib diisi.', 'warning'); return; }
        if (type === 'PCT' && value >= 100) { showToast('Diskon persen harus di bawah 100%. Untuk gratis gunakan kolom Gratis.', 'warning'); return; }
        if (!cart.length) { showToast('Keranjang masih kosong.', 'warning'); return; }
        disc = { type, value, reason }; refreshTotals();
    }
    $('#discApply').on('click', function () {
        if (!CAN_DISCOUNT && !sup) { openSup(applyDiscount, 'discount'); return; }
        applyDiscount();
    });
    $('#discClear').on('click', () => { disc = null; $('#discValue,#discReason').val(''); refreshTotals(); });

    render();
})();
</script>
<?= $this->endSection() ?>
