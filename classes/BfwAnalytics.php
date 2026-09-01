<?php

defined('ABSPATH') || exit;

/**
 * Analytics and Reports Class
 * Класс аналитики и отчетов для системы бонусов
 *
 * @version 8.3.0
 * @since 8.3.0
 */
class BfwAnalytics
{

    /**
     * Получить распределение баллов по диапазонам
     *
     * @return array
     */
    public static function getPointsDistribution(): array
    {
        global $wpdb;

        $results = $wpdb->get_results("
            SELECT 
                CASE 
                    WHEN meta_value < 100 THEN '0-99'
                    WHEN meta_value < 500 THEN '100-499'
                    WHEN meta_value < 1000 THEN '500-999'
                    WHEN meta_value < 5000 THEN '1000-4999'
                    ELSE '5000+'
                END as `range`,
                COUNT(*) as count
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'computy_point'
            GROUP BY `range`
            ORDER BY `range`
        ");

        return $results;
    }

    /**
     * AJAX handler для распределения баллов
     */
    public static function ajaxGetPointsDistribution()
    {
        check_ajax_referer('bfw_analytics_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        try {
            $data = self::getPointsDistribution();
            wp_send_json_success($data);
        } catch (Exception $e) {
            error_log('BfwAnalytics: Distribution error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Получить тренд начисления и списания баллов за период
     *
     * @param int $days Количество дней
     * @return array
     */
    public static function getPointsTrend(int $days = 30): array
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(date) as date,
                SUM(CASE WHEN symbol = '+' THEN points ELSE 0 END) as issued,
                SUM(CASE WHEN symbol = '-' THEN points ELSE 0 END) as spent
            FROM {$wpdb->prefix}bfw_history_computy
            WHERE date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(date)
            ORDER BY date ASC",
            $days
        ));

        return $results;
    }

    /**
     * AJAX handler для тренда баллов
     */
    public static function ajaxGetPointsTrend()
    {
        check_ajax_referer('bfw_analytics_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
        wp_send_json_success(self::getPointsTrend($days));
    }

    /**
     * Получить общую статистику системы
     *
     * @return array
     */
    public static function getOverallStats(): array
    {
        global $wpdb;

        // Общее количество баллов у всех пользователей
        $total_points = $wpdb->get_var("
            SELECT SUM(CAST(meta_value AS DECIMAL(19,4)))
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'computy_point'
        ");

        // Количество пользователей с баллами
        $users_with_points = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'computy_point'
            AND CAST(meta_value AS DECIMAL(19,4)) > 0
        ");

        // Всего начислено баллов за все время
        $total_earned = $wpdb->get_var("
            SELECT SUM(points)
            FROM {$wpdb->prefix}bfw_history_computy
            WHERE symbol = '+'
        ");

        // Всего списано баллов за все время
        $total_spent = $wpdb->get_var("
            SELECT SUM(points)
            FROM {$wpdb->prefix}bfw_history_computy
            WHERE symbol = '-'
        ");

        // Количество активных участников программы
        $active_users = $wpdb->get_var("
            SELECT COUNT(DISTINCT user)
            FROM {$wpdb->prefix}bfw_history_computy
            WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        return [
            'total_points' => (float)($total_points ?? 0),
            'users_with_points' => (int)($users_with_points ?? 0),
            'total_earned' => (float)($total_earned ?? 0),
            'total_spent' => (float)($total_spent ?? 0),
            'active_users' => (int)($active_users ?? 0),
        ];
    }

    /**
     * AJAX handler для общей статистики
     */
    public static function ajaxGetOverallStats()
    {
        check_ajax_referer('bfw_analytics_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success(self::getOverallStats());
    }

    /**
     * Получить топ пользователей по баллам
     *
     * @param int $limit Количество пользователей
     * @return array
     */
    public static function getTopUsersByPoints(int $limit = 10): array
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                um.user_id,
                um.meta_value as points,
                u.user_login,
                u.user_email,
                u.display_name
            FROM {$wpdb->usermeta} um
            INNER JOIN {$wpdb->users} u ON um.user_id = u.ID
            WHERE um.meta_key = 'computy_point'
            AND CAST(um.meta_value AS DECIMAL(19,4)) > 0
            ORDER BY CAST(um.meta_value AS DECIMAL(19,4)) DESC
            LIMIT %d",
            $limit
        ));

        return $results;
    }

    /**
     * AJAX handler для топ пользователей
     */
    public static function ajaxGetTopUsersByPoints()
    {
        check_ajax_referer('bfw_analytics_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        wp_send_json_success(self::getTopUsersByPoints($limit));
    }

    /**
     * Получить статистику по статусам
     *
     * @return array
     */
    public static function getStatusDistribution(): array
    {
        global $wpdb;

        $results = $wpdb->get_results("
            SELECT 
                r.name as status_name,
                r.percent as cashback_percent,
                COUNT(DISTINCT um.user_id) as user_count
            FROM {$wpdb->prefix}bfw_computy r
            LEFT JOIN {$wpdb->usermeta} um ON um.meta_value = r.id
            WHERE um.meta_key = 'bfw_status'
            GROUP BY r.id, r.name, r.percent
            ORDER BY r.summa_start ASC
        ");

        return $results;
    }

    /**
     * AJAX handler для статистики по статусам
     */
    public static function ajaxGetStatusDistribution()
    {
        check_ajax_referer('bfw_analytics_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success(self::getStatusDistribution());
    }

    /**
     * Получить статистику по реферальной системе
     *
     * @return array
     */
    public static function getReferralStats(): array
    {
        global $wpdb;

        // Количество пользователей с реферальными ссылками
        $users_with_referral = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'bfw_points_referral'
            AND meta_value != ''
        ");

        // Количество приглашенных пользователей
        $invited_users = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'bfw_points_referral_invite'
            AND CAST(meta_value AS SIGNED) > 0
        ");

        // Топ рефералов по количеству приглашений
        $top_referrers = $wpdb->get_results("
            SELECT 
                inviter.meta_value as inviter_id,
                COUNT(DISTINCT invitee.user_id) as invite_count,
                u.user_login,
                u.display_name
            FROM {$wpdb->usermeta} invitee
            INNER JOIN {$wpdb->usermeta} inviter ON invitee.meta_value = inviter.user_id
            INNER JOIN {$wpdb->users} u ON inviter.user_id = u.ID
            WHERE invitee.meta_key = 'bfw_points_referral_invite'
            AND inviter.meta_key = 'bfw_points_referral'
            AND CAST(invitee.meta_value AS SIGNED) > 0
            GROUP BY inviter.user_id
            ORDER BY invite_count DESC
            LIMIT 10
        ");

        return [
            'users_with_referral' => (int)($users_with_referral ?? 0),
            'invited_users' => (int)($invited_users ?? 0),
            'top_referrers' => $top_referrers,
        ];
    }

    /**
     * AJAX handler для статистики по рефералам
     */
    public static function ajaxGetReferralStats()
    {
        check_ajax_referer('bfw_analytics_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success(self::getReferralStats());
    }

    /**
     * Получить статистику по купонам
     *
     * @return array
     */
    public static function getCouponStats(): array
    {
        global $wpdb;

        // Всего создано купонов
        $total_coupons = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}bfw_coupons_computy
        ");

        // Активных купонов
        $active_coupons = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}bfw_coupons_computy
            WHERE status = 'active'
        ");

        // Использованных купонов
        $used_coupons = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}bfw_coupons_computy
            WHERE status = 'used'
        ");

        // Общая сумма купонов
        $total_coupon_sum = $wpdb->get_var("
            SELECT SUM(sum)
            FROM {$wpdb->prefix}bfw_coupons_computy
        ");

        return [
            'total_coupons' => (int)($total_coupons ?? 0),
            'active_coupons' => (int)($active_coupons ?? 0),
            'used_coupons' => (int)($used_coupons ?? 0),
            'total_coupon_sum' => (float)($total_coupon_sum ?? 0),
        ];
    }

    /**
     * AJAX handler для статистики по купонам
     */
    public static function ajaxGetCouponStats()
    {
        check_ajax_referer('bfw_analytics_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        wp_send_json_success(self::getCouponStats());
    }

    /**
     * Экспорт аналитики в CSV
     *
     * @param string $type Тип отчета (distribution, trend, overall)
     * @return void
     */
    public static function exportToCSV(string $type): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        $filename = 'bfw-analytics-' . $type . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        switch ($type) {
            case 'distribution':
                $data = self::getPointsDistribution();
                fputcsv($output, ['Range', 'Count']);
                foreach ($data as $row) {
                    fputcsv($output, [$row->range, $row->count]);
                }
                break;

            case 'trend':
                $data = self::getPointsTrend(30);
                fputcsv($output, ['Date', 'Issued', 'Spent']);
                foreach ($data as $row) {
                    fputcsv($output, [$row->date, $row->issued, $row->spent]);
                }
                break;

            case 'overall':
                $data = self::getOverallStats();
                fputcsv($output, ['Metric', 'Value']);
                foreach ($data as $key => $value) {
                    fputcsv($output, [$key, $value]);
                }
                break;

            default:
                wp_die('Invalid report type');
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX handler для экспорта аналитики
     */
    public static function ajaxExportToCSV()
    {
        check_ajax_referer('bfw_analytics_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'overall';

        // Generate CSV and return as download
        self::exportToCSV($type);
    }
}
