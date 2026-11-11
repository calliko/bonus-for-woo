<?php

defined('ABSPATH') || exit;

/**
 * Класс подключения actions, filters, shortcodes
 * Рефакторенная версия с модульной архитектурой
 *
 * @version 8.1.0
 * @since 8.1.0
 */
class BfwRouter
{

    public static function init()
    {
        self::initStatistics();
        self::initAnalytics();
        self::initExport();
        self::initUserProfile();

        // Initialize modular classes
        BfwHooks::init();
        BfwFilters::init();
        BfwShortcodes::init();
        BfwAjaxHandlers::init();
    }

    /**
     * Хуки профиля пользователя
     */
    private static function initUserProfile()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_action('personal_options_update', ['BfwAccount', 'profileUserUpdate']);
        add_action('edit_user_profile_update', ['BfwAccount', 'profileUserUpdate']);
    }

    /**
     * Статистика и аналитика
     */
    private static function initStatistics()
    {
        BfwStatistic::init();
    }

    /**
     * Аналитика и отчеты
     */
    private static function initAnalytics()
    {
        // AJAX handlers для аналитики
        add_action('wp_ajax_bfw_analytics_distribution', ['BfwAnalytics', 'ajaxGetPointsDistribution']);
        add_action('wp_ajax_bfw_analytics_trend', ['BfwAnalytics', 'ajaxGetPointsTrend']);
        add_action('wp_ajax_bfw_analytics_overall', ['BfwAnalytics', 'ajaxGetOverallStats']);
        add_action('wp_ajax_bfw_analytics_top_users', ['BfwAnalytics', 'ajaxGetTopUsersByPoints']);
        add_action('wp_ajax_bfw_analytics_status', ['BfwAnalytics', 'ajaxGetStatusDistribution']);
        add_action('wp_ajax_bfw_analytics_referral', ['BfwAnalytics', 'ajaxGetReferralStats']);
        add_action('wp_ajax_bfw_analytics_coupon', ['BfwAnalytics', 'ajaxGetCouponStats']);

        // Экспорт аналитики через GET
        add_action('admin_init', ['BfwRouter', 'handleAnalyticsExport']);
    }

    /**
     * Экспорт данных
     */
    private static function initExport()
    {
        Bfw_Export::init();
        self::handleExportPoints();
        self::handleRemoveExport();
    }

    /**
     * Обработка экспорта баллов через GET-запрос
     */
    private static function handleExportPoints()
    {
        if (!isset($_GET['export_bfw_points']) || !current_user_can(BFW_CAP_MANAGE)) {
            return;
        }

        check_admin_referer('bfw_export_points');

        self::streamExport();
    }

    /**
     * Отдаёт CSV баллов потоком, не создавая файл на диске.
     *
     * Раньше файл писался в uploads с фиксированным именем и оставался там
     * после выгрузки — то есть данные всех клиентов (email, телефон, баланс)
     * лежали по постоянному публичному URL. Теперь данные уходят в php://output
     * и на диске не остаются.
     *
     * @return void
     */
    private static function streamExport(): void
    {
        $output = fopen('php://output', 'wb');

        if ($output === false) {
            wp_die(esc_html__('Cannot create export file.', 'bonus-for-woo'));
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bfw_points_' . gmdate('Y-m-d') . '.csv"');

        fwrite($output, "\xEF\xBB\xBF"); // BOM для UTF-8

        fputcsv($output, ['User id', 'User name', 'Email', 'Phone', 'Points', 'Status', 'Comment'], ',');

        foreach (self::iterateExportRows() as $row) {
            fputcsv($output, $row, ',');
        }

        fclose($output);
        exit;
    }

    /** Размер пакета при экспорте: столько пользователей держим в памяти за раз */
    const EXPORT_BATCH_SIZE = 500;

    /**
     * Построчный обход пользователей для экспорта.
     *
     * Раньше все пользователи и все их мета выбирались целиком в память, а
     * статус запрашивался отдельно на каждого (N+1). Теперь выборка идёт
     * пакетами, а статусы подтягиваются одним SQL-запросом на пакет.
     *
     * @return \Generator
     */
    private static function iterateExportRows(): \Generator
    {
        global $wpdb;

        $offset = 0;

        do {
            $users = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, user_nicename, user_email FROM {$wpdb->users} ORDER BY ID LIMIT %d OFFSET %d",
                self::EXPORT_BATCH_SIZE,
                $offset
            ));

            if (empty($users)) {
                break;
            }

            $user_ids = array_map('intval', wp_list_pluck($users, 'ID'));

            // 1. Мета и статусы — по одному запросу на пакет
            $metas = self::getUsersMetaBatch($user_ids);
            $statuses = self::getUsersStatusNamesBatch($user_ids);

            // 2. Отдаём строки
            foreach ($users as $user) {
                $user_id = (int) $user->ID;

                yield [
                    'id'      => $user_id,
                    'name'    => sanitize_text_field($user->user_nicename),
                    'email'   => sanitize_email($user->user_email),
                    'phone'   => sanitize_text_field($metas[$user_id]['billing_phone'] ?? ''),
                    'points'  => (int) ($metas[$user_id]['computy_point'] ?? 0),
                    'status'  => sanitize_text_field($statuses[$user_id] ?? ''),
                    'comment' => '',
                ];
            }

            $offset += self::EXPORT_BATCH_SIZE;
            unset($users, $metas, $statuses);
        } while (count($user_ids) === self::EXPORT_BATCH_SIZE);
    }

    /**
     * Метрики баллов и телефона для пакета пользователей.
     *
     * @param int[] $user_ids
     * @return array<int, array<string, string>>
     */
    private static function getUsersMetaBatch(array $user_ids): array
    {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, meta_key, meta_value
             FROM {$wpdb->usermeta}
             WHERE user_id IN ({$placeholders}) AND meta_key IN ('computy_point', 'billing_phone')",
            ...$user_ids
        ));

        $metas = [];
        foreach ($rows as $row) {
            $metas[(int) $row->user_id][$row->meta_key] = $row->meta_value;
        }

        return $metas;
    }

    /**
     * Названия статусов для пакета пользователей — один запрос вместо getRole() в цикле.
     *
     * @param int[] $user_ids
     * @return array<int, string>
     */
    private static function getUsersStatusNamesBatch(array $user_ids): array
    {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT um.user_id, r.name
             FROM {$wpdb->usermeta} um
             INNER JOIN {$wpdb->prefix}bfw_computy r ON r.id = um.meta_value
             WHERE um.user_id IN ({$placeholders}) AND um.meta_key = 'bfw_status'",
            ...$user_ids
        ));

        $statuses = [];
        foreach ($rows as $row) {
            $statuses[(int) $row->user_id] = (string) $row->name;
        }

        return $statuses;
    }

    /**
     * Обработка удаления файла экспорта баллов
     */
    private static function handleRemoveExport()
    {
        if (!isset($_GET['remove_export_bfw_points']) || !current_user_can(BFW_CAP_MANAGE)) {
            return;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'bfw_remove_export')) {
            wp_die('Security check failed');
        }

        /*
         * Экспорт больше не пишется на диск (см. streamExport()), но у сайтов,
         * обновлённых с версий <= 8.5.1, в uploads может остаться старый файл —
         * с персональными данными и по публичному URL. Чистим его.
         */
        BfwFunctions::deleteLegacyExportFile();

        wp_safe_redirect(admin_url('admin.php?page=bonus-for-woo%2Fpages%2Ftools.php&bfw_export_removed=1'));
        exit;
    }

    /**
     * Обработка экспорта аналитики через GET
     */
    public static function handleAnalyticsExport()
    {
        if (!isset($_GET['bfw_analytics_export']) || !current_user_can(BFW_CAP_MANAGE)) {
            return;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'bfw_analytics_nonce')) {
            wp_die('Security check failed');
        }

        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'overall';
        BfwAnalytics::exportToCSV($type);
    }

}
