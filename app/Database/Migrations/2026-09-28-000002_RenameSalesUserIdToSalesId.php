<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kode (SalesOrderModel/Controller/Views) memakai sales_orders.sales_id yang merujuk master `sales`,
 * sedangkan migration awal membuat kolom sales_user_id. Samakan nama kolom & tambahkan FK ke sales.
 */
class RenameSalesUserIdToSalesId extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE `sales_orders` CHANGE `sales_user_id` `sales_id` INT(11) UNSIGNED NULL');
        $this->db->query('ALTER TABLE `sales_orders` ADD CONSTRAINT `sales_orders_sales_id_foreign` FOREIGN KEY (`sales_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE `sales_orders` DROP FOREIGN KEY `sales_orders_sales_id_foreign`');
        $this->db->query('ALTER TABLE `sales_orders` CHANGE `sales_id` `sales_user_id` INT(11) UNSIGNED NULL');
    }
}
