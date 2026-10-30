<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fondasi Master Data untuk PO / Taking Order & Pricing Engine.
 *
 * PENTING: tabel-tabel ini TIDAK menyimpan harga aktif permanen.
 * Harga (purchase price, packing price, transport cost) disimpan
 * pada Price Period (lihat migration fase berikutnya) agar historical
 * dan immutable setelah dipakai oleh PO/Taking Order.
 */
class CreatePricingMasterData extends Migration
{
    public function up()
    {
        // PRODUCT CATEGORIES
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'     => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('product_categories');

        // PRODUCTS (Master Product - TANPA harga aktif permanen)
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'product_code'    => ['type' => 'VARCHAR', 'constraint' => 30],
            'product_name'    => ['type' => 'VARCHAR', 'constraint' => 150],
            'category_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'unit'            => ['type' => 'VARCHAR', 'constraint' => 20], // Kg, Pcs, Liter, Box, Unit, Dus, Peti
            'shrinkage_type'  => ['type' => 'ENUM', 'constraint' => ['PERCENTAGE', 'FIXED'], 'default' => 'PERCENTAGE'],
            'shrinkage_value' => ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 0], // % jika PERCENTAGE, Rupiah jika FIXED
            'status'          => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('product_code');
        $this->forge->addKey('category_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('category_id', 'product_categories', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('products');

        // PACKING MATERIALS
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'         => ['type' => 'VARCHAR', 'constraint' => 30],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'unit'         => ['type' => 'VARCHAR', 'constraint' => 20], // Pcs, Kg, Dus, dst.
            'capacity_kg'  => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true], // null jika material tidak berbasis kapasitas (mis. Koran dihitung langsung per Kg)
            'status'       => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'notes'        => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('status');
        $this->forge->createTable('packing_materials');

        // PRODUCT PACKING RECIPES (Product -> Packing Material -> Quantity/Usage)
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'product_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'packing_material_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'quantity_usage'      => ['type' => 'DECIMAL', 'constraint' => '10,4', 'default' => 1], // pengali terhadap cost/kg packing material
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['product_id', 'packing_material_id']);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('packing_material_id', 'packing_materials', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('product_packing_recipes');

        // DESTINATIONS
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100], // Jakarta, BLT, dst.
            'status'     => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('destinations');

        // TRANSPORT ROUTES (Origin -> Destination, basis default)
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'origin'          => ['type' => 'VARCHAR', 'constraint' => 100], // mis. Sukabumi
            'destination_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'default_basis'   => ['type' => 'ENUM', 'constraint' => ['CAPACITY', 'ACTUAL'], 'default' => 'CAPACITY'],
            'status'          => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('destination_id');
        $this->forge->addUniqueKey(['origin', 'destination_id']);
        $this->forge->addForeignKey('destination_id', 'destinations', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('transport_routes');
    }

    public function down()
    {
        $this->forge->dropTable('transport_routes', true);
        $this->forge->dropTable('destinations', true);
        $this->forge->dropTable('product_packing_recipes', true);
        $this->forge->dropTable('packing_materials', true);
        $this->forge->dropTable('products', true);
        $this->forge->dropTable('product_categories', true);
    }
}
