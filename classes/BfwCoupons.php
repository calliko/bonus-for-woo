<?php
/**
 * Class BfwCoupons
 * Класс купонов
 *
 * @version 6.4.0
 * @since 4.1.0
 */

defined('ABSPATH') || exit;

class BfwCoupons
{

    /**
     * Adding a coupon
     * Добавление купона
     *
     * @param string $code Код купона
     * @param float $sum сумма
     * @param string $comment_admin комментарий админа
     * @param string $status
     * @param int $reusable многоразовый ли купон
     * @return void
     * @version 6.7.5
     */

    public static function addCoupon(
            string $code,
            float $sum,
            string $comment_admin,
            string $status,
            int $reusable = 0
    ): void {

        if (!empty($code)) {
            global $wpdb;
            $wpdb->insert(
                    $wpdb->prefix . 'bfw_coupons_computy',
                    [
                            'code' => $code,
                            'created' => current_time('mysql'),
                            'sum' => $sum,
                            'comment_admin' => $comment_admin,
                            'status' => $status,
                            'reusable' => $reusable
                    ],
                    ['%s', '%s', '%f', '%s', '%s', '%d']
            );
        }
    }


    /**
     * Добавление многоразового купона для пользователя
     *
     * @param string $code_id
     * @param int $user_id
     * @return void
     */
    public static function addReusableCoupon(int $user_id, string $code_id): void
    {

        if (!empty($code_id)) {
            global $wpdb;
            $wpdb->insert(
                    $wpdb->prefix . 'bfw_coupon_usages',
                    [
                            'code' => $code_id,
                            'date_use' => current_time('mysql'),
                            'user' => $user_id
                    ],
                    ['%s', '%s', '%s']
            );
        }
    }

    /**
     * Количество использований многоразового купона
     *
     * @param $coupon_id
     * @return int
     */
    public static function countUsedReusableCoupon($coupon_id): int
    {
        global $wpdb;

// Получаем информацию о колонках
        $columns = $wpdb->get_results("SELECT COUNT(id) FROM `{$wpdb->prefix}bfw_coupon_usages` WHERE code = '{$coupon_id}'",
                ARRAY_A);

// Получаем количество столбцов
        return $columns[0]['COUNT(id)'] ?? 0;
    }


    /**
     * Show all coupons
     * Показ всех купонов
     *
     * @return void
     * @version 6.4.0
     */
    public static function getListCoupons(): void
    {
        global $wpdb;
        $table_bfw = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bfw_coupons_computy ORDER BY id DESC");

        if ($table_bfw) {
            ob_start();
            ?>
            <table class="table-bfw table-bfw-history-points"
                   id='table-coupons'>
                <thead>
                <tr>
                    <th>№</th>
                    <th><?php
                        echo __('Coupon code', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Sum', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Create date', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Comment admin', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Client', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Date of use', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Status', 'bonus-for-woo'); ?></th>
                    <th><?php
                        echo __('Action', 'bonus-for-woo'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $i = 1;
                foreach ($table_bfw as $bfw) {
                    $bgtr = ($bfw->status == 'active') ? 'background:#fff;' : (($bfw->status == 'noactive') ? 'background:#ff9a9a;' : 'background:#89f784;');
                    ?>
                    <tr style="<?php echo esc_attr($bgtr); ?>">
                        <td><?php echo $i++; ?></td>
                        <td><b><?php
                                echo esc_html($bfw->get_code()); ?></b>
                            <?php
                            if ($bfw->reusable == 1) {
                                echo '<span title="' . __('Reusable',
                                                'bonus-for-woo') . '" class="dashicons dashicons-update"></span>';
                            }
                            ?>
                        </td>
                        <td><b><?php
                                echo BfwPoints::roundPoints($bfw->sum); ?></b></td>
                        <td><?php echo date_format(date_create($bfw->created), 'd.m.Y H:i'); ?></td>
                        <td><?php echo esc_html($bfw->comment_admin); ?> </td>
                        <?php
                        if ($bfw->reusable == 1) {
                            echo '<td>' . __('Coupon usage:',
                                            'bonus-for-woo') . ' ' . self::countUsedReusableCoupon($bfw->id) . '</td>';
                        } elseif (!empty($bfw->user)) {
                            $user = get_userdata($bfw->user);
                            $nameuser = !empty($user->first_name) ? $user->first_name . ' ' . $user->last_name : $user->user_login;
                            ?>
                            <td><a href="/wp-admin/user-edit.php?user_id=<?php
                                echo $bfw->user; ?>" target="_blank"><?php
                                    echo $nameuser; ?></a></td>
                            <?php
                        } else { ?>
                            <td>-</td>
                            <?php
                        } ?>
                        <td><?php echo $bfw->date_use ?? '-'; ?></td>
                        <?php

                        switch ($bfw->status) {
                            case 'active':
                                $statustext = __('Active', 'bonus-for-woo');
                                break;
                            case 'noactive':
                                $statustext = __('Not active', 'bonus-for-woo');
                                break;
                            case 'used':
                                $statustext = __('Used', 'bonus-for-woo');
                                break;
                            default:
                                $statustext = '';
                                break;
                        }
                        ?>
                        <td><?php
                            echo esc_html($statustext); ?></td>
                        <td style="display: flex; justify-content: space-between;">
                            <?php
                            if ($bfw->status === 'active') { ?>
                                <form method="post" action=""
                                      class="list_role_computy">
                                    <input type="hidden" name="status_coupon"
                                           value="active">
                                    <input type="hidden"
                                           name="bfw_edit_status_coupon"
                                           value="<?php
                                           echo esc_attr($bfw->id); ?>">
                                    <input type="submit" value="<?php
                                    echo __('Deactivate', 'bonus-for-woo'); ?>"
                                           class="button_activated_coupon"
                                           title="<?php
                                           echo __('Deactivate', 'bonus-for-woo'); ?>">
                                </form>
                                <?php
                            } elseif ($bfw->status === 'noactive') { ?>
                                <form method="post" action=""
                                      class="list_role_computy">
                                    <input type="hidden" name="status_coupon"
                                           value="noactive">
                                    <input type="hidden"
                                           name="bfw_edit_status_coupon"
                                           value="<?php
                                           echo esc_attr($bfw->id); ?>">
                                    <input type="submit" value="<?php
                                    echo __('Activate', 'bonus-for-woo'); ?>"
                                           class="button_activated_coupon"
                                           title="<?php
                                           echo __('Activate', 'bonus-for-woo'); ?>">
                                </form>
                                <?php
                            } ?>
                            <form method="post" action=""
                                  class="list_role_computy">
                                <input type="hidden" name="bfw_delete_coupon"
                                       value="<?php
                                       echo esc_attr($bfw->id); ?>">
                                <input type="submit" value="+"
                                       class="delete_role-bfw" title="<?php
                                echo __('Delete', 'bonus-for-woo'); ?>"
                                       onclick="return window.confirm('<?php
                                       echo __('Are you sure you want to delete this coupon?', 'bonus-for-woo'); ?>');">
                            </form>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <?php
            echo ob_get_clean();
        }
    }


    /**
     * Removes a coupon by ID
     * Удаляет купон по идентификатору
     *
     * @param int $id Идентификатор купона
     *
     * @return void
     * @version 6.4.0
     */
    public static function deleteCoupon(int $id): void
    {
        global $wpdb;
        $table_name_reus = $wpdb->prefix . 'bfw_coupon_usages';
        $wpdb->query(
                $wpdb->prepare("DELETE FROM $table_name_reus WHERE code = %s", $id)
        );
        $table_name = $wpdb->prefix . 'bfw_coupons_computy';
        $wpdb->delete($table_name, array('id' => $id), array('%d'));
    }


    /**
     * Change coupon status
     * Изменение статуса купона
     *
     * @param int $id
     * @param string $status
     *
     * @return void
     * @version 6.4.0
     */

    public static function editStatusCoupon(int $id, string $status): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfw_coupons_computy';
        $wpdb->query(
                $wpdb->prepare("UPDATE %i SET `status` = %s WHERE `id` = %d",
                        $table_name,
                        $status,
                        $id
                )
        );
    }


    /**
     * Display of one coupon
     * Вывод одного купона
     *
     * @param string $code
     *
     * @return mixed
     * @version 6.4.0
     */
    public static function getCoupon(string $code)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfw_coupons_computy';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM  %i WHERE code=%s", $table_name, $code));
    }


    /**
     * Можно ли пользоваться купон этому пользователю?
     *
     * @param int $user_id
     * @param $coupon_id
     * @return bool
     * @version 7.1.0
     */
    public static function bfwCanUseCoupon(int $user_id, $coupon_id): bool
    {
        global $wpdb;

        $usage_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bfw_coupon_usages WHERE user = %d AND code = %s",
                $user_id, $coupon_id
        ));

        return $usage_exists == 0;
    }

    /**
     * Coupon application by the customer
     * Применение купона клиентом
     *
     * @param int $userid
     * @param string $code_coupon
     *
     * @return string
     * @version 7.1.0
     */
    public static function enterCoupon(int $userid, string $code_coupon): string
    {
        $coupon = self::getCoupon($code_coupon);

        if (isset($coupon->code) && $coupon->status === 'active') {
            $daily_coupon = get_user_meta($userid, 'daily_coupon', true);
            $count_limit_day = 1;

            if (!empty($daily_coupon)) {
                $qca = BfwSetting::get('quantity-coupon-applied', 1);

                $count_limit_day = $daily_coupon[1] + 1;

                if ($daily_coupon[0] === gmdate('d.m.y') && $daily_coupon[1] >= $qca) {
                    return 'limit';
                }
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'bfw_coupons_computy';
            $table_usagees = $wpdb->prefix . 'bfw_coupon_usages';
            if ($coupon->reusable == 1) {
                //применяется многоразовый купон
                if (self::bfwCanUseCoupon($userid, $coupon->id)) {

                    self::addReusableCoupon($userid, $coupon->id);
                } else {

                    // Купон уже использован этим пользователем
                    return 'limit';
                }

            } else {
                //Применяется одноразовый купон

                $wpdb->update($table_name,
                        array('status' => 'used', 'user' => $userid, 'date_use' => gmdate('Y-m-d H:i:s')),
                        array('id' => $coupon->id));
            }


            $old_points = BfwPoints::getPoints($userid);
            $coupon_sum = $coupon->sum;
            $new_ball = $old_points + $coupon_sum;
            BfwPoints::updatePoints($userid, $new_ball);

            update_user_meta($userid, 'daily_coupon', array(gmdate('d.m.y'), $count_limit_day));

            $pricina = __('Coupon usage', 'bonus-for-woo') . ' ' . $code_coupon;
            BfwHistory::add_history($userid, '+', $coupon_sum, '0', $pricina);

            return 'good';
        }

        return 'not_coupon';
    }

    /**
     * Action when deleting a coupon of points(woo blocks)
     * Действие при удалении купона баллов(woo blocks)
     *
     * @param $coupon_code string
     *
     * @return void
     * @version 6.4.0
     */
    public static function trueRedirectOnCouponRemoval(string $coupon_code): void
    {
        $cart_discount = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
        if (strtolower($coupon_code) === strtolower($cart_discount)) {
            BfwPoints::updateFastPoints(get_current_user_id(), 0);
        }
    }


    /**
     * Export bonus csv file
     * Экспорт csv файла бонусов
     *
     * @return void
     * @version 7.1.0
     */

    public static function bfwExportCoupons(): void
    {
        $response = json_decode(stripslashes($_POST['response']), true);
        $url_export_file = $response['data']['url']; // ссылка на загруженный файл экспорта

        $limit = 100; // сколько строк обрабатывать в каждом пакете
        $fileHandle = fopen($url_export_file, "rb");

        if ($fileHandle === false) {
            die(__('Error opening', 'bonus-for-woo') . ' ' . htmlspecialchars($url_export_file));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bfw_coupons_computy';

        while (!feof($fileHandle)) {
            $i = 0;
            while ($i < $limit && ($currRow = fgetcsv($fileHandle)) !== false) {
                $i++;

                $coupon = (string)$currRow[0];
                $sum = (float)$currRow[1];
                $comment_admin = $currRow[2];
                $status = in_array($currRow[3], ['active', 'noactive', 'used']) ? $currRow[3] : 'noactive';
                $reusable = (int)$currRow[4] ?? 0;
                if ($comment_admin !== 'comment_admin' && !$wpdb->get_var($wpdb->prepare("SELECT code FROM %i WHERE code = %s",
                                $table, $coupon))) {
                    self::addCoupon($coupon, $sum, $comment_admin, $status, $reusable);
                }
            }
        }

        fclose($fileHandle);
        echo 'good';
        exit();
    }

    /**todo проверить на работоспособность. Возможно заменить верхним
     *
     * @return void
     */
    public static function NEWbfwExportCoupons(): void
    {
        // Проверяем nonce для безопасности
        check_ajax_referer('bfw_export_nonce', 'nonce');

        $response = json_decode(stripslashes($_POST['response']), true);

        // Проверяем наличие данных
        if (!isset($response['data']['url'])) {
            wp_die(__('Invalid data', 'bonus-for-woo'));
        }

        $url_export_file = $response['data']['url'];

        // Инициализируем WP_Filesystem
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        // Проверяем, что файловая система доступна
        if (!$wp_filesystem || !$wp_filesystem->exists($url_export_file)) {
            wp_die(__('Error accessing file', 'bonus-for-woo') . ' ' . esc_html($url_export_file));
        }

        // Получаем содержимое файла
        $file_content = $wp_filesystem->get_contents($url_export_file);

        if ($file_content === false) {
            wp_die(__('Error reading file', 'bonus-for-woo'));
        }

        // Обрабатываем CSV данные
        $lines = explode("\n", $file_content);
        global $wpdb;
        $table = $wpdb->prefix . 'bfw_coupons_computy';

        $limit = 100; // сколько строк обрабатывать в каждом пакете
        $processed = 0;
        $total_lines = count($lines);

        for ($j = 0; $j < $total_lines; $j += $limit) {
            $end = min($j + $limit, $total_lines);

            for ($i = $j; $i < $end; $i++) {
                // Пропускаем пустые строки
                if (empty(trim($lines[$i]))) {
                    continue;
                }

                // Парсим CSV строку
                $currRow = str_getcsv($lines[$i]);

                // Проверяем корректность строки
                if (count($currRow) < 5) {
                    continue;
                }

                $coupon = (string)$currRow[0];
                $sum = (float)$currRow[1];
                $comment_admin = $currRow[2];
                $status = in_array($currRow[3], ['active', 'noactive', 'used']) ? $currRow[3] : 'noactive';
                $reusable = isset($currRow[4]) ? (int)$currRow[4] : 0;

                // Пропускаем заголовок, если он есть
                if ($coupon === 'code' || $coupon === 'comment_admin') {
                    continue;
                }

                // Проверяем существование купона
                $safe_table_name = str_replace('`', '``', $table);
                $existing_coupon = $wpdb->get_var(
                        $wpdb->prepare(
                                "SELECT code FROM `{$safe_table_name}` WHERE code = %s",
                                $coupon
                        )
                );

                if (!$existing_coupon) {
                    self::addCoupon($coupon, $sum, $comment_admin, $status, $reusable);
                    $processed++;
                }
            }

            // Делаем небольшую паузу между пакетами для оптимизации нагрузки
            if (($j + $limit) < $total_lines) {
                sleep(1);
            }
        }

        // Удаляем временный файл, если он был загружен
        if (strpos($url_export_file, wp_upload_dir()['basedir']) !== false) {
            $wp_filesystem->delete($url_export_file);
        }

        wp_send_json_success(array(
                'message' => __('Coupons imported successfully', 'bonus-for-woo'),
                'processed' => $processed
        ));
    }


    public static function getListCodeCoupons()
    {
        global $wpdb;

        return $wpdb->get_results("SELECT code FROM {$wpdb->prefix}bfw_coupons_computy ORDER BY id DESC");
    }

    public static function usingWrongCoupon($err, $err_code, $coupon)
    {
        // Проверяем, ошибка ли "купон не найден"

        if ($err_code == '105') {

            $arrayCoupons = self::getListCodeCoupons();
            $codes = wp_list_pluck($arrayCoupons, 'code');
            $codes_lower = array_map('strtolower', $codes); // Приводим к нижнему регистру

            if (in_array(strtolower($coupon->get_code()), $codes_lower)) {
                return __('The form for receiving bonus points is located in your personal account.', 'bonus-for-woo');
            }
        }

        // Для всех остальных ошибок — оставляем оригинальное сообщение
        return $err;
    }

}
