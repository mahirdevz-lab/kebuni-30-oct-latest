<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-4">
    <div class="section-header"><i class="fa-solid fa-wallet me-1"></i> Kelola Rekening Kas</div>
    <div class="card-body">
        <form id="accountForm" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Nama Rekening <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="cth: Bank BCA, Dompet Operasional" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipe</label>
                <select name="type" class="form-select">
                    <option value="BANK">BANK</option>
                    <option value="CASH">CASH</option>
                    <option value="EWALLET">E-WALLET</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Saldo Awal (Rp) <span class="text-danger">*</span></label>
                <input type="number" name="initial_balance" class="form-control" value="0" step="any" required>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Tambah Rekening</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Rekening</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Tipe</th>
                    <th class="text-end">Saldo Awal</th>
                    <th class="text-end">Pemasukan</th>
                    <th class="text-end">Pengeluaran</th>
                    <th class="text-end">Saldo Sekarang</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($a['name']) ?></td>
                    <td><span class="badge bg-light text-dark border"><?= esc($a['type'] ?? '-') ?></span></td>
                    <td class="text-end">
                        <span id="bal-<?= $a['id'] ?>">Rp <?= number_format($a['initial_balance'], 0, ',', '.') ?></span>
                        <button class="btn btn-sm btn-link p-0 ms-1" onclick="editBalance(<?= $a['id'] ?>, '<?= esc($a['name']) ?>', <?= $a['initial_balance'] ?>)" title="Ubah saldo awal">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td class="text-end text-success">Rp <?= number_format($a['total_in'], 0, ',', '.') ?></td>
                    <td class="text-end text-danger">Rp <?= number_format($a['total_out'], 0, ',', '.') ?></td>
                    <td class="text-end fw-bold">Rp <?= number_format($a['balance'], 0, ',', '.') ?></td>
                    <td>
                        <?php if ($a['is_active']): ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleAccount(<?= $a['id'] ?>)">
                            <?= $a['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Edit Saldo -->
<div class="modal fade" id="modalBalance" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ubah Saldo Awal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Rekening: <strong id="modalAccountName"></strong></p>
                <label class="form-label">Saldo Awal (Rp)</label>
                <input type="number" id="modalBalanceValue" class="form-control" step="any">
                <div class="form-text">Saldo sekarang = saldo awal + pemasukan − pengeluaran.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSaveBalance">Simpan</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const BASE = "<?= base_url('admin/finance') ?>";
let editId = null;

$('#accountForm').on('submit', function (e) {
    e.preventDefault();
    $.post(BASE + '/accounts/store', $(this).serialize())
        .done(res => {
            if (res.success) { showToast(res.message, 'success'); setTimeout(() => location.reload(), 800); }
            else showToast(res.message, 'error');
        })
        .fail(() => showToast('Gagal.', 'error'));
});

function editBalance(id, name, balance) {
    editId = id;
    $('#modalAccountName').text(name);
    $('#modalBalanceValue').val(balance);
    new bootstrap.Modal($('#modalBalance')).show();
}

$('#btnSaveBalance').on('click', function () {
    $.post(BASE + '/accounts/' + editId + '/balance', { initial_balance: $('#modalBalanceValue').val() })
        .done(res => {
            if (res.success) { showToast(res.message, 'success'); setTimeout(() => location.reload(), 800); }
            else showToast(res.message, 'error');
        });
});

function toggleAccount(id) {
    $.post(BASE + '/accounts/' + id + '/toggle')
        .done(res => {
            if (res.success) setTimeout(() => location.reload(), 500);
            else showToast(res.message, 'error');
        });
}
</script>
<?= $this->endSection() ?>
