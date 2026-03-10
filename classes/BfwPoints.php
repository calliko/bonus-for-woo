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
        return (float)$points;
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
        return (float)$points;
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
     * @param $userId
     *
     * @return float
     * @version 6.5.0
     *
     */
    public static function getSumUserOrders($userId = null): float
    {

        if ($userId == null) {
            $current_user = get_current_user_id();
            if ($current_user === 0) {
                return 0;
            } else {
                $userId = $current_user;
            }

        }

        $order_staus = sanitize_text_field(BfwSetting::get('add_points_order_status', 'completed'));


        $data_start = '';
        if (BfwRoles::isPro()) {
            /*С какой даты начинать считать сумму заказов*/
            $data_start = BfwSetting::get('order_start_date', '');
            if (!empty($data_start)) {
                $datastart = sanitize_text_field(BfwSetting::get('order_start_date'));

                if (class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled()) {
                    $data_start = "AND date_created_gmt >=   '$datastart' ";
                } else {
                    $data_start = "AND p.post_date >=   '$datastart' ";
                }

            }
        }

        $order_staus = "'wc-" . $order_staus . "'";
        $order_staus = apply_filters('bfw_add_points_order_status_filter', $order_staus);

        if (is_array($order_staus)) {
            // Добавляем префикс 'wc-' к каждому элементу массива
            $prefixed_statuses = array_map(function ($status) {
                return "'wc-" . $status . "'";
            }, $order_staus);
            $order_staus = implode(', ', $prefixed_statuses);
        }

        global $wpdb;


        if (class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled()) {
            $total_all = $wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$wpdb->prefix}wc_orders  WHERE status IN ({$order_staus}) AND customer_id = %d {$data_start}",
                $userId));

            $total_shipping = 0;

            if (BfwSetting::get('shipping-total-sum')) {
                $total_shipping = $wpdb->get_var($wpdb->prepare("SELECT SUM(shipping_total_amount)
FROM  {$wpdb->prefix}wc_order_operational_data WHERE 
    order_id IN (
        SELECT 
            id
        FROM 
            {$wpdb->prefix}wc_orders
        WHERE 
          status IN ({$order_staus}) AND customer_id = %d {$data_start})", $userId));
            }


            // Получаем общую сумму возвратов
            $total_refunds = $wpdb->get_var($wpdb->prepare("SELECT SUM(meta_value) 
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
     )", $userId));

            if ($total_refunds) {
                $total_all -= $total_refunds;
            }

            $total_alls = $total_all - $total_shipping;

        } else {

            $total_all = $wpdb->get_var(
                $wpdb->prepare("SELECT SUM(pm.meta_value) FROM {$wpdb->prefix}postmeta as pm
  INNER JOIN {$wpdb->prefix}posts as p ON pm.post_id = p.ID
  INNER JOIN {$wpdb->prefix}postmeta as pm2 ON pm.post_id = pm2.post_id
  WHERE p.post_status IN ({$order_staus})  AND p.post_type LIKE 'shop_order'
  AND pm.meta_key LIKE '_order_total' AND pm2.meta_key LIKE '_customer_user'
  AND pm2.meta_value LIKE %d $data_start 
  ", $userId));

            // Получаем общую сумму возвратов
            $total_refunds = $wpdb->get_var($wpdb->prepare("SELECT SUM(pm.meta_value) FROM {$wpdb->prefix}postmeta as pm
        INNER JOIN {$wpdb->prefix}posts as p ON pm.post_id = p.ID
        INNER JOIN {$wpdb->prefix}postmeta as pm2 ON pm.post_id = pm2.post_id
        WHERE p.post_status IN  ({$order_staus}) AND p.post_type LIKE 'shop_order'
        AND pm.meta_key LIKE '_order_refund_amount' AND pm2.meta_key LIKE '_customer_user'
        AND pm2.meta_value LIKE %d $data_start", $userId));

            // Вычитаем сумму возвратов из общей суммы заказов
            if ($total_refunds) {
                $total_all -= $total_refunds;
            }


            $total_shipping = 0;
            if (BfwSetting::get('shipping-total-sum')) {

                $query = $wpdb->prepare("SELECT post_id
    FROM {$wpdb->prefix}postmeta
    WHERE meta_key = '_customer_user' AND meta_value = %d", $userId);

                $order_ids = $wpdb->get_col($query);

                foreach ($order_ids as $order_id) {
                    // Получаем статус заказа
                    $order_statust = get_post_status($order_id);

                    if (!is_array($order_staus)) {
                        $order_staus = (array)$order_staus;
                    }
                    // Проверяем, что статус заказа - wc-completed
                    if (in_array($order_statust, $order_staus)) {
                        // Получаем стоимость доставки для заказа
                        $shipping_cost = get_post_meta($order_id, '_order_shipping', true);

                        // Если стоимость доставки существует, добавляем её к общей сумме
                        if (!empty($shipping_cost)) {
                            $total_shipping += (float)$shipping_cost;
                        }
                    }
                }


            }


            $total_alls = $total_all - $total_shipping;

        }


        if (empty($total_alls)) {
            $total_alls = 0;
        }


        return $total_alls;
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
            if ((int)$point_every_day > 0) {
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


                    $cause = sprintf(__('Daily %s for the login.', 'bonus-for-woo'),
                        strtolower(BfwSetting::get('bonus-points-on-cart')));

                    //Записываем в историю
                    BfwHistory::add_history($user_id, '+', $point_every_day, '0', $cause);
                    //отправляем письмо

                    if (BfwSetting::get('email-when-everyday-login')) {
                        /*Шаблонизатор письма*/

                        $text_email = BfwSetting::get('email-when-everyday-login-text', '');

                        $title_email = BfwSetting::get('email-when-everyday-login-title',
                            __('Bonus points have been added to you!', 'bonus-for-woo'));

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

        $redirect = $_POST['redirect'];
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
        $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));

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

        $not_use_points_text = $head . ' <div class="text_how_many_points">' . __('You cannot use points on this order.',
                'bonus-for-woo') . '</div>' . $foot;


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
            $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));

            if ($woo->cart->applied_coupons && BfwSetting::get('balls-and-coupon')) {
                /*Если применяется купон*/
                $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));

                if (!in_array($cart_discount, $woo->cart->get_applied_coupons()) or in_array($cart_discount,
                        $woo->cart->get_applied_coupons()) && count($woo->cart->get_applied_coupons()) > 1) {
                    self::updateFastPoints($user_id, 0);
                    $woo->cart->remove_coupon($cart_discount);
                    $woo->cart->calculate_totals();//Пересчет общей суммы заказа
                    return $head . ' <div class="text_how_many_points">' . sprintf(__('To use %s, you must remove the coupon.',
                            'bonus-for-woo'), self::pointsLabel(5)) . '</div>' . $foot;
                }

            }


            if (BfwSetting::get('minimal-amount')) {
                $carttotal = $woo->cart->subtotal;
                // $carttotal = $woo->cart->total;

                $user_fast_points = self::getFastPoints($user_id);
                if ($carttotal < (int)BfwSetting::get('minimal-amount')) {
                    //сумма заказа должна быть больше чем 'minimal-amount'
                    foreach ($woo->cart->get_applied_coupons() as $code) {
                        if (strtolower($code) === mb_strtolower($cart_discount)) {
                            $woo->cart->remove_coupon($code);
                        }
                    }
                    self::updateFastPoints($user_id, 0);
                    $woo->cart->calculate_totals();
                    return $head . ' <div class="text_how_many_points">' . sprintf(__('To use %s, the order amount must be more than',
                            'bonus-for-woo'),
                            self::pointsLabel(5)) . ' ' . BfwSetting::get('minimal-amount') . ' ' . get_woocommerce_currency_symbol() . '</div>' . $foot;


                }
            }


            if ($vozmojniy_ball < $user_fast_points) {
                $woo->cart->remove_coupon($cart_discount);
                $woo->cart->calculate_totals();
            }

            $bonustext_in_cart = BfwSetting::get('bonustext-in-cart',
                __('You can use [points] in this order.', 'bonus-for-woo'));

            $bonustext_in_cart_array = [
                '[points]' => '<b>' . $vozmojniy_ball . ' ' . self::pointsLabel($vozmojniy_ball) . '</b>',
                '[discount]' => '<b>' . $vozmojniy_ball . ' ' . get_woocommerce_currency_symbol() . '</b>'
            ];
            $bonustext_in_carts = (new BfwEmail())::template($bonustext_in_cart, $bonustext_in_cart_array);


            $return = '<div class="text_how_many_points">' . $bonustext_in_carts . '</div>
<div class="write_points_form">
        <input type="hidden" name="action" value="computy_trata_points">
        <input type="hidden" name="redirect" value="' . $redirect . '">
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
        if (!isset($_POST['computy_input_points'], $_POST['redirect'])) {
            wp_send_json_error(__('The required data is missing from the request.', 'bonus-for-woo'));
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('Error getting user ID.', 'bonus-for-woo'));
            return;
        }
        $requestedPoints = self::roundPoints((float)$_POST['computy_input_points']);
        if ($requestedPoints <= 0) {
            wp_send_json_success($_POST['redirect']);
            return;
        }
        try {
            $woocommerce = WC();
            // Не начисляем кешбэк если применился сторонний купон
            if ($woocommerce->cart->applied_coupons && BfwSetting::get('yous_coupon_no_cashback')) {
                /*Если применяется купон*/
                $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
                //если система с помощью купонов
                if (!in_array($cart_discount, $woocommerce->cart->get_applied_coupons()) or in_array($cart_discount,
                        $woocommerce->cart->get_applied_coupons()) && count($woocommerce->cart->get_applied_coupons()) > 1) {
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
        $requestedPoints = isset($params['points']) ? self::roundPoints((float)$params['points']) : 0;
        $redirect = isset($params['redirect']) ? esc_url($params['redirect']) : wc_get_cart_url();

        $user_id = get_current_user_id();

        if ($requestedPoints <= 0) {
            return new WP_REST_Response(['success' => true, 'data' => $redirect], 200);
        }

        try {
            $woocommerce = WC();

            // Проверка на сторонние купоны
            if ($woocommerce->cart->applied_coupons && BfwSetting::get('yous_coupon_no_cashback')) {
                $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
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

        if (isset($woo->cart) && is_object($woo->cart)) {
            if (method_exists($woo->cart, 'get_cart')) {
                // Метод get_cart() существует, можно его вызвать
                $items = $woo->cart->get_cart();
            } else {
                return 0;
            }
        } else {
            return 0;
        }


        /*убираем из общей суммы: скидки, купоны, доставку*/
        //$maxPossiblePoints = $woo->cart->total;
        $maxPossiblePoints = $woo->cart->subtotal;

        // Получаем все примененные купоны
        $applied_coupons = $woo->cart->get_applied_coupons();

// Проходим по каждому купону
        foreach ($applied_coupons as $coupon_code) {
            // Если это не купон "бонусы", вычитаем его скидку
            if ($coupon_code !== mb_strtolower(BfwSetting::get('bonus-points-on-cart'))) {
                // Получаем объект купона
                $coupon = new WC_Coupon($coupon_code);

                // Получаем скидку от этого купона
                $discount_amount = $woo->cart->get_coupon_discount_amount($coupon_code);

                // Вычитаем скидку из общей суммы
                $maxPossiblePoints -= $discount_amount;
            }
        }


        /*Максимальный процент списания для про*/
        $max_percent = $BfwRoles::isPro() ? BfwSetting::get('max-percent-bonuses', 100) : 100;
        $max_percent = apply_filters('max-percent-bonuses-filter', $max_percent, $maxPossiblePoints);

        //Исключение товаров и категорий
        if ($BfwRoles::isPro()) {
            $maxPossiblePoints = BfwFunctions::bfwExcludeCategoryCashback($maxPossiblePoints);
            $maxPossiblePoints = BfwFunctions::bfwExcludeProductCashback($maxPossiblePoints);
        }

        //Когда включена настройка "Скрыть возможность потратить баллы для товаров со скидкой"
        if (BfwSetting::get('spisanie-onsale')) {
            $totalItems = 0;
            $saleItems = 0;

            foreach ($items as $item) {
                $totalItems++;
                $product = wc_get_product($item['product_id']);

                if ($product->is_on_sale()) {
                    $saleItems++;
                    $maxPossiblePoints -= $item['data']->get_price() * $item['quantity'];
                }
            }

            //Если все товары в корзине со скидкой
            //Если не разрешено списывать баллы у распродажи
            if ($saleItems === $totalItems) {
                return 0;
            }
        }


        $maxPossiblePoints = self::roundPoints($maxPossiblePoints * $max_percent / 100);


        return $maxPossiblePoints;
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

        $cartDiscount = BfwSetting::get('bonus-points-on-cart');
        if ($cartDiscount) {
            $cartDiscount = mb_strtolower(trim($cartDiscount));
            $cart = WC()->cart;

            if ($cart) {
                // Удаляем из корзины
                if (in_array($cartDiscount, array_map('strtolower', $cart->get_applied_coupons()), true)) {
                    $cart->remove_coupon($cartDiscount);
                    $cart->calculate_totals();
                }

                // 🔥 КРИТИЧЕСКИ ВАЖНО: Очистить applied_coupons в сессии
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
            'cod' => (string)$status_code,
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
        if (!isset($_POST['order_id']) || !isset($_POST['points'])) {
            wp_send_json_error(__('Not enough data.', 'bonus-for-woo'));
        }

        $order_id = (int)$_POST['order_id'];
        $order = new WC_Order($_POST['order_id']);
        $user_id = $order->get_user_id();
        //сколько баллов у пользователя
        $balluser = BfwPoints::getPoints($user_id);
        if ($balluser < $_POST['points']) {
            wp_send_json_error(__('The user does not have enough points. Balance', 'bonus-for-woo') . $balluser);
        }
        $points = (float)$_POST['points'];
        BfwPoints::updateFastPoints($user_id, $points);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(__('Order not found.', 'bonus-for-woo'));
        }

        // Создаем уникальный код купона
        $coupon_code = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
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

            wp_send_json_success(__('The coupon has been successfully created and applied to your order.',
                'bonus-for-woo'));
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
        $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
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


    /**
     * Adding a discount
     * Добавляем скидку
     *
     * @return void
     * @version 7.4.6
     */
    public static function bfwoo_add_fee(): void
    {

        if (!BfwSetting::get('bonus-points-on-cart')) {
            return;
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return;
        }

        $userPoints = self::getFastPoints($userId);
        if ($userPoints <= 0) {
            return;
        }

        $woocommerce = WC();
        if (!isset($woocommerce->cart)) {
            return;
        }

        $couponCode = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
        $cart = $woocommerce->cart;

        if (!$cart->has_discount($couponCode) && !in_array($couponCode, $cart->applied_coupons, true)) {
            self::safe_apply_coupon($cart, $couponCode);
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
            error_log("Coupon bonus error: " . $e->getMessage());
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

            $cart_discount = BfwSetting::get('bonus-points-on-cart');
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

        $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
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
        $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
        $coupon_data = $coupon->get_data();
        if (!empty($coupon_data) && strtolower($coupon_data['code']) === strtolower($cart_discount)) {
            $sprintf = BfwSetting::get('bonus-points-on-cart');
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

        $offset = (int)($_POST['offset'] ?? 0);
        $limit = 100;
        $search_by = $_POST['search_by'] ?? 'by_id';

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        $array = json_decode(stripslashes($_POST['response']), true);
        if (!$array || empty($array['data']['url'])) {
            wp_send_json_error('Invalid response data');
        }

        $url = $array['data']['url'];
        $file_path = wp_normalize_path(ABSPATH . str_replace(site_url(), '', $url));

        if (!file_exists($file_path)) {
            // error_log("Файл не найден: $file_path");
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
                    error_log('User not found with email: ' . $currRow[2]);
                    continue;
                }
            } elseif ($search_by === 'by_phone') {
                $id = self::get_customer_by_billing_phone($currRow[3]);
                if (!$id) {
                    continue;
                }
            } else {
                $id = (int)$currRow[0];
            }

            // Обновление баллов
            $point = (float)$currRow[4];
            $comment = (string)($currRow[5] ?? '');
            $user_point = (float)get_user_meta($id, 'computy_point', true);

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

            $prichina = sprintf(__('Use of %s', 'bonus-for-woo'), self::pointsLabel(5));
            BfwHistory::add_history($customer_user, '-', $computy_point_fast, $order_id, $prichina);


            $title_email = BfwSetting::get('email-when-order-confirm-title',
                __('Writing off bonus points', 'bonus-for-woo'));
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

        $order_total = (float)$order->get_total();
        $payment_method = $order->get_payment_method();

        // Проверка исключенных методов оплаты
        if (BfwSetting::get('exclude-payment-method') && in_array($payment_method,
                BfwSetting::get('exclude-payment-method'))) {
            return false;
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
            $cashback_amount = isset($cashback_data['amount']) ? (float)$cashback_data['amount'] : 0;
            // $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;

            $cashback_internal += $cashback_amount;
        }

        $shipping_total = (float)$order->get_shipping_total();
        $percent_data = $bfwRoles::getRole($user_id);
        $percent = isset($percent_data['percent']) ? (float)$percent_data['percent'] : 0;

        // Добавляем кешбэк за доставку, если не отключено
        if (!BfwSetting::get('cashback-for-shipping')) {
            $cashback_internal += $shipping_total * $percent / 100;
        }

        $cashback_internal = apply_filters('bfw-completed-points-internal', $cashback_internal, $order_id, $order);

        // Если internal ноль — ничего не начисляем
        if ((float)$cashback_internal <= 0) {
            return false;
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
        $cashback_internal = apply_filters('bfw-cashback-this-order-internal', $cashback_internal, $order_total,
            $percent);

        if ($cashback_for_user > 0 && $bfwRoles::isInvalve($user_id)) {
            if ($bfwRoles::isPro()) {
                // Проверка минимальной суммы (Pro)
                if (BfwSetting::get('minimal-amount')) {
                    $minimal_amount = (float)BfwSetting::get('minimal-amount');
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

            // Сохраняем мета заказа о начислении покупателю
            $order->update_meta_data('cashback_receipt', 'received');
            $order->update_meta_data('cashback_amount', $cashback_for_user);
            $order->save();

            // Запись в историю и e-mail
            $reason = __('Points accrual', 'bonus-for-woo');
            $bfwHistory::add_history($user_id, '+', $cashback_for_user, $order_id, $reason);

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
            $get_referral_invite = (int)$get_referral_invite;

            if ($get_referral_invite > 0) {
                $sumordersforreferral = (float)BfwSetting::get('sum-orders-for-referral', 0.0);
                $totalref = self::getSumUserOrders($get_referral_invite);

                if ($totalref >= $sumordersforreferral) {
                    // процент рефералки из настроек
                    $percent_for_referal = (float)BfwSetting::get('referal-cashback', 0.0);
                    // оплаченная сумма заказа
                    $paid_amount = (float)$order->get_total();

                    // вычисление баллов реферера: percent_for_referal% от оплаченной суммы
                    $pointsForRef = $paid_amount * ($percent_for_referal / 100);
                    $pointsForRef = self::roundPoints($pointsForRef);

                    if ($pointsForRef > 0 && $percent_for_referal > 0) {
                        BfwReferral::addReferralPoints($user_id, $pointsForRef, $get_referral_invite, $order_id);
                    }
                }

                // второй уровень
                if (BfwSetting::get('level-two-referral')) {
                    $get_referral_invite_two_level = get_user_meta($get_referral_invite, 'bfw_points_referral_invite',
                        true);
                    $get_referral_invite_two_level = (int)$get_referral_invite_two_level;

                    if ($get_referral_invite_two_level !== 0) {
                        $sumordersforreferral2 = (float)BfwSetting::get('sum-orders-for-referral', 0.0);

                        $totalref2 = self::getSumUserOrders($get_referral_invite_two_level);

                        if ($totalref2 >= $sumordersforreferral2) {
                            $percent_for_referal_two_level = floatval(BfwSetting::get('referal-cashback-two-level', 0));

                            $paid_amount = (float)$order->get_total();
                            $pointsForRef2 = $paid_amount * ($percent_for_referal_two_level / 100);
                            $pointsForRef2 = self::roundPoints($pointsForRef2);

                            if ($pointsForRef2 > 0 && $percent_for_referal_two_level > 0) {
                                BfwReferral::addReferralPoints($user_id, $pointsForRef2, $get_referral_invite_two_level,
                                    $order_id);
                            }
                        }
                    }
                }
            }
        }

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
                error_log("BfW: Order {$order_id} not found");
                return false;
            }

            // Если уже начислены баллы, то выходим
            if ($order->get_meta('cashback_receipt') == 'received') {
                return false;
            }

            $user_id = $order->get_customer_id();

            // Проверка пользователя
            if ($user_id === 0) {
                error_log("BfW: User ID is 0 for order {$order_id}");
                return false;
            }
            if (BfwSetting::get('daily_cashback_check')) {
                //добавляем задержку начисления баллов
                $order->update_meta_data('_bonus_cashback_pending',
                    ['process_after' => time() + (int)BfwSetting::get('daily_cashback_check') * DAY_IN_SECONDS]);
                $order->save();
                return true;
            }

            //если задержки начислений нет, то начисляем мгновенно
            if (self::addPointsForOrder($order_id)) {
                return true;
            }
            return false;

        } catch (Exception $e) {
            error_log("BfW Error in ifCompletedOrder for order {$order_id}: " . $e->getMessage());
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
     * Action when points refund is issued
     * Действие когда оформлен возврат баллов
     *
     * @param $order_id int
     *
     * @return void
     * @version 6.3.5
     */
    public static function refundedPoints(int $order_id): void
    {
        global $wpdb;

        $order = wc_get_order($order_id);
        $customer_user = $order->get_customer_id();

        $computy_point_old = self::getPoints($customer_user);


        $fee_total = BfwFunctions::feeOrCoupon($order);
        $count_point = $computy_point_old + $fee_total;

        $cause = __('Refund of bonus points', 'bonus-for-woo');

        //ищем последнее добавление баллов в истории
        $getplusball = $wpdb->get_var($wpdb->prepare('SELECT points FROM ' . $wpdb->prefix . 'bfw_history_computy WHERE user = %d AND symbol="+" AND orderz = %d ORDER BY id DESC LIMIT 1',
            $customer_user, $order_id));

        $info_email = '';


        if (!empty($getplusball)) {
            $getplusball = self::roundPoints($getplusball);
        } else {
            $getplusball = 0;
        }

        if ($getplusball > 0) {
            BfwHistory::add_history($customer_user, '-', $getplusball, $order_id, $cause);
            $count_point -= $getplusball;
            $info_email .= sprintf(__('The %1$s bonus points you earned for order no. %2$s have been canceled.',
                'bonus-for-woo'), $getplusball, $order_id);
        }

        if ($fee_total > 0) {
            /*Добавляем списанные баллы*/
            BfwHistory::add_history($customer_user, '+', $fee_total, $order_id, $cause);
            $info_email .= sprintf(__('You have returned %1$d bonus points for order number %2$d.', 'bonus-for-woo'),
                $fee_total, $order_id);
        }
        self::updatePoints($customer_user, $count_point);//Обновляем баллы клиенту
        BfwRoles::updateRole($customer_user); //Обновляем роль клиенту

        //Если уже начислены баллы, то выходим
        if ($order->get_meta('cashback_receipt') == 'received') {
            $order->delete_meta_data('cashback_amount');
            $order->delete_meta_data('cashback_receipt');
            $order->save();
        }

        /*email*/
        /*Шаблонизатор письма*/
        $title_email = BfwSetting::get('email-when-order-change-title-vozvrat',
            __('Refund of bonus points', 'bonus-for-woo'));

        $text_email = BfwSetting::get('email-when-order-change-text-vozvrat', '');

        $user = get_userdata($customer_user);
        $get_referral = get_user_meta($customer_user, 'bfw_points_referral', true);
        $text_email_array = array(
            '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral),
            '[user]' => $user->display_name,
            '[cashback]' => $getplusball,
            '[order]' => $order_id,
            '[points]' => $fee_total,
            '[total]' => $count_point
        );
        $message_email = BfwEmail::template($text_email, $text_email_array);
        /*Шаблонизатор письма*/

        if (BfwSetting::get('email-when-order-change')) {
            if ($getplusball > 0 || $fee_total > 0) {
                (new BfwEmail())->getMail($customer_user, '', $title_email, $message_email);
            }
        }
        /*email*/
    }


    /**
     * Removing points for inactivity.
     * Удаление баллов за бездействие.
     *
     * @return void
     * @version 6.3.5
     */
    public static function deleteBallsOldClients(): void
    {
        $day_day = (int)BfwSetting::get('day-inactive', 0);
        $exclude_role = BfwSetting::get('exclude-role', array());
        $exclude_role = apply_filters('bfw-exclude-role-for-cron', $exclude_role);
        $args = array(
            'role__not_in' => $exclude_role,
            'meta_query' => array(array('key' => 'computy_point', 'value' => 0, 'compare' => '>')),
        );
        $users = get_users($args);
        $today = strtotime(gmdate("d.m.Y"));
        global $wpdb;
        $bfwEmail = new BfwEmail();
        $bfwHistory = new BfwHistory();

        foreach ($users as $user) {
            $last_actions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bfw_history_computy WHERE user = {$user->ID} ORDER BY date DESC LIMIT 1");
            $last_action = $last_actions ? strtotime(gmdate("d.m.Y",
                strtotime($last_actions[0]->date))) : strtotime(gmdate("d.m.Y", strtotime($user->user_registered)));
            $seconds = abs($today - $last_action);
            $days = floor($seconds / 86400);

            if (BfwSetting::get('day-inactive-notice')) {
                $day_notice_remove_points = BfwSetting::get('day-inactive-notice');
                if ($day_notice_remove_points !== '' && $day_notice_remove_points > 0) {
                    $notice = get_user_meta($user->ID, 'mail_remove_points', true) ?? '';
                    if ($notice !== 'yes' && $days > $day_day - $day_notice_remove_points) {
                        $title_email = BfwSetting::get('email-when-inactive-notice-title',
                            __('Your points will be deleted soon.', 'bonus-for-woo'));

                        $text_email = BfwSetting::get('email-when-inactive-notice-text', '');
                        $ball_user = self::getPoints($user->ID);
                        $text_email_array = array(
                            '[user]' => $user->display_name,
                            '[days]' => $day_notice_remove_points,
                            '[points]' => $ball_user
                        );
                        $message_email = $bfwEmail::template($text_email, $text_email_array);
                        if (BfwSetting::get('email-when-inactive-notice')) {
                            $bfwEmail->getMail($user->ID, '', $title_email, $message_email);
                        }
                        update_user_meta($user->ID, 'mail_remove_points', 'yes');
                    }
                }
            }

            if ($days > $day_day && $day_day > 1) {
                $computy_point_old = self::getPoints($user->ID);
                $bfwHistory::add_history($user->ID, '-', $computy_point_old, '0',
                    sprintf(__('Inactivity %d days', 'bonus-for-woo'), $day_day));
                update_user_meta($user->ID, 'mail_remove_points', 'no');
                self::updatePoints($user->ID, 0);
            }
        }
    }


    public static function handleSendPointsFromOrder()
    {
        $order_id = intval($_POST['orderId']);

        //начисляем баллы
        if (self::addPointsForOrder($order_id)) {
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

        $title_email = $bonus_option_name['email-whens-birthday-title'] ?? __('Bonus points on your birthday',
            'bonus-for-woo');
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
     * Метод для теста. Выводит текущее изменение статуса
     *
     * @param $order_id
     * @return void
     * @testMethod
     */
    public static function test($order_id): void
    {
        $order = wc_get_order($order_id);

        // Возвращаем статус заказа
        $status = $order->get_status();

        error_log('Изменение статуса:' . $status);
    }
}
