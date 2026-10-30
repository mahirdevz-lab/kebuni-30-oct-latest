<?php

namespace App\Commands;

use App\Services\PosPricingService;
use App\Services\StockOpeningService;
use App\Services\SupervisorAuthService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Dipanggil oleh training:setup (dengan KEBUNI_DB_OVERRIDE). Menolak berjalan di database yang namanya tidak berakhiran _training. */
class TrainingSeed extends BaseCommand
{
    protected $group       = 'Kebuni POS';
    protected $name        = 'training:seed';
    protected $description = 'Isi data contoh di database latihan (internal).';

    private const PRODUCTS = [
        // kode, nama, satuan, harga jual, modal, stok awal, minimum
        ['LTH-001', 'Beras Premium 5kg', 'pak', 72000, 63000, 20, 5],
        ['LTH-002', 'Minyak Goreng 1L', 'btl', 18500, 16000, 36, 12],
        ['LTH-003', 'Gula Pasir 1kg', 'pak', 17000, 14800, 30, 10],
        ['LTH-004', 'Telur Ayam', 'kg', 29000, 26000, 15, 5],
        ['LTH-005', 'Mie Instan Goreng', 'bks', 3500, 2900, 120, 40],
        ['LTH-006', 'Kopi Sachet', 'bks', 2000, 1500, 200, 50],
        ['LTH-007', 'Air Mineral 600ml', 'btl', 4000, 2800, 48, 24],
        ['LTH-008', 'Sabun Mandi', 'pcs', 5500, 4200, 3, 6],
    ];

    public function run(array $params)
    {
        $db = db_connect();
        if (! str_ends_with((string) $db->getDatabase(), '_training') || ! getenv('KEBUNI_DB_OVERRIDE')) {
            CLI::error('Ditolak: bukan database latihan.');

            return EXIT_ERROR;
        }
        $now = date('Y-m-d H:i:s');

        $cat = $db->table('product_categories')->orderBy('id')->get(1)->getRowArray();
        if (! $cat) {
            $db->table('product_categories')->insert(['name' => 'Sembako', 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now]);
            $cat = ['id' => $db->insertID()];
        }

        $price = new PosPricingService();
        $lines = [];
        foreach (self::PRODUCTS as [$code, $name, $unit, $sell, $cost, $qty, $min]) {
            $db->table('products')->insert([
                'product_code' => $code, 'product_name' => $name, 'category_id' => $cat['id'], 'unit' => $unit,
                'status' => 'aktif', 'is_pos' => 1, 'min_stock' => $min, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $pid = (int) $db->insertID();
            $price->setRetailPrice($pid, (float) $sell, date('Y-m-d H:i:s', strtotime('-2 days')), null);
            $lines[] = ['product_id' => $pid, 'qty' => $qty, 'unit_cost' => $cost, 'cost_source' => 'Data latihan'];
        }

        $op = new StockOpeningService();
        $d  = $op->createDraft(date('Y-m-d H:i:s', strtotime('-1 day')), 'Stok awal data latihan', null);
        $op->saveLines((int) $d['id'], $lines);
        $r = $op->post((int) $d['id'], null);
        if (! $r['success']) {
            CLI::error('Stok awal gagal: ' . $r['message']);

            return EXIT_ERROR;
        }

        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $auth     = new SupervisorAuthService();
        CLI::write('Akun latihan (catat sekarang, tidak ditampilkan lagi):', 'yellow');
        foreach ([['latihan.kasir', 'Kasir Latihan', 'kasir'], ['latihan.spv', 'Supervisor Latihan', 'supervisor-toko'], ['latihan.admin', 'Admin Latihan', 'admin']] as [$u, $n, $role]) {
            $pw = '';
            for ($i = 0; $i < 10; $i++) {
                $pw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $rid = (int) $db->table('roles')->where('slug', $role)->get()->getRow('id');
            $db->table('users')->insert([
                'role_id' => $rid, 'full_name' => $n, 'username' => $u, 'email' => $u . '@latihan.local',
                'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $uid  = (int) $db->insertID();
            $line = sprintf('  %-14s %-18s password: %s', $u, $role, $pw);
            if ($role !== 'kasir') {
                do {
                    $pin = (string) random_int(100000, 999999);
                    $set = $auth->setPin($uid, $pw, $pin);
                } while (! $set['success']);
                $line .= "   PIN: {$pin}";
            }
            CLI::write($line);
        }
        CLI::write('Data contoh: ' . count(self::PRODUCTS) . ' produk dengan harga & stok awal (Sabun Mandi sengaja di bawah minimum).', 'green');

        return EXIT_SUCCESS;
    }
}
