<?php
/**
 * Модернизированная страница вывода логов
 *
 * @version 8.0.0
 */
defined( 'ABSPATH' ) || exit;
require_once 'datatable.php';

$date_start = $_GET['date_start'] ?? false;
$date_finish = $_GET['date_finish'] ?? gmdate("Y-m-d");
?>

<style>
    :root {
        --bfw-primary: #2271b1;
        --bfw-bg: #f0f2f5;
        --bfw-card-bg: #ffffff;
        --bfw-border: #dcdcde;
        --bfw-text: #1d2327;
        --bfw-text-muted: #646970;
        --bfw-shadow: 0 2px 15px rgba(0,0,0,0.05);
    }

    .bfw-logs-dashboard {
        margin: 20px 20px 20px 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    /* Header */
    .bfw-dashboard-header {
        background: linear-gradient(135deg, #0d1d29 0%, #71c4fc 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }

    .bfw-dashboard-header h1 {
        color: white;
        font-size: 28px;
        margin: 0 0 10px 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .bfw-dashboard-header p { margin: 0; opacity: 0.8; font-size: 15px; }

    /* Filter Card */
    .bfw-filter-card {
        background: white;
        border-radius: 12px;
        padding: 20px 25px;
        border: 1px solid var(--bfw-border);
        box-shadow: var(--bfw-shadow);
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }

    .bfw-filter-form {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .bfw-filter-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .bfw-filter-group label {
        font-weight: 600;
        font-size: 13px;
        color: var(--bfw-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bfw-date-input {
        padding: 8px 12px;
        border: 1px solid var(--bfw-border);
        border-radius: 6px;
        font-size: 14px;
        color: var(--bfw-text);
        transition: border-color 0.2s;
    }
    .bfw-date-input:focus { border-color: var(--bfw-primary); outline: none; }

    .bfw-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        font-size: 14px;
        text-decoration: none;
    }
    .bfw-btn-primary { background: var(--bfw-primary); color: white; }
    .bfw-btn-primary:hover { background: #135e96; }
    .bfw-btn-outline { background: white; color: var(--bfw-text); border: 1px solid var(--bfw-border); }
    .bfw-btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

    /* Table Container */
    .bfw-table-container {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid var(--bfw-border);
        box-shadow: var(--bfw-shadow);
    }

    .bfw-log-notice {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fef2f2;
        color: #b91c1c;
        padding: 8px 15px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 15px;
    }
</style>

<div class="bfw-logs-dashboard">
    <header class="bfw-dashboard-header">
        <h1>
            <span class="dashicons dashicons-media-text" style="font-size: 32px; width:32px; height:32px;"></span>
            <?php echo __('Logs', 'bonus-for-woo'); ?>
        </h1>
        <p><?php _e('Review system activities, errors, and automated processes tracking.', 'bonus-for-woo'); ?></p>
    </header>

    <div class="bfw-filter-card">
        <form class="bfw-filter-form" method="get">
            <input type="hidden" name="page" value="bonus-for-woo/pages/logs.php">
            
            <div class="bfw-filter-group">
                <label><?php echo __('From', 'bonus-for-woo'); ?></label>
                <input type="date" id="date_start" name="date_start" class="bfw-date-input" 
                       value="<?php echo esc_attr($date_start); ?>" 
                       max="<?php echo gmdate("Y-m-d"); ?>" 
                       onchange="bfwchangestart()">
            </div>

            <div class="bfw-filter-group">
                <label><?php echo __('to', 'bonus-for-woo'); ?></label>
                <input type="date" id="date_finish" name="date_finish" class="bfw-date-input" 
                       value="<?php echo esc_attr($date_finish); ?>" 
                       max="<?php echo gmdate("Y-m-d"); ?>" 
                       <?php if (!$date_start) echo 'disabled'; ?>>
            </div>

            <button type="submit" class="bfw-btn bfw-btn-primary">
                <span class="dashicons dashicons-search"></span> <?php echo __('Search', 'bonus-for-woo'); ?>
            </button>
            
            <a class="bfw-btn bfw-btn-outline" href="admin.php?page=bonus-for-woo/pages/logs.php">
                <span class="dashicons dashicons-no-alt"></span> <?php echo __('Clear', 'bonus-for-woo'); ?>
            </a>
        </form>

        <?php if (empty($_GET['date_start'])) : ?>
            <div class="bfw-log-notice">
                <span class="dashicons dashicons-info"></span>
                <?php echo __('The last 500 entries are displayed.', 'bonus-for-woo'); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="bfw-table-container">
        <?php BfwLogs::getListLog($date_start, $date_finish); ?>
    </div>
</div>

<script>
    function bfwchangestart() {
        let start = document.getElementById('date_start');
        let finish = document.getElementById('date_finish');
        if (start.value.length === 10) {
            finish.disabled = false;
            finish.min = start.value;
        } else {
            finish.disabled = true;
        }
    }
</script>