<?php

defined('ABSPATH') || exit;

/**
 * Class BfwAjaxHandlers
 * Handles all AJAX handlers for the bonus system
 *
 * @version 8.0.1
 * @since 8.0.1
 */
class BfwAjaxHandlers
{
    /**
     * Initialize all AJAX handlers
     *
     * @return void
     */
    public static function init()
    {
        self::initPointsAjaxHandlers();
        self::initCashbackAjaxHandlers();
        self::initCouponAjaxHandlers();
        self::initAdminAjaxHandlers();
        self::initPublicAjaxHandlers();
    }

    /**
     * Points related AJAX handlers
     */
    private static function initPointsAjaxHandlers()
    {
        /*-------AJAX обработка списания баллов-------*/
        add_action('wp_ajax_computy_trata_points', array('BfwPoints', 'bfwoo_trata_points'));
        add_action('wp_ajax_nopriv_computy_trata_points', array('BfwPoints', 'bfwoo_trata_points'));
        
        /*-------AJAX очистка быстрых баллов-------*/
        add_action('wp_ajax_clear_bonus', array('BfwPoints', 'bfwoo_clean_fast_bonus'));
        add_action('wp_ajax_nopriv_clear_bonus', array('BfwPoints', 'bfwoo_clean_fast_bonus'));

        /*-------Списание баллов в редакторе заказа-------*/
        add_action('wp_ajax_deduct_points', array('BfwPoints', 'handle_deduct_points_in_order'));

        /*-------Возврат баллов при удалении купона бонусных баллов в редакторе заказа-------*/
        add_action('wp_ajax_track_coupon_removal', array('BfwPoints', 'handle_track_coupon_removal'));
        
        /*-------Экспорт csv файла бонусов  */
        add_action('wp_ajax_bfw_export_bonuses', array('BfwPoints', 'bfw_export_bonuses'));

        /*-------Начисляем кешбэк-баллы в редакторе заказа-------*/
        add_action('wp_ajax_bfw_send_points_from_order', array('BfwPoints', 'handleSendPointsFromOrder'));

        /*Пересчет баллов у всех пользователей на основе истории*/
        add_action('wp_ajax_computy_recalculation_points', array('BfwPoints', 'computyRecalculationPoints'));
    }

    /**
     * Cashback related AJAX handlers
     */
    private static function initCashbackAjaxHandlers()
    {
        /*Начисление кешбэка за прошлые заказы*/
        add_action('wp_ajax_cashback_prepare', array('BfwCashback', 'cashbackPrepare'));
        add_action('wp_ajax_cashback_recount', array('BfwCashback', 'cashbackRecount'));

        /*Массовое начисление баллов без уведомленний*/
        add_action('wp_ajax_computy_mass_add_points', array('BfwCashback', 'computyMassAddPoints'));
    }

    /**
     * Coupon related AJAX handlers
     */
    private static function initCouponAjaxHandlers()
    {
        /*-------Экспорт csv файла купонов  */
        add_action('wp_ajax_bfw_export_coupons', array('BfwCoupons', 'bfwExportCoupons'));
    }

    /**
     * Admin related AJAX handlers
     */
    private static function initAdminAjaxHandlers()
    {
        /*Статистика и аналитика*/
        add_action('wp_ajax_bfw_get_stats_timestamp', ['BfwStatistic', 'ajax_get_stats_timestamp']);
    }

    /**
     * Public AJAX handlers (both logged in and not logged in users)
     */
    private static function initPublicAjaxHandlers()
    {
        // These handlers are already registered in other methods
        // but listed here for completeness
    }
}
