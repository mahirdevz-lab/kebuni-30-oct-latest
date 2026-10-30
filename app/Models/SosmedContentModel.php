<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Konten sosmed tim (workflow: submit → review → revisi → approve → publish).
 */
class SosmedContentModel extends Model
{
    protected $table            = 'sosmed_contents';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'title', 'type', 'created_by', 'canva_link', 'video_path', 'caption_draft',
        'status', 'ig_link', 'tiktok_link', 'published_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public const STATUS_LABELS = [
        'DRAFT'          => 'Draft',
        'SUBMITTED'      => 'Menunggu Review',
        'NEEDS_REVISION' => 'Perlu Revisi',
        'APPROVED'       => 'Disetujui',
        'REJECTED'       => 'Ditolak',
        'PUBLISHED'      => 'Terbit',
    ];

    public const STATUS_COLORS = [
        'DRAFT'          => 'secondary',
        'SUBMITTED'      => 'warning',
        'NEEDS_REVISION' => 'danger',
        'APPROVED'       => 'info',
        'REJECTED'       => 'dark',
        'PUBLISHED'      => 'success',
    ];

    /**
     * Daftar konten dengan nama pembuat.
     */
    public function listContents(?string $search = null, ?string $status = null, ?string $type = null, ?int $creatorId = null): array
    {
        $builder = $this->select('sosmed_contents.*, users.full_name AS creator_name, users.username AS creator_username')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left')
            ->orderBy('sosmed_contents.updated_at', 'DESC');

        if ($search) {
            $builder->groupStart()
                ->like('sosmed_contents.title', $search)
                ->orLike('users.full_name', $search)
                ->groupEnd();
        }

        if ($status) {
            $builder->where('sosmed_contents.status', $status);
        }

        if ($type) {
            $builder->where('sosmed_contents.type', $type);
        }

        if ($creatorId) {
            $builder->where('sosmed_contents.created_by', $creatorId);
        }

        return $builder->findAll();
    }

    /**
     * Detail konten + nama pembuat.
     */
    public function detail(int $id): ?array
    {
        return $this->select('sosmed_contents.*, users.full_name AS creator_name')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left')
            ->where('sosmed_contents.id', $id)
            ->first();
    }

    /**
     * Hitung konten per status (untuk badge/ringkasan).
     */
    public function countByStatus(): array
    {
        $rows = $this->select('status, COUNT(*) AS total')
            ->groupBy('status')
            ->findAll();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['total'];
        }

        return $out;
    }

    /**
     * Statistik per kreator per tipe untuk satu bulan (status PUBLISHED).
     * Return: [creator_id => ['name' => ..., 'FEED' => n, 'VIDEO' => n]]
     */
    public function monthlyStats(int $year, int $month): array
    {
        $rows = $this->select('sosmed_contents.created_by, users.full_name AS creator_name, sosmed_contents.type, COUNT(*) AS total')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left')
            ->where('sosmed_contents.status', 'PUBLISHED')
            ->where('YEAR(sosmed_contents.published_at)', $year)
            ->where('MONTH(sosmed_contents.published_at)', $month)
            ->groupBy('sosmed_contents.created_by, sosmed_contents.type')
            ->findAll();

        $out = [];
        foreach ($rows as $r) {
            $cid = (int) $r['created_by'];
            if (! isset($out[$cid])) {
                $out[$cid] = ['name' => $r['creator_name'] ?? '-', 'FEED' => 0, 'VIDEO' => 0];
            }
            $out[$cid][$r['type']] = (int) $r['total'];
        }

        return $out;
    }

    /**
     * Breakdown mingguan satu bulan: [label_minggu => ['FEED' => n, 'VIDEO' => n]].
     */
    public function weeklyBreakdown(int $year, int $month): array
    {
        $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $out  = [];

        for ($d = 1; $d <= $days; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $week = date('oW', strtotime($date));
            if (! isset($out[$week])) {
                // Label: rentang tanggal minggu tsb di bulan ini
                $out[$week] = ['label' => '', 'FEED' => 0, 'VIDEO' => 0, 'dates' => []];
            }
            $out[$week]['dates'][] = $date;
        }

        foreach ($out as $week => &$w) {
            $first = reset($w['dates']);
            $last  = end($w['dates']);
            $w['label'] = date('d', strtotime($first)) . '–' . date('d M', strtotime($last));
            unset($w['dates']);
        }
        unset($w);

        $rows = $this->select("CONCAT(YEAR(sosmed_contents.published_at), LPAD(WEEK(sosmed_contents.published_at, 3), 2, '0')) AS yw, sosmed_contents.type, COUNT(*) AS total")
            ->where('sosmed_contents.status', 'PUBLISHED')
            ->where('YEAR(sosmed_contents.published_at)', $year)
            ->where('MONTH(sosmed_contents.published_at)', $month)
            ->groupBy('yw, sosmed_contents.type')
            ->findAll();

        foreach ($rows as $r) {
            if (isset($out[$r['yw']])) {
                $out[$r['yw']][$r['type']] = (int) $r['total'];
            }
        }

        return array_values($out);
    }

    /**
     * Timeline aktivitas: submit, komentar/review, publish.
     */
    public function timeline(?int $limit = 50, ?int $creatorId = null): array
    {
        $items = [];

        // 1. Submit konten
        $b = $this->select('sosmed_contents.id, sosmed_contents.title, sosmed_contents.type, sosmed_contents.created_by, users.full_name AS creator_name, sosmed_contents.created_at AS at')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left');
        if ($creatorId) {
            $b->where('sosmed_contents.created_by', $creatorId);
        }
        foreach ($b->orderBy('sosmed_contents.created_at', 'DESC')->limit($limit)->findAll() as $r) {
            $items[] = [
                'at'      => $r['at'],
                'icon'    => 'fa-plus',
                'color'   => 'primary',
                'text'    => ($r['creator_name'] ?? 'Tim') . ' submit konten ' . ($r['type'] === 'FEED' ? 'Feeds' : 'Video'),
                'title'   => $r['title'],
                'content_id' => (int) $r['id'],
            ];
        }

        // 2. Komentar / review notes
        $cb = (new \App\Models\SosmedCommentModel())
            ->select('sosmed_comments.*, users.full_name AS author_name, sosmed_contents.title, sosmed_contents.created_by')
            ->join('users', 'users.id = sosmed_comments.user_id', 'left')
            ->join('sosmed_contents', 'sosmed_contents.id = sosmed_comments.content_id', 'left');
        if ($creatorId) {
            $cb->where('sosmed_contents.created_by', $creatorId);
        }
        foreach ($cb->orderBy('sosmed_comments.created_at', 'DESC')->limit($limit)->findAll() as $r) {
            $msg = $r['message'];
            if (str_starts_with($msg, '[REVISI]')) {
                $icon = 'fa-rotate'; $color = 'warning'; $text = ($r['author_name'] ?? 'Reviewer') . ' minta revisi';
            } elseif (str_starts_with($msg, '[APPROVED]')) {
                $icon = 'fa-check'; $color = 'success'; $text = ($r['author_name'] ?? 'Reviewer') . ' approve konten';
            } elseif (str_starts_with($msg, '[REJECTED]')) {
                $icon = 'fa-xmark'; $color = 'dark'; $text = ($r['author_name'] ?? 'Reviewer') . ' reject konten';
            } elseif (str_starts_with($msg, '[RESUBMIT]')) {
                $icon = 'fa-upload'; $color = 'info'; $text = ($r['author_name'] ?? 'Tim') . ' kirim revisi ulang';
            } else {
                $icon = 'fa-comment'; $color = 'secondary'; $text = ($r['author_name'] ?? 'User') . ' berkomentar';
            }
            $items[] = [
                'at'      => $r['created_at'],
                'icon'    => $icon,
                'color'   => $color,
                'text'    => $text,
                'title'   => $r['title'],
                'content_id' => (int) $r['content_id'],
            ];
        }

        // 3. Publish
        $pb = $this->select('sosmed_contents.id, sosmed_contents.title, sosmed_contents.type, users.full_name AS creator_name, sosmed_contents.published_at AS at')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left')
            ->where('sosmed_contents.status', 'PUBLISHED');
        if ($creatorId) {
            $pb->where('sosmed_contents.created_by', $creatorId);
        }
        foreach ($pb->orderBy('sosmed_contents.published_at', 'DESC')->limit($limit)->findAll() as $r) {
            $items[] = [
                'at'      => $r['at'],
                'icon'    => 'fa-rocket',
                'color'   => 'success',
                'text'    => ($r['creator_name'] ?? 'Tim') . ' terbitkan konten',
                'title'   => $r['title'],
                'content_id' => (int) $r['id'],
            ];
        }

        // Urutkan gabungan by waktu DESC
        usort($items, fn ($a, $b) => strcmp($b['at'] ?? '', $a['at'] ?? ''));
        $items = array_slice($items, 0, $limit);

        // Kelompokkan per tanggal
        $grouped = [];
        foreach ($items as $it) {
            $day = $it['at'] ? date('Y-m-d', strtotime($it['at'])) : 'unknown';
            $grouped[$day][] = $it;
        }

        return $grouped;
    }

    /**
     * Daftar konten terbit satu bulan (untuk report).
     */
    public function publishedInMonth(int $year, int $month): array
    {
        return $this->select('sosmed_contents.*, users.full_name AS creator_name')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left')
            ->where('sosmed_contents.status', 'PUBLISHED')
            ->where('YEAR(sosmed_contents.published_at)', $year)
            ->where('MONTH(sosmed_contents.published_at)', $month)
            ->orderBy('sosmed_contents.published_at', 'DESC')
            ->findAll();
    }
}
