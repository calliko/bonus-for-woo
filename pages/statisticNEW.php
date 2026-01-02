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
        <div class="bfw-stat-block" style="width: 300px">
            <h3><?php _e('Order statistics', 'bonus-for-woo'); ?></h3>
            <button id="bfw-start-order-stats" class="button button-primary"><?php _e('Start Calculation',
                        'bonus-for-woo'); ?></button>
            <div id="bfw-progress-bar" style="margin-top: 10px; display:none">
                <div style="height:20px;background:#eee;border-radius:5px;overflow:hidden;">
                    <div id="bfw-progress-fill" style="height:100%;width:0%;background:#0073aa;"></div>
                </div>
                <p id="bfw-progress-text">0%</p>
            </div>
            <div id="bfw-order-stats-result"></div>
        </div>

        <script>
            jQuery(function ($) {
                $('#bfw-start-order-stats').on('click', function () {
                    $('#bfw-progress-bar').show();
                    let paged = 1;
                    let total_pages = 100; // переопределим потом с сервера
                    let total = 0;
                    let with_bonus = 0;
                    let spent_total = 0;

                    function processStep() {
                        $.post(ajaxurl, {
                            action: 'bfw_calculate_order_stats',
                            paged: paged
                        }, function (res) {
                            if (res.success) {
                                total += res.data.total_orders;
                                with_bonus += res.data.with_bonus;
                                spent_total += res.data.spent;

                                let percent = Math.round((paged / res.data.total_pages) * 100);
                                $('#bfw-progress-fill').css('width', percent + '%');
                                $('#bfw-progress-text').text(percent + '%');
                                paged++;
                                if (paged <= res.data.total_pages) {
                                    processStep();
                                } else {
                                    $('#bfw-order-stats-result').html(
                                        '<p><?php _e('Total orders:', 'bonus-for-woo'); ?> ' + total + '</p>' +
                                        '<p><?php _e('Orders with bonuses:',
                                                'bonus-for-woo'); ?> ' + with_bonus + '</p>' +
                                        '<p><?php echo __('Total spent by users: ',
                                                'bonus-for-woo'); ?> ' + spent_total.toFixed(0) + ' <?php echo BfwPoints::pointsLabel(5);?></p>'
                                    );
                                }
                            } else {
                                alert(res.data.message || 'Error');
                            }
                        });
                    }

                    processStep();
                });
            });
        </script>
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


        global $wpdb;
        $total_in_bfw_names = '';
        $total_in_bfw_count_users = '';

        $rowcount = get_transient('bfw_stat_user_status_count');
        if ($rowcount === false) {
            $rowcount = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}usermeta WHERE meta_key = 'bfw_status'");
            set_transient('bfw_stat_user_status_count', $rowcount, HOUR_IN_SECONDS);
        }


        ?>


    </div>
