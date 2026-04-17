<?php
defined( 'ABSPATH' ) || exit;

/**
 * Модернизированная страница купонов
 *
 * @version 8.0.0
 */

/* Обработка форм купонов (логика сохранена без изменений) */
if (isset($_POST['bfw_computy_add_coupon_ajax'])) {
    if ($_POST['bfw_computy_add_coupon_ajax'] === 'bfw_computy_add_coupon_ajax') {
        if (!isset($_POST['bfw_nonce_coupon']) || !wp_verify_nonce($_POST['bfw_nonce_coupon'], 'bfw_action_coupon')) {
            wp_die('Ошибка безопасности!');
        }
        global $wpdb;
        $code = sanitize_text_field($_POST['code']);
        $sum = sanitize_text_field($_POST['sum']);
        $comment_admin = sanitize_text_field($_POST['comment_admin']);
        $status = sanitize_text_field($_POST['status']);
        $reusable = $_POST['reusable'] ?? 0;

        if ($wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "bfw_coupons_computy WHERE `code` = %s", $code))) {
            echo '<div class="bfw-notice bfw-notice-warning"><span class="dashicons dashicons-warning"></span> ' . sprintf(__('Coupon <b>%s</b> is already being used.', 'bonus-for-woo'), $code) . '</div>';
        } else {
            BfwCoupons::addCoupon($code, $sum, $comment_admin, $status, $reusable);
            echo '<div class="bfw-notice bfw-notice-success"><span class="dashicons dashicons-yes-alt"></span> ' . __('Coupon', 'bonus-for-woo') . ' <b>' . $code . '</b> ' . __('added', 'bonus-for-woo') . '.</div>';
        }
    }
}

if (isset($_POST['bfw_delete_coupon'])) {
    if (!isset($_POST['bfw_nonce_coupon']) || !wp_verify_nonce($_POST['bfw_nonce_coupon'], 'bfw_action_coupon')) {
        wp_die('Ошибка безопасности!');
    }
    BfwCoupons::deleteCoupon($_POST['bfw_delete_coupon']);
    echo '<div class="bfw-notice bfw-notice-warning"><span class="dashicons dashicons-trash"></span> ' . __('deleted', 'bonus-for-woo') . '.</div>';
}

if (isset($_POST['bfw_edit_status_coupon'])) {
    if (!isset($_POST['bfw_nonce_coupon']) || !wp_verify_nonce($_POST['bfw_nonce_coupon'], 'bfw_action_coupon')) {
        wp_die('Ошибка безопасности!');
    }
    $status = (sanitize_text_field($_POST['status_coupon']) == 'active') ? 'noactive' : 'active';
    BfwCoupons::editStatusCoupon($_POST['bfw_edit_status_coupon'], $status);
    echo '<div class="bfw-notice bfw-notice-warning"><span class="dashicons dashicons-update"></span> ' . __('Coupon status changed', 'bonus-for-woo') . '.</div>';
}
?>

<style>
    :root {
        --bfw-primary: #2271b1;
        --bfw-bg: #f0f2f5;
        --bfw-card-bg: #ffffff;
        --bfw-border: #dcdcde;
        --bfw-text: #1d2327;
        --bfw-text-muted: #646970;
        --bfw-success: #2ecc71;
        --bfw-warning: #f39c12;
        --bfw-danger: #e74c3c;
        --bfw-shadow: 0 2px 15px rgba(0,0,0,0.05);
    }

    .bfw-coupons-dashboard {
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
        box-shadow: 0 10px 25px rgba(34, 113, 177, 0.15);
    }

    .bfw-dashboard-header h1 {
        color: white;
        font-size: 28px;
        margin: 0 0 10px 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .bfw-dashboard-header p { margin: 0; opacity: 0.9; font-size: 15px; }

    /* Action Cards Grid */
    .bfw-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .bfw-action-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        border: 1px solid var(--bfw-border);
        box-shadow: var(--bfw-shadow);
    }

    .bfw-action-card h3 {
        margin: 0 0 20px 0;
        padding-bottom: 12px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
        color: var(--bfw-text);
    }

    /* Form Styles */
    .bfw-form-group { margin-bottom: 15px; }
    .bfw-form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; }
    
    .bfw-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--bfw-border);
        border-radius: 8px;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    .bfw-input:focus { border-color: var(--bfw-primary); outline: none; box-shadow: 0 0 0 1px var(--bfw-primary); }

    .bfw-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        font-size: 14px;
        text-decoration: none;
    }
    .bfw-btn-primary { background: var(--bfw-primary); color: white; }
    .bfw-btn-primary:hover { background: #135e96; transform: translateY(-1px); }

    /* Notices */
    .bfw-notice {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        border-left: 5px solid;
    }
    .bfw-notice-success { background: #ecfdf5; border-color: var(--bfw-success); color: #065f46; }
    .bfw-notice-warning { background: #fff7ed; border-color: var(--bfw-warning); color: #9a3412; }

    /* Table Container */
    .bfw-table-container {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid var(--bfw-border);
        box-shadow: var(--bfw-shadow);
    }

    /* Import Section */
    .bfw-import-box {
        background: #f8fafc;
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
    }
    .file-input-wrapper { margin: 15px 0; }
</style>

<div class="bfw-coupons-dashboard">
    <header class="bfw-dashboard-header">
        <h1>
            <span class="dashicons dashicons-tickets" style="font-size: 32px; width:32px; height:32px;"></span>
            <?php echo sprintf(__('Coupons for %s ', 'bonus-for-woo'), BfwPoints::pointsLabel(5)); ?>
        </h1>
        <p><?php _e('Create, import and manage loyalty bonus coupons for your customers.', 'bonus-for-woo'); ?></p>
    </header>

    <div class="bfw-cards-grid">
        <!-- Card: Add Coupon -->
        <div class="bfw-action-card">
            <h3><span class="dashicons dashicons-plus-alt"></span> <?php _e('Add a new coupon', 'bonus-for-woo'); ?></h3>
            <form method="post" id="add_coupon_form">
                <?php wp_nonce_field('bfw_action_coupon', 'bfw_nonce_coupon'); ?>
                <input type="hidden" name="bfw_computy_add_coupon_ajax" value="bfw_computy_add_coupon_ajax">
                
                <div class="bfw-form-group">
                    <label><?php _e('Сode coupon', 'bonus-for-woo'); ?></label>
                    <input type="text" name="code" class="bfw-input" placeholder="SUMMER2024" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="bfw-form-group">
                        <label><?php _e('Number of points', 'bonus-for-woo'); ?></label>
                        <input type="number" name="sum" class="bfw-input" min="1" placeholder="500" required>
                    </div>
                    <div class="bfw-form-group">
                        <label><?php _e('Status', 'bonus-for-woo'); ?></label>
                        <select name="status" class="bfw-input">
                            <option value="active"><?php _e('Active', 'bonus-for-woo'); ?></option>
                            <option value="noactive"><?php _e('Not active', 'bonus-for-woo'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="bfw-form-group">
                    <label><?php _e('Comment admin', 'bonus-for-woo'); ?></label>
                    <textarea name="comment_admin" class="bfw-input" style="height: 60px;"></textarea>
                </div>

                <div class="bfw-form-group">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="reusable" value="1"> <?php _e('Reusable', 'bonus-for-woo'); ?>
                    </label>
                </div>

                <button type="submit" class="bfw-btn bfw-btn-primary" style="width: 100%;">
                    <span class="dashicons dashicons-saved"></span> <?php _e('Add coupon', 'bonus-for-woo'); ?>
                </button>
            </form>
        </div>

        <!-- Card: Import -->
        <div class="bfw-action-card">
            <h3><span class="dashicons dashicons-upload"></span> <?php _e('Import coupons', 'bonus-for-woo'); ?></h3>
            <p style="font-size: 13px; color: var(--bfw-text-muted); margin-bottom: 15px;">
                <?php _e('To create multiple coupons, create a csv file and import it using the form below.', 'bonus-for-woo'); ?>
            </p>
            
            <div class="bfw-import-box">
                <form action="<?php echo admin_url("admin-post.php"); ?>" class="bfw_export_bonuses" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="bfw_import_coupons">
                    <div class="file-input-wrapper">
                        <input name="file" type="file" id="bfw-file-export" required>
                    </div>
                    <button type="button" class="bfw-btn bfw-btn-primary" onclick="upload();">
                        <span class="dashicons dashicons-database-import"></span> <?php _e('Import', 'bonus-for-woo'); ?>
                    </button>
                </form>
                <div id="bfw-file-export-result" style="margin-top: 10px;"></div>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="<?php echo BONUS_COMPUTY_PLUGIN_URL; ?>templates/coupons_bfw.csv" class="bfw-btn bfw-btn-outline" style="border: 1px solid #ddd; justify-content: center;">
                    <span class="dashicons dashicons-download"></span> <?php _e('Example of CSV file', 'bonus-for-woo'); ?>
                </a>
            </div>
        </div>

        <!-- Card: Info -->
        <div class="bfw-action-card" style="background: #f8fafc;">
            <h3><span class="dashicons dashicons-info"></span> <?php _e('Description', 'bonus-for-woo'); ?></h3>
            <ul style="font-size: 13px; line-height: 1.6; color: #475569; padding-left: 20px; margin: 0;">
                <li><?php _e('Create a coupon in the form and it will appear in the list of coupons.', 'bonus-for-woo'); ?></li>
                <li><?php _e('The customer can only use active coupons.', 'bonus-for-woo'); ?></li>
                <li><?php _e('Coupon can only be used once.', 'bonus-for-woo'); ?></li>
                <li><?php _e('Once a coupon has been used, it cannot be activated. Only delete.', 'bonus-for-woo'); ?></li>
                <li><?php _e('If the "Reusable" box is checked, then all users can use it.', 'bonus-for-woo'); ?></li>
            </ul>


        </div>
    </div>

    <div class="bfw-table-container">
        <h2 style="margin: 0 0 20px 0;"><?php _e('Coupons list', 'bonus-for-woo'); ?></h2>
        <?php
        $coupons_table = new Bfw_Coupons_List_Table();
        $coupons_table->prepare_items();
        ?>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php
            $coupons_table->search_box(__('Search Coupons', 'bonus-for-woo'), 'bfw-search-coupons');
            $coupons_table->display();
            ?>
        </form>
    </div>
</div>

<script type="text/javascript">
    function upload() {
        let fileExtension = ['csv'];
        if (jQuery.inArray(jQuery('#bfw-file-export').val().split('.').pop().toLowerCase(), fileExtension) === -1) {
            jQuery('#bfw-file-export-result').html('<span style="color:red"><?php _e('Only CSV format allowed!', 'bonus-for-woo'); ?></span>');
        } else {
            jQuery('.bfw_export_bonuses').css('opacity', '0.5');
            let formData = new FormData();
            formData.append("action", "upload-attachment");
            let fileInputElement = document.getElementById("bfw-file-export");

            formData.append("async-upload", fileInputElement.files[0]);
            formData.append("name", fileInputElement.files[0].name);
            formData.append("_wpnonce", "<?php echo wp_create_nonce('media-form'); ?>");
            
            let xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    jQuery.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: {
                            action: 'bfw_export_coupons',
                            nonce: '<?php echo wp_create_nonce('bfw_export_coupons'); ?>',
                            response: xhr.responseText,
                        },
                        success: function (data) {
                            if (data === 'good') {
                                jQuery('#bfw-file-export-result').html('<span style="color:green;font-weight:bold;"><?php _e('Import successful! Reloading...', 'bonus-for-woo'); ?></span>');
                                setTimeout(function () { location.reload(); }, 1500);
                            } else {
                                jQuery('#bfw-file-export-result').html('<span style="color:red"><?php _e('Import failed.', 'bonus-for-woo'); ?></span>');
                                jQuery('.bfw_export_bonuses').css('opacity', '1');
                            }
                        }
                    });
                }
            }
            xhr.open("POST", "/wp-admin/async-upload.php", true);
            xhr.send(formData);
        }
    }
</script>



