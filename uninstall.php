<?php
/**
 * Действия во время удаления плагина
 *
 * @version 5.1.0
 * @since 4.4.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}


if (BfwSetting::get('clear-bfw-bd')) {
    //drop tables
    global $wpdb;

    $table_name1 = $wpdb->prefix . 'bfw_computy';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name1}");

    $table_name2 = $wpdb->prefix . 'bfw_history_computy';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name2}");

    $table_name3 = $wpdb->prefix . 'bfw_logs_computy';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name3}");

    $table_name4 = $wpdb->prefix . 'bfw_coupons_computy';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name4}");

    //delete user_meta
    $users = get_users();
    foreach ($users as $user) {
        delete_user_meta($user->ID, 'computy_point');
        delete_user_meta($user->ID, 'computy_fast_point');
        delete_user_meta($user->ID, 'bfw_points_referral');
        delete_user_meta($user->ID, 'bfw_points_referral_invite');
    }

    //delete options
    $options = array(
        'bonus_option_name',
        'bonus-for-woo-pro',
        'bonus-for-woo-offline-product'
    );
    foreach ($options as $option) {
        if (get_option($option)) {
            delete_option($option);
        }
    }
}
