<?php
/**
 * Bonus for Woo - WooCommerce Cashback and Bonus System
 *
 * @wordpress-plugin
 * Plugin Name:       Bonus for Woo
 * Version:           8.5.1
 * Plugin URI:        https://computy.ru/blog/bonus-for-woo-wordpress
 * Description:       A comprehensive cashback bonus system for WooCommerce with user status management.
 * Author:            computy
 * Author URI:        https://computy.ru
 * Text Domain:       bonus-for-woo
 * Domain Path:       /lang
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   11.0.1
 * Requires Plugins:  woocommerce
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */


defined('ABSPATH') || exit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;


// Define plugin constants.
const BONUS_COMPUTY_VERSION = '8.5.1';
const BONUS_COMPUTY_VERSION_DB = '8';
define('BONUS_COMPUTY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BONUS_COMPUTY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BFW_CSV_IMPORT_LIMIT', 100); // сколько строк обрабатывать в каждом пакете
define('BFW_HISTORY_LIMIT', 500); //Лимит в истории начислений
define('BFW_COUNT_STEPS_FOR_REQUESTS', 20);//Количество обработок за 1 запрос
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

/*
 * Плагин без WooCommerce работать не может: все расчёты завязаны на WC_Order,
 * WC_Coupon и хуки woocommerce_*. Заголовок «Requires Plugins» срабатывает
 * только на экране активации (WP 6.5+, установка из каталога), поэтому
 * отключение WooCommerce позже приводило к фатальной ошибке вместо
 * понятного уведомления. Деградируем явно.
 */
add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        return;
    }

    add_action('admin_notices', function () {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>'
            . esc_html__('Bonus for Woo requires WooCommerce to be installed and active. The plugin is paused until WooCommerce is back.', 'bonus-for-woo')
            . '</p></div>';
    });
}, 5, 0);

/**
 * Возвращает true, если зависимости на месте.
 */
if (!function_exists('bfw_dependencies_met')) {
    function bfw_dependencies_met(): bool
    {
        return class_exists('WooCommerce');
    }
}

/*Подключаем все экшены, фильтры, шорткоды*/
add_action('init', function () {
    if (!bfw_dependencies_met()) {
        return;
    }
    BfwRouter::init();
});

/*-------Страница админки*-------*/
add_action('init', function () {
    if (!bfw_dependencies_met()) {
        return;
    }
    BfwAdmin::init();
});


/*-------Действия после обновления-------*/
if (get_transient('bfw_pro_updated')) {
    /*Проверка бд после обновления */
    BfwAdmin::bfw_search_pro();
    BfwDB::checkDb();
    delete_transient('bfw_pro_updated');
}
/*-------Действия после обновления-------*/


/*-------Функция, которая запускается при активации плагина-------*/
register_activation_hook(__FILE__, array('BfwFunctions', 'bfwActivatePlugin'));

/*------Функция, которая запускается при деактивации плагина------*/
register_deactivation_hook(__FILE__, array('BfwFunctions', 'bfwDeactivatePlugin'));


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
        'permission_callback' => function () {
            return is_user_logged_in();
        },
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
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    register_rest_route('bfw/v1', '/get-cashback-html', [
        'methods' => 'POST',
        'callback' => ['BfwCashback', 'rest_get_cashback_html'],
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    register_rest_route('bfw/v1', '/activate-coupon', [
        'methods' => 'POST',
        'callback' => ['BfwPoints', 'rest_activate_coupon'],
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    register_rest_route('bfw/v1', '/check-points-only-products', [
        'methods' => 'POST',
        'callback' => ['BfwPoints', 'rest_check_points_only_products'],
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});



