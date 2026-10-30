<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('tgl_id')) {
    /**
     * Format tanggal/waktu ke Bahasa Indonesia memakai ICU (IntlDateFormatter).
     *
     * Contoh:
     *   tgl_id('2026-10-05')                    -> "05 Oktober 2026"
     *   tgl_id('2026-10-05', 'EEEE, dd MMMM yyyy') -> "Senin, 05 Oktober 2026"
     *   tgl_id(null, 'MMMM yyyy')                -> nama bulan + tahun saat ini ("Oktober 2026")
     *
     * Jika ekstensi intl tidak tersedia, fallback ke date() biasa (Bahasa Inggris).
     *
     * @param string|int|null $date  'Y-m-d', 'Y-m-d H:i:s', timestamp, atau null (= sekarang)
     * @param string          $pattern pola ICU, mis. 'dd MMM yyyy', 'EEEE, dd MMMM yyyy HH.mm'
     */
    function tgl_id($date = null, string $pattern = 'dd MMMM yyyy'): string
    {
        try {
            $ts = $date === null ? time() : (is_numeric($date) ? (int) $date : strtotime((string) $date));
            if ($ts === false) {
                return (string) $date;
            }
            if (! class_exists(\IntlDateFormatter::class)) {
                return date('d M Y', $ts);
            }
            $fmt = new \IntlDateFormatter('id_ID', \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, 'Asia/Jakarta');
            $fmt->setPattern($pattern);

            return $fmt->format($ts);
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }
}
