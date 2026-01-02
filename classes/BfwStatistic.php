<?php

defined( 'ABSPATH' ) || exit;

/**
 * Класс статистики
 */
class BfwStatistic
{
    const OPTION_KEY = 'bfw_cached_statistics';
    const PARTIAL_OPTION = 'bfw_partial_statistics';

    public static function init()
    {
        add_action('wp_ajax_bfw_stat_step', [__CLASS__, 'ajax_step_calc']);
        add_action('wp_ajax_bfw_get_stats_timestamp', [__CLASS__, 'ajax_get_stats_timestamp']);
        add_action('wp_ajax_bfw_clear_stats', [__CLASS__, 'ajax_clear_stats']);
    }

    public static function ajax_get_stats_timestamp()
    {
        $data = get_option(self::OPTION_KEY);
        wp_send_json_success([
            'timestamp' => $data['timestamp'] ?? 0,
        ]);
    }

    public static function ajax_step_calc()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $step = (int)($_POST['step'] ?? 0);
        $paged = (int)($_POST['paged'] ?? 1);

        global $wpdb;

        switch ($step) {
            case 0:
                $res = $wpdb->get_results("SELECT meta_value, COUNT(*) as count FROM {$wpdb->prefix}usermeta WHERE meta_key = 'bfw_status' GROUP BY meta_value",
                    OBJECT_K);
                $data = [
                    'user_status' => wp_list_pluck($res, 'count', 'meta_value')
                ];
                update_option(self::PARTIAL_OPTION, $data);
                wp_send_json_success(['paged' => 0]);

            case 1:
                $data = get_option(self::PARTIAL_OPTION, []);
                $data['points_total'] = (int)$wpdb->get_var("SELECT SUM(meta_value) FROM {$wpdb->prefix}usermeta WHERE meta_key = 'computy_point'");
                update_option(self::PARTIAL_OPTION, $data);
                wp_send_json_success(['paged' => 0]);

            case 2:
                $data = get_option(self::PARTIAL_OPTION, []);

                $exclude_roles = BfwSetting::get('exclude-role', array());

                $data['referrals_total'] = count(get_users([
                    'role__not_in' => $exclude_roles,
                    'meta_query' => [
                        [
                            'key' => 'bfw_points_referral',
                            'value' => '0',
                            'compare' => '!=',
                        ]
                    ]
                ]));

                $data['referrals_invited'] = count(get_users([
                    'role__not_in' => $exclude_roles,
                    'meta_query' => [
                        [
                            'key' => 'bfw_points_referral_invite',
                            'value' => '0',
                            'compare' => '!=',
                        ]
                    ]
                ]));

                update_option(self::PARTIAL_OPTION, $data);
                wp_send_json_success(['paged' => 0]);

            case 3:
                $data = get_option(self::PARTIAL_OPTION, []);

                $order_status = BfwSetting::get('add_points_order_status', 'completed');

                $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart', ''));

                $limit = 100;

                // Подсчёт общего количества заказов только на первой странице
                $total_orders = null;
                if ($paged === 1) {
                    $total_orders = wc_get_orders([
                        'status' => ['wc-' . $order_status],
                        'limit' => -1,
                        'return' => 'ids',
                    ]);
                    $data['orders_total_count'] = count($total_orders);
                    $data['orders_max_pages'] = ceil($data['orders_total_count'] / $limit);
                }

                $order_ids = wc_get_orders([
                    'status' => ['wc-' . $order_status],
                    'limit' => $limit,
                    'paged' => $paged,
                    'return' => 'ids',
                ]);

                foreach ($order_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    if (!$order) {
                        continue;
                    }

                    $data['orders_total'] = ($data['orders_total'] ?? 0) + 1;
                    $spent = BfwFunctions::feeOrCoupon($order);
                    $data['spent_total'] = ($data['spent_total'] ?? 0) + $spent;

                    $has_bonus = $spent > 0;
                    foreach ($order->get_coupon_codes() as $code) {
                        if (mb_strtolower($code) === $cart_discount) {
                            $has_bonus = true;
                        }
                    }

                    if ($has_bonus) {
                        $data['orders_with_bonus'] = ($data['orders_with_bonus'] ?? 0) + 1;
                    }
                }

                update_option(self::PARTIAL_OPTION, $data);

                if (count($order_ids) < $limit) {
                    $data['timestamp'] = current_time('timestamp');
                    update_option(self::OPTION_KEY, $data);
                    delete_option(self::PARTIAL_OPTION);
                    wp_send_json_success(['done' => true]);
                } else {
                    wp_send_json_success([
                        'paged' => $paged + 1,
                        'max_pages' => $data['orders_max_pages'] ?? 1,
                    ]);
                }

            default:
                wp_send_json_error(['message' => 'Unknown step']);
        }
    }

    /**
     * Получить кэшированную статистику
     */
    public static function get_stats()
    {
        return get_option(self::OPTION_KEY);
    }

    /** Очистка сохраненной стистики
     *
     * @return void
     */
    public static function ajax_clear_stats()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        delete_option(self::OPTION_KEY);
        delete_option('bfw_partial_statistics'); // если используется
        wp_send_json_success(['message' => 'Статистика очищена']);
    }

}