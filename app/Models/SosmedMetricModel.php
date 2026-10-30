<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Metrik performa konten per platform (diisi manual dari IG Insights / TikTok Studio).
 * Menghitung engagement rate + vonis berdasarkan benchmark riset 2026-10-19.
 */
class SosmedMetricModel extends Model
{
    protected $table            = 'sosmed_metrics';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'content_id', 'platform', 'views', 'reach', 'likes',
        'comments', 'shares', 'saves', 'recorded_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Benchmark ER dari riset (Okt 2026).
     * IG: by reach, target >= 3%. TikTok: by views, target >= 4%.
     */
    public const ER_TARGETS = [
        'IG'     => 3.0,
        'TIKTOK' => 4.0,
    ];

    /**
     * Hitung engagement rate + vonis untuk satu baris metrik.
     * Return: ['er' => float|null, 'verdict' => 'bagus'|'cukup'|'kurang'|null, 'label' => string]
     */
    public static function engagement(array $m): array
    {
        $platform = $m['platform'] ?? 'IG';
        $likes    = (int) ($m['likes'] ?? 0);
        $comments = (int) ($m['comments'] ?? 0);
        $shares   = (int) ($m['shares'] ?? 0);
        $saves    = (int) ($m['saves'] ?? 0);

        if ($platform === 'IG') {
            $reach = (int) ($m['reach'] ?? 0);
            if ($reach <= 0) {
                return ['er' => null, 'verdict' => null, 'label' => 'by reach'];
            }
            $er = ($likes + $comments + $shares + $saves) / $reach * 100;

            return [
                'er'      => round($er, 2),
                'verdict' => $er >= 3 ? 'bagus' : ($er >= 1.5 ? 'cukup' : 'kurang'),
                'label'   => 'by reach',
            ];
        }

        // TIKTOK: by views, tanpa saves
        $views = (int) ($m['views'] ?? 0);
        if ($views <= 0) {
            return ['er' => null, 'verdict' => null, 'label' => 'by views'];
        }
        $er = ($likes + $comments + $shares) / $views * 100;

        return [
            'er'      => round($er, 2),
            'verdict' => $er >= 4 ? 'bagus' : ($er >= 2 ? 'cukup' : 'kurang'),
            'label'   => 'by views',
        ];
    }

    /**
     * Ambil metrik satu konten: ['IG' => [...], 'TIKTOK' => [...]] (+ hasil ER).
     */
    public function forContent(int $contentId): array
    {
        $rows = $this->where('content_id', $contentId)->findAll();
        $out  = [];
        foreach ($rows as $r) {
            $r['calc'] = self::engagement($r);
            $out[$r['platform']] = $r;
        }

        return $out;
    }

    /**
     * Simpan/update metrik (upsert per content_id + platform).
     */
    public function saveMetrics(int $contentId, string $platform, array $data): bool
    {
        $db = db_connect();
        $existing = $db->table($this->table)
            ->where('content_id', $contentId)
            ->where('platform', $platform)
            ->get()->getRowArray();

        $payload = [
            'views'       => max(0, (int) ($data['views'] ?? 0)),
            'reach'       => max(0, (int) ($data['reach'] ?? 0)),
            'likes'       => max(0, (int) ($data['likes'] ?? 0)),
            'comments'    => max(0, (int) ($data['comments'] ?? 0)),
            'shares'      => max(0, (int) ($data['shares'] ?? 0)),
            'saves'       => max(0, (int) ($data['saves'] ?? 0)),
            'recorded_at' => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            return $db->table($this->table)->where('id', $existing['id'])->update($payload);
        }

        $payload['content_id'] = $contentId;
        $payload['platform']   = $platform;
        $payload['created_at'] = date('Y-m-d H:i:s');

        return $db->table($this->table)->insert($payload);
    }

    /**
     * Rata-rata ER per kreator per bulan (untuk dashboard).
     * Return: [creator_id => ['name' =>, 'ig_er' => avg|null, 'tt_er' => avg|null, 'n' => count]]
     */
    public function monthlyErByCreator(int $year, int $month): array
    {
        $rows = $this->select('sosmed_contents.created_by, users.full_name AS creator_name, sosmed_metrics.*')
            ->join('sosmed_contents', 'sosmed_contents.id = sosmed_metrics.content_id')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left')
            ->where('sosmed_contents.status', 'PUBLISHED')
            ->where('YEAR(sosmed_contents.published_at)', $year)
            ->where('MONTH(sosmed_contents.published_at)', $month)
            ->findAll();

        $agg = [];
        foreach ($rows as $r) {
            $cid = (int) $r['created_by'];
            if (! isset($agg[$cid])) {
                $agg[$cid] = ['name' => $r['creator_name'] ?? '-', 'ig' => [], 'tt' => []];
            }
            $calc = self::engagement($r);
            if ($calc['er'] !== null) {
                $key = $r['platform'] === 'IG' ? 'ig' : 'tt';
                $agg[$cid][$key][] = $calc['er'];
            }
        }

        $out = [];
        foreach ($agg as $cid => $a) {
            $out[$cid] = [
                'name'  => $a['name'],
                'ig_er' => $a['ig'] ? round(array_sum($a['ig']) / count($a['ig']), 2) : null,
                'tt_er' => $a['tt'] ? round(array_sum($a['tt']) / count($a['tt']), 2) : null,
                'n'     => count($a['ig']) + count($a['tt']),
            ];
        }

        return $out;
    }

    /**
     * Konten terbaik & terburuk bulan ini by ER (untuk dashboard).
     */
    public function topBottom(int $year, int $month, int $limit = 5): array
    {
        $rows = $this->select('sosmed_contents.id, sosmed_contents.title, sosmed_contents.type, users.full_name AS creator_name, sosmed_metrics.*')
            ->join('sosmed_contents', 'sosmed_contents.id = sosmed_metrics.content_id')
            ->join('users', 'users.id = sosmed_contents.created_by', 'left')
            ->where('sosmed_contents.status', 'PUBLISHED')
            ->where('YEAR(sosmed_contents.published_at)', $year)
            ->where('MONTH(sosmed_contents.published_at)', $month)
            ->findAll();

        $scored = [];
        foreach ($rows as $r) {
            $calc = self::engagement($r);
            if ($calc['er'] !== null) {
                $r['er'] = $calc['er'];
                $r['verdict'] = $calc['verdict'];
                $scored[] = $r;
            }
        }

        usort($scored, fn ($a, $b) => $b['er'] <=> $a['er']);

        return [
            'top'    => array_slice($scored, 0, $limit),
            'bottom' => array_slice(array_reverse($scored), 0, $limit),
        ];
    }
}
