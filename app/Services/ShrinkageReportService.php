<?php

namespace App\Services;

/**
 * Laporan penyusutan (PRD FR-11, AC "laporan membedakan ... penyusutan").
 *
 * - Known shrinkage   : Broken Stock yang disetujui (mutasi BROKEN).
 * - Unknown shrinkage : selisih opname fisik kurang (OPNAME_ADJUST keluar).
 * - Fisik lebih       : selisih opname fisik lebih (OPNAME_ADJUST masuk) - ditampilkan terpisah, bukan pengurang penyusutan.
 *
 * Nilai diambil dari stock_ledger (biaya moving average saat posting), bukan estimasi saat lapor.
 * Periode memakai waktu efektif mutasi (opname = waktu cut-off).
 */
class ShrinkageReportService
{
    public function build(string $from, string $to, ?int $productId = null): array
    {
        $db    = db_connect();
        $loc   = (new DailyClosingService())->locationId();
        $start = $from . ' 00:00:00';
        $end   = $to . ' 23:59:59';

        // --- Known: semua laporan Broken Stock pada periode (status apa pun), nilai resmi hanya untuk POSTED
        $b = $db->table('stock_broken_reports r')
            ->select('r.*, p.product_code, p.product_name, l.total_cost AS ledger_value, l.effective_at, ur.full_name AS reporter, ua.full_name AS approver')
            ->join('products p', 'p.id = r.product_id')
            ->join('stock_ledger l', 'l.id = r.ledger_id', 'left')
            ->join('users ur', 'ur.id = r.reported_by', 'left')->join('users ua', 'ua.id = r.approved_by', 'left')
            ->where('r.location_id', $loc)
            ->groupStart()
                ->groupStart()->where('r.status', 'POSTED')->where('l.effective_at >=', $start)->where('l.effective_at <=', $end)->groupEnd()
                ->orGroupStart()->where('r.status !=', 'POSTED')->where('r.reported_at >=', $start)->where('r.reported_at <=', $end)->groupEnd()
            ->groupEnd();
        if ($productId) {
            $b->where('r.product_id', $productId);
        }
        $known = $b->orderBy('r.reported_at', 'DESC')->get()->getResultArray();

        // --- Opname (unknown + fisik lebih) dari sesi yang sudah diposting
        $o = $db->table('stock_opname_lines ol')
            ->select('ol.*, s.session_no, s.cutoff_at, p.product_code, p.product_name, p.unit, l.total_cost AS ledger_value, ua.full_name AS approver')
            ->join('stock_opname_sessions s', 's.id = ol.session_id')
            ->join('products p', 'p.id = ol.product_id')
            ->join('stock_ledger l', 'l.id = ol.ledger_id')
            ->join('users ua', 'ua.id = s.approved_by', 'left')
            ->where('s.status', 'POSTED')->where('s.location_id', $loc)
            ->where('s.cutoff_at >=', $start)->where('s.cutoff_at <=', $end)
            ->where('ol.difference !=', 0);
        if ($productId) {
            $o->where('ol.product_id', $productId);
        }
        $opname  = $o->orderBy('s.cutoff_at', 'DESC')->get()->getResultArray();
        $unknown = array_values(array_filter($opname, static fn ($r) => (float) $r['difference'] > 0));
        $surplus = array_values(array_filter($opname, static fn ($r) => (float) $r['difference'] < 0));

        // --- HPP penjualan periode (pembanding rasio penyusutan)
        // HPP bersih: penjualan + gratis promo dikurangi retur pelanggan (biaya pokok asli).
        $c = $db->table('stock_ledger')->select("product_id, SUM(CASE WHEN movement_type = 'CUSTOMER_RETURN' THEN -total_cost ELSE total_cost END) AS cogs", false)
            ->where('location_id', $loc)->whereIn('movement_type', ['SALE', 'PROMO_FREE', 'CUSTOMER_RETURN'])->where('status', 'POSTED')
            ->where('effective_at >=', $start)->where('effective_at <=', $end);
        if ($productId) {
            $c->where('product_id', $productId);
        }
        $cogs = array_column($c->groupBy('product_id')->get()->getResultArray(), 'cogs', 'product_id');

        // --- Ringkasan per produk & per alasan
        $byProduct = [];
        $add = static function (array $r, string $key, float $qty, float $value) use (&$byProduct) {
            $pid = (int) $r['product_id'];
            $byProduct[$pid] ??= [
                'product_id' => $pid, 'product_code' => $r['product_code'], 'product_name' => $r['product_name'], 'unit' => $r['unit'],
                'known_qty' => 0.0, 'known_value' => 0.0, 'unknown_qty' => 0.0, 'unknown_value' => 0.0, 'surplus_qty' => 0.0, 'surplus_value' => 0.0,
            ];
            $byProduct[$pid][$key . '_qty']   += $qty;
            $byProduct[$pid][$key . '_value'] += $value;
        };
        $byReason = [];
        $pending  = ['count' => 0, 'value' => 0.0];
        foreach ($known as $r) {
            if ($r['status'] === 'POSTED') {
                $add($r, 'known', (float) $r['qty'], (float) $r['ledger_value']);
                $byReason[$r['reason_code']] = ($byReason[$r['reason_code']] ?? 0) + (float) $r['ledger_value'];
            } elseif ($r['status'] === 'DRAFT') {
                $pending['count']++;
                $pending['value'] += (float) $r['est_value'];
            }
        }
        foreach ($unknown as $r) {
            $add($r, 'unknown', (float) $r['difference'], (float) $r['ledger_value']);
        }
        foreach ($surplus as $r) {
            $add($r, 'surplus', -(float) $r['difference'], (float) $r['ledger_value']);
        }
        foreach ($byProduct as $pid => &$p) {
            $p['cogs']        = (float) ($cogs[$pid] ?? 0);
            $p['total_value'] = $p['known_value'] + $p['unknown_value'];
            // Rasio penyusutan terhadap (HPP terjual + penyusutan) = porsi persediaan keluar yang hilang.
            $base       = $p['cogs'] + $p['total_value'];
            $p['ratio'] = $base > 0 ? $p['total_value'] / $base * 100 : null;
        }
        unset($p);
        usort($byProduct, static fn ($a, $b) => $b['total_value'] <=> $a['total_value']);

        $totals = [
            'known'   => array_sum(array_column($byProduct, 'known_value')),
            'unknown' => array_sum(array_column($byProduct, 'unknown_value')),
            'surplus' => array_sum(array_column($byProduct, 'surplus_value')),
            'cogs'    => array_sum(array_map('floatval', $cogs)),
        ];
        $totals['total'] = $totals['known'] + $totals['unknown'];

        arsort($byReason);

        return compact('known', 'unknown', 'surplus', 'byProduct', 'byReason', 'totals', 'pending');
    }
}
