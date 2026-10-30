<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $T = \App\Services\PosExceptionService::TYPES; ?>
<form class="card mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label mb-1">Dari</label><input type="date" name="from" class="form-control" value="<?= esc($from) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label mb-1">Sampai</label><input type="date" name="to" class="form-control" value="<?= esc($to) ?>"></div>
        <div class="col-md-4"><label class="form-label mb-1">Jenis</label>
            <select name="type" class="form-select"><option value="">Semua</option><?php foreach ($T as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v[0] ?> (<?= $counts[$k] ?>)</option><?php endforeach ?></select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Tampilkan</button></div>
    </div>
</form>

<div class="row g-2 mb-3">
    <?php foreach ($T as $k => $v): ?>
        <div class="col-6 col-md-3 col-xl"><a class="text-decoration-none" href="?from=<?= esc($from) ?>&to=<?= esc($to) ?>&type=<?= $k ?>"><div class="card"><div class="card-body py-2"><div class="small text-muted"><?= $v[0] ?></div><div class="fs-4 fw-bold text-<?= $counts[$k] ? $v[1] : 'muted' ?>"><?= $counts[$k] ?></div></div></div></a></div>
    <?php endforeach ?>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-triangle-exclamation me-1"></i> Kejadian di luar alur normal</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Waktu</th><th>Jenis</th><th>Referensi</th><th>Kasir / pelaku</th><th>Disetujui oleh</th><th class="text-end">Nilai (Rp)</th><th>Keterangan</th></tr></thead>
            <tbody>
            <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Tidak ada pengecualian pada periode ini.</td></tr><?php endif ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="small text-nowrap"><?= $r['at'] ? date('d/m H:i', strtotime($r['at'])) : '-' ?></td>
                    <td><span class="badge text-bg-<?= $T[$r['type']][1] ?>"><?= $T[$r['type']][0] ?></span></td>
                    <td><?php if ($r['url']): ?><a href="<?= esc($r['url']) ?>"><?= esc($r['ref']) ?></a><?php else: ?><?= esc($r['ref']) ?><?php endif ?></td>
                    <td><?= esc($r['actor'] ?: '-') ?></td>
                    <td><?= $r['approver'] ? esc($r['approver']) : '<span class="text-muted">-</span>' ?></td>
                    <td class="text-end"><?= $r['amount'] != 0 ? number_format($r['amount'], 0, ',', '.') : '-' ?></td>
                    <td class="small"><?= esc($r['detail']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Pola yang perlu diwaspadai: kasir yang sama berulang kali meminta override/diskon/void, void di jam sepi, atau selisih kas yang terus searah.</div>
</div>
<?= $this->endSection() ?>
