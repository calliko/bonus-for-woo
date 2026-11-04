<?php

defined('ABSPATH') || exit;

/**
 * Class BfwDB
 * Класс базы данных
 *
 * @version 6.4.0
 * @since 5.0.0
 */
class BfwDB
{
    /**
     * Текущая версия схемы. Должна совпадать с BONUS_COMPUTY_VERSION_DB.
     *
     * @since 8.5.0
     */
    const SCHEMA_VERSION = 8;

    /**
     * Checking the database version
     * Проверяем версию базы данных и при необходимости обновляем схему.
     *
     * @return void
     * @version 8.5.0
     * @since 5.0.0
     */
    public static function checkDb(): void
    {
        $current = (int) get_option('bfw_version_db', 0);

        // Схема актуальна — миграции не нужны
        if ($current >= self::SCHEMA_VERSION) {
            return;
        }

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

        /*
         * percent и summa_start — ЧИСЛА. Ранее были varchar(50), из-за чего
         * сортировка шла лексикографически (0, 1222, 14000, 150000, 300, 800),
         * то есть порядок статусов ломался, и каждый запрос вынужден был
         * приводить тип вручную через CAST.
         */
        $sql1 = "CREATE TABLE {$bfw_computy} (
		id mediumint NOT NULL AUTO_INCREMENT,
		name varchar(255) DEFAULT '' NOT NULL,
		slug varchar(150) DEFAULT '' NOT NULL,
		percent decimal(6,2) NOT NULL DEFAULT 0,
		summa_start decimal(19,2) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id)
	) $charset_collate;";


        $bfw_history_computy = $wpdb->prefix . 'bfw_history_computy';
        /*
         * Индексы: user_date_index покрывает выборки по пользователю,
         * отдельные name_index и user_index — дубликаты, удалены.
         * id/user/orderz — bigint: mediumint упирается в 8,3 млн строк истории.
         */
        $sql2 = "CREATE TABLE $bfw_history_computy (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user bigint(20) unsigned NOT NULL,
		date datetime DEFAULT NULL,
		symbol varchar(10) NOT NULL,
		points decimal(19,4)  NOT NULL,
		orderz bigint(20) unsigned NOT NULL DEFAULT 0,
		comment_admin VARCHAR(255)  DEFAULT NULL,
		status varchar(10)  DEFAULT NULL,
		PRIMARY KEY (id),
		KEY date_index (date),
		KEY order_index (orderz),
		KEY user_date_index (user, date)
	) $charset_collate;";


        $bfw_coupons_computy = $wpdb->prefix . 'bfw_coupons_computy';
        $sql3 = "CREATE TABLE $bfw_coupons_computy (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		code varchar(250) NOT NULL,
		sum decimal(19,4) NOT NULL,
		status varchar(10) DEFAULT '' NOT NULL,
		created datetime DEFAULT NULL,
		date_use datetime DEFAULT NULL,
		user bigint(20) unsigned NOT NULL DEFAULT 0,
		reusable tinyint(1) DEFAULT NULL,
		comment_admin varchar(255) DEFAULT NULL,
		PRIMARY KEY (id),
		KEY code_lookup (code),
		KEY status_lookup (status)
                   ) $charset_collate;";

        $bfw_coupon_usages = $wpdb->prefix . 'bfw_coupon_usages';
        $sql4 = "CREATE TABLE $bfw_coupon_usages (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		code varchar(250) NOT NULL,
		user bigint(20) unsigned NOT NULL,
		date_use datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY code_user (code, user)
                   ) $charset_collate;";

        $bfw_logs_computy = $wpdb->prefix . 'bfw_logs_computy';
        $sql5 = "CREATE TABLE $bfw_logs_computy (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event varchar(250) NOT NULL,
		message text NOT NULL,
		status varchar(10) DEFAULT '' NOT NULL,
		created datetime DEFAULT NULL,
		user bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY created_index (created),
		KEY user_index (user)
                   ) $charset_collate;";

        /*
         * Журнал транзакций (append-only ledger). Единственный источник истины
         * по тому, какая операция уже была применена. Уникальный ключ
         * (ref_type, ref_id, reason) делает повторное начисление/списание
         * за тот же объект невозможным на уровне БД.
         */
        $bfw_ledger = $wpdb->prefix . 'bfw_ledger';
        $sql6 = "CREATE TABLE $bfw_ledger (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		delta decimal(19,4) NOT NULL,
		balance_after decimal(19,4) NOT NULL,
		reason varchar(64) NOT NULL,
		ref_type varchar(32) NOT NULL DEFAULT '',
		ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
		note varchar(255) DEFAULT NULL,
		created datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY user_created (user_id, created),
		KEY ref_lookup (ref_type, ref_id),
		UNIQUE KEY uniq_operation (ref_type, ref_id, reason, user_id)
	) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);
        dbDelta($sql6);

        /*
         * Пошаговые миграции данных и типов. dbDelta не меняет тип существующей
         * колонки и не удаляет индексы — это делается здесь, по версии.
         */
        $from = (int) get_option('bfw_version_db', 0);

        if ($from < 8) {
            self::migrateTo8();
        }

        update_option('bfw_version_db', self::SCHEMA_VERSION);
    }

    /**
     * Миграция схемы 7 -> 8.
     *
     * 1. percent / summa_start: varchar -> decimal (нормализация значений).
     * 2. mediumint -> bigint для id/user (предел mediumint — 8,3 млн строк).
     * 3. Удаление дублирующих индексов истории (name_index, user_index).
     * 4. Уникальный ключ (code, user) на использования купонов.
     *
     * @return void
     * @version 8.5.0
     */
    private static function migrateTo8(): void
    {
        global $wpdb;

        $t_computy = $wpdb->prefix . 'bfw_computy';
        $t_history = $wpdb->prefix . 'bfw_history_computy';
        $t_coupons = $wpdb->prefix . 'bfw_coupons_computy';
        $t_usages  = $wpdb->prefix . 'bfw_coupon_usages';
        $t_logs    = $wpdb->prefix . 'bfw_logs_computy';

        /* --- 1. Числовые типы статусов --- */
        $column = $wpdb->get_row($wpdb->prepare(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'percent'",
            $t_computy
        ));

        if ($column && strtolower((string) $column->DATA_TYPE) !== 'decimal') {
            /*
             * Нормализуем значения ДО смены типа: пустые и нечисловые строки
             * в strict mode дадут ошибку, а в нестрогом превратятся в 0 молча.
             */
            foreach (array('percent' => '100', 'summa_start' => '0') as $col => $fallback) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE %i SET `$col` = %s
                     WHERE `$col` IS NULL OR `$col` = '' OR `$col` NOT REGEXP %s",
                    $t_computy,
                    $fallback,
                    '^-?[0-9]+([.][0-9]+)?$'
                ));
            }

            $wpdb->query(
                "ALTER TABLE {$t_computy}
                   MODIFY percent decimal(6,2) NOT NULL DEFAULT 0,
                   MODIFY summa_start decimal(19,2) NOT NULL DEFAULT 0"
            );
        }

        /* --- 2. BIGINT для идентификаторов --- */
        $wpdb->query(
            "ALTER TABLE {$t_history}
               MODIFY id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
               MODIFY user bigint(20) unsigned NOT NULL,
               MODIFY orderz bigint(20) unsigned NOT NULL DEFAULT 0"
        );
        $wpdb->query(
            "ALTER TABLE {$t_coupons}
               MODIFY id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
               MODIFY user bigint(20) unsigned NOT NULL DEFAULT 0"
        );
        $wpdb->query(
            "ALTER TABLE {$t_usages}
               MODIFY id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
               MODIFY user bigint(20) unsigned NOT NULL"
        );
        $wpdb->query(
            "ALTER TABLE {$t_logs}
               MODIFY id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
               MODIFY user bigint(20) unsigned NOT NULL DEFAULT 0"
        );

        /* --- 3. Дублирующие индексы истории --- */
        foreach (array('name_index', 'user_index') as $dup) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s",
                $t_history,
                $dup
            ));

            if ((int) $exists > 0) {
                $wpdb->query($wpdb->prepare('ALTER TABLE %i DROP INDEX %i', $t_history, $dup));
            }
        }

        /* --- 4. Уникальность использования купонов --- */
        $has_unique = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
               AND INDEX_NAME = 'code_user' AND NON_UNIQUE = 0",
            $t_usages
        ));

        if (!(int) $has_unique) {
            // Дубли мешают создать уникальный индекс — снимаем их заранее
            $wpdb->query(
                "DELETE u1 FROM {$t_usages} u1
                   JOIN {$t_usages} u2
                     ON u1.code = u2.code AND u1.user = u2.user AND u1.id > u2.id"
            );
            $wpdb->query("ALTER TABLE {$t_usages} ADD UNIQUE KEY code_user (code, user)");
        }

        BfwLogs::addLog(
            'migration',
            get_current_user_id(),
            'Схема БД обновлена до версии ' . self::SCHEMA_VERSION
        );
    }


}
