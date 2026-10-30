<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="fa-solid fa-timeline me-1"></i> Timeline Aktivitas</h5>
        <form class="d-flex gap-2" method="get">
            <select name="creator" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Anggota</option>
                <?php foreach ($creators as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $creator === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['name'] ?? '-') ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php foreach ($grouped as $day => $items): ?>
<div class="card mb-3">
    <div class="section-header">
        <i class="fa-solid fa-calendar-day me-1"></i>
        <?= date('l, d M Y', strtotime($day)) ?>
        <span class="badge bg-light text-dark border ms-1"><?= count($items) ?> aktivitas</span>
    </div>
    <div class="card-body">
        <div class="timeline">
            <?php foreach ($items as $it): ?>
            <div class="d-flex gap-3 mb-3">
                <div class="flex-shrink-0">
                    <span class="badge bg-<?= $it['color'] ?> rounded-circle p-2" style="width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center">
                        <i class="fa-solid <?= $it['icon'] ?>"></i>
                    </span>
                </div>
                <div class="flex-grow-1 border-bottom pb-2">
                    <div><?= esc($it['text']) ?></div>
                    <a href="<?= base_url('admin/sosmed/' . $it['content_id']) ?>" class="fw-semibold"><?= esc($it['title']) ?></a>
                    <div class="text-muted small"><?= date('H:i', strtotime($it['at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($grouped)): ?>
<div class="card"><div class="card-body text-center text-muted py-4">Belum ada aktivitas.</div></div>
<?php endif; ?>

<?= $this->endSection() ?>
