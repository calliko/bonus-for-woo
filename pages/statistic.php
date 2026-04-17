<?php
defined( 'ABSPATH' ) || exit;
?>
<style>
    :root {
        --bfw-primary: #2271b1;
        --bfw-success: #00a65a;
        --bfw-warning: #f0b849;
        --bfw-bg: #f6f7f7;
        --bfw-card-bg: #ffffff;
        --bfw-text-main: #1d2327;
        --bfw-text-muted: #646970;
        --bfw-border: #dcdcde;
    }

    .bfw-stat-wrap {
        max-width: 1200px;
        margin-top: 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    .bfw-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .bfw-card {
        background: var(--bfw-card-bg);
        border: 1px solid var(--bfw-border);
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        position: relative;
        transition: all 0.3s ease;
    }

    .bfw-card:hover {
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .bfw-card::after {
        content: '';
        position: absolute;
        left: 0;
        top: 20px;
        bottom: 20px;
        width: 4px;
        background: var(--bfw-primary);
        border-radius: 0 4px 4px 0;
        opacity: 0.3;
    }

    .bfw-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .bfw-card-title {
        color: var(--bfw-text-muted);
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .bfw-card-icon {
        background: #f0f6fa;
        color: var(--bfw-primary);
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }

    .bfw-card-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--bfw-text-main);
        margin-bottom: 10px;
    }

    .bfw-card-footer {
        font-size: 13px;
        color: var(--bfw-text-muted);
        line-height: 1.4;
    }

    .bfw-trend {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
        margin-right: 5px;
    }

    .bfw-trend-up { background: #eefaf3; color: var(--bfw-success); }
    .bfw-trend-blue { background: #f0f6fa; color: var(--bfw-primary); }

    .bfw-section-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    @media (max-width: 960px) {
        .bfw-section-grid { grid-template-columns: 1fr; }
    }

    .bfw-health-bar {
        background: #f0f0f1;
        height: 12px;
        border-radius: 6px;
        overflow: hidden;
        margin: 15px 0;
    }

    .bfw-health-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--bfw-primary), var(--bfw-success));
        transition: width 1s ease-in-out;
    }

    .bfw-badge-time {
        background: #fff;
        border: 1px solid var(--bfw-border);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        color: var(--bfw-text-muted);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    #bfw-recalc-progress {
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        border: 1px solid var(--bfw-border);
        margin-top: 30px;
    }
</style>

<div class="wrap bfw-stat-wrap">
    <h1 style="font-weight: 700; margin-bottom: 25px;"><?php _e('Advanced Business Analytics', 'bonus-for-woo'); ?></h1>

    <?php
    $stats = BfwStatistic::get_stats();
    if ($stats) : ?>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;">
            <div class="bfw-badge-time">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php _e('Range:', 'bonus-for-woo'); ?> 
                <strong><?php echo !empty($stats['date_start']) ? esc_html($stats['date_start']) : 'All time'; ?> - <?php echo !empty($stats['date_end']) ? esc_html($stats['date_end']) : 'All time'; ?></strong>
                <span style="font-size: 11px; margin-left:10px;">(Upd: <?php echo date_i18n('H:i', $stats['timestamp']); ?>)</span>
            </div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="date" id="bfw-date-start" class="regular-text" value="<?php echo esc_attr($stats['date_start'] ?? date('Y-m-d', strtotime('-30 days'))); ?>" style="height:35px; width: 140px;">
                <span>&mdash;</span>
                <input type="date" id="bfw-date-end" class="regular-text" value="<?php echo esc_attr($stats['date_end'] ?? date('Y-m-d')); ?>" style="height:35px; width: 140px;">
                <button id="bfw-clear-stats" class="button"><?php _e('Reset', 'bonus-for-woo'); ?></button>
                <button id="bfw-recalc-start" class="button button-primary" style="height: auto; padding: 6px 20px;"><?php _e('Refresh Analytics', 'bonus-for-woo'); ?></button>
            </div>
        </div>

        <!-- Ряд 1: Главные деньги -->
        <div class="bfw-dashboard-grid">
            <div class="bfw-card">
                <div class="bfw-card-header">
                    <span class="bfw-card-title"><?php _e('Lifetime Value (LTV)', 'bonus-for-woo'); ?></span>
                    <div class="bfw-card-icon"><span class="dashicons dashicons-id"></span></div>
                </div>
                <div class="bfw-card-value"><?php echo wc_price($stats['ltv_bonus'] ?? 0); ?></div>
                <div class="bfw-card-footer">
                    <?php echo __('Average total revenue from each participant of the bonus system.', 'bonus-for-woo'); ?>
                </div>
            </div>

            <div class="bfw-card">
                <div class="bfw-card-header">
                    <span class="bfw-card-title"><?php _e('Referral Revenue', 'bonus-for-woo'); ?></span>
                    <div class="bfw-card-icon"><span class="dashicons dashicons-groups"></span></div>
                </div>
                <div class="bfw-card-value"><?php echo wc_price($stats['referral_revenue'] ?? 0); ?></div>
                <div class="bfw-card-footer">
                    <?php _e('Total money brought by invited friends.', 'bonus-for-woo'); ?>
                </div>
            </div>

            <div class="bfw-card">
                <div class="bfw-card-header">
                    <span class="bfw-card-title"><?php _e('Customer Retention', 'bonus-for-woo'); ?></span>
                    <div class="bfw-card-icon"><span class="dashicons dashicons-update"></span></div>
                </div>
                <div class="bfw-card-value"><?php echo round($stats['retention_rate'] ?? 0, 1); ?>%</div>
                <div class="bfw-card-footer">
                    <?php _e('Percentage of loyal customers who make repeat purchases.', 'bonus-for-woo'); ?>
                </div>
            </div>

            <div class="bfw-card">
                <div class="bfw-card-header">
                    <span class="bfw-card-title"><?php _e('Point Liability', 'bonus-for-woo'); ?></span>
                    <div class="bfw-card-icon"><span class="dashicons dashicons-money-alt"></span></div>
                </div>
                <div class="bfw-card-value"><?php echo wc_price($stats['points_total'] ?? 0); ?></div>
                <div class="bfw-card-footer">
                    <?php _e('Current financial "debt" in points sitting on user balances.', 'bonus-for-woo'); ?>
                </div>
            </div>

            <div class="bfw-card">
                <div class="bfw-card-header">
                    <span class="bfw-card-title"><?php _e('Pending Points', 'bonus-for-woo'); ?></span>
                    <div class="bfw-card-icon"><span class="dashicons dashicons-clock"></span></div>
                </div>
                <div class="bfw-card-value" style="color: var(--bfw-warning);"><?php echo number_format($stats['pending_points_total'] ?? 0, 0, '.', ' '); ?></div>
                <div class="bfw-card-footer">
                    <?php _e('Points awaiting accrual — orders currently in Processing status.', 'bonus-for-woo'); ?>
                </div>
            </div>

            <div class="bfw-card">
                <div class="bfw-card-header">
                    <span class="bfw-card-title"><?php _e('Burn Rate', 'bonus-for-woo'); ?></span>
                    <div class="bfw-card-icon"><span class="dashicons dashicons-chart-line"></span></div>
                </div>
                <?php $burn = round($stats['prr'] ?? 0, 1); ?>
                <div class="bfw-card-value" style="color: <?php echo $burn >= 20 && $burn <= 50 ? 'var(--bfw-success)' : 'var(--bfw-warning)'; ?>"><?php echo $burn; ?>%</div>
                <div class="bfw-card-footer">
                    <?php _e('Share of issued points that users actually spend. 20–40% is healthy.', 'bonus-for-woo'); ?>
                </div>
            </div>

            <div class="bfw-card">
                <div class="bfw-card-header">
                    <span class="bfw-card-title"><?php _e('Program Penetration', 'bonus-for-woo'); ?></span>
                    <div class="bfw-card-icon"><span class="dashicons dashicons-admin-users"></span></div>
                </div>
                <div class="bfw-card-value" style="color: var(--bfw-primary);"><?php echo round($stats['penetration'] ?? 0, 1); ?>%</div>
                <div class="bfw-card-footer">
                    <?php _e('Share of all active customers using the bonus system.', 'bonus-for-woo'); ?>
                </div>
            </div>
        </div>

        <div class="bfw-section-grid">
            <!-- Здоровье системы -->
            <div class="bfw-card">
                <h3 style="margin-top: 0; margin-bottom: 20px;"><?php _e('Ecosystem Health', 'bonus-for-woo'); ?></h3>
                <div style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                        <span style="font-weight: 600;"><?php _e('Redemption Rate', 'bonus-for-woo'); ?></span>
                        <span style="font-size: 24px; font-weight: 800; color: var(--bfw-primary);"><?php echo round($stats['prr'] ?? 0, 1); ?>%</span>
                    </div>
                    <div class="bfw-health-bar"><div class="bfw-health-fill" style="width: <?php echo min(100, $stats['prr'] ?? 0); ?>%"></div></div>
                    <p style="font-size: 13px; color: var(--bfw-text-muted);">
                        <?php _e('This shows how actively users spend their points. 20-40% is healthy for most stores.', 'bonus-for-woo'); ?>
                    </p>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; padding-top: 15px; border-top: 1px solid var(--bfw-border);">
                    <div>
                        <small style="color: var(--bfw-text-muted); display: block; margin-bottom: 4px;"><?php _e('Total Issued', 'bonus-for-woo'); ?></small>
                        <strong style="font-size: 16px;"><?php echo number_format($stats['issued_total_life'] ?? 0, 0, '.', ' '); ?></strong>
                    </div>
                    <div>
                        <small style="color: var(--bfw-text-muted); display: block; margin-bottom: 4px;"><?php _e('Total Spent', 'bonus-for-woo'); ?></small>
                        <strong style="font-size: 16px;"><?php echo number_format($stats['spent_total_life'] ?? 0, 0, '.', ' '); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Распределение уровней -->
            <div class="bfw-card">
                <h3 style="margin-top: 0; margin-bottom: 20px;"><?php _e('Customer Segmentation', 'bonus-for-woo'); ?></h3>
                <?php 
                $labels = []; $counts = [];
                global $wpdb;
                if (!empty($stats['user_status'])) {
                    $bfw_records = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}bfw_computy WHERE id IN (" . implode(',', array_map('intval', array_keys($stats['user_status']))) . ")");
                    $names_map = wp_list_pluck($bfw_records, 'name', 'id');
                    foreach ($stats['user_status'] as $id => $count) {
                        $labels[] = $names_map[$id] ?? __('Level', 'bonus-for-woo') . ' ' . $id;
                        $counts[] = $count;
                    }
                }
                ?>
                <div style="height: 220px;"><canvas id="bfwMainChart"></canvas></div>
            </div>
        </div>

        <div class="bfw-section-grid" style="grid-template-columns: 1fr; margin-bottom: 20px;">
             <!-- Динамика баллов -->
             <div class="bfw-card">
                 <h3 style="margin-top: 0; margin-bottom: 20px;"><?php _e('Points Flow Dynamics', 'bonus-for-woo'); ?></h3>
                 <div style="height: 350px;"><canvas id="bfwTrendChart"></canvas></div>
             </div>
        </div>

        <div class="bfw-section-grid">
             <!-- Сводка оборота -->
             <div class="bfw-card">
                <h3 style="margin-top: 0; margin-bottom: 20px;"><?php _e('Revenue Breakdown', 'bonus-for-woo'); ?></h3>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span><?php _e('Total Store Revenue', 'bonus-for-woo'); ?></span>
                        <strong><?php echo wc_price($stats['total_revenue'] ?? 0); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--bfw-text-muted);">
                        <span><?php _e('From orders with bonus discount', 'bonus-for-woo'); ?></span>
                        <span><?php echo wc_price($stats['total_revenue'] - ($stats['rev_no_bonus'] ?? ($stats['total_revenue'] / 2))); ?></span>
                    </div>
                </div>
                
                <div style="padding-top: 15px; border-top: 1px solid var(--bfw-border);">
                     <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600;"><?php _e('AOV Increase', 'bonus-for-woo'); ?></span>
                        <?php 
                        $aov_no = $stats['aov_no_bonus'] ?? 0;
                        $aov_with = $stats['aov_with_bonus'] ?? 0;
                        $increase = ($aov_no > 0) ? (($aov_with - $aov_no) / $aov_no) * 100 : 0;
                        ?>
                        <span class="bfw-trend bfw-trend-up" style="font-size: 14px;">+<?php echo round($increase, 1); ?>%</span>
                     </div>
                     <p style="font-size: 12px; color: var(--bfw-text-muted); margin-top: 5px;">
                        <?php echo sprintf(__('Orders with bonuses are on average %s more expensive.', 'bonus-for-woo'), round($increase, 1) . '%'); ?>
                     </p>
                </div>
             </div>

             <!-- Реферальная активность -->
             <div class="bfw-card">
                <h3 style="margin-top: 0; margin-bottom: 20px;"><?php _e('Viral Growth (Referrals)', 'bonus-for-woo'); ?></h3>
                <?php if (BfwRoles::isPro() && BfwSetting::get('referal-system')) : ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <small style="color: var(--bfw-text-muted);"><?php _e('Ambassadors', 'bonus-for-woo'); ?></small>
                            <div style="font-size: 20px; font-weight: 700;"><?php echo $stats['referrals_total'] ?? 0; ?></div>
                        </div>
                        <div>
                            <small style="color: var(--bfw-text-muted);"><?php _e('Friends Invited', 'bonus-for-woo'); ?></small>
                            <div style="font-size: 20px; font-weight: 700;"><?php echo $stats['referrals_invited'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div style="margin-top: 20px; background: #fdfaf2; padding: 12px; border-radius: 8px; border-left: 4px solid var(--bfw-warning); font-size: 13px;">
                        <?php echo sprintf(__('Referral system has generated %s in sales.', 'bonus-for-woo'), '<strong>' . wc_price($stats['referral_revenue'] ?? 0) . '</strong>'); ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--bfw-text-muted); text-align: center; padding: 20px 0;">
                        <?php _e('Referral system is currently disabled.', 'bonus-for-woo'); ?>
                    </p>
                <?php endif; ?>
             </div>
             
             <!-- Топ клиентов -->
             <div class="bfw-card">
                 <h3 style="margin-top: 0; margin-bottom: 20px;"><?php _e('Top 5 VIP Customers', 'bonus-for-woo'); ?></h3>
                 <table style="width: 100%; border-collapse: collapse; text-align: left;">
                     <thead>
                         <tr style="border-bottom: 2px solid var(--bfw-border); color: var(--bfw-text-muted);">
                             <th style="padding: 10px 5px;"><?php _e('User', 'bonus-for-woo'); ?></th>
                             <th style="padding: 10px 5px;"><?php _e('Revenue', 'bonus-for-woo'); ?></th>
                             <th style="padding: 10px 5px;"><?php _e('Orders', 'bonus-for-woo'); ?></th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php if (!empty($stats['top_customers'])) : foreach ($stats['top_customers'] as $vip) : 
                               $u = get_userdata($vip['id']);
                               $name = $u ? $u->display_name : 'User #'.$vip['id'];
                         ?>
                         <tr style="border-bottom: 1px solid #eee;">
                             <td style="padding: 12px 5px; font-weight: 600;"><?php echo esc_html($name); ?></td>
                             <td style="padding: 12px 5px; color: var(--bfw-success);"><?php echo wc_price($vip['revenue']); ?></td>
                             <td style="padding: 12px 5px;"><?php echo $vip['orders']; ?></td>
                         </tr>
                         <?php endforeach; else: ?>
                         <tr><td colspan="3" style="padding: 15px; text-align: center;"><?php _e('Not enough data.', 'bonus-for-woo'); ?></td></tr>
                         <?php endif; ?>
                     </tbody>
                 </table>
             </div>
        </div>

        <script>
        jQuery(function($) {
            if (typeof Chart !== 'undefined' && document.getElementById('bfwMainChart')) {
                new Chart($('#bfwMainChart'), {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($counts); ?>,
                            backgroundColor: ['#2271b1', '#3498db', '#1abc9c', '#2ecc71', '#9b59b6', '#f0b849', '#e67e22'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        cutoutPercentage: 70,
                        legend: { position: 'right', labels: { usePointStyle: true, padding: 15 } }
                    }
                });
            }

            // Trend Line Chart
            if (typeof Chart !== 'undefined' && document.getElementById('bfwTrendChart')) {
                new Chart($('#bfwTrendChart'), {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($stats['chart_data']['labels'] ?? []); ?>,
                        datasets: [
                            {
                                label: '<?php _e('Issued Points (+)', 'bonus-for-woo'); ?>',
                                data: <?php echo json_encode($stats['chart_data']['issued'] ?? []); ?>,
                                borderColor: '#00a65a',
                                backgroundColor: 'rgba(0, 166, 90, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3
                            },
                            {
                                label: '<?php _e('Spent Points (-)', 'bonus-for-woo'); ?>',
                                data: <?php echo json_encode($stats['chart_data']['spent'] ?? []); ?>,
                                borderColor: '#f0b849',
                                backgroundColor: 'rgba(240, 184, 73, 0.1)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        legend: { position: 'top' },
                        scales: {
                            yAxes: [{ ticks: { beginAtZero: true } }]
                        }
                    }
                });
            }
        });
        </script>

    <?php else : ?>
        <div style="text-align: center; padding: 80px 20px; background: #fff; border-radius: 12px; border: 1px solid var(--bfw-border);">
            <span class="dashicons dashicons-analytics" style="font-size: 80px; width: 80px; height: 80px; color: #ccd0d4; margin-bottom: 20px;"></span>
            <h2><?php _e('Analytics is ready to work', 'bonus-for-woo'); ?></h2>
            <p><?php _e('Calculate your first report to unlock insights about LTV, Retention and Referral ROI.', 'bonus-for-woo'); ?></p>
            <button id="bfw-recalc-start" class="button button-primary button-large" style="margin-top: 20px; height: auto; padding: 10px 40px;"><?php _e('Start Initial Calculation', 'bonus-for-woo'); ?></button>
        </div>
    <?php endif; ?>

    <div id="bfw-recalc-progress" style="display:none;">
        <h3 style="margin-top: 0;"><?php _e('Deep Data Analysis', 'bonus-for-woo'); ?>...</h3>
        <div style="background: #f0f0f1; height: 10px; border-radius: 5px; overflow: hidden; margin: 15px 0;">
            <div id="bfw-progress-inner" style="width: 0%; background: var(--bfw-primary); height: 100%; transition: width 0.3s;"></div>
        </div>
        <p id="bfw-progress-text" style="font-weight: 600;"><?php _e('Processing...', 'bonus-for-woo'); ?></p>
    </div>
</div>

<?php
wp_enqueue_style('chart-css', BONUS_COMPUTY_PLUGIN_URL . '_inc/chart/Chart.min.css', array(), BONUS_COMPUTY_VERSION);
wp_enqueue_script('chart-js', BONUS_COMPUTY_PLUGIN_URL . '_inc/chart/Chart.min.js', array('jquery'), BONUS_COMPUTY_VERSION);
?>

<script>
jQuery(function ($) {
    let step = 0;
    const totalSteps = 5;

    function updateProgress(step) {
        let percent = Math.floor((step / totalSteps) * 100);
        $('#bfw-progress-inner').css('width', percent + '%');
        const msgs = [
            '<?php _e('Analyzing user statuses...', 'bonus-for-woo'); ?>',
            '<?php _e('Calculating Point Redemption Rate...', 'bonus-for-woo'); ?>',
            '<?php _e('Auditing Referral network and Revenue...', 'bonus-for-woo'); ?>',
            '<?php _e('Correlating Retention and LTV metrics...', 'bonus-for-woo'); ?>',
            '<?php _e('Rendering dynamic timeline...', 'bonus-for-woo'); ?>'
        ];
        $('#bfw-progress-text').text(msgs[step] || '<?php _e('Finalizing...', 'bonus-for-woo'); ?>');
    }

    $('#bfw-recalc-start').on('click', function () {
        step = 0;
        $('#bfw-recalc-progress').fadeIn();
        $(this).prop('disabled', true);
        runStep();
    });

    function runStep() {
        $.post(ajaxurl, { 
            action: 'bfw_stat_step', 
            step: step,
            date_start: $('#bfw-date-start').val(),
            date_end: $('#bfw-date-end').val(),
            nonce: '<?php echo wp_create_nonce("bfw_stat_nonce"); ?>'
        }, function (res) {
            if (res.success) {
                step++;
                updateProgress(step);
                if (step < totalSteps) {
                    setTimeout(runStep, 100);
                } else {
                    $('#bfw-progress-text').text('✅ <?php _e('Analysis Complete! Reloading...', 'bonus-for-woo'); ?>');
                    setTimeout(() => window.location.reload(), 1000);
                }
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Analysis error. Please check logs.');
                $('#bfw-recalc-start').prop('disabled', false);
            }
        });
    }

    $('#bfw-clear-stats').on('click', function () {
        if (!confirm('<?php _e('Clear stats cache?', 'bonus-for-woo'); ?>')) return;
        $.post(ajaxurl, { 
            action: 'bfw_clear_stats',
            nonce: '<?php echo wp_create_nonce("bfw_stat_nonce"); ?>'
        }, function() { window.location.reload(); });
    });
});
</script>
