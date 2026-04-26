<?php

defined('ABSPATH') || exit;

/**
 * Class BfwShortcodes
 * Handles all shortcodes for the bonus system
 *
 * @version 8.0.1
 * @since 8.0.1
 */
class BfwShortcodes
{
    /**
     * Initialize all shortcodes
     *
     * @return void
     */
    public static function init()
    {
        self::initAccountShortcodes();
        self::initPointsShortcodes();
        self::initCashbackShortcodes();
        self::initReferralShortcodes();
        self::initHistoryShortcodes();
    }

    /**
     * Account related shortcodes
     */
    private static function initAccountShortcodes()
    {
        /*-------Шорткод: вывод статуса клиента-------*/
        add_shortcode('bfw_status', array('BfwAccount', 'getStatus'));

        /*-------Шорткод: вывод процента кешбэка-------*/
        add_shortcode('bfw_cashback', array('BfwAccount', 'getCashback'));

        /*-------Шорткод: вывод количества баллов-------*/
        add_shortcode('bfw_points', array('BfwAccount', 'getPoints'));

        /*-------Шорткод: вывод реферальной системы-------*/
        add_shortcode('bfw_account_referral', array('BfwAccount', 'accountReferral'));

        /*-------Шорткод: Вывод реферальной ссылки клиента-------*/
        add_shortcode('bfw_ref', array('BfwAccount', 'getReferralLink'));

        /*-------Шорткод: вывод аккаунта пользователя-------*/
        add_shortcode('bfw_account', array('BfwAccount', 'accountContentShortcode'));

        /*-------Шорткод: вывод карточки пользователя-------*/
        add_shortcode('bfw_cart_user', array('BfwAccount', 'accountBasicInfo'));

        /*Вывод ссылки на условия бонусной системы*/
        add_shortcode('link_on_rulles', array('BfwAccount', 'accountRules'));

        add_shortcode('bfw_coupon_form', array('BfwAccount', 'accountCoupon'));
    }

    /**
     * Points related shortcodes
     */
    private static function initPointsShortcodes()
    {
        /**-----Шорткод: вывод суммы заказов клиента--**/
        add_shortcode('bfw_get_sum_orders', array('BfwPoints', 'getSumUserOrders'));

        /*шорткод списания баллов в корзине*/
        add_shortcode('bfw-write-off-bonuses', array('BfwPoints', 'bfwoo_spisaniebonusov_in_cart_shortcode'));

        /*шорткод списания баллов в оформлении заказа*/
        add_shortcode(
            'bfw-write-off-bonuses-checkout',
            array('BfwPoints', 'bfwoo_spisaniebonusov_in_checkout_shortcode')
        );
    }

    /**
     * Cashback related shortcodes
     */
    private static function initCashbackShortcodes()
    {
        /*Шорт код для вставки кешбэка только на странице продукта*/
        add_shortcode('bfw_cashback_in_product', array('BfwSingleProduct', 'ballsAfterProductPriceShortcode'), 100, 2);

        /*-------Шорткод: Вывод количества возможного кешбэка в корзине-------*/
        add_shortcode('bfw_how_much_cashback', array('BfwCashback', 'bfwGetCashbackInCartForShortcode'));
    }

    /**
     * Referral related shortcodes
     */
    private static function initReferralShortcodes()
    {
        // Referral shortcodes are handled in initAccountShortcodes()
    }

    /**
     * History related shortcodes
     */
    private static function initHistoryShortcodes()
    {
        /*-------Шорткод: вывод истории баллов-------*/
        add_shortcode('bfw_history_points', array('BfwHistory', 'getHistory'));
    }
}
