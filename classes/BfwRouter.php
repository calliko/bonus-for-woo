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
        add_action('wp_ajax_bfw_get_stats_timestamp', ['BfwStatistic', 'ajax_get_stats_timestamp']);
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
        if (!isset($_GET['export_bfw_points']) || !current_user_can('manage_options')) {
            return;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'bfw_export_points')) {
            wp_die('Security check failed');
        }

        self::generateExportFile();
    }

    /**
     * Генерация CSV файла экспорта баллов
     */
    private static function generateExportFile()
    {
        $file_path = wp_normalize_path(BONUS_COMPUTY_PLUGIN_DIR . '/export_bfw.csv');
        $buffer = fopen($file_path, 'w');

        if (!$buffer) {
            wp_die('Cannot create export file');
        }

        fwrite($buffer, "\xEF\xBB\xBF"); // BOM for UTF-8

        $title_export = ['User id', 'User name', 'Email', 'Phone', 'Points', 'Status', 'Comment'];
        fputcsv($buffer, $title_export, ',');

        $status = new BfwRoles();
        $data = self::prepareExportData($status);

        foreach ($data as $row) {
            fputcsv($buffer, $row, ',');
        }

        fclose($buffer);
    }

    /**
     * Подготовка данных для экспорта баллов
     */
    private static function prepareExportData($status): array
    {
        global $wpdb;

        // 1. Получаем только необходимые поля
        $users = $wpdb->get_results("SELECT ID, user_nicename, user_email FROM {$wpdb->users} ORDER BY ID");

        if (empty($users)) {
            return [];
        }

        $user_ids = wp_list_pluck($users, 'ID');
        $metas = [];

        // 2. Собираем метаполя ОДНИМ запросом для всех
        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $meta_query = $wpdb->prepare(
            "SELECT user_id, meta_key, meta_value 
         FROM {$wpdb->usermeta} 
         WHERE user_id IN ($placeholders) AND meta_key IN ('computy_point', 'billing_phone')",
            ...$user_ids // Важно: распаковываем массив для wpdb->prepare
        );

        foreach ($wpdb->get_results($meta_query) as $meta) {
            $metas[$meta->user_id][$meta->meta_key] = $meta->meta_value;
        }

        // 3. Формируем итоговый массив
        $data = [];
        foreach ($users as $user) {
            $points = (int)($metas[$user->ID]['computy_point'] ?? 0);
            $phone = $metas[$user->ID]['billing_phone'] ?? '';

            // Внимание: если getRole внутри делает SQL-запрос,
            // скрипт все еще может притормаживать на больших объемах.
            $arrayRole = $status->getRole($user->ID);

            $data[] = [
                'id'      => (int)$user->ID,
                'name'    => sanitize_text_field($user->user_nicename),
                'email'   => sanitize_email($user->user_email),
                'phone'   => sanitize_text_field($phone),
                'points'  => $points,
                'status'  => sanitize_text_field($arrayRole['name'] ?? ''),
                'comment' => '',
            ];
        }

        return $data;
    }

    /**
     * Обработка удаления файла экспорта баллов
     */
    private static function handleRemoveExport()
    {
        if (!isset($_GET['remove_export_bfw_points']) || !current_user_can('manage_options')) {
            return;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'bfw_remove_export')) {
            wp_die('Security check failed');
        }

        $file_path = wp_normalize_path(BONUS_COMPUTY_PLUGIN_DIR . '/export_bfw.csv');

        if (strpos($file_path, BONUS_COMPUTY_PLUGIN_DIR) !== 0) {
            wp_die('Invalid file path');
        }

        if (file_exists($file_path)) {
            wp_delete_file($file_path);
        }
    }

}
