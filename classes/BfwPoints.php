<?php

defined('ABSPATH') || exit;


use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Class Points
 *
 * @version 2.5.1
 */
class BfwPoints
{


    /**
     * Declension of nouns after numerals.
     * Склонение существительных после числительных.
     *
     * @param float $points
     * @param bool $show Включает значение $value в результирующею строку
     *
     * @return string
     * @version 2.10.1
     */
    public static function pointsLabel(float $points, bool $show = true): string
    {
        $label_point = BfwSetting::get('label_point', __('Point', 'bonus-for-woo'));
        $label_point_two = BfwSetting::get('label_point_two', __('Points', 'bonus-for-woo'));
        $label_points = BfwSetting::get('label_points', __('Points', 'bonus-for-woo'));

        return BfwFunctions::declination($points, $label_point, $label_point_two, $label_points);
    }


    /**
     * This solution is for those parameters where the preposition "up to" is used before the number, for example "up to 101 points"
     * Данное решение для тех параметров, где используется перед числом предлог "до" например "до 101 балла"
     *
     * @param float $points
     * @param bool $show
     *
     * @return string
     * @version 6.4.0
     */
    public static function pointsLabelUp(float $points, bool $show = true): string
    {
        $label_point_two = BfwSetting::get('label_point_two', __('Points', 'bonus-for-woo'));
        $label_point_two = sanitize_text_field($label_point_two);
        $label_points = BfwSetting::get('label_points', __('Points', 'bonus-for-woo'));
        $label_points = sanitize_text_field($label_points);
        return BfwFunctions::declination($points, $label_point_two, $label_points, $label_points);
    }


    /**
     * The method decides whether to use the method with "before" or without "before"
     * Метод решает какой использовать метод с "до" или без "до"
     *
     * @param string $up
     * @param float $points
     *
     * @return string
     * @version 6.4.0
     */
    public static function howLabel(string $up, float $points): string
    {
        if (!BfwSetting::get('bonus-in-price-upto') && !empty($up)) {
            return self::pointsLabelUp($points);
        }

        return self::pointsLabel($points);
    }


    /**
     * Returns the number of bonus points the user has
     * Возвращает количество бонусных баллов пользователя
     *
     * @param int $userId
     *
     * @return float
     * @version 2.5.1
     */
    public static function getPoints(int $userId): float
    {
        $points = get_user_meta($userId, 'computy_point', true) ?? 0;
        return (float) $points;
    }


    /**
     * Rounds off points to the required number
     * Округляет баллы до нужного числа
     *
     * @param float $points
     *
     * @return float
     * @since  6.3.4
     */
    public static function roundPoints(float $points): float
    {
        $precision = BfwSetting::get('round_points') ? 2 : 0;
        return round($points, $precision);
    }


    /**
     * Returns the user's points that he wants to write off
     * Возвращает баллы пользователя, которые он хочет списать
     *
     * @param int $userId
     *
     * @return float
     * @version 2.5.1
     */
    public static function getFastPoints(int $userId): float
    {
        $points = get_user_meta($userId, 'computy_fast_point', true);
        return (float) $points;
    }


    /**
     * Refresh Bonus Points
     * Обновление бонусных баллов
     *
     * @param int $userId
     * @param float $newBalls
     *
     * @return bool
     * @version 4.8.0
     * @since 2.5.1
     */
    public static function updatePoints(int $userId, float $newBalls): bool
    {
        $newBalls = max(0, $newBalls);
        $newBalls = apply_filters('bfw-update-points-filter', $newBalls, $userId);
        if (update_user_meta($userId, 'computy_point', $newBalls)) {
            return true;
        }
        return false;
    }


    /**
     * Updating the points that the user wants to write off
     * Обновление баллов, которые пользователь хочет списать
     *
     * @param int $userId
     * @param float $newBalls
     *
     * @version 4.8.0
     * @since 2.5.1
     */
    public static function updateFastPoints(int $userId, float $newBalls): void
    {
        $newBall = max(0, $newBalls);
        update_user_meta($userId, 'computy_fast_point', $newBall);
    }



    /**
     * Find the sum of all paid orders of the client
     * Находим сумму всех оплаченных заказов клиента
     * так как wc_get_customer_total_spent ($to_user->ID); включает сумму не оплаченных заказов тоже.
     *
     * @param int|null $userId
     * @return float
     * @version 6.5.1
     */
    public static function getSumUserOrders($userId = null): float
    {
        $userId = self::resolveUserId($userId);
        if ($userId === 0) {
            return 0.0;
        }

        // Check cache first
        $cached_sum = get_user_meta($userId, 'total_purchases_sum', true);
        if ($cached_sum !== '') {
            return (float) $cached_sum;
        }

        $total = self::calculateTotalPurchases($userId);

        update_user_meta($userId, 'total_purchases_sum', $total);

        return $total;
    }

    /**
     * Resolve user ID from input or current user
     */
    private static function resolveUserId($userId): int
    {
        if ($userId !== null) {
            return (int) $userId;
        }

        $current_user = get_current_user_id();
        return $current_user ?: 0;
    }

    /**
     * Calculate total purchases for a user
     */
    private static function calculateTotalPurchases(int $userId): float
    {
        global $wpdb;

        $statuses = self::getOrderStatuses();
        $dateFilter = self::getDateFilter();
        $excludeShipping = !BfwSetting::get('shipping-total-sum');

        if (self::isCustomOrdersTableEnabled()) {
            $total = self::calculateTotalFromOrdersTable($wpdb, $userId, $statuses, $dateFilter, $excludeShipping);
        } else {
            $total = self::calculateTotalFromPostsTable($wpdb, $userId, $statuses, $dateFilter, $excludeShipping);
        }

        return max(0.0, (float) $total);
    }

    /**
     * Get order statuses with wc- prefix
     */
    private static function getOrderStatuses(): array
    {
        $statuses = sanitize_text_field(BfwSetting::get('add_points_order_status', 'completed'));
        $statuses = apply_filters('bfw_add_points_order_status_filter', $statuses);

        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }

        // Add 'wc-' prefix and escape once
        return array_map(function($status) {
            return 'wc-' . esc_sql($status);
        }, $statuses);
    }

    /**
     * Get date filter for pro users
     */
    private static function getDateFilter(): string
    {
        if (!BfwRoles::isPro()) {
            return '';
        }

        $dataStart = BfwSetting::get('order_start_date', '');
        if (empty($dataStart)) {
            return '';
        }
global $wpdb;
        $dataStart = sanitize_text_field($dataStart);
        $dateColumn = self::isCustomOrdersTableEnabled() ? 'date_created_gmt' : 'p.post_date';

        return $wpdb->prepare(" AND {$dateColumn} >= %s ", $dataStart);
    }

    /**
     * Check if custom orders table is enabled
     */
    private static function isCustomOrdersTableEnabled(): bool
    {
        return class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Calculate total from HPOS (custom orders table)
     */
    private static function calculateTotalFromOrdersTable($wpdb, int $userId, array $statuses, string $dateFilter, bool $excludeShipping): float
    {
        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
        $args = array_merge($statuses, [$userId]);

        // Single query to get order totals and shipping in one go
        $query = $wpdb->prepare(
            "SELECT 
            SUM(o.total_amount) as total,
            " . ($excludeShipping ? "0" : "SUM(odata.shipping_total_amount)") . " as shipping
        FROM {$wpdb->prefix}wc_orders o
        LEFT JOIN {$wpdb->prefix}wc_order_operational_data odata ON o.id = odata.order_id
        WHERE o.status IN ({$placeholders}) 
        AND o.customer_id = %d 
        {$dateFilter}",
            ...$args
        );

        $result = $wpdb->get_row($query);

        if (!$result) {
            return 0.0;
        }

        $total = (float) $result->total;
        $shipping = (float) $result->shipping;

        // Subtract refunds
        $refunds = self::getRefundsFromOrdersTable($wpdb, $userId);

        return max(0.0, $total - $refunds - $shipping);
    }

    /**
     * Get refunds from HPOS
     */
    private static function getRefundsFromOrdersTable($wpdb, int $userId): float
    {
        $refund = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(meta_value) 
        FROM {$wpdb->prefix}wc_orders_meta 
        WHERE meta_key = '_refund_amount' 
        AND order_id IN (
            SELECT id 
            FROM {$wpdb->prefix}wc_orders 
            WHERE parent_order_id IN (
                SELECT id 
                FROM {$wpdb->prefix}wc_orders 
                WHERE customer_id = %d
            )
        )",
            $userId
        ));

        return (float) $refund;
    }

    /**
     * Calculate total from posts table (legacy)
     */
    private static function calculateTotalFromPostsTable($wpdb, int $userId, array $statuses, string $dateFilter, bool $excludeShipping): float
    {
        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
        $args = array_merge($statuses, [$userId]);

        // Optimized query using joins instead of subqueries
        $query = $wpdb->prepare(
            "SELECT 
            SUM(CASE WHEN pm.meta_key = '_order_total' THEN CAST(pm.meta_value AS DECIMAL(15,2)) ELSE 0 END) as total,
            " . ($excludeShipping ? "0" : "
            SUM(CASE WHEN pm.meta_key = '_order_shipping' THEN CAST(pm.meta_value AS DECIMAL(15,2)) ELSE 0 END)
            ") . " as shipping,
            SUM(CASE WHEN pm.meta_key = '_order_refund_amount' THEN CAST(pm.meta_value AS DECIMAL(15,2)) ELSE 0 END) as refunds
        FROM {$wpdb->prefix}posts p
        INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_status IN ({$placeholders})
        AND p.ID IN (
            SELECT post_id 
            FROM {$wpdb->prefix}postmeta 
            WHERE meta_key = '_customer_user' 
            AND meta_value = %d
        )
        {$dateFilter}
        AND pm.meta_key IN ('_order_total', '_order_shipping', '_order_refund_amount')",
            ...$args
        );

        $result = $wpdb->get_row($query);

        if (!$result) {
            return 0.0;
        }

        $total = (float) $result->total;
        $shipping = (float) $result->shipping;
        $refunds = (float) $result->refunds;

        return max(0.0, $total - $refunds - $shipping);
    }

    /**
     * Invalidate cache for total purchases sum
     *
     * @param int $order_id
     */
    public static function clearUserOrdersSumCache(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if ($order) {
            $user_id = $order->get_customer_id();
            if ($user_id) {
                delete_user_meta($user_id, 'total_purchases_sum');
            }
        }
    }

    /**
     * Carrying out an offline order
     * Проведение оффлайн-заказа
     *
     * @param $price float
     * @param $user_id int
     *
     * @return void
     * @version 5.10.0
     * @since 5.1.0
     */
    public static function addOfflineOrder(float $price, int $user_id): void
    {
        /*1. Создаем офлайн продукт*/
        $offline_product = get_option('bonus-for-woo-offline-product');
        if (get_post($offline_product)) {
            BfwFunctions::setPostStatusBfw('publish', $offline_product);
            $post_id = $offline_product;
        } else {
            $post_title = BfwSetting::get('title-product-offline-order', __('Offline product', 'bonus-for-woo'));

            $post_id = wp_insert_post(array(
                'post_title' => $post_title,
                'post_type' => 'product',
                'post_status' => 'publish',
                'post_content' => __('Technical product for accrual of bonus points', 'bonus-for-woo')
            ));

            wp_set_object_terms($post_id, 'simple', 'product_type');
            update_post_meta($post_id, '_visibility', 'hidden');/*скрыть с каталога*/
            update_post_meta($post_id, '_stock_status', 'instock');
            update_post_meta($post_id, '_virtual', 'yes');
            update_post_meta($post_id, '_regular_price', "1");
            update_post_meta($post_id, '_price', "1");

            update_option('bonus-for-woo-offline-product', $post_id);/*указываем товар для проведения продаж офлайн*/
        }

        /*2. Создаем заказ клиенту на нужную сумму*/
        $order = wc_create_order();
        $order->add_product(wc_get_product($post_id), $price);
        // Установим платёжный метод, например пусть это будет оплата наличными при получении
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        if (!empty($payment_gateways['cod'])) {
            $order->set_payment_method($payment_gateways['cod']);
        }

        $add_points_order_status = BfwSetting::get('add_points_order_status', 'completed');

        // Пересчитываем заказ
        $order->calculate_totals();
        $current_user = wp_get_current_user();
        $order->add_order_note(__('Order created by administrator: ', 'bonus-for-woo') . $current_user->user_login);

        $order->set_customer_id($user_id);

        if ($order->update_status($add_points_order_status)) {
            /*3. кидаем офлайн продукт в черновики*/
            BfwFunctions::setPostStatusBfw('draft', $post_id);
        }
    }


    /**
     * Earn daily points for your first login
     * Начисление ежедневных баллов за первый вход. Карова.
     *
     * @param $user_id int
     *
     * @return void
     * @version 5.2.0
     * @since 5.2.0
     */
    public static function addEveryDays(int $user_id): void
    {
        if (BfwRoles::isInvalve($user_id)) {
            $point_every_day = BfwSetting::get('every_days', 0);
            if ((int) $point_every_day > 0) {
                //Проверяем получал ли сегодня клиент баллы
                $last_day = get_user_meta($user_id, 'points_every_day', true);
                if ($last_day !== gmdate('d')) {
                    //обновляем день
                    update_user_meta($user_id, 'points_every_day', gmdate('d'));
                    //Узнаем количество баллов клиента
                    $count_point = static::getPoints($user_id);
                    $new_point = $count_point + $point_every_day;


                    //Начисляем баллы клиенту
                    static::updatePoints($user_id, $new_point);


                    $cause = sprintf(
                        __('Daily %s for the login.', 'bonus-for-woo'),
                        strtolower(BfwSetting::get('bonus-points-on-cart'))
                    );

                    //Записываем в историю
                    BfwHistory::add_history($user_id, '+', $point_every_day, '0', $cause);

                    // Добавляем лог
                    BfwLogs::addLog('add_points', $user_id, $cause);

                    //отправляем письмо

                    if (BfwSetting::get('email-when-everyday-login')) {
                        /*Шаблонизатор письма*/

                        $text_email = BfwSetting::get('email-when-everyday-login-text', '');

                        $title_email = BfwSetting::get(
                            'email-when-everyday-login-title',
                            __('Bonus points have been added to you!', 'bonus-for-woo')
                        );

                        $user = get_userdata($user_id);
                        $text_email_array = array(
                            '[user]' => $user->display_name,
                            '[points]' => $point_every_day,
                            '[total]' => $new_point
                        );

                        $message_email = (new BfwEmail())::template($text_email, $text_email_array);
                        /*Шаблонизатор письма*/
                        (new BfwEmail())->getMail($user_id, '', $title_email, $message_email);
                    }
                }
            }
        }
    }


    /**
     * Displaying write-offs in the shopping cart
     * Вывод списания в корзине
     *
     * @return void
     */
    public static function bfwoo_spisaniebonusov_in_cart()
    {
        $redirect = wc_get_cart_url();
        echo self::bfw_write_off_points($redirect);
    }

    public static function bfwoo_spisaniebonusov_in_cart_shortcode()
    {

        $redirect = wc_get_cart_url();
        return self::bfw_write_off_points($redirect);
    }

    /**
     * Displaying write-offs in the shopping cart and checkout blocks
     * Вывод списания в корзине и оформлении заказа blocks
     *
     * @return void
     */
    public static function bfwoo_spisaniebonusov_in_cart_blocks()
    {
        // Проверка nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bfw_cart_blocks')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $redirect = esc_url_raw($_POST['redirect'] ?? '');
        if (empty($redirect)) {
            wp_send_json_error('Invalid redirect URL');
            return;
        }

        echo self::bfw_write_off_points($redirect);
        exit();
    }

    // Метод для получения HTML списания
    public static function rest_get_spisanie_html(WP_REST_Request $request)
    {
        // 1. Инициализация сессии
        if (null === WC()->session) {
            $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
            WC()->session = new $session_class();
            WC()->session->init();
        }

        // 2. Инициализация КЛИЕНТА (решает проблему get_shipping_country)
        if (null === WC()->customer) {
            WC()->customer = new WC_Customer(get_current_user_id(), true);
        }

        // 3. Инициализация корзины
        if (null === WC()->cart) {
            WC()->cart = new WC_Cart();
            WC()->cart->get_cart();
        }
        $params = $request->get_json_params();
        $redirect = isset($params['redirect']) ? $params['redirect'] : '';

        ob_start();
        $html = self::bfw_write_off_points($redirect);
        $buffered_content = ob_get_clean();

        // Если метод вернул пустую строку, но что-то попало в буфер
        if (empty($html)) {
            $html = $buffered_content;
        }

        return new WP_REST_Response(['html' => $html], 200);
    }


    /**
     * Display of points write-off in order processing
     * Вывод списания баллов в оформлении заказов
     *
     * @return void
     */
    public static function bfwoo_spisaniebonusov_in_checkout(): void
    {
        if (BfwSetting::get('spisanie-in-checkout')) {
            $redirect = wc_get_checkout_url();
            echo self::bfw_write_off_points($redirect);
        }

    }

    public static function bfwoo_spisaniebonusov_in_checkout_shortcode()
    {
        if (BfwSetting::get('spisanie-in-checkout')) {
            $redirect = wc_get_checkout_url();
            return self::bfw_write_off_points($redirect);
        }
    }


    /**
     * Write-off of points in the cart and checkout
     * Вывод списания баллов в корзине и оформлении заказа
     *
     * @param $redirect
     *
     * @return string
     * @version 6.5.5
     */
    public static function bfw_write_off_points($redirect): string
    {
        global $pagenow;
        if ($pagenow === 'post.php') {
            //Если находимся в редакторе страницы, то ничего не делаем
            return '';
        }

        $woo = WC();
        $user_id = get_current_user_id();

        if (!BfwRoles::isInvalve($user_id)) {
            return '';
        }
        $computy_point = self::roundPoints(self::getPoints($user_id));
        $user_fast_points = self::getFastPoints($user_id);
        $cart_discount_name = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
        $cart_discount = mb_strtolower($cart_discount_name);

        //удалить купон если нет баллов
        if ($user_fast_points == 0 && $woo->cart->has_discount($cart_discount)) {
            $woo->cart->remove_coupon($cart_discount);
        }

        $use_points_on_cart = BfwSetting::get('use-points-on-cart', __('Use points', 'bonus-for-woo'));
        $bonustext_in_cart4 = BfwSetting::get('bonustext-in-cart4', __('Apply', 'bonus-for-woo'));
        $bonustext_in_cart5 = BfwSetting::get('bonustext-in-cart5', __('Total', 'bonus-for-woo'));

        $head = '<div class="bfw-write-off-block"> <div class="bfw-spisanie-blocks-button">
    <span class="bfw-spisanie-blocks-button-text">' . $use_points_on_cart . '</span>
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" class="wc-block-components-panel__button-icon"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg>
  </div><div class="computy_skidka_form">';

        $foot = ' <div class="text_total_points">' . $bonustext_in_cart5 . ': ' . $computy_point . ' ' . self::pointsLabel($computy_point) . '</div></div></div>';

        $not_use_points_text = $head . ' <div class="text_how_many_points">' . __(
                'You cannot use points on this order.',
                'bonus-for-woo'
            ) . '</div>' . $foot;


        $maxPossiblePoints = self::maxPossiblePointsInOrder(); //максимальное кол-во баллов, которое можно потратить в текущем заказе

        //return $maxPossiblePoints;

        if ($maxPossiblePoints == 0 || $computy_point == 0) {
            return apply_filters('bfw_not_use_points_text', $not_use_points_text);
        }


        if ($computy_point > 0) {

            $vozmojniy_ball = self::roundPoints(min($computy_point, $maxPossiblePoints));

            if ($vozmojniy_ball == 0) {
                return apply_filters('bfw_not_use_points_text', $not_use_points_text);
            }

            if ($woo->cart->applied_coupons && BfwSetting::get('balls-and-coupon')) {
                /*Если применяется купон*/

                if (
                    !in_array($cart_discount, $woo->cart->get_applied_coupons()) or in_array(
                        $cart_discount,
                        $woo->cart->get_applied_coupons()
                    ) && count($woo->cart->get_applied_coupons()) > 1
                ) {
                    self::updateFastPoints($user_id, 0);
                    $woo->cart->remove_coupon($cart_discount);
                    $woo->cart->calculate_totals();//Пересчет общей суммы заказа
                    return $head . ' <div class="text_how_many_points">' . sprintf(__(
                            'To use %s, you must remove the coupon.',
                            'bonus-for-woo'
                        ), self::pointsLabel(5)) . '</div>' . $foot;
                }

            }


            if (BfwSetting::get('minimal-amount')) {
                $carttotal = $woo->cart->subtotal;
                // $carttotal = $woo->cart->total;

                $user_fast_points = self::getFastPoints($user_id);
                if ($carttotal < (int) BfwSetting::get('minimal-amount')) {
                    //сумма заказа должна быть больше чем 'minimal-amount'
                    foreach ($woo->cart->get_applied_coupons() as $code) {
                        if (strtolower($code) === mb_strtolower($cart_discount)) {
                            $woo->cart->remove_coupon($code);
                        }
                    }
                    self::updateFastPoints($user_id, 0);
                    $woo->cart->calculate_totals();
                    return $head . ' <div class="text_how_many_points">' . sprintf(
                            __(
                                'To use %s, the order amount must be more than',
                                'bonus-for-woo'
                            ),
                            self::pointsLabel(5)
                        ) . ' ' . BfwSetting::get('minimal-amount') . ' ' . get_woocommerce_currency_symbol() . '</div>' . $foot;


                }
            }


            if ($vozmojniy_ball < $user_fast_points) {
                $woo->cart->remove_coupon($cart_discount);
                $woo->cart->calculate_totals();
            }

            $bonustext_in_cart = BfwSetting::get(
                'bonustext-in-cart',
                __('You can use [points] in this order.', 'bonus-for-woo')
            );

            $bonustext_in_cart_array = [
                '[points]' => '<b>' . $vozmojniy_ball . ' ' . self::pointsLabel($vozmojniy_ball) . '</b>',
                '[discount]' => '<b>' . $vozmojniy_ball . ' ' . get_woocommerce_currency_symbol() . '</b>'
            ];
            $bonustext_in_carts = (new BfwEmail())::template($bonustext_in_cart, $bonustext_in_cart_array);


            $return = '<div class="text_how_many_points">' . $bonustext_in_carts . '</div>
<div class="write_points_form">
 
        <input type="hidden" name="action" value="computy_trata_points">
        <input type="hidden" name="redirect" value="' . $redirect . '">
        <input type="hidden" name="_wpnonce" value="' . wp_create_nonce('bfw_trata_points') . '">
        <input type="text" name="computy_input_points" class="input-text" value="' . self::roundPoints(($user_fast_points > 0 ? $user_fast_points : $vozmojniy_ball)) . '">
       <button type="button" class="button write_points" >
    <span class="button__text">' . $bonustext_in_cart4 . '</span>
</button>
        
        </div>
        
        
        ';
        } else {
            return apply_filters('bfw_not_use_points_text', $not_use_points_text);
        }

        if (self::getFastPoints($user_id) > 0) {
            $removeOnCart = BfwSetting::get('remove-on-cart', __('Remove points', 'bonus-for-woo'));

            $return .= '<div class="remove_points_form">
                <input type="hidden" name="action" value="clear_bonus" />
                <input type="hidden" name="redirect" value="' . $redirect . '">
                
                
                <button type="button" class="remove_points" >
    <span class="button__text">' . $removeOnCart . '</span>
</button>
               
            </div>';
        }

        $woo->cart->calculate_totals();//Пересчет общей суммы заказа
        return $head . $return . $foot;
    }


    /**
     * Spending points
     * Трата баллов
     *
     * @return void
     * @version 6.7.5
     */
    public static function bfwoo_trata_points(): void
    {
        // Проверка nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'bfw_trata_points')) {
            wp_send_json_error('Security check failed');
            return;
        }

        if (!isset($_POST['computy_input_points'], $_POST['redirect'])) {
            wp_send_json_error(__('The required data is missing from the request.', 'bonus-for-woo'));
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('Error getting user ID.', 'bonus-for-woo'));
            return;
        }

        $requestedPoints = self::roundPoints((float) ($_POST['computy_input_points'] ?? 0));
        $redirect = esc_url_raw($_POST['redirect'] ?? '');

        if ($requestedPoints <= 0) {
            wp_send_json_success($redirect);
            return;
        }
        try {
            $woocommerce = WC();
            // Не начисляем кешбэк если применился сторонний купон
            if ($woocommerce->cart->applied_coupons && BfwSetting::get('yous_coupon_no_cashback')) {
                /*Если применяется купон*/
                $cart_discount_name = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
                $cart_discount = mb_strtolower($cart_discount_name);
  //если система с помощью купонов
                if (
                    !in_array($cart_discount, $woocommerce->cart->get_applied_coupons()) or in_array(
                        $cart_discount,
                        $woocommerce->cart->get_applied_coupons()
                    ) && count($woocommerce->cart->get_applied_coupons()) > 1
                ) {
                    $requestedPoints = 0;
                }
            }

            $maxPossiblePoints = self::maxPossiblePointsInOrder();
            $userPoints = self::roundPoints(self::getPoints($user_id));

            $pointsToApply = min($requestedPoints, $maxPossiblePoints, $userPoints);

            self::updateFastPoints($user_id, $pointsToApply);

            wp_send_json_success($_POST['redirect']);
        } catch (Exception $e) {
            wp_send_json_error(__('An error occurred while processing your request.', 'bonus-for-woo'));
        }

    }

    public static function rest_trata_points(WP_REST_Request $request): WP_REST_Response
    {
        // Проверка, инициализирован ли WooCommerce и корзина
        if (null === WC()->cart) {
            wc_load_cart(); // Принудительная загрузка, если эндпоинт вызван слишком рано
        }
        $params = $request->get_json_params();
        $requestedPoints = isset($params['points']) ? self::roundPoints((float) $params['points']) : 0;
        $redirect = isset($params['redirect']) ? esc_url($params['redirect']) : wc_get_cart_url();

        $user_id = get_current_user_id();

        if ($requestedPoints <= 0) {
            return new WP_REST_Response(['success' => true, 'data' => $redirect], 200);
        }

        try {
            $woocommerce = WC();

            // Проверка на сторонние купоны
            if ($woocommerce->cart->applied_coupons && BfwSetting::get('yous_coupon_no_cashback')) {
                $cart_discount_name = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
                $cart_discount = mb_strtolower($cart_discount_name);
                $applied = $woocommerce->cart->get_applied_coupons();

                if (!in_array($cart_discount, $applied) || count($applied) > 1) {
                    $requestedPoints = 0;
                }
            }

            $maxPossiblePoints = self::maxPossiblePointsInOrder();
            $userPoints = self::roundPoints(self::getPoints($user_id));

            $pointsToApply = min($requestedPoints, $maxPossiblePoints, $userPoints);

            self::updateFastPoints($user_id, $pointsToApply);

            // Пересчитываем корзину, чтобы купон применился сразу
            $woocommerce->cart->calculate_totals();

            return new WP_REST_Response(['success' => true, 'data' => $redirect], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Находим максимальное кол-во баллов, которое можно потратить в текущем заказе.
     * Эта цифра не зависит от пользователя
     *
     * @return float
     */
    public static function maxPossiblePointsInOrder(): float
    {
        $BfwRoles = new BfwRoles();
        $woo = WC();

        if (!isset($woo->cart) || !is_object($woo->cart) || !method_exists($woo->cart, 'get_cart')) {
            return 0;
        }

        $items = $woo->cart->get_cart();
        $is_pro = $BfwRoles::isPro();

        // Get exclusion settings
        $exclude_onsale = BfwSetting::get('spisanie-onsale');
        $excluded_cats = $is_pro ? BfwSetting::get('exclude-category-cashback', 'not') : 'not';
        $exclude_products_raw = $is_pro ? BfwSetting::get('exclude-tovar-cashback', '') : '';

        $excluded_products = array_filter(explode(',', $exclude_products_raw));
        $excluded_products = apply_filters('bfw-excluded-products-filter', $excluded_products, $exclude_products_raw);

        $eligible_subtotal = 0;

        foreach ($items as $item) {
            $product = $item['data'];
            $product_id = $item['product_id'];

            $is_eligible = true;

            // 1. Check Sale
            if ($exclude_onsale && $product->is_on_sale()) {
                $is_eligible = false;
            }

            // 2. Check Category (Pro)
            if ($is_eligible && $is_pro && $excluded_cats !== 'not' && !empty($excluded_cats)) {
                if (has_term($excluded_cats, 'product_cat', $product_id)) {
                    $is_eligible = false;
                }
            }

            // 3. Check Product (Pro)
            if ($is_eligible && $is_pro && !empty($excluded_products)) {
                if (in_array($product_id, $excluded_products)) {
                    $is_eligible = false;
                }
            }

            if ($is_eligible) {
                $eligible_subtotal += $product->get_price() * $item['quantity'];
            }
        }

        // Subtract cart-wide coupons from eligible subtotal
        $applied_coupons = $woo->cart->get_applied_coupons();
        $cart_discount_name = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
        $bonus_coupon_code = mb_strtolower($cart_discount_name);


        foreach ($applied_coupons as $coupon_code) {
            if ($coupon_code !== $bonus_coupon_code) {
                $discount_amount = $woo->cart->get_coupon_discount_amount($coupon_code);
                $eligible_subtotal -= $discount_amount;
            }
        }

        /*Максимальный процент списания для про*/
        $max_percent = $is_pro ? BfwSetting::get('max-percent-bonuses', 100) : 100;
        $max_percent = apply_filters('max-percent-bonuses-filter', $max_percent, $eligible_subtotal);

        $maxPossiblePoints = self::roundPoints($eligible_subtotal * $max_percent / 100);

        return max(0.0, $maxPossiblePoints);
    }


    /**
     * Clearing temporary points
     * Очищение временных баллов
     *
     * @return void
     * @version 5.3.3
     */
    /*public static function bfwoo_clean_fast_bonus(): void
    {
        // Получаем ID текущего пользователя
        $userId = get_current_user_id();

        // Обнуляем быстрые бонусные баллы
        self::updateFastPoints($userId, 0);

        // Проверяем, есть ли в опциях бонусные баллы в корзине
        if (BfwSetting::get('bonus-points-on-cart')) {
            $cartDiscount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
            $woo = WC();

            // Проверяем, есть ли корзина и примененные купоны
            if (isset($woo->cart) && $woo->cart->get_applied_coupons()) {
                foreach ($woo->cart->get_applied_coupons() as $code) {
                    if (strtolower($code) === $cartDiscount) {
                        $woo->cart->remove_coupon($code);
                    }
                }
            }
        }

        // Проверяем, есть ли редирект в POST-запросе
        if (isset($_POST['redirect'])) {
            wp_send_json_success($_POST['redirect']);
        }
    } */
    public static function bfwoo_clean_fast_bonus_rest(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        self::updateFastPoints($userId, 0);
        $cart_discount_name = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
        $cartDiscount = mb_strtolower($cart_discount_name);
        if ($cartDiscount) {
            $cartDiscount = mb_strtolower(trim($cartDiscount));
            $cart = WC()->cart;

            if ($cart) {
                // Удаляем из корзины
                if (in_array($cartDiscount, array_map('strtolower', $cart->get_applied_coupons()), true)) {
                    $cart->remove_coupon($cartDiscount);
                    $cart->calculate_totals();
                }

                // Очистить applied_coupons в сессии
                $applied = WC()->session->get('applied_coupons', []);
                $applied = array_filter($applied, function ($code) use ($cartDiscount) {
                    return strtolower(trim($code)) !== $cartDiscount;
                });
                WC()->session->set('applied_coupons', array_values($applied));

                wc_clear_notices();
            }
        }

        $redirect = $request->get_param('redirect') ?: home_url();
        return new WP_REST_Response(['success' => true, 'data' => $redirect], 200);
    }

    /**
     * Earning points from a coupon
     * Получение баллов с купона
     * REST API Callback
     *
     * @return void
     * @version 7.6.4
     *
     *
     */
    public static function rest_activate_coupon(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        $code_coupon = isset($params['code_coupon']) ? sanitize_text_field($params['code_coupon']) : '';
        $redirect_url = isset($params['redirect']) ? esc_url($params['redirect']) : home_url();

        if (empty($code_coupon)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Please enter a coupon code.', 'bonus-for-woo')
            ], 400);
        }

        $userid = get_current_user_id();
        $bfw_coupons = new BfwCoupons();
        $zapros = $bfw_coupons::enterCoupon($userid, $code_coupon);

        // Логика определения ответа
        $status_code = 200;
        if ($zapros === 'limit') {
            $status_code = 404;
            $message = __('Sorry. The coupon usage limit has been reached.', 'bonus-for-woo');
        } elseif ($zapros === 'not_coupon') {
            $status_code = 404;
            $message = __('Sorry, no such coupon found.', 'bonus-for-woo');
        } else {
            $message = __('Coupon activated.', 'bonus-for-woo');
        }

        return new WP_REST_Response([
            'cod' => (string) $status_code,
            'message' => esc_html($message),
            'redirect' => $redirect_url
        ], 200);
    }


    /**
     * Списание баллов в редакторе заказа
     *
     * @return void
     * @version 6.7.0
     */
    public static function handle_deduct_points_in_order(): void
    {
        check_ajax_referer('bfw_order_points_nonce', 'security');

        if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied.', 'bonus-for-woo'));
            return;
        }

        if (!isset($_POST['order_id'], $_POST['points'])) {
            wp_send_json_error(__('Not enough data.', 'bonus-for-woo'));
            return;
        }

        $order_id = (int) $_POST['order_id'];
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Order not found');
            return;
        }

        $user_id = $order->get_user_id();
        $points = (float) $_POST['points'];

        if ($points <= 0) {
            wp_send_json_error('Invalid points amount');
            return;
        }
        //сколько баллов у пользователя
        $balluser = BfwPoints::getPoints($user_id);
        if ($balluser < $_POST['points']) {
            wp_send_json_error(__('The user does not have enough points. Balance', 'bonus-for-woo') . $balluser);
        }
        $points = (float) $_POST['points'];
        BfwPoints::updateFastPoints($user_id, $points);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(__('Order not found.', 'bonus-for-woo'));
        }

        // Создаем уникальный код купона
        $cart_discount_name = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
        $coupon_code = mb_strtolower($cart_discount_name);
        // Создаем купон
        $coupon = array(
            'post_title' => $coupon_code,
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'shop_coupon'
        );

        $new_coupon_id = wp_insert_post($coupon);

        if (is_wp_error($new_coupon_id)) {
            wp_send_json_error(__('Error creating coupon.', 'bonus-for-woo'));
        }

        // Устанавливаем параметры купона
        update_post_meta($new_coupon_id, 'discount_type', 'fixed_cart'); // Тип скидки (фиксированная сумма)
        update_post_meta($new_coupon_id, 'coupon_amount', $points); // Сумма скидки
        update_post_meta($new_coupon_id, 'individual_use', 'yes'); // Купон только для индивидуального использования
        update_post_meta($new_coupon_id, 'usage_limit', 1); // Ограничение на использование (1 раз)
        update_post_meta($new_coupon_id, 'expiry_date', ''); // Срок действия (необязательно)

        // Применяем купон к заказу
        if ($order->apply_coupon($coupon_code)) {
            // Сохраняем информацию о списании баллов в метаданные заказа
            $order->update_meta_data('_points_deducted', $points);
            $order->save();
            wp_delete_post($new_coupon_id, true); // Удаляем купон из базы данных

            $prichina = sprintf(__('Use of %s', 'bonus-for-woo'), self::pointsLabel(5));
            BfwHistory::add_history($user_id, '-', $points, $order_id, $prichina);
            self::updateFastPoints($user_id, 0);

            $count_point = $balluser - $points;
            self::updatePoints($user_id, $count_point);

            // Добавляем лог
            BfwLogs::addLog('remove_points', $user_id, $prichina);

            wp_send_json_success(__(
                'The coupon has been successfully created and applied to your order.',
                'bonus-for-woo'
            ));
        } else {
            wp_send_json_error(__('Failed to apply coupon to order.', 'bonus-for-woo'));
        }
    }


    /**
     * Возврат баллов при удалении купона бонусных баллов в редакторе заказа
     *
     * @return void
     * @version 6.7.1
     */
    public static function handle_track_coupon_removal(): void
    {
        check_ajax_referer('bfw_order_points_nonce', 'security');

        if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied.', 'bonus-for-woo'));
            return;
        }
        $order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';

        if ($order_id && $coupon_code) {

            $order = wc_get_order($order_id);
            $customer_user = $order->get_customer_id();
            $computy_point_old = self::getPoints($customer_user);
            $fee_total = BfwFunctions::feeOrCoupon($order);
            $count_point = $computy_point_old + $fee_total;
            $cause = __('Refund of bonus points', 'bonus-for-woo');
            if ($fee_total > 0) {
                /*Добавляем списанные баллы*/
                BfwHistory::add_history($customer_user, '+', $fee_total, $order_id, $cause);
                self::updatePoints($customer_user, $count_point);//Обновляем баллы клиенту
                wp_send_json_success(__('The user points have been returned.', 'bonus-for-woo'));

            }
            wp_send_json_success(__('Points were not returned to the user.', 'bonus-for-woo'));


        } else {
            wp_send_json_error(__('Error: Data not transferred.', 'bonus-for-woo'));
        }
    }


    public static function bfwoo_add_fee($cart)
    {
        static $is_calculating = false;
        if ($is_calculating)
            return;
        if (is_admin() && !wp_doing_ajax())
            return;

        $couponCodeSetting = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');;
        if (!$cart || $cart->is_empty() || !$couponCodeSetting)
            return;
        $userId = get_current_user_id();
        if (!$userId || BfwPoints::getFastPoints($userId) <= 0)
            return;
        $couponCode = mb_strtolower($couponCodeSetting);
        
        // ПУНКТ 1: Если купона нет в списке, ПРИНУДИТЕЛЬНО применяем его
        if (!$cart->has_discount($couponCode)) {
            $is_calculating = true;
            
            // Применяем купон стандартным методом
            $cart->apply_coupon($couponCode);
            
            // СИЛОВОЙ МЕТОД: Если после apply_coupon он все еще не появился, добавляем его в массив принудительно
            if (!$cart->has_discount($couponCode)) {
                $applied_coupons = $cart->get_applied_coupons();
                if (!in_array($couponCode, $applied_coupons)) {
                    $cart->applied_coupons[] = $couponCode;
                }
            }
            
            // Проверяем сессию и сохраняем примененные купоны
            if (WC()->session) {
                $session_coupons = WC()->session->get('applied_coupons', []);
                if (!in_array($couponCode, $session_coupons)) {
                    $session_coupons[] = $couponCode;
                    WC()->session->set('applied_coupons', $session_coupons);
                }
            }
            
            if (wp_doing_ajax()) {
                WC()->session->set('cart_totals', null);
                WC()->session->set('shipping_for_package_0', null);
            }
            
            $is_calculating = false;
        }
        
        // Проверяем, что купон применился и баллы есть
        if ($cart->has_discount($couponCode) && BfwPoints::getFastPoints($userId) > 0) {
            // Обновляем виртуальный купон с правильной суммой
            add_filter('woocommerce_get_shop_coupon_data', function($coupon_data, $coupon_code, $coupon) use ($couponCode) {
                if (strtolower($coupon_code) === $couponCode) {
                    $fast_points = BfwPoints::getFastPoints(get_current_user_id());
                    if ($fast_points > 0) {
                        $coupon_data['amount'] = $fast_points;
                        $coupon_data['discount_type'] = 'fixed_cart';
                        $coupon_data['individual_use'] = BfwSetting::get('balls-and-coupon') ? true : false;
                    }
                }
                return $coupon_data;
            }, 10, 3);
        }
    }

    /**
     * Гарантированное применение купона после расчета корзины
     * 
     * @param WC_Cart $cart
     * @return void
     */
    public static function ensure_coupon_applied($cart): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        $couponCodeSetting = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
        if (!$couponCodeSetting || $cart->is_empty()) {
            return;
        }
        
        $userId = get_current_user_id();
        if (!$userId || BfwPoints::getFastPoints($userId) <= 0) {
            return;
        }
        
        $couponCode = mb_strtolower($couponCodeSetting);
        $fastPoints = BfwPoints::getFastPoints($userId);
        
        // Если есть быстрые баллы, но купон не применен - применяем принудительно
        if ($fastPoints > 0 && !$cart->has_discount($couponCode)) {
            // Добавляем купон в массив примененных купонов
            $applied_coupons = $cart->get_applied_coupons();
            if (!in_array($couponCode, $applied_coupons)) {
                $cart->applied_coupons[] = $couponCode;
            }
            
            // Обновляем сессию
            if (WC()->session) {
                $session_coupons = WC()->session->get('applied_coupons', []);
                if (!in_array($couponCode, $session_coupons)) {
                    $session_coupons[] = $couponCode;
                    WC()->session->set('applied_coupons', $session_coupons);
                }
            }
        }
        
        // Если купон применен, но баллов нет - удаляем купон
        if ($cart->has_discount($couponCode) && $fastPoints <= 0) {
            $cart->remove_coupon($couponCode);
        }
    }

    /**
     * Clearing temporary points
     * Очищение временных баллов (AJAX версия)
     *
     * @return void
     * @version 5.3.3
     */
    public static function bfwoo_clean_fast_bonus(): void
    {
        // Получаем ID текущего пользователя
        $userId = get_current_user_id();

        // Обнуляем быстрые бонусные баллы
        self::updateFastPoints($userId, 0);

        // Проверяем, есть ли в опциях бонусные баллы в корзине
        if (BfwSetting::get('bonus-points-on-cart')) {
            $couponName = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
            $cartDiscount = mb_strtolower($couponName);
            $woo = WC();

            // Проверяем, есть ли корзина и примененные купоны
            if (isset($woo->cart) && $woo->cart->get_applied_coupons()) {
                foreach ($woo->cart->get_applied_coupons() as $code) {
                    if (strtolower($code) === $cartDiscount) {
                        $woo->cart->remove_coupon($code);
                    }
                }
            }
        }

        // Проверяем, есть ли редирект в POST-запросе
        if (isset($_POST['redirect'])) {
            wp_send_json_success($_POST['redirect']);
        } else {
            wp_send_json_success(wc_get_cart_url());
        }
    }

    /**
     * Безопасный способ добавления баллов(купона)
     *
     * @param $cart
     * @param $couponCode
     * @return bool
     * @version 7.4.7
     */
    public static function safe_apply_coupon($cart, $couponCode): bool
    {

        try {
            // Пробуем стандартный метод
            if ($cart->apply_coupon($couponCode)) {
                return true;
            }
        } catch (Exception $e) {
            // Логируем ошибку, но продолжаем
            BfwLogs::addLog('error', get_current_user_id(), "Coupon bonus error: " . $e->getMessage());
        }

        // Если стандартный метод не сработал, пробуем прямой
        if (!in_array($couponCode, $cart->get_applied_coupons())) {
            $cart->applied_coupons[] = $couponCode;
            $cart->calculate_totals();
            return true;
        }

        return false;
    }

    /**
     * Delete button in subtotal (using commissions)
     * Кнопка удаления в подытоге (с помощью комиссий)
     *
     * @param $cart_totals_fee_html
     * @param $fee
     *
     * @return string
     * @version 6.4.0
     */
    public static function bfw_button_delete_fast_point($cart_totals_fee_html, $fee): string
    {
        if (!empty($fee)) {
            $fee_name = $fee->name;
            $remove_cart_text = BfwSetting::get('remove-on-cart', __('Remove points', 'bonus-for-woo'));

            $cart_discount = BfwSetting::get('bonus-points-on-cart')  ??  __('Bonus points', 'bonus-for-woo');;
            if (mb_strtolower($cart_discount) === mb_strtolower($fee_name)) {
                $cart_totals_fee_html .= '<a id="bfw_remove_cart_point" title="' . $remove_cart_text . '">' . $remove_cart_text . '</a>';
            }
        }
        return $cart_totals_fee_html;
    }


    /**
     * Create a virtual coupon
     * Создаем виртуальный купон
     *
     * @param $response !!!не удаляем!!!
     * @param $coupon_data
     * @param $coupon
     * @return array|null
     * @version 6.4.0
     */
    public static function get_virtual_coupon_data_bfw($response, $coupon_data, $coupon)
    {

        $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart', __('Bonus points', 'bonus-for-woo')));
        $individual_use = false;
        if (BfwSetting::get('balls-and-coupon')) {
            $individual_use = true;
        }
        if ($coupon_data == $cart_discount) {
            $userid = get_current_user_id();
            $computy_point_old = self::getFastPoints($userid); //узнаем баллы которые он решил списать
            $computy_point_old = self::roundPoints($computy_point_old);
            // $discount_type = 'fixed_cart';
            if ($computy_point_old > 0) {
                return array(
                    'id' => time() . wp_rand(2, 9),
                    //ID купона (для виртуальных можно задавать вручную)
                    'discount_type' => 'fixed_cart',
                    //Тип скидки fixed_cart,percent,fixed_product
                    'amount' => max(0, $computy_point_old),
                    //Размер скидки (число).
                    'individual_use' => $individual_use,
                    //применение с другими купонами
                    'product_ids' => array(),
                    //Массив ID товаров, на которые купон действует.
                    'exclude_product_ids' => array(),
                    //Массив ID товаров, которые исключены из действия купона.
                    'usage_limit' => '',
                    //Сколько раз купон может быть использован всего (всеми пользователями)
                    'usage_limit_per_user' => '',
                    //Сколько раз один пользователь может использовать купон.
                    'limit_usage_to_x_items' => '',
                    //Ограничивает скидку количеством товаров, на которые она применяется.
                    'usage_count' => '',
                    //Сколько раз купон уже был использован.
                    'expiry_date' => '',
                    //Дата истечения купона (строка YYYY-MM-DD).
                    'apply_before_tax' => 'yes',
                    //yes → применить скидку до налога no → после налогов
                    'free_shipping' => false,
                    //true → купон даёт бесплатную доставку.
                    'product_categories' => array(),
                    //ID категорий, к которым применяется купон.
                    'exclude_product_categories' => array(),
                    //ID категорий, которые исключены из действия купона.
                    'exclude_sale_items' => false,
                    //true → купон не действует на товары по распродаже.false → действует на все товары.
                    'minimum_amount' => '',
                    //Минимальная сумма корзины для применения купона.
                    'maximum_amount' => '',
                    //Максимальная сумма корзины, при которой купон всё ещё действует.

                    'customer_email' => ''
                    //Список email пользователя, для которого разрешён купон.
                    /*
                     * Другие параметры
                     * 'product_categories' Категории, включённые в действие
                     * 'exclude_product_categories' Исключённые категории
                     * 'apply_to_shipping' Применить скидку к доставке
                     * 'apply_to_fees' Применить скидку к комиссиям
                     * 'max_discount' Максимальный размер скидки (например, не более 500₽)
                     * 'min_discount' Минимальный размер скидки
                     * 'auto_apply' aвтоматически применять купон
                     *
                     *
                     * */
                );
            }

        }
        return $response;
    }

    /**
     * View of coupons in the basket
     * Вид купонов в корзине
     *
     * @param $html
     * @param $coupon
     *
     * @return string
     * @version 6.4.0
     */
    public static function bfw_coupon_html($html, $coupon): string
    {
        $removeOnCart = BfwSetting::get('balls-and-coupon', __('Remove points', 'bonus-for-woo'));

        $couponName = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
        $cart_discount = mb_strtolower($couponName);
        $coupon_data = mb_strtolower($coupon->get_code());
        $userid = get_current_user_id();
        $computy_point_old = self::getFastPoints($userid); //узнаем баллы которые он решил списать
        $computy_point_old = self::roundPoints($computy_point_old);

        if (strtolower($coupon_data) === strtolower($cart_discount)) {
            $html = ' <span class="woocommerce-Price-amount amount">-' . wc_price($computy_point_old) . '</span>
    <a id="bfw_remove_cart_point" title="' . $removeOnCart . '">' . $removeOnCart . '</a>';
        }
        return $html;
    }

    /**
     * Remove the "coupon" from the cart
     * Убираем "купон" в корзине
     *
     * @param $sprintf
     * @param $coupon
     *
     * @return string
     * @version 6.4.0
     */
    public static function woocommerceChangeCouponLabelBfw($sprintf, $coupon): string
    {
        $couponName = BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo');
        $cart_discount = mb_strtolower($couponName);
        $coupon_data = $coupon->get_data();
        if (!empty($coupon_data) && strtolower($coupon_data['code']) === strtolower($cart_discount)) {
            $sprintf =$couponName;
        }
        return $sprintf;
    }


    /**
     * Excluding tax deductions
     * Исключаем скидку из налогов
     *
     * @param $taxes
     * @param $fee
     * @param $cart
     *
     * @return array
     * @version 6.4.0
     */
    public static function excludeCartFeesTaxes($taxes, $fee, $cart): array
    {
        return [];
    }

    /**
     * Removing temporary points when emptying the recycle bin
     * Удаление временных баллов при очистке корзины
     *
     * @return void
     * @version 6.4.0
     */
    public static function actionWoocommerceBeforeCartItemQuantityZero(): void
    {
        self::updateFastPoints(get_current_user_id(), 0);
    }


    /**
     * Clearing time points when changing the quantity of goods
     * Очищение временных баллов при изменении количества товаров
     *
     * @return void
     * @version 6.4.0
     */
    public static function bfwCartItemSetQuantity(): void
    {
        if (BfwSetting::get('clear-fast-bonus-were-qty-cart')) {
            self::updateFastPoints(get_current_user_id(), 0);
        }
    }


    /**
     * Получение покупателя
     * по телефону
     *
     * @param string $phone телефон
     *
     * @return int|false
     */
    public static function get_customer_by_billing_phone(string $phone)
    {
        global $wpdb;

        $phone = trim($phone);

        $phone = preg_replace('/[^0-9]/', '', $phone);
        $customer_id = $wpdb->get_var($wpdb->prepare("
    SELECT user_id 
    FROM $wpdb->usermeta 
    WHERE meta_key = 'billing_phone'
     AND REPLACE(REGEXP_REPLACE(meta_value, '[^0-9]', ''), ' ', '') = %s 
  ", $phone));

        if (!$customer_id) {
            return false;
        }
        return $customer_id;

    }

    /**
     * Export bonus csv file
     * Экспорт csv файла бонусов
     *
     * @return void
     * @version 6.4.0
     */
    public static function bfw_export_bonuses(): void
    {
        check_ajax_referer('bfw_export_bonuses_nonce', 'nonce');

        $offset = (int) ($_POST['offset'] ?? 0);
        $limit = 100;

        $search_by = in_array($_POST['search_by'] ?? 'by_id', ['by_id', 'by_email', 'by_phone'])
            ? sanitize_text_field($_POST['search_by'])
            : 'by_id';
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
            return;
        }

        $array = json_decode(stripslashes($_POST['response']), true);
        if (!$array || empty($array['data']['url'])) {
            wp_send_json_error('Invalid response data');
        }

        $url = $array['data']['url'];
        $file_path = wp_normalize_path(ABSPATH . str_replace(site_url(), '', $url));

        if (!file_exists($file_path)) {
            wp_send_json_error('Файл не найден');
        }

        $fileHandle = fopen($file_path, 'rb');
        if ($fileHandle === false) {
            wp_send_json_error('Ошибка при открытии файла');
        }

        // Пропускаем заголовок CSV
        fgets($fileHandle);

        // Пропускаем строки до нужного смещения
        $current = 0;
        while (!feof($fileHandle) && $current < $offset) {
            fgets($fileHandle);
            $current++;
        }

        $processed = 0;
        while (!feof($fileHandle) && $processed < $limit) {
            $currRow = fgetcsv($fileHandle);
            if (!$currRow || count($currRow) < 5) {
                continue;
            }

            // Поиск пользователя
            if ($search_by === 'by_email') {
                $user = get_user_by('email', $currRow[2]);
                $id = $user ? $user->ID : null;
                if (!$id) {
                    BfwLogs::addLog('error', 0, 'User not found with email: ' . $currRow[2]);
                    continue;
                }
            } elseif ($search_by === 'by_phone') {
                $id = self::get_customer_by_billing_phone($currRow[3]);
                if (!$id) {
                    continue;
                }
            } else {
                $id = (int) $currRow[0];
            }

            // Обновление баллов
            $point = (float) $currRow[4];
            $comment = (string) ($currRow[5] ?? '');
            $user_point = (float) get_user_meta($id, 'computy_point', true);

            $issetUser = get_userdata($id);
            if ($issetUser && $user_point !== $point) {
                $diff = abs($user_point - $point);
                BfwHistory::add_history($id, $user_point > $point ? '-' : '+', $diff, '0', $comment);
                update_user_meta($id, 'computy_point', $point);
            }

            $processed++;
        }

        $done = feof($fileHandle); // сохранить статус до закрытия
        fclose($fileHandle);

        if ($offset === 0) {
            $file = fopen($file_path, "rb");
            if (!$file) {
                wp_send_json_error('Не удалось открыть файл');
            }

            $total_lines = 0;
            fgets($file); // пропускаем заголовок
            while (fgets($file) !== false) {
                $total_lines++;
            }
            fclose($file);
        } else {
            $total_lines = null;
        }

        wp_send_json_success([
            'next_offset' => $offset + $processed,
            'processed' => $processed,
            'done' => $done,
            'total' => $total_lines // будет только на первом шаге
        ]);
    }

    /**
     * Action when the client confirms the order - write-off of points
     * Действие когда клиент подтверждает заказ - списание баллов
     *
     * @param $order_id int
     *
     * @return void
     * @version 6.4.0
     */
    public static function newOrder(int $order_id): void
    {

        $order = wc_get_order($order_id);
        $customer_user = $order->get_customer_id();
        $bfwEmail = new BfwEmail();

        $computy_point_old = self::getPoints($customer_user);
        $computy_point_fast = self::getFastPoints($customer_user);

        if ($computy_point_fast > 0) {
            $count_point = $computy_point_old - $computy_point_fast;
            self::updatePoints($customer_user, $count_point);

            // Mark the order as having deducted points to ensure correct return on refund
            $order->update_meta_data('_bfw_points_deducted', $computy_point_fast);
            $order->save();

            $prichina = sprintf(__('Use of %s', 'bonus-for-woo'), self::pointsLabel(5));
            BfwHistory::add_history($customer_user, '-', $computy_point_fast, $order_id, $prichina);


            $title_email = BfwSetting::get(
                'email-when-order-confirm-title',
                __('Writing off bonus points', 'bonus-for-woo')
            );
            $text_email = BfwSetting::get('email-when-order-confirm-text', '');

            $user = get_userdata($customer_user);
            $get_referral = get_user_meta($customer_user, 'bfw_points_referral', true);

            $text_email_array = array(
                '[user]' => $user->display_name,
                '[order]' => $order_id,
                '[points]' => $computy_point_fast,
                '[total]' => $count_point,
                '[cause]' => $prichina,
                '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral)
            );

            $message_email = $bfwEmail::template($text_email, $text_email_array);

            if (BfwSetting::get('email-when-order-confirm')) {
                $bfwEmail->getMail($customer_user, '', $title_email, $message_email);
            }
        }

        self::updateFastPoints($customer_user, 0);
    }


    /**
     * Вычисляем количество предполагаемого кешбэка в заказе
     *
     * @param int $order_id
     * @description Применяется для расчета и перерасчета кешбэка в заказе.
     * @return float
     * @version 7.6.0
     */
    public static function howMatchCashbackInOrder(int $order_id): float
    {
        $order = wc_get_order($order_id);
        $user_id = $order->get_customer_id();

        $order_items = $order->get_items();
        $bfwRoles = new BfwRoles();
        $bfwSingleProduct = new BfwSingleProduct();
        $bfwFunctions = new BfwFunctions();


        // обновляем роль пользователя
        $bfwRoles::updateRole($user_id);

        $order_total = (float) $order->get_total();
        $payment_method = $order->get_payment_method();

        // Проверка исключенных методов оплаты
        if (
            BfwSetting::get('exclude-payment-method') && in_array(
                $payment_method,
                BfwSetting::get('exclude-payment-method')
            )
        ) {
            return 0.0;
        }

        // --- Вычисляем кешбэк по товарам (internal) ---
        $cashback_internal = 0;
        foreach ($order_items as $item_id => $item) {
            $product_id = $item['product_id'];
            $variation_id = $item['variation_id'] ?? null;

            $total = $item->get_total();//цена товара

            if (BfwSetting::get('cashback-on-sale-products')) {
                // не начисляем кешбэк если товар со скидкой
                $subtotal = $item->get_subtotal();

                $item_discount = $subtotal - $total;
                if ($item_discount > 0.001) {
                    continue;
                }
            }

            // Безопасное получение данных о кешбэке
            $cashback_data = $bfwSingleProduct->cashbackFromOneProduct($product_id, $user_id, $variation_id, $total);
            $cashback_amount = isset($cashback_data['amount']) ? (float) $cashback_data['amount'] : 0;
            $cashback_internal += $cashback_amount;
        }

        $shipping_total = (float) $order->get_shipping_total();
        $percent_data = $bfwRoles::getRole($user_id);
        $percent = isset($percent_data['percent']) ? (float) $percent_data['percent'] : 0;

        // Добавляем кешбэк за доставку, если не отключено
        if (!BfwSetting::get('cashback-for-shipping')) {
            $cashback_internal += $shipping_total * $percent / 100;
        }

        $cashback_internal = apply_filters('bfw-completed-points-internal', $cashback_internal, $order_id, $order);

        // Если internal ноль — ничего не начисляем
        if ((float) $cashback_internal <= 0) {
            return 0.0;
        }

        // --- Фактический кешбэк для покупателя (после вычетов, списаний) ---
        $cashback_for_user = $cashback_internal;
        $fee_total = $bfwFunctions::feeOrCoupon($order);

        // Если включена опция "если используются баллы — не начислять кешбэк" — обнуляем кешбэк покупателю
        if (BfwSetting::get('yous_balls_no_cashback') && $fee_total > 0) {
            $cashback_for_user = 0;
        } else {

            $cd = $fee_total * $percent / 100;
            $cashback_for_user = $cashback_for_user - $cd;
            if ($cashback_for_user < 0) {
                $cashback_for_user = 0;
            }
        }

        // Применяем фильтры/корректировки
        $percent = apply_filters('bfw-filter-percent-in-cart', $percent, $order_total);
        $cashback_for_user = apply_filters('bfw-cashback-this-order', $cashback_for_user, $order_total, $percent);
        $cashback_internal = apply_filters(
            'bfw-cashback-this-order-internal',
            $cashback_internal,
            $order_total,
            $percent
        );

        if ($cashback_for_user > 0 && $bfwRoles::isInvalve($user_id)) {
            if ($bfwRoles::isPro()) {
                // Проверка минимальной суммы (Pro)
                if (BfwSetting::get('minimal-amount')) {
                    $minimal_amount = (float) BfwSetting::get('minimal-amount');
                    if ($order_total < $minimal_amount && BfwSetting::get('minimal-amount-cashback')) {
                        $cashback_for_user = 0;
                    }
                }

                //применялись ли другие купоны
                $coupon = $order->get_coupon_codes();
                if (!empty($coupon) && BfwSetting::get('yous_coupon_no_cashback')) {
                    $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
                    if (!in_array($cart_discount, $coupon) or in_array($cart_discount, $coupon) && count($coupon) > 1) {
                        $cashback_for_user = 0;
                    }
                }

            }
        }

        return BfwPoints::roundPoints($cashback_for_user);
    }

    /**
     * Начисление балов пользователю за заказ.
     *
     * @param int $order_id
     * @param bool $sendNotification Надо ли отправлять уведомление по Email?
     * @return bool
     * @version 7.6.0
     */
    public static function addPointsForOrder(int $order_id, bool $sendNotification = true): bool
    {
        $order = wc_get_order($order_id);
        $user_id = $order->get_customer_id();

        $bfwRoles = new BfwRoles();
        $bfwHistory = new BfwHistory();
        $bfwEmail = new BfwEmail();

        // обновляем роль пользователя
        $bfwRoles::updateRole($user_id);

        $cashback_for_user = self::howMatchCashbackInOrder($order_id);

        // Если покупателю что-то начислено — записываем и шлём письмо
        if ($cashback_for_user > 0 && $bfwRoles::isInvalve($user_id)) {

            $computy_point_old = self::getPoints($user_id);
            $new_points = $computy_point_old + $cashback_for_user;
            $new_points = self::roundPoints($new_points);

            self::updatePoints($user_id, $new_points);
            self::updateFastPoints($user_id, 0);

            // Очищаем pending баллы для этого заказа
            self::clearPendingPoints($order_id);

            // Сохраняем мета заказа о начислении покупателю
            $order->update_meta_data('cashback_receipt', 'received');
            $order->update_meta_data('cashback_amount', $cashback_for_user);
            $order->save();

            // Запись в историю и e-mail
            $reason = __('Points accrual', 'bonus-for-woo');
            $bfwHistory::add_history($user_id, '+', $cashback_for_user, $order_id, $reason);

            // Добавляем лог
            BfwLogs::addLog('add_points', $user_id, $reason . ' (' . __('Order', 'bonus-for-woo') . ' #' . $order_id . ')');

            // Отправка email
            if ($sendNotification) {
                $text_email = BfwSetting::get('email-when-order-change-text', '');
                $title_email = BfwSetting::get('email-when-order-change-title', __('Points accrual', 'bonus-for-woo'));

                $user = get_userdata($user_id);

                if ($user) {
                    $get_referral = get_user_meta($user_id, 'bfw_points_referral', true);
                    $text_email_array = array(
                        '[user]' => $user->display_name,
                        '[order]' => $order_id,
                        '[points]' => $cashback_for_user,
                        '[total]' => $new_points,
                        '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral)
                    );
                    $message_email = $bfwEmail::template($text_email, $text_email_array);
                    if (BfwSetting::get('email-when-order-change')) {
                        $bfwEmail->getMail($user_id, '', $title_email, $message_email);
                    }
                }
            }

        }

        // --- Начисляем рефералку от фактически оплаченной суммы заказа (order total) ---

        if (BfwSetting::get('referal-system')) {
            $get_referral_invite = get_user_meta($user_id, 'bfw_points_referral_invite', true);
            $get_referral_invite = (int) $get_referral_invite;

            if ($get_referral_invite > 0) {
                $sumordersforreferral = (float) BfwSetting::get('sum-orders-for-referral', 0.0);
                $totalref = self::getSumUserOrders($get_referral_invite);

                if ($totalref >= $sumordersforreferral) {
                    // процент рефералки из настроек
                    $percent_for_referal = (float) BfwSetting::get('referal-cashback', 0.0);
                    // База для рефералки: Сумма всего чека минус налоги и доставка (чистая сумма за товары)
                    $paid_amount = (float) $order->get_total() - (float) $order->get_shipping_total() - (float) $order->get_total_tax();
                    if ($paid_amount < 0) {
                        $paid_amount = 0;
                    }

                    // вычисление баллов реферера: percent_for_referal% от чистой суммы
                    $pointsForRef = $paid_amount * ($percent_for_referal / 100);
                    $pointsForRef = self::roundPoints($pointsForRef);

                    if ($pointsForRef > 0 && $percent_for_referal > 0) {
                        BfwReferral::addReferralPoints($user_id, $pointsForRef, $get_referral_invite, $order_id);
                    }
                }

                // второй уровень
                if (BfwSetting::get('level-two-referral')) {
                    $get_referral_invite_two_level = get_user_meta(
                        $get_referral_invite,
                        'bfw_points_referral_invite',
                        true
                    );
                    $get_referral_invite_two_level = (int) $get_referral_invite_two_level;

                    if ($get_referral_invite_two_level !== 0) {
                        $sumordersforreferral2 = (float) BfwSetting::get('sum-orders-for-referral', 0.0);

                        $totalref2 = self::getSumUserOrders($get_referral_invite_two_level);

                        if ($totalref2 >= $sumordersforreferral2) {
                            $percent_for_referal_two_level = floatval(BfwSetting::get('referal-cashback-two-level', 0));

                            $paid_amount = (float) $order->get_total() - (float) $order->get_shipping_total() - (float) $order->get_total_tax();
                            $pointsForRef2 = $paid_amount * ($percent_for_referal_two_level / 100);
                            $pointsForRef2 = self::roundPoints($pointsForRef2);

                            if ($pointsForRef2 > 0 && $percent_for_referal_two_level > 0) {
                                BfwReferral::addReferralPoints(
                                    $user_id,
                                    $pointsForRef2,
                                    $get_referral_invite_two_level,
                                    $order_id
                                );
                            }
                        }
                    }
                }
            }
        }


        delete_user_meta($user_id, 'first_order');
        return true;
    }


    /**
     * Action when the order status is completed - accrual of points
     * Действие когда статус заказа выполнен - начисление баллов
     *
     * @param int $order_id
     * @return bool
     * @version 7.4.6
     */
    public static function ifCompletedOrder(int $order_id): bool
    {
        try {
            $order = wc_get_order($order_id);

            // Проверка существования заказа
            if (!$order) {
                BfwLogs::addLog('error', 0, "BfW: Order {$order_id} not found");
                return false;
            }

            // Если уже начислены баллы, то выходим
            if ($order->get_meta('cashback_receipt') === 'received') {
                return false;
            }

            $user_id = $order->get_customer_id();

            // Проверка пользователя
            if ($user_id === 0) {
                BfwLogs::addLog('error', 0, "BfW: User ID is 0 for order {$order_id}");
                return false;
            }
            if (BfwSetting::get('daily_cashback_check')) {
                //добавляем задержку начисления баллов
                $order->update_meta_data(
                    '_bonus_cashback_pending',
                    ['process_after' => time() + (int) BfwSetting::get('daily_cashback_check') * DAY_IN_SECONDS]
                );
                $order->save();
                return true;
            }

            //если задержки начислений нет, то начисляем мгновенно
            if (self::addPointsForOrder($order_id)) {
                return true;
            }
            return false;

        } catch (Exception $e) {
            BfwLogs::addLog('error', isset($user_id) ? $user_id : 0, "BfW Error in ifCompletedOrder for order {$order_id}: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Ищем заказы на которые можно начислить кешбэк
     *
     * @return void
     */
    public static function searchCashbackCheck()
    {
        $order_status = BfwSetting::get('add_points_order_status', 'completed');

        $args = [
            'type' => 'shop_order',
            'status' => ['wc-' . $order_status], // используем WooCommerce статус
            'limit' => -1,
            'meta_key' => '_bonus_cashback_pending',
            'meta_compare' => 'EXISTS',
            'return' => 'objects',
        ];

        $orders = wc_get_orders($args);

        foreach ($orders as $order) {
            $order_id = $order->get_id();

            $pending = $order->get_meta('_bonus_cashback_pending', true);

            if (!is_array($pending)) {
                $order->delete_meta_data('_bonus_cashback_pending');
                $order->save();
                continue;
            }

            // Проверяем время начисления
            if (isset($pending['process_after']) && $pending['process_after'] > time()) {
                continue; // ещё рано
            }

            // Дополнительная проверка статуса заказа
            if (!$order->has_status(array($order_status))) {
                $order->delete_meta_data('_bonus_cashback_pending');
                $order->save();
                continue;
            }

            // Начисляем кешбэк
            self::addPointsForOrder($order_id);
            // Удаляем мета-данные
            $order->delete_meta_data('_bonus_cashback_pending');
            $order->save();
        }

    }

    /**
     * Pending-баллы: рассчитываем и сохраняем ожидаемый кешбэк за заказ
     * Вызывается при переходе заказа в статус processing/on-hold
     *
     * @param int $order_id
     * @return void
     * @version 8.0.0
     */
    public static function setPendingPoints(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $customer_id = (int) $order->get_customer_id();
        if ($customer_id <= 0 || !BfwRoles::isInvalve($customer_id)) {
            return;
        }

        // Если уже были начислены реальные баллы за этот заказ — не ставим pending
        if (get_post_meta($order_id, '_bonus_cashback_paid', true) || $order->get_meta('_bonus_cashback_paid')) {
            return;
        }

        // Получаем процент кешбэка для этого клиента
        $role = BfwRoles::getRole($customer_id);
        $percent = (float) ($role['percent'] ?? 0);

        if ($percent <= 0) {
            return;
        }

        // Считаем сумму для начисления (без доставки если настройка не включена)
        $order_total = (float) $order->get_subtotal();

        // Проверяем исключения (скидки, купоны)
        $no_cashback_with_coupon = BfwSetting::get('yous_coupon_no_cashback');
        if ($no_cashback_with_coupon && count($order->get_coupon_codes()) > 0) {
            return;
        }

        $pending_points = self::roundPoints(($order_total * $percent) / 100);

        if ($pending_points > 0) {
            // Сохраняем информацию о pending баллах в метаданные заказа для отображения в админке
            $order->update_meta_data('_bfw_pending_points', $pending_points);
            $order->save();
        }
    }

    /**
     * Pending-баллы: очищаем ожидаемый кешбэк при отмене/возврате заказа
     *
     * @param int $order_id
     * @return void
     * @version 8.0.0
     */
    public static function clearPendingPoints(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Убираем метку заказа о pending баллах
        $order->delete_meta_data('_bfw_pending_points');
        $order->save();
    }


    /**
     * Action when points refund is issued
     * Действие когда оформлен возврат баллов
     *
     * @param int $order_id
     * @param int|null $refund_id
     * @return void
     * @version 8.0.1
     */
    public static function refundedPoints(int $order_id, int $refund_id = null): void
    {
        // Prevent double processing in the same request due to multiple hooks
        static $processing_order = [];

        if (isset($processing_order[$order_id])) {
            return;
        }
        $processing_order[$order_id] = true;

        $order = wc_get_order($order_id);
        if (!$order) {
            unset($processing_order[$order_id]);
            return;
        }

        $customer_user = $order->get_customer_id();
        if (!$customer_user) {
            unset($processing_order[$order_id]);
            return;
        }

        // 1. Calculate Refund Amount and Ratio
        $original_total = (float) $order->get_total();
        if ($original_total <= 0) {
            unset($processing_order[$order_id]);
            return;
        }

        $current_refund_amount = 0;
        $refund_statuses = (array) BfwSetting::get('refunded_points_order_status', array('refunded'));
        $is_full_refund = false;

        // Определяем сумму возврата ТОЛЬКО одним способом
        if ($refund_id) {
            $refund = wc_get_order($refund_id);
            if ($refund) {
                $current_refund_amount = abs($refund->get_amount());
                $is_full_refund = ($current_refund_amount >= $original_total);
            }
        } else {
            // Проверяем статус заказа с учетом префикса wc-
            $order_status = $order->get_status();
            $order_status_clean = ltrim($order_status, 'wc-');
            
            if (in_array($order_status_clean, $refund_statuses) || in_array($order_status, $refund_statuses)) {
                $current_refund_amount = $original_total;
                $is_full_refund = true;
            }
        }

        if ($current_refund_amount <= 0) {
            unset($processing_order[$order_id]);
            return;
        }

        $ratio = min(1, $current_refund_amount / $original_total);

        // 2. Get current state from order meta
        $total_earned = (float) $order->get_meta('cashback_amount');
        $revoked_so_far = (float) $order->get_meta('_bfw_revoked_points');
        $total_spent = (float) $order->get_meta('_bfw_points_deducted');
        $returned_so_far = (float) $order->get_meta('_bfw_returned_points');

        // Fallback для старых заказов без метаданных
        if ($total_spent <= 0 && $order->get_meta('cashback_receipt') !== 'received') {
            $total_spent = (float) BfwFunctions::feeOrCoupon($order);
            // Если заказ отменен и баллы не были списаны
            if ($order->get_status() === 'cancelled' && !$order->get_meta('_bfw_points_deducted')) {
                $total_spent = 0;
            }
        }

        // 3. Calculate points to revoke (earned cashback)
        $points_to_revoke = 0;
        if ($order->get_meta('cashback_receipt') === 'received' && $total_earned > 0) {
            if ($is_full_refund) {
                $points_to_revoke = max(0, $total_earned - $revoked_so_far);
            } else {
                $points_to_revoke = min(
                    self::roundPoints($total_earned * $ratio),
                    max(0, $total_earned - $revoked_so_far)
                );
            }
        }

        // 4. Calculate points to return (spent points)
        $points_to_return = 0;
        if ($total_spent > 0) {
            if ($is_full_refund) {
                $points_to_return = max(0, $total_spent - $returned_so_far);
            } else {
                $points_to_return = min(
                    self::roundPoints($total_spent * $ratio),
                    max(0, $total_spent - $returned_so_far)
                );
            }
        }

        if ($points_to_revoke <= 0 && $points_to_return <= 0) {
            unset($processing_order[$order_id]);
            return;
        }

        // 5. Update user points
        $current_points = self::getPoints($customer_user);
        $new_points = $current_points + $points_to_return - $points_to_revoke;

        $cause = __('Refund of bonus points', 'bonus-for-woo');
        $info_email = '';
        $needs_save = false;

        if ($points_to_revoke > 0) {
            BfwHistory::add_history($customer_user, '-', $points_to_revoke, $order_id, $cause);
            $info_email .= sprintf(
                __('The %1$s bonus points you earned for order no. %2$s have been canceled.', 'bonus-for-woo'),
                $points_to_revoke,
                $order_id
            );
            BfwLogs::addLog('remove_points', $customer_user,
                sprintf(__('Cancellation of %s points for order #%d due to refund.', 'bonus-for-woo'),
                    $points_to_revoke, $order_id));

            $new_revoked = $revoked_so_far + $points_to_revoke;
            $order->update_meta_data('_bfw_revoked_points', $new_revoked);
            $needs_save = true;
        }

        if ($points_to_return > 0) {
            BfwHistory::add_history($customer_user, '+', $points_to_return, $order_id, $cause);
            $info_email .= sprintf(
                __('You have returned %1$s bonus points for order number %2$s.', 'bonus-for-woo'),
                $points_to_return,
                $order_id
            );
            BfwLogs::addLog('add_points', $customer_user,
                sprintf(__('Refund of %s spent points for order #%d.', 'bonus-for-woo'),
                    $points_to_return, $order_id));

            $new_returned = $returned_so_far + $points_to_return;
            $order->update_meta_data('_bfw_returned_points', $new_returned);
            $needs_save = true;
        }

        // 6. Apply changes
        if ($needs_save) {
            $order->save();
        }

        self::updatePoints($customer_user, $new_points);
        BfwRoles::updateRole($customer_user);

        // 7. Update order status if fully processed
        if ($is_full_refund || ($revoked_so_far + $points_to_revoke >= $total_earned && $total_earned > 0)) {
            if ($order->get_meta('cashback_receipt') === 'received') {
                $order->update_meta_data('cashback_receipt', 'refunded');
                $order->save();
            }
        }

        // 8. Send email notification
        $title_email = BfwSetting::get('email-when-order-change-title-vozvrat',
            __('Refund of bonus points', 'bonus-for-woo'));
        $text_email = BfwSetting::get('email-when-order-change-text-vozvrat', '');
        $user = get_userdata($customer_user);

        if ($user && !empty($info_email) && BfwSetting::get('email-when-order-change')) {
            $get_referral = get_user_meta($customer_user, 'bfw_points_referral', true);
            $text_email_array = array(
                '[user]' => $user->display_name,
                '[order]' => $order_id,
                '[points_info]' => $info_email,
                '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral)
            );
            $bfwEmail = new BfwEmail();
            $message_email = $bfwEmail::template($text_email ?: $info_email, $text_email_array);
            $bfwEmail->getMail($customer_user, '', $title_email, $message_email);
        }

        unset($processing_order[$order_id]);
    }

    /**
     * Обработка изменения статуса заказа для возвратов (1С и внешние системы)
     *
     * @param int $order_id
     * @param string $from_status
     * @param string $to_status
     * @param WC_Order $order
     * @return void
     */
    public static function handleOrderStatusChangeForRefunds(int $order_id, string $from_status, string $to_status, WC_Order $order): void
    {
        $refund_statuses = (array) BfwSetting::get('refunded_points_order_status', array('refunded'));
        
        // Нормализуем статусы для сравнения (убираем префикс wc- если есть)
        $to_status_clean = ltrim($to_status, 'wc-');
        
        // Создаем массив для проверки обоих вариантов (с префиксом и без)
        $statuses_to_check = [];
        foreach ($refund_statuses as $status) {
            $statuses_to_check[] = $status;           // без префикса
            $statuses_to_check[] = 'wc-' . $status;   // с префиксом
        }
        
        // Если статус изменился на статус возврата
        if (in_array($to_status, $statuses_to_check) || in_array($to_status_clean, $refund_statuses)) {
            self::refundedPoints($order_id);
        }
    }

    /**
     * Обработка создания возврата через wp_insert_post (для 1С)
     *
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     * @return void
     */
    public static function handleRefundCreation(int $post_id, WP_Post $post, bool $update): void
    {
        // Обрабатываем только создание новых возвратов
        if ($update || $post->post_type !== 'shop_order_refund') {
            return;
        }

        $refund = wc_get_order($post_id);
        if (!$refund) {
            return;
        }

        $parent_order_id = $refund->get_parent_id();
        if ($parent_order_id) {
            self::refundedPoints($parent_order_id, $post_id);
        }
    }

    /**
     * Обработка обновления возврата (для 1С)
     *
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     * @return void
     */
    public static function handleRefundUpdate(int $post_id, WP_Post $post, bool $update): void
    {
        if ($post->post_type !== 'shop_order_refund') {
            return;
        }

        $refund = wc_get_order($post_id);
        if (!$refund) {
            return;
        }

        $parent_order_id = $refund->get_parent_id();
        if ($parent_order_id) {
            self::refundedPoints($parent_order_id, $post_id);
        }
    }

    /**
     * Removing points for inactivity.
     * Удаление баллов за бездействие.
     *
     * @return void
     * @version 6.4.0
     */
    public static function deleteBallsOldClients(): void
    {
        global $wpdb;

        // 1. Выносим глобальные настройки из цикла
        $day_day = (int) BfwSetting::get('day-inactive', 0);
        if ($day_day <= 1) {
            return; // Если проверка отключена или некорректна, сразу выходим
        }

        $day_notice_remove_points = (int) BfwSetting::get('day-inactive-notice', 0);
        $enable_notice = BfwSetting::get('day-inactive-notice') && $day_notice_remove_points > 0;
        $send_email_allowed = (bool) BfwSetting::get('email-when-inactive-notice');

        $exclude_role = BfwSetting::get('exclude-role', array());
        $exclude_role = apply_filters('bfw-exclude-role-for-cron', $exclude_role);

        // 2. Получаем пользователей с баллами стандартным WP_User_Query (быстро, благодаря индексам)
        $args = array(
            'role__not_in' => $exclude_role,
            'meta_query'   => array(
                array('key' => 'computy_point', 'value' => 0, 'compare' => '>')
            ),
            'fields'       => array('ID', 'display_name', 'user_registered'),
        );
        $users = get_users($args);

        if (empty($users)) {
            return;
        }

        // Подготавливаем объекты
        $bfwEmail = new BfwEmail();
        $bfwHistory = new BfwHistory();
        $today = new DateTime('today'); // Текущая дата (00:00:00) для точного расчета дней

        // Шаблоны писем (выносим из цикла, чтобы не дергать базу)
        if ($enable_notice) {
            $title_email = BfwSetting::get(
                'email-when-inactive-notice-title',
                __('Your points will be deleted soon.', 'bonus-for-woo')
            );
            $text_email = BfwSetting::get('email-when-inactive-notice-text', '');
        }

        // 3. Собираем массив ID для ОДНОГО массового запроса дат активности
        $user_ids = wp_list_pluck($users, 'ID');
        $ids_string = implode(',', array_map('intval', $user_ids));

        // Получаем последние действия для ВСЕХ пользователей одним махом
        $history_table = "{$wpdb->prefix}bfw_history_computy";
        $last_actions_raw = $wpdb->get_results(
            "SELECT user, MAX(date) as last_date FROM {$history_table} WHERE user IN ($ids_string) GROUP BY user",
            OBJECT_K
        );

        // 4. Основной цикл теперь работает только с готовыми данными в памяти
        foreach ($users as $user) {
            // Определяем дату последней активности
            if (isset($last_actions_raw[$user->ID])) {
                $last_action_date = new DateTime($last_actions_raw[$user->ID]->last_date);
            } else {
                $last_action_date = new DateTime($user->user_registered);
            }

            // Считаем разницу в полных днях
            $last_action_date->setTime(0, 0, 0);
            $days = $today->diff($last_action_date)->days;

            // Блок уведомления
            if ($enable_notice) {
                if ($days > ($day_day - $day_notice_remove_points)) {
                    $notice = get_user_meta($user->ID, 'mail_remove_points', true);

                    if ($notice !== 'yes') {
                        if ($send_email_allowed) {
                            $ball_user = self::getPoints($user->ID);
                            $text_email_array = array(
                                '[user]'   => $user->display_name,
                                '[days]'   => $day_notice_remove_points,
                                '[points]' => $ball_user
                            );
                            $message_email = $bfwEmail::template($text_email, $text_email_array);
                            $bfwEmail->getMail($user->ID, '', $title_email, $message_email);
                        }
                        update_user_meta($user->ID, 'mail_remove_points', 'yes');
                    }
                }
            }

            // Блок удаления баллов
            if ($days > $day_day) {
                $computy_point_old = self::getPoints($user->ID);

                $bfwHistory::add_history(
                    $user->ID,
                    '-',
                    $computy_point_old,
                    '0',
                    sprintf(__('Inactivity %d days', 'bonus-for-woo'), $day_day)
                );

                update_user_meta($user->ID, 'mail_remove_points', 'no');
                self::updatePoints($user->ID, 0);

                BfwLogs::addLog('remove_points', $user->ID, sprintf(__('Inactivity %d days', 'bonus-for-woo'), $day_day));
            }
        }
    }


    public static function handleSendPointsFromOrder()
    {
        check_ajax_referer('bfw_send_points_from_order_nonce', 'nonce');

        if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Access denied.', 'bonus-for-woo'));
            return;
        }
        $order_id = intval($_POST['orderId']);

        //начисляем баллы
        if (self::addPointsForOrder($order_id)) {
            // Добавляем лог (хотя addPointsForOrder уже добавит лог, здесь можно добавить уточнение)
            $order = wc_get_order($order_id);
            $user_id = $order ? $order->get_customer_id() : 0;
            BfwLogs::addLog('add_points', $user_id, __('Manual points accrual from order editor.', 'bonus-for-woo'));

            wp_send_json_success(array(
                'message' => 'Баллы успешно добавлены пользователю!'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Ошибка при добавлении баллов!'
            ));
        }

    }

    /**
     * Earning points on your birthday
     * Начисление баллов в день рождение
     *
     * @return void
     * @version 6.3.5
     */
    public static function addBallsForBirthday(): void
    {
        $bonus_option_name = BfwSetting::get_all();
        $bonus_option_birthday = $bonus_option_name['birthday'];
        $exclude_role = $bonus_option_name['exclude-role'] ?? array();
        $args = array(
            'role__not_in' => $exclude_role,
            'meta_query' => array(array('key' => 'dob', 'value' => 0, 'compare' => '>')),
        );
        $users = get_users($args);

        $title_email = $bonus_option_name['email-whens-birthday-title'] ?? __(
            'Bonus points on your birthday',
            'bonus-for-woo'
        );
        $text_email = $bonus_option_name['email-when-birthday-text'] ?? '';


        $cause = __('Birthday', 'bonus-for-woo');

        foreach ($users as $user) {
            $bonus_option_birthday = apply_filters('birthday-points-filter', $bonus_option_birthday, $user->ID);
            $text_email_array = array('[user]' => '', '[points_for_birthday]' => $bonus_option_birthday);
            $message_email = BfwEmail::template($text_email, $text_email_array);

            $how_many_birthday = $bonus_option_name['how-many-birthday'] ?? 0;

            if (gmdate("d.m", strtotime($user->dob . ' -' . $how_many_birthday . ' days')) === gmdate('d.m')) {
                $count_point = self::getPoints($user->ID) + $bonus_option_birthday;
                if (!empty($user->this_year)) {
                    if ($user->this_year !== gmdate('Y')) {
                        self::updatePoints($user->ID, $count_point);
                        BfwHistory::add_history($user->ID, '+', $bonus_option_birthday, '0', $cause);
                        update_user_meta($user->ID, 'this_year', gmdate('Y'));

                        // Добавляем лог
                        BfwLogs::addLog('add_points', $user->ID, $cause);

                        if (!empty($bonus_option_name['email-when-birthday'])) {
                            (new BfwEmail())->getMail($user->ID, '', $title_email, $message_email);
                        }
                    }
                } else {
                    self::updatePoints($user->ID, $count_point);
                    BfwHistory::add_history($user->ID, '+', $bonus_option_birthday, '0', $cause);
                    update_user_meta($user->ID, 'this_year', gmdate('Y'));

                    if (!empty($bonus_option_name['email-when-birthday'])) {
                        (new BfwEmail())->getMail($user->ID, '', $title_email, $message_email);
                    }
                }
            }
        }
    }

    /**
     * Recalculates total points for all users based on their history records.
     * Processes in batches via AJAX.
     *
     * @return void
     * @version 8.0.0
     */
    public static function computyRecalculationPoints(): void
    {
        check_ajax_referer('bfw_recalc_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Access denied.', 'bonus-for-woo'));
            return;
        }

        global $wpdb;
        $batch_size = 50;
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;

        // Get total users
        $total_users = (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->prefix}users");

        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$wpdb->prefix}users LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ));

        if (empty($users)) {
            echo 'done';
            exit;
        }

        foreach ($users as $user) {
            $user_id = (int) $user->ID;

            // Sum points from history table
            $history_sum = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(CASE WHEN symbol = '+' THEN points WHEN symbol = '-' THEN -points ELSE 0 END) 
                 FROM {$wpdb->prefix}bfw_history_computy 
                 WHERE user = %d",
                $user_id
            ));

            // Ensure we don't have negative points unless allowed (defaulting to 0)
            $history_sum = max(0, $history_sum);

            // Update the user meta
            self::updatePoints($user_id, $history_sum);
        }

        $new_offset = $offset + count($users);
        $percent = min(100, round(($new_offset / $total_users) * 100));

        wp_send_json(sprintf(__('Processed %d of %d users (%d%%)...', 'bonus-for-woo'), $new_offset, $total_users, $percent));
    }
}
