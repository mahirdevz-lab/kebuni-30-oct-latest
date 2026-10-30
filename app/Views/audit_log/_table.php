<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th width="150">Waktu</th><th>User</th><th>Modul</th><th>Aksi</th><th>Keterangan</th><th>IP</th></tr></thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-inbox"></i><p class="mb-0">Belum ada aktivitas tercatat.</p></div></td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td class="small text-muted"><?= tgl_id($r['created_at'], 'dd MMM yyyy HH.mm.ss') ?></td>
                    <td><?= $r['full_name'] ? esc($r['full_name']) . ' <span class="text-muted small">(' . esc($r['username']) . ')</span>' : '<span class="text-muted">-</span>' ?></td>
                    <td><span class="badge bg-light text-dark border"><?= esc($r['module']) ?></span></td>
                    <td class="fw-semibold"><?= esc($r['action']) ?></td>
                    <td class="small"><?= esc($r['description'] ?? '-') ?></td>
                    <td class="small text-muted"><?= esc($r['ip_address'] ?? '-') ?></td>
                </tr>
            <?php endforeach; endif ?>
        </tbody>
    </table>
</div>
<?= $this->include('partials/pager_footer', ['pager' => $pager]) ?>
