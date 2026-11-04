<?php

defined('ABSPATH') || exit;

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
     *
     * @return void
     */
    public static function bfwooComputyStyles(): void
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
     *
     * @return void
     */
    public static function bfwooComputyScript(): void
    {
        wp_register_script(
                'bonus-computy-script',
                BONUS_COMPUTY_PLUGIN_URL . '_inc/bonus-computy-script.js',
                array('jquery'),
                BONUS_COMPUTY_VERSION,
                true
        );
        wp_enqueue_script('bonus-computy-script');
        wp_localize_script('bonus-computy-script', 'wpApiSettings', [
                'nonce' => wp_create_nonce('wp_rest'),
                 'isLoggedIn' => is_user_logged_in(),
        ]);
    }


    /**
     * Path to the points export CSV.
     *
     * The file used to live inside the plugin directory. A plugin installed from
     * the admin screen cannot write there (and on nginx the bundled .htaccess
     * does nothing), so the export silently failed. Uploads are writable and,
     * with the default flat structure, not web-readable.
     *
     * @return string
     * @since 8.5.1
     */
    public static function getExportFilePath(): string
    {
        $upload_dir = wp_upload_dir(null, false);

        return wp_normalize_path(trailingslashit($upload_dir['basedir']) . 'bfw_points_export.csv');
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
        /*
         * ВАЖНО: плагин НЕ должен выдавать роли shop_manager права администратора.
         * Раздача manage_options / edit_users при активации превращала любого
         * менеджера магазина в полного администратора сайта (и не откатывалась
         * при деактивации). Доступ к страницам настроек плагина менеджеру не нужен.
         */
        delete_option('rewrite_rules');
        //Проверка бд
        BfwDB::checkDb();
        BfwAdmin::bfw_search_pro();
        // Проверяем, не остались ли права от предыдущих версий плагина
        self::revokeLegacyElevatedCaps();
    }

    /**
     * Снимает права manage_options / edit_users, выданные роли shop_manager
     * предыдущими версиями плагина (<= 8.3.3).
     *
     * Вызывается на activation/upgrade и один раз на admin_init, чтобы
     * уже существующие сайты избавились от эскалации без ручных действий.
     * edit_user НЕ трогаем — это штатное право WooCommerce для менеджеров.
     *
     * @return void
     * @version 8.3.4
     */
    public static function revokeLegacyElevatedCaps(): void
    {
        $shop_manager = get_role('shop_manager');
        if (!$shop_manager) {
            return;
        }

        $removed = array();
        foreach (array('manage_options', 'edit_users') as $cap) {
            if (!empty($shop_manager->capabilities[$cap])) {
                $shop_manager->remove_cap($cap);
                $removed[] = $cap;
            }
        }

        if ($removed) {
            BfwLogs::addLog(
                'security',
                get_current_user_id(),
                'Removed elevated capabilities from shop_manager: ' . implode(', ', $removed)
            );
        }

        update_option('bfw_caps_reconciled', BONUS_COMPUTY_VERSION, false);
    }


    /**
     * Runs when the plugin is deactivated
     * Запускается при деактивации плагина
     *
     * Снимаем все cron-события: иначе WordPress продолжает дёргать их
     * ежедневно, а обработчики уже не зарегистрированы — холостые вызовы
     * навсегда остаются в расписании.
     *
     * @return void
     * @version 8.5.0
     */
    public static function bfwDeactivate(): void
    {
        self::unscheduleCronEvents();
        delete_option('rewrite_rules');
    }

    /**
     * Снимает все cron-события плагина.
     *
     * @param int|null $site_id Сайт, для которого снимаем (мультисайт)
     * @return void
     * @version 8.5.0
     */
    public static function unscheduleCronEvents(?int $site_id = null): void
    {
        $hooks = array('bfw_clear_old_cashback', 'bfw_search_birthday', 'bfw_daily_cashback_check');

        if ($site_id !== null && is_multisite()) {
            switch_to_blog($site_id);
        }

        foreach ($hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }

        if ($site_id !== null && is_multisite()) {
            restore_current_blog();
        }
    }

    /**
     * Активация плагина, в том числе на сети сайтов.
     *
     * register_activation_hook передаёт $network_wide: при активации на уровне
     * сети таблицы и опции нужны КАЖДОМУ сайту, иначе первый же запрос к
     * wp_2_bfw_* упадёт с «таблица не существует».
     *
     * @param bool $network_wide
     * @return void
     * @version 8.5.0
     */
    public static function bfwActivatePlugin(bool $network_wide = false): void
    {
        if (!is_multisite() || !$network_wide) {
            self::bfwActivate();
            return;
        }

        $site_ids = get_sites(array(
            'fields'                => 'ids',
            'number'                => 0,
            'update_site_cache'     => false,
            'update_site_meta_cache' => false,
        ));

        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);
            self::bfwActivate();
            restore_current_blog();
        }
    }

    /**
     * Деактивация плагина, в том числе на сети сайтов.
     *
     * @param bool $network_wide
     * @return void
     * @version 8.5.0
     */
    public static function bfwDeactivatePlugin(bool $network_wide = false): void
    {
        if (!is_multisite() || !$network_wide) {
            self::bfwDeactivate();
            return;
        }

        $site_ids = get_sites(array(
            'fields'            => 'ids',
            'number'            => 0,
            'update_site_cache' => false,
        ));

        foreach ($site_ids as $site_id) {
            self::unscheduleCronEvents((int) $site_id);
        }

        delete_network_option(null, 'bfw_network_activated');
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
        if (empty($options['action'])
            || $options['action'] !== 'update'
            || empty($options['type'])
            || $options['type'] !== 'plugin'
            || empty($options['plugins'])) {
            return;
        }

        $our_plugin = 'bonus-for-woo/index.php';

        foreach ($options['plugins'] as $plugin) {
            if ($plugin === $our_plugin) {
                set_transient('bfw_pro_updated', 1);
                break; // Прерываем цикл после нахождения плагина
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

        $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart') ??  __('Bonus points', 'bonus-for-woo'));
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
     * Copyright computy.ru
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
                        title="<?php echo __('About the bonus for woo plugin.', 'bonus-for-woo'); ?>"> Bonus for woo.</a></div>
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
        /*
         * Ключ передаётся на сервер лицензий, который возвращает ПОДПИСАННЫЙ
         * токен. Плагин проверяет подпись вшитым публичным ключом и только
         * затем сохраняет токен. Ответ сервера без валидной подписи
         * игнорируется — подделать его нельзя без приватного ключа.
         */
        $result = BfwLicense::activate($key);

        if ($result['ok']) {
            wp_safe_redirect(admin_url('admin.php?page=bonus_for_woo-plugin-options&bfw_license=ok'));
            exit;
        }

        $messages = array(
            'empty_key'                 => __('Enter your activation key.', 'bonus-for-woo'),
            'invalid_key'               => __('This key is not valid for this site. Check that the key and the domain match.', 'bonus-for-woo'),
            'wrong_domain'              => __('This key was issued for a different site.', 'bonus-for-woo'),
            'limit_reached'             => __('The activation limit for this key has been reached.', 'bonus-for-woo'),
            'unavailable'               => __('The license server is temporarily unavailable. Please try again later.', 'bonus-for-woo'),
            'network'                   => __('Could not reach the license server. Check your connection and try again.', 'bonus-for-woo'),
            'unexpected_response'       => __('The license server returned an unexpected response. Please try again later.', 'bonus-for-woo'),
            'bad_token_bad_signature'   => __('The license response could not be verified. Please contact support.', 'bonus-for-woo'),
            'bad_token_domain_mismatch' => __('This key was issued for a different site.', 'bonus-for-woo'),
            'bad_token_expired'         => __('This license has expired.', 'bonus-for-woo'),
        );

        $message = $messages[$result['reason']] ?? __('The key is not correct! Contact info@computy.ru', 'bonus-for-woo');

        printf(
            '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
            esc_html($message)
        );
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
     * @version 8.3.0
     */
    public static function updatePageIfChangePaymentMethod(): void
    {
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
            return;
        }
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
        return '<span class="bfw-help-tip ' . esc_attr($event) . '" data-tip="' . esc_attr($text) . '"></span>';
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
    public static function get_customer_order_count(int $user_id, array $order_status = null, $exclude_order_id = null): int
    {
        if (!$user_id) {
            return 0;
        }

        if (!$order_status) {
            $order_status = array('completed', 'processing');
        }
        $args = array(
                'customer_id' => $user_id,
                'status' => $order_status,
                'limit' => -1, // Получаем все заказы
                'return' => 'ids',
        );

        if ($exclude_order_id) {
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
            // Justified: only excluding one order, query already filtered by customer_id
            $args['exclude'] = [$exclude_order_id];
        }

        return count(wc_get_orders($args));
    }
}
