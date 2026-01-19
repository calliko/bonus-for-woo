<?php

defined('ABSPATH') || exit;

/**
 * Class History
 * Класс истории начисления\списания бонусных баллов
 *
 * @version 6.2.3
 * @since 2.2.3
 */
class BfwHistory
{

    /**
     * Adding a record to history
     * Добавление записи в историю
     *
     * @param int $user_id
     * @param string $symbol //+ -
     * @param float $points
     * @param int $order номер заказа
     * @param string $cause //причина
     * @param string $status //добавляется id приглашенного реферала
     *
     * @return void
     * @version 6.2.3
     */
    public static function add_history(
            int $user_id,
            string $symbol,
            float $points,
            int $order,
            string $cause,
            string $status = ''
    ): void {
        if ($points != 0) {
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'bfw_history_computy',
                    array(
                            'user' => $user_id,
                            'date' => current_time('Y-m-d H:i:s'),
                            'symbol' => $symbol,
                            'points' => $points,
                            'orderz' => $order,
                            'comment_admin' => $cause,
                            'status' => $status
                    ), array(
                            '%d', // %d - значит число
                            '%s', // %s - значит строка
                            '%s',
                            '%s',
                            '%d',
                            '%s',
                            '%s'
                    ));
        }
    }


    /**
     * Showing the history of one client
     * Показ истории одного клиента
     *
     * @param $user_id //Id пользователя
     *
     * @return void
     *
     * @version 7.1.4
     */
    public static function getHistory($user_id = 0): string
    {
        global $wpdb;
        $output = '';
        if (empty($user_id)) {
            $user_id = get_current_user_id();
        }
        if ($user_id === 0) {
            return '';
        }

        $history_title = esc_html(BfwSetting::get('title-on-history-account', __('Points accrual', 'bonus-for-woo')));

        $history = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$wpdb->prefix}bfw_history_computy WHERE `user` = %d ORDER BY id DESC",
                        $user_id)
        );

        if (empty($history)) {
            return '';
        }

        ob_start();
        require_once BONUS_COMPUTY_PLUGIN_DIR . '/pages/datatable.php';
        ?>
        <div class="bfw-history-points-header">
            <h3><?php echo $history_title; ?></h3>

            <?php if (current_user_can('manage_options')): ?>
                <a class="clear_history" href="javascript:void(0);" onclick="return confirmClearHistory();">
                    <?php echo __('Clear the history', 'bonus-for-woo'); ?>
                </a>

                <script>
                    function confirmClearHistory() {
                        if (confirm("<?php echo esc_js(__('Are you sure you want to clear this customer bonus points history?',
                                'bonus-for-woo')); ?>")) {
                            window.location = "/wp-admin/user-edit.php?user_id=<?php echo esc_js($user_id); ?>&bfw_delete_all_post_history_points=<?php echo esc_js($user_id); ?>";
                        }
                    }
                </script>
            <?php endif; ?>
        </div>


        <table class="table-bfw table-bfw-history-points nowrap" id="table-history-points" style="width:100%">
            <thead>
            <tr>
                <th>№</th>
                <th><?php echo __('Date', 'bonus-for-woo'); ?></th>
                <th><?php echo BfwPoints::pointsLabel(5); ?></th>
                <th><?php echo __('Event', 'bonus-for-woo'); ?></th>
                <?php if (current_user_can('manage_options')): ?>
                    <th><?php echo __('Action', 'bonus-for-woo'); ?></th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $index => $item): ?>
                <?php
                $color = $item->symbol === '+' ? '#23CE48' : ($item->symbol === '-' ? '#FF001D' : '');


                $order_link = '';
                if ($item->orderz != '0') {
                    $order_text = __('Order', 'bonus-for-woo') . ' №' . $item->orderz;
                    if (current_user_can('manage_options')) {
                        $order_link = sprintf(
                                '<a href="%s">%s</a> ',
                                esc_url(admin_url("post.php?post={$item->orderz}&action=edit")),
                                esc_html($order_text)
                        );
                    } else {

                        $order = wc_get_order($item->orderz);
                        if ($order && $order->get_customer_id() === $user_id) {
                            //Когда заказ этого пользователя
                            $endpoint = get_option('woocommerce_myaccount_view_order_endpoint', 'view-order');
                            $account_page = get_permalink(get_option('woocommerce_myaccount_page_id'));
                            $order_link = sprintf(
                                    '<a href="%s">%s</a> ',
                                    esc_url("{$account_page}{$endpoint}/{$item->orderz}"),
                                    esc_html($order_text)
                            );

                        } else {
                            //Когда заказ другого пользователя
                            $order_link = $order_text . ' ';
                        }


                    }
                }
                ?>
                <tr>
                    <td><?php echo esc_html($index + 1); ?></td>
                    <td><?php echo esc_html(gmdate('d.m.Y H:i', strtotime($item->date))); ?></td>
                    <td>
                        <span style="color: <?php echo esc_attr($color); ?>"><?php echo esc_html($item->symbol . BfwPoints::roundPoints($item->points)); ?></span>
                    </td>
                    <td><?php echo $order_link . esc_html($item->comment_admin); ?></td>
                    <?php if (current_user_can('manage_options')): ?>
                        <td>
                            <form method="post" class="list_role_computy">
                                <input type="hidden" name="bfw_delete_post_history_points"
                                       value="<?php echo esc_attr($item->id); ?>">
                                <input type="submit" value="+" class="delete_role-bfw"
                                       title="<?php echo esc_attr(__('Delete', 'bonus-for-woo')); ?>"
                                       onclick="return confirm('<?php echo esc_js(__('Are you sure you want to remove this entry from your reward points history?',
                                               'bonus-for-woo')); ?>');">
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $output = ob_get_clean();

        return $output;
    }

    /**
     * Show history of all clients
     * Показ истории всех клиентов
     *
     * @param string $date_start С какой даты
     * @param string $date_finish По какую дату
     *
     * @return void
     * @version 6.4.0
     */
    public static function getListHistory($date_start = false, $date_finish = false): void
    {
        $where = '';
        if ($date_start) {
            $limit = '';
            $endDate = $date_finish ?? gmdate('Y-m-d');
            $where = " WHERE date BETWEEN '" . $date_start . "' AND '" . $endDate . "'";
        } else {
            $limit = ' LIMIT 500';
        }
        global $wpdb;
        $table_bfw = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bfw_history_computy {$where}  ORDER BY date DESC {$limit}");
        if ($table_bfw) { ?>

            <table class="table-bfw table-bfw-history-points"
                   id='table-history-points'>
                <thead>
                <tr>
                    <th>№</th>
                    <th><?php
                        echo __('Date', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('ID client', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Email client', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Client', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Status', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo BfwPoints::pointsLabel(5); ?></th>
                    <th><?php
                        echo __('Event', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Action', 'bonus-for-woo'); ?></th>

                </tr>
                </thead>
                <tbody>
                <?php
                $i = 1;
                foreach ($table_bfw as $bfw) {
                    $getorderz = '';
                    if ($bfw->orderz != '0') {
                        $getorderz = '<a href="/wp-admin/post.php?post=' . $bfw->orderz . '&action=edit">' . __('Order',
                                        'bonus-for-woo') . ' №' . $bfw->orderz . '</a> ';
                    }
                    $color = $bfw->symbol === '+' ? '#23CE48' : ($bfw->symbol === '-' ? '#FF001D' : '');
                    echo '<tr><td>' . $i++ . '</td>
<td>' . date_format(date_create($bfw->date), 'd.m.Y H:i') . '</td>';
                    echo '<td>' . $bfw->user . '</td>';

                    $user = get_userdata($bfw->user);
                    echo '<td>' . $user->user_email . '</td>';
                    $role = BfwRoles::getRole($bfw->user);
                    if (!empty($user->first_name)) {
                        $nameuser = $user->first_name . ' ' . $user->last_name;
                    } else {
                        $nameuser = $user->user_login ?? 'login';
                    }
                    echo '<td><a href="/wp-admin/user-edit.php?user_id=' . $bfw->user . '" target="_blank">' . $nameuser . '</a></td><td>' . $role['name'] . '</td>
<td><span style="color:' . $color . ' ">' . $bfw->symbol . BfwPoints::roundPoints($bfw->points) . '</span></td>
<td>' . $getorderz . $bfw->comment_admin . '</td>';
                    if (current_user_can('manage_options')) {
                        echo '<td><form method="post" action="" class="list_role_computy"><input type="hidden" name="bfw_delete_post_history_points" value="' . $bfw->id . '" >
   <input type="submit" value="+" class="delete_role-bfw" title="' . __('Delete',
                                        'bonus-for-woo') . '" onclick="return window.confirm(\' ' . __('Are you sure you want to remove this entry from your reward points history?',
                                        'bonus-for-woo') . ' \');">
                  </form> </td>';
                    }
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<h3>' . __('No points accrual history found.', 'bonus-for-woo') . '</h3>';
        }
    }


    /**
     * Delete all history of a specific client
     * Удаление всей истории определенного клиента
     *
     * @param int $user_id
     *
     * @return void
     * @version 6.4.0
     */
    public static function clearAllHistoryUser(int $user_id): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfw_history_computy';
        $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE `user` = %d", $user_id));
    }


    /**
     * Deleting one entry in history
     * Удаление одной записи в истории
     *
     * @param int $id
     *
     * @return void
     * @version 2.5.1
     */
    public static function deleteHistoryId(int $id): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfw_history_computy';
        $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE `id` = %d", $id));
    }


    /**
     * Clearing history when deleting a user
     * Очищение истории при удалении пользователя
     *
     * @param int $user_id
     *
     * @return void
     * @version 6.4.0
     */
    public static function bfw_when_delete_user(int $user_id): void
    {
        self::clearAllHistoryUser($user_id);
    }
}
