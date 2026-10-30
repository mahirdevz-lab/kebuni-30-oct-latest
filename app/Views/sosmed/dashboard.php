<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end justify-content-between">
        <div>
            <h5 class="mb-1">KPI <?= $monthName ?> <?= $year ?></h5>
            <div class="text-muted small">
                Target: <?= $feedsPerWeek ?> Feeds + <?= $videosPerWeek ?> Video / minggu
                &times; <?= $numWeeks ?> minggu = <strong><?= $targetFeeds ?> Feeds + <?= $targetVideos ?> Video</strong> / orang / bulan
            </div>
        </div>
        <form class="d-flex gap-2" method="get">
            <select name="month" class="form-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'][$m] ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-select">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button class="btn btn-primary">Lihat</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body d-flex align-items-center gap-3">
            <div class="display-6 text-primary"><i class="fa-solid fa-image"></i></div>
            <div><div class="fs-4 fw-bold"><?= $totalFeeds ?></div><div class="text-muted small">Feeds terbit (target <?= $targetFeeds ?>/orang)</div></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body d-flex align-items-center gap-3">
            <div class="display-6 text-danger"><i class="fa-solid fa-video"></i></div>
            <div><div class="fs-4 fw-bold"><?= $totalVideos ?></div><div class="text-muted small">Video terbit (target <?= $targetVideos ?>/orang)</div></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body d-flex align-items-center gap-3">
            <div class="display-6 text-success"><i class="fa-solid fa-users"></i></div>
            <div><div class="fs-4 fw-bold"><?= count($rows) ?></div><div class="text-muted small">Anggota tim aktif</div></div>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-bullseye me-1"></i> Pencapaian per Anggota</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th class="text-center">Feeds<br><small class="text-muted">target <?= $targetFeeds ?></small></th>
                        <th class="text-center">Video<br><small class="text-muted">target <?= $targetVideos ?></small></th>
                        <th style="min-width:200px">Total</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($r['name']) ?></td>
                        <td class="text-center"><?= $r['feeds'] ?> <small class="text-muted">(<?= $r['achFeed'] ?>%)</small></td>
                        <td class="text-center"><?= $r['videos'] ?> <small class="text-muted">(<?= $r['achVideo'] ?>%)</small></td>
                        <td>
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= $r['feeds'] + $r['videos'] ?> / <?= $targetTotal ?></span>
                                <span class="fw-bold"><?= $r['achTotal'] ?>%</span>
                            </div>
                            <div class="progress" style="height:8px">
                                <div class="progress-bar <?= $r['achTotal'] >= 100 ? 'bg-success' : ($r['achTotal'] >= 70 ? 'bg-warning' : 'bg-danger') ?>"
                                     style="width:<?= min(100, $r['achTotal']) ?>%"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if ($r['status'] === 'Tercapai'): ?>
                                <span class="badge bg-success">Tercapai</span>
                            <?php elseif ($r['status'] === 'Hampir'): ?>
                                <span class="badge bg-warning text-dark">Hampir</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Kurang</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data bulan ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-chart-column me-1"></i> Konten per Minggu</div>
    <div class="card-body">
        <?php $max = 1; foreach ($weekly as $w) { $max = max($max, $w['FEED'] + $w['VIDEO']); } ?>
        <?php foreach ($weekly as $w): ?>
            <?php $tot = $w['FEED'] + $w['VIDEO']; ?>
            <div class="row align-items-center mb-2 g-2">
                <div class="col-3 col-md-2 small text-muted"><?= $w['label'] ?></div>
                <div class="col-9 col-md-10">
                    <div class="d-flex" style="height:22px">
                        <div class="bg-primary rounded-start" style="width:<?= $tot ? $w['FEED'] / $max * 100 : 0 ?>%" title="<?= $w['FEED'] ?> feeds"></div>
                        <div class="bg-danger rounded-end" style="width:<?= $tot ? $w['VIDEO'] / $max * 100 : 0 ?>%" title="<?= $w['VIDEO'] ?> video"></div>
                    </div>
                    <div class="small text-muted"><?= $w['FEED'] ?> feeds &middot; <?= $w['VIDEO'] ?> video</div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="small mt-2"><span class="badge bg-primary">Feeds</span> <span class="badge bg-danger">Video</span></div>
    </div>
</div>

<!-- PERFORMA KONTEN (ENGAGEMENT RATE) -->
<div class="card">
    <div class="section-header"><i class="fa-solid fa-chart-line me-1"></i> Performa Konten — Engagement Rate</div>
    <div class="card-body">
        <div class="small text-muted mb-3">
            Target: IG <strong>&ge; 3%</strong> (by reach) &middot; TikTok <strong>&ge; 4%</strong> (by views).
            Isi metrik di halaman detail konten yang sudah terbit.
        </div>

        <?php if (empty($erByCreator)): ?>
            <div class="alert alert-info mb-0 small">Belum ada metrik tercatat bulan ini.</div>
        <?php else: ?>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Kreator</th><th>IG (avg ER)</th><th>TikTok (avg ER)</th><th>Konten bermetrik</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($erByCreator as $c): ?>
                            <tr>
                                <td><?= esc($c['name']) ?></td>
                                <td>
                                    <?php if ($c['ig_er'] !== null): ?>
                                        <span class="badge bg-<?= $c['ig_er'] >= 3 ? 'success' : ($c['ig_er'] >= 1.5 ? 'warning text-dark' : 'danger') ?>"><?= $c['ig_er'] ?>%</span>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['tt_er'] !== null): ?>
                                        <span class="badge bg-<?= $c['tt_er'] >= 4 ? 'success' : ($c['tt_er'] >= 2 ? 'warning text-dark' : 'danger') ?>"><?= $c['tt_er'] ?>%</span>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td><?= $c['n'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="fw-semibold small mb-2"><i class="fa-solid fa-trophy text-warning me-1"></i>Konten Terbaik</div>
                    <?php if (empty($topBottom['top'])): ?>
                        <div class="text-muted small">-</div>
                    <?php else: ?>
                        <?php foreach ($topBottom['top'] as $t): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-1 small">
                                <div>
                                    <a href="<?= base_url('admin/sosmed/' . $t['id']) ?>"><?= esc(mb_strimwidth($t['title'], 0, 40, '...')) ?></a>
                                    <span class="badge bg-secondary ms-1"><?= $t['platform'] ?></span>
                                    <div class="text-muted"><?= esc($t['creator_name']) ?></div>
                                </div>
                                <span class="badge bg-success fs-6"><?= $t['er'] ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="fw-semibold small mb-2"><i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>Perlu Perhatian</div>
                    <?php if (empty($topBottom['bottom'])): ?>
                        <div class="text-muted small">-</div>
                    <?php else: ?>
                        <?php foreach ($topBottom['bottom'] as $t): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-1 small">
                                <div>
                                    <a href="<?= base_url('admin/sosmed/' . $t['id']) ?>"><?= esc(mb_strimwidth($t['title'], 0, 40, '...')) ?></a>
                                    <span class="badge bg-secondary ms-1"><?= $t['platform'] ?></span>
                                    <div class="text-muted"><?= esc($t['creator_name']) ?></div>
                                </div>
                                <span class="badge bg-<?= $t['verdict'] === 'cukup' ? 'warning text-dark' : 'danger' ?> fs-6"><?= $t['er'] ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
