<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Menyiapkan database LATIHAN terpisah (<db>_training) berisi struktur yang sama + data master + produk/stok/akun contoh.
 * Database asli tidak disentuh. Jalankan aplikasi latihan dengan:  php spark training:serve
 *
 *   php spark training:setup [--reset]
 */
class TrainingSetup extends BaseCommand
{
    protected $group       = 'Kebuni POS';
    protected $name        = 'training:setup';
    protected $description = 'Buat database latihan terpisah (struktur + master + data contoh).';
    protected $usage       = 'training:setup [--reset]';

    /** Tabel yang datanya ikut disalin (master/pengaturan); sisanya hanya struktur kosong. */
    private const COPY = ['migrations', 'roles', 'permissions', 'role_permissions', 'settings', 'cash_accounts', 'stock_locations', 'product_categories'];

    public function run(array $params)
    {
        if (getenv('KEBUNI_DB_OVERRIDE')) {
            CLI::error('Jalankan perintah ini tanpa KEBUNI_DB_OVERRIDE.');

            return EXIT_ERROR;
        }
        $db   = db_connect();
        $src  = $db->getDatabase();
        $dest = $src . '_training';
        $reset = CLI::getOption('reset') !== null || in_array('--reset', $params, true);

        $exists = $db->query('SELECT COUNT(*) c FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$dest])->getRow('c') > 0;
        if ($exists && ! $reset) {
            CLI::error("Database {$dest} sudah ada. Pakai --reset untuk membuat ulang (semua data latihan dihapus).");

            return EXIT_ERROR;
        }
        if ($exists) {
            $db->query("DROP DATABASE `{$dest}`");
        }
        $db->query("CREATE DATABASE `{$dest}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

        $tables = array_column($db->query("SHOW FULL TABLES FROM `{$src}` WHERE Table_type = 'BASE TABLE'")->getResultArray(), 'Tables_in_' . $src);
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $t) {
            $db->query("CREATE TABLE `{$dest}`.`{$t}` LIKE `{$src}`.`{$t}`");
            if (in_array($t, self::COPY, true)) {
                $db->query("INSERT INTO `{$dest}`.`{$t}` SELECT * FROM `{$src}`.`{$t}`");
            }
        }
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        CLI::write(count($tables) . " tabel disalin ke {$dest}.", 'green');

        // Isi data contoh lewat proses terpisah yang memakai database latihan.
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOTPATH . 'spark') . ' training:seed';
        $env = array_merge($_SERVER, ['KEBUNI_DB_OVERRIDE' => $dest]);
        $env = array_filter($env, 'is_string');
        $p   = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH, $env);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $code = proc_close($p);
        CLI::write(trim($out));

        return $code === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
