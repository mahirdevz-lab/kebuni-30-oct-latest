<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rp  = static fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
$rp2 = static fn ($n) => 'Rp ' . number_format((float) $n, 2, ',', '.');
$kg  = static fn ($n) => number_format((float) $n, 2, ',', '.');
$s   = $report['summary'] ?? null;
$per = $report['period'] ?? null;
$exportUrl = $period_id ? base_url('admin/report/final') . '?' . http_build_query(array_filter(['period_id' => $period_id, 'order_id' => $order_id, 'export' => 1])) : '#';
$isSingleOrder = ! empty($order_id) && ! empty($report['order']);
?>
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Price Period</label>
                <select name="period_id" class="form-select form-select-sm" onchange="this.form.order_id.value='';this.form.submit()">
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int) $period_id === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= esc($p['period_code'] . ' — ' . $p['name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Transaksi</label>
                <select name="order_id" class="form-select form-select-sm">
                    <option value="">Semua Transaksi</option>
                    <?php foreach ($orders as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= (int) $order_id === (int) $o['id'] ? 'selected' : '' ?>>
                            <?= esc($o['order_no'] . ' — ' . $o['customer'] . ' (' . $o['order_date'] . ')') ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill">Terapkan</button>
                <?php if ($period_id): ?>
                    <a href="<?= esc($exportUrl) ?>" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
                <?php endif ?>
            </div>
        </form>
        <?php if ($isSingleOrder): ?>
            <div class="alert alert-info mt-3 mb-0 py-2 small">
                <i class="fa-solid fa-circle-info me-1"></i>
                Menampilkan 1 transaksi: <strong><?= esc($report['order']['order_no']) ?></strong>
                — <?= esc($report['order']['customer']) ?> (<?= esc($report['order']['order_date']) ?>).
                <a href="<?= base_url('admin/report/final') . '?period_id=' . (int) $period_id ?>" class="alert-link">Kembali ke semua transaksi</a>
            </div>
        <?php endif ?>
    </div>
</div>

<?php if (! $report): ?>
    <div class="alert alert-info">Pilih Price Period untuk melihat Final Report.</div>
<?php else: ?>

<!-- RINGKASAN -->
<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="kpi-card"><div class="kpi-value"><?= $rp($s['omzet']) ?></div><div class="kpi-label">Total Omzet (<?= $kg($s['total_qty']) ?> Kg)</div></div></div>
    <div class="col-md-2"><div class="kpi-card"><div class="kpi-value"><?= $rp($s['modal']) ?></div><div class="kpi-label">Total Modal (COGS)</div></div></div>
    <div class="col-md-2"><div class="kpi-card"><div class="kpi-value <?= $s['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($s['profit']) ?></div><div class="kpi-label">Laba Kotor (<?= $s['margin_percent'] ?>%)</div></div></div>
    <div class="col-md-2"><div class="kpi-card"><div class="kpi-value text-warning"><?= $rp($s['total_discount']) ?></div><div class="kpi-label">Diskon Kualitas</div></div></div>
    <div class="col-md-2"><div class="kpi-card"><div class="kpi-value text-danger"><?= $rp($report['stock']['total_shrink_value'] ?? 0) ?></div><div class="kpi-label">Nilai Shrinkage<?= $isSingleOrder ? ' (periode)' : '' ?><?= ! empty($report['stock']['n_pending']) ? '<br><span class="text-warning">' . (int) $report['stock']['n_pending'] . ' produk belum dihitung (tidak termasuk)</span>' : '' ?></div></div></div>
    <div class="col-md-2"><div class="kpi-card"><div class="kpi-value"><?= $rp($report['receivables']['total_sisa']) ?></div><div class="kpi-label">Piutang Outstanding</div></div></div>
</div>
<div class="row g-3 mb-4">
    <?php $c = $report['commission']; ?>
    <div class="col-md-4"><div class="kpi-card"><div class="kpi-value text-warning"><?= $rp($c['total']) ?></div><div class="kpi-label">Total Komisi Sales</div></div></div>
    <div class="col-md-4"><div class="kpi-card"><div class="kpi-value profit-positive"><?= $rp($c['paid']) ?></div><div class="kpi-label">Komisi Sudah Dibayar</div></div></div>
    <div class="col-md-4"><div class="kpi-card"><div class="kpi-value text-danger"><?= $rp($c['remaining']) ?></div><div class="kpi-label">Sisa Hutang Komisi</div></div></div>
</div>

<!-- PER PRODUK -->
<div class="card mb-4">
    <div class="section-header"><i class="fa-solid fa-boxes-stacked me-1"></i> Per Produk — Avg Jual Aktual vs Rekomendasi</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
                <thead>
                    <tr>
                        <th>Kode</th><th>Produk</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Avg Rekomendasi</th>
                        <th class="text-end">Avg Aktual</th>
                        <th class="text-end">Selisih</th>
                        <th class="text-end">Diskon %</th>
                        <th class="text-end">Omzet</th>
                        <th class="text-end">COGS</th>
                        <th class="text-end">Profit</th>
                        <th class="text-end">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['products'] as $r): ?>
                        <tr>
                            <td class="text-muted"><?= esc($r['code']) ?></td>
                            <td class="fw-semibold"><?= esc($r['name']) ?></td>
                            <td class="text-end"><?= $kg($r['qty']) ?> <?= esc($r['unit']) ?></td>
                            <td class="text-end text-muted"><?= $rp2($r['avg_recommended']) ?></td>
                            <td class="text-end fw-semibold"><?= $rp2($r['avg_actual']) ?></td>
                            <td class="text-end <?= ($r['avg_recommended'] - $r['avg_actual']) > 0 ? 'text-warning fw-semibold' : '' ?>">
                                <?= $rp2($r['avg_recommended'] - $r['avg_actual']) ?>
                            </td>
                            <td class="text-end"><?= $r['discount_percent'] ?>%</td>
                            <td class="text-end"><?= $rp($r['omzet']) ?></td>
                            <td class="text-end"><?= $rp($r['cogs']) ?></td>
                            <td class="text-end <?= $r['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($r['profit']) ?></td>
                            <td class="text-end"><?= $r['margin_percent'] ?>%</td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (! $report['products']): ?>
                        <tr><td colspan="11" class="text-center text-muted py-3">Belum ada penjualan pada periode ini.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- TRANSAKSI PER CUSTOMER (full, bukan cuma piutang) -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="section-header"><i class="fa-solid fa-users me-1"></i> Transaksi per Customer <span class="badge bg-primary ms-1"><?= $report['transactions']['n_customer'] ?> customer</span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
                        <thead><tr><th>Customer</th><th class="text-center">Order</th><th class="text-end">Omzet</th><th class="text-end">Terbayar</th><th class="text-end">Sisa</th><th class="text-center">Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($report['transactions']['rows'] as $tc): ?>
                                <tr>
                                    <td class="fw-semibold"><?= esc($tc['customer'] ?? '-') ?></td>
                                    <td class="text-center"><?= $tc['n_order'] ?></td>
                                    <td class="text-end"><?= $rp($tc['omzet']) ?></td>
                                    <td class="text-end text-success"><?= $rp($tc['terbayar']) ?></td>
                                    <td class="text-end fw-semibold"><?= $rp($tc['sisa']) ?></td>
                                    <td class="text-center">
                                        <?php if ((float) $tc['sisa'] <= 0): ?>
                                            <span class="badge bg-success">Lunas</span>
                                        <?php elseif ((int) $tc['n_overdue'] > 0): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Belum Lunas</span>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                            <?php if (! $report['transactions']['rows']): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada transaksi pada periode ini.</td></tr>
                            <?php endif ?>
                        </tbody>
                        <?php if ($report['transactions']['rows']): ?>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-center"><?= $report['transactions']['total_order'] ?></td>
                                    <td class="text-end"><?= $rp($report['transactions']['total_omzet']) ?></td>
                                    <td class="text-end"><?= $rp($report['transactions']['total_terbayar']) ?></td>
                                    <td class="text-end"><?= $rp($report['transactions']['total_sisa']) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        <?php endif ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MATRIKS MARGIN + ANALISIS -->
    <?php if (! $isSingleOrder && ! empty($report['matrix'])): ?>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="section-header"><i class="fa-solid fa-th-large me-1"></i> Matriks Margin per Produk</div>
            <div class="card-body">
                <?php
                $mq = $report['matrix']['quadrants'];
                $qmeta = [
                    'star'      => ['label' => '⭐ STAR', 'desc' => 'Laris + margin tinggi', 'cls' => 'success'],
                    'plowhorse' => ['label' => '🐴 PLOW HORSE', 'desc' => 'Laris, margin tipis', 'cls' => 'warning'],
                    'puzzle'    => ['label' => '🧩 PUZZLE', 'desc' => 'Margin bagus, kurang laris', 'cls' => 'info'],
                    'dog'       => ['label' => '🐕 DOG', 'desc' => 'Sepi + margin rendah', 'cls' => 'danger'],
                ];
                ?>
                <div class="row g-2">
                    <?php foreach ($qmeta as $qk => $qm): ?>
                    <div class="col-md-6">
                        <div class="border rounded p-2 h-100 border-<?= $qm['cls'] ?>">
                            <div class="fw-bold text-<?= $qm['cls'] ?>" style="font-size:.8rem"><?= $qm['label'] ?></div>
                            <div class="text-muted" style="font-size:.72rem"><?= $qm['desc'] ?> (<?= count($mq[$qk]) ?>)</div>
                            <div class="mt-1" style="font-size:.78rem; max-height:120px; overflow-y:auto">
                                <?php foreach ($mq[$qk] as $qp): ?>
                                    <div class="d-flex justify-content-between">
                                        <span><?= esc($qp['name']) ?></span>
                                        <span class="text-muted"><?= number_format($qp['margin_percent'], 1) ?>%</span>
                                    </div>
                                <?php endforeach ?>
                                <?php if (! $mq[$qk]): ?>
                                    <span class="text-muted fst-italic">— kosong —</span>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
                <div class="mt-2 text-muted" style="font-size:.72rem">
                    Pembagi: qty ≥ <?= $report['matrix']['avg_qty'] ?> = laris; margin ≥ <?= $report['matrix']['avg_margin'] ?>% = profitabel.
                </div>
            </div>
        </div>
        <div class="card mb-4">
            <div class="section-header"><i class="fa-solid fa-lightbulb me-1"></i> Analisis</div>
            <div class="card-body">
                <ul class="mb-0" style="font-size:.85rem; padding-left:1.2rem">
                    <?php foreach ($report['matrix']['analysis'] as $an): ?>
                        <li class="mb-1"><?= esc($an) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif ?>

    <!-- STOK OPNAME (hanya level periode) -->
    <?php if (! $isSingleOrder): ?>
</div>

<div class="card mb-4">
    <div class="section-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-boxes-packing me-1"></i> Stok Opname &amp; Shrinkage — Detail per Produk</span>
        <button type="button" class="btn btn-sm btn-primary no-print" id="btnSaveOpname"><i class="fa-solid fa-floppy-disk me-1"></i> Simpan Opname</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.82rem; min-width:1200px">
                <thead class="text-center">
                    <tr>
                        <th rowspan="2" class="align-middle text-start" style="min-width:180px">Produk</th>
                        <th rowspan="2" class="align-middle text-end" style="white-space:nowrap; min-width:90px">COGS/kg</th>
                        <th rowspan="2" class="align-middle text-end" style="white-space:nowrap; min-width:90px">Stok Awal</th>
                        <th rowspan="2" class="align-middle text-end" style="white-space:nowrap; min-width:90px">Pembelian</th>
                        <th rowspan="2" class="align-middle text-end" style="white-space:nowrap; min-width:90px">Terjual</th>
                        <th colspan="2" class="border-start">Saldo Buku</th>
                        <th colspan="2" class="border-start">Sisa Stok Aktual</th>
                        <th colspan="2" class="border-start">Shrinkage</th>
                        <th colspan="2" class="border-start">Laba / Rugi</th>
                    </tr>
                    <tr>
                        <th class="text-end border-start" style="min-width:80px">Qty</th><th class="text-end" style="min-width:120px">Value</th>
                        <th class="text-end border-start" style="min-width:80px">Qty</th><th class="text-end" style="min-width:120px">Value</th>
                        <th class="text-end border-start" style="min-width:80px">Qty</th><th class="text-end" style="min-width:120px">Value</th>
                        <th class="text-end border-start" style="min-width:80px">Qty</th><th class="text-end" style="min-width:120px">Value</th>
                    </tr>
                </thead>
                <tbody id="opnameBody">
                    <?php foreach ($report['stock']['rows'] as $st): ?>
                        <tr data-product="<?= $st['product_id'] ?>">
                            <td class="fw-semibold"><?= esc($st['name']) ?><div class="small text-muted"><?= esc($st['unit']) ?></div></td>
                            <td class="text-end text-muted"><?= $rp2($st['buy_price']) ?></td>
                            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end op-awal" value="<?= $st['stok_awal'] ?>" style="width:80px"></td>
                            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end op-beli" value="<?= $st['pembelian'] ?>" style="width:80px"></td>
                            <td class="text-end text-muted"><?= $kg($st['terjual']) ?></td>
                            <td class="text-end border-start"><?= $kg($st['stok_buku']) ?></td>
                            <td class="text-end text-muted"><?= $rp($st['buku_value']) ?></td>
                            <td class="border-start"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end op-aktual <?= $st['has_opname'] ? '' : 'border-warning' ?>" value="<?= $st['has_opname'] ? $st['stok_aktual'] : '' ?>" placeholder="belum" title="Kosong = belum dihitung (tidak dihitung susut). Isi 0 bila barang memang habis." style="width:80px"></td>
                            <td class="text-end text-muted"><?= $st['has_opname'] ? $rp($st['aktual_value']) : '—' ?></td>
                            <td class="text-end border-start <?= $st['shrink_qty'] < 0 ? 'text-danger fw-semibold' : '' ?>"><?= $st['has_opname'] ? $kg($st['shrink_qty']) : '—' ?></td>
                            <td class="text-end <?= $st['shrink_value'] < 0 ? 'text-danger' : '' ?>"><?= $st['has_opname'] ? $rp($st['shrink_value']) : '—' ?></td>
                            <td class="text-end border-start"><?= $st['has_opname'] ? $kg($st['laba_rugi_qty']) : '—' ?></td>
                            <td class="text-end fw-semibold <?= $st['laba_rugi'] >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $st['has_opname'] ? $rp($st['laba_rugi']) : '—' ?></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (! $report['stock']['rows']): ?>
                        <tr><td colspan="13" class="text-center text-muted py-3">Belum ada produk terjual pada periode ini.</td></tr>
                    <?php endif ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <?php
                    $tAwal = $tBeli = $tJual = $tBukuQ = $tBukuV = $tAktQ = $tAktV = $tShrQ = $tShrV = $tLRQ = $tLR = 0;
                    foreach ($report['stock']['rows'] as $st) {
                        $tAwal  += $st['stok_awal'];
                        $tBeli  += $st['pembelian'];
                        $tJual  += $st['terjual'];
                        $tBukuQ += $st['stok_buku'];
                        $tBukuV += $st['buku_value'];
                        if ($st['has_opname']) {
                            $tAktQ += $st['stok_aktual'];
                            $tAktV += $st['aktual_value'];
                            $tShrQ += $st['shrink_qty'];
                            $tShrV += $st['shrink_value'];
                            $tLRQ  += $st['laba_rugi_qty'];
                            $tLR   += $st['laba_rugi'];
                        }
                    }
                    ?>
                    <tr>
                        <td>TOTAL</td>
                        <td></td>
                        <td class="text-end"><?= $kg($tAwal) ?></td>
                        <td class="text-end"><?= $kg($tBeli) ?></td>
                        <td class="text-end"><?= $kg($tJual) ?></td>
                        <td class="text-end border-start"><?= $kg($tBukuQ) ?></td>
                        <td class="text-end"><?= $rp($tBukuV) ?></td>
                        <td class="text-end border-start"><?= $kg($tAktQ) ?></td>
                        <td class="text-end"><?= $rp($tAktV) ?></td>
                        <td class="text-end border-start <?= $tShrQ < 0 ? 'text-danger' : '' ?>"><?= $kg($tShrQ) ?></td>
                        <td class="text-end <?= $tShrV < 0 ? 'text-danger' : '' ?>"><?= $rp($tShrV) ?></td>
                        <td class="text-end border-start"><?= $kg($tLRQ) ?></td>
                        <td class="text-end <?= $tLR >= 0 ? 'profit-positive' : 'profit-negative' ?>"><?= $rp($tLR) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="p-3 border-top small text-muted">
            Saldo Buku = Stok Awal + Pembelian − Terjual. Shrinkage = Saldo Buku − Stok Aktual (positif = susut).
            Valuasi @ harga beli periode ini. Laba/Rugi Stok = Nilai Sisa Aktual − Nilai Shrinkage.
            <?= $report['stock']['n_input'] ?> dari <?= count($report['stock']['rows']) ?> produk sudah diinput.
            Stok aktual <strong>kosong = belum dihitung</strong> dan tidak masuk shrinkage; isi <strong>0</strong> bila barang memang habis.
        </div>
    </div>
</div>
    <?php endif ?>

<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const PERIOD_ID = <?= (int) $period_id ?>;

$('#btnSaveOpname').on('click', function () {
    const rows = [];
    $('#opnameBody tr[data-product]').each(function () {
        rows.push({
            product_id:  $(this).data('product'),
            stok_awal:   parseFloat($(this).find('.op-awal').val()) || 0,
            pembelian:   parseFloat($(this).find('.op-beli').val()) || 0,
            // Kosong dikirim sebagai null (belum dihitung), bukan 0.
            stok_aktual: $(this).find('.op-aktual').val().trim() === '' ? null : (parseFloat($(this).find('.op-aktual').val()) || 0),
        });
    });

    confirmAction({
        title: 'Simpan Stok Opname',
        text: `Simpan data opname ${rows.length} produk untuk periode ini?`,
        onConfirm: () => ajaxPost(
            "<?= base_url('admin/report/final/opname') ?>",
            { period_id: PERIOD_ID, rows: JSON.stringify(rows) },
            () => window.location.reload()
        ),
    });
});
</script>
<?= $this->endSection() ?>
