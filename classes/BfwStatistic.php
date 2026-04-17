<?php

defined('ABSPATH') || exit;

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
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        $data = get_option(self::OPTION_KEY);
        wp_send_json_success([
            'timestamp' => $data['timestamp'] ?? 0,
        ]);
    }

    /**
     * Определение контекста запроса заказов (HPOS vs Posts)
     */
    private static function get_order_context()
    {
        global $wpdb;
        $is_hpos = false;

        // Основной способ WC 8.0+
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
            method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'is_custom_order_tables_usage_enabled')) {
            $is_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::is_custom_order_tables_usage_enabled();
        }

        // Запасной способ через опции (если класс еще не подгружен)
        if (!$is_hpos) {
            $is_hpos = (get_option('woocommerce_custom_orders_table_enabled') === 'yes');
        }

        if ($is_hpos) {
            return [
                'table'    => "{$wpdb->prefix}wc_orders",
                'id'       => 'id',
                'customer' => 'customer_id',
                'status'   => 'status',
                'type'     => 'type',
                'total'    => 'total_amount',
                'hpos'     => true
            ];
        }

        return [
            'table'    => "{$wpdb->prefix}posts",
            'id'       => 'ID',
            'customer' => 'post_author',
            'status'   => 'post_status',
            'type'     => 'post_type',
            'total'    => 'meta_value',
            'hpos'     => false
        ];
    }

    public static function ajax_step_calc()
    {
        check_ajax_referer('bfw_stat_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $step = (int)($_POST['step'] ?? 0);
        $paged = (int)($_POST['paged'] ?? 1);
        $date_start = sanitize_text_field($_POST['date_start'] ?? '');
        $date_end = sanitize_text_field($_POST['date_end'] ?? '');
        
        $date_filter_history = "";
        $date_args = [];
        
        if ($date_start && $date_end) {
            $date_filter_history = " AND date BETWEEN %s AND %s ";
            $date_args = [$date_start . ' 00:00:00', $date_end . ' 23:59:59'];
        }

        global $wpdb;

        switch ($step) {
            case 0:
                // Статусы пользователей
                $res = $wpdb->get_results("SELECT meta_value, COUNT(*) as count FROM {$wpdb->prefix}usermeta WHERE meta_key = 'bfw_status' GROUP BY meta_value", OBJECT_K);
                $data = [
                    'user_status' => wp_list_pluck($res, 'count', 'meta_value')
                ];
                update_option(self::PARTIAL_OPTION, $data);
                wp_send_json_success(['paged' => 0]);

            case 1:
                // Сумма всех баллов на балансах
                $data = get_option(self::PARTIAL_OPTION, []);
                $data['date_start'] = $date_start;
                $data['date_end'] = $date_end;
                
                $data['points_total'] = (float)$wpdb->get_var("SELECT SUM(CAST(meta_value AS DECIMAL(12,2))) FROM {$wpdb->prefix}usermeta WHERE meta_key = 'computy_point'");
                
                // Активные пользователи (с балансом > 0)
                $data['active_users'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}usermeta WHERE meta_key = 'computy_point' AND CAST(meta_value AS DECIMAL(12,2)) > 0");

                // Penetration Rate
                $total_customers = (int)$wpdb->get_var("
                    SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->prefix}users u
                    INNER JOIN {$wpdb->prefix}usermeta um ON u.ID = um.user_id
                    WHERE um.meta_key = '{$wpdb->prefix}capabilities' AND um.meta_value LIKE '%customer%'
                ");
                $data['penetration'] = $total_customers > 0 ? ($data['active_users'] / $total_customers) * 100 : 0;

                // Расчет Redemption Rate (PRR)
                $history_totals = $wpdb->get_row($wpdb->prepare("
                    SELECT 
                        SUM(CASE WHEN symbol = '+' THEN points ELSE 0 END) as issued,
                        ABS(SUM(CASE WHEN symbol = '-' THEN points ELSE 0 END)) as spent
                    FROM {$wpdb->prefix}bfw_history_computy
                    WHERE 1=1 {$date_filter_history}
                ", ...($date_args ?: ['1']))); // mock arg if empty
                $data['issued_total_life'] = (float)($history_totals->issued ?? 0);
                $data['spent_total_life'] = (float)($history_totals->spent ?? 0);
                $data['prr'] = $data['issued_total_life'] > 0 ? ($data['spent_total_life'] / $data['issued_total_life']) * 100 : 0;

                // Pending-баллы: сумма ожидающих начисления по всем пользователям
                $data['pending_points_total'] = (float)$wpdb->get_var(
                    "SELECT SUM(CAST(meta_value AS DECIMAL(12,2))) FROM {$wpdb->prefix}usermeta WHERE meta_key = 'computy_point_pending' AND meta_value > 0"
                );

                update_option(self::PARTIAL_OPTION, $data);
                wp_send_json_success(['paged' => 0]);

            case 2:
                // Реферальная система (SQL вместо get_users)
                $data = get_option(self::PARTIAL_OPTION, []);
                $exclude_roles = BfwSetting::get('exclude-role', array());
                $exclude_sql = "";
                if (!empty($exclude_roles)) {
                    $role_queries = [];
                    foreach ((array)$exclude_roles as $role) {
                        $role_queries[] = $wpdb->prepare("meta_value LIKE %s", '%' . $wpdb->esc_like($role) . '%');
                    }
                    $exclude_sql = " AND user_id NOT IN (SELECT user_id FROM {$wpdb->prefix}usermeta WHERE meta_key = '{$wpdb->prefix}capabilities' AND (" . implode(' OR ', $role_queries) . "))";
                }

                $data['referrals_total'] = (int)$wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}usermeta WHERE meta_key = 'bfw_points_referral' AND meta_value != '0' {$exclude_sql}");
                $data['referrals_invited'] = (int)$wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}usermeta WHERE meta_key = 'bfw_points_referral_invite' AND meta_value != '0' {$exclude_sql}");

                // Выручка от реферальной системы (с учетом HPOS)
                $ctx = self::get_order_context();
                $order_status = BfwSetting::get('add_points_order_status', 'completed');
                $st = 'wc-' . $order_status;
                
                $order_date_field = $ctx['hpos'] ? 'date_created_gmt' : 'post_date';
                $order_date_field_p = $ctx['hpos'] ? 'date_created_gmt' : 'p.post_date';
                $date_sql = $date_start ? " AND {$order_date_field} BETWEEN %s AND %s " : "";
                $date_sql_p = $date_start ? " AND {$order_date_field_p} BETWEEN %s AND %s " : "";
                
                $full_args_ref = $date_start ? array_merge([$st, $order_status], $date_args) : [$st, $order_status];

                if ($ctx['hpos']) {
                    $data['referral_revenue'] = (float)$wpdb->get_var($wpdb->prepare("
                        SELECT SUM({$ctx['total']}) FROM {$ctx['table']} 
                        WHERE {$ctx['status']} IN (%s, %s) AND {$ctx['type']} = 'shop_order' {$date_sql}
                        AND {$ctx['customer']} IN (SELECT user_id FROM {$wpdb->prefix}usermeta WHERE meta_key = 'bfw_points_referral_invite')
                    ", ...$full_args_ref));
                    
                    $top_users = $wpdb->get_results($wpdb->prepare("
                        SELECT {$ctx['customer']} as id, SUM({$ctx['total']}) as revenue, COUNT({$ctx['id']}) as orders
                        FROM {$ctx['table']}
                        WHERE {$ctx['status']} IN (%s, %s) AND {$ctx['type']} = 'shop_order' {$date_sql}
                        GROUP BY {$ctx['customer']}
                        ORDER BY revenue DESC LIMIT 5
                    ", ...$full_args_ref), ARRAY_A);
                } else {
                    $data['referral_revenue'] = (float)$wpdb->get_var($wpdb->prepare("
                        SELECT SUM(pm.meta_value) FROM {$wpdb->prefix}postmeta pm
                        JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
                        WHERE pm.meta_key = '_order_total' AND p.post_type = 'shop_order' AND p.post_status IN (%s, %s) {$date_sql_p}
                        AND p.post_author IN (SELECT user_id FROM {$wpdb->prefix}usermeta WHERE meta_key = 'bfw_points_referral_invite')
                    ", ...$full_args_ref));
                    
                     $top_users = $wpdb->get_results($wpdb->prepare("
                        SELECT p.post_author as id, SUM(CAST(pm.meta_value AS DECIMAL(12,2))) as revenue, COUNT(p.ID) as orders
                        FROM {$wpdb->prefix}posts p
                        JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
                        WHERE pm.meta_key = '_order_total' AND p.post_type = 'shop_order' AND p.post_status IN (%s, %s) {$date_sql_p}
                        GROUP BY p.post_author
                        ORDER BY revenue DESC LIMIT 5
                    ", ...$full_args_ref), ARRAY_A);
                }
                
                $data['top_customers'] = array_filter((array)$top_users, function($u) { return !empty($u['id']); });

                update_option(self::PARTIAL_OPTION, $data);
                wp_send_json_success(['paged' => 0]);

            case 3:
                // Статистика заказов (Агрегатный SQL с учетом HPOS)
                $data = get_option(self::PARTIAL_OPTION, []);
                $ctx = self::get_order_context();
                $order_status = BfwSetting::get('add_points_order_status', 'completed');
                $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart', ''));
                $st = 'wc-' . $order_status;
                
                $order_date_field = $ctx['hpos'] ? 'date_created_gmt' : 'post_date';
                $order_date_field_p = $ctx['hpos'] ? 'date_created_gmt' : 'p.post_date';
                $date_sql = $date_start ? " AND {$order_date_field} BETWEEN %s AND %s " : "";
                $date_sql_p = $date_start ? " AND {$order_date_field_p} BETWEEN %s AND %s " : "";
                
                $full_args = $date_start ? array_merge([$st, $order_status], $date_args) : [$st, $order_status];

                // Общее кол-во и оборот
                if ($ctx['hpos']) {
                    $order_stats = $wpdb->get_row($wpdb->prepare("
                        SELECT COUNT({$ctx['id']}) as count, SUM({$ctx['total']}) as revenue 
                        FROM {$ctx['table']} WHERE {$ctx['status']} IN (%s, %s) AND {$ctx['type']} = 'shop_order' {$date_sql}
                    ", ...$full_args));
                } else {
                    $order_stats = $wpdb->get_row($wpdb->prepare("
                        SELECT COUNT(p.ID) as count, SUM(CAST(m.meta_value AS DECIMAL(12,2))) as revenue 
                        FROM {$wpdb->prefix}posts p 
                        JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id 
                        WHERE p.post_type = 'shop_order' AND p.post_status IN (%s, %s) AND m.meta_key = '_order_total' {$date_sql_p}
                    ", ...$full_args));
                }

                $data['orders_total'] = (int)$order_stats->count;
                $data['total_revenue'] = (float)$order_stats->revenue;

                // Заказы с бонусами и сумма списания
                $bonus_stats_args = $date_start ? array_merge([$cart_discount, $st], $date_args) : [$cart_discount, $st];
                $bonus_stats = $wpdb->get_row($wpdb->prepare("
                    SELECT COUNT(DISTINCT oi.order_id) as count, SUM(CAST(om.meta_value AS DECIMAL(12,2))) as spent 
                    FROM {$wpdb->prefix}woocommerce_order_items oi
                    JOIN {$wpdb->prefix}woocommerce_order_itemmeta om ON oi.order_item_id = om.order_item_id
                    JOIN {$ctx['table']} p ON oi.order_id = p.{$ctx['id']}
                    WHERE oi.order_item_type = 'coupon' 
                    AND LOWER(oi.order_item_name) = %s
                    AND om.meta_key = 'discount_amount'
                    AND p.{$ctx['status']} = %s {$date_sql_p}
                ", ...$bonus_stats_args));

                $data['orders_with_bonus'] = (int)$bonus_stats->count;
                $data['spent_total'] = (float)$bonus_stats->spent;

                // Средний чек
                $data['aov_total'] = $data['orders_total'] > 0 ? $data['total_revenue'] / $data['orders_total'] : 0;
                
                // Средний чек без бонусов
                $orders_no_bonus = $data['orders_total'] - $data['orders_with_bonus'];
                if ($ctx['hpos']) {
                     $rev_with_bonus = (float)$wpdb->get_var($wpdb->prepare("
                        SELECT SUM({$ctx['total']}) FROM {$ctx['table']} 
                        WHERE id IN (SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_type = 'coupon' AND LOWER(order_item_name) = %s)
                        AND status = %s AND type = 'shop_order' {$date_sql}
                     ", ...$bonus_stats_args));
                } else {
                     $rev_with_bonus = (float)$wpdb->get_var($wpdb->prepare("
                        SELECT SUM(CAST(m.meta_value AS DECIMAL(12,2))) FROM {$wpdb->prefix}postmeta m 
                        JOIN {$wpdb->prefix}posts p ON p.ID = m.post_id
                        WHERE m.meta_key = '_order_total' AND p.post_status = %s {$date_sql_p} AND m.post_id IN (
                            SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_type = 'coupon' AND LOWER(order_item_name) = %s
                        )
                     ", ...($date_start ? array_merge([$st], $date_args, [$cart_discount]) : [$st, $cart_discount])));
                }
                
                $rev_no_bonus = $data['total_revenue'] - $rev_with_bonus;
                $data['rev_no_bonus'] = (float)$rev_no_bonus;
                $data['aov_no_bonus'] = $orders_no_bonus > 0 ? $rev_no_bonus / $orders_no_bonus : 0;
                $data['aov_with_bonus'] = $data['orders_with_bonus'] > 0 ? $rev_with_bonus / $data['orders_with_bonus'] : 0;

                // Retention & LTV (Участников бонусной системы)
                $bonus_user_ids = $wpdb->get_col("SELECT DISTINCT user FROM {$wpdb->prefix}bfw_history_computy");
                if (!empty($bonus_user_ids)) {
                    $user_ids = array_map('intval', $bonus_user_ids);
                    $placeholders = implode(', ', array_fill(0, count($user_ids), '%d'));
                    
                    $args_loyal = $date_start ? array_merge([$st, $order_status], $date_args, $user_ids) : array_merge([$st, $order_status], $user_ids);
                    $loyal_count = (int)$wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*) FROM (
                            SELECT {$ctx['customer']} FROM {$ctx['table']}
                            WHERE {$ctx['status']} IN (%s, %s) AND {$ctx['type']} = 'shop_order' {$date_sql}
                            AND {$ctx['customer']} IN ($placeholders)
                            GROUP BY {$ctx['customer']}
                            HAVING COUNT({$ctx['id']}) > 1
                        ) as loyal
                    ", ...$args_loyal));

                    $total_bonus_users = count($bonus_user_ids);
                    $data['retention_rate'] = $total_bonus_users > 0 ? ($loyal_count / $total_bonus_users) * 100 : 0;

                    $args_rev = $date_start ? array_merge([$st, $order_status], $date_args, $user_ids) : array_merge([$st, $order_status], $user_ids);
                    $total_bonus_rev = (float)$wpdb->get_var($wpdb->prepare("
                        SELECT SUM({$ctx['total']}) FROM {$ctx['table']}
                        WHERE {$ctx['status']} IN (%s, %s) AND {$ctx['type']} = 'shop_order' {$date_sql} AND {$ctx['customer']} IN ($placeholders) 
                    ", ...$args_rev));
                    $data['ltv_bonus'] = $total_bonus_users > 0 ? $total_bonus_rev / $total_bonus_users : 0;
                }

                $data['timestamp'] = current_time('timestamp');
                update_option(self::PARTIAL_OPTION, $data);
                wp_send_json_success(['paged' => 0]);
                
            case 4:
                // Trend Chart (Line Chart data)
                $data = get_option(self::PARTIAL_OPTION, []);
                
                $trend = $wpdb->get_results($wpdb->prepare("
                    SELECT 
                        DATE(date) as day, 
                        SUM(CASE WHEN symbol = '+' THEN points ELSE 0 END) as issued,
                        ABS(SUM(CASE WHEN symbol = '-' THEN points ELSE 0 END)) as spent
                    FROM {$wpdb->prefix}bfw_history_computy
                    WHERE 1=1 {$date_filter_history}
                    GROUP BY DATE(date)
                    ORDER BY day ASC
                ", ...($date_args ?: ['1'])));

                $chart_labels = [];
                $chart_issued = [];
                $chart_spent = [];
                
                foreach ($trend as $t) {
                    // if range is wide, could use month, but daily is good
                    $chart_labels[] = $t->day;
                    $chart_issued[] = (float)$t->issued;
                    $chart_spent[] = (float)$t->spent;
                }
                
                $data['chart_data'] = [
                    'labels' => $chart_labels,
                    'issued' => $chart_issued,
                    'spent' => $chart_spent
                ];

                $data['timestamp'] = current_time('timestamp');
                update_option(self::OPTION_KEY, $data);
                delete_option(self::PARTIAL_OPTION);
                wp_send_json_success(['done' => true]);


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
        check_ajax_referer('bfw_stat_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        delete_option(self::OPTION_KEY);
        delete_option('bfw_partial_statistics'); // если используется
        wp_send_json_success(['message' => 'Статистика очищена']);
    }

}