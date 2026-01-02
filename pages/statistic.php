<?php
defined( 'ABSPATH' ) || exit;
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
    <?php
    $stats = BfwStatistic::get_stats();
    if ($stats) {
    echo '<p><strong>' . __('Last statistics update:', 'bonus-for-woo') . '</strong> 
' .
            date_i18n(get_option('date_format') . ' H:i', $stats['timestamp']) . '
<button id="bfw-clear-stats" class="button">' . __('Clear statistics', 'bonus-for-woo') . '</button></p> 

<div class="bfw-stat-wrap">';
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


    global $wpdb;
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
<?php

?>

<div class="bfw-stat-block" style="width: 300px">
    <h3><?php
        echo __(
                        'Total amount of points in users accounts:',
                        'bonus-for-woo'
                ) . ' ' . number_format($stats['points_total'], 0, '', ' ') . ' ';
        echo BfwPoints::pointsLabel($stats['points_total']); ?></h3>
    <h3><?php echo __('Order statistics', 'bonus-for-woo'); ?></h3>
    <?php echo '<p>' . __('Total spent by users: ', 'bonus-for-woo') . round(
                    $stats['spent_total'], 2) . ' ' . BfwPoints::pointsLabel($stats['spent_total']);
    '</p>';

    echo '<p>' . sprintf(
                    __('Out of %1$d of orders in %2$d points applied', 'bonus-for-woo'),
                    $stats['orders_total'],
                    $stats['orders_with_bonus']
            ) . '</p>';

    $percent_with_fee = 100 * $stats['orders_with_bonus'] / $stats['orders_total'];
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

</div>

<?php if (BfwRoles::isPro() && BfwSetting::get('referal-system')) { ?>
    <div class="bfw-stat-block" style="width: 300px">
        <h3><?php echo __('Referral statistics', 'bonus-for-woo'); ?></h3>
        <?php
        echo '<p>' . __('Referral system members:', 'bonus-for-woo') . ' '
                . $stats['referrals_total'] . '</p>';

        echo '<p>' . __('Total invitees:', 'bonus-for-woo') . ' ' . $stats['referrals_invited'] . '</p>';
        ?>

    </div>

    <?php
}
?>
</div>
<?php

}

?>

<button id="bfw-recalc-start" class="button button-primary"><?php echo __('Recalculate statistics',
            'bonus-for-woo') ?></button>
<div id="bfw-recalc-progress" style="display:none; margin-top:10px;">
    <div id="bfw-progress-bar" style="width: 100%; background: #e0e0e0; border-radius: 4px;">
        <div id="bfw-progress-inner" style="width: 0%; background: #2271b1; height: 20px; border-radius: 4px;"></div>
    </div>
    <p id="bfw-progress-text" style="margin-top: 5px;"><?php echo __('Start of calculations', 'bonus-for-woo'); ?>
        ...</p>
</div>

<script>
    jQuery(function ($) {
        let step = 0;
        let paged = 1;
        const totalSteps = 4;
        let ordersMaxPages = 1; // получим с сервера

        function updateProgress(step, paged, maxPaged) {
            let percent;

            if (step < 3) {
                percent = Math.floor((step / totalSteps) * 100);
            } else if (step === 3 && maxPaged > 0) {
                const pageProgress = (paged - 1) / maxPaged;
                percent = Math.floor(((step + pageProgress) / totalSteps) * 100);
            } else {
                percent = Math.floor((step / totalSteps) * 100);
            }

            percent = Math.min(percent, 100);
            $('#bfw-progress-inner').css('width', percent + '%');
            $('#bfw-progress-text').text('<?php echo __('Progress', 'bonus-for-woo'); ?>: ' + percent + '%');
            if (percent === 100) {
                $('#bfw-progress-text').text('<?php echo __('Please wait until the page reloads!',
                        'bonus-for-woo'); ?>');
            }
        }

        function runStep() {
            $.post(ajaxurl, {
                action: 'bfw_stat_step',
                step: step,
                paged: paged
            }, function (res) {
                if (res.success) {
                    if (step === 3 && res.data.paged > 0) {
                        paged = res.data.paged;
                        if (res.data.max_pages) ordersMaxPages = res.data.max_pages;
                        updateProgress(step, paged, ordersMaxPages);
                        setTimeout(runStep, 200);
                    } else {
                        step++;
                        paged = 1;
                        updateProgress(step, 0, ordersMaxPages);
                        if (step < totalSteps) {
                            setTimeout(runStep, 200);
                        } else {
                            $('#bfw-progress-text').text('✅ <?php echo __('Statistics updated.', 'bonus-for-woo'); ?>');
                            window.location.reload();
                        }
                    }
                } else {
                    $('#bfw-progress-text').text('❌ <?php echo __('Error.',
                            'bonus-for-woo'); ?>: ' + (res.data?.message || 'Неизвестно'));
                }
            });
        }

        $('#bfw-recalc-start').on('click', function () {
            step = 0;
            paged = 1;
            $('#bfw-recalc-progress').show();
            updateProgress(0, 0);
            runStep();
        });


        $('#bfw-clear-stats').on('click', function () {
            if (!confirm('<?php echo __('Are you sure you want to clear statistics?', 'bonus-for-woo'); ?>')) return;

            $.post(ajaxurl, {
                action: 'bfw_clear_stats'
            }, function (res) {
                if (res.success) {

                    $('#bfw-progress-text').text('<?php echo __('Statistics reset', 'bonus-for-woo'); ?>');
                    $('#bfw-progress-inner').css('width', '0%');
                    window.location.reload();
                } else {
                    alert('❌ <?php echo __('Error.', 'bonus-for-woo'); ?>: ' + (res.data?.message || 'Неизвестно'));
                }
            });
        });


    });
</script>



