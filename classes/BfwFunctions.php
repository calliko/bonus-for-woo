<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class functions
 * Класс с различными функциями, которые не подходят к другим классам
 *
 * @version 5.5.0
 *
 */
class BfwFunctions
{

    /**  Добавляем стили на фронте
     * @return void
     */
    public static  function bfwooComputyStyles(): void
    {
        wp_register_style(
                'bonus-computy-style',
                BONUS_COMPUTY_PLUGIN_URL . '_inc/bonus-computy-style.css',
                array(),
                BONUS_COMPUTY_VERSION
        );
        wp_enqueue_style('bonus-computy-style');
    }


    /** Добавляем скрипты на фронте
     * @return void
     */
    public static  function bfwooComputyScript(): void
    {
        wp_register_script(
                'bonus-computy-script',
                BONUS_COMPUTY_PLUGIN_URL . '_inc/bonus-computy-script.js',
                array('jquery'),
                BONUS_COMPUTY_VERSION,
                true
        );
        wp_enqueue_script('bonus-computy-script');
    }


    /**
     * Launched when the plugin is activated
     * Запускается при активации плагина
     *
     * @return void
     * @version 6.4.0
     */
    public static function bfwActivate(): void
    {
        // Возможность менеджерам настраивать плагин
        $shop_manager = get_role('shop_manager');
        $shop_manager->add_cap('manage_options');
        $shop_manager->add_cap('edit_users');
        $shop_manager->add_cap('edit_user');

        delete_option('rewrite_rules');
        //Проверка бд
        BfwDB::checkDb();
        BfwAdmin::bfw_search_pro();
    }

    /**
     * Runs when the plugin is deactivated
     * Запускается при деактивации плагина
     *
     * @return void
     * @version 6.4.0
     */
    public static function bfwDeactivate(): void
    {
        delete_option('rewrite_rules');
    }

    /**
     * Action after plugin update
     * Действие после обновления плагина
     *
     * @param $upgrader_object !!не удалять!!
     * @param $options
     *
     * @return void
     * @version 7.2.1
     */
    public static function bfwUpdateCompleted($upgrader_object, $options): void
    {
        if (!empty($options['action']) &&
                $options['action'] === 'update' &&
                $options['type'] === 'plugin' &&
                !empty($options['plugins'])) {
            $our_plugin = 'bonus-for-woo/index.php';
            foreach ($options['plugins'] as $plugin) {
                if ($plugin === $our_plugin) {
                    set_transient('bfw_pro_updated', 1);
                    break; // Прерываем цикл после нахождения плагина
                }
            }
        }
    }


    /**
     * Find the points used in the order
     * Находим используемые баллы в заказе
     *
     * @param $order object
     *
     * @return float
     * @version 6.6.8
     */
    public static function feeOrCoupon(object $order): float
    {
        $fee_total = 0;

        $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
        // Получаем данные о купонах
        $coupons = $order->get_coupons();
        // Массив для хранения информации о скидках
        foreach ($coupons as $coupon) {
            if (strtolower($coupon->get_code()) === $cart_discount) {
                $fee_total = $coupon->get_discount();
                break; // Прерываем цикл, так как нашли нужный купон
            }
        }
        return abs($fee_total); // Используем abs вместо absint для float
    }


    /**
     * Copyright computy
     * Копирайт computy.ru
     *
     * @return void
     * @version 6.4.0
     */
    public static function computy_copyright(): void
    {
        if (!BfwRoles::isPro()) {
            ?>
            <div class="computy_copyright"><?php
                echo __('Works on', 'bonus-for-woo'); ?> <a
                        href="https://computy.ru/blog/bonus-for-woo-wordpress/" target="_blank"
                        title="<?php echo __('About the bonus for woo plugin.', 'bonus-for-woo'); ?>"> Bonus for
                    woo.</a></div>
            <?php
        }
    }

    /**
     * Checking the key
     *
     * @param string $key
     * @param int $alternate_server
     *
     * @return void
     * @version 6.6.3
     */
    public static function checkingKey(string $key, int $alternate_server): void
    {
        $get = array('key' => sanitize_text_field($key), 'site' => get_site_url());
        $response = wp_remote_get(base64_decode('aHR0cHM6Ly9jb21wdXR5LnJ1L0FQSS9hcGkucGhwPw==') . http_build_query($get));
        if ($alternate_server === 1) {
            //todo переделать
            //  $response = wp_remote_get(base64_decode('aHR0cHM6Ly9jb21wdXR5LmFsd2F5c2RhdGEubmV0L0FQSS9hcGkucGhwPw==') . http_build_query($get));
        }


        if (!is_wp_error($response)) {
            $json = wp_remote_retrieve_body($response);
            $data = json_decode($json, true);

            if (isset($data['status']) && $data['status'] === 'OK') {
                if (isset($data['response']) && $data['response']) {
                    /*Yes, that's it.☺ If you have any questions or suggestions, write to https://t.me/ca666ko , let's chat.*/
                    /*Да, вот так просто.☺ Есть вопросы и предложения пиши https://t.me/ca666ko , пообщаемся.*/
                    update_option(base64_decode('Ym9udXMtZm9yLXdvby1wcm8='), base64_decode('YWN0aXZl'));
                    wp_safe_redirect('/wp-admin/admin.php?page=bonus_for_woo-plugin-options');
                    exit;
                } else {
                    echo '<div class="notice notice-error is-dismissible">' . __('The key is not correct! Contact info@computy.ru',
                                    'bonus-for-woo') . '</div>';
                }
            } elseif (isset($data['error'])) {
                $dataError = '';
                if ($data['error'] == '2') {
                    $dataError = __('The key is not correct.', 'bonus-for-woo');
                }
                echo '<div class="notice notice-error is-dismissible">' . __('Error code:',
                                'bonus-for-woo') . $data['error'] . ' ' . $dataError . '</div>';
            } else {
                echo '<div class="notice notice-error is-dismissible">' . __('Error while receiving data.',
                                'bonus-for-woo') . '</div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible">' . __('Error connecting to key verification server.',
                            'bonus-for-woo') . '</div>';
        }
    }

    /**
     * Exclusion of product categories
     * Исключение категорий товаров
     *
     * @param float $total_order
     *
     * @return float
     * @version 6.4.0
     */
    public static function bfwExcludeCategoryCashback(float $total_order): float
    {
        $categoriexs = BfwSetting::get('exclude-category-cashback', 'not');
        if ($categoriexs !== 'not') {
            $cart_items = WC()->cart->get_cart(); // получаем корзину один раз
            foreach ($cart_items as $cart_item_key => $cart_item) {
                $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item,
                        $cart_item_key);
                if (has_term($categoriexs, 'product_cat', $cart_item['product_id'])) {
                    $sum_exclude_cat = $_product->get_price() * $cart_item['quantity'];
                    $total_order -= $sum_exclude_cat;
                }
            }
        }
        return $total_order;
    }


    /**
     * Exclusion of products
     * Исключение товаров
     *
     * @param float $total_order
     *
     * @return float
     * @version 6.4.0
     */
    public static function bfwExcludeProductCashback(float $total_order): float
    {
        $exclude_product = BfwSetting::get('exclude-tovar-cashback', '');
        $products = apply_filters('bfw-excluded-products-filter', explode(",", $exclude_product), $exclude_product);

        $cart_items = WC()->cart->get_cart(); // получаем корзину один раз
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            if (in_array($cart_item['product_id'], $products)) {
                $categoriexs = BfwSetting::get('exclude-category-cashback', 'not');
                if (!has_term($categoriexs, 'product_cat',
                        $cart_item['product_id'])) {/*если еще не исключены категории, то:*/
                    $total_order -= $_product->get_price() * $cart_item['quantity'];
                }
            }
        }

        return $total_order;
    }


    /**
     * Sorting an array in ascending order
     * Сортировка массива по возрастанию
     *
     * @return array
     * @version 6.4.0
     */
    public static function arrayMultisortValue(): array
    {
        $args = func_get_args();
        $data = array_shift($args);

        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $args[$n] = array_column($data, $field);
            }
        }

        $args[] = &$data;
        call_user_func_array('array_multisort', $args);

        return $data;
    }

    /**
     * Changing the status of a post\product
     * Меняем статус поста\товара
     *
     * @param $post_status
     * @param $post
     *
     * @return bool
     * @version 6.4.0
     */
    public static function setPostStatusBfw($post_status, $post = null): bool
    {
        $post = get_post($post);

        if (!is_object($post)) {
            return false;
        }

        return wp_update_post(array('ID' => $post->ID, 'post_status' => $post_status));
    }

    /**
     * Declension
     * Склонение
     *
     * @param float $points
     * @param string $label_point
     * @param string $label_point_two
     * @param string $label_points
     *
     * @return string
     * @version 6.4.0
     */
    public static function declination(
            float $points,
            string $label_point,
            string $label_point_two,
            string $label_points
    ): string {
        $words = array($label_point, $label_point_two, $label_points);
        $points = (int)$points;
        $num = $points % 100;
        if ($num > 19) {
            $num %= 10;
        }

        switch ($num) {
            case 1:
                $out = $words[0];
                break;
            case 2:
            case 3:
            case 4:
                $out = $words[1];
                break;
            default:
                $out = $words[2];
                break;
        }

        return $out;
    }


    /**
     * Refreshes the page when choosing a payment method
     * Обновляет страницу при выборе метода оплаты при выводе [woocommerce_checkout]
     *
     * @return void
     * @version 6.4.0
     */
    public static function updatePageIfChangePaymentMethod(): void
    {
        $expm = BfwSetting::get('exclude-payment-method', array());
        if (isset($expm[0])) {
            $count_expm = count($expm);
            $ert = "ert==='" . $expm[0] . "'";
            $et = "et==='" . $expm[0] . "'";
            if ($count_expm > 1) {
                for ($i = 1, $iMax = count($expm); $i < $iMax; $i++) {
                    $ert .= " || ert==='" . $expm[$i] . "' ";
                    $et .= " || et==='" . $expm[$i] . "' ";
                }
            }
            ?>
            <script>
                (function ($) {
                    window.addEventListener('load', function () {

                        function getSelectedPaymentMethod() {
                            return $('input[name^="payment_method"]:checked, input[name^="radio-control-wc-payment-method-options"]:checked').val();
                        }

                        // Отслеживание изменений в динамически добавляемых элементах

                        let ert = getSelectedPaymentMethod();

                        function checkAndHide() {
                            if ($('.order-cashback').length) {
                                if (<?php echo $ert; ?>) {
                                    $('.bfw-write-off-block').hide();
                                    $('.order-cashback').hide();
                                    $('.bfw-order-cashback-blocks').hide();
                                }
                            } else {
                                //так сделано потому что динамические элементы чекаута
                                setTimeout(checkAndHide, 500); // Проверяем каждые 500 мс
                            }
                        }

                        checkAndHide();


                        $(document).on('change', 'input[name^="payment_method"], input[name^="radio-control-wc-payment-method-options"]', function () {
                            let et = $(this).val();
                            if (<?php echo $et; ?>) {
                                $('.order-cashback').hide();
                                $('.bfw-write-off-block').hide();
                                $('.bfw-order-cashback-blocks').hide();
                                $(".remove_points").trigger("click");
                            } else {
                                $('.order-cashback').show();
                                $('.bfw-write-off-block').show();
                                $('.bfw-order-cashback-blocks').show();
                            }
                        });
                    });

                })(jQuery);
            </script>
            <?php
        }
        ?>
        <script>jQuery(document).ready(function ($) {
                $('form.checkout').on('change', 'input[name^="payment_method"]', function () {
                    $(document.body).trigger('update_checkout');
                });
            });
        </script>
        <?php
    }


    /**
     * Показ всплывающих подсказок.
     *
     * @param string $text Help tip text.
     * @param string $event danger | faq | info
     *
     * @return string
     * @since  5.0.0
     */
    public static function helpTip(string $text, string $event = 'faq'): string
    {
        return '<span class="bfw-help-tip ' . esc_attr($event) . '" data-tip="' . $text . '"></span>';
    }


    /**
     * Сохраняет метаданные о товаре, что он был со скидкой
     *
     * @param $item
     * @param $cart_item_key
     * @param $values
     * @param $order
     *
     * @return void
     * @since  6.4.9
     */
    public static function saveSaleStatusToOrderItemMeta($item, $cart_item_key, $values, $order): void
    {
        // Проверяем, был ли товар со скидкой
        if (isset($values['data']) && $values['data']->is_on_sale()) {
            // Добавляем метаданные к элементу заказа
            $item->update_meta_data('_was_on_sale', 'yes');
        }
    }


    /**
     * Получает количество заказов пользователя
     */
    public static function get_customer_order_count(int $user_id, array $order_status = null): int
    {
        if (!$user_id) {
            return 0;
        }

        if (!$order_status) {
            $order_status = array('completed', 'processing', 'on-hold');
        }
        $args = array(
                'customer_id' => $user_id,
                'status' => $order_status,
                'limit' => -1, // Получаем все заказы
                'return' => 'ids',
        );

        return count(wc_get_orders($args));
    }
}
