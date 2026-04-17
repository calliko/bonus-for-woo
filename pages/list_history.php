<?php
/**
 * Модернизированная страница истории всех начислений баллов
 *
 * @version 5.9.0
 */
defined( 'ABSPATH' ) || exit;

// Обработчик удаления записи
if (isset($_POST['bfw_delete_post_history_points'])) {
    if (!isset($_POST['bfw_nonce_history']) || !wp_verify_nonce($_POST['bfw_nonce_history'], 'bfw_action_history')) {
        wp_die('Security Error');
    }
    BfwHistory::deleteHistoryId(sanitize_text_field($_POST['bfw_delete_post_history_points']));
    echo '<div class="bfw-notice bfw-notice-success is-dismissible"><p>' . __('Record deleted successfully.', 'bonus-for-woo') . '</p></div>';
}

$date_start = $_GET['date_start'] ?? false;
$date_finish = $_GET['date_finish'] ?? current_time("Y-m-d");

?>
<style>
    :root {
        --bfw-primary: #2271b1;
        --bfw-border: #dcdcde;
        --bfw-card-bg: #ffffff;
        --bfw-success: #2ecc71;
        --bfw-danger: #e74c3c;
        --bfw-text-muted: #646970;
    }

    .bfw-admin-wrap {
        margin: 20px 20px 0 0;
    }

    .bfw-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .bfw-header h1 {
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Специфические стили для таблицы WP */
    .wp-list-table {
        border: 1px solid var(--bfw-border) !important;
        border-radius: 8px !important;
        overflow: hidden;
        background: var(--bfw-card-bg) !important;
    }

    .wp-list-table thead th {
        background: #f8f9fa !important;
        font-weight: 600 !important;
        padding-top: 15px !important;
        padding-bottom: 15px !important;
    }

    .wp-list-table tbody td {
        padding-top: 12px !important;
        padding-bottom: 12px !important;
        vertical-align: middle !important;
    }

    /* Подсветка баллов */
    .column-points {
        font-weight: 700 !important;
        font-size: 15px !important;
    }

    .column-points:contains('+') {
        color: var(--bfw-success) !important;
    }

    .column-points:contains('-') {
        color: var(--bfw-danger) !important;
    }

    /* Форма фильтра */
    .bfw-filter-bar {
        background: #fff;
        padding: 20px;
        border: 1px solid var(--bfw-border);
        border-radius: 10px;
        margin-bottom: 25px;
        display: flex;
        align-items: flex-end;
        gap: 20px;
    }

    .bfw-filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .bfw-filter-group label {
        font-weight: 600;
        font-size: 13px;
        color: var(--bfw-text-muted);
    }

    .bfw-input-date {
        height: 38px;
        border: 1px solid var(--bfw-border);
        border-radius: 6px;
        padding: 0 10px;
        min-width: 160px;
    }

    .bfw-btn {
        height: 38px;
        padding: 0 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s;
    }

    .bfw-btn-primary {
        background: var(--bfw-primary);
        color: #fff;
        border: none;
    }
    
    .bfw-btn-primary:hover { color: #fff; opacity: 0.9; }

    .bfw-btn-outline {
        background: #fff;
        border: 1px solid var(--bfw-border);
        color: #1d2327;
    }

    .bfw-btn-outline:hover { background: #f6f7f7; }
</style>

<div class="bfw-admin-wrap">
    <div class="bfw-header">
        <h1>
            <span class="dashicons dashicons-calendar-alt" style="font-size: 30px; width:30px; height:30px;"></span>
            <?php  echo sprintf(
                __('History of %s for all customers', 'bonus-for-woo'),
                BfwPoints::pointsLabel(5)
        ); ?>
        </h1>
    </div>

    <form method="get" class="bfw-filter-bar">
        <input type="hidden" name="page" value="bonus-for-woo/pages/list_history.php">
        
        <div class="bfw-filter-group">
            <label><?php _e('From', 'bonus-for-woo'); ?></label>
            <input type="date" id="date_start" name="date_start" class="bfw-input-date" 
                   value="<?php echo esc_html($date_start); ?>" 
                   max="<?php echo current_time("Y-m-d"); ?>" 
                   onchange="bfwChangeStart()">
        </div>

        <div class="bfw-filter-group">
            <label><?php _e('to', 'bonus-for-woo'); ?></label>
            <input type="date" id="date_finish" name="date_finish" class="bfw-input-date" 
                   value="<?php echo esc_html($date_finish); ?>" 
                   max="<?php echo current_time("Y-m-d"); ?>" 
                   <?php echo !$date_start ? 'disabled' : ''; ?>>
        </div>

        <div style="display:flex; gap: 10px;">
            <button type="submit" class="bfw-btn bfw-btn-primary">
                <span class="dashicons dashicons-search"></span>
                <?php _e('Search', 'bonus-for-woo'); ?>
            </button>
            <a href="/wp-admin/admin.php?page=bonus-for-woo%2Fpages%2Flist_history.php" class="bfw-btn bfw-btn-outline">
                <?php _e('Reset', 'bonus-for-woo'); ?>
            </a>
        </div>
    </form>

    <div class="bfw-table-wrap">
        <?php
        $history_table = new Bfw_History_List_Table();
        $history_table->prepare_items();
        ?>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page'] ?? ''); ?>" />
            <?php $history_table->display(); ?>
        </form>
    </div>
</div>

<script>
    function bfwChangeStart() {
        let start = document.getElementById('date_start');
        let finish = document.getElementById('date_finish');
        if (start.value.length === 10) {
            finish.disabled = false;
            finish.min = start.value;
        } else {
            finish.disabled = true;
        }
    }

    // JS-хук для подсветки баллов (так как в CSS :contains не поддерживается большинством браузеров без JS/JQ)
    jQuery(function($) {
        $('.column-points').each(function() {
            let text = $(this).text();
            if (text.indexOf('+') !== -1) {
                $(this).css('color', 'var(--bfw-success)');
            } else if (text.indexOf('-') !== -1) {
                $(this).css('color', 'var(--bfw-danger)');
            }
        });
    });
</script>
