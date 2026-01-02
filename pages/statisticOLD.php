<?php
defined( 'ABSPATH' ) || exit;

/**
 * Страница статистики
 *
 * @version 5.1.2
 */


/*todo см ниже

https://dev.to/realflowcontrol/processing-one-billion-rows-in-php-3eg0
сделать через ajax. то есть обработка в несколько проходов. тогда будет виден процесс и не будет белого экрана*/

?>
<style>
    .bfw-stat-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }

    .bfw-stat-block {
        background: #fff;
        padding: 0 20px 20px 20px;
        margin: 10px 0;
        border-radius: 5px;
        border: 1px solid #dcdcde;
    }
</style>
<div class="wrap bonus-for-woo-admin">
    <?php
    echo '<h1>' . __('Bonus system statistic', 'bonus-for-woo') . '</h1>'; ?>
    <p style="color: red"><?php
        echo __(
                ' At the moment, the statistics are in testing mode.',
                'bonus-for-woo'
        ); ?></p>
    <p><?php
        echo __(
                'Statistics will be updated. For suggestions on statistics, please email info@computy.ru.',
                'bonus-for-woo'
        ); ?></p>
    <hr>
    <div class="bfw-stat-wrap">
        <?php


        wp_register_style(
                'chart.min.css',
                BONUS_COMPUTY_PLUGIN_URL . '_inc/chart/Chart.min.css',
                array(),
                BONUS_COMPUTY_VERSION
        );
        wp_register_script(
                'chart.min.js',
                BONUS_COMPUTY_PLUGIN_URL . '_inc/chart/Chart.min.js',
                array(),
                BONUS_COMPUTY_VERSION
        );
        wp_register_script(
                'knob.min.js',
                BONUS_COMPUTY_PLUGIN_URL . '_inc/chart/jquery.knob.min.js',
                array(),
                BONUS_COMPUTY_VERSION
        );

        wp_enqueue_style('chart.min.css');
        wp_enqueue_script('chart.min.js');
        wp_enqueue_script('knob.min.js');

        $exclude_roles = BfwSetting::get('exclude-role', array());

        /*
            // Начало отсчета времени и памяти
           $startTime = microtime(true);
           $startMemory = memory_get_usage();

           ....тут код

            // Конец отсчета времени и памяти
            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            // Вычисляем время выполнения и использование памяти
            $executionTime = $endTime - $startTime;
            $memoryUsage = $endMemory - $startMemory;
            echo "<div>Время выполнения: " . round($executionTime,2) . " секунд<br>";
            echo "Использование памяти: " . round(($memoryUsage/1048576),2) . " Мбайт<br></div>";

        */

        global $wpdb;
        $total_in_bfw_names = '';
        $total_in_bfw_count_users = '';

        $rowcount = $wpdb->get_var(
                "SELECT count(*) FROM " . $wpdb->prefix
                . "usermeta WHERE meta_key = 'bfw_status' "
        );


        echo '<div class="bfw-stat-block" style="width: 300px">
<h3>' . sprintf(
                        __('Total in the bonus system: %s of users', 'bonus-for-woo'),
                        $rowcount
                ) . '</h3>';


        $total_in_bfw_names2 = [];
        $total_in_bfw_count_users2 = [];
        $done4 = $wpdb->get_results(
                "SELECT meta_value, COUNT(*) as count FROM {$wpdb->prefix}usermeta WHERE meta_key = 'bfw_status'  GROUP BY meta_value"
        );

        $done44 = $wpdb->get_results(
                "SELECT SUM(meta_value) AS total_sum  FROM {$wpdb->prefix}usermeta WHERE meta_key = 'computy_point' "
        );

        // Получаем все записи из bfw_computy заранее
        $bfw_ids = array_column($done4, 'meta_value');
        if (!empty($bfw_ids)) {
            $bfw_records
                    = $wpdb->get_results(
                    "SELECT id, name FROM {$wpdb->prefix}bfw_computy WHERE id IN ("
                    . implode(',', array_map('intval', $bfw_ids)) . ")"
            );

            $bfw_names_map = [];
            foreach ($bfw_records as $record) {
                $bfw_names_map[$record->id] = $record->name;
            }

            foreach ($done4 as $bfw) {
                if (isset($bfw_names_map[$bfw->meta_value])
                        && !empty($bfw_names_map[$bfw->meta_value])
                ) {
                    $total_in_bfw_count_users2[] = "'" . $bfw->count . "'";
                    $total_in_bfw_names2[] = "'"
                            . $bfw_names_map[$bfw->meta_value]
                            . "'";
                }
            }

// Преобразуем массивы обратно в строки, если это необходимо
            $total_in_bfw_count_users2 = implode(
                    ',',
                    $total_in_bfw_count_users2
            );
            $total_in_bfw_names2 = implode(',', $total_in_bfw_names2);
        } else {
            echo __('No users found in the bonus system.', 'bonus-for-woo');
        }

        ?>
        <canvas id="pieChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 250px;"></canvas>
    </div>
    <script>
        jQuery(function () {
            let donutData = {
                labels: [  <?php  echo $total_in_bfw_names2; ?> ],
                datasets: [
                    {
                        data: [ <?php  echo $total_in_bfw_count_users2; ?> ],
                        backgroundColor: ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de', '#333', '#5c17b8', '#e9ec23'],
                    }
                ]
            }
            let pieChartCanvas = jQuery('#pieChart').get(0).getContext('2d')
            let pieData = donutData;
            let pieOptions = {
                maintainAspectRatio: true,
                responsive: true,
                legend: {
                    display: false
                }
            }
            new Chart(pieChartCanvas, {
                type: 'pie',
                data: pieData,
                options: pieOptions
            })

        })
    </script>
    <?php if (!empty($done44[0]->total_sum)){ ?>

    <div class="bfw-stat-block" style="width: 300px">
        <h3><?php
            echo __(
                            'Total amount of points in users accounts:',
                            'bonus-for-woo'
                    ) . ' ' . number_format($done44[0]->total_sum, 0, '', ' ') . ' ';
            echo BfwPoints::pointsLabel($done44[0]->total_sum); ?></h3>

        <h3><?php echo __('Order statistics', 'bonus-for-woo'); ?></h3>
        <?php
        $order_status = $val['add_points_order_status'] ?? 'completed';
        $args3 = array(
                'status' => array('wc-' . $order_status),
                'limit' => 100,
                'paginate' => true,
                'paged' => 1, // Начинаем с первой страницы
            // Исключение неавторизованных пользователей можно добавить позже
        );


        $fes_totals = 0;
        $count_fee = 0;
        $i = 0;
        $cart_discount = mb_strtolower($val['bonus-points-on-cart']);
        do {
            $orders = wc_get_orders($args3);

            foreach ($orders->orders as $one_order) {
                $i++;
                $fee_total = BfwFunctions::feeOrCoupon($one_order);
                $fes_totals += $fee_total;

                // Бонусы с помощью купонов
                foreach ($one_order->get_coupon_codes() as $coupon_code) {
                    $coupon = new WC_Coupon($coupon_code);
                    $get_code = $coupon->get_code();

                    if (strtolower($get_code) === strtolower($cart_discount)) {
                        $count_fee++;
                    }
                }

                $count_fee += count($one_order->get_items('fee'));
            }

            $args3['paged']++; // Переходим к следующей странице
        } while ($args3['paged'] <= $orders->max_num_pages);
        $count_all_orders = $i;

        /*
         $fes_totals = 0;
         $orders = wc_get_orders($args3);

         $count_all_orders = count($orders);
         if ($count_all_orders === 0) {
             echo __('No orders found.', 'bonus-for-woo');
         } else {
             $count_fee = 0;
             $cart_discount = mb_strtolower($val['bonus-points-on-cart']);
             foreach ($orders as $one_order) {
                 $fee_total = BfwFunctions::feeOrCoupon($one_order);
                 $fes_totals += $fee_total;
                 // Бонусы с помощью купонов
                 foreach ($one_order->get_coupon_codes() as $coupon_code) {
                     $coupon = new WC_Coupon($coupon_code);
                     $get_code = $coupon->get_code();

                     if (strtolower($get_code) === strtolower($cart_discount)) {
                         $count_fee++;
                     }
                 }
                 // Бонусы с помощью комиссий
                 $count_fee += count($one_order->get_items('fee'));
             }
 */

        echo '<p>' . __('Total spent by users: ', 'bonus-for-woo') . round(
                        $fes_totals,
                        2
                ) . ' ' . BfwPoints::pointsLabel($done44[0]->total_sum);
        '</p>';

        echo '<p>' . sprintf(
                        __('Out of %1$d of orders in %2$d points applied', 'bonus-for-woo'),
                        $count_all_orders,
                        $count_fee
                ) . '</p>';
        //Найти сколько потрачено баллов
        $percent_with_fee = 100 * $count_fee / $count_all_orders;
        $percent_with_fee = round($percent_with_fee);
        echo ' <input type="text" class="knob" value="' . $percent_with_fee . '" data-width="90" data-height="90" data-fgColor="#3c8dbc"
                           data-readonly="true">';
        ?>
        <script>
            jQuery(function () {
                jQuery('.knob').knob({
                        'format': function (value) {
                            return value + '%';
                        }
                    }
                );

            })
        </script>

        <?php
        }
        ?>
    </div>


    <?php if (BfwRoles::isPro() && !empty($val['referal-system'])) { ?>
        <div class="bfw-stat-block" style="width: 300px">
            <h3><?php echo __('Referral statistics', 'bonus-for-woo'); ?></h3>
            <?php


            $args_ref = array(
                    'role__not_in' => $exclude_roles,
                    'meta_query' => array(
                            'key' => 'bfw_points_referral',
                            'value' => '0',
                            'compare' => '!=',
                    ),
            );
            $args_invite = array(
                    'role__not_in' => $exclude_roles,
                    'meta_key' => 'bfw_points_referral_invite',
                    'meta_value' => '0',
                    'meta_compare' => '!=',
            );
            $users = get_users($args_ref);
            //Количество пользователей в реферальной системе
            echo '<p>' . __('Referral system members:', 'bonus-for-woo') . ' '
                    . count($users);
            $users = get_users($args_invite);
            //Количество приглашенных
            echo '<p>' . __('Total invitees:', 'bonus-for-woo') . ' ' . count($users);

            ?>

        </div>

        <?php
    } ?>


</div>
