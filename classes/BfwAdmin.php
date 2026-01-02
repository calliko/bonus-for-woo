<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class BfwAdmin
 *
 * @version 3.4.2
 */
class BfwAdmin
{

    /**
     * !!! Безопасная подпись пользователя: имя/фамилия или логин; если пользователь удалён — заглушка.
     */
    private static function safe_user_label(int $user_id): string
    {
        $u = get_userdata($user_id);
        if ($u && is_object($u)) {
            $first = isset($u->first_name) ? trim((string)$u->first_name) : '';
            $last = isset($u->last_name) ? trim((string)$u->last_name) : '';
            $login = isset($u->user_login) ? trim((string)$u->user_login) : '';
            $name = trim(($first || $last) ? ($first . ' ' . $last) : $login);
            return $name !== '' ? $name : sprintf(__('User #%d', 'bonus-for-woo'), $user_id);
        }
        return sprintf(__('Deleted user #%d', 'bonus-for-woo'), $user_id);
    }

    /**
     * Добавляет ссылку "Настройки" на странице плагинов
     *
     * @param array $links Массив ссылок плагина
     * @param string $file Имя файла плагина
     * @return array
     */
    public static function add_settings_link($links, $file) {
        // Проверка - наш это плагин или нет
        if ($file !== plugin_basename(BONUS_COMPUTY_PLUGIN_DIR . 'index.php')) {
            return $links;
        }

        $settings_link = sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=bonus_for_woo-plugin-options'),
                __('Settings', 'bonus-for-woo')
        );
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Инициализация меню, скриптов
     *
     * @return void
     */
    public static function init(): void
    {
        if (current_user_can('manage_options') || (defined('WP_CLI') && WP_CLI)) {
            /* Инициализируем меню в админке*/
            add_action('admin_menu', array('BfwAdmin', 'add_admin_menu'));

            /*Загружаем скрипты и стили*/
            add_action('admin_enqueue_scripts', array('BfwAdmin', 'load_scripts'));
            /*Вывод настроек в меню*/
            add_action('admin_init', array('BfwAdmin', 'plugin_settings'));

            /*------------Добавление поля в профиле клиента для администратора------------*/
            if (current_user_can('administrator') || current_user_can('shop_manager')) {
                add_action('show_user_profile', array('BfwAdmin', 'bfwooAddBonusInUserProfile'));
                add_action('edit_user_profile', array('BfwAdmin', 'bfwooAddBonusInUserProfile'));
            }
            /*----Добавление поля на странице wp-admin/users.php----*/
            add_filter('manage_users_sortable_columns', array('BfwAdmin', 'bfwoo_sortable_cake_column'), 10, 1);
            add_filter('manage_users_sortable_columns', array('BfwAdmin', 'bfwoo_sortable_cake_column_status'), 10, 1);
            /* order by*/
            add_action('pre_get_users', array('BfwAdmin', 'bfwoo_action_pre_get_users'), 10, 1);
            add_filter('manage_users_columns', array('BfwAdmin', 'bfwoo_add_new_user_column_bonus'));
            add_filter('manage_users_columns', array('BfwAdmin', 'bfwoo_add_new_user_column_bonus_status'));

            add_filter('manage_users_custom_column', array('BfwAdmin', 'bfwoo_add_new_user_column_content'), 10, 3);
            add_filter('manage_users_custom_column', array('BfwAdmin', 'bfwoo_add_new_user_column_content_status'), 10,
                    3);


            add_action('woocommerce_admin_order_data_after_order_details',
                    array('BfwAdmin', 'display_cashback_info_in_admin'));

            add_action('woocommerce_screen_ids', array('BfwAdmin', 'set_screen_id'));

            if (BfwRoles::isPro()) {
                /*Настройка поля постоянного кешбэка в редакторе товара*/
                add_action('woocommerce_product_options_general_product_data',
                        array('BfwAdmin', 'addConstantProductCashback'));
                add_action('woocommerce_process_product_meta', array('BfwAdmin', 'saveConstantProductCashback'));
                add_action('woocommerce_product_after_variable_attributes',
                        array('BfwAdmin', 'addConstantVariationPrCash'), 10, 3);
                add_action('woocommerce_save_product_variation', array('BfwAdmin', 'saveConstantVariationPrCash'), 10,
                        2);
            }
        }
    }


    /**
     * Вывод информации о кешбэке в редакторе заказа
     *
     * @param $order
     * @return void
     */
    public static function display_cashback_info_in_admin($order)
    {
        $cashback_status = $order->get_meta('cashback_receipt');
        $cashback_amount = $order->get_meta('cashback_amount');

        if ($cashback_status || $cashback_amount) {
            echo '<div class="order-cashback-info">';
            echo '<h3>Bonus for woo</h3>';

            if ($cashback_amount) {
                echo '<p><strong>' . __('Cashback received:',
                                'bonus-for-woo') . '</strong> ' . $cashback_amount . ' ' . BfwPoints::pointsLabel($cashback_amount) . '</p>';
            }

            echo '</div>';
        } else {
            $order_id = $order->get_id();
            $estimated = BfwPoints::howMatchCashbackInOrder($order_id);
            if ((int)$estimated > 0) {
                echo '<div class="order-cashback-info"><h3>Bonus for woo</h3>
<p><strong>' . __('Estimated cashback:',
                                'bonus-for-woo') . '</strong> ' . $estimated . ' ' . BfwPoints::pointsLabel($estimated) . '</p>';

                $pending = $order->get_meta('_bonus_cashback_pending', true);

                if (is_array($pending)) {
                    $date = wp_date('d.m.y H:i:s', $pending['process_after']);
                    echo '<p><strong>' . __('Planned accrual time:', 'bonus-for-woo') . '</strong> ' . $date . '</p>';
                }
                if ((int)$estimated > 0) {
                    echo '<button id="send-points-from-order" class="button button-primary">' . __('Send now',
                                    'bonus-for-woo') . '</button>
                    <div id="bonus-points-response" style="margin-top: 10px; display: none;"></div>';
                }
                echo '</div>';
            }

        }
    }

    /**
     * Настройка поля постоянного кешбэка в редакторе товара
     *
     * @return void
     * @version 7.0.0
     */
    public static function addConstantProductCashback()
    {
        // Добавляем текстовое поле
        woocommerce_wp_text_input(array(
                'id' => '_constant_cashback_percentage',
                'label' => __('Cashback percentage', 'woocommerce'),
                'placeholder' => __('Enter cashback percentage', 'woocommerce'),
                'desc_tip' => 'true',
                'description' => __('This cashback status does not affect.', 'woocommerce'),
        ));
    }

    /**
     * Сохранение поля постоянного кешбэка в редакторе товара
     *
     * @return void
     * @version 7.0.0
     */
    public static function saveConstantProductCashback($post_id)
    {
        // Получаем значение поля
        $custom_field_value = isset($_POST['_constant_cashback_percentage']) ? sanitize_text_field($_POST['_constant_cashback_percentage']) : '';
        // Сохраняем значение в метаданных товара
        update_post_meta($post_id, '_constant_cashback_percentage', $custom_field_value);
    }


    /**
     * Настройка поля постоянного кешбэка в редакторе вариативного товара
     *
     * @return void
     * @version 7.0.0
     */
    public static function addConstantVariationPrCash($loop, $variation_data, $variation)
    {
        // Добавляем текстовое поле
        woocommerce_wp_text_input(array(
                'id' => '_constant_cashback_percentage_variation[' . $variation->ID . ']',
                'label' => __('Cashback percentage', 'woocommerce'),
                'placeholder' => __('Enter cashback percentage', 'woocommerce'),
                'desc_tip' => 'true',
                'description' => __('This cashback status does not affect.', 'woocommerce'),
                'value' => get_post_meta($variation->ID, '_constant_cashback_percentage_variation', true),
        ));
    }


    /**
     * Сохранение поля постоянного кешбэка в редакторе вариативного товара
     *
     * @return void
     * @version 7.0.0
     */
    public static function saveConstantVariationPrCash($variation_id, $i)
    {
        // Получаем значение поля
        $custom_field_value = isset($_POST['_constant_cashback_percentage_variation'][$variation_id]) ? sanitize_text_field($_POST['_constant_cashback_percentage_variation'][$variation_id]) : '';

        // Сохраняем значение в метаданных вариации
        update_post_meta($variation_id, '_constant_cashback_percentage_variation', $custom_field_value);
    }


    /**
     * @param $screen
     *
     * @return mixed
     */
    public static function set_screen_id($screen)
    {
        $screen[] = 'woocommerce_page_bonus_for_woo-plugin-options';
        return $screen;
    }


    /**
     * Добавление поля в профиле клиента для администратора
     *
     * @param $user
     *
     * @return void
     */
    public static function bfwooAddBonusInUserProfile($user): void
    {
        ?>
        <hr>
        <div class="user_profile_bfw">
            <h1><?php echo esc_html(__('User bonus points', 'bonus-for-woo')); ?></h1>
            <?php
            echo '<p><b>' . esc_html(__('Total amount of orders',
                            'bonus-for-woo')) . ': ' . esc_html(BfwPoints::getSumUserOrders($user->ID)) . ' ' . esc_html(get_woocommerce_currency_symbol()) . '</b></p>';

            if (BfwRoles::isInvalve($user->ID)) {
                $roles = BfwRoles::getRole($user->ID);
                echo '<p><b>' . esc_html(__('Status',
                                'bonus-for-woo')) . ': ' . esc_html($roles['name']) . '</b></p> <hr>';

                /*Обработчик удаления записи истории начисления баллов*/
                if (isset($_POST['bfw_delete_post_history_points'])) {
                    BfwHistory::deleteHistoryId(sanitize_text_field($_POST['bfw_delete_post_history_points']));
                    echo '<div id="message" class="notice notice-warning is-dismissible">
	<p>' . esc_html__('deleted', 'bonus-for-woo') . '.</p></div>';
                }
                /*Обработчик удаления записи истории начисления баллов*/

                /*Обработчик удаления всей истории начисления баллов*/
                if (isset($_GET['bfw_delete_all_post_history_points'])) {
                    $delete_history_points = sanitize_text_field($_GET['bfw_delete_all_post_history_points']);
                    BfwHistory::clearAllHistoryUser($delete_history_points);

                    echo '<div id="message" class="notice notice-warning is-dismissible"><p>' . esc_html__('Cleared',
                                    'bonus-for-woo') . '.</p></div>';
                }
                /*Обработчик удаления всей истории начисления баллов*/

                if (BfwRoles::isPro()) { ?>
                    <div class="bfw-offline-block"><span class="bfw-help-tip faq"
                                                         data-tip="<?php echo esc_attr(__('The client will have a new order and earn bonus points',
                                                                 'bonus-for-woo')); ?>"></span>
                        <label for="bfw_offline_order_price"><b><?php esc_html_e('Place an order offline',
                                        'bonus-for-woo'); ?></b>
                        </label>
                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                        <input type="text" id="bfw_offline_order_price" name="bfw_offline_order_price"
                               placeholder="<?php echo esc_attr(__('Enter amount', 'bonus-for-woo')); ?>">
                        <input type="submit" name="submit" id="submit3" class="button button-primary"
                               value="<?php echo esc_attr(__('Add order', 'bonus-for-woo')); ?>">
                    </div>
                    <hr>
                    <p>
                        <label for="dob"><b><?php esc_html_e('Date of birth', 'bonus-for-woo'); ?></b> </label>
                        <input type="date" class="woocommerce-Input woocommerce-Input--text input-text" name="dob"
                               id="dob"
                               value="<?php echo esc_attr($user->dob); ?>"/>

                        <?php if (isset($user->this_year) && $user->this_year == gmdate('Y')) {
                            echo esc_html__('The client received points this year', 'bonus-for-woo');
                        } else {
                            echo esc_html__('The client did not receive points this year', 'bonus-for-woo');
                        } ?>
                    </p>
                    <hr>
                    <i style="color: #005ac9"><?php echo esc_html(__('You can change the number of bonus points.',
                                'bonus-for-woo')); ?>
                    </i>
                <?php } ?>
                <p><b><?php echo esc_html(__('Total bonus points', 'bonus-for-woo')); ?>:</b> <?php
                    $balluser = BfwPoints::getPoints($user->ID);
                    echo esc_html($balluser);
                    ?></p>

                <p><label> <?php echo esc_html(__('Change bonus points', 'bonus-for-woo')); ?>
                        <input type="number" name="computy_input_points" value="<?php echo esc_attr($balluser); ?>"
                               class="regular-text"/></label></p>
                <p><label><textarea style="width: 100%;height: 100px;" name="prichinaizmeneniya"
                                    placeholder="<?php echo esc_attr(__('The reason for the change in points. It will be displayed in the client\'s accrual history.',
                                            'bonus-for-woo')); ?>"></textarea></label>
                </p>
                <p><input type="submit" name="submit" id="submit1" class="button button-primary"
                          value="<?php echo esc_attr(__('change', 'bonus-for-woo')); ?>"></p>

                <hr>
                <?php
                /*история начислений баллов клиента (HTML внутри — собственный вывод)*/
                echo BfwHistory::getHistory($user->ID);


                /*если включена реферальная система*/
                if (BfwRoles::isPro() && BfwSetting::get('referal-system')) { ?>
                    <hr> <h3><?php echo esc_html(__('Referral system', 'bonus-for-woo')); ?></h3>
                    <?php
                    $get_referral = get_user_meta($user->ID, 'bfw_points_referral', true);
                    $get_referral_invite = get_user_meta($user->ID, 'bfw_points_referral_invite', true);

                    /*Сколько людей пригласил*/
                    $argsa = array(
                            'meta_query' => array(
                                    array(
                                            'key' => 'bfw_points_referral_invite',
                                            'value' => trim((string)$user->ID),
                                            'compare' => '==',
                                    ),
                            ),
                            'fields' => array('ID', 'user_nicename'),
                            'number' => -1,
                    );
                    $refere_data = get_users($argsa);

                    $referral_one_user_name = array();
                    $referral_one_id = array();
                    foreach ($refere_data as $ref_data_one) {
                        $referral_one_user_name[] = $ref_data_one->user_nicename;
                        $referral_one_id[] = $ref_data_one->ID;
                    }

                    echo esc_html(__('Referral link',
                                    'bonus-for-woo')) . ': <code>' . esc_url(site_url() . '?bfwkey=') . '</code>'
                            . '<input type="text" name="bfw-referall-link" value="' . esc_attr($get_referral) . '"><br>';

                    /* Безопасный вывод пригласившего */
                    if (!empty($get_referral_invite) && (int)$get_referral_invite > 0) {
                        $inviter_id = (int)$get_referral_invite;
                        $inviter_obj = get_userdata($inviter_id);

                        // собираем подпись
                        if ($inviter_obj && is_object($inviter_obj)) {
                            $first = isset($inviter_obj->first_name) ? trim((string)$inviter_obj->first_name) : '';
                            $last = isset($inviter_obj->last_name) ? trim((string)$inviter_obj->last_name) : '';
                            $login = isset($inviter_obj->user_login) ? trim((string)$inviter_obj->user_login) : '';
                            $inviter_label = trim(($first || $last) ? ($first . ' ' . $last) : $login);
                            if ($inviter_label === '') {
                                $inviter_label = sprintf(__('User #%d', 'bonus-for-woo'), $inviter_id);
                            }

                            $edit_url = admin_url('user-edit.php?user_id=' . $inviter_id);
                            echo esc_html(__('Invited by user', 'bonus-for-woo')) . ': '
                                    . '<a href="' . esc_url($edit_url) . '">' . esc_html($inviter_label) . '</a><br>';
                        } else {
                            // Пользователь удалён — без ссылки
                            $deleted_label = sprintf(__('Deleted user #%d', 'bonus-for-woo'), $inviter_id);
                            echo esc_html(__('Invited by user',
                                            'bonus-for-woo')) . ': ' . esc_html($deleted_label) . '<br>';
                        }
                    }

                    echo esc_html(__('Invited',
                                    'bonus-for-woo')) . ' ' . esc_html(count($refere_data)) . ' ' . esc_html(__('people',
                                    'bonus-for-woo'));
                    echo ': ';

                    $allSum = 0;
                    for ($i = 0; $i <= count($refere_data) - 1; $i++) {
                        /*Выводим список приглашенных первого уровня*/
                        $uid = (int)$referral_one_id[$i];
                        $name = (string)$referral_one_user_name[$i];
                        $sum = BfwPoints::getSumUserOrders($uid);
                        $allSum += $sum;
                        $edit_url = admin_url('user-edit.php?user_id=' . $uid);
                        echo ' <a href="' . esc_url($edit_url) . '">' . esc_html($name) . ' (' . esc_html($sum) . ' ' . esc_html(get_woocommerce_currency_symbol()) . ')</a>, ';
                    }
                    echo '<p>' . esc_html(__('Total amount of referral orders:',
                                    'bonus-for-woo')) . ' ' . esc_html($allSum) . ' ' . esc_html(get_woocommerce_currency_symbol()) . '</p>';

                    if (BfwSetting::get('level-two-referral')) {
                        /* Считаем второй уровень. */
                        $referral_two_user_name = array();
                        $referral_two_id = array();
                        $refere_data_two_two = 0;

                        foreach ($refere_data as $refere_data_two) {
                            $argsatwo = array(
                                    'meta_query' => array(
                                            array(
                                                    'key' => 'bfw_points_referral_invite',
                                                    'value' => trim((string)$refere_data_two->ID),
                                                    'compare' => '==',
                                            ),
                                    ),
                                    'fields' => array('ID', 'user_nicename'),
                                    'number' => -1,
                            );

                            $ref_list = get_users($argsatwo);
                            $refere_data_two_two += count($ref_list);

                            foreach ($ref_list as $ref_data_twos) {
                                $referral_two_user_name[] = $ref_data_twos->user_nicename;
                                $referral_two_id[] = $ref_data_twos->ID;
                            }
                        }

                        echo '<br>' . esc_html(__('Invited friends',
                                        'bonus-for-woo')) . ' ' . esc_html($refere_data_two_two) . ' ' . esc_html(__('people',
                                        'bonus-for-woo'));
                        echo ': ';
                        for ($i = 0; $i <= $refere_data_two_two - 1; $i++) {
                            $uid2 = (int)$referral_two_id[$i];
                            $name2 = (string)$referral_two_user_name[$i];
                            $edit2 = admin_url('user-edit.php?user_id=' . $uid2);
                            echo ' <a href="' . esc_url($edit2) . '">' . esc_html($name2) . '</a>, ';
                        }
                    }
                }

            } else {
                echo ' <b>' . esc_html(__('This user does not participate in the bonus system.',
                                'bonus-for-woo')) . '</b>';
            }
            ?>
        </div>
        <?php
    }


    /**
     *
     * @param $query
     *
     * @return void
     */
    public static function bfwoo_action_pre_get_users($query): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $orderby = $query->get('orderby');
        if ($orderby === __('Bonus points', 'bonus-for-woo')) {
            $query->set('orderby', 'meta_value_num');
            $query->set('meta_key', 'computy_point');
        }
    }


    /**
     * @param $columns
     *
     * @return mixed
     */
    public static function bfwoo_add_new_user_column_bonus($columns)
    {
        $columns['computy_point'] = __('Bonus points', 'bonus-for-woo');
        return $columns;
    }

    /**
     * @param $columns
     *
     * @return mixed
     */
    public static function bfwoo_add_new_user_column_bonus_status($columns)
    {
        $columns['bfw_status'] = __('User status', 'bonus-for-woo');
        return $columns;
    }

    /**
     * @param $content
     * @param $column
     * @param int $user_id
     *
     * @return float|mixed
     */
    public static function bfwoo_add_new_user_column_content($content, $column, int $user_id)
    {
        if ('computy_point' === $column) {
            $content = BfwPoints::getPoints($user_id);
        }
        return $content;
    }

    /**
     * @param $content
     * @param $column
     * @param int $user_id
     *
     * @return mixed
     */
    public static function bfwoo_add_new_user_column_content_status($content, $column, int $user_id)
    {
        if ('bfw_status' === $column) {
            $content = BfwRoles::getRole($user_id)['name'];
        }
        return $content;
    }



    /*----Добавление поля на странице wp-admin/users.php----*/
    /**
     * @param $columns
     *
     * @return mixed
     */
    public static function bfwoo_sortable_cake_column($columns)
    {
        $columns['computy_point'] = __('Bonus points', 'bonus-for-woo');
        return $columns;
    }

    /**
     * @param $columns
     *
     * @return mixed
     */
    public static function bfwoo_sortable_cake_column_status($columns)
    {
        $columns['bfw_status'] = __('User status', 'bonus-for-woo');
        return $columns;
    }

    /**
     * @param $menu_links
     *
     * @return array
     */
    public static function bonuses_link($menu_links): array
    {
        $menu_links = array_slice($menu_links, 0, 5, true) + array(
                        'bonuses' => __('My bonuses', 'bonus-for-woo')
                ) + array_slice($menu_links, 5, null, true);
        $menu_links['bonuses'] = __('My bonuses', 'bonus-for-woo');
        return $menu_links;
    }


    /**
     * Инициализируем меню в админке
     *
     * @return void
     */
    public static function add_admin_menu(): void
    {

        $home_menu_title = __('Bonus for Woo', 'bonus-for-woo');
        $menu_list_history = __('Bonus points history', 'bonus-for-woo');
        $menu_list_history_title = __('History of bonus points for all customers', 'bonus-for-woo');

        /*Страница с основными настройками*/
        add_menu_page($home_menu_title, $home_menu_title, 'manage_options', 'bonus_for_woo-plugin-options',
                array('BfwAdmin', 'bonus_plugin_options'), plugins_url('bonus-for-woo/img/coin.svg'), 30);

        /* Добавляем в меню историю всех начислений клиентов*/
        add_submenu_page('bonus_for_woo-plugin-options', $menu_list_history_title, $menu_list_history, 'manage_options',
                'bonus-for-woo/pages/list_history.php', '', 1);

        /*cтраница статистик*/
        add_submenu_page('bonus_for_woo-plugin-options', __('Bonus points statistic - Bonus for Woo', 'bonus-for-woo'),
                __('Bonus system statistic', 'bonus-for-woo'), 'manage_woocommerce',
                'bonus-for-woo/pages/statistic.php', '', 2);

        /*Страница управления купонами*/
        if (BfwRoles::isPro() && BfwSetting::get('coupon-system')) {
            add_submenu_page('bonus_for_woo-plugin-options',
                    __('Bonus points coupons - Bonus for Woo', 'bonus-for-woo'),
                    __('Bonus points coupons', 'bonus-for-woo'), 'manage_woocommerce',
                    'bonus-for-woo/pages/coupons.php', '', 3);
        }
        add_submenu_page('bonus_for_woo-plugin-options', __('Tools', 'bonus-for-woo'), __('Tools', 'bonus-for-woo'),
                'manage_woocommerce', 'bonus-for-woo/pages/tools.php', '', 4);


        /*Генератор правил и условий*/
        add_submenu_page('', __('Rules and Conditions Generator', 'bonus-for-woo'),
                __('Rules and Conditions Generator', 'bonus-for-woo'), 'manage_woocommerce',
                'bonus-for-woo/pages/generator.php', '', 4);


        /* Добавляем в меню историю всех начислений клиентов*/
        add_submenu_page('bonus_for_woo-plugin-options', __('Logs', 'bonus-for-woo'), __('Logs', 'bonus-for-woo'),
                'manage_options', 'bonus-for-woo/pages/logs.php', '', 5);

    }


    /**
     * Загрузка скриптов
     *
     * @return void
     */
    public static function load_scripts(): void
    {
        wp_register_style('bonus-for-woo-computy-style-admin',
                BONUS_COMPUTY_PLUGIN_URL . '_inc/bonus-for-woo-style-admin.css', array(), BONUS_COMPUTY_VERSION);
        wp_enqueue_style('bonus-for-woo-computy-style-admin');

        wp_register_style('slimselectcss', BONUS_COMPUTY_PLUGIN_URL . '_inc/slimselect.min.css', array(),
                BONUS_COMPUTY_VERSION);
        wp_enqueue_style('slimselectcss');

        wp_register_script('bonus-computy-script-admin',
                BONUS_COMPUTY_PLUGIN_URL . '_inc/bonus-computy-script-admin.js', array('jquery'), BONUS_COMPUTY_VERSION,
                true);
        wp_enqueue_script('bonus-computy-script-admin');
        wp_localize_script('bonus-computy-script-admin', 'bfwScriptAdmin', [
                'done' => __('Done! Orders processed:', 'bonus-for-woo'),
        ]);
        if (BfwRoles::isPro()) {
            wp_enqueue_script('custom-admin-script', BONUS_COMPUTY_PLUGIN_URL . '_inc/write-points-in-order-admin.js',
                    array('jquery'), BONUS_COMPUTY_VERSION, true);
        }
        wp_register_script('slimselect', BONUS_COMPUTY_PLUGIN_URL . '_inc/slimselect.min.js', array('jquery'),
                BONUS_COMPUTY_VERSION, true);
        wp_enqueue_script('slimselect');
    }


    /**
     * Вывод настроек в меню
     *
     * @return void
     */
    public static function plugin_settings(): void
    {
        register_setting('option_group_bonus', 'bonus_option_name',
                array('sanitize_callback' => array('BfwAdmin', 'sanitize_callback_bfw'),));
        $trans1 = __('Plugin settings', 'bonus-for-woo');

        $trans_write_points_order_status = __('At what order status can points be debited?', 'bonus-for-woo');
        $trans_add_points_order_status = __('At what status of an order can points be awarded?',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('Attention! The amount of orders and the setting of user statuses will be calculated according to this order status.',
                        'bonus-for-woo'), 'danger');
        $trans_refunded_points_order_status = __('At what order status will points be returned?', 'bonus-for-woo');
        $trans2 = __('Number of bonus points for a product review', 'bonus-for-woo');
        $trans2 .= BfwFunctions::helpTip(__('* If the value is greater than 0, all reviews will have to be manually approved.',
                        'bonus-for-woo') . ' ' . __('* Accrued only if the customer bought this product.',
                        'bonus-for-woo'));

        $trans_rulles = __('Link to the terms and conditions.', 'bonus-for-woo');
        $trans_round_points = __('Do not round decimals in points?', 'bonus-for-woo');
        $trans12 = __('Should cashback be shown on the product page?', 'bonus-for-woo');
        $trans12b = __('Should cashback be displayed on category pages?', 'bonus-for-woo');
        $trans12c = __('Do not show the word "up to" before the score.',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('The prefix "Do" is displayed only for unregistered users and in variable products.',
                        'bonus-for-woo'));

        $trans14 = __('Writing off points in ordering',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('Uncheck the checkbox if the checkout is in the basket.',
                        'bonus-for-woo'), 'danger');
        $trans15 = __('Hide deduction of points for sale items?', 'bonus-for-woo');

        $trans16 = __('Percentage of the order amount that can be spent by the client in points.', 'bonus-for-woo');
        $trans17 = __('Exclude product categories that cannot be purchased with cashback points.', 'bonus-for-woo');
        $transpaymethod = __('Exclude payment method from the bonus system.', 'bonus-for-woo');
        $trans18 = __('Exclude products that cannot be purchased with cashback points.', 'bonus-for-woo');
        $trans19 = __('Points for registration', 'bonus-for-woo');
        $trans20 = __('Accrue cashback on excluded products and categories?', 'bonus-for-woo');
        $trans20sale = __('Do not accrue cashback on sale products?', 'bonus-for-woo');
        $trans20a = __('If the client uses points, cashback is not credited.', 'bonus-for-woo');
        $trans20b = __('When a customer uses a coupon, cashback is not accrued.', 'bonus-for-woo');
        $trans21 = '<span style="font-size: 20px;">' . __('Referral system', 'bonus-for-woo') . '</span>';
        $trans_coupon = '<label for="coupon-system" style="font-size: 20px;">' . __('Coupons',
                        'bonus-for-woo') . '</label>';
        $trans21qty = __('Clear points that the customer wants to deduct when the number of items in the cart changes',
                'bonus-for-woo');
        $trans22 = __('Exclude roles from the bonus system', 'bonus-for-woo');
        $trans23 = __('Don\'t spend points if a coupon is applied?',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('When the checkbox is selected, the points cannot be spent, but the cashback will be accrued.',
                        'bonus-for-woo'));
        $transefees = __('Ignore coupons and discounts when calculating cashback', 'bonus-for-woo');
        if (function_exists('BfwFunctions::helpTip')) {
            $transefees .= BfwFunctions::helpTip(__('Cashback will be credited without taking into account discounts.',
                    'bonus-for-woo'));
        }
        $trans24 = __('Remove cashback for delivery?',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('When the checkbox is checked, cashback for delivery will not be credited.',
                        'bonus-for-woo'));
        $shippingtotalsum = __('Do not include shipping when calculating the order amount.', 'bonus-for-woo');
        $trans25 = __('The minimum order amount to use points.', 'bonus-for-woo');
        $trans26 = __('The percentage of points accrued from the referral\'s order.', 'bonus-for-woo');
        $trans_referral_cashback_two_level = __('The percentage of points accrued from the second level referral\'s order.',
                'bonus-for-woo');
        $trans27 = __('Earn points only for the first referral order?',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('The referer will not receive points from the second order.',
                        'bonus-for-woo'));
        $leveltwo = __('Use a two-tier system',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('Points will come for those invited by your friends.',
                        'bonus-for-woo'));

        $trans28 = __('Hide customer points history?', 'bonus-for-woo');
        $trans12a = __('Show points that will be returned to the customer in the cart and checkout?', 'bonus-for-woo');
        $trans29 = __('The Sum of orders after which the referral system will become available to the client.',
                'bonus-for-woo');
        $trans_soc = __('Social links for the referral system', 'bonus-for-woo');
        if (function_exists('BfwFunctions::helpTip')) {
            $trans_soc .= BfwFunctions::helpTip(__('The icons will appear on the user\'s account page.',
                    'bonus-for-woo'));
        }
        $buy_balls = __('Product for which 100% cashback is charged.', 'bonus-for-woo');
        $order_start_date = __('From what date to count the amount of orders?', 'bonus-for-woo');
        $birthday = __('Points on your birthday', 'bonus-for-woo');
        if (function_exists('BfwFunctions::helpTip')) {
            $birthday .= BfwFunctions::helpTip(__('If more than 0 is specified, then the client will have a date entry field in the account settings.',
                    'bonus-for-woo'));
        }
        $every_days = __('Daily points for the first login', 'bonus-for-woo');
        if (function_exists('BfwFunctions::helpTip')) {
            $every_days .= BfwFunctions::helpTip(__('Awarding points to the client for logging in. Charged once a day.',
                    'bonus-for-woo'));
        }
        $cashback_for_first_order = __('Cashback for the first order', 'bonus-for-woo');
        $trans_soc2 = __('Show social media links on product page?', 'bonus-for-woo');
        $transclear = __('Remove plugin traces upon activation.',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('Attention! This will remove all settings and all user bonus points.',
                        'bonus-for-woo'), 'danger');
        $trans30 = __('The order of the "My bonuses" menu item in the client account.', 'bonus-for-woo');
        $trans_inactive = __('Removing bonus points for inactivity', 'bonus-for-woo');
        $trans_inactive_notice = __('How many days in advance do I have to give notice to deduct points?',
                'bonus-for-woo');
        $trans_fill_burn_point_in_account = __('Do not show in your account how many days of points burning are left.',
                'bonus-for-woo');
        register_setting('option_trans_group_bonus', 'bonus_option_name',
                array('type' => 'array', 'sanitize_callback' => array('BfwAdmin', 'sanitize_callback_bfw'),));
        add_settings_section('bonus_section_id', $trans1, '', 'primer_page_bonus');
        //позднее включим когда время будет все доделать
        if (function_exists('BfwFunctions::helpTip')) {
            add_settings_field('bonus_field0', __('Points Label',
                            'bonus-for-woo') . BfwFunctions::helpTip(__('First field singular, second plural.',
                            'bonus-for-woo')), array('BfwAdmin', 'name_points'), 'primer_page_bonus',
                    'bonus_section_id');
        } else {
            add_settings_field('bonus_field0', __('Points Label', 'bonus-for-woo'), array('BfwAdmin', 'name_points'),
                    'primer_page_bonus', 'bonus_section_id');
        }
        add_settings_field('field_rulles', $trans_rulles, array('BfwAdmin', 'fill_rulles'), 'primer_page_bonus',
                'bonus_section_id');

        add_settings_field('field_round_points', $trans_round_points, array('BfwAdmin', 'fill_round_points'),
                'primer_page_bonus', 'bonus_section_id');
        add_settings_field('field_write_points_order_status', $trans_write_points_order_status,
                array('BfwAdmin', 'fill_write_points_order_status'), 'primer_page_bonus', 'bonus_section_id');

        add_settings_field('field_add_points_order_status', $trans_add_points_order_status,
                array('BfwAdmin', 'fill_add_points_order_status'), 'primer_page_bonus', 'bonus_section_id');
        add_settings_field('field_refunded_points_order_status', $trans_refunded_points_order_status,
                array('BfwAdmin', 'fill_refunded_points_order_status'), 'primer_page_bonus', 'bonus_section_id');
        add_settings_field('bonus_field1', $trans2, array('BfwAdmin', 'fill_primer_field1'), 'primer_page_bonus',
                'bonus_section_id');


        add_settings_field('my_checkbox_field', $trans12, array('BfwAdmin', 'fill_primer_field12'), 'primer_page_bonus',
                'bonus_section_id');
        add_settings_field('my_checkbox_fieldb', $trans12b, array('BfwAdmin', 'fill_primer_field12b'),
                'primer_page_bonus', 'bonus_section_id');
        add_settings_field('my_checkbox_fieldc', $trans12c, array('BfwAdmin', 'fill_primer_field12c'),
                'primer_page_bonus', 'bonus_section_id');

        add_settings_field('my_checkbox_field12a', $trans12a, array('BfwAdmin', 'fill_primer_field12a'),
                'primer_page_bonus', 'bonus_section_id');

        add_settings_field('my_checkbox_field14', $trans14, array('BfwAdmin', 'fill_primer_field14'),
                'primer_page_bonus', 'bonus_section_id');
        add_settings_field('my_checkbox_field15', $trans15, array('BfwAdmin', 'fill_primer_field15'),
                'primer_page_bonus', 'bonus_section_id');
        add_settings_field('my_checkbox_field23', $trans23, array('BfwAdmin', 'fill_primer_field23'),
                'primer_page_bonus', 'bonus_section_id');
        add_settings_field('my_checkbox_exclude_fees_coupons', $transefees, array('BfwAdmin', 'exclude_fees_coupons'),
                'primer_page_bonus', 'bonus_section_id');
        add_settings_field('my_checkbox_field24', $trans24, array('BfwAdmin', 'fill_primer_field24'),
                'primer_page_bonus', 'bonus_section_id');
        add_settings_field('my_checkbox_field24two', $shippingtotalsum, array('BfwAdmin', 'fill_shipping_total_sum'),
                'primer_page_bonus', 'bonus_section_id');

        add_settings_field('my_checkbox_field28', $trans28, array('BfwAdmin', 'fill_primer_field28'),
                'primer_page_bonus', 'bonus_section_id');

        add_settings_field('bonus_field30', $trans30, array('BfwAdmin', 'fill_primer_field30'), 'primer_page_bonus',
                'bonus_section_id');


        register_setting('option_mail_group_bonus', 'bonus_option_name',
                array('type' => 'array', 'sanitize_callback' => array('BfwAdmin', 'sanitize_callback_bfw'),));
        add_settings_section('bonus_section_id', __('Pro settings', 'bonus-for-woo'), '', 'pro_page_bonus');


        if (BfwRoles::isPro()) {

            add_settings_field('input_fill_order_start_date', $order_start_date,
                    array('BfwAdmin', 'fill_order_start_date'), 'pro_page_bonus', 'bonus_section_id');

            add_settings_field('input_birthday', $birthday, array('BfwAdmin', 'fill_birthday'), 'pro_page_bonus',
                    'bonus_section_id');

            add_settings_field('input_every_days', $every_days, array('BfwAdmin', 'every_days'), 'pro_page_bonus',
                    'bonus_section_id');
            add_settings_field('cashback_for_first_order', $cashback_for_first_order,
                    array('BfwAdmin', 'cashback_for_first_order'), 'pro_page_bonus', 'bonus_section_id');

            add_settings_field('my_checkbox_field16', $trans16, array('BfwAdmin', 'fill_primer_field16'),
                    'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_checkbox_field_pay_method', $transpaymethod,
                    array('BfwAdmin', 'fill_primer_field_pay_method'), 'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_checkbox_field17', $trans17, array('BfwAdmin', 'fill_primer_field17'),
                    'pro_page_bonus', 'bonus_section_id');


            add_settings_field('my_checkbox_field18', $trans18, array('BfwAdmin', 'fill_primer_field18'),
                    'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_checkbox_field_buy_balls', $buy_balls, array('BfwAdmin', 'fill_buy_balls'),
                    'pro_page_bonus', 'bonus_section_id');

            add_settings_field('my_checkbox_field20', $trans20, array('BfwAdmin', 'fill_primer_field20'),
                    'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_checkbox_fill_cashback_on_sale_products', $trans20sale,
                    array('BfwAdmin', 'fill_cashback_on_sale_products'), 'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_checkbox_field20o', $trans20a, array('BfwAdmin', 'yous_balls_no_cashback_fild'),
                    'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_checkbox_field20b', $trans20b, array('BfwAdmin', 'yous_coupon_no_cashback_fild'),
                    'pro_page_bonus', 'bonus_section_id');


            add_settings_field('my_checkbox_field19', $trans19, array('BfwAdmin', 'fill_primer_field19'),
                    'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_checkbox_field122', $trans22, array('BfwAdmin', 'fill_primer_field22'),
                    'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_checkbox_field125', $trans25, array('BfwAdmin', 'fill_primer_field25'),
                    'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_checkbox_field21qty', $trans21qty, array('BfwAdmin', 'fill_primer_field21qty'),
                    'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_input_inactive', $trans_inactive, array('BfwAdmin', 'fill_inactive'),
                    'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_input_inactive_notice', $trans_inactive_notice,
                    array('BfwAdmin', 'fill_inactive_notice'), 'pro_page_bonus', 'bonus_section_id');
            add_settings_field('my_input_fill_burn_point_in_account', $trans_fill_burn_point_in_account,
                    array('BfwAdmin', 'fill_burn_point_in_account'), 'pro_page_bonus', 'bonus_section_id');

            add_settings_field('my_checkbox_field21', $trans21, array('BfwAdmin', 'fill_primer_field21'),
                    'pro_page_bonus', 'bonus_section_id');

            if (BfwSetting::get('referal-system')) {
                add_settings_field('my_checkbox_field27', $trans27, array('BfwAdmin', 'fill_primer_field27'),
                        'pro_page_bonus', 'bonus_section_id');
                add_settings_field('my_checkbox_fill_level_two', $leveltwo, array('BfwAdmin', 'fill_level_two'),
                        'pro_page_bonus', 'bonus_section_id');
                add_settings_field('my_checkbox_field26', $trans26, array('BfwAdmin', 'fill_primer_field26'),
                        'pro_page_bonus', 'bonus_section_id');
                if (!empty($ref['level-two-referral'])) {
                    add_settings_field('my_cashback_two_level', $trans_referral_cashback_two_level,
                            array('BfwAdmin', 'fill_referal_cashback_two_level'), 'pro_page_bonus', 'bonus_section_id');
                }
                add_settings_field('my_checkbox_field29', $trans29, array('BfwAdmin', 'fill_primer_field29'),
                        'pro_page_bonus', 'bonus_section_id');
                add_settings_field('social_icons', $trans_soc, array('BfwAdmin', 'fill_primer_field_social'),
                        'pro_page_bonus', 'bonus_section_id');
                add_settings_field('social_icons2', $trans_soc2, array('BfwAdmin', 'fill_primer_field_social_on_page'),
                        'pro_page_bonus', 'bonus_section_id');

            }
            add_settings_field('my_checkbox_coupon', $trans_coupon, array('BfwAdmin', 'fill_primer_coupons'),
                    'pro_page_bonus', 'bonus_section_id');
            if (!empty($ref['coupon-system'])) {
                add_settings_field('limit_coupon',
                        __('Maximum number of coupons per day for one client.', 'bonus-for-woo'),
                        array('BfwAdmin', 'limit_coupon'), 'pro_page_bonus', 'bonus_section_id');
            }

        }

        add_settings_field('my_checkbox_clear', $transclear, array('BfwAdmin', 'fill_primer_clear'),
                'primer_page_bonus', 'bonus_section_id');


        $trans3 = __('The title in the customers account.', 'bonus-for-woo');
        $trans3h = __('Bonus point history heading.', 'bonus-for-woo');
        $trans4 = __('"My status" in the customer account.', 'bonus-for-woo');
        $trans5 = __('"My cashback percentage" in the customer account.', 'bonus-for-woo');

        $trans7 = __('Text in the form of writing off points.', 'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                        'bonus-for-woo') . '<br>[points]-' . __('Number of points',
                        'bonus-for-woo') . '<br>[discount]-' . __('amount received', 'bonus-for-woo');
        $trans75 = __('Text of the number of points.', 'bonus-for-woo');

        $trans8 = __('The text "Use points" when opening the points write-off form.', 'bonus-for-woo');
        $trans9 = __('"Bonus points" name.',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('Main title. Used throughout the bonus system.',
                        'bonus-for-woo'), 'danger');
        $trans10 = __('The text "Delete points" on the button in the points write-off form.', 'bonus-for-woo');
        $trans_offline = __('Name of the offline product in the buyer\'s order', 'bonus-for-woo');
        $trans11 = __('Information about the remaining amount for the transition to another status.',
                        'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                        'bonus-for-woo') . '<br>[percent]-' . __('Next percentage',
                        'bonus-for-woo') . '<br>[status]-' . __('Next status',
                        'bonus-for-woo') . '<br>[sum]-' . __('Remaining amount', 'bonus-for-woo');

        $trans_match_bonus = __('The word "Cashback" in the product card.', 'bonus-for-woo');

        add_settings_section('bonus_section_id', __('Translate', 'bonus-for-woo'), '', 'trans_page_bonus');
        add_settings_field('bonus_field9', $trans9, array('BfwAdmin', 'fill_primer_field9'), 'trans_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field2', $trans3, array('BfwAdmin', 'fill_primer_field2'), 'trans_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field2h', $trans3h, array('BfwAdmin', 'fill_primer_field2h'), 'trans_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field4', $trans4, array('BfwAdmin', 'fill_primer_field4'), 'trans_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field5', $trans5, array('BfwAdmin', 'fill_primer_field5'), 'trans_page_bonus',
                'bonus_section_id');

        add_settings_field('bonus_field11', $trans11, array('BfwAdmin', 'fill_primer_field11'), 'trans_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field8', $trans8, array('BfwAdmin', 'fill_primer_field8'), 'trans_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field7', $trans7, array('BfwAdmin', 'fill_primer_field7'), 'trans_page_bonus',
                'bonus_section_id');

        add_settings_field('bonus_field75', $trans75, array('BfwAdmin', 'fill_primer_field75'), 'trans_page_bonus',
                'bonus_section_id');


        add_settings_field('bonus_field10', $trans10, array('BfwAdmin', 'fill_primer_field10'), 'trans_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field12', $trans_offline, array('BfwAdmin', 'fill_title_product_offline_order'),
                'trans_page_bonus', 'bonus_section_id');
        add_settings_field('bonus_field13', $trans_match_bonus, array('BfwAdmin', 'fill_title_how_match_bonus'),
                'trans_page_bonus', 'bonus_section_id');


        /*emails*/
        $trans_e = __('Email settings', 'bonus-for-woo');
        $trans_e0 = __('Use your send method',
                        'bonus-for-woo') . BfwFunctions::helpTip(__('Notifications will no longer be sent to customers by email. Check out the setup in the manual.',
                        'bonus-for-woo'), 'danger');
        $trans_e1 = __('Headings and styling:', 'bonus-for-woo');
        $trans_e2 = __('Changes to points by the administrator', 'bonus-for-woo');
        $trans_e3 = __('When registering', 'bonus-for-woo');
        $trans_e4 = __('When the order is confirmed', 'bonus-for-woo');
        $trans_e5 = __('When processing an order by an administrator(paid, returned)', 'bonus-for-woo');
        $trans_e6 = __('Product Review Notice(approval, rejection)', 'bonus-for-woo');
        $trans_e7 = __('When the status changes', 'bonus-for-woo');
        $fill_email_on_birthday = __('When are points earned on your birthday', 'bonus-for-woo');
        $fill_email_on_remove_points = __('About the imminent deletion of points.', 'bonus-for-woo');
        $fill_email_on_every_day = __('About earning points for signing in.', 'bonus-for-woo');


        add_settings_section('bonus_section_id', $trans_e, '', 'mail_page_bonus');

        add_settings_field('bonus_field_email_0', $trans_e0, array('BfwAdmin', 'fill_email_my_methode'),
                'mail_page_bonus', 'bonus_section_id');
        add_settings_field('bonus_field_email_1', $trans_e1, array('BfwAdmin', 'fill_email_1'), 'mail_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field_email_2', $trans_e2, array('BfwAdmin', 'fill_email_2'), 'mail_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field_email_2t',
                __('Email template when points are added by admin', 'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                        'bonus-for-woo') . '<br> [user]-' . __('Client name',
                        'bonus-for-woo') . '<br>[total]-' . __('Total points for the client',
                        'bonus-for-woo') . '<br>[points]-' . __('Number of points',
                        'bonus-for-woo') . '<br>[cause]-' . __('Cause',
                        'bonus-for-woo') . '<br>[referral-link]-' . __('Referral link', 'bonus-for-woo'),
                array('BfwAdmin', 'fill_email_2_template'), 'mail_page_bonus', 'bonus_section_id');

        add_settings_field('bonus_field_email_2ts',
                __('Email template when writing off points by admin', 'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                        'bonus-for-woo') . '<br> [user]-' . __('Client name',
                        'bonus-for-woo') . '<br>[total]-' . __('Total points for the client',
                        'bonus-for-woo') . '<br>[points]-' . __('Number of points',
                        'bonus-for-woo') . '<br>[cause]-' . __('Cause',
                        'bonus-for-woo') . '<br>[referral-link]-' . __('Referral link', 'bonus-for-woo'),
                array('BfwAdmin', 'fill_email_2_template2'), 'mail_page_bonus', 'bonus_section_id');


        if (BfwRoles::isPro()) {
            add_settings_field('bonus_field_email_3', $trans_e3, array('BfwAdmin', 'fill_email_3'), 'mail_page_bonus',
                    'bonus_section_id');
            add_settings_field('bonus_field_email_3t',
                    __('New user registration email template', 'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                            'bonus-for-woo') . '<br> [user]-' . __('Client name',
                            'bonus-for-woo') . '<br>[total]-' . __('Total points for the client',
                            'bonus-for-woo') . '<br>[points]-' . __('Number of points',
                            'bonus-for-woo') . '<br>[cause]-' . __('Cause',
                            'bonus-for-woo') . '<br>[referral-link]-' . __('Referral link', 'bonus-for-woo'),
                    array('BfwAdmin', 'fill_email_3_template'), 'mail_page_bonus', 'bonus_section_id');
        }
        add_settings_field('bonus_field_email_4', $trans_e4, array('BfwAdmin', 'fill_email_4'), 'mail_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field_email_4t', __('Template of the letter when the customer confirmed the order',
                        'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                        'bonus-for-woo') . '<br>[order]-' . __('Order',
                        'bonus-for-woo') . '<br> [user]-' . __('Client name',
                        'bonus-for-woo') . '<br>[total]-' . __('Total points for the client',
                        'bonus-for-woo') . '<br>[points]-' . __('Number of points',
                        'bonus-for-woo') . '<br>[cause]-' . __('Cause',
                        'bonus-for-woo') . '<br>[referral-link]-' . __('Referral link', 'bonus-for-woo'),
                array('BfwAdmin', 'fill_email_4_template'), 'mail_page_bonus', 'bonus_section_id');

        add_settings_field('bonus_field_email_5', $trans_e5, array('BfwAdmin', 'fill_email_5'), 'mail_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field_email_5t',
                __('Email template, when order status is complete', 'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                        'bonus-for-woo') . '<br> [user]-' . __('Client name',
                        'bonus-for-woo') . '<br>[total]-' . __('Total points for the client',
                        'bonus-for-woo') . '<br>[points]-' . __('Points amount',
                        'bonus-for-woo') . '<br>[order]-' . __('Order',
                        'bonus-for-woo') . '<br>[referral-link]-' . __('Referral link', 'bonus-for-woo'),
                array('BfwAdmin', 'fill_email_5_template'), 'mail_page_bonus', 'bonus_section_id');
        if (BfwRoles::isPro()) {
            add_settings_field('bonus_field_email_5tr',
                    __('Email template when order status is complete from referral.',
                            'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                            'bonus-for-woo') . '<br> [user]-' . __('Client name',
                            'bonus-for-woo') . '<br>[total]-' . __('Total points for the client',
                            'bonus-for-woo') . '<br>[points]-' . __('Points amount',
                            'bonus-for-woo') . '<br>[cause]-' . __('Cause',
                            'bonus-for-woo') . '<br>[referral-link]-' . __('Referral link', 'bonus-for-woo'),
                    array('BfwAdmin', 'fill_email_5_template_ref'), 'mail_page_bonus', 'bonus_section_id');
        }
        add_settings_field('bonus_field_email_5t2',
                __('Email template, when order status is returned', 'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                        'bonus-for-woo') . '<br> [user]-' . __('Client name',
                        'bonus-for-woo') . '<br>[total]-' . __('Total points for the client',
                        'bonus-for-woo') . '<br>[cashback]-' . __('Cashback amount',
                        'bonus-for-woo') . '<br>[points]-' . __('Number of points',
                        'bonus-for-woo') . '<br>[order]-' . __('Order',
                        'bonus-for-woo') . '<br>[referral-link]-' . __('Referral link', 'bonus-for-woo'),
                array('BfwAdmin', 'fill_email_5_template2'), 'mail_page_bonus', 'bonus_section_id');

        add_settings_field('bonus_field_email_6', $trans_e6, array('BfwAdmin', 'fill_email_6'), 'mail_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field_email_6t',
                __('Email template when leaving a product review', 'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                        'bonus-for-woo') . '<br> [user]-' . __('Client name',
                        'bonus-for-woo') . '<br>[total]-' . __('Total points for the client',
                        'bonus-for-woo') . '<br>[points]-' . __('Number of points',
                        'bonus-for-woo') . '<br>[cause]-' . __('Сause',
                        'bonus-for-woo') . '<br>[referral-link]-' . __('Referral link', 'bonus-for-woo'),
                array('BfwAdmin', 'fill_email_6_template'), 'mail_page_bonus', 'bonus_section_id');


        add_settings_field('bonus_field_email_7', $trans_e7, array('BfwAdmin', 'fill_email_7'), 'mail_page_bonus',
                'bonus_section_id');
        add_settings_field('bonus_field_email_7t', __('Template for a letter when a client status changes',
                        'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                        'bonus-for-woo') . '<br> [user]-' . __('Client name',
                        'bonus-for-woo') . '<br>[role]-' . __('User status',
                        'bonus-for-woo') . '<br>[cashback]-' . __('Cashback percentage',
                        'bonus-for-woo') . '<br>[referral-link]-' . __('Referral link', 'bonus-for-woo'),
                array('BfwAdmin', 'fill_email_7_template'), 'mail_page_bonus', 'bonus_section_id');

        if (BfwRoles::isPro()) {
            add_settings_field('bonus_field_email_birthday', $fill_email_on_birthday,
                    array('BfwAdmin', 'fill_email_on_birthday'), 'mail_page_bonus', 'bonus_section_id');


            add_settings_field('bonus_field_email_birthday_text',
                    __('Letter template when points are awarded on a birthday',
                            'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                            'bonus-for-woo') . '<br> [user]-' . __('Client name',
                            'bonus-for-woo') . '<br>[points_for_birthday]-' . __('Points on your birthday',
                            'bonus-for-woo'), array('BfwAdmin', 'fill_email_on_birthday_text'), 'mail_page_bonus',
                    'bonus_section_id');


            add_settings_field('bonus_field_email_on_remove_points', $fill_email_on_remove_points,
                    array('BfwAdmin', 'fill_email_on_remove_points'), 'mail_page_bonus', 'bonus_section_id');

            add_settings_field('bonus_field_email_remove_points_text',
                    __('Letter template for deducting points soon', 'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                            'bonus-for-woo') . '<br> [user]-' . __('Client name',
                            'bonus-for-woo') . '<br>[points]-' . __('Number of points',
                            'bonus-for-woo') . '<br>[days]-' . __('Days before deducting points', 'bonus-for-woo'),
                    array('BfwAdmin', 'fill_email_inactive_notice_text'), 'mail_page_bonus', 'bonus_section_id');


            add_settings_field('bonus_field_email_on_every_day', $fill_email_on_every_day,
                    array('BfwAdmin', 'fill_email_on_every_day'), 'mail_page_bonus', 'bonus_section_id');

            add_settings_field('bonus_field_email_every_day_text',
                    __('Letter template for about earning points for signing in.',
                            'bonus-for-woo') . '<br>' . __('Use shortcodes:',
                            'bonus-for-woo') . '<br> [user]-' . __('Client name',
                            'bonus-for-woo') . '<br>[points]-' . __('Number of points',
                            'bonus-for-woo') . '<br>[total]-' . __('Total points for the client', 'bonus-for-woo'),
                    array('BfwAdmin', 'fill_email_every_day_text'), 'mail_page_bonus', 'bonus_section_id');
        }
        /*emails*/
    }


    /**
     * Проверка на про
     *
     * @return void
     */
    public static function bfw_search_pro(): void
    {
        $p0 = BfwRoles::isPro() ? base64_decode('cHJv') : base64_decode('bmVwcm8=');
        $c1 = array(
                base64_decode('a2V5') => base64_decode('Y2hlY2twcm8='),
                base64_decode('c2l0ZQ==') => get_site_url(),
                base64_decode('cHJv') => $p0
        );
        $k2 = wp_remote_get(base64_decode('aHR0cHM6Ly9jb21wdXR5LnJ1L0FQSS9hcGkucGhwPw==') . http_build_query($c1));
        if (is_wp_error($k2)) {
            return;
        }
        $l3 = wp_remote_retrieve_body($k2);
        $x4 = json_decode($l3, true);
        if (isset($x4[base64_decode('c3RhdHVz')]) && $x4[base64_decode('c3RhdHVz')] !== base64_decode('T0s=')) {
            add_action(base64_decode('YWRtaW5fbm90aWNlcw=='),
                    array(base64_decode('QmZ3QWRtaW4='), base64_decode('YXV0aG9yX2FkbWluX25vdGljZV9iZndwcm8=')));
            update_option(base64_decode('Ym9udXMtZm9yLXdvby1wcm8='), base64_decode('bm9hY3RpdmU='));
        }
    }

    /**
     * @return void
     */
    public static function author_admin_notice_bfwpro(): void
    {
        echo '<div class="notice notice-info is-dismissible">
          <p>' . __('The Pro version of the Bonus for Woo plugin has not been confirmed. Reactivate the Pro version. If you have any difficulties, write to us at info@computy.ru',
                        'bonus-for-woo') . '</p>
         </div>';
    }


    /**
     * Социальные ссылки для рефералов
     *
     * @return void
     */
    public static function fill_primer_field_social(): void
    {
        $checkedvk = BfwSetting::get('ref-social-vk') ? "checked" : "";
        $checkedfb = BfwSetting::get('ref-social-fb') ? "checked" : "";
        $checkedtw = BfwSetting::get('ref-social-tw') ? "checked" : "";
        $checkedtg = BfwSetting::get('ref-social-tg') ? "checked" : "";
        $checkedwhatsapp = BfwSetting::get('ref-social-whatsapp') ? "checked" : "";
        $checkedviber = BfwSetting::get('ref-social-viber') ? "checked" : "";
        $checkedqrcode = BfwSetting::get('ref-social-qrcode') ? "checked" : "";
        ?>
        <input id="ref-social-vk" name="bonus_option_name[ref-social-vk]" type="checkbox"
               value="1" <?php echo esc_attr($checkedvk); ?> >
        <label for="ref-social-vk" class="ref-social ref-social-vk">VK</label>

        <input id="ref-social-fb" name="bonus_option_name[ref-social-fb]" type="checkbox"
               value="1" <?php echo esc_attr($checkedfb); ?> >
        <label for="ref-social-fb" class="ref-social ref-social-fb">FACEBOOK</label>

        <input id="ref-social-tw" name="bonus_option_name[ref-social-tw]" type="checkbox"
               value="1" <?php echo esc_attr($checkedtw); ?> >
        <label for="ref-social-tw" class="ref-social ref-social-tw">TWITTER</label>

        <input id="ref-social-tg" name="bonus_option_name[ref-social-tg]" type="checkbox"
               value="1" <?php echo esc_attr($checkedtg); ?> >
        <label for="ref-social-tg" class="ref-social ref-social-tg">TELEGRAM</label>

        <input id="ref-social-whatsapp" name="bonus_option_name[ref-social-whatsapp]" type="checkbox"
               value="1" <?php echo esc_attr($checkedwhatsapp); ?> >
        <label for="ref-social-whatsapp" class="ref-social ref-social-whatsapp">WHATSAPP</label>

        <input id="ref-social-viber" name="bonus_option_name[ref-social-viber]" type="checkbox"
               value="1" <?php echo esc_attr($checkedviber); ?> >
        <label for="ref-social-viber" class="ref-social ref-social-viber">VIBER</label>

        <input id="ref-social-qrcode" name="bonus_option_name[ref-social-qrcode]" type="checkbox"
               value="1" <?php echo esc_attr($checkedqrcode); ?> >
        <label for="ref-social-qrcode" class="ref-social ref-social-qrcode">QR</label>

    <?php }


    /**
     * Социальные ссылки для рефералов на странице продукта
     *
     * @return void
     */
    public static function fill_primer_field_social_on_page(): void
    {
        $checked = BfwSetting::get('ref-links-on-single-page') ? "checked" : "";
        ?>
        <input name="bonus_option_name[ref-links-on-single-page]" type="checkbox" value="1" <?php echo $checked; ?> >

    <?php }


    /**
     * Поддержка купонов
     *
     * @return void
     */
    public static function fill_primer_coupons(): void
    {
        $checked = BfwSetting::get('coupon-system') ? "checked" : "";
        ?>
        <input id="coupon-system" name="bonus_option_name[coupon-system]" type="checkbox"
               value="1" <?php echo $checked; ?> >

    <?php }


    /**
     * Лимит количества купонов в сутки
     *
     * @return void
     */
    public static function limit_coupon(): void
    {
        $value = BfwSetting::get('quantity-coupon-applied', 1);
        ?>
        <input style="width: 100px" min="1" placeholder="1" step="1" pattern="\d+" type="number"
               name="bonus_option_name[quantity-coupon-applied]"
               value="<?php echo esc_attr($value) ?>"/>
        <?php

    }


    /**
     * Свой метод отправки формы
     *
     * @return void
     */
    public static function fill_email_my_methode(): void
    {
        $checked = BfwSetting::get('email-my-methode') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-my-methode]" type="checkbox" value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Заполняем опцию fill_email_1
     *
     * @return void
     */
    public static function fill_email_1(): void
    { ?>
        <p><?php echo __('In the <a href="/wp-admin/admin.php?page=wc-settings&tab=email" target="_blank">settings woocommerce</a>, it is indicated from whom the letter will be sent. If not specified, the general settings <a href="/wp-admin/options-general.php" target="_blank">from the page</a> will be used. All email design in settings Woocommerce. The notification text is generated automatically.',
                    'bonus-for-woo'); ?>
            <a href="https://computy.ru/blog/docs/bonus-for-woo/shablony/"
               target="_blank"> <?php echo __('Use your own heading templates.', 'bonus-for-woo') ?></a></p>
    <?php }


    /**
     * Отправлять ли письмо при изменениях баллов админом
     *
     * @return void
     */
    public static function fill_email_2(): void
    {
        $checked = BfwSetting::get('email-change-admin') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-change-admin]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Заполняем шаблон при начислении баллов админом
     *
     * @return void
     */
    public static function fill_email_2_template(): void
    {
        $value = BfwSetting::get('email-change-admin-title',
                __('Bonus points have been added to you!', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user]</p>
      <p>' . sprintf(__('%s bonus points have been added to you.', 'bonus-for-woo'), '[points]') . '</p>
      <p>' . __('Cause', 'bonus-for-woo') . ': [cause]</p>
      <p>' . __('The sum of your bonus points is now', 'bonus-for-woo') . ': <b>[total] ' . __('points',
                        'bonus-for-woo') . '</b></p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%"
               placeholder="<?php echo __('Bonus points have been added to you!', 'bonus-for-woo'); ?>" type="text"
               name="bonus_option_name[email-change-admin-title]" value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-change-admin-text', $text_email);
        $editor_id = 'email-change-admin-text';
        $settings = array('media_buttons' => true, 'textarea_name' => 'bonus_option_name[email-change-admin-text]');
        wp_editor($content, $editor_id, $settings);

    }


    /**
     * Заполняем шаблон при списании баллов админом
     *
     * @return void
     */
    public static function fill_email_2_template2(): void
    {
        $value = BfwSetting::get('email-change-admin-title-spisanie', __('Writing off bonus points', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user]</p>
      <p>' . sprintf(__('%s bonus points were deducted from you.', 'bonus-for-woo'), '[points]') . '</p>
      <p>' . __('Cause', 'bonus-for-woo') . ': [cause]</p> 
      <p>' . __('The sum of your bonus points is now', 'bonus-for-woo') . ': <b>[total] ' . __('points',
                        'bonus-for-woo') . '</b></p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%" placeholder="<?php echo __('Writing off bonus points', 'bonus-for-woo'); ?>"
               type="text" name="bonus_option_name[email-change-admin-title-spisanie]"
               value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-change-admin-text-spisanie', $text_email);
        $editor_id = 'email-change-admin-text-spisanie';
        $settings = array(
                'media_buttons' => true,
                'textarea_name' => 'bonus_option_name[email-change-admin-text-spisanie]'
        );
        wp_editor($content, $editor_id, $settings);

    }


    /**
     * Отправлять ли письмо о начислении баллов за регистрацию
     *
     * @return void
     */
    public static function fill_email_3(): void
    {
        $checked = BfwSetting::get('email-when-register') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-when-register]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Заполняем шаблон письма при регистрации
     *
     * @return void
     */
    public static function fill_email_3_template(): void
    {
        $value = BfwSetting::get('email-when-register-title',
                __('Bonus points have been added to you!', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user]</p>
      <p>' . sprintf(__('%s bonus points have been added to you.', 'bonus-for-woo'), '[points]') . '</p>
      <p>' . __('Cause', 'bonus-for-woo') . ': [cause]</p> 
      <p>' . __('The sum of your bonus points is now', 'bonus-for-woo') . ': <b>[total] ' . __('points',
                        'bonus-for-woo') . '</b></p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%"
               placeholder="<?php echo __('Bonus points have been added to you!', 'bonus-for-woo'); ?>" type="text"
               name="bonus_option_name[email-when-register-title]" value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-register-title', $text_email);
        $editor_id = 'email-when-register-text';
        $settings = array('media_buttons' => true, 'textarea_name' => 'bonus_option_name[email-when-register-text]');
        wp_editor($content, $editor_id, $settings);

    }


    /**
     * Отправлять ли письмо о начислении баллов когда заказ подтвержден клиентом
     *
     * @return void
     */
    public static function fill_email_4(): void
    {
        $checked = BfwSetting::get('email-when-order-confirm') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-when-order-confirm]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Заполняем шаблон письма когда заказ подтвержден клиентом
     *
     * @return void
     */
    public static function fill_email_4_template(): void
    {
        $value = BfwSetting::get('email-when-order-confirm-title', __('Writing off bonus points', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user]</p>
      <p>' . sprintf(__('You used %s bonus points to pay for order number %s.', 'bonus-for-woo'), '[points]',
                        '[order]') . '</p>
      <p>' . __('Cause', 'bonus-for-woo') . ': [cause]</p> 
      <p>' . __('The sum of your bonus points is now', 'bonus-for-woo') . ': <b>[total] ' . __('points',
                        'bonus-for-woo') . '</b></p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%"
               placeholder="<?php echo __('Bonus points have been added to you!', 'bonus-for-woo'); ?>" type="text"
               name="bonus_option_name[email-when-order-confirm-title]" value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-order-confirm-text', $text_email);
        $editor_id = 'email-when-order-confirm-text';
        $settings = array(
                'media_buttons' => true,
                'textarea_name' => 'bonus_option_name[email-when-order-confirm-text]'
        );
        wp_editor($content, $editor_id, $settings);

    }


    /**
     * Отправлять ли письмо когда поменялся статус заказа
     *
     * @return void
     */
    public static function fill_email_5(): void
    {
        $checked = BfwSetting::get('email-when-order-change') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-when-order-change]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Заполняем шаблон при начислении баллов когда заказ оплачен
     *
     * @return void
     */
    public static function fill_email_5_template(): void
    {
        $value = BfwSetting::get('email-when-order-change-title', __('Points accrual', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user]</p>
      <p>' . sprintf(__('You have accrued %s bonus points from order number %s.', 'bonus-for-woo'), '[points]',
                        '[order]') . '</p>
      <p>' . __('The sum of your bonus points is now', 'bonus-for-woo') . ': <b>[total] ' . __('points',
                        'bonus-for-woo') . '</b></p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%"
               placeholder="<?php echo __('Bonus points have been added to you!', 'bonus-for-woo'); ?>" type="text"
               name="bonus_option_name[email-when-order-change-title]" value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-order-change-text', $text_email);
        $editor_id = 'email-when-order-change-text';
        $settings = array(
                'media_buttons' => true,
                'textarea_name' => 'bonus_option_name[email-when-order-change-text]'
        );
        wp_editor($content, $editor_id, $settings);
    }


    /**
     * Заполняем шаблон при начислении баллов когда заказ оплачен рефералом
     *
     * @return void
     */
    public static function fill_email_5_template_ref(): void
    {
        $value = BfwSetting::get('email-when-order-change-referal-title', __('Points accrual', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user]</p>
      <p>' . sprintf(__('You have been credited with %s bonus points.', 'bonus-for-woo'), '[points]') . '</p>
      <p>' . __('Cause', 'bonus-for-woo') . ': [cause]</p>
      <p>' . __('The sum of your bonus points is now', 'bonus-for-woo') . ': <b>[total] ' . __('points',
                        'bonus-for-woo') . '</b></p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%" placeholder="<?php echo __('Points accrual', 'bonus-for-woo'); ?>" type="text"
               name="bonus_option_name[email-when-order-change-referal-title]" value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-order-change-referal-text', $text_email);
        $editor_id = 'email-when-order-change-referal-text';
        $settings = array(
                'media_buttons' => true,
                'textarea_name' => 'bonus_option_name[email-when-order-change-referal-text]'
        );
        wp_editor($content, $editor_id, $settings);
    }


    /**
     * Заполняем шаблон при начислении баллов когда заказ возвращен
     *
     * @return void
     */
    public static function fill_email_5_template2(): void
    {
        $value = BfwSetting::get('email-when-order-change-title-vozvrat',
                __('Refund of bonus points', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user]</p>
      <p>' . sprintf(__('The %1$s bonus points you earned for order no. %2$s have been canceled.', 'bonus-for-woo'),
                        '[cashback]', '[order]') . '</p>
      <p>' . sprintf(__('You have returned %1$s bonus points for order number %2$s.', 'bonus-for-woo'), '[points]',
                        '[order]') . '</p>
      <p>' . __('The sum of your bonus points is now', 'bonus-for-woo') . ': <b>[total] ' . __('points',
                        'bonus-for-woo') . '</b></p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%"
               placeholder="<?php echo __('Bonus points have been added to you!', 'bonus-for-woo'); ?>" type="text"
               name="bonus_option_name[email-when-order-change-title-vozvrat]" value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-order-change-text-vozvrat', $text_email);
        $editor_id = 'email-when-order-change-text-vozvrat';
        $settings = array(
                'media_buttons' => true,
                'textarea_name' => 'bonus_option_name[email-when-order-change-text-vozvrat]'
        );
        wp_editor($content, $editor_id, $settings);
    }


    /**
     * Заполняем опцию fill_email_6
     *
     * @return void
     */
    public static function fill_email_6(): void
    {
        $checked = BfwSetting::get('email-when-review') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-when-review]" type="checkbox" value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Заполняем шаблон при начислении баллов когда одобрен отзыв
     *
     * @return void
     */
    public static function fill_email_6_template(): void
    {
        $value = BfwSetting::get('email-when-review-title', __('Points accrual', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user]</p>
      <p>' . sprintf(__('You have received %s bonus points for a product review.', 'bonus-for-woo'), '[points]') . '</p>
      <p>' . __('Cause', 'bonus-for-woo') . ': [cause]</p>
      <p>' . __('The sum of your bonus points is now', 'bonus-for-woo') . ': <b>[total] ' . __('points',
                        'bonus-for-woo') . '</b></p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%"
               placeholder="<?php echo __('Bonus points have been added to you!', 'bonus-for-woo'); ?>" type="text"
               name="bonus_option_name[email-when-review-title]" value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-review-text', $text_email);
        $editor_id = 'email-when-review-text';
        $settings = array('media_buttons' => true, 'textarea_name' => 'bonus_option_name[email-when-review-text]');
        wp_editor($content, $editor_id, $settings);

    }


    /**
     * Заполняем опцию fill_email_7
     *
     * @return void
     */
    public static function fill_email_7(): void
    {
        $checked = BfwSetting::get('email-when-status-chenge') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-when-status-chenge]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Шаблон письма, когда меняется статус
     *
     * @return void
     */
    public static function fill_email_7_template(): void
    {
        $value = BfwSetting::get('email-when-status-chenge-title', __('Changing your status', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user]</p>
      <p>' . sprintf(__('Now your status is "%s".', 'bonus-for-woo'), '[role]') . '</p> 
      <p>' . __('Now the percentage of cashback:', 'bonus-for-woo') . ' [cashback]%</p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%" placeholder="<?php echo __('Changing your status', 'bonus-for-woo'); ?>" type="text"
               name="bonus_option_name[email-when-status-chenge-title]" value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-status-chenge-text', $text_email);
        $editor_id = 'email-when-status-chenge-text';
        $settings = array(
                'media_buttons' => true,
                'textarea_name' => 'bonus_option_name[email-when-status-chenge-text]'
        );
        wp_editor($content, $editor_id, $settings);

    }


    /**
     * Отправлять ли письмо о начислении балов за др
     *
     * @return void
     */
    public static function fill_email_on_birthday(): void
    {
        $checked = BfwSetting::get('email-when-birthday') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-when-birthday]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Шаблон письма о начислении балов за др
     *
     * @return void
     */
    public static function fill_email_on_birthday_text(): void
    {
        $value = BfwSetting::get('email-whens-birthday-title', __('Bonus points on your birthday', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user].</p>
       
      <p>' . __('Happy birthday and give you bonus points:', 'bonus-for-woo') . ' [points_for_birthday]</p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%" placeholder="<?php echo __('Bonus points on your birthday', 'bonus-for-woo'); ?>"
               type="text" name="bonus_option_name[email-whens-birthday-title]" value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-birthday-text', $text_email);
        $editor_id = 'email-when-birthday-text';
        $settings = array('media_buttons' => true, 'textarea_name' => 'bonus_option_name[email-when-birthday-text]');
        wp_editor($content, $editor_id, $settings);

    }


    /**
     * Отправлять ли письмо о скором сгорании баллов
     *
     * @return void
     */
    public static function fill_email_on_remove_points(): void
    {
        $checked = BfwSetting::get('email-when-inactive-notice') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-when-inactive-notice]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Шаблон письма о скором сгорании баллов
     *
     * @return void
     */
    public static function fill_email_inactive_notice_text(): void
    {
        $value = BfwSetting::get('email-when-inactive-notice-title',
                __('Your points will be deleted soon', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user].</p>
       <p>' . sprintf(__('Your points will be deleted after %s days.', 'bonus-for-woo'), '[days]') . '</p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%" placeholder="<?php echo __('Your points will be deleted soon', 'bonus-for-woo'); ?>"
               type="text" name="bonus_option_name[email-when-inactive-notice-title]"
               value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-inactive-notice-text', $text_email);
        $editor_id = 'email-when-inactive-notice-text';
        $settings = array(
                'media_buttons' => true,
                'textarea_name' => 'bonus_option_name[email-when-inactive-notice-text]'
        );
        wp_editor($content, $editor_id, $settings);

    }


    /**
     * Отправлять ли письмо о ежедневном начислении баллов
     *
     * @return void
     */
    public static function fill_email_on_every_day(): void
    {
        $checked = BfwSetting::get('email-when-everyday-login') ? "checked" : "";
        ?>
        <input name="bonus_option_name[email-when-everyday-login]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Шаблон письма о ежедневном начислении баллов
     *
     * @return void
     */
    public static function fill_email_every_day_text(): void
    {
        $value = BfwSetting::get('email-when-everyday-login-title',
                __('Bonus points have been added to you!', 'bonus-for-woo'));

        $text_email = '<p>' . __('Hello', 'bonus-for-woo') . ', [user].</p>
       <p>' . sprintf(__('You get %s points for logging into your account.  ', 'bonus-for-woo'), '[points]') . '</p>
        <p>' . __('The sum of your bonus points is now', 'bonus-for-woo') . ': <b>[total] ' . __('points',
                        'bonus-for-woo') . '</b></p>';
        ?>
        <div class="label-input-mail"><?php echo __('Email header', 'bonus-for-woo'); ?></div>
        <input style="width:100%"
               placeholder="<?php echo __('Bonus points have been added to you!', 'bonus-for-woo'); ?>"
               type="text" name="bonus_option_name[email-when-everyday-login-title]"
               value="<?php echo esc_attr($value) ?>"/>
        <div class="label-editor-mail"><?php echo __('Letter template', 'bonus-for-woo'); ?></div>
        <?php
        $content = BfwSetting::get('email-when-everyday-login-text', $text_email);
        $editor_id = 'email-when-everyday-login-text';
        $settings = array(
                'media_buttons' => true,
                'textarea_name' => 'bonus_option_name[email-when-everyday-login-text]'
        );
        wp_editor($content, $editor_id, $settings);

    }


    /**
     * Заполняем опцию 166
     *
     * @return void
     */
    public static function fill_primer_field166(): void
    { ?>
        <hr>
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field12(): void
    {
        $checked = BfwSetting::get('bonus-in-price') ? "checked" : "";
        ?>
        <input name="bonus_option_name[bonus-in-price]" type="checkbox" value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field12b(): void
    {
        $checked = BfwSetting::get('bonus-in-price-loop') ? "checked" : "";
        ?>
        <input name="bonus_option_name[bonus-in-price-loop]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Заполняем опцию upto
     *
     * @return void
     */
    public static function fill_primer_field12c(): void
    {
        $checked = BfwSetting::get('bonus-in-price-upto') ? "checked" : "";
        ?>
        <input name="bonus_option_name[bonus-in-price-upto]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field12a(): void
    {
        $checked = BfwSetting::get('cashback-in-cart') ? "checked" : "";
        ?>
        <input name="bonus_option_name[cashback-in-cart]" type="checkbox" value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Списание баллов в оформлении заказа
     *
     * @return void
     */
    public static function fill_primer_field14(): void
    {
        $checked = BfwSetting::get('spisanie-in-checkout') ? "checked" : "";
        ?>
        <input name="bonus_option_name[spisanie-in-checkout]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * Скрыть возможность потратить баллы для товаров со скидкой?
     *
     * @return void
     */
    public static function fill_primer_field15(): void
    {
        $checked = BfwSetting::get('spisanie-onsale') ? "checked" : "";
        ?>
        <input name="bonus_option_name[spisanie-onsale]" type="checkbox" value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * С какой даты начинать считать заказы
     *
     * @return void
     */
    public static function fill_order_start_date(): void
    {
        $value = BfwSetting::get('order_start_date', '');
        ?>
        <input type="date" name="bonus_option_name[order_start_date]"
               value="<?php echo esc_attr($value) ?>"/>
        <small style="color:#999999"><?php ?></small>
    <?php }


    /**
     * Баллы за день рождение
     *
     * @return void
     */
    public static function fill_birthday(): void
    {
        $val1 = BfwSetting::get('birthday', 0);
        $hmb = BfwSetting::get('how-many-birthday', 0);
        ?>

        <input style="width: 60px" type="text" name="bonus_option_name[birthday]"
               value="<?php echo esc_attr($val1) ?>"/>
        <small style="color:#999999"><?php echo BfwPoints::pointsLabel(esc_attr($val1)); ?>.</small>
        <small style="color:#999999"><?php echo __('Accrue for', 'bonus-for-woo') ?></small>
        <input style="width: 60px" type="number" name="bonus_option_name[how-many-birthday]"
               value="<?php echo esc_attr($hmb) ?>"/>
        <small style="color:#999999"><?php echo __('days', 'bonus-for-woo') ?>.</small>
    <?php }


    /**
     * Ежедневные баллы за первый вход
     *
     * @return void
     */
    public static function every_days(): void
    {
        $val = BfwSetting::get('every_days', 0);
        ?>

        <input style="width: 60px" type="text" name="bonus_option_name[every_days]"
               value="<?php echo esc_attr($val) ?>"/>
        <small style="color:#999999"><?php echo BfwPoints::pointsLabel(esc_attr($val)); ?></small>
    <?php }

    /**
     * кешбэк за первый заказ
     *
     * @return void
     */
    public static function cashback_for_first_order(): void
    {
        $val = BfwSetting::get('cashback_for_first_order', 0);
        ?>
        <input style="width: 60px" type="number" min="0" max="100" name="bonus_option_name[cashback_for_first_order]"
               value="<?php echo esc_attr($val) ?>">
        <small style="color:#999999">%</small>
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field16(): void
    {
        $val = BfwSetting::get('max-percent-bonuses', 100);
        ?>

        <input style="width: 60px" type="number" min="0" max="100" name="bonus_option_name[max-percent-bonuses]"
               value="<?php echo esc_attr($val) ?>"/>
        <small style="color:#999999"><?php echo __('%', 'bonus-for-woo') ?></small>
    <?php }


    /**
     * Категории, которые надо исключить
     *
     * @return void
     */
    public static function fill_primer_field17(): void
    {
        ?>
        <select multiple="multiple" id="exclude-category" name="bonus_option_name[exclude-category-cashback][]">
            <?php

            $get_categories_product = get_terms([
                    'taxonomy' => 'product_cat',
                    "orderby" => "name", // Тип сортировки
                    "order" => "ASC", // Направление сортировки
                    "hide_empty" => 0, // Скрывать пустые. 1 - да, 0 - нет.
            ]);
            foreach ($get_categories_product as $cat_item) {
                $selected = in_array($cat_item->term_id,
                        BfwSetting::get('exclude-category-cashback', array())) ? ' selected="selected" ' : '';

                echo "<option " . $selected . " value='" . esc_attr($cat_item->term_id) . "'>" . esc_html($cat_item->name) . "</option>";
            }
            ?>
        </select>

        <?php
    }


    /**
     * Исключаем метод оплаты из бонусной системы
     *
     * @return void
     */
    public static function fill_primer_field_pay_method(): void
    {
        ?>
        <select multiple="multiple" id="exclude-payment-method" name="bonus_option_name[exclude-payment-method][]">
            <option disabled><?php echo __('Select a Payment Method', 'bonus-for-woo'); ?></option>
            <?php

            $gateways = WC()->payment_gateways->payment_gateways();
            $options = array();

            $options = array_map(function ($gateway) {
                return $gateway->get_method_title();
            }, $gateways);

            foreach ($options as $payment_id => $method_title) {
                $selected = in_array($payment_id,
                        BfwSetting::get('exclude-payment-method', array())) ? ' selected="selected" ' : '';

                $option = '<option value="' . $payment_id . '" ';
                $option .= $selected;
                $option .= '>';
                $option .= $method_title;
                $option .= '</option>';
                echo $option;
            }

            ?>
        </select>

        <?php
    }


    /**
     * @return void
     */
    public static function fill_primer_field18(): void
    {
        $value = BfwSetting::get('exclude-payment-method', '');
        ?>
        <input style="width: 250px" placeholder="3124,524,231" type="text"
               name="bonus_option_name[exclude-tovar-cashback]" value="<?php echo esc_attr($value) ?>"/>
        <?php
    }


    /**
     * @return void
     */
    public static function fill_buy_balls(): void
    {
        $value = BfwSetting::get('buy_balls-cashback', '');
        ?>
        <input style="width: 250px" placeholder="2374" type="text" name="bonus_option_name[buy_balls-cashback]"
               value="<?php echo esc_attr($value) ?>"/>
        <small style="color:grey">* <?php echo __('Only for logged in clients.', 'bonus-for-woo') ?>
        </small>  <?php
    }


    /**
     * @return void
     */
    public static function fill_primer_field19(): void
    {
        $valpr = BfwSetting::get('points-for-registration', 0);
        ?>
        <input style="width: 80px" placeholder="50" type="number" min="0"
               name="bonus_option_name[points-for-registration]" value="<?php echo esc_attr($valpr) ?>"/>

        <?php

        if (BfwSetting::get('referal-system')) {
            $checkedr = BfwSetting::get('register-points-only-referal') ? "checked" : "";
            ?>
            <input name="bonus_option_name[register-points-only-referal]" type="checkbox"
                   value="1" <?php echo esc_attr($checkedr); ?> >
            <small style="color:grey">* <?php echo __('Add points only to the referral.', 'bonus-for-woo') ?>
            </small>
            <?php
        }
    }


    /**
     * не начислять кешбэк если клиент использует баллы
     *
     * @return void
     */
    public static function yous_balls_no_cashback_fild(): void
    {
        $checked = BfwSetting::get('yous_balls_no_cashback') ? "checked" : "";
        ?>
        <input name="bonus_option_name[yous_balls_no_cashback]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * не начислять кешбэк если клиент использует купон
     *
     * @return void
     */
    public static function yous_coupon_no_cashback_fild(): void
    {
        $checked = BfwSetting::get('yous_coupon_no_cashback') ? "checked" : "";
        ?>
        <input name="bonus_option_name[yous_coupon_no_cashback]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field20(): void
    {
        $checked = BfwSetting::get('addkeshback-exclude') ? "checked" : "";
        ?>
        <input name="bonus_option_name[addkeshback-exclude]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
        <small style="color:grey">* <?php echo __('Yes, accrue', 'bonus-for-woo'); ?>
        </small>

    <?php }


    /**
     * не начислять кешбэк на товары со скидкой
     *
     * @return void
     */
    public static function fill_cashback_on_sale_products(): void
    {
        $checked = BfwSetting::get('cashback-on-sale-products') ? "checked" : "";
        ?>
        <input name="bonus_option_name[cashback-on-sale-products]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * Очищать баллы, которые хочет списать клиент при изменении количества товаров корзине
     *
     * @return void
     */
    public static function fill_primer_field21qty(): void
    {
        $checked = BfwSetting::get('clear-fast-bonus-were-qty-cart') ? "checked" : "";
        ?>
        <input name="bonus_option_name[clear-fast-bonus-were-qty-cart]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * Удаление баллов за бездействие
     *
     * @return void
     */
    public static function fill_inactive(): void
    {
        $value = BfwSetting::get('day-inactive', 0);
        ?>
        <input style="width: 100px" placeholder="365" step="1" pattern="\d+" type="number"
               name="bonus_option_name[day-inactive]" min="0"
               value="<?php echo esc_attr($value) ?>"/> <?php echo __('days without accrual or debit of points',
            'bonus-for-woo') ?>
        <?php

    }


    /**
     * Уведомление об удалении баллов за бездействие
     *
     * @return void
     */
    public static function fill_inactive_notice(): void
    {
        $value = BfwSetting::get('day-inactive-notice', 0);
        ?>
        <input style="width: 100px" placeholder="0" step="1" pattern="\d+" type="number"
               name="bonus_option_name[day-inactive-notice]" min="0"
               value="<?php echo esc_attr($value) ?>"/> <?php echo __('Days before deducting points',
            'bonus-for-woo') ?>
        <?php
    }


    /**
     * Скрыть сколько дней осталось до списания баллов
     *
     * @return void
     */
    public static function fill_burn_point_in_account(): void
    {
        $checked = BfwSetting::get('burn_point_in_account') ? "checked" : "";
        ?>
        <input name="bonus_option_name[burn_point_in_account]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * Реферальная система
     *
     * @return void
     */
    public static function fill_primer_field21(): void
    {
        $checked = BfwSetting::get('referal-system') ? "checked" : "";
        ?>
        <input id="referal-system" name="bonus_option_name[referal-system]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
        <?php if (!BfwSetting::get('referal-system')) {
        echo __('When the referral system is activated, additional settings will appear.', 'bonus-for-woo');
    }
    }


    /**
     * Начислять баллы только за 1 заказ реферала
     *
     * @return void
     */
    public static function fill_primer_field27(): void
    {
        $checked = BfwSetting::get('first-order-referal') ? "checked" : "";
        ?>
        <input name="bonus_option_name[first-order-referal]" type="checkbox"
               value="1" <?php echo esc_attr($checked) ?> >

    <?php }


    /**
     *  Второй уровень реферала
     *
     * @return void
     */
    public static function fill_level_two(): void
    {
        $checked = BfwSetting::get('level-two-referral') ? "checked" : "";
        ?>
        <input name="bonus_option_name[level-two-referral]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * кешбэк за инвайта первого уровня
     *
     * @return void
     */
    public static function fill_primer_field26(): void
    {
        $val = BfwSetting::get('referal-cashback', 0);
        ?>

        <input style="width: 60px" placeholder="0" min="0" max="100" type="number"
               name="bonus_option_name[referal-cashback]" value="<?php echo esc_attr($val) ?>"/>
        <small style="color:#999999">%</small>
    <?php }


    /**
     * кешбэк за инвайта второго уровня
     *
     * @return void
     */
    public static function fill_referal_cashback_two_level(): void
    {
        $val = BfwSetting::get('referal-cashback-two-level', 0);
        ?>

        <input style="width: 60px" placeholder="0" min="0" max="100" type="number"
               name="bonus_option_name[referal-cashback-two-level]" value="<?php echo esc_attr($val) ?>"/>
        <small style="color:#999999">%</small>
    <?php }


    /**
     * Сумма заказов, после которой клиенту станет доступна реферальная система.
     *
     * @return void
     */
    public static function fill_primer_field29(): void
    {
        $val = BfwSetting::get('sum-orders-for-referral', 0);
        ?>
        <input style="width: 80px" placeholder="0" type="text"
               name="bonus_option_name[sum-orders-for-referral]"
               value="<?php echo esc_attr($val) ?>"/> <?php echo get_woocommerce_currency_symbol(); ?>

    <?php }


    /**
     * Минимальная сумма заказа
     *
     * @return void
     */
    public static function fill_primer_field25(): void
    {
        $valas = BfwSetting::get('minimal-amount', 0);
        ?>
        <input style="width: 80px" placeholder="50" min="0" type="number" name="bonus_option_name[minimal-amount]"
               value="<?php echo esc_attr($valas) ?>"/> <?php echo get_woocommerce_currency_symbol();

        $checkedmin = BfwSetting::get('minimal-amount-cashback') ? "checked" : "";

        ?>
        <input name="bonus_option_name[minimal-amount-cashback]" type="checkbox"
               value="1" <?php echo esc_attr($checkedmin); ?> >
        <small style="color:grey">* <?php echo __('Valid for cashback', 'bonus-for-woo'); ?>
        </small>
        <?php
    }


    /**
     * Исключить роли
     *
     * @return void
     */
    public static function fill_primer_field22(): void
    {
        ?>
        <select multiple="multiple" id="exclude-role" name="bonus_option_name[exclude-role][]">
            <?php
            foreach (get_editable_roles() as $role => $details) {
                $name = translate_user_role($details['name']);
                $selected = in_array($role, BfwSetting::get('exclude-role', array())) ? ' selected="selected" ' : '';
                echo "<option " . $selected . " value='" . esc_attr($role) . "'>$name</option>";
            }
            ?>
        </select>
        <?php
    }


    /**
     * Ссылка на правила и условия
     *
     * @return void
     */
    public static function fill_rulles(): void
    {
        $rulles_value = BfwSetting::get('rulles_value', __('Terms & Conditions', 'bonus-for-woo'));
        $rulles_url = BfwSetting::get('rulles_url', '');
        ?>
        <input name="bonus_option_name[rulles_value]" type="text" value="<?php echo esc_attr($rulles_value); ?>"
               placeholder="<?php echo esc_attr($rulles_value); ?>">
        <input name="bonus_option_name[rulles_url]" type="text" value="<?php echo esc_attr($rulles_url); ?>"
               placeholder="https://">
        <?php
    }


    /**
     * Do not round decimals in points?
     * Не округлять десятичные дроби в баллах?
     *
     * @return void
     */
    public static function fill_round_points(): void
    {
        $checked = BfwSetting::get('round_points') ? "checked" : "";
        ?>
        <input name="bonus_option_name[round_points]" type="checkbox" value="1" <?php echo esc_attr($checked); ?> >
        <small style="color: red"><?php echo __('If selected, points will not be rounded.', 'bonus-for-woo'); ?></small>
        <?php
    }


    /**
     * При каком статусе списывать баллы
     *
     * @return void
     */
    public static function fill_write_points_order_status(): void
    {
        $write_points_order_status = BfwSetting::get('write_points_order_status', 'processed');
        ?>
        <select id="write_points_order_status" name="bonus_option_name[write_points_order_status]">
            <?php
            $default_selected = ($write_points_order_status === 'processed') ? 'selected="selected"' : '';
            echo '<option value="processed" ' . $default_selected . '>' . __('At the time of order confirmation',
                            'bonus-for-woo') . '</option>';

            foreach (wc_get_order_statuses() as $status => $status_name) {
                $status_value = mb_substr($status, 3);
                $selected = ($write_points_order_status === $status_value) ? 'selected="selected"' : '';
                printf('<option value="%s" %s>%s</option>', esc_attr($status_value), $selected, esc_html($status_name));
            }
            ?>
        </select>
        <?php
    }


    /**
     * При каком статусе заказа могут начисляться баллы
     *
     * @return void
     */
    public static function fill_add_points_order_status(): void
    {
        $add_points_order_status = BfwSetting::get('add_points_order_status', 'completed');
        $statuses = wc_get_order_statuses();

        ?>
        <select id="add_points_order_status" name="bonus_option_name[add_points_order_status]">
            <?php
            foreach ($statuses as $status => $status_name) {
                $status_value = mb_substr($status, 3);
                $selected = ($add_points_order_status === $status_value) ? 'selected="selected"' : '';
                printf('<option value="%s" %s>%s</option>', esc_attr($status_value), $selected, esc_html($status_name));
            }
            ?>
        </select>
        <?php
        if (BfwRoles::isPro()) {
            $daily_cashback_check = BfwSetting::get('daily_cashback_check', 0);
            echo __('through', 'bonus-for-woo'); ?> <input value="<?php echo $daily_cashback_check; ?>"
                                                           name="bonus_option_name[daily_cashback_check]" type="number"
                                                           style="width: 80px">
            <?php echo __('days', 'bonus-for-woo');
        } ?>


        <?php
    }


    /**
     * Возврат баллов по указанному статусу заказа
     *
     * @return void
     */
    public static function fill_refunded_points_order_status(): void
    {
        $refunded_points_order_status = BfwSetting::get('refunded_points_order_status', 'refunded');
        ?>
        <select id="refunded_points_order_status" name="bonus_option_name[refunded_points_order_status]">
            <?php
            foreach (wc_get_order_statuses() as $status => $status_name) {
                $short_status = mb_substr($status, 3);
                $selected = ($refunded_points_order_status == $short_status) ? 'selected="selected"' : '';
                echo '<option value="' . esc_attr($short_status) . '" ' . $selected . '>' . esc_html($status_name) . '</option>';
            }
            ?>
        </select>
        <?php
    }


    /**
     * Название баллов со склонениями
     *
     * @return void
     */
    public static function name_points(): void
    {
        $label_point = BfwSetting::get('label_point', __('Point', 'bonus-for-woo'));
        $label_point_two = BfwSetting::get('label_point_two', __('Points', 'bonus-for-woo'));
        $label_points = BfwSetting::get('label_points', __('Points', 'bonus-for-woo'));
        ?>
        1 <input style="width: 80px" type="text" name="bonus_option_name[label_point]"
                 value="<?php echo esc_attr($label_point) ?>"/>,
        2 <input style="width: 80px" type="text" name="bonus_option_name[label_point_two]"
                 value="<?php echo esc_attr($label_point_two) ?>"/>,
        25 <input style="width: 80px" type="text" name="bonus_option_name[label_points]"
                  value="<?php echo esc_attr($label_points) ?>"/>
        <?php
    }


    /**
     * Количество бонусов за отзыв
     *
     * @return void
     */
    public static function fill_primer_field1(): void
    {
        $val = BfwSetting::get('bonus-for-otziv', 0);
        ?>

        <input style="width: 60px" type="number" min="0" step="any" name="bonus_option_name[bonus-for-otziv]"
               value="<?php echo esc_attr($val) ?>"/> <?php echo BfwPoints::pointsLabel($val); ?>
    <?php }


    /**
     * Заполняем опцию 30 порядок меню в аккаунте
     *
     * @return void
     */
    public static function fill_primer_field30(): void
    {
        $value = BfwSetting::get('poryadok-in-account', 4);
        ?>

        <input style="width: 50px" min="1" max="10" type="number" name="bonus_option_name[poryadok-in-account]"
               value="<?php echo esc_attr($value) ?>"/>
    <?php }


    /**
     * Не тратить баллы, если применяется купон?
     *
     * @return void
     */
    public static function fill_primer_field23(): void
    {
        $checked = BfwSetting::get('balls-and-coupon') ? "checked" : "";
        ?>
        <input id="balls-and-coupon" name="bonus_option_name[balls-and-coupon]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * не учитывать купоны и скидки при подсчете кешбэке
     *
     * @return void
     */
    public static function exclude_fees_coupons(): void
    {
        $checked = BfwSetting::get('exclude-fees-coupons') ? "checked" : "";
        ?>
        <input id="exclude-fees-coupons" name="bonus_option_name[exclude-fees-coupons]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Снять кешбэк за доставку?
     *
     * @return void
     */
    public static function fill_primer_field24(): void
    {
        $checked = BfwSetting::get('cashback-for-shipping') ? "checked" : "";
        ?>
        <input name="bonus_option_name[cashback-for-shipping]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }

    /**
     * Не учитывать доставку в расчете суммы заказов.
     *
     * @return void
     */
    public static function fill_shipping_total_sum(): void
    {
        $checked = BfwSetting::get('shipping-total-sum') ? "checked" : "";
        ?>
        <input name="bonus_option_name[shipping-total-sum]" type="checkbox"
               value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * Удалите следы плагина при удалении плагина.
     *
     * @return void
     */
    public static function fill_primer_clear(): void
    {
        $checked = BfwSetting::get('clear-bfw-bd') ? "checked" : "";
        ?>
        <input name="bonus_option_name[clear-bfw-bd]" type="checkbox" value="1" <?php echo esc_attr($checked); ?> >

    <?php }


    /**
     * Скрыть историю начисления баллов
     *
     * @return void
     */
    public static function fill_primer_field28(): void
    {
        $checked = BfwSetting::get('hystory-hide') ? "checked" : "";
        ?>
        <input name="bonus_option_name[hystory-hide]" type="checkbox" value="1" <?php echo esc_attr($checked); ?> >
    <?php }


    /**
     * Заголовок на странице аккаунта
     *
     * @return void
     */
    public static function fill_primer_field2(): void
    {
        $value = BfwSetting::get('title-on-account', __('Bonus page', 'bonus-for-woo'));
        ?>
        <input style="width: 350px" type="text" name="bonus_option_name[title-on-account]"
               value="<?php echo esc_attr($value) ?>" placeholder="<?php echo __('Bonus page', 'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field2h(): void
    {
        $title_history = BfwSetting::get('title-on-history-account', __('Points accrual', 'bonus-for-woo'));
        ?>
        <input style="width: 350px" type="text" name="bonus_option_name[title-on-history-account]"
               value="<?php echo esc_attr($title_history) ?>"
               placeholder="<?php echo __('Points accrual', 'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * Заголовок статуса в аккаунте
     *
     * @return void
     */
    public static function fill_primer_field4(): void
    {
        $value = BfwSetting::get('title-my-status-on-account', __('My status', 'bonus-for-woo'));
        ?>
        <input style="width: 350px" type="text" name="bonus_option_name[title-my-status-on-account]"
               value="<?php echo esc_attr($value) ?>" placeholder="<?php echo __('My status', 'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * Заголовок "Мой процент кэшбека"
     *
     * @return void
     */
    public static function fill_primer_field5(): void
    {
        $value = BfwSetting::get('my-procent-on-account', __('My cashback percentage', 'bonus-for-woo'));
        ?>
        <input style="width: 350px" type="text" name="bonus_option_name[my-procent-on-account]"
               value="<?php echo esc_attr($value) ?>"
               placeholder="<?php echo __('My cashback percentage', 'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field11(): void
    {
        $value = BfwSetting::get('remaining-amount',
                __('Up to [percent]% cashback and «[status]» status, you have [sum] left to spend.', 'bonus-for-woo'));
        ?>
        <input style="width: 400px" type="text" name="bonus_option_name[remaining-amount]"
               value="<?php echo esc_attr($value) ?>"
               placeholder="<?php echo __('Up to [percent]% cashback and «[status]» status, you have [sum] left to spend.',
                       'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field7(): void
    {
        $value = BfwSetting::get('bonustext-in-cart', __('You can use [points] in this order.', 'bonus-for-woo'));
        $value4 = BfwSetting::get('bonustext-in-cart4', __('Apply', 'bonus-for-woo'));
        ?>
        <input style="width: 400px" type="text" name="bonus_option_name[bonustext-in-cart]"
               value="<?php echo esc_attr($value) ?>"
               placeholder="<?php echo __('You can use [points] in this order.', 'bonus-for-woo'); ?>"/>
        <input style="width: 170px" type="text" name="bonus_option_name[bonustext-in-cart4]"
               value="<?php echo esc_attr($value4) ?>" placeholder="<?php echo __('Apply', 'bonus-for-woo'); ?>"/>
    <?php }

    public static function fill_primer_field75(): void
    {
        $value5 = BfwSetting::get('bonustext-in-cart5', __('Total', 'bonus-for-woo'));
        ?>
        <input style="width: 170px" type="text" name="bonus_option_name[bonustext-in-cart5]"
               value="<?php echo esc_attr($value5) ?>" placeholder="<?php echo __('Total', 'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field8(): void
    {
        $value = BfwSetting::get('use-points-on-cart', __('Use points', 'bonus-for-woo'));
        ?>
        <input style="width: 350px" type="text" name="bonus_option_name[use-points-on-cart]"
               value="<?php echo esc_attr($value) ?>" placeholder="<?php echo __('Use points', 'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field9(): void
    {
        $value = BfwSetting::get('bonus-points-on-cart', __('Bonus points', 'bonus-for-woo'));
        ?>
        <input style="width: 350px" type="text" name="bonus_option_name[bonus-points-on-cart]"
               value="<?php echo esc_attr($value) ?>"/>
    <?php }


    /**
     * @return void
     */
    public static function fill_primer_field10(): void
    {
        $value = BfwSetting::get('remove-on-cart', __('Remove points', 'bonus-for-woo'));
        ?>
        <input style="width: 350px" type="text" name="bonus_option_name[remove-on-cart]"
               value="<?php echo esc_attr($value) ?>"
               placeholder="<?php echo __('Remove points', 'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * Название офлайн-продукта в заказе покупателя
     *
     * @return void
     */
    public static function fill_title_product_offline_order(): void
    {
        $value = BfwSetting::get('title-product-offline-order', __('Offline product', 'bonus-for-woo'));
        ?>
        <input style="width: 350px" type="text" name="bonus_option_name[title-product-offline-order]"
               value="<?php echo esc_attr($value) ?>"
               placeholder="<?php echo __('Offline product', 'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * Название строчки "Кешбэк" в карточке товара
     *
     * @return void
     */
    public static function fill_title_how_match_bonus(): void
    {
        $value = BfwSetting::get('how-mach-bonus-title', __('Cashback:', 'bonus-for-woo'));
        ?>
        <input style="width: 350px" type="text" name="bonus_option_name[how-mach-bonus-title]"
               value="<?php echo esc_attr($value) ?>" placeholder="<?php echo __('Cashback:', 'bonus-for-woo'); ?>"/>
    <?php }


    /**
     * Очистка данных
     *
     * @param $options
     *
     * @return mixed
     */
    public static function sanitize_callback_bfw($options)
    {
        if (BfwSetting::get('bonus-for-otziv')) {
            update_option('comment_moderation', '1');/*баллы утверждаются вручную*/
        }

        /*CRON*/
        if (BfwSetting::get('day-inactive') && (int)BfwSetting::get('day-inactive') > 1) {
            /*Запись в крон (Проверка каждый день)*/
            wp_schedule_event(time(), 'daily', 'bfw_clear_old_cashback');
        } else {
            /*Убрать крон*/
            wp_clear_scheduled_hook('bfw_clear_old_cashback');
        }

        if (BfwSetting::get('birthday')) {

            if (!wp_next_scheduled('bfw_search_birthday')) {
                /*Запись в крон (Проверка каждый день)*/
                wp_schedule_event(time(), 'daily', 'bfw_search_birthday');
            }
        } else {
            /*Убрать крон*/
            wp_clear_scheduled_hook('bfw_search_birthday');
        }


        if (BfwSetting::get('daily_cashback_check')) {
            if (!wp_next_scheduled('bfw_daily_cashback_check')) {
                /*Запись в крон (Проверка каждый день)*/
                wp_schedule_event(time(), 'daily', 'bfw_daily_cashback_check');
            }
        } else {
            /*Убрать крон*/
            wp_clear_scheduled_hook('bfw_daily_cashback_check');
        }


        /*CRON*/

        foreach ($options as $name => & $val) {
            if ($name && !is_array($val)) {
                $val = wp_strip_all_tags($val);
            }
        }
        return $options;
    }


    /**
     * @return void
     */
    public static function bonus_plugin_options(): void
    {
        if (current_user_can('manage_options')) {

            if (BfwRoles::isPro()) {
                $pro_text = ' Pro';
            } else {
                $pro_text = '';
            }
            ?>
            <div class="wrap bonus-for-woo-admin">

                <h2><?php echo _e('Bonus for Woo',
                            'bonus-for-woo'), $pro_text, ' <small>V', BONUS_COMPUTY_VERSION . '</small>'; ?> </h2>
                <p><?php echo __('With the support of', 'bonus-for-woo'); ?> <a href="https://computy.ru"
                                                                                target="_blank"
                                                                                title="Разработка и поддержка сайтов на WordPress">
                        computy</a> <br>
                    <?php echo __('Thank you for rating', 'bonus-for-woo'); ?> <a
                            class="tfr" href="https://wordpress.org/plugins/bonus-for-woo/#reviews"
                            target="_blank">★★★★★</a> | <a
                            href="https://computy.ru/blog/kakie-novye-funkczii-vam-nuzhny/"
                            target="_blank"><?php echo __('Vote for new features!', 'bonus-for-woo'); ?></a> <br>
                    <a href="https://computy.ru/plugins.php"
                       target="_blank"><?php echo __('Our other plugins', 'bonus-for-woo'); ?></a>
                </p>
                <hr>
                <div class="bfw_texts_wrap">
                    <div class="bfw_text">
                        <h3><?php echo __('Description', 'bonus-for-woo'); ?></h3>
                        <p><?php echo __('This plugin is designed to create a bonus system with cashback.',
                                    'bonus-for-woo'); ?>
                            <br>
                            <?php echo __('The cashback percentage is calculated based on the users status in the form of bonus points.',
                                    'bonus-for-woo'); ?>
                            <br>
                            <?php echo __('Each user status has a corresponding cashback percentage.',
                                    'bonus-for-woo'); ?>
                            <br>
                            <?php echo __('The users status depends on the total amount of the users orders.',
                                    'bonus-for-woo'); ?>
                            <br>
                            <?php echo __('You can add points to your customers on the page  <a href="users.php" target="_blank">users</a>,by selecting the desired client.',
                                    'bonus-for-woo'); ?>
                        </p>
                        <a href="https://computy.ru/blog/bonus-for-woo-wordpress/"
                           target="_blank" class="pdf-button"><?php echo __('About plugin', 'bonus-for-woo'); ?></a>
                        <a class="pdf-button" href="https://computy.ru/blog/docs/bonus-for-woo/"
                           target="_blank"><?php echo __('Documentation', 'bonus-for-woo'); ?></a>

                    </div>
                    <div class="bfw_text">
                        <h3><?php echo __('Shordcodes', 'bonus-for-woo'); ?></h3>
                        <p>
                            1. <?php echo __('You can place the client status anywhere in the site using a shortcode:  [bfw_status]',
                                    'bonus-for-woo'); ?>
                            <br>
                            2. <?php echo __('To display the percentage of cachek, use a shortcode: [bfw_cashback]',
                                    'bonus-for-woo'); ?>
                            <br>
                            3. <?php echo __('To display the number of points awarded, use the shortcode: [bfw_points]',
                                    'bonus-for-woo'); ?>
                            <br>
                            4. <?php echo __('Withdrawal of the bonus page from the personal account: [bfw_account]',
                                    'bonus-for-woo'); ?>
                            <br>
                            5. <?php echo __('Withdrawal of a block of information of the referral system from the account: [bfw_account_referral]',
                                    'bonus-for-woo'); ?>


                        </p><a class="pdf-button" href="https://computy.ru/blog/docs/bonus-for-woo/shortkody/"
                               target="_blank"><?php echo __('Full list of shortcodes', 'bonus-for-woo'); ?></a>
                    </div>
                    <div class="bfw_text">
                        <h3><?php echo __('Add-ons', 'bonus-for-woo'); ?></h3>

                        <div class="bfw_addons">
                            <div class="bfw_addon">
                                <div class="bfw_addon_title">Bonus for Woo addon API</div>
                                <div class="bfw_addon_description"><?php echo __('API for Bonus for Woo for CRM systems',
                                            'bonus-for-woo'); ?></div>
                                <a href="https://computy.ru/blog/docs/bonus-for-woo/platnye-dopolneniya/bonus-for-woo-addon-api/"
                                   target="_blank"><?php echo __('More details', 'bonus-for-woo'); ?> ↗</a>
                            </div>

                            <div class="bfw_addon">
                                <div class="bfw_addon_title">BFW addon for referral</div>
                                <div class="bfw_addon_description"><?php echo __('Expands the Bonus for Woo referral system',
                                            'bonus-for-woo'); ?></div>
                                <a href="https://computy.ru/blog/docs/bonus-for-woo/platnye-dopolneniya/bfw-addon-for-referral/"
                                   target="_blank"><?php echo __('More details', 'bonus-for-woo'); ?> ↗</a>
                            </div>

                            <div class="bfw_addon">
                                <div class="bfw_addon_title">BFW addon write off percentage</div>
                                <div class="bfw_addon_description"><?php echo __('The percentage of points written off depending on the clients status.',
                                            'bonus-for-woo'); ?></div>
                                <a href="https://computy.ru/blog/docs/bonus-for-woo/platnye-dopolneniya/bfw-addon-write-off-percentage/"
                                   target="_blank"><?php echo __('More details', 'bonus-for-woo'); ?> ↗</a>
                            </div>

                            <div class="bfw_addon">
                                <div class="bfw_addon_title">BFW addon transfer points for users</div>
                                <div class="bfw_addon_description"><?php echo __('Transfers of bonus points between users.',
                                            'bonus-for-woo'); ?></div>
                                <a href="https://computy.ru/blog/docs/bonus-for-woo/platnye-dopolneniya/bfw-addon-transfer-points-for-users/"
                                   target="_blank"><?php echo __('More details', 'bonus-for-woo'); ?> ↗</a>
                            </div>


                        </div>

                    </div>
                </div>


                <div class="wrap">
                    <?php


                    //Обработчик запроса добавления статуса
                    if (isset($_POST['bfw_computy_ajax']) && $_POST['bfw_computy_ajax'] === 'bfw_computy_ajax') {
                        $name_role = sanitize_text_field($_POST['name_role']);
                        $percent_role = sanitize_text_field($_POST['percent_role']);
                        $summa_start = (float)sanitize_text_field($_POST['summa_start']);
                        BfwRoles::addRole($name_role, $percent_role, $summa_start);

                    } //Обработчик запроса обновления статуса
                    elseif (isset($_POST['bfw_computy_ajax']) && $_POST['bfw_computy_ajax'] === 'editrolehidden') {
                        $percent_role = sanitize_text_field($_POST['percent_role']);
                        $summa_start = (float)sanitize_text_field($_POST['summa_start']);
                        $name_role = sanitize_text_field($_POST['name_role']);
                        BfwRoles::updateStatus($name_role, $percent_role, $summa_start);
                    }


                    //Обработчик запроса удаления статуса
                    if (isset($_POST['delete_role'])) {
                        $delete_role_name = sanitize_text_field($_POST['delete_role_name']);
                        BfwRoles::deleteStatus($_POST['delete_role'], $delete_role_name);
                    }


                    ?>


                    <div class="tabs_bfw">
                        <input type="radio" name="odin" checked="checked" id="vkl1"/><label for="vkl1"><i
                                    class="statusicon"></i><?php echo __('Client status management',
                                    'bonus-for-woo'); ?>
                        </label>
                        <input type="radio" name="odin" id="vkl2"/><label for="vkl2"><i
                                    class="settingsicon"></i><?php echo __('Plugin settings', 'bonus-for-woo'); ?>
                        </label>
                        <?php
                        if (BfwRoles::isPro()) { ?>
                            <input type="radio" name="odin" id="vkl5"/><label class="nf0" for="vkl5"><i
                                        class="proicon"></i><?php echo __('Pro', 'bonus-for-woo'); ?></label>
                        <?php } ?>
                        <input type="radio" name="odin" id="vkl3"/><label for="vkl3"><i
                                    class="notifiicon"></i><?php echo __('Email notifications', 'bonus-for-woo'); ?>
                        </label>
                        <input type="radio" name="odin" id="vkl4"/><label for="vkl4"><i
                                    class="transicon"></i><?php echo __('Translate', 'bonus-for-woo'); ?></label>

                        <?php
                        if (!BfwRoles::isPro()) { ?>
                            <input type="radio" name="odin" id="vkl5"/><label class="nf0" for="vkl5"><i
                                        class="proicon"></i><?php echo __('Pro', 'bonus-for-woo'); ?></label>
                        <?php } ?>
                        <div class="tab_bfw tab_bfw1">
                            <h2><?php echo __('Client status management', 'bonus-for-woo'); ?></h2>
                            <h3><b><?php echo __('Terms:', 'bonus-for-woo'); ?></b></h3>
                            <p><?php echo __('<b>Status name</b> - the name that will be displayed in the client\'s account and his personal account.',
                                        'bonus-for-woo'); ?></p>
                            <p><?php echo __('<b> Slug </b> is a unique name for the system to work.',
                                        'bonus-for-woo'); ?></p>
                            <p><?php echo __('<b> Cashback percentage </b> - the percentage that will be credited to a client with the corresponding status.',
                                        'bonus-for-woo'); ?></p>
                            <p><?php echo __('<b> Amount of orders </b> - is a number corresponding to the amount of customer orders with which this role is applied to the user.
              For example, if you enter 3000, this status will change only when the sum of all customer orders exceeds 3000. If you enter 0, the users status will change immediately, without purchases.',
                                        'bonus-for-woo'); ?></p>
                            <?php
                            $table_bfw = BfwRoles::getRoles();
                            if ($table_bfw) {
                                echo "
          <div class='table-content-bfw'> <table class='table-bfw' ><thead><tr>
                  <th>" . __('Status name', 'bonus-for-woo') . "</th>
                  <th>" . __('Role slug (automatic)', 'bonus-for-woo') . "</th>
                  <th>" . __('Cashback percentage', 'bonus-for-woo') . "</th>
                  <th>" . __('Amount of orders', 'bonus-for-woo') . "</th>
                  <th>" . __('Action', 'bonus-for-woo') . "</th>
              </tr> </thead><tbody>
    ";

                                foreach ($table_bfw as $bfw) {
                                    echo '<tr><td  >' . $bfw->name . '</td><td>' . $bfw->slug . '</td><td>' . $bfw->percent . '%</td><td>' . $bfw->summa_start . ' ' . get_woocommerce_currency_symbol() . '</td>
                  <td class="action_for_role"><input  class="pencil" type="button" value="' . $bfw->id . '">
                  <form method="post" action="" class="list_role_computy">
                  <input type="hidden" name="delete_role_summa_start" value="' . $bfw->summa_start . '" >
                  <input type="hidden" name="delete_role_percent" value="' . $bfw->percent . '" >
                  <input type="hidden" name="delete_role" value="' . $bfw->id . '" >
                  <input type="hidden" name="delete_role_slag" value="' . $bfw->slug . '" >
                  <input type="hidden" name="delete_role_name" value="' . $bfw->name . '" >
                  <input type="submit" value="+" class="delete_role-bfw" title="' . __('Delete status',
                                                    'bonus-for-woo') . '" onclick="return window.confirm(\' ' . __('Are you sure you want to delete the status?',
                                                    'bonus-for-woo') . ' \');">
                  </form></td></tr>';
                                }
                                echo '</tbody></table></div>';

                            } else {
                                echo '<h3>' . __('To get started with the bonus system with cashback, create a new status for customers.',
                                                'bonus-for-woo') . '</h3>';
                            }

                            ?>
                            <form method="post" action="" id="add_role_form">
                                <input type="hidden" id="bfw_computy_ajax" name="bfw_computy_ajax"
                                       value="bfw_computy_ajax">
                                <table class="form-table">
                                    <tbody>
                                    <tr>
                                        <td id="text_new_status"
                                            style="width: 130px"><?php echo __('New status for clients',
                                                    'bonus-for-woo'); ?></td>
                                        <td class="table-bfw">
                                            <input type="text" id="add_role_form_name_role" name="name_role" value=""
                                                   placeholder="<?php echo __('Status name', 'bonus-for-woo'); ?>">
                                            <input type="number" id="add_role_form_percent_role" name="percent_role"
                                                   value="" min="0" max="100" step="any"
                                                   placeholder="<?php echo __('Cashback percentage',
                                                           'bonus-for-woo'); ?>">
                                            <input type="number" id="add_role_form_summa_start" name="summa_start"
                                                   value=""
                                                   min="0"
                                                   placeholder="<?php echo __('Amount of orders', 'bonus-for-woo'); ?>">
                                        </td>
                                    </tr>

                                    </tbody>
                                </table>

                                <p class="submit"><input type="submit" name="submit" id="submitaddrole"
                                                         class="bfw-admin-button"
                                                         value="<?php echo __('Add satus', 'bonus-for-woo'); ?>"></p>
                            </form>


                            <script>
                                function addrole() {
                                    let parentElement = jQuery(this).parent();
                                    let rolename = parentElement.find('input[name="delete_role_name"]').val();
                                    let rolepercent = parentElement.find('input[name="delete_role_percent"]').val();
                                    let rolesumma_start = parentElement.find('input[name="delete_role_summa_start"]').val();

                                    jQuery("#add_role_form_name_role ").val(rolename).addClass('hidden')/*.prop('disabled', true)*/;
                                    jQuery("#add_role_form_percent_role").val(rolepercent);
                                    jQuery("#add_role_form_summa_start").val(rolesumma_start);

                                    let statusText = "<?php echo __('Change status', 'bonus-for-woo');?> " + rolename;
                                    jQuery("#text_new_status").text(statusText);

                                    let editButtonText = "<?php echo __('Edit', 'bonus-for-woo');?>";
                                    jQuery("#add_role_form #submitaddrole").val(editButtonText);

                                    jQuery("#bfw_computy_ajax").val("editrolehidden");
                                }

                                jQuery(function () {
                                    jQuery('.pencil').on('click', addrole);
                                })
                            </script>
                        </div>
                        <div class="tab_bfw tab_bfw2">
                            <form action="options.php" method="POST">
                                <?php

                                settings_fields('option_group_bonus');
                                do_settings_sections('primer_page_bonus');

                                ?>
                                <button class="bfw-admin-button bfw-save-button"
                                        type="submit"><?php echo __('Save Changes', 'bonus-for-woo'); ?></button>
                        </div>
                        <?php
                        if (BfwRoles::isPro()) { ?>
                            <div class="tab_bfw tab_bfw5">
                                <?php
                                settings_fields('option_pro_group_bonus');
                                do_settings_sections('pro_page_bonus');

                                ?>
                                <button class="bfw-admin-button bfw-save-button"
                                        type="submit"><?php echo __('Save Changes', 'bonus-for-woo'); ?></button>
                            </div>
                        <?php } ?>
                        <div class="tab_bfw tab_bfw3">

                            <?php

                            settings_fields('option_mail_group_bonus');
                            do_settings_sections('mail_page_bonus');

                            ?>
                            <button class="bfw-admin-button bfw-save-button"
                                    type="submit"><?php echo __('Save Changes', 'bonus-for-woo'); ?></button>
                        </div>
                        <div class="tab_bfw tab_bfw4">

                            <?php
                            settings_fields('option_trans_group_bonus');
                            do_settings_sections('trans_page_bonus'); ?>
                            <button class="bfw-admin-button bfw-save-button"
                                    type="submit"><?php echo __('Save Changes', 'bonus-for-woo'); ?></button>
                            </form>
                        </div>


                        <?php
                        if (!BfwRoles::isPro()) { ?>
                            <div class="tab_bfw tab_bfw5">
                                <h2><?php echo __('Bonus for woo Pro', 'bonus-for-woo'); ?></h2>

                                <p><?php echo __('Activate the Pro version for your site now and forever. Hurry up to buy cheaper, because with the addition of new features the price will increase.',
                                            'bonus-for-woo'); ?></p>

                                <div class="countdown">
                                    <p class="price-pro"><?php
                                        if (determine_locale() === 'ru_RU') {
                                            echo __('Цена версии Pro: <del>4500</del> <b>3500 рублей</b>',
                                                    'bonus-for-woo');
                                        } else {
                                            echo 'Pro version price: <del>$60</del> <b>$46</b>';
                                        }
                                        ?></p>
                                    <!--
                                    <div>
                                        <span id="days">00</span>
                                        <span class="label"><?php echo __('Days', 'bonus-for-woo'); ?></span>
                                    </div>
                                    <div>
                                        <span id="hours">00</span>
                                        <span class="label"><?php echo __('Hours', 'bonus-for-woo'); ?></span>
                                    </div>
                                    <div>
                                        <span id="minutes">00</span>
                                        <span class="label"><?php echo __('Minutes', 'bonus-for-woo'); ?></span>
                                    </div>
                                    <div>
                                        <span id="seconds">00</span>
                                        <span class="label"><?php echo __('Seconds', 'bonus-for-woo'); ?></span>
                                    </div>-->
                                </div>


                                <h3><?php echo __('What will the Pro version give?', 'bonus-for-woo'); ?></h3>
                                <ul class="listpro">
                                    <li><?php echo __('The ability to earn points on your birthday.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('The choice from which date to start counting the status of customers.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('The ability to set the percentage of the order amount that can be spent by the client with points.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Ability to exclude products and categories that cannot be purchased with points.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Possibility to earn daily points for logging in.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('The ability to credit cashback for excluded products and categories.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Cashback is not accrued for discounted items.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('The ability to specify a cashback percentage in the product editor that does not affect the client status.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Possibility to earn points for registration.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Set up a product with 100% cashback (required to purchase points).',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Ability to exclude roles from the bonus system.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Withdrawal of bonus points if the client has not made orders for a long time.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('The ability to set a minimum order amount at which you can write off points.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Referral system.', 'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Bonus points coupons', 'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Possibility to export bonus points history to pdf and excel.',
                                                'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Points accrual delay.', 'bonus-for-woo'); ?></li>
                                    <li><?php echo __('Accrual of points for previous orders.',
                                                'bonus-for-woo'); ?></li>
                                </ul>
                                <hr>
                                <p><?php echo __('To purchase Bonus for Woo Pro, click on the "Buy" button.',
                                            'bonus-for-woo');
                                    echo '<br>' . sprintf(__('The key will be sent to your email: %s.',
                                                    'bonus-for-woo'), get_option('admin_email'));
                                    ?><br> <span
                                            style="color:#ff001d"><?php echo __('In case of problems, please contact info@computy.ru',
                                                'bonus-for-woo'); ?></span>
                                </p>

                                <form action="https://computy.ru/buy_plugin.php" method="post">
                                    <input type="hidden" name="successURL"
                                           value="<?php echo get_site_url() . $_SERVER['REQUEST_URI']; ?>">
                                    <input type="hidden" name="label"
                                           value="<?php echo get_site_url(); ?>|bonus-for-woo|<?php echo BONUS_COMPUTY_VERSION; ?>|<?php echo get_option('admin_email'); ?>">
                                    <input type="submit" value="<?php echo __('Buy', 'bonus-for-woo'); ?>"
                                           class="buy_pro_button">
                                </form>


                                <p><?php echo __('Enter the activation key sent to your email.',
                                            'bonus-for-woo'); ?></p>
                                <form method="post" action="" style="margin-bottom: 20px">
                                    <input style="width: 250px" type="text" name="bonus-for-woo-pro" value=""/>
                                    <input type="submit" value="<?php echo __('Activate', 'bonus-for-woo'); ?>"
                                           class="active-pro-button">
                                    <!-- <br><label style="margin-top: 15px; display: block;"><?php echo __('Use an alternate server',
                                            'bonus-for-woo'); ?>
                                   <?php $checked_alternate = '';
                                    if (!empty(get_option('bfw_alternate_server'))) {
                                        $checked_alternate = 'checked';
                                    } ?>
                                    <input type="checkbox" name="alternate_server" value="1" <?php echo $checked_alternate; ?>></label>-->
                                </form>


                                <?php if (isset($_POST['bonus-for-woo-pro'])) {
                                    $alernate_server = 0;
                                    if (isset($_POST['alternate_server'])) {
                                        $alernate_server = 1;
                                    }
                                    update_option('bfw_alternate_server', $alernate_server);
                                    BfwFunctions::checkingKey($_POST['bonus-for-woo-pro'], $alernate_server);
                                }
                                ?>

                            </div>
                        <?php } ?>

                    </div>


                </div>
            </div>
            <?php
        }
    }

}
