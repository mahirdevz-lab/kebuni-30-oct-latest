<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Menjalankan server lokal yang memakai database latihan (port 8092) — database asli tidak disentuh. */
class TrainingServe extends BaseCommand
{
    protected $group       = 'Kebuni POS';
    protected $name        = 'training:serve';
    protected $description = 'Jalankan aplikasi mode latihan di http://localhost:8092 (butuh training:setup dulu).';
    protected $usage       = 'training:serve [port]';

    public function run(array $params)
    {
        $dest = db_connect()->getDatabase() . '_training';
        $port = (int) ($params[0] ?? 8092);

        // Router sementara: paksa baseURL ke localhost agar redirect tidak menuju domain di .env, dan pakai database latihan.
        $router = WRITEPATH . 'cache/training_router.php';
        file_put_contents($router, "<?php\nforeach (['app.baseURL' => 'http://localhost:{$port}/', 'CI_ENVIRONMENT' => 'production', 'KEBUNI_DB_OVERRIDE' => '{$dest}'] as \$k => \$v) {\n    putenv(\"{\$k}={\$v}\");\n    \$_ENV[\$k] = \$v;\n    \$_SERVER[\$k] = \$v;\n}\n\nreturn require " . var_export(ROOTPATH . 'system/rewrite.php', true) . ";\n");

        CLI::write("Mode LATIHAN memakai database {$dest} — http://localhost:{$port}/ (Ctrl+C untuk berhenti)", 'yellow');
        passthru(escapeshellarg(PHP_BINARY) . ' -S localhost:' . $port . ' -t ' . escapeshellarg(ROOTPATH . 'public') . ' ' . escapeshellarg($router));

        return EXIT_SUCCESS;
    }
}
