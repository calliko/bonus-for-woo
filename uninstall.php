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

$val = get_option('bonus_option_name');

// Если админ не просил очищать БД — убираем только опции плагина.
if (empty($val['clear-bfw-bd'])) {
    delete_option('bonus_option_name');
    delete_option('bonus-for-woo-pro');
    delete_option('bfw_version_db');
    delete_option('bfw_caps_reconciled');
    return;
}

if (!empty($val['clear-bfw-bd'])) {
    //drop tables
    global $wpdb;

    $table_name1 = $wpdb->prefix . 'bfw_computy';
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name1));

    $table_name2 = $wpdb->prefix . 'bfw_history_computy';
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name2));

    $table_name3 = $wpdb->prefix . 'bfw_logs_computy';
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name3));

    $table_name4 = $wpdb->prefix . 'bfw_coupons_computy';
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name4));

    // Ранее не удалялись: использования купонов и журнал транзакций
    $table_name5 = $wpdb->prefix . 'bfw_coupon_usages';
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name5));

    $table_name6 = $wpdb->prefix . 'bfw_ledger';
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name6));

    //delete user_meta (батчами, чтобы не съесть память на больших сайтах)
    $bfw_user_meta_keys = array(
        'computy_point',
        'computy_fast_point',
        'bfw_points_referral',
        'bfw_points_referral_invite',
        'bfw_status',
        'dob',
        'points_every_day',
        'daily_coupon',
        'mail_remove_points',
        'total_purchases_sum',
        'this_year',
        'first_order',
        'f14',
    );

    $bfw_keys_in = implode(', ', array_fill(0, count($bfw_user_meta_keys), '%s'));
    $bfw_batch   = 1000;

    do {
        $bfw_user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$wpdb->usermeta}
             WHERE meta_key IN ({$bfw_keys_in})
             LIMIT %d",
            array_merge($bfw_user_meta_keys, array($bfw_batch))
        ));

        if (empty($bfw_user_ids)) {
            break;
        }

        foreach ($bfw_user_ids as $bfw_uid) {
            foreach ($bfw_user_meta_keys as $bfw_key) {
                delete_user_meta((int) $bfw_uid, $bfw_key);
            }
        }
    } while (count($bfw_user_ids) === $bfw_batch);

    // Меты комментариев (начисление за отзыв)
    $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE meta_key = '_bfw_review_points_awarded'");

    // Меты заказов (HPOS + legacy)
    $bfw_order_meta_keys = array(
        '_bfw_points_to_deduct',
        '_bfw_points_deducted',
        '_bfw_returned_points',
        '_bfw_revoked_points',
        '_bfw_pending_points',
        '_bonus_cashback_pending',
        '_bonus_cashback_paid',
        'cashback_receipt',
        'cashback_amount',
        '_was_on_sale',
    );

    foreach ($bfw_order_meta_keys as $bfw_key) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = %s",
            $bfw_key
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $bfw_key
        ));
    }

    // Метки товаров
    foreach (array('_constant_cashback_percentage', '_constant_cashback_percentage_variation') as $bfw_key) {
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $bfw_key));
    }

    // Товар оффлайн-заказа запоминаем до удаления опции
    $bfw_offline_product = get_option('bonus-for-woo-offline-product');

    //delete options
    $options = array(
        'bonus_option_name',
        'bonus-for-woo-pro',
        'bonus-for-woo-offline-product',
        'bfw_version_db',
        'bfw_caps_reconciled',
        'cashback_orders_to_recount',
        'bfw_ledger_migrated_history_id',
        'bfw_ledger_migrated',
        'bfw_pro_updated'
    );
    foreach ($options as $option) {
        delete_option($option);
    }

    // Транзиенты плагина
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_bfw%'
            OR option_name LIKE '_transient_timeout_bfw%'"
    );

    // Файлы экспорта: новый (uploads) и старый (каталог плагина)
    $bfw_upload_dir = wp_upload_dir(null, false);
    $bfw_export_files = array(
        WP_PLUGIN_DIR . '/bonus-for-woo/export_bfw.csv',
        trailingslashit($bfw_upload_dir['basedir']) . 'bfw_points_export.csv',
    );
    foreach ($bfw_export_files as $bfw_export_file) {
        if (is_file($bfw_export_file)) {
            wp_delete_file($bfw_export_file);
        }
    }

    // Товар оффлайн-заказа
    if ($bfw_offline_product) {
        wp_delete_post((int) $bfw_offline_product, true);
    }

    // Права, выданные старыми версиями плагина
    $bfw_shop_manager = get_role('shop_manager');
    if ($bfw_shop_manager) {
        $bfw_shop_manager->remove_cap('manage_options');
        $bfw_shop_manager->remove_cap('edit_users');
    }
}
