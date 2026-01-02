<?php

defined( 'ABSPATH' ) || exit;

/**
 * Cashback Management Class
 * Класс управления вывода кешбэка кешбэком
 *
 * Class BfwCashback
 *
 * @version 6.4.0
 */
class BfwCashback
{


    /**
     * Cashback write-off output in the cart
     * Вывод суммы начисления кешбэка в корзине и в оформлении заказа
     *
     * @return array|null
     * @version 6.8.0
     */
    public static function bfw_get_cashback_in_cart(): ?array
    {
        global $wpdb;
        $upto = '';
        $percentUp = '';
        $woocommerce = WC();
        $user_id = get_current_user_id();

        $bfwFunctions = new BfwFunctions();
        $Role = new BfwRoles();
        $bfwSingleProduct = new BfwSingleProduct();

        // Calculate total order amount
        $total_order = $woocommerce->cart->total;
        if (BfwSetting::get('exclude-fees-coupons')) {
            $total_order = $woocommerce->cart->get_subtotal();
        }

        $user_role = $Role->getRole($user_id);
        if (is_user_logged_in()) {
            $user_percent = $user_role['percent'] ?? 0;
        } else {
            $user_percent = BfwRoles::maxPercent();
        }

        // Calculate cashback amount from products
        $cashback_this_order = 0;
        $coupon_deduction = 0;
        $cart_items = $woocommerce->cart->get_cart();

        // Считает сумму всех примененных купонов в корзине не считая бонусных баллов
        $filtered_discounts = self::calculate_coupons_total_except_specific();

        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item['product_id'];
            //Если товар имеет variation_id, значит это вариативный товар
            $variation_id = $cart_item['variation_id'] ?? null;

            $product_cashback_info = $bfwSingleProduct->cashbackFromOneProduct($product_id, $user_id, $variation_id);

            $cashback_this_order += $product_cashback_info['amount'] * $cart_item['quantity'];

            $product_percent = $product_cashback_info['percent'] ?? $user_percent;

            // Распределяем купон пропорционально стоимости товара
            //   $product_share = ($cart_item['line_total'] / $woocommerce->cart->get_subtotal()) * $filtered_discounts;
            //   $coupon_deduction += $product_share * $product_percent / 100;


        }


        $coupon_deduction = self::calculate_coupon_deduction_with_product_percents(
            $filtered_discounts,
            $cart_items,
            $user_id,
            $user_percent
        );


        if ($user_percent != 0) {
            $cashback_this_order -= $coupon_deduction;
            //$cashback_this_order -= $filtered_discounts * $user_percent / 100;

            if (!BfwSetting::get('cashback-for-shipping')) {
                $shipping_total = $woocommerce->cart->shipping_total;
                $cashback_this_order += $shipping_total * $user_percent / 100;
            }
        }


        // Calculate user's role and cashback percentage
        if (is_user_logged_in()) {
            //учитываем в кешбэке баллы, которые хочет списать

            $use_points = BfwPoints::getFastPoints($user_id);


            if ($user_percent != 0) {
                // вычитаем использованные баллы из кешбэка
                $cash_fast = $use_points * $user_percent / 100;
                $cashback_this_order = $cashback_this_order - $cash_fast;
            }


            $total_all = BfwPoints::getSumUserOrders($user_id);
            $sumbudet = $total_all + $total_order;
            $all_role = $wpdb->get_results("SELECT summa_start FROM " . $wpdb->prefix . "bfw_computy");
            $all_role = json_decode(wp_json_encode($all_role), true);
            $all_role = $bfwFunctions::arrayMultisortValue($all_role, 'summa_start', SORT_ASC);
            $summa = 0;
            foreach ($all_role as $a) {
                if ($sumbudet >= $a['summa_start']) {
                    $summa = $a['summa_start'];
                }
            }
            $you_next_role = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "bfw_computy WHERE summa_start=%s",
                $summa));
            $this_percent = BfwRoles::getRole($user_id);
            $percent = $this_percent['percent'] ?? 0;
            $percent = apply_filters('bfw-filter-percent-in-cart', $percent, $total_order);
            $cashback_this_order = apply_filters('bfw-cashback-this-order', $cashback_this_order, $total_order,
                $percent);

            if (BfwSetting::get('cashback_for_first_order') && (new BfwFunctions())->get_customer_order_count($user_id) === 0) {
                $percent = BfwSetting::get('cashback_for_first_order');
            }
            $percentUp = ' ' . $percent . '%';

            if (!BfwSetting::get('cashback_for_first_order') && $you_next_role && $you_next_role[0]->percent !== $this_percent['percent'] && $this_percent['percent'] !== 0) {
                $percent = $you_next_role[0]->percent;
                $percentUp = ' ' . $percent . "% ▲";

                if ($this_percent['percent'] != 0) {
                    $cashback_this_order = $cashback_this_order * $percent / $this_percent['percent'];
                }

            }

            //если используются баллы, то не начисляем кешбэк
            if (BfwSetting::get('yous_balls_no_cashback') && $use_points > 0) {
                $cashback_this_order = 0;
            }

            // Не начисляем кешбэк если применился сторонний купон
            if ($woocommerce->cart->applied_coupons && BfwSetting::get('yous_coupon_no_cashback')) {
                /*Если применяется купон*/
                $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
                //если система с помощью купонов
                if (!in_array($cart_discount, $woocommerce->cart->get_applied_coupons()) or in_array($cart_discount,
                        $woocommerce->cart->get_applied_coupons()) && count($woocommerce->cart->get_applied_coupons()) > 1) {
                    $cashback_this_order = 0;
                }
            }
            // Не начисляем кешбэк если применился сторонний купон


        } else {
            if (!BfwSetting::get('bonus-in-price-upto')) {
                $upto = __('up to', 'bonus-for-woo');
            }
        }

        if (BfwRoles::isPro() && BfwSetting::get('minimal-amount') && $total_order < BfwSetting::get('minimal-amount') && BfwSetting::get('minimal-amount-cashback')) {
            $cashback_this_order = 0;
        }


        // Return cashback data
        $return = array();
        if ($cashback_this_order > 0) {
            $return['cashback_title'] = sprintf(__('Cashback %s', 'bonus-for-woo'), $upto);
            $return['percentUp'] = $percentUp;
            $return['cashback_this_order'] = BfwPoints::roundPoints($cashback_this_order);
            $return['upto'] = $upto;
        }

        return $return;
    }


    /**
     * When is cashback output displayed in [woocommerce-cart]
     * Когда вывод кешбэка выводится в [woocommerce-cart]
     *
     * @return void
     * @version 6.4.0
     */
    public static function getCashbackInCart(): void
    {
        $return = self::bfw_get_cashback_in_cart();

        if ($return) {
            $cashback_title = esc_html($return['cashback_title']);
            $percent_up = esc_html($return['percentUp']);
            $upto_label = BfwPoints::howLabel(
                $return['upto'],
                $return['cashback_this_order']
            );

            echo '<tr class="order-cashback">'
                . '<th><span class="order-cashback-title">' . $cashback_title . ' ' . $percent_up . '</span></th>'
                . '<td data-title="' . $cashback_title . ' ' . $percent_up . '">'
                . '<span class="order-cashback-value">' . $return['cashback_this_order'] . ' ' . $upto_label . '</span>'
                . '</td>'
                . '</tr>';
        }
    }


    /**
     * Cashback withdrawal when the cart is implemented using blocks.
     * Вывод кешбэка когда корзина реализована с помощью blocks.
     *
     * @return void
     */
    public static function getCashbackInCartBlocks(): void
    {
        $return = self::bfw_get_cashback_in_cart();

        if ($return) {
            $cashback_title = esc_html($return['cashback_title']);
            $percent_up = esc_html($return['percentUp']);
            $upto_label = BfwPoints::howLabel(
                $return['upto'],
                $return['cashback_this_order']
            );

            echo '<div class="bfw-order-cashback-title-blocks">' . $cashback_title . $percent_up . '</div>'
                . '<div class="bfw-order-cashback-value-blocks">' . $return['cashback_this_order'] . ' ' . $upto_label . '</div>';
            exit();
        }
        exit();
    }


    /**
     * When the cashback output is displayed by shortcode.
     * Когда вывод кешбэка выводится шорткодом.
     *
     * @return string
     * @version 6.4.0
     */
    public static function bfwGetCashbackInCartForShortcode(): string
    {
        $return = self::bfw_get_cashback_in_cart();
        if ($return) {
            return '<div class="bfw-how-match-cashback-block">
                 <span class="order-cashback-title">' . $return['cashback_title']
                . $return['percentUp'] . '</span>
            <span class="order-cashback-value">' .
                $return['cashback_this_order'] . ' ' . BfwPoints::howLabel(
                    $return['upto'],
                    $return['cashback_this_order']
                ) . '
            </span>
            </div>';
        }

        return '';
    }


    /**
     * Считает сумму всех примененных купонов в корзине
     *
     * @return int
     */
    public static function calculate_coupons_total_except_specific(): float
    {
        $woo = WC();
        $coupons_total = 0;
        $excluded_coupon = BfwSetting::get('bonus-points-on-cart'); // Купон, который нужно исключить

        // Получаем все примененные купоны
        if (isset($woo->cart) && $woo->cart->get_applied_coupons()) {
            foreach ($woo->cart->get_applied_coupons() as $code) {
                if (mb_strtolower($code) !== mb_strtolower($excluded_coupon)) {
                    $discount = $woo->cart->get_coupon_discount_amount($code);
                    $coupons_total += $discount;
                }
            }
        }
        return $coupons_total;
    }

    private static function calculate_coupon_deduction_with_product_percents(
        $total_discounts,
        $cart_items,
        $user_id,
        $user_percent
    ) {
        $bfwSingleProduct = new BfwSingleProduct();
        $deduction = 0;

        // Получаем общую стоимость товаров (без скидок)
        $cart_subtotal = WC()->cart->get_subtotal();
        if ($cart_subtotal <= 0) {
            return 0;
        }

        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? null;
            $line_total = $cart_item['line_total']; // стоимость товара после скидок

            // Получаем процент кешбэка для этого товара
            $product_cashback_info = $bfwSingleProduct->cashbackFromOneProduct($product_id, $user_id, $variation_id);

            // Если метод возвращает массив с percent, используем его, иначе берем сумму и вычисляем процент

            $product_percent = $product_cashback_info['percent'];


            // Распределяем скидки пропорционально стоимости товара
            $product_ratio = $line_total / $cart_subtotal;
            $product_discount_share = $total_discounts * $product_ratio;

            // Вычисляем вычет для этого товара
            $deduction += $product_discount_share * $product_percent / 100;
        }

        return $deduction;
    }


    /** Находим все заказы на которые не начислен кешбэк
     *
     * @return void
     */
    public static function cashbackPrepare()
    {
        $order_status = BfwSetting::get('add_points_order_status', 'completed');
        $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
        $args = [
            'type' => 'shop_order',
            'status' => ['wc-' . $order_status],
            'limit' => -1,
            'return' => 'ids',

            'meta_query' => [
                'relation' => 'AND',

                // 1. cashback_receipt не существует
                [
                    'key' => 'cashback_receipt',
                    'compare' => 'NOT EXISTS',
                ],

                // 2. не должно быть применено купона "bonus_points"
                [
                    'key' => '_used_coupons',
                    'value' => $cart_discount, // ваш системный код купона
                    'compare' => 'NOT LIKE',
                ],
            ],
        ];


        $ids = wc_get_orders($args);
        update_option('cashback_orders_to_recount', $ids);

        wp_send_json([
            'total' => count($ids),
        ]);
    }

    /** Начисление кешбэка за прошлые заказы
     *
     * @return void
     */
    public static function cashbackRecount()
    {
        $batch = 20; // количество заказов за шаг

        $order_ids = get_option('cashback_orders_to_recount', []);
        $offset = intval($_POST['offset'] ?? 0);

        $ids = array_slice($order_ids, $offset, $batch);

        foreach ($ids as $order_id) {
            $order = wc_get_order($order_id);
            // Пропускаем, если кешбэк уже начислен
            if ($order->get_meta('cashback_receipt')) {
                continue;
            }

            BfwPoints::addPointsForOrder($order_id, false);
        }
        $processedTotal = $offset + count($ids);
        wp_send_json([
            'nextOffset' => $offset + $batch,
            'total' => count($order_ids),
            'processedTotal' => $processedTotal,
        ]);
    }


}
