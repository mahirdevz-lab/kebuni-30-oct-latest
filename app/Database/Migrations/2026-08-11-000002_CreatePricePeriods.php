<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Price Period = versi harga yang berlaku pada rentang tanggal tertentu.
 *
 * PENTING (sesuai spesifikasi):
 * - Price Period bersifat HISTORICAL & IMMUTABLE setelah dipakai transaksi.
 * - Perubahan harga di tengah periode TIDAK boleh mengubah Price Period lama;
 *   harus membuat Price Period baru (revision).
 * - Lifecycle: DRAFT -> PUBLISHED -> CLOSED (tidak bisa mundur).
 */
class CreatePricePeriods extends Migration
{
    public function up()
    {
        // PRICE PERIODS (header)
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'period_code' => ['type' => 'VARCHAR', 'constraint' => 30], // PP-2026-08-11
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'start_date'  => ['type' => 'DATE'],
            'end_date'    => ['type' => 'DATE'],
            'status'      => ['type' => 'ENUM', 'constraint' => ['DRAFT', 'PUBLISHED', 'CLOSED'], 'default' => 'DRAFT'],
            'notes'       => ['type' => 'TEXT', 'null' => true],
            'created_by'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('period_code');
        $this->forge->addKey('status');
        $this->forge->addKey(['start_date', 'end_date']);
        $this->forge->createTable('price_periods');

        // PRICE PERIOD PRODUCTS (purchase price per produk pada periode ini)
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'price_period_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'product_id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'purchase_price'    => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'shrinkage_percent' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true], // override shrinkage master jika diisi
            'notes'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['price_period_id', 'product_id']);
        $this->forge->addForeignKey('price_period_id', 'price_periods', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('price_period_products');

        // PRICE PERIOD PACKINGS (harga packing material pada periode ini)
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'price_period_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'packing_material_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'price'               => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'capacity_kg'         => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true], // snapshot capacity saat itu
            'cost_per_kg'         => ['type' => 'DECIMAL', 'constraint' => '18,4'], // auto = price/capacity, atau manual override
            'is_manual_override'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'notes'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['price_period_id', 'packing_material_id']);
        $this->forge->addForeignKey('price_period_id', 'price_periods', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('packing_material_id', 'packing_materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('price_period_packings');

        // PRICE PERIOD TRANSPORTS (transport cost pada periode ini)
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'price_period_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'transport_route_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'total_transport_cost'  => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'volume_kg'             => ['type' => 'DECIMAL', 'constraint' => '18,2'], // capacity truk atau actual loaded volume, sesuai basis
            'basis'                 => ['type' => 'ENUM', 'constraint' => ['CAPACITY', 'ACTUAL'], 'default' => 'CAPACITY'],
            'cost_per_kg'           => ['type' => 'DECIMAL', 'constraint' => '18,4'], // auto = total_transport_cost / volume_kg
            'notes'                 => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['price_period_id', 'transport_route_id']);
        $this->forge->addForeignKey('price_period_id', 'price_periods', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('transport_route_id', 'transport_routes', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('price_period_transports');
    }

    public function down()
    {
        $this->forge->dropTable('price_period_transports', true);
        $this->forge->dropTable('price_period_packings', true);
        $this->forge->dropTable('price_period_products', true);
        $this->forge->dropTable('price_periods', true);
    }
}
