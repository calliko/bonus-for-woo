<?php

defined('ABSPATH') || exit;

/**
 * Класс подключения actions, filters, shortcodes
 *
 * @version 5.10.0
 * @since 5.10.0
 */
class BfwRouter
{

    public static function init()
    {

        /*Статистика*/
        BfwStatistic::init();
        add_action('wp_ajax_bfw_get_stats_timestamp', ['BfwStatistic', 'ajax_get_stats_timestamp']);


        /*
         * Действие при нажатии кнопки экспорта баллов
        * @since 4.4.0
        * @version 4.4.0
        */

        if (isset($_GET['export_bfw_points']) && current_user_can('manage_options')) {
            $file_path = BONUS_COMPUTY_PLUGIN_DIR . '/export_bfw.csv';
            $buffer = fopen($file_path, 'w');

            // Add BOM (Byte Order Mark) to ensure proper UTF-8 encoding
            fwrite($buffer, "\xEF\xBB\xBF");

            global $wpdb;
            $users = $wpdb->get_results("SELECT * FROM {$wpdb->users} ORDER BY ID");
            $title_export = ['User id', 'User name', 'Email', 'Phone', 'Points', 'Status', 'Comment'];
            fputcsv($buffer, $title_export, ',');
            $status = new BfwRoles();

            $data = [];
            foreach ($users as $user) {
                $points = get_user_meta($user->ID, 'computy_point', true) ?? 0;
                $arrayRole = $status->getRole($user->ID);
                $data[] = [
                    'id' => $user->ID,
                    'name' => $user->user_nicename,
                    'email' => $user->user_email,
                    'phone' => get_user_meta($user->ID, 'billing_phone', true) ?? '',
                    'points' => $points,
                    'status' => $arrayRole['name'],
                    'comment' => '',
                ];
            }

            foreach ($data as $row) {
                fputcsv($buffer, $row, ',');
            }

            fclose($buffer);
        }

        if (isset($_GET['remove_export_bfw_points']) && current_user_can('manage_options')) {
            $file_path = BONUS_COMPUTY_PLUGIN_DIR . '/export_bfw.csv';
            wp_delete_file($file_path);
        }
        /*-------Действие при нажатии кнопки экспорта баллов-------*/


        if (current_user_can('manage_options')) {
            /* Сохранение изменений в профиле клиента*/
            add_action('personal_options_update', array('BfwAccount', 'profileUserUpdate'));
            add_action('edit_user_profile_update', array('BfwAccount', 'profileUserUpdate'));
        }


        /*-------Действия после обновления-------*/
        add_action('upgrader_process_complete', array('BfwFunctions', 'bfwUpdateCompleted'), 10, 2);

        // Исключаем скидку из налогов
        add_action('woocommerce_cart_totals_get_fees_from_cart_taxes', array('BfwPoints', 'excludeCartFeesTaxes'), 10,
            3);


        /** Accounts */


        /*-------Добавляем конечную точку bonuses-------*/
        (new BfwAccount())->bonusesAddEndpoint();

        /*-------Поле дня рождения-------*/
        add_action('woocommerce_edit_account_form_start', array('BfwAccount', 'bfwDobAccountDetails'));
        add_action('woocommerce_save_account_details', array('BfwAccount', 'bfwDobSaveAccountDetails'));

        /*-------Вывод текста над формой регистрации-------*/
        add_action('woocommerce_register_form_start', array('BfwAccount', 'formRegister'), 2, 1);
        /*-------Вывод текста над заголовком оставить отзыв-------*/
        add_action('comment_form_before', array('BfwReview', 'liveReviewAndPoint'));

        /*-------Действия при авторизации пользователя-------*/
        add_action('wp_login', array('BfwAccount', 'addBallWhenUserLogin'), 10, 2);

        /** Заголовок страницы бонусов в аккаунте **/
        add_action('bfw_account_title', array('BfwAccount', 'accountTitle'));

        /** Вывод основной информации: статус, процент кешбэка, количество бонусных баллов **/
        add_action('bfw_account_basic_info', array('BfwAccount', 'accountBasicInfo'));

        if (BfwRoles::isPro()) {
            /** Ввод купонов(только для PRO-версии) **/
            //  add_action('bfw_account_coupon', array('BfwAccount', 'accountCoupon'));

            /** Реферальная система (только для PRO-версии) **/
            add_filter('bfw_account_referal', array('BfwAccount', 'accountReferral'));

            /*------- Действия при регистрации клиента -------*/
            add_action('user_register', array('BfwAccount', 'actionPointsForRegistrationBfw'));


        }

        /** Прогресс бар **/
        add_action('bfw_account_progress', array('BfwAccount', 'accountProgress'));

        /** История начисления баллов **/
        add_action('bfw_account_history', array('BfwAccount', 'accountHistory'));

        /*Вывод ссылки на условия бонусной системы*/
        add_action('bfw_account_rulles', array('BfwAccount', 'accountRules'));

        /*-------Добавляем контент на страницу бонусов-------*/
        add_action('woocommerce_account_bonuses_endpoint', array('BfwAccount', 'accountContent'), 25);

        /*-------Вывод кешбэка в корзине и в оформлении товара-------*/
        if (BfwSetting::get('cashback-in-cart')) {
            add_action('woocommerce_review_order_after_order_total', array('BfwCashback', 'getCashbackInCart'));
            add_action('woocommerce_cart_totals_after_order_total', array('BfwCashback', 'getCashbackInCart'));
        }

        /*-------Списание баллов в корзине и оформлении заказа-------*/
        add_action('woocommerce_before_cart', array('BfwPoints', 'bfwoo_spisaniebonusov_in_cart'), 9);
        add_action('woocommerce_before_checkout_form', array('BfwPoints', 'bfwoo_spisaniebonusov_in_checkout'), 9);


        /*-------Списание баллов в редакторе заказа-------*/
        add_action('wp_ajax_deduct_points', array('BfwPoints', 'handle_deduct_points_in_order'));

        /*-------Возврат баллов при удалении купона бонусных баллов в редакторе заказа-------*/
        add_action('wp_ajax_track_coupon_removal', array('BfwPoints', 'handle_track_coupon_removal'));
        /*-------Экспорт csv файла бонусов  */
        add_action('wp_ajax_bfw_export_bonuses', array('BfwPoints', 'bfw_export_bonuses'));
        add_action('wp_ajax_nopriv_bfw_export_bonuses', array('BfwPoints', 'bfw_export_bonuses'));

        /*-------Экспорт csv файла купонов  */
        add_action('wp_ajax_bfw_export_coupons', array('BfwCoupons', 'bfwExportCoupons'));
        add_action('wp_ajax_nopriv_bfw_export_coupons', array('BfwCoupons', 'bfwExportCoupons'));

        /*-------Добавляем скидку-------*/
        add_action('woocommerce_cart_calculate_fees', array('BfwPoints', 'bfwoo_add_fee'), 10, 1);

        /*-------Начисляем кешбэк-баллы в редакторе заказа-------*/
        add_action('wp_ajax_bfw_send_points_from_order', array('BfwPoints', 'handleSendPointsFromOrder'));


        /*-------Очищение истории при удалении пользователя-------*/
        add_action('delete_user', array('BfwHistory', 'bfw_when_delete_user'));

        /*-------Удаление временных баллов при очистке корзины-------*/
        add_action('woocommerce_remove_cart_item', array('BfwPoints', 'actionWoocommerceBeforeCartItemQuantityZero'),
            10, 1);

        /*Действие сработает при изменении количества товаров*/
        add_action('woocommerce_cart_item_set_quantity', array('BfwPoints', 'bfwCartItemSetQuantity'), 10, 3);

        /*Действие при удалении купона баллов(woo blocks)*/
        add_action('woocommerce_removed_coupon', array('BfwCoupons', 'trueRedirectOnCouponRemoval'), 20);


        /*-------Если есть исключенный метод оплаты-------*/
        if (BfwSetting::get('exclude-payment-method')) {
            /* Добавляет обновление страницы при выборе метода оплаты*/
            add_action('wp_footer', array('BfwFunctions', 'updatePageIfChangePaymentMethod'));
        }

        add_action('woocommerce_checkout_create_order_line_item',
            array('BfwFunctions', 'saveSaleStatusToOrderItemMeta'), 10, 4);

        /*-------Реферальная система-------*/
        if (BfwSetting::get('referal-system')) {
            (new BfwReferral())->bfwSetCookies();
            add_action('user_register', array('BfwReferral', 'registerInvate'));
        }

        add_action('computy_copyright', array('BfwFunctions', 'computy_copyright'), 25);

        /*------------Действие когда клиент подтверждает заказ - списание баллов------------*/
        $order_status_write = BfwSetting::get('write_points_order_status', 'processed');
        if ($order_status_write == 'processed') {
            $order_status_write_action = 'woocommerce_checkout_order_processed';
        } else {
            $order_status_write_action = 'woocommerce_order_status_' . $order_status_write;
        }
        add_action($order_status_write_action, array('BfwPoints', 'newOrder'), 20, 1);

        /*------------Действие когда статус заказа выполнен - начисление баллов------------*/
        $order_status = BfwSetting::get('add_points_order_status', 'completed');

        //Когда необходимо учитывать несколько значений.
        $order_status = apply_filters('bfw_add_points_order_status_filter', $order_status);

        $statuses = (array)$order_status;
        foreach ($statuses as $status) {
            add_action('woocommerce_order_status_' . $status, array('BfwPoints', 'ifCompletedOrder'), 10, 1);
        }


        /*------------Действие когда оформлен возврат баллов-----------*/
        $order_status_refunded = BfwSetting::get('refunded_points_order_status', 'refunded');
        add_action('woocommerce_order_status_' . $order_status_refunded, array('BfwPoints', 'refundedPoints'), 10, 1);
        ///add_action( 'woocommerce_order_status_changed' , array('BfwPoints', 'test'), 10, 1 );
        //Если отзыв о товаре одобрен добавляет баллы
        add_action('comment_unapproved_to_approved', array('BfwReview', 'bfwoo_approve_comment_callback'));

        //Если отзыв о товаре отклонен удаляет баллы
        add_action('comment_approved_to_unapproved', array('BfwReview', 'bfwoo_unapproved_comment_callback'));

        /*Cron Удаление баллов за бездействие. Находим старых клиентов*/
        add_action('bfw_clear_old_cashback', array('BfwPoints', 'deleteBallsOldClients'), 10, 3);

        /*cron Начисление баллов в день рождение*/
        add_action('bfw_search_birthday', array('BfwPoints', 'addBallsForBirthday'), 10, 3);

        /*cron ищем заказы на которые можно начислить кешбэк*/
        add_action('bfw_daily_cashback_check', array('BfwPoints', 'searchCashbackCheck'), 10, 3);


        /*-------Добавляем стили на фронте-------*/
        add_action('wp_enqueue_scripts', array('BfwFunctions', 'bfwooComputyStyles'));

        /*-------Добавляем скрипты на фронте-------*/
        add_action('wp_enqueue_scripts', array('BfwFunctions', 'bfwooComputyScript'));


        // !!! Очищаем ссылку на пригласителя у приглашённых при удалении пользователя
        add_action('delete_user', array('BfwReferral', 'bfw_clear_referral_invites_on_delete'), 10, 1);
        // Для мультисайта (удаление через сеть)
        add_action('wpmu_delete_user', array('BfwReferral', 'bfw_clear_referral_invites_on_delete'), 10, 1);


        /**
         * FILTERS
         */

        add_filter('plugin_action_links', array('BfwAdmin', 'add_settings_link'), 10, 2);

        /** Accounts */

        /*-------Создаем ссылку в меню woocommerce account бонусная система-------*/
        add_filter('woocommerce_account_menu_items', array('BfwAccount', 'bonusesLink'), 25);

        add_filter('woocommerce_get_query_vars', function ($vars) {
            $vars['bonuses'] = 'bonuses';
            return $vars;
        });

        /*Кнопка удаления в подытоге(комиссии) */
        add_filter('woocommerce_cart_totals_fee_html', array('BfwPoints', 'bfw_button_delete_fast_point'), 10, 2);


        /*Создаем виртуальный купон*/
        add_filter('woocommerce_get_shop_coupon_data', array('BfwPoints', 'get_virtual_coupon_data_bfw'), 10, 3);
        /*Вид купонов в корзине*/
        add_filter('woocommerce_cart_totals_coupon_html', array('BfwPoints', 'bfw_coupon_html'), 99, 2);
        /* Убираем слово "купон" в корзине*/
        add_filter('woocommerce_cart_totals_coupon_label', array('BfwPoints', 'woocommerceChangeCouponLabelBfw'), 10,
            2);

        /*Выводим свое уведомление при добавлении баллов*/
        add_filter('woocommerce_coupon_message', function ($msg, $msg_code, $coupon) {

            if (!BfwSetting::get('bonus-points-on-cart')) {
                return $msg;
            }

            $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));

            // Проверяем: 1) что это объект купона, 2) что это нужный купон, 3) что код сообщения указывает на успешное применение
            if ($coupon instanceof WC_Coupon &&
                mb_strtolower($coupon->get_code()) === $cart_discount &&
                $msg_code === WC_Coupon::WC_COUPON_SUCCESS) {

                /* translators: "applied" in the plural. */
                return BfwSetting::get('bonus-points-on-cart') . ' ' . __('applied', 'bonus-for-woo') . '.';
            }

            return $msg;
        }, 10, 3);

        /*Выводим свое уведомление при удалении баллов (не работает из-за перезагрузки страницы)*/
        add_action('woocommerce_removed_coupon', function ($coupon_code) {

            if (BfwSetting::get('bonus-points-on-cart')) {
                $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
                $removed_coupon = mb_strtolower($coupon_code);

                if ($removed_coupon === $cart_discount) {
                    // Опционально: проверяем, существует ли такой купон
                    $coupon = new WC_Coupon($coupon_code);
                    if ($coupon->get_id()) {
                        wc_clear_notices();
                        wc_add_notice(BfwSetting::get('bonus-points-on-cart') . ' ' . __('removed',
                                'bonus-for-woo') . '.', 'notice');
                    }
                }
            }
        }, 10, 1);

        /*Начисление кешбэка за прошлые заказы*/
        add_filter('wp_ajax_cashback_prepare', array('BfwCashback', 'cashbackPrepare'));
        add_filter('wp_ajax_cashback_recount', array('BfwCashback', 'cashbackRecount'));


        /*-------Возможность менеджерам настраивать плагин-------*/
        add_filter('woocommerce_shop_manager_editable_roles', array('BfwRoles', 'bfwManagerRoleEditCapabilities'));

        /*-------Текст на странице товара (сколько бонусов получите)-------*/
        add_filter('woocommerce_get_price_html', array('BfwSingleProduct', 'ballsAfterProductPriceAll'), 100, 2);


        /**
         * SHORTCODES
         */


        /**-----Шорткод: вывод суммы заказов клиента--**/
        add_shortcode('bfw_get_sum_orders', array('BfwPoints', 'getSumUserOrders'));

        /*-------Шорткод: вывод статуса клиента-------*/
        add_shortcode('bfw_status', array('BfwAccount', 'getStatus'));

        /*-------Шорткод: вывод процента кешбэка-------*/
        add_shortcode('bfw_cashback', array('BfwAccount', 'getCashback'));

        /*-------Шорткод: вывод количества баллов-------*/
        add_shortcode('bfw_points', array('BfwAccount', 'getPoints'));

        /*Шорт код для вставки кешбэка только на странице продукта*/
        add_shortcode('bfw_cashback_in_product', array('BfwSingleProduct', 'ballsAfterProductPriceShortcode'), 100, 2);

        /*-------Шорткод: Вывод количества возможного кешбэка в корзине-------*/
        add_shortcode('bfw_how_much_cashback', array('BfwCashback', 'bfwGetCashbackInCartForShortcode'));

        /*шорткод списания баллов в корзине*/
        add_shortcode('bfw-write-off-bonuses', array('BfwPoints', 'bfwoo_spisaniebonusov_in_cart_shortcode'));

        /*шорткод списания баллов в оформлении заказа*/
        add_shortcode('bfw-write-off-bonuses-checkout',
            array('BfwPoints', 'bfwoo_spisaniebonusov_in_checkout_shortcode'));

        /*Вывод ссылки на условия бонусной системы*/
        add_shortcode('link_on_rulles', array('BfwAccount', 'accountRules'));

        /*-------Шорткод: вывод реферальной системы-------*/
        add_shortcode('bfw_account_referral', array('BfwAccount', 'accountReferral'));

        /*-------Шорткод: Вывод реферальной ссылки клиента-------*/
        add_shortcode('bfw_ref', array('BfwAccount', 'getReferralLink'));

        /*-------Шорткод: вывод аккаунта пользователя-------*/
        add_shortcode('bfw_account', array('BfwAccount', 'accountContentShortcode'));

        /*-------Шорткод: вывод карточки пользователя-------*/
        add_shortcode('bfw_cart_user', array('BfwAccount', 'accountBasicInfo'));

        /*-------Шорткод: вывод истории баллов-------*/
        add_shortcode('bfw_history_points', array('BfwHistory', 'getHistory'));

        add_shortcode('bfw_coupon_form', array('BfwAccount', 'accountCoupon'));


        /*Notices*/
        //Сработает когда бонусные купоны используют в поле woocommerce купонов
        add_filter('woocommerce_coupon_error', array('BfwCoupons', 'usingWrongCoupon'), 10, 3);


        /* REST API*/
        add_action('rest_api_init', function () {
            //очищение баллов
            register_rest_route('bfw/v1', '/clear-fast-bonus', [
                'methods' => 'POST',
                'callback' => ['BfwPoints', 'bfwoo_clean_fast_bonus_rest'],
                'permission_callback' => '__return_true',
                // или свою логику проверки прав
                //Если действие доступно только авторизованным пользователям — замените '__return_true' на, например, function() { return is_user_logged_in(); }.
            ]);

            //Списание баллов
            register_rest_route('bfw/v1', '/apply-points', array(
                'methods' => 'POST',
                'callback' => array('BfwPoints', 'rest_trata_points'),
                'permission_callback' => function () {
                    return is_user_logged_in(); // Только для авторизованных
                },
            ));

            // Получение блока списания  для woo_blocks
            register_rest_route('bfw/v1', '/get-spisanie-html', [
                'methods' => 'POST',
                'callback' => ['BfwPoints', 'rest_get_spisanie_html'],
                'permission_callback' => '__return_true',
            ]);

            // Получение блока кешбэка для woo_blocks
            register_rest_route('bfw/v1', '/get-cashback-html', [
                'methods' => 'POST',
                'callback' => ['BfwCashback', 'rest_get_cashback_html'],
                'permission_callback' => '__return_true',
            ]);

            //ввод купона дляполучения баллов
            register_rest_route('bfw/v1', '/activate-coupon', [
                'methods' => 'POST',
                'callback' => ['BfwPoints', 'rest_activate_coupon'],
                'permission_callback' => function () {
                    return is_user_logged_in(); // Только для авторизованных
                },
            ]);
        });


        add_filter('woocommerce_coupon_error', function ($err, $err_code, $coupon) {
            $bonus_coupon_code = mb_strtolower(trim(BfwSetting::get('bonus-points-on-cart')));
            if (strtolower($coupon->get_code()) === $bonus_coupon_code) {
                return false; // Подавляем ошибку для бонусного купона
            }
            return $err;
        }, 10, 3);


    }

}
