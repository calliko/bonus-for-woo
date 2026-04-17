<?php
defined( 'ABSPATH' ) || exit;

/**
 * Модернизированная страница инструментов
 */
?>
<style>
    :root {
        --bfw-primary: #2271b1;
        --bfw-primary-hover: #135e96;
        --bfw-bg: #f0f2f5;
        --bfw-card-bg: #ffffff;
        --bfw-border: #dcdcde;
        --bfw-text: #1d2327;
        --bfw-text-muted: #646970;
        --bfw-success: #00a32a;
        --bfw-warning: #f0b849;
        --bfw-danger: #d63638;
        --bfw-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .bfw-admin-wrap {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        margin: 20px 20px 0 0;
        color: var(--bfw-text);
    }

    .bfw-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .bfw-header h1 {
        font-size: 28px;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .bfw-tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
    }

    .bfw-tool-card {
        background: var(--bfw-card-bg);
        border-radius: 12px;
        padding: 25px;
        border: 1px solid var(--bfw-border);
        box-shadow: var(--bfw-shadow);
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .bfw-tool-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .bfw-tool-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
    }

    .bfw-tool-icon {
        background: #f0f6fb;
        color: var(--bfw-primary);
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bfw-tool-icon .dashicons {
        font-size: 20px;
        width: 20px;
        height: 20px;
    }

    .bfw-tool-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }

    .bfw-tool-desc {
        font-size: 14px;
        color: var(--bfw-text-muted);
        line-height: 1.5;
        flex-grow: 1;
        margin-bottom: 20px;
    }

    .bfw-tool-footer {
        display: flex;
        align-items: center;
        gap: 15px;
        padding-top: 20px;
        border-top: 1px solid #f0f0f1;
    }

    /* Кнопки */
    .bfw-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        font-size: 14px;
    }

    .bfw-btn-primary {
        background: var(--bfw-primary);
        color: white;
    }

    .bfw-btn-primary:hover {
        background: var(--bfw-primary-hover);
        color: white;
    }

    .bfw-btn-outline {
        background: transparent;
        border: 1px solid var(--bfw-border);
        color: var(--bfw-text);
    }

    .bfw-btn-outline:hover {
        background: #f6f7f7;
        border-color: #8c8f94;
    }

    .bfw-btn-danger {
        background: #fff;
        border: 1px solid var(--bfw-danger);
        color: var(--bfw-danger);
    }

    .bfw-btn-danger:hover {
        background: var(--bfw-danger);
        color: white;
    }

    /* Формы внутри карточек */
    .bfw-tool-form {
        margin-bottom: 20px;
    }

    .bfw-form-group {
        margin-bottom: 15px;
    }

    .bfw-form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
        font-size: 13px;
    }

    .bfw-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--bfw-border);
        border-radius: 6px;
        font-size: 14px;
    }

    .bfw-badge-pro {
        background: #fdf2f2;
        color: var(--bfw-danger);
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .bfw-alert {
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 13px;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }

    .bfw-alert-warning {
        background: #fff8e5;
        border-left: 4px solid var(--bfw-warning);
        color: #856404;
    }

    .bfw-progress-container {
        margin-top: 15px;
        background: #f0f0f1;
        height: 8px;
        border-radius: 4px;
        overflow: hidden;
        display: none;
    }

    .bfw-progress-bar {
        background: var(--bfw-primary);
        height: 100%;
        width: 0%;
        transition: width 0.3s;
    }
</style>

<div class="bfw-admin-wrap">
    <div class="bfw-header">
        <h1>
            <span class="dashicons dashicons-admin-tools" style="font-size: 32px; width: 32px; height: 32px;"></span>
            <?php echo __('Bonus for Woo', 'bonus-for-woo') . ' - ' . __('Tools', 'bonus-for-woo'); ?>
        </h1>
    </div>

    <div class="bfw-tools-grid">
        
        <!-- Карточка 1: Прошлые заказы -->
        <div class="bfw-tool-card">
            <div class="bfw-tool-header">
                <div class="bfw-tool-icon"><span class="dashicons dashicons-backup"></span></div>
                <h2 class="bfw-tool-title"><?php _e('Accrual of points for previous orders.', 'bonus-for-woo'); ?></h2>
            </div>
            <p class="bfw-tool-desc">
                <?php _e('We will award points to users for previous orders, before installing the bonus system plugin.', 'bonus-for-woo'); ?>
                <br><a href="https://computy.ru/blog/docs/bonus-for-woo/instrumenty/nachislenie-ballov-za-predydushhie-zakazy/" target="_blank" style="text-decoration:none; color: var(--bfw-primary); font-size: 13px;"><?php _e('More details', 'bonus-for-woo'); ?> ↗</a>
            </p>

            <div class="bfw-alert bfw-alert-warning">
                <span class="dashicons dashicons-warning"></span>
                <span> <?php _e('Attention! The action is irreversible!', 'bonus-for-woo'); ?></span>
            </div>

            <div class="bfw-tool-footer">
                <?php if (BfwRoles::isPro()) : ?>
                    <div style="display:flex; flex-direction:column; gap:10px; width: 100%;">
                        <button id="startRecalculation" class="bfw-btn bfw-btn-danger">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php _e('Recalculation', 'bonus-for-woo'); ?>
                        </button>
                        <div id="progress" style="font-weight: 600; color: var(--bfw-success); font-size: 13px;"></div>
                    </div>
                <?php else : ?>
                    <span class="bfw-badge-pro"><?php _e('Available in PRO version.', 'bonus-for-woo'); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Карточка 2: Массовое начисление -->
        <div class="bfw-tool-card">
            <div class="bfw-tool-header">
                <div class="bfw-tool-icon"><span class="dashicons dashicons-plus-alt"></span></div>
                <h2 class="bfw-tool-title"><?php _e('Bulk adding points', 'bonus-for-woo'); ?></h2>
            </div>
            <p class="bfw-tool-desc"><?php _e('Works without creating a notification. It won\'t be sent to your email.', 'bonus-for-woo'); ?></p>

            <?php if (BfwRoles::isPro()) : ?>
                <div class="bfw-tool-form">
                    <div class="bfw-form-group">
                        <label><?php _e('How many points should I add?', 'bonus-for-woo'); ?></label>
                        <input type="number" id="mass_points" class="bfw-input" value="100" min="1">
                    </div>
                    <div class="bfw-form-group">
                        <label><?php _e('Text in history', 'bonus-for-woo'); ?></label>
                        <input type="text" id="mass_text" class="bfw-input" placeholder="<?php _e('e.g. Holiday Gift', 'bonus-for-woo'); ?>">
                    </div>
                </div>
                <div class="bfw-tool-footer">
                    <div style="display:flex; flex-direction:column; gap:10px; width: 100%;">
                        <button id="mass-start" class="bfw-btn bfw-btn-primary">
                            <span class="dashicons dashicons-share-alt"></span>
                            <?php _e('Start adding points', 'bonus-for-woo'); ?>
                        </button>
                        <div id="mass-progress" style="font-size: 13px;"></div>
                    </div>
                </div>
            <?php else : ?>
                <div class="bfw-tool-footer">
                    <span class="bfw-badge-pro"><?php _e('Available in PRO version.', 'bonus-for-woo'); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Карточка 3: Импорт и Экспорт -->
        <div class="bfw-tool-card" style="grid-column: span 1;">
            <div class="bfw-tool-header">
                <div class="bfw-tool-icon"><span class="dashicons dashicons-media-spreadsheet"></span></div>
                <h2 class="bfw-tool-title"><?php _e('Export/Import', 'bonus-for-woo'); ?></h2>
            </div>
            <p class="bfw-tool-desc"><?php _e('When you click the "Create CSV export file" button, a link to download the file will appear next to it. You can download the file and edit it and then import it in the form below. After that, the bonus points data will be updated.', 'bonus-for-woo'); ?></p>

            <div class="bfw-tool-form">
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                    <?php $filename = BONUS_COMPUTY_PLUGIN_DIR . '/export_bfw.csv'; ?>
                    <?php if (file_exists($filename)) : ?>
                        <a class="bfw-btn bfw-btn-outline" href="<?php echo wp_nonce_url('?page=bonus-for-woo%2Fpages%2Ftools.php&export_bfw_points=true', 'bfw_export_points'); ?>">
                            <span class="dashicons dashicons-update"></span> <?php _e('Refresh CSV', 'bonus-for-woo'); ?>
                        </a>
                        <a class="bfw-btn bfw-btn-outline" href="<?php echo BONUS_COMPUTY_PLUGIN_URL . 'export_bfw.csv'; ?>" download>
                            <span class="dashicons dashicons-download"></span> <?php _e('Download CSV', 'bonus-for-woo'); ?>
                        </a>
                        <a title="<?php _e('Delete export file', 'bonus-for-woo'); ?>" class="bfw-btn bfw-btn-danger" style="padding: 10px;" href="<?php echo wp_nonce_url('?page=bonus-for-woo%2Fpages%2Ftools.php&remove_export_bfw_points=true', 'bfw_remove_export'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </a>
                    <?php else : ?>
                        <a class="bfw-btn bfw-btn-outline" href="<?php echo wp_nonce_url('?page=bonus-for-woo%2Fpages%2Ftools.php&export_bfw_points=true', 'bfw_export_points'); ?>">
                             <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e('Сreate CSV export file', 'bonus-for-woo'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <div style="padding: 15px; background: #fcfcfc; border: 1px dashed var(--bfw-border); border-radius: 8px;">
                    <label style="display:block; font-weight:600; margin-bottom:10px; font-size:13px;"><?php _e('Upload CSV file', 'bonus-for-woo'); ?></label>
                    <input name="file" type="file" id="bfw-file-export" style="font-size:12px; width:100%; margin-bottom:15px;">
                    
                    <div style="display:flex; gap:10px;">
                        <select id="search_by" class="bfw-input" style="width: auto; height: 38px;">
                            <option value="by_id"><?php _e('by ID', 'bonus-for-woo'); ?></option>
                            <option value="by_email"><?php _e('by email', 'bonus-for-woo'); ?></option>
                            <option value="by_phone"><?php _e('by phone', 'bonus-for-woo'); ?></option>
                        </select>
                        <button class="bfw-btn bfw-btn-primary" onclick="upload(); return false;">
                            <span class="dashicons dashicons-upload"></span> <?php _e('import', 'bonus-for-woo'); ?>
                        </button>
                    </div>
                    <div id="bfw-file-export-result" style="margin-top:10px; font-size:13px;"></div>
                </div>
            </div>
        </div>

        <!-- Карточка 4: Служебные -->
        <div class="bfw-tool-card">
            <div class="bfw-tool-header">
                <div class="bfw-tool-icon"><span class="dashicons dashicons-admin-users"></span></div>
                <h2 class="bfw-tool-title"><?php _e('Update user statuses', 'bonus-for-woo'); ?></h2>
            </div>
            <div class="bfw-tool-desc">
                 <div style="margin-bottom: 20px;">
                    <p style="font-size:13px; margin-bottom:12px;"><?php _e('Force update all loyalty statuses based on current user balance.', 'bonus-for-woo'); ?></p>
                     <div class="bfw-alert bfw-alert-warning">
                         <span class="dashicons dashicons-warning"></span>
                         <span> <?php _e('Update all users statuses. Requires a lot of resources!', 'bonus-for-woo'); ?></span>
                     </div>
                    <form method="post" action="">
                        <?php wp_nonce_field('bfw_update_statuses_action', 'bfw_update_statuses_nonce'); ?>
                        <input type="hidden" name="update_statuses" value="1">
                        <button type="submit" class="bfw-btn bfw-btn-outline">
                            <span class="dashicons dashicons-update-alt"></span>
                            <?php _e('Update user statuses', 'bonus-for-woo'); ?>
                        </button>
                    </form>
                    <?php if (!empty($_POST['update_statuses'])) {
                         if (!isset($_POST['bfw_update_statuses_nonce']) || !wp_verify_nonce($_POST['bfw_update_statuses_nonce'], 'bfw_update_statuses_action')) {
                            wp_die('Security Error');
                         }
                         $exclude_roles = BfwSetting::get('exclude-role', array());
                         $args1 = array('role__not_in' => $exclude_roles, 'number' => -1, 'fields' => 'ID');
                         $users_bs = get_users($args1);
                         foreach ($users_bs as $user) { BfwRoles::updateRole($user, false); }
                         echo '<p style="color:var(--bfw-success); margin-top:10px; font-weight:600; font-size:13px;">✅ ' . sprintf(__('Updated %d users.', 'bonus-for-woo'), count($users_bs)) . '</p>';
                    } ?>
                 </div>


            </div>
        </div>

        <!-- Карточка 5: Служебные -->
        <div class="bfw-tool-card">
            <div class="bfw-tool-header">
                <div class="bfw-tool-icon"><span class="dashicons dashicons-editor-paragraph"></span></div>
                <h2 class="bfw-tool-title"><?php _e('Rules and Conditions Generator', 'bonus-for-woo'); ?></h2>
            </div>
            <div class="bfw-tool-desc">


                <div>

                    <p style="font-size:13px; margin-bottom:12px;"><?php _e('This generator will create text for you based on the plugin settings.', 'bonus-for-woo'); ?></p>
                    <a class="bfw-btn bfw-btn-outline" href="?page=bonus-for-woo/pages/generator.php">
                        <span class="dashicons dashicons-editor-paragraph"></span>
                        <?php _e('Rules and Conditions Generator', 'bonus-for-woo'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(function ($) {
    // --- Массовое начисление ---
    let massPage = 1;
    function processMassBatch() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'computy_mass_add_points',
                nonce: '<?php echo wp_create_nonce("bfw_mass_add"); ?>',
                paged: massPage,
                points: $('#mass_points').val(),
                text: $('#mass_text').val()
            },
            success: function(response) {
                if (response.data && response.data.done) {
                    $('#mass-progress').html('<span style="color:var(--bfw-success); font-weight:700;">✅ ' + '<?php _e('Completed Successfully!', 'bonus-for-woo'); ?>' + '</span>');
                    $('#mass-start').prop('disabled', false);
                    return;
                }
                if (response.data && response.data.next_page) {
                    $('#mass-progress').html('<?php _e('Processing page:', 'bonus-for-woo'); ?> ' + massPage);
                    massPage = response.data.next_page;
                    setTimeout(processMassBatch, 300);
                }
            }
        });
    }

    $('#mass-start').on('click', function(){
        if(!confirm('<?php _e('Add points to ALL users?', 'bonus-for-woo'); ?>')) return;
        massPage = 1;
        $(this).prop('disabled', true);
        $('#mass-progress').html('🚀 <?php _e('Initiating...', 'bonus-for-woo'); ?>');
        processMassBatch();
    });

    // --- Импорт (Window Scope для inline onclick) ---
    window.upload = function() {
        let fileInput = document.getElementById("bfw-file-export");
        let search_by = jQuery('#search_by').val();

        if (!fileInput.files.length) return alert("<?php _e('Select CSV file', 'bonus-for-woo'); ?>");

        let formData = new FormData();
        formData.append("action", "upload-attachment");
        formData.append("async-upload", fileInput.files[0]);
        formData.append("name", fileInput.files[0].name);
        formData.append("_ajax_nonce", "<?php echo wp_create_nonce('media-form'); ?>");

        let resultBlock = jQuery('#bfw-file-export-result');
        resultBlock.html('<?php _e('Uploading...', 'bonus-for-woo'); ?>');

        fetch("/wp-admin/async-upload.php", { method: "POST", body: formData })
        .then(r => r.text())
        .then(responseText => {
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                data: { 
                    action: 'bfw_export_bonuses', 
                    response: responseText, 
                    search_by: search_by, 
                    offset: 0,
                    nonce: '<?php echo wp_create_nonce("bfw_export_bonuses_nonce"); ?>'
                },
                success: function (init) {
                    if (!init.success) { 
                        resultBlock.html('<span style="color:var(--bfw-danger);"><?php _e('Initialization error', 'bonus-for-woo'); ?></span>'); 
                        return; 
                    }
                    processImportBatch(responseText, search_by, init.data.next_offset, init.data.total || 5000);
                }
            });
        });
    };

    function processImportBatch(response, search_by, offset, total) {
        let resultBlock = jQuery('#bfw-file-export-result');
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: { 
                action: 'bfw_export_bonuses', 
                response: response, 
                search_by: search_by, 
                offset: offset,
                nonce: '<?php echo wp_create_nonce("bfw_export_bonuses_nonce"); ?>'
            },
            success: function (res) {
                if (!res.success) { resultBlock.html('<span style="color:var(--bfw-danger);"><?php _e('Import error', 'bonus-for-woo'); ?></span>'); return; }
                if (!res.data.done) {
                    let nextOffset = res.data.next_offset;
                    let percent = Math.round((nextOffset / total) * 100);
                    resultBlock.html('<?php _e('Importing:', 'bonus-for-woo'); ?> ' + (percent > 100 ? 100 : percent) + '%');
                    processImportBatch(response, search_by, nextOffset, total);
                } else {
                    resultBlock.html('<span style="color:var(--bfw-success); font-weight:700;">✅ <?php _e('Import Complete!', 'bonus-for-woo'); ?></span>');
                    jQuery('#bfw-file-export').val('');
                }
            }
        });
    }
});
</script>