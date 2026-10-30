<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <!-- Kolom kiri: preview + info -->
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1"><?= esc($content['title']) ?></h5>
                        <div class="text-muted small">
                            <?= $content['type'] === 'FEED' ? '<i class="fa-solid fa-image me-1"></i>Feeds' : '<i class="fa-solid fa-video me-1"></i>Video' ?>
                            &middot; oleh <?= esc($content['creator_name'] ?? '-') ?>
                            &middot; <?= date('d M Y H:i', strtotime($content['created_at'])) ?>
                        </div>
                    </div>
                    <span class="badge bg-<?= $statusColors[$content['status']] ?> fs-6"><?= $statusLabels[$content['status']] ?></span>
                </div>

                <?php if ($content['type'] === 'FEED' && $content['canva_link']): ?>
                    <a href="<?= esc($content['canva_link']) ?>" target="_blank" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Buka Desain di Canva
                    </a>
                    <div class="text-muted small text-break"><?= esc($content['canva_link']) ?></div>
                <?php elseif ($content['type'] === 'VIDEO' && $content['video_path']): ?>
                    <video controls class="w-100 rounded" style="max-height:480px;background:#000" preload="metadata">
                        <source src="<?= base_url('admin/sosmed/' . $content['id'] . '/video') ?>" type="video/mp4">
                        Browser tidak mendukung video.
                    </video>
                <?php endif; ?>

                <?php if (! empty($content['caption_draft'])): ?>
                    <div class="mt-3">
                        <div class="fw-semibold small text-muted mb-1">DRAFT CAPTION</div>
                        <div class="border rounded p-2 bg-light small" style="white-space:pre-wrap"><?= esc($content['caption_draft']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($content['status'] === 'PUBLISHED'): ?>
                    <div class="mt-3">
                        <div class="fw-semibold small text-muted mb-1">LINK PUBLIKASI</div>
                        <?php if ($content['ig_link']): ?>
                            <div><i class="fa-brands fa-instagram text-danger me-1"></i> <a href="<?= esc($content['ig_link']) ?>" target="_blank" class="text-break"><?= esc($content['ig_link']) ?></a></div>
                        <?php endif; ?>
                        <?php if ($content['tiktok_link']): ?>
                            <div><i class="fa-brands fa-tiktok me-1"></i> <a href="<?= esc($content['tiktok_link']) ?>" target="_blank" class="text-break"><?= esc($content['tiktok_link']) ?></a></div>
                        <?php endif; ?>
                    </div>

                    <!-- METRIK PERFORMA -->
                    <div class="mt-3">
                        <div class="fw-semibold small text-muted mb-2"><i class="fa-solid fa-chart-line me-1"></i>PERFORMA KONTEN</div>
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabIg" type="button">
                                    <i class="fa-brands fa-instagram text-danger me-1"></i>Instagram
                                    <?php if (isset($metrics['IG'])): ?>
                                        <span class="badge bg-<?= $metrics['IG']['calc']['verdict'] === 'bagus' ? 'success' : ($metrics['IG']['calc']['verdict'] === 'cukup' ? 'warning text-dark' : 'danger') ?> ms-1">
                                            <?= $metrics['IG']['calc']['er'] !== null ? $metrics['IG']['calc']['er'] . '%' : '-' ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTt" type="button">
                                    <i class="fa-brands fa-tiktok me-1"></i>TikTok
                                    <?php if (isset($metrics['TIKTOK'])): ?>
                                        <span class="badge bg-<?= $metrics['TIKTOK']['calc']['verdict'] === 'bagus' ? 'success' : ($metrics['TIKTOK']['calc']['verdict'] === 'cukup' ? 'warning text-dark' : 'danger') ?> ms-1">
                                            <?= $metrics['TIKTOK']['calc']['er'] !== null ? $metrics['TIKTOK']['calc']['er'] . '%' : '-' ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content border border-top-0 rounded-bottom p-3">
                            <div class="tab-pane fade show active" id="tabIg" role="tabpanel">
                                <?php $ig = $metrics['IG'] ?? null; ?>
                                <?php if ($ig && $ig['calc']['er'] !== null): ?>
                                    <div class="alert alert-<?= $ig['calc']['verdict'] === 'bagus' ? 'success' : ($ig['calc']['verdict'] === 'cukup' ? 'warning' : 'danger') ?> py-2 small">
                                        <strong>ER <?= $ig['calc']['er'] ?>%</strong> (by reach)
                                        — <?= $ig['calc']['verdict'] === 'bagus' ? 'Bagus! Di atas target 3%.' : ($ig['calc']['verdict'] === 'cukup' ? 'Cukup, masih di bawah target 3%.' : 'Kurang, perlu evaluasi konten.') ?>
                                    </div>
                                <?php endif; ?>
                                <form class="metricsForm" data-platform="IG">
                                    <?= csrf_field() ?>
                                    <div class="row g-2">
                                        <div class="col-4"><label class="form-label small">Reach *</label><input type="number" name="reach" class="form-control form-control-sm" min="0" value="<?= $ig['reach'] ?? '' ?>"></div>
                                        <div class="col-4"><label class="form-label small">Views</label><input type="number" name="views" class="form-control form-control-sm" min="0" value="<?= $ig['views'] ?? '' ?>"></div>
                                        <div class="col-4"><label class="form-label small">Likes</label><input type="number" name="likes" class="form-control form-control-sm" min="0" value="<?= $ig['likes'] ?? '' ?>"></div>
                                        <div class="col-4"><label class="form-label small">Comments</label><input type="number" name="comments" class="form-control form-control-sm" min="0" value="<?= $ig['comments'] ?? '' ?>"></div>
                                        <div class="col-4"><label class="form-label small">Shares</label><input type="number" name="shares" class="form-control form-control-sm" min="0" value="<?= $ig['shares'] ?? '' ?>"></div>
                                        <div class="col-4"><label class="form-label small">Saves</label><input type="number" name="saves" class="form-control form-control-sm" min="0" value="<?= $ig['saves'] ?? '' ?>"></div>
                                    </div>
                                    <div class="form-text mb-2">Angka dari Instagram Insights. Reach wajib diisi untuk hitung ER.</div>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-save me-1"></i>Simpan Metrik IG</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="tabTt" role="tabpanel">
                                <?php $tt = $metrics['TIKTOK'] ?? null; ?>
                                <?php if ($tt && $tt['calc']['er'] !== null): ?>
                                    <div class="alert alert-<?= $tt['calc']['verdict'] === 'bagus' ? 'success' : ($tt['calc']['verdict'] === 'cukup' ? 'warning' : 'danger') ?> py-2 small">
                                        <strong>ER <?= $tt['calc']['er'] ?>%</strong> (by views)
                                        — <?= $tt['calc']['verdict'] === 'bagus' ? 'Bagus! Di atas target 4%.' : ($tt['calc']['verdict'] === 'cukup' ? 'Cukup, masih di bawah target 4%.' : 'Kurang, perlu evaluasi konten.') ?>
                                    </div>
                                <?php endif; ?>
                                <form class="metricsForm" data-platform="TIKTOK">
                                    <?= csrf_field() ?>
                                    <div class="row g-2">
                                        <div class="col-4"><label class="form-label small">Views *</label><input type="number" name="views" class="form-control form-control-sm" min="0" value="<?= $tt['views'] ?? '' ?>"></div>
                                        <div class="col-4"><label class="form-label small">Likes</label><input type="number" name="likes" class="form-control form-control-sm" min="0" value="<?= $tt['likes'] ?? '' ?>"></div>
                                        <div class="col-4"><label class="form-label small">Comments</label><input type="number" name="comments" class="form-control form-control-sm" min="0" value="<?= $tt['comments'] ?? '' ?>"></div>
                                        <div class="col-4"><label class="form-label small">Shares</label><input type="number" name="shares" class="form-control form-control-sm" min="0" value="<?= $tt['shares'] ?? '' ?>"></div>
                                        <div class="col-4"><label class="form-label small">Reach</label><input type="number" name="reach" class="form-control form-control-sm" min="0" value="<?= $tt['reach'] ?? '' ?>" placeholder="Opsional"></div>
                                        <div class="col-4"><label class="form-label small">Saves</label><input type="number" name="saves" class="form-control form-control-sm" min="0" value="<?= $tt['saves'] ?? '' ?>" placeholder="Opsional"></div>
                                    </div>
                                    <div class="form-text mb-2">Angka dari TikTok Studio. Views wajib diisi untuk hitung ER.</div>
                                    <button type="submit" class="btn btn-sm btn-dark"><i class="fa-solid fa-save me-1"></i>Simpan Metrik TikTok</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Aksi sesuai status -->
        <div class="card">
            <div class="card-body">
                <?php if (in_array($content['status'], ['SUBMITTED', 'NEEDS_REVISION'], true)): ?>
                    <div class="fw-semibold mb-2"><i class="fa-solid fa-clipboard-check me-1"></i> Review</div>
                    <div class="mb-2">
                        <textarea id="reviewNotes" class="form-control" rows="2" placeholder="Catatan (wajib jika minta revisi / reject, opsional jika approve)"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success flex-fill" onclick="doReview('approve')">
                            <i class="fa-solid fa-check me-1"></i> Approve
                        </button>
                        <button class="btn btn-warning flex-fill" onclick="doReview('revise')">
                            <i class="fa-solid fa-rotate me-1"></i> Revisi
                        </button>
                        <button class="btn btn-danger flex-fill" onclick="doReview('reject')">
                            <i class="fa-solid fa-xmark me-1"></i> Reject
                        </button>
                    </div>
                <?php elseif ($content['status'] === 'APPROVED'): ?>
                    <div class="fw-semibold mb-2"><i class="fa-solid fa-upload me-1"></i> Input Link Publikasi</div>
                    <p class="text-muted small">Setelah upload ke IG / TikTok, masukkan linknya di sini.</p>
                    <div class="mb-2">
                        <label class="form-label small">Link Instagram</label>
                        <input type="url" id="igLink" class="form-control" placeholder="https://instagram.com/p/...">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Link TikTok</label>
                        <input type="url" id="tiktokLink" class="form-control" placeholder="https://tiktok.com/@.../video/...">
                    </div>
                    <button class="btn btn-primary w-100" onclick="doPublish()">
                        <i class="fa-solid fa-check me-1"></i> Tandai Terbit
                    </button>
                <?php elseif (in_array($content['status'], ['NEEDS_REVISION', 'REJECTED'], true) && $isOwner): ?>
                    <!-- tombol resubmit ada di bawah (form revisi) -->
                <?php elseif ($content['status'] === 'PUBLISHED'): ?>
                    <div class="alert alert-success mb-0 py-2 small"><i class="fa-solid fa-check-circle me-1"></i> Konten sudah terbit.</div>
                <?php else: ?>
                    <div class="alert alert-light mb-0 py-2 small text-muted">Menunggu aksi.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (in_array($content['status'], ['NEEDS_REVISION', 'REJECTED'], true) && $isOwner): ?>
        <div class="card mt-3">
            <div class="card-body">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-upload me-1"></i> Submit Revisi</div>
                <form id="resubmitForm" enctype="multipart/form-data">
                    <?php if ($content['type'] === 'FEED'): ?>
                        <div class="mb-2">
                            <label class="form-label small">Link Canva (baru)</label>
                            <input type="url" name="canva_link" class="form-control" value="<?= esc($content['canva_link']) ?>">
                        </div>
                    <?php else: ?>
                        <div class="mb-2">
                            <label class="form-label small">Upload Video (baru) — kosongkan jika tidak diganti</label>
                            <input type="file" name="video" class="form-control" accept=".mp4,.mov,.webm">
                        </div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label small">Draft Caption</label>
                        <textarea name="caption_draft" class="form-control" rows="2"><?= esc($content['caption_draft'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fa-solid fa-paper-plane me-1"></i> Kirim Revisi untuk Review Ulang
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Kolom kanan: thread komentar -->
    <div class="col-lg-5">
        <div class="card" style="position:sticky;top:1rem">
            <div class="section-header"><i class="fa-solid fa-comments me-1"></i> Diskusi & Notes Revisi</div>
            <div class="card-body">
                <div id="commentThread" style="max-height:420px;overflow-y:auto" class="mb-3">
                    <?php foreach ($comments as $cm): ?>
                        <?php
                        $isMine = (int) $cm['user_id'] === (int) session()->get('user_id');
                        $isSystem = str_starts_with($cm['message'], '[');
                        ?>
                        <div class="mb-2 <?= $isMine ? 'text-end' : '' ?>">
                            <div class="d-inline-block rounded px-3 py-2 small <?= $isSystem ? 'bg-light border w-100 text-start' : ($isMine ? 'bg-primary text-white' : 'bg-light border') ?>" style="max-width:90%">
                                <?php if (! $isMine): ?>
                                    <div class="fw-semibold small"><?= esc($cm['author_name'] ?? 'User') ?></div>
                                <?php endif; ?>
                                <div style="white-space:pre-wrap"><?= esc($cm['message']) ?></div>
                                <div class="small <?= $isMine && ! $isSystem ? 'text-white-50' : 'text-muted' ?> mt-1"><?= date('d M H:i', strtotime($cm['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($comments)): ?>
                        <div class="text-muted small text-center py-3">Belum ada diskusi.</div>
                    <?php endif; ?>
                </div>
                <form id="commentForm" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="text" id="commentInput" class="form-control" placeholder="Tulis komentar / balasan..." maxlength="2000">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const CID = <?= (int) $content['id'] ?>;
const BASE = "<?= base_url('admin/sosmed') ?>";
// Pakai var biar aman kalau script ke-load 2x
var SOSMED_CSRF = window.SOSMED_CSRF || {
    name: "<?= csrf_token() ?>",
    hash: "<?= csrf_hash() ?>"
};
window.SOSMED_CSRF = SOSMED_CSRF;

function csrfData(extra) {
    const d = {};
    d[SOSMED_CSRF.name] = SOSMED_CSRF.hash;
    return Object.assign(d, extra || {});
}

function scrollThread() {
    const el = document.getElementById('commentThread');
    el.scrollTop = el.scrollHeight;
}
scrollThread();

$('#commentForm').on('submit', function (e) {
    e.preventDefault();
    const msg = $('#commentInput').val().trim();
    if (! msg) return;
    $.post(BASE + '/' + CID + '/comment', csrfData({ message: msg }))
        .done(res => {
            if (res.success) setTimeout(() => location.reload(), 400);
            else showToast(res.message, 'error');
        })
        .fail(() => showToast('Gagal kirim komentar.', 'error'));
});

function doReview(action) {
    const notes = $('#reviewNotes').val().trim();
    if ((action === 'revise' || action === 'reject') && ! notes) { showToast('Wajib isi notes revisi/reject.', 'warning'); return; }
    const labels = { approve: 'Setujui konten ini?', revise: 'Kirim permintaan revisi?', reject: 'Reject konten ini? Konten akan dikembalikan ke draft.' };
    if (! confirm(labels[action] || 'Lanjutkan?')) return;

    $.post(BASE + '/' + CID + '/review', csrfData({ action: action, notes: notes }))
        .done(res => {
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) setTimeout(() => location.reload(), 800);
        })
        .fail(() => showToast('Gagal.', 'error'));
}

function doPublish() {
    const ig = $('#igLink').val().trim();
    const tt = $('#tiktokLink').val().trim();
    if (! ig && ! tt) { showToast('Isi minimal salah satu link.', 'warning'); return; }

    $.post(BASE + '/' + CID + '/publish', csrfData({ ig_link: ig, tiktok_link: tt }))
        .done(res => {
            showToast(res.message, res.success ? 'success' : 'error');
            if (res.success) setTimeout(() => location.reload(), 800);
        })
        .fail(() => showToast('Gagal.', 'error'));
}

const resubmitForm = document.getElementById('resubmitForm');
if (resubmitForm) {
    resubmitForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append(SOSMED_CSRF.name, SOSMED_CSRF.hash);
        $.ajax({
            url: BASE + '/' + CID + '/resubmit',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (res) {
                showToast(res.message, res.success ? 'success' : 'error');
                if (res.success) setTimeout(() => location.reload(), 800);
            },
            error: function () { showToast('Gagal submit revisi.', 'error'); }
        });
    });
}

// Simpan metrik performa (IG / TikTok)
$('.metricsForm').on('submit', function (e) {
    e.preventDefault();
    const form = $(this);
    const platform = form.data('platform');
    const payload = csrfData({ platform: platform });
    form.serializeArray().forEach(f => { payload[f.name] = f.value; });

    form.find('button[type=submit]').prop('disabled', true);
    $.post(BASE + '/' + CID + '/metrics', payload)
        .done(res => {
            if (res.success) {
                showToast('Metrik tersimpan' + (res.er !== null ? ' — ER ' + res.er + '% (' + res.verdict + ').' : '.'), 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message, 'error');
            }
        })
        .fail(() => showToast('Gagal menyimpan metrik.', 'error'))
        .always(() => form.find('button[type=submit]').prop('disabled', false));
});
</script>
<?= $this->endSection() ?>
