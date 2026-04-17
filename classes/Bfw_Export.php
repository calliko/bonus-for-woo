<?php

defined('ABSPATH') || exit;

class Bfw_Export
{
    public static function init()
    {
        add_action('admin_init', [__CLASS__, 'handle_export_requests']);
    }

    public static function handle_export_requests()
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['bfw_export']) && isset($_GET['_wpnonce'])) {
            $export_type = sanitize_text_field($_GET['bfw_export']);
            $nonce = $_GET['_wpnonce'];

            if (!wp_verify_nonce($nonce, 'bfw_export_action')) {
                wp_die(__('Security check failed', 'bonus-for-woo'));
            }

            switch ($export_type) {
                case 'coupons':
                    self::export_coupons();
                    break;
                case 'history':
                    self::export_history();
                    break;
            }
        }
    }

    private static function export_coupons()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfw_coupons_computy';

        $where = '';
        if (!empty($_REQUEST['s'])) {
            $search = esc_sql($_REQUEST['s']);
            $where = $wpdb->prepare(" WHERE code LIKE %s OR comment_admin LIKE %s", '%' . $search . '%', '%' . $search . '%');
        }

        $results = $wpdb->get_results("SELECT id, code, sum, created, comment_admin, status, user, date_use, reusable FROM {$table_name}{$where} ORDER BY id DESC", ARRAY_A);

        $filename = 'bfw_coupons_' . date('Y-m-d') . '.csv';
        $header = [
            'ID',
            __('Coupon code', 'bonus-for-woo'),
            __('Sum', 'bonus-for-woo'),
            __('Create date', 'bonus-for-woo'),
            __('Comment admin', 'bonus-for-woo'),
            __('Status', 'bonus-for-woo'),
            __('User ID', 'bonus-for-woo'),
            __('Date of use', 'bonus-for-woo'),
            __('Reusable', 'bonus-for-woo')
        ];

        self::output_csv($filename, $header, $results);
    }

    private static function export_history()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfw_history_computy';

        $where = ' WHERE 1=1';
        if (!empty($_GET['date_start'])) {
            $where .= $wpdb->prepare(" AND date >= %s", $_GET['date_start'] . ' 00:00:00');
        }
        if (!empty($_GET['date_finish'])) {
            $where .= $wpdb->prepare(" AND date <= %s", $_GET['date_finish'] . ' 23:59:59');
        }

        $results = $wpdb->get_results("SELECT id, user, symbol, points, comment_admin, date, orderz FROM {$table_name}{$where} ORDER BY id DESC", ARRAY_A);

        $filename = 'bfw_history_' . date('Y-m-d') . '.csv';
        $header = [
            'ID',
            __('User ID', 'bonus-for-woo'),
            __('Operation', 'bonus-for-woo'),
            __('Points', 'bonus-for-woo'),
            __('Cause', 'bonus-for-woo'),
            __('Date', 'bonus-for-woo'),
            __('Order ID', 'bonus-for-woo')
        ];

        self::output_csv($filename, $header, $results);
    }


    private static function output_csv($filename, $header, $data)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 support
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $header);

        if ($data) {
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }

        fclose($output);
        exit;
    }
}
