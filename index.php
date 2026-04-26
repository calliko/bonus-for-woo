<?php
/**
 * Bonus for Woo - WooCommerce Cashback and Bonus System
 *
 * @wordpress-plugin
 * Plugin Name:       Bonus for Woo
 * Version:           8.1.0
 * Plugin URI:        https://computy.ru/blog/bonus-for-woo-wordpress
 * Description:       A comprehensive cashback bonus system for WooCommerce with user status management.
 * Author:            computy
 * Author URI:        https://computy.ru
 * Text Domain:       bonus-for-woo
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   10.7.0
 * Requires Plugins:  woocommerce
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */


defined('ABSPATH') || exit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;


// Define plugin constants.
const BONUS_COMPUTY_VERSION = '8.1.0';
const BONUS_COMPUTY_VERSION_DB = '6';
define('BONUS_COMPUTY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BONUS_COMPUTY_PLUGIN_URL', plugin_dir_url(__FILE__));


/**
 * Автозагрузка классов
 */

spl_autoload_register(static function ($class) {
    $file = str_replace('\\', '/', BONUS_COMPUTY_PLUGIN_DIR . '/classes/' . $class)
        . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
//$val = BfwSetting::get_all();

/*Поддержка новой системы заказов. Не убирать отсюда!*/
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/*Подключаем все экшены, фильтры, шорткоды*/
add_action('init', array('BfwRouter', 'init'));

/*-------Страница админки*-------*/
add_action('init', array('BfwAdmin', 'init'));


/*-------Действия после обновления-------*/
if (get_transient('bfw_pro_updated')) {
    /*Проверка бд после обновления */
    BfwAdmin::bfw_search_pro();
    BfwDB::checkDb();
    delete_transient('bfw_pro_updated');
}
/*-------Действия после обновления-------*/


/*-------Функция, которая запускается при активации плагина-------*/
register_activation_hook(__FILE__, array('BfwFunctions', 'bfwActivate'));

/*------Функция, которая запускается при деактивации плагина------*/
register_deactivation_hook(__FILE__, array('BfwFunctions', 'bfwDeactivate'));


/**
 * Translations
 * Переводы. Не удалять отсюда!!!!
 *
 * @return void
 * @version 6.6.6
 */
add_action('plugins_loaded', 'bfw_load_textdomain');
function bfw_load_textdomain()
{
    load_plugin_textdomain('bonus-for-woo', false, dirname(plugin_basename(__FILE__)) . '/lang/');
}

/*-------Регистрация REST API маршрутов (отдельно от init для корректной работы)-------*/
add_action('rest_api_init', function () {
    register_rest_route('bfw/v1', '/clear-fast-bonus', [
        'methods' => 'POST',
        'callback' => ['BfwPoints', 'bfwoo_clean_fast_bonus_rest'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('bfw/v1', '/apply-points', [
        'methods' => 'POST',
        'callback' => ['BfwPoints', 'rest_trata_points'],
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    register_rest_route('bfw/v1', '/get-spisanie-html', [
        'methods' => 'POST',
        'callback' => ['BfwPoints', 'rest_get_spisanie_html'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('bfw/v1', '/get-cashback-html', [
        'methods' => 'POST',
        'callback' => ['BfwCashback', 'rest_get_cashback_html'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('bfw/v1', '/activate-coupon', [
        'methods' => 'POST',
        'callback' => ['BfwPoints', 'rest_activate_coupon'],
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});
