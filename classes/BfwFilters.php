<?php

defined('ABSPATH') || exit;

/**
 * Class BfwFilters
 * Handles all WordPress filters for the bonus system
 *
 * @version 8.0.1
 * @since 8.0.1
 */
class BfwFilters
{
    /**
     * Initialize all filters
     *
     * @return void
     */
    public static function init()
    {
        self::initAccountFilters();
        self::initCartFilters();
        self::initCouponFilters();
        self::initAdminFilters();
        self::initProductFilters();
    }

    /**
     * Account related filters
     */
    private static function initAccountFilters()
    {
        /*-------Создаем ссылку в меню woocommerce account бонусная система-------*/
        add_filter('woocommerce_account_menu_items', array('BfwAccount', 'bonusesLink'), 25);

        add_filter('woocommerce_get_query_vars', function ($vars) {
            $vars['bonuses'] = 'bonuses';
            return $vars;
        });
    }

    /**
     * Cart related filters
     */
    private static function initCartFilters()
    {
        /*Кнопка удаления в подытоге(комиссии) */
        add_filter('woocommerce_cart_totals_fee_html', array('BfwPoints', 'bfw_button_delete_fast_point'), 10, 2);
    }

    /**
     * Coupon related filters
     */
    private static function initCouponFilters()
    {
        /*Создаем виртуальный купон*/
        add_filter('woocommerce_get_shop_coupon_data', array('BfwPoints', 'get_virtual_coupon_data_bfw'), 10, 3);
        
        /*Вид купонов в корзине*/
        add_filter('woocommerce_cart_totals_coupon_html', array('BfwPoints', 'bfw_coupon_html'), 99, 2);
        
        /* Убираем слово "купон" в корзине*/
        add_filter(
            'woocommerce_cart_totals_coupon_label',
            array('BfwPoints', 'woocommerceChangeCouponLabelBfw'),
            10,
            2
        );

        /*Выводим свое уведомление при добавлении баллов*/
        add_filter('woocommerce_coupon_message', function ($msg, $msg_code, $coupon) {

            if (!BfwSetting::get('bonus-points-on-cart')) {
                return $msg;
            }

            $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));

            // Проверяем: 1) что это объект купона, 2) что это нужный купон, 3) что код сообщения указывает на успешное применение
            if (
                $coupon instanceof WC_Coupon &&
                mb_strtolower($coupon->get_code()) === $cart_discount &&
                $msg_code === WC_Coupon::WC_COUPON_SUCCESS
            ) {

                /* translators: "applied" in the plural. */
                return BfwSetting::get('bonus-points-on-cart') . ' ' . __('applied', 'bonus-for-woo') . '.';
            }

            return $msg;
        }, 10, 3);

        add_filter('woocommerce_coupon_error', function ($err, $err_code, $coupon) {
            $bonus_coupon_code = mb_strtolower(trim(BfwSetting::get('bonus-points-on-cart')));
            if (strtolower($coupon->get_code()) === $bonus_coupon_code) {
                return false; // Подавляем ошибку для бонусного купона
            }
            return $err;
        }, 10, 3);

        /*Notices*/
        //Сработает когда бонусные купоны используют в поле woocommerce купонов
        add_filter('woocommerce_coupon_error', array('BfwCoupons', 'usingWrongCoupon'), 10, 3);
    }

    /**
     * Admin related filters
     */
    private static function initAdminFilters()
    {
        add_filter('plugin_action_links', array('BfwAdmin', 'add_settings_link'), 10, 2);
    }

    /**
     * Product related filters
     */
    private static function initProductFilters()
    {
        /*-------Текст на странице товара (сколько бонусов получите)-------*/
        add_filter('woocommerce_get_price_html', array('BfwSingleProduct', 'ballsAfterProductPriceAll'), 100, 2);
    }
}
