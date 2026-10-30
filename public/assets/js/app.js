/**
 * app.js
 * Helper global untuk seluruh halaman Back Office.
 * Business logic tetap di server; file ini hanya mengatur interaksi UI.
 */
$(function () {
    // Toggle sidebar (desktop collapse / mobile show)
    $('#sidebarToggle').on('click', function () {
        if (window.innerWidth <= 768) {
            $('#sidebar').toggleClass('show');
        } else {
            $('#sidebar').toggleClass('collapsed');
        }
    });

    // Auto-init semua toast yang ada di halaman
    $('.toast').each(function () {
        new bootstrap.Toast(this).show();
    });
});

/**
 * Dropdown-checklist packing (PRD-BACKOFFICE-UI T4).
 * Dipakai di: taking_order/index, price_simulator/index, sales_order/form.
 *
 * Menampilkan tombol dropdown berisi checkbox + chip pilihan, sambil menjaga
 * <select class="item-packing d-none" multiple> tetap tersinkron — sehingga
 * kode payload lama ($(row).find('.item-packing').val()) tidak berubah (regresi 0).
 */
const PackingChecklist = {
    esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    },

    /**
     * @param {Array} packings     [{id, name|packing_name|type}]
     * @param {Array} selectedIds id yang pre-terpilih (string/number) — untuk mode edit
     * @return {string} HTML isi sel (tanpa <td>)
     */
    innerHtml(packings, selectedIds = []) {
        const items = Array.isArray(packings) ? packings : [];
        const sel = new Set((selectedIds || []).map(String));
        const labelOf = (p) => this.esc(p.name || p.packing_name || p.type || ('Packing #' + p.id));

        const checks = items.map(p => {
            const label = labelOf(p);
            const checked = sel.has(String(p.id)) ? ' checked' : '';
            return '<li><label class="dropdown-item d-flex align-items-center gap-2 mb-0 py-1">' +
                `<input type="checkbox" class="form-check-input m-0 packing-check" value="${p.id}" data-label="${label}"${checked}>` +
                `<span>${label}</span></label></li>`;
        }).join('');

        const opts = items.map(p =>
            `<option value="${p.id}"${sel.has(String(p.id)) ? ' selected' : ''}>${labelOf(p)}</option>`
        ).join('');

        return `<select class="item-packing d-none" multiple tabindex="-1" aria-hidden="true">${opts}</select>` +
            '<div class="dropdown">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start packing-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-boundary="viewport">' +
            '<span class="packing-label text-muted">Pilih packing...</span></button>' +
            `<ul class="dropdown-menu p-1 packing-menu" style="max-height: 240px; overflow-y: auto; min-width: 220px;">` +
            (checks || '<li><span class="dropdown-item-text text-muted small">Tidak ada packing.</span></li>') +
            '</ul></div>' +
            '<div class="packing-chips d-flex flex-wrap gap-1 mt-1"></div>';
    },

    /**
     * Varian inline: checkbox langsung tampil di sel (tanpa dropdown).
     * Kontrak payload sama: hidden select .item-packing + checkbox .packing-check,
     * jadi syncRow()/refreshAll()/payload lama tetap jalan tanpa perubahan.
     */
    innerHtmlInline(packings, selectedIds = []) {
        const items = Array.isArray(packings) ? packings : [];
        const sel = new Set((selectedIds || []).map(String));
        const labelOf = (p) => this.esc(p.name || p.packing_name || p.type || ('Packing #' + p.id));

        const opts = items.map(p =>
            `<option value="${p.id}"${sel.has(String(p.id)) ? ' selected' : ''}>${labelOf(p)}</option>`
        ).join('');

        const checks = items.map(p => {
            const label = labelOf(p);
            const checked = sel.has(String(p.id)) ? ' checked' : '';
            return '<label class="d-flex align-items-center gap-2 mb-1" style="cursor:pointer;font-size:.85rem;white-space:nowrap;">' +
                `<input type="checkbox" class="form-check-input m-0 packing-check" value="${p.id}" data-label="${label}"${checked}>` +
                `<span>${label}</span></label>`;
        }).join('');

        return `<select class="item-packing d-none" multiple tabindex="-1" aria-hidden="true">${opts}</select>` +
            `<div class="packing-inline">${checks || '<span class="text-muted small">Tidak ada packing.</span>'}</div>`;
    },

    /** Sinkronkan hidden select + chip + label tombol untuk satu baris. */
    syncRow($row) {
        const vals = [];
        const chips = [];
        $row.find('.packing-check:checked').each(function () {
            vals.push($(this).val());
            chips.push({ v: $(this).val(), t: $(this).data('label') });
        });
        $row.find('.item-packing').val(vals);

        const $label = $row.find('.packing-label');
        const $wrap = $row.find('.packing-chips');
        $wrap.empty();
        if (vals.length === 0) {
            $label.text('Pilih packing...').addClass('text-muted');
            return;
        }
        $label.text(vals.length + ' packing dipilih').removeClass('text-muted');
        chips.forEach(c => {
            $wrap.append(
                '<span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">' + c.t +
                `<button type="button" class="packing-chip-x border-0 bg-transparent p-0 lh-1" data-value="${c.v}" aria-label="Hapus"><i class="fa-solid fa-xmark"></i></button></span>`
            );
        });
    },

    /** Sinkronkan semua baris yang ada (dipanggil setelah render baris dinamis). */
    refreshAll() {
        const seen = new Set();
        $('.packing-check').each(function () {
            const tr = $(this).closest('tr')[0];
            if (tr && !seen.has(tr)) {
                seen.add(tr);
                PackingChecklist.syncRow($(tr));
            }
        });
    },

    init() {
        $(document).on('change', '.packing-check', function () {
            PackingChecklist.syncRow($(this).closest('tr'));
        });
        $(document).on('click', '.packing-chip-x', function (e) {
            e.stopPropagation();
            const $row = $(this).closest('tr');
            $row.find('.packing-check[value="' + $(this).data('value') + '"]').prop('checked', false);
            PackingChecklist.syncRow($row);
        });
    }
};

$(function () {
    PackingChecklist.init();
    PackingChecklist.refreshAll();
});
/** Loading overlay global — untuk aksi simpan/hapus (refresh tabel pakai tableSkeleton()). */
function showLoading() {
    $('#globalLoadingOverlay').removeClass('d-none');
}
function hideLoading() {
    $('#globalLoadingOverlay').addClass('d-none');
}

/**
 * Skeleton baris tabel — dipakai saat refresh tabel via AJAX agar layar
 * tidak tertutup overlay global (PRD-BACKOFFICE-UI T3).
 * Contoh: $('#tableContainer').html(tableSkeleton(8));
 */
function tableSkeleton(cols = 6, rows = 3) {
    let cells = '';
    for (let c = 0; c < cols; c++) cells += '<td><div class="skel-bar"></div></td>';
    let body = '';
    for (let r = 0; r < rows; r++) body += '<tr>' + cells + '</tr>';
    return '<div class="table-responsive"><table class="table mb-0" aria-busy="true"><tbody>' + body + '</tbody></table></div>';
}

/**
 * Toast notification generik (dipakai untuk response AJAX).
 */
function showToast(message, type = 'success') {
    const icons = { success: 'check-circle', error: 'circle-exclamation', warning: 'triangle-exclamation', info: 'circle-info' };
    const bg = type === 'error' ? 'danger' : type;
    const $toast = $(`
        <div class="toast align-items-center text-bg-${bg} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="fa-solid fa-${icons[type] || 'circle-info'} me-2"></i>${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    $('.toast-container').append($toast);
    new bootstrap.Toast($toast[0], { autohide: true, delay: 4000 }).show();
}

/**
 * Modal konfirmasi reusable.
 * Contoh pemakaian:
 *   confirmAction({
 *     title: 'Hapus Data',
 *     text: 'Data yang dihapus tidak dapat dikembalikan.',
 *     onConfirm: () => ajaxDelete('/admin/account/5/delete')
 *   });
 */
function confirmAction({ title = 'Konfirmasi', text = 'Apakah Anda yakin?', onConfirm }) {
    $('#modalConfirmTitle').text(title);
    $('#modalConfirmText').text(text);

    const modalEl = document.getElementById('modalConfirm');
    const modal = new bootstrap.Modal(modalEl);

    $('#modalConfirmBtn').off('click').on('click', function () {
        modal.hide();
        if (typeof onConfirm === 'function') onConfirm();
    });

    modal.show();
}

/**
 * Helper AJAX POST generik dengan loading + toast otomatis.
 */
function ajaxPost(url, data = {}, onSuccess = null) {
    showLoading();
    $.post(url, data)
        .done(function (res) {
            if (res.success) {
                showToast(res.message || 'Berhasil.', 'success');
                if (typeof onSuccess === 'function') onSuccess(res);
            } else {
                showToast(res.message || 'Terjadi kesalahan.', 'error');
            }
        })
        .fail(function (xhr) {
            const msg = xhr.responseJSON?.message || 'Terjadi kesalahan pada server.';
            showToast(msg, 'error');
        })
        .always(hideLoading);
}

function ajaxDelete(url, onSuccess = null) {
    ajaxPost(url, { _method: 'DELETE' }, onSuccess);
}

/**
 * Format angka ke format Indonesia, contoh: 2500000 -> "2.500.000"
 * @param {number} decimals jumlah digit desimal (default 0)
 */
function fmtNum(number, decimals = 0) {
    return Number(number || 0).toLocaleString('id-ID', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

/**
 * Format angka ke Rupiah Indonesia, contoh: 2500000 -> "Rp 2.500.000"
 * @param {number} decimals jumlah digit desimal (default 0)
 */
function fmtRp(number, decimals = 0) {
    return 'Rp ' + fmtNum(number, decimals);
}

/**
 * Format tanggal ke Bahasa Indonesia, contoh: "2026-10-05" -> "05 Oktober 2026"
 * @param {string} dateString format "Y-m-d" atau "Y-m-d H:i:s"
 * @param {boolean} withTime sertakan jam:menit
 */
function fmtDate(dateString, withTime = false) {
    if (!dateString) return '-';
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return dateString;
    const opts = withTime
        ? { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }
        : { day: '2-digit', month: 'long', year: 'numeric' };
    return new Intl.DateTimeFormat('id-ID', opts).format(d);
}

/**
 * Format angka ke Rupiah, contoh: 2500000 -> "Rp 2.500.000"
 * (alias lawas dari fmtRp — dipertahankan agar view lama tidak rusak)
 */
function formatRupiah(number) {
    return fmtRp(number);
}
