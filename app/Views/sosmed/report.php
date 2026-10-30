<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-3 d-print-none">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end justify-content-between">
        <div>
            <h5 class="mb-1">Report <?= $monthName ?> <?= $year ?></h5>
            <div class="text-muted small">
                Target: <?= $feedsPerWeek ?> Feeds + <?= $videosPerWeek ?> Video / minggu &times; <?= $numWeeks ?> minggu
            </div>
        </div>
        <div class="d-flex gap-2">
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
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Cetak</button>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="section-header"><i class="fa-solid fa-table me-1"></i> Ringkasan Pencapaian</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama</th>
                        <th class="text-center">Feeds<br><small>target <?= $targetFeeds ?></small></th>
                        <th class="text-center">Video<br><small>target <?= $targetVideos ?></small></th>
                        <th class="text-center">Total<br><small>target <?= $targetFeeds + $targetVideos ?></small></th>
                        <th class="text-center">Pencapaian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $tf = 0; $tv = 0; ?>
                    <?php foreach ($stats as $cid => $s): ?>
                        <?php
                        $tot = $s['FEED'] + $s['VIDEO'];
                        $tgt = $targetFeeds + $targetVideos;
                        $pct = $tgt > 0 ? round($tot / $tgt * 100) : 0;
                        $tf += $s['FEED']; $tv += $s['VIDEO'];
                        ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($s['name']) ?></td>
                        <td class="text-center"><?= $s['FEED'] ?></td>
                        <td class="text-center"><?= $s['VIDEO'] ?></td>
                        <td class="text-center fw-bold"><?= $tot ?></td>
                        <td class="text-center"><span class="badge <?= $pct >= 100 ? 'bg-success' : ($pct >= 70 ? 'bg-warning text-dark' : 'bg-danger') ?>"><?= $pct ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($stats)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada konten terbit bulan ini.</td></tr>
                    <?php else: ?>
                    <tr class="table-light fw-bold">
                        <td>Total Tim</td>
                        <td class="text-center"><?= $tf ?></td>
                        <td class="text-center"><?= $tv ?></td>
                        <td class="text-center"><?= $tf + $tv ?></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="section-header"><i class="fa-solid fa-list me-1"></i> Daftar Konten Terbit (<?= count($published) ?>)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Tanggal</th><th>Judul</th><th>Tipe</th><th>Pembuat</th><th>ER IG</th><th>ER TikTok</th><th>Link</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($published as $p): ?>
                    <tr>
                        <td class="text-muted small"><?= date('d M Y', strtotime($p['published_at'])) ?></td>
                        <td><a href="<?= base_url('admin/sosmed/' . $p['id']) ?>"><?= esc($p['title']) ?></a></td>
                        <td><?= $p['type'] === 'FEED' ? 'Feeds' : 'Video' ?></td>
                        <td><?= esc($p['creator_name'] ?? '-') ?></td>
                        <td>
                            <?php $ig = $p['metrics']['IG'] ?? null; ?>
                            <?php if ($ig && $ig['calc']['er'] !== null): ?>
                                <span class="badge bg-<?= $ig['calc']['verdict'] === 'bagus' ? 'success' : ($ig['calc']['verdict'] === 'cukup' ? 'warning text-dark' : 'danger') ?>"><?= $ig['calc']['er'] ?>%</span>
                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                        </td>
                        <td>
                            <?php $tt = $p['metrics']['TIKTOK'] ?? null; ?>
                            <?php if ($tt && $tt['calc']['er'] !== null): ?>
                                <span class="badge bg-<?= $tt['calc']['verdict'] === 'bagus' ? 'success' : ($tt['calc']['verdict'] === 'cukup' ? 'warning text-dark' : 'danger') ?>"><?= $tt['calc']['er'] ?>%</span>
                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($p['ig_link']): ?><a href="<?= esc($p['ig_link']) ?>" target="_blank" class="me-2"><i class="fa-brands fa-instagram text-danger"></i> IG</a><?php endif; ?>
                            <?php if ($p['tiktok_link']): ?><a href="<?= esc($p['tiktok_link']) ?>" target="_blank"><i class="fa-brands fa-tiktok"></i> TikTok</a><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($published)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
