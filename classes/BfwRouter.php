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
        $users = $wpdb->get_results("SELECT * FROM {$wpdb->users} ORDER BY ID");
        $data = [];

        foreach ($users as $user) {
            $points = (int)(get_user_meta($user->ID, 'computy_point', true) ?? 0);
            $arrayRole = $status->getRole($user->ID);

            $data[] = [
                'id' => (int)$user->ID,
                'name' => sanitize_text_field($user->user_nicename),
                'email' => sanitize_email($user->user_email),
                'phone' => sanitize_text_field(get_user_meta($user->ID, 'billing_phone', true) ?? ''),
                'points' => $points,
                'status' => sanitize_text_field($arrayRole['name']),
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
