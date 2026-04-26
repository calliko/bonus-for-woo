<?php

defined('ABSPATH') || exit;

/**
 * Class BfwHooks
 * Handles all WooCommerce hooks for the bonus system
 *
 * @version 8.0.1
 * @since 8.0.1
 */
class BfwHooks
{
    /**
     * Initialize all hooks
     *
     * @return void
     */
    public static function init()
    {
        self::initAccountHooks();
        self::initOrderHooks();
        self::initCartHooks();
        self::initAdminHooks();
        self::initSystemHooks();
    }

    /**
     * Account related hooks
     */
    private static function initAccountHooks()
    {
        /*-------Действия после обновления-------*/
        add_action('upgrader_process_complete', array('BfwFunctions', 'bfwUpdateCompleted'), 10, 2);

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

        /*-------Вывод информации о кешбэке на странице просмотра заказа в личном кабинете-------*/
        add_action('woocommerce_view_order', array('BfwAccount', 'display_cashback_info_in_order_view'), 20);
    }

    /**
     * Order related hooks
     */
    private static function initOrderHooks()
    {
        /*------------Действие когда клиент подтверждает заказ - списание баллов------------*/
        $order_status_write = BfwSetting::get('write_points_order_status', 'processed');
        if ($order_status_write == 'processed') {
            $order_status_write_action = 'woocommerce_new_order';
        } else {
            $order_status_write_action = 'woocommerce_order_status_' . $order_status_write;
        }

        add_action($order_status_write_action, array('BfwPoints', 'newOrder'), 20, 1);

        /*------------Действие когда статус заказа выполнен - начисление баллов------------*/
        $order_status = BfwSetting::get('add_points_order_status', 'completed');
        $order_status = apply_filters('bfw_add_points_order_status_filter', $order_status);
        $statuses = (array) $order_status;
        
        foreach ($statuses as $status) {
            add_action('woocommerce_order_status_' . $status, array('BfwPoints', 'ifCompletedOrder'), 10, 1);
        }

        /*------------Очистка кэша суммы покупок при изменении заказа------------*/
        add_action('woocommerce_order_status_changed', array('BfwPoints', 'clearUserOrdersSumCache'), 10, 1);
        add_action('woocommerce_delete_order', array('BfwPoints', 'clearUserOrdersSumCache'), 10, 1);
        add_action('woocommerce_trash_order', array('BfwPoints', 'clearUserOrdersSumCache'), 10, 1);
        
        /*------------Очистка pending баллов при удалении/восстановлении заказа------------*/
        add_action('woocommerce_delete_order', array('BfwPoints', 'clearPendingPoints'), 10, 1);
        add_action('woocommerce_trash_order', array('BfwPoints', 'clearPendingPoints'), 10, 1);
        add_action('woocommerce_untrash_order', array('BfwPoints', 'clearPendingPoints'), 10, 1);
        
        add_action('woocommerce_refund_created', function($refund_id, $args) {
            if (!empty($args['order_id'])) {
                BfwPoints::clearUserOrdersSumCache($args['order_id']);
            }
        }, 10, 2);

        /*------------Pending-баллы: начисляем "ожидающие" при оформлении заказа------------*/
        add_action('woocommerce_order_status_processing', array('BfwPoints', 'setPendingPoints'), 5, 1);
        add_action('woocommerce_order_status_on-hold', array('BfwPoints', 'setPendingPoints'), 5, 1);
        
        // Очищаем pending при отмене/отказе/возврате
        add_action('woocommerce_order_status_cancelled', array('BfwPoints', 'clearPendingPoints'), 5, 1);
        add_action('woocommerce_order_status_failed', array('BfwPoints', 'clearPendingPoints'), 5, 1);
        add_action('woocommerce_order_status_refunded', array('BfwPoints', 'clearPendingPoints'), 5, 1);

        // Обработка возвратов (полных и частичных) пропорционально
        add_action('woocommerce_order_refunded', array('BfwPoints', 'refundedPoints'), 10, 2);

        // Ручное изменение статуса на "Возвращен" (или выбранный в настройках)
        $refund_statuses = BfwSetting::get('refunded_points_order_status', array('refunded'));
        if (is_array($refund_statuses)) {
            foreach ($refund_statuses as $status) {
                add_action('woocommerce_order_status_' . $status, array('BfwPoints', 'refundedPoints'), 10, 1);
            }
        } else {
            // fallback если вдруг вернулась строка
            add_action('woocommerce_order_status_' . $refund_statuses, array('BfwPoints', 'refundedPoints'), 10, 1);
        }

        //Если отзыв о товаре одобрен добавляет баллы
        add_action('comment_unapproved_to_approved', array('BfwReview', 'bfwoo_approve_comment_callback'));

        //Если отзыв о товаре отклонен удаляет баллы
        add_action('comment_approved_to_unapproved', array('BfwReview', 'bfwoo_unapproved_comment_callback'));

        /*-------Начисляем кешбэк-баллы в редакторе заказа-------*/
        add_action('wp_ajax_bfw_send_points_from_order', array('BfwPoints', 'handleSendPointsFromOrder'));

        add_action(
            'woocommerce_checkout_create_order_line_item',
            array('BfwFunctions', 'saveSaleStatusToOrderItemMeta'),
            10,
            4
        );
    }

    /**
     * Cart related hooks
     */
    private static function initCartHooks()
    {
        /*-------Вывод кешбэка в корзине и в оформлении товара-------*/
        if (BfwSetting::get('cashback-in-cart')) {
            add_action('woocommerce_review_order_after_order_total', array('BfwCashback', 'getCashbackInCart'));
            add_action('woocommerce_cart_totals_after_order_total', array('BfwCashback', 'getCashbackInCart'));
        }

        /*-------Списание баллов в корзине и оформлении заказа-------*/
        add_action('woocommerce_before_cart', array('BfwPoints', 'bfwoo_spisaniebonusov_in_cart'), 9);
        add_action('woocommerce_before_checkout_form', array('BfwPoints', 'bfwoo_spisaniebonusov_in_checkout'), 9);

        /*-------Добавляем скидку-------*/
        add_action('woocommerce_cart_calculate_fees', array('BfwPoints', 'bfwoo_add_fee'), 1, 1);
        
        /*-------Дополнительный хук для применения купона после расчета-------*/
        add_action('woocommerce_after_calculate_totals', array('BfwPoints', 'ensure_coupon_applied'), 10, 1);

        /*-------Удаление временных баллов при очистке корзины-------*/
        add_action(
            'woocommerce_remove_cart_item',
            array('BfwPoints', 'actionWoocommerceBeforeCartItemQuantityZero'),
            10,
            1
        );

        /*Действие сработает при изменении количества товаров*/
        add_action('woocommerce_cart_item_set_quantity', array('BfwPoints', 'bfwCartItemSetQuantity'), 10, 3);

        /*Действие при удалении купона баллов(woo blocks)*/
        add_action('woocommerce_removed_coupon', array('BfwCoupons', 'trueRedirectOnCouponRemoval'), 20);

        // Исключаем скидку из налогов
        add_action(
            'woocommerce_cart_totals_get_fees_from_cart_taxes',
            array('BfwPoints', 'excludeCartFeesTaxes'),
            10,
            3
        );
    }

    /**
     * Admin related hooks
     */
    private static function initAdminHooks()
    {
        /*-------Списание баллов в редакторе заказа-------*/
        add_action('wp_ajax_deduct_points', array('BfwPoints', 'handle_deduct_points_in_order'));

        /*-------Возврат баллов при удалении купона бонусных баллов в редакторе заказа-------*/
        add_action('wp_ajax_track_coupon_removal', array('BfwPoints', 'handle_track_coupon_removal'));
        
        /*-------Экспорт csv файла бонусов  */
        add_action('wp_ajax_bfw_export_bonuses', array('BfwPoints', 'bfw_export_bonuses'));

        /*-------Экспорт csv файла купонов  */
        add_action('wp_ajax_bfw_export_coupons', array('BfwCoupons', 'bfwExportCoupons'));

        /*-------Возможность менеджерам настраивать плагин-------*/
        add_filter('woocommerce_shop_manager_editable_roles', array('BfwRoles', 'bfwManagerRoleEditCapabilities'));

        /*Начисление кешбэка за прошлые заказы*/
        add_action('wp_ajax_cashback_prepare', array('BfwCashback', 'cashbackPrepare'));
        add_action('wp_ajax_cashback_recount', array('BfwCashback', 'cashbackRecount'));

        /*Массовое начисление баллов без уведомленний*/
        add_action('wp_ajax_computy_mass_add_points', array('BfwCashback', 'computyMassAddPoints'));

        /*Пересчет баллов у всех пользователей на основе истории*/
        add_action('wp_ajax_computy_recalculation_points', array('BfwPoints', 'computyRecalculationPoints'));
    }

    /**
     * System related hooks
     */
    private static function initSystemHooks()
    {
        /*-------Очищение истории при удалении пользователя-------*/
        add_action('delete_user', array('BfwHistory', 'bfw_when_delete_user'));

        /*-------Если есть исключенный метод оплаты-------*/
        if (BfwSetting::get('exclude-payment-method')) {
            /* Добавляет обновление страницы при выборе метода оплаты*/
            add_action('wp_footer', array('BfwFunctions', 'updatePageIfChangePaymentMethod'));
        }

        /*-------Реферальная система-------*/
        if (BfwSetting::get('referal-system')) {
            (new BfwReferral())->bfwSetCookies();
            add_action('user_register', array('BfwReferral', 'registerInvate'));
        }

        add_action('computy_copyright', array('BfwFunctions', 'computy_copyright'), 25);

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
    }
}
