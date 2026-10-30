<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\SosmedCommentModel;
use App\Models\SosmedContentModel;

/**
 * Modul Social Media — workflow konten tim.
 * Alur: SUBMITTED → (review) → NEEDS_REVISION / APPROVED → PUBLISHED.
 */
class SosmedController extends BaseController
{
    // ---------------- DAFTAR KONTEN ----------------

    public function index()
    {
        $model = new SosmedContentModel();

        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status') ?: null;
        $type   = $this->request->getGet('type') ?: null;

        $data = [
            'title'       => 'Konten Social Media',
            'breadcrumbs' => [
                ['label' => 'Social Media'],
                ['label' => 'Konten'],
            ],
            'contents'   => $model->listContents($search, $status, $type),
            'countByStatus' => $model->countByStatus(),
            'statusLabels'  => SosmedContentModel::STATUS_LABELS,
            'statusColors'  => SosmedContentModel::STATUS_COLORS,
            'search'     => $search,
            'status'     => $status,
            'type'       => $type,
        ];

        if ($this->request->isAJAX()) {
            return view('sosmed/_table', $data);
        }

        return view('sosmed/index', $data);
    }

    // ---------------- SUBMIT KONTEN BARU ----------------

    public function store()
    {
        $model = new SosmedContentModel();

        $title = trim((string) $this->request->getPost('title'));
        $type  = $this->request->getPost('type');

        if ($title === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Judul konten wajib diisi.']);
        }

        if (! in_array($type, ['FEED', 'VIDEO'], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tipe konten tidak valid.']);
        }

        $data = [
            'title'         => $title,
            'type'          => $type,
            'created_by'    => session()->get('user_id'),
            'caption_draft' => $this->request->getPost('caption_draft'),
            'status'        => 'SUBMITTED',
        ];

        if ($type === 'FEED') {
            $canvaLink = trim((string) $this->request->getPost('canva_link'));
            if ($canvaLink === '') {
                return $this->response->setJSON(['success' => false, 'message' => 'Link Canva wajib diisi untuk konten Feeds.']);
            }
            $data['canva_link'] = $canvaLink;
        } else {
            // VIDEO: upload file
            $video = $this->request->getFile('video');
            if (! $video || ! $video->isValid() || $video->hasMoved()) {
                return $this->response->setJSON(['success' => false, 'message' => 'File video wajib diupload.']);
            }

            $ext = strtolower($video->getExtension());
            if (! in_array($ext, ['mp4', 'mov', 'webm'], true)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Format video harus MP4/MOV/WebM.']);
            }

            if ($video->getSize() > 100 * 1024 * 1024) {
                return $this->response->setJSON(['success' => false, 'message' => 'Ukuran video maksimal 100MB.']);
            }

            $dir = 'uploads/sosmed/';
            if (! is_dir(WRITEPATH . $dir)) {
                mkdir(WRITEPATH . $dir, 0755, true);
            }

            $fileName = 'sosmed_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $video->move(WRITEPATH . $dir, $fileName);
            $data['video_path'] = $dir . $fileName;
        }

        if (! $model->insert($data)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan.', 'errors' => $model->errors()]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Konten berhasil disubmit untuk review.', 'id' => $model->getInsertID()]);
    }

    // ---------------- DETAIL + THREAD KOMENTAR ----------------

    public function show(int $id)
    {
        $model         = new SosmedContentModel();
        $commentModel  = new SosmedCommentModel();

        $content = $model->detail($id);

        if (! $content) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $data = [
            'title'       => 'Detail Konten',
            'breadcrumbs' => [
                ['label' => 'Social Media'],
                ['label' => 'Konten', 'url' => base_url('admin/sosmed')],
                ['label' => 'Detail'],
            ],
            'content'      => $content,
            'comments'     => $commentModel->thread($id),
            'metrics'      => (new \App\Models\SosmedMetricModel())->forContent($id),
            'statusLabels' => SosmedContentModel::STATUS_LABELS,
            'statusColors' => SosmedContentModel::STATUS_COLORS,
            'isOwner'      => (int) $content['created_by'] === (int) session()->get('user_id'),
        ];

        return view('sosmed/show', $data);
    }

    // ---------------- KOMENTAR / BALASAN ----------------

    public function comment(int $id)
    {
        $model = new SosmedContentModel();

        if (! $model->find($id)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Konten tidak ditemukan.']);
        }

        $message = trim((string) $this->request->getPost('message'));

        if ($message === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Komentar tidak boleh kosong.']);
        }

        $commentModel = new SosmedCommentModel();
        db_connect()->table('sosmed_comments')->insert([
            'content_id' => $id,
            'user_id'    => session()->get('user_id'),
            'message'    => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true, 'message' => 'Komentar terkirim.']);
    }

    // ---------------- REVIEW: ACC / MINTA REVISI ----------------

    public function review(int $id)
    {
        $model   = new SosmedContentModel();
        $content = $model->find($id);

        if (! $content) {
            return $this->response->setJSON(['success' => false, 'message' => 'Konten tidak ditemukan.']);
        }

        $action = $this->request->getPost('action'); // approve | revise | reject
        $notes  = trim((string) $this->request->getPost('notes'));

        if ($action === 'approve') {
            if (! in_array($content['status'], ['SUBMITTED', 'NEEDS_REVISION'], true)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Status konten tidak bisa di-approve.']);
            }

            $model->update($id, ['status' => 'APPROVED']);

            if ($notes !== '') {
                db_connect()->table('sosmed_comments')->insert([
                    'content_id' => $id,
                    'user_id'    => session()->get('user_id'),
                    'message'    => '[APPROVED] ' . $notes,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Konten disetujui. Tim bisa upload ke IG & TikTok.']);
        }

        if ($action === 'revise') {
            if ($notes === '') {
                return $this->response->setJSON(['success' => false, 'message' => 'Wajib isi notes revisi.']);
            }

            $model->update($id, ['status' => 'NEEDS_REVISION']);
            db_connect()->table('sosmed_comments')->insert([
                'content_id' => $id,
                'user_id'    => session()->get('user_id'),
                'message'    => '[REVISI] ' . $notes,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true, 'message' => 'Permintaan revisi terkirim ke tim.']);
        }

        if ($action === 'reject') {
            if ($notes === '') {
                return $this->response->setJSON(['success' => false, 'message' => 'Wajib isi alasan reject.']);
            }

            $model->update($id, ['status' => 'REJECTED']);
            db_connect()->table('sosmed_comments')->insert([
                'content_id' => $id,
                'user_id'    => session()->get('user_id'),
                'message'    => '[REJECTED] ' . $notes,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true, 'message' => 'Konten di-reject dan dikembalikan ke tim.']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Aksi tidak dikenal.']);
    }

    // ---------------- RESUBMIT REVISI (update link/file) ----------------

    public function resubmit(int $id)
    {
        $model   = new SosmedContentModel();
        $content = $model->find($id);

        if (! $content) {
            return $this->response->setJSON(['success' => false, 'message' => 'Konten tidak ditemukan.']);
        }

        if (! in_array($content['status'], ['NEEDS_REVISION', 'REJECTED'], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Konten tidak dalam status revisi/ditolak.']);
        }

        $data = ['status' => 'SUBMITTED'];

        if ($content['type'] === 'FEED') {
            $canvaLink = trim((string) $this->request->getPost('canva_link'));
            if ($canvaLink !== '') {
                $data['canva_link'] = $canvaLink;
            }
        } else {
            $video = $this->request->getFile('video');
            if ($video && $video->isValid() && ! $video->hasMoved()) {
                $ext = strtolower($video->getExtension());
                if (! in_array($ext, ['mp4', 'mov', 'webm'], true)) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Format video harus MP4/MOV/WebM.']);
                }
                if ($video->getSize() > 100 * 1024 * 1024) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Ukuran video maksimal 100MB.']);
                }

                $dir = 'uploads/sosmed/';
                if (! is_dir(WRITEPATH . $dir)) {
                    mkdir(WRITEPATH . $dir, 0755, true);
                }

                // Hapus video lama
                if (! empty($content['video_path']) && is_file(WRITEPATH . $content['video_path'])) {
                    @unlink(WRITEPATH . $content['video_path']);
                }

                $fileName = 'sosmed_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $video->move(WRITEPATH . $dir, $fileName);
                $data['video_path'] = $dir . $fileName;
            }
        }

        $caption = $this->request->getPost('caption_draft');
        if ($caption !== null) {
            $data['caption_draft'] = $caption;
        }

        $model->update($id, $data);

        db_connect()->table('sosmed_comments')->insert([
            'content_id' => $id,
            'user_id'    => session()->get('user_id'),
            'message'    => '[RESUBMIT] Revisi sudah diupload, siap direview ulang.',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true, 'message' => 'Revisi berhasil disubmit ulang.']);
    }

    // ---------------- INPUT LINK PUBLISH ----------------

    public function publish(int $id)
    {
        $model   = new SosmedContentModel();
        $content = $model->find($id);

        if (! $content) {
            return $this->response->setJSON(['success' => false, 'message' => 'Konten tidak ditemukan.']);
        }

        if ($content['status'] !== 'APPROVED') {
            return $this->response->setJSON(['success' => false, 'message' => 'Konten belum disetujui.']);
        }

        $igLink     = trim((string) $this->request->getPost('ig_link'));
        $tiktokLink = trim((string) $this->request->getPost('tiktok_link'));

        if ($igLink === '' && $tiktokLink === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Isi minimal salah satu link (IG atau TikTok).']);
        }

        $model->update($id, [
            'ig_link'      => $igLink ?: null,
            'tiktok_link'  => $tiktokLink ?: null,
            'status'       => 'PUBLISHED',
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true, 'message' => 'Link publikasi tersimpan.']);
    }

    // ---------------- DASHBOARD KPI ----------------

    public function dashboard()
    {
        $year  = (int) ($this->request->getGet('year') ?: date('Y'));
        $month = (int) ($this->request->getGet('month') ?: date('m'));

        $contentModel = new SosmedContentModel();
        $targetModel  = new \App\Models\SosmedTargetModel();

        $target = $targetModel->activeAt(sprintf('%04d-%02d-01', $year, $month));
        $feedsPerWeek  = $target ? (int) $target['feeds_per_week'] : 2;
        $videosPerWeek = $target ? (int) $target['videos_per_week'] : 1;

        // Jumlah minggu (ISO) yang menyentuh bulan ini
        $weeks = [];
        $days  = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        for ($d = 1; $d <= $days; $d++) {
            $weeks[date('oW', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d)))] = true;
        }
        $numWeeks = count($weeks);

        $targetFeeds  = $feedsPerWeek * $numWeeks;
        $targetVideos = $videosPerWeek * $numWeeks;

        $stats = $contentModel->monthlyStats($year, $month);

        // Lengkapi kreator yang belum ada konten bulan ini (biar muncul 0)
        $creators = $contentModel->select('sosmed_contents.created_by, users.full_name AS name')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left')
            ->groupBy('sosmed_contents.created_by')
            ->findAll();
        foreach ($creators as $cr) {
            $cid = (int) $cr['created_by'];
            if (! isset($stats[$cid])) {
                $stats[$cid] = ['name' => $cr['name'] ?? '-', 'FEED' => 0, 'VIDEO' => 0];
            }
        }

        $totalFeeds = 0;
        $totalVideos = 0;
        $rows = [];
        foreach ($stats as $cid => $s) {
            $achFeed  = $targetFeeds > 0 ? round($s['FEED'] / $targetFeeds * 100) : 0;
            $achVideo = $targetVideos > 0 ? round($s['VIDEO'] / $targetVideos * 100) : 0;
            $achTotal = ($targetFeeds + $targetVideos) > 0
                ? round(($s['FEED'] + $s['VIDEO']) / ($targetFeeds + $targetVideos) * 100)
                : 0;
            $rows[] = [
                'name' => $s['name'], 'feeds' => $s['FEED'], 'videos' => $s['VIDEO'],
                'achFeed' => $achFeed, 'achVideo' => $achVideo, 'achTotal' => $achTotal,
                'status' => $achTotal >= 100 ? 'Tercapai' : ($achTotal >= 70 ? 'Hampir' : 'Kurang'),
            ];
            $totalFeeds  += $s['FEED'];
            $totalVideos += $s['VIDEO'];
        }
        usort($rows, fn ($a, $b) => $b['achTotal'] <=> $a['achTotal']);

        $metricModel = new \App\Models\SosmedMetricModel();

        $data = [
            'title'       => 'Dashboard KPI Sosmed',
            'breadcrumbs' => [
                ['label' => 'Social Media'],
                ['label' => 'Dashboard KPI'],
            ],
            'year' => $year, 'month' => $month,
            'monthName' => $this->monthName($month),
            'targetFeeds' => $targetFeeds, 'targetVideos' => $targetVideos,
            'targetTotal' => $targetFeeds + $targetVideos,
            'numWeeks' => $numWeeks,
            'feedsPerWeek' => $feedsPerWeek, 'videosPerWeek' => $videosPerWeek,
            'totalFeeds' => $totalFeeds, 'totalVideos' => $totalVideos,
            'rows' => $rows,
            'weekly' => $contentModel->weeklyBreakdown($year, $month),
            'target' => $target,
            'erByCreator' => $metricModel->monthlyErByCreator($year, $month),
            'topBottom' => $metricModel->topBottom($year, $month, 5),
        ];

        return view('sosmed/dashboard', $data);
    }

    // ---------------- TIMELINE ----------------

    public function timeline()
    {
        $contentModel = new SosmedContentModel();
        $creatorId    = $this->request->getGet('creator') ? (int) $this->request->getGet('creator') : null;

        $creators = $contentModel->select('sosmed_contents.created_by AS id, users.full_name AS name')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left')
            ->groupBy('sosmed_contents.created_by')
            ->findAll();

        $data = [
            'title'       => 'Timeline Aktivitas',
            'breadcrumbs' => [
                ['label' => 'Social Media'],
                ['label' => 'Timeline'],
            ],
            'grouped'  => $contentModel->timeline(60, $creatorId),
            'creators' => $creators,
            'creator'  => $creatorId,
        ];

        return view('sosmed/timeline', $data);
    }

    // ---------------- REPORT BULANAN ----------------

    public function report()
    {
        $year  = (int) ($this->request->getGet('year') ?: date('Y'));
        $month = (int) ($this->request->getGet('month') ?: date('m'));

        $contentModel = new SosmedContentModel();
        $targetModel  = new \App\Models\SosmedTargetModel();

        $target = $targetModel->activeAt(sprintf('%04d-%02d-01', $year, $month));
        $feedsPerWeek  = $target ? (int) $target['feeds_per_week'] : 2;
        $videosPerWeek = $target ? (int) $target['videos_per_week'] : 1;

        $weeks = [];
        $days  = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        for ($d = 1; $d <= $days; $d++) {
            $weeks[date('oW', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d)))] = true;
        }
        $numWeeks = count($weeks);

        $stats = $contentModel->monthlyStats($year, $month);
        $published = $contentModel->publishedInMonth($year, $month);

        // Tempel metrik + ER ke tiap konten terbit
        $metricModel = new \App\Models\SosmedMetricModel();
        foreach ($published as &$p) {
            $p['metrics'] = $metricModel->forContent((int) $p['id']);
        }
        unset($p);

        $data = [
            'title'       => 'Report Bulanan Sosmed',
            'breadcrumbs' => [
                ['label' => 'Social Media'],
                ['label' => 'Report Bulanan'],
            ],
            'year' => $year, 'month' => $month,
            'monthName' => $this->monthName($month),
            'targetFeeds' => $feedsPerWeek * $numWeeks,
            'targetVideos' => $videosPerWeek * $numWeeks,
            'numWeeks' => $numWeeks,
            'feedsPerWeek' => $feedsPerWeek, 'videosPerWeek' => $videosPerWeek,
            'stats' => $stats,
            'published' => $published,
            'target' => $target,
        ];

        return view('sosmed/report', $data);
    }

    // ---------------- PENGATURAN TARGET (khusus Admin) ----------------

    public function targets()
    {
        if (! \App\Helpers\AdminGuard::isAdmin()) {
            return redirect()->to(base_url('admin/sosmed/dashboard'))->with('error', 'Hanya admin yang bisa akses Pengaturan Target.');
        }

        $targetModel = new \App\Models\SosmedTargetModel();

        $data = [
            'title'       => 'Pengaturan Target KPI',
            'breadcrumbs' => [
                ['label' => 'Social Media'],
                ['label' => 'Pengaturan Target'],
            ],
            'history' => $targetModel->history(),
            'active'  => $targetModel->activeAt(date('Y-m-d')),
        ];

        return view('sosmed/targets', $data);
    }

    public function saveTarget()
    {
        if (! \App\Helpers\AdminGuard::isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Akses ditolak.'])->setStatusCode(403);
        }

        $feeds  = (int) $this->request->getPost('feeds_per_week');
        $videos = (int) $this->request->getPost('videos_per_week');
        $from   = $this->request->getPost('effective_from');

        if ($feeds < 0 || $videos < 0 || ($feeds + $videos) === 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Target tidak valid.']);
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tanggal berlaku tidak valid.']);
        }

        // Upsert: kalau sudah ada target dengan tanggal berlaku yang sama, update. Kalau belum, insert baru.
        $db = db_connect();
        $existing = $db->table('sosmed_targets')->where('effective_from', $from)->get()->getRowArray();

        if ($existing) {
            $db->table('sosmed_targets')->where('id', $existing['id'])->update([
                'feeds_per_week'  => $feeds,
                'videos_per_week' => $videos,
            ]);
            $message = 'Target berhasil diupdate.';
        } else {
            $db->table('sosmed_targets')->insert([
                'feeds_per_week'  => $feeds,
                'videos_per_week' => $videos,
                'effective_from'  => $from,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            $message = 'Target baru tersimpan.';
        }

        return $this->response->setJSON(['success' => true, 'message' => $message]);
    }

    public function deleteTarget(int $id)
    {
        if (! \App\Helpers\AdminGuard::isAdmin()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Akses ditolak.'])->setStatusCode(403);
        }

        $db = db_connect();

        // Jangan boleh hapus kalau cuma sisa 1 target
        $count = $db->table('sosmed_targets')->countAllResults();
        if ($count <= 1) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak bisa hapus. Minimal harus ada 1 target.']);
        }

        $db->table('sosmed_targets')->where('id', $id)->delete();

        return $this->response->setJSON(['success' => true, 'message' => 'Target berhasil dihapus.']);
    }

    // ---------------- SIMPAN METRIK PERFORMA ----------------

    public function saveMetrics(int $id)
    {
        $model   = new SosmedContentModel();
        $content = $model->find($id);

        if (! $content) {
            return $this->response->setJSON(['success' => false, 'message' => 'Konten tidak ditemukan.']);
        }

        if ($content['status'] !== 'PUBLISHED') {
            return $this->response->setJSON(['success' => false, 'message' => 'Metrik hanya untuk konten yang sudah terbit.']);
        }

        $platform = strtoupper((string) $this->request->getPost('platform'));
        if (! in_array($platform, ['IG', 'TIKTOK'], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Platform tidak valid.']);
        }

        $metricModel = new \App\Models\SosmedMetricModel();
        $ok = $metricModel->saveMetrics($id, $platform, [
            'views'    => $this->request->getPost('views'),
            'reach'    => $this->request->getPost('reach'),
            'likes'    => $this->request->getPost('likes'),
            'comments' => $this->request->getPost('comments'),
            'shares'   => $this->request->getPost('shares'),
            'saves'    => $this->request->getPost('saves'),
        ]);

        if (! $ok) {
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal menyimpan metrik.']);
        }

        $saved = $metricModel->where('content_id', $id)->where('platform', $platform)->first();
        $calc  = \App\Models\SosmedMetricModel::engagement($saved);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Metrik tersimpan.',
            'er'      => $calc['er'],
            'verdict' => $calc['verdict'],
        ]);
    }

    private function monthName(int $m): string
    {
        $names = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $names[$m] ?? '';
    }

    // ---------------- STREAM VIDEO ----------------

    public function video(int $id)
    {
        $content = (new SosmedContentModel())->find($id);

        if (! $content || empty($content['video_path'])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $path = WRITEPATH . $content['video_path'];

        if (! is_file($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $mime = mime_content_type($path) ?: 'video/mp4';

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Accept-Ranges', 'bytes')
            ->setBody(file_get_contents($path));
    }
}
