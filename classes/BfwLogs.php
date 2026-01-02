<?php

defined( 'ABSPATH' ) || exit;

/**
 * Класс логирования
 *
 * @version 6.4.0
 */
class BfwLogs
{


    /**
     * Adding a log
     * Добавление лога
     *
     * @param string $event Событие: message,error,add_points, remove_points,
     * @param int $user_id
     * @param string $message
     *
     * @return void
     * @version 6.4.0
     */
    public static function addLog(string $event, int $user_id, string $message): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'bfw_logs_computy', // указываем таблицу
                array(
                        'user' => $user_id,
                        'created' => current_time('Y-m-d H:i:s'),
                        'event' => $event,
                        'message' => $message,
                        'status' => ''

                ), array(
                        '%d', // %d - значит число
                        '%s', // %s - значит строка
                        '%s',
                        '%s',
                        '%s'
                ));
    }


    /**
     * Output of log sheet
     * Вывод листа логов
     *
     * @param string $date_start
     * @param string $date_finish
     *
     * @return void
     * @version 6.6.2
     */
    public static function getListLog(string $date_start = '', string $date_finish = ''): void
    {
        $where = '';
        $limit = ' LIMIT 500';
        if (!empty($date_start)) {
            $limit = '';
            $endDate = $date_finish ?? gmdate('Y-m-d');
            $where = " WHERE created BETWEEN '" . $date_start . "' AND '" . $endDate . "'";
        }
        global $wpdb;
        $table_bfw = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bfw_logs_computy {$where}  ORDER BY created DESC {$limit}");
        if ($table_bfw) { ?>

            <table class="table-bfw table-bfw-history-points"
                   id='table-history-points'>
                <thead>
                <tr>
                    <th>№</th>
                    <th><?php
                        echo __('Event', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Date', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('User', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Message', 'bonus-for-woo'); ?></th>

                </tr>
                </thead>
                <tbody>
                <?php
                $i = 1;
                foreach ($table_bfw as $bfw) {
                    echo '<tr><td>' . $i++ . '</td>
<td>' . $bfw->event . '</td>
<td>' . date_format(date_create($bfw->created), 'd.m.Y H:i') . '</td>';

                    if ($bfw->user === 0) {
                        echo '<td> </td>';
                    } else {
                        $user = get_userdata($bfw->user);

                        if (!empty($user->first_name)) {
                            $nameuser = $user->first_name . ' ' . $user->last_name;
                        } else {
                            $nameuser = $user->user_login ?? 'login';
                        }
                        echo '<td><a href="/wp-admin/user-edit.php?user_id=' . $bfw->user . '" target="_blank">' . $nameuser . '</a></td>';
                    }


                    echo ' <td>' . $bfw->message . '</td> </tr>';
                }
                ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<h3>' . __('Logs not found.', 'bonus-for-woo') . '</h3>';
        }
    }


}
