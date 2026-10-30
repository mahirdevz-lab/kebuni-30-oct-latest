<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Judul</th>
                <th>Tipe</th>
                <th>Pembuat</th>
                <th>Status</th>
                <th>Update Terakhir</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contents as $c): ?>
            <tr>
                <td class="fw-semibold"><?= esc($c['title']) ?></td>
                <td>
                    <?php if ($c['type'] === 'FEED'): ?>
                        <span class="badge bg-light text-dark border"><i class="fa-solid fa-image me-1"></i>Feeds</span>
                    <?php else: ?>
                        <span class="badge bg-light text-dark border"><i class="fa-solid fa-video me-1"></i>Video</span>
                    <?php endif; ?>
                </td>
                <td><?= esc($c['creator_name'] ?? $c['creator_username'] ?? '-') ?></td>
                <td><span class="badge bg-<?= $statusColors[$c['status']] ?>"><?= $statusLabels[$c['status']] ?></span></td>
                <td class="text-muted small"><?= date('d M Y H:i', strtotime($c['updated_at'])) ?></td>
                <td class="text-end">
                    <a href="<?= base_url('admin/sosmed/' . $c['id']) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-eye me-1"></i> Detail
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($contents)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada konten.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
