<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * OPEX (Operational Expense) — biaya di LUAR labour cost & transport cost:
 * sewa, listrik/utility, biaya tak terduga, gaji karyawan tetap, dll.
 *
 * Struktur mengikuti pola yang sama dengan price_period_transports:
 * total biaya dialokasikan ke Rp/Kg berdasarkan estimasi volume periode itu.
 * Bedanya, OPEX dipecah per AREA (cost center: HO, Sukabumi, Jakarta, dst.)
 * dan per KATEGORI biaya (rincian), bukan cuma satu angka.
 *
 * - opex_areas.destination_id dipakai untuk area yang mewakili tujuan
 *   pengiriman (mis. Jakarta), sehingga engine bisa otomatis mencocokkan
 *   area berdasarkan destination pada Transport Route yang dipilih.
 *   Area cost-center murni (HO) sengaja dibiarkan destination_id = NULL.
 */
class CreateOpex extends Migration
{
    public function up()
    {
        // OPEX CATEGORIES (master rincian biaya: sewa, listrik, gaji tetap, dst.)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'     => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('opex_categories');

        // OPEX AREAS (cost center: HO, Sukabumi, Jakarta, dst.)
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 30], // HO, SUKABUMI, JAKARTA
            'name'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'destination_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'status'         => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'notes'          => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('destination_id');
        $this->forge->addForeignKey('destination_id', 'destinations', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('opex_areas');

        // PRICE PERIOD OPEX AREAS (header per Price Period + Area)
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'price_period_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'opex_area_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'allocation_volume_kg' => ['type' => 'DECIMAL', 'constraint' => '18,2'], // estimasi total Kg yang diproses/terjual area ini pada periode ini
            'total_opex'           => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0], // auto = SUM(items.amount)
            'opex_cost_per_kg'     => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0], // auto = total_opex / allocation_volume_kg
            'notes'                => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['price_period_id', 'opex_area_id']);
        $this->forge->addForeignKey('price_period_id', 'price_periods', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('opex_area_id', 'opex_areas', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('price_period_opex_areas');

        // PRICE PERIOD OPEX ITEMS (rincian per kategori biaya, di bawah header area di atas)
        $this->forge->addField([
            'id'                        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'price_period_opex_area_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'opex_category_id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'amount'                    => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'notes'                     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'                => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['price_period_opex_area_id', 'opex_category_id']);
        $this->forge->addForeignKey('price_period_opex_area_id', 'price_period_opex_areas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('opex_category_id', 'opex_categories', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('price_period_opex_items');

        // SEED default OPEX areas: HO & Sukabumi (cost center murni),
        // Jakarta ditautkan otomatis ke destination "Jakarta" jika sudah ada.
        $now = date('Y-m-d H:i:s');

        $jakartaDestinationId = $this->db->table('destinations')
            ->like('name', 'Jakarta')
            ->get()
            ->getRow('id');

        $this->db->table('opex_areas')->insertBatch([
            ['code' => 'HO', 'name' => 'Head Office', 'destination_id' => null, 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SUKABUMI', 'name' => 'Sukabumi (Gudang/Produksi)', 'destination_id' => null, 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'JAKARTA', 'name' => 'Jakarta', 'destination_id' => $jakartaDestinationId, 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('price_period_opex_items', true);
        $this->forge->dropTable('price_period_opex_areas', true);
        $this->forge->dropTable('opex_areas', true);
        $this->forge->dropTable('opex_categories', true);
    }
}
