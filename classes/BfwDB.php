<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class BfwDb
 * Класс базы данных
 *
 * @version 6.4.0
 * @since 5.0.0
 */
class BfwDB
{

    /**
     * Checking the database version
     * Проверяем версию базы данных
     *
     * @return void
     * @version 6.4.0
     * @since 5.0.0
     */
    public static function checkDb(): void
    {
        self::getUpdateDb();
    }

    /**
     * Updating the database to the current version. Triggered during plugin activation and update.
     * Обновление базы данных (и полей и структуры) до актуальной версии.
     * Срабатывает во время активации и обновлении плагина.
     *
     * @return void
     * @version 6.4.0
     * @since 5.0.0
     */
    public static function getUpdateDb(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $bfw_computy = $wpdb->prefix . 'bfw_computy';

        $sql1 = "CREATE TABLE {$bfw_computy} (
		id mediumint NOT NULL AUTO_INCREMENT,
		name varchar(255) DEFAULT '' NOT NULL,
		slug varchar(150) DEFAULT '' NOT NULL,
		percent varchar(50) DEFAULT '' NOT NULL,
		summa_start varchar(50)  NOT NULL, 
		PRIMARY KEY  (id)
	) $charset_collate;";


        $bfw_history_computy = $wpdb->prefix . 'bfw_history_computy';
        $sql2 = "CREATE TABLE $bfw_history_computy (
		id mediumint NOT NULL AUTO_INCREMENT,
		user int(9) NOT NULL,
		date datetime DEFAULT NULL,
    	symbol varchar(10) NOT NULL,
		points decimal(19,4)  NOT NULL,
		orderz int(10)  NOT NULL,
		comment_admin VARCHAR(255)  DEFAULT NULL,
		status varchar(10)  DEFAULT NULL,
		PRIMARY KEY (id),
		INDEX name_index (user)
	) $charset_collate;";


        $bfw_coupons_computy = $wpdb->prefix . 'bfw_coupons_computy';
        $sql3 = "CREATE TABLE $bfw_coupons_computy (
		id mediumint NOT NULL AUTO_INCREMENT,
		code varchar(250) NOT NULL,
		sum decimal(19,4) NOT NULL,
		status varchar(10) DEFAULT '' NOT NULL,
		created datetime DEFAULT NULL,
	    date_use datetime DEFAULT NULL,
		user int(10) NOT NULL,
		reusable tinyint(1) DEFAULT NULL,
	comment_admin varchar(255) DEFAULT NULL,
		PRIMARY KEY (id)
                   ) $charset_collate;";

        $bfw_coupon_usages = $wpdb->prefix . 'bfw_coupon_usages';
        $sql4 = "CREATE TABLE $bfw_coupons_computy (
		id mediumint NOT NULL AUTO_INCREMENT,
		code varchar(250) NOT NULL,		 
		user int(10) NOT NULL,
		date_use datetime DEFAULT NULL,
		PRIMARY KEY (id)
                   ) $charset_collate;";

        $bfw_logs_computy = $wpdb->prefix . 'bfw_logs_computy';
        $sql5 = "CREATE TABLE $bfw_logs_computy (
		id mediumint NOT NULL AUTO_INCREMENT,
		event varchar(250) NOT NULL,
		message text NOT NULL,
		status varchar(10) DEFAULT '' NOT NULL,
		created datetime DEFAULT NULL,
		user int(10) NOT NULL,
		PRIMARY KEY (id)
                   ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);


        update_option('bfw_version_db', 6);
    }


}
