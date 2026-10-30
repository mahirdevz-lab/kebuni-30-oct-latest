<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
    .so-bar { position: sticky; top: 0; z-index: 20; background: var(--bs-body-bg); padding: .5rem 0; }
    .so-item { border: 1px solid var(--bs-border-color); border-radius: .6rem; padding: .75rem; margin-bottom: .6rem; background: var(--bs-body-bg); }
    .so-item.done { border-color: var(--bs-success); }
    .so-item.err { border-color: var(--bs-danger); }
    .so-item .so-qty { font-size: 1.4rem; text-align: right; }
    .so-submit { position: sticky; bottom: 0; z-index: 20; background: var(--bs-body-bg); padding: .6rem 0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$q       = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
$round   = (int) $session['count_round'];
$visible = array_values(array_filter($lines, static fn ($l) => $l['countable']));
?>
<div class="mx-auto" style="max-width: 640px">
    <div class="alert <?= $round > 1 ? 'alert-danger' : 'alert-warning' ?> py-2 small mb-2">
        <strong><?= esc($session['session_no']) ?></strong> · cut-off <?= date('d/m/Y H:i', strtotime($session['cutoff_at'])) ?>
        · <strong><?= $round > 1 ? 'HITUNG ULANG ke-' . $round : 'Hitung ke-1' ?></strong>.<br>
        <?php if ($round > 1): ?>
            Hitung ulang fisik produk di bawah dengan teliti (cek barang terlewat, double count, salah satuan). Angka sebelumnya sengaja tidak ditampilkan.
        <?php else: ?>
            <strong>Blind count:</strong> hitung fisik tiap barang lalu langsung ketik jumlahnya — tersimpan otomatis per produk. Saldo sistem tidak ditampilkan.
        <?php endif ?>
        Checkout terkunci selama sesi ini.
    </div>

    <div class="so-bar">
        <div class="d-flex justify-content-between small mb-1"><span>Progres hitung</span><span><strong id="soDone">0</strong> / <?= count($visible) ?></span></div>
        <div class="progress mb-2" style="height:8px"><div class="progress-bar bg-success" id="soProgress" style="width:0%"></div></div>
        <input type="search" id="soSearch" class="form-control form-control-lg mb-2" placeholder="Cari / scan barcode produk" autocomplete="off">
        <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="soFilter" id="fTodo" value="todo" checked><label class="btn btn-outline-secondary btn-sm" for="fTodo">Belum dihitung</label>
            <input type="radio" class="btn-check" name="soFilter" id="fAll" value="all"><label class="btn btn-outline-secondary btn-sm" for="fAll">Semua</label>
            <input type="radio" class="btn-check" name="soFilter" id="fDone" value="done"><label class="btn btn-outline-secondary btn-sm" for="fDone">Sudah</label>
        </div>
    </div>

    <div id="soList" class="mt-2">
        <?php foreach ($visible as $l): ?>
            <div class="so-item <?= $l['counted_qty'] !== null ? 'done' : '' ?>" data-pid="<?= (int) $l['product_id'] ?>"
                 data-search="<?= esc(mb_strtolower($l['product_name'] . ' ' . $l['product_code'] . ' ' . ($l['barcode'] ?? ''))) ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold"><?= esc($l['product_name']) ?></div>
                        <div class="small text-muted"><?= esc($l['product_code']) ?><?= (int) $l['added_during_count'] ? ' · <span class="badge text-bg-info">ditemukan saat hitung</span>' : '' ?></div>
                    </div>
                    <span class="so-status small text-success"><?= $l['counted_qty'] !== null ? '<i class="fa-solid fa-circle-check"></i> ' . esc($l['counted_name'] ?? '') : '' ?></span>
                </div>
                <div class="input-group input-group-lg mt-2">
                    <input type="text" class="form-control so-qty" inputmode="decimal" placeholder="Jumlah fisik" value="<?= $l['counted_qty'] !== null ? $q($l['counted_qty']) : '' ?>">
                    <span class="input-group-text"><?= esc($l['unit']) ?></span>
                </div>
                <input type="text" class="form-control form-control-sm mt-2 so-note" placeholder="Catatan (mis. busuk, rusak, salah satuan)" value="<?= esc($l['note'] ?? '') ?>" maxlength="255">
            </div>
        <?php endforeach ?>
        <div id="soEmpty" class="text-center text-muted py-4 d-none">Tidak ada produk pada filter ini.</div>
    </div>

    <div class="card mb-2">
        <div class="card-body py-2">
            <a class="small" data-bs-toggle="collapse" href="#soAddBox"><i class="fa-solid fa-plus me-1"></i> Ada barang fisik yang tidak ada di daftar?</a>
            <div class="collapse mt-2" id="soAddBox">
                <input type="search" id="soAddFind" class="form-control mb-1" placeholder="Cari nama / kode / barcode">
                <select id="soAddProduct" class="form-select mb-2">
                    <option value="">- pilih produk -</option>
                    <?php foreach ($otherProducts as $p): ?>
                        <option value="<?= $p['id'] ?>" data-search="<?= esc(mb_strtolower($p['product_name'] . ' ' . $p['product_code'] . ' ' . ($p['barcode'] ?? ''))) ?>"><?= esc($p['product_name']) ?> (<?= esc($p['product_code']) ?>, <?= esc($p['unit']) ?>)</option>
                    <?php endforeach ?>
                </select>
                <button type="button" class="btn btn-outline-primary btn-sm" id="soAddBtn">Tambahkan ke daftar hitung</button>
            </div>
        </div>
    </div>

    <div class="so-submit">
        <button class="btn btn-success btn-lg w-100" id="soSubmit"><i class="fa-solid fa-paper-plane me-1"></i> Selesai Hitung — Submit</button>
        <?php if ($canApprove): ?>
            <button class="btn btn-link text-danger w-100" id="soCancel">Batalkan sesi opname</button>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const URL = BASE_URL + 'admin/stock/opname/<?= (int) $session['id'] ?>';
    const total = $('.so-item').length;

    function progress() {
        const done = $('.so-item.done').length;
        $('#soDone').text(done); $('#soProgress').css('width', (total ? done / total * 100 : 0) + '%');
    }
    function filter() {
        const q = $('#soSearch').val().trim().toLowerCase(), f = $('input[name=soFilter]:checked').val();
        let shown = 0;
        $('.so-item').each(function () {
            const $i = $(this), done = $i.hasClass('done') && !$i.data('editing');
            const ok = (!q || $i.data('search').includes(q)) && (f === 'all' || (f === 'todo' ? !done : done));
            $i.toggleClass('d-none', !ok); if (ok) shown++;
        });
        $('#soEmpty').toggleClass('d-none', shown > 0);
    }

    function save($item) {
        const raw = $item.find('.so-qty').val().trim().replace(',', '.');
        if (raw !== '' && (isNaN(Number(raw)) || Number(raw) < 0)) { $item.addClass('err'); showToast('Jumlah tidak valid.', 'error'); return; }
        const $st = $item.find('.so-status').html('<i class="fa-solid fa-spinner fa-spin text-muted"></i>');
        $.post(URL + '/count', { product_id: $item.data('pid'), counted_qty: raw, note: $item.find('.so-note').val() })
            .done(() => { $item.removeClass('err').toggleClass('done', raw !== ''); $st.html(raw !== '' ? '<i class="fa-solid fa-circle-check"></i> tersimpan' : ''); progress(); })
            .fail(x => { $item.addClass('err'); $st.html('<span class="text-danger">gagal</span>'); showToast(x.responseJSON?.message || 'Gagal menyimpan — periksa koneksi lalu ulangi.', 'error'); });
    }

    // Simpan saat selesai mengetik (blur/Enter). Item yang sedang diedit tidak langsung hilang dari filter "Belum dihitung".
    $('#soList').on('focus', '.so-qty, .so-note', function () { $(this).closest('.so-item').data('editing', true); })
        .on('change', '.so-qty, .so-note', function () { save($(this).closest('.so-item')); })
        .on('blur', '.so-qty, .so-note', function () { const $i = $(this).closest('.so-item'); setTimeout(() => { $i.data('editing', false); }, 300); })
        .on('keydown', '.so-qty', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault(); $(this).trigger('change');
            const $next = $(this).closest('.so-item').nextAll('.so-item:not(.d-none)').first().find('.so-qty');
            $next.length ? $next.focus() : this.blur();
        });

    $('#soSearch').on('input', filter).on('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const $first = $('.so-item:not(.d-none)').first();
        if ($first.length) $first.find('.so-qty').focus();
    });
    $('input[name=soFilter]').on('change', filter);

    $('#soAddFind').on('input', function () {
        const q = this.value.trim().toLowerCase(); let first = null;
        $('#soAddProduct option[value!=""]').each(function () { const ok = !q || $(this).data('search').includes(q); $(this).prop('hidden', !ok); if (ok && !first) first = this.value; });
        if (q && first) $('#soAddProduct').val(first);
    });
    $('#soAddBtn').on('click', function () {
        const pid = $('#soAddProduct').val();
        if (!pid) { showToast('Pilih produk.', 'warning'); return; }
        ajaxPost(URL + '/add-line', { product_id: pid }, () => location.reload());
    });

    $('#soSubmit').on('click', () => confirmAction({
        title: 'Submit hasil hitung', text: 'Pastikan semua produk sudah dihitung dan tidak ada yang terlewat. Setelah submit, supervisor melakukan rekonsiliasi buku vs fisik.',
        onConfirm: () => ajaxPost(URL + '/submit', {}, () => location.reload())
    }));
    $('#soCancel').on('click', () => {
        const reason = prompt('Alasan membatalkan sesi opname:');
        if (reason) ajaxPost(URL + '/cancel', { reason }, () => location.href = BASE_URL + 'admin/stock/opname');
    });

    progress(); filter();
})();
</script>
<?= $this->endSection() ?>
