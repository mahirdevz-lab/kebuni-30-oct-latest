<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Pager extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Templates
     * --------------------------------------------------------------------------
     *
     * Pagination links are rendered out using views to configure their
     * appearance. This array contains aliases and the view names to
     * use when rendering the links.
     *
     * Within each view, the Pager object will be available as $pager,
     * and the desired group as $pagerGroup;
     *
     * @var array<string, string>
     */
    public array $templates = [
        // Template kustom Kebuni: First/Last + Prev/Next + angka.
        // Semua link memakai query string (?page=N) sehingga cocok dengan
        // handler AJAX loadTable() yang membaca url.searchParams.get('page').
        // (Catatan: CI4 4.7 di repo ini tidak mengenal properti
        // usePageQueryString — default segment=0 sudah menghasilkan link
        // query string dan addQuery() mempertahankan filter yang ada.)
        'default_full'   => 'App\Views\pager\kebuni_full',
        'default_simple' => 'CodeIgniter\Pager\Views\default_simple',
        'default_head'   => 'CodeIgniter\Pager\Views\default_head',
    ];

    /**
     * --------------------------------------------------------------------------
     * Items Per Page
     * --------------------------------------------------------------------------
     *
     * The default number of results shown in a single page.
     * Disamakan dengan default mayoritas Service (10) — lihat audit pagination §3.3.
     */
    public int $perPage = 10;
}
