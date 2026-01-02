<?php
defined( 'ABSPATH' ) || exit;
/*
 * Страница инструментов
 * */
?>
<div class="wrap bonus-for-woo-admin">
    <?php echo '<h1>' . __('Bonus for Woo', 'bonus-for-woo') . ' - ' . __('Tools', 'bonus-for-woo') . '</h1>'; ?>

    <div class="card">
        <h2 class="title"><?php echo __('Accrual of points for previous orders.', 'bonus-for-woo'); ?></h2>
        <p><?php echo __('We will award points to users for previous orders, before installing the bonus system plugin.',
                    'bonus-for-woo'); ?>
            <a href="https://computy.ru/blog/docs/bonus-for-woo/instrumenty/nachislenie-ballov-za-predydushhie-zakazy/"
               target="_blank"><?php echo __('More details', 'bonus-for-woo'); ?> ↗</a></p>
        <p style="color: red"><?php echo __('Attention! The action is irreversible!', 'bonus-for-woo'); ?></p>
        <?php if (BfwRoles::isPro()) { ?>
            <div style="height: 20px;  color: green;    line-height: 7px;" id="progress"></div>
            <a id="startRecalculation" class="pdf-button" href="#"><?php echo __('Recalculation',
                        'bonus-for-woo'); ?></a>
        <?php } else { ?>
            <p style="font-size: 22px;
    font-weight: bold;
    color: #ed4949;"><?php echo __('Available in PRO version.', 'bonus-for-woo'); ?></p>
        <?php } ?>


    </div>

    <div class="card">
        <h2 class="title"><?php echo __('Rules and Conditions Generator', 'bonus-for-woo'); ?></h2>
        <p><?php echo __('This generator will create text for you based on the plugin settings.',
                    'bonus-for-woo'); ?></p>
        <a class="pdf-button"
           href="?page=bonus-for-woo/pages/generator.php"><?php echo __('Rules and Conditions Generator',
                    'bonus-for-woo'); ?></a>
    </div>


    <div class="card">
        <h2 class="title"><?php echo __('Update user statuses', 'bonus-for-woo'); ?></h2>
        <p><?php echo __('Update all users statuses. Requires a lot of resources!', 'bonus-for-woo'); ?></p>
        <form method="post" action="">
            <input type="hidden" name="update_statuses" value="1">
            <input type="submit" class="pdf-button" value="<?php echo __('Update user statuses', 'bonus-for-woo'); ?>">
        </form>
        <?php

        if (!empty($_POST['update_statuses'])) {

            $exclude_roles = BfwSetting::get('exclude-role', array());
            $args1 = array(
                    'role__not_in' => $exclude_roles,//Исключенные роли
                    'number' => -1, // Получить всех пользователей
                    'fields' => 'ID'
            );
            $users_bs = get_users($args1);
            //Обновление статусов пользователей
            foreach ($users_bs as $user) {
                BfwRoles::updateRole($user, false);
            }
            echo '<p>' . __('Updated statuses:', 'bonus-for-woo') . ' ' . count($users_bs) . '</p>';
            BfwLogs::addLog('tool', get_current_user_id(), __('Updated all user statuses.', 'bonus-for-woo'));
        }
        ?>

    </div>


    <div class="card">
        <h2 class="title"><?php echo __('Export/Import', 'bonus-for-woo') . ' ' .BfwSetting::get('label_points'); ?></h2>
        <div class=" ">

            <?php $filename = BONUS_COMPUTY_PLUGIN_DIR . '/export_bfw.csv';
            // echo 'При нажатии кнопки "Создать CSV файл экспорта", рядом появиться ссылка для скачивания файла.';
            echo '<p>' . __('When you click the "Create CSV export file" button, a link to download the file will appear next to it. You can download the file and edit it and then import it in the form below. After that, the bonus points data will be updated.',
                            'bonus-for-woo') . '</p><br>';
            if (file_exists($filename)) {
                echo '<a class="bfw-admin-button" href="?page=bonus-for-woo%2Fpages%2Ftools.php&export_bfw_points=true">' . __('Recreate CSV export file',
                                'bonus-for-woo') . '</a> ';

                echo ' <a href="' . BONUS_COMPUTY_PLUGIN_URL . 'export_bfw.csv"   download><i class="exporticon"></i>' . __('download CSV file',
                                'bonus-for-woo') . '</a>';
                echo '<a title="' . __('Delete export file',
                                'bonus-for-woo') . '" class="remove_export" href="?page=bonus-for-woo%2Fpages%2Ftools.php&remove_export_bfw_points=true">+</a>';

            } else {
                echo '<a class="bfw-admin-button" href="?page=bonus-for-woo%2Fpages%2Ftools.php&export_bfw_points=true">'
                        . __('Сreate CSV export file', 'bonus-for-woo') . '</a> ';

            }

            echo '<br><br><br>';
            ?>
            <form action="#" class="bfw_export_bonuses" method="post" enctype="multipart/form-data">
                <label for="bfw-file-export"><?php _e('Upload CSV file', 'bonus-for-woo'); ?><br></label>
                <input name="file" type="file" id="bfw-file-export" required>
                <br><label for="search_by"><?php _e('Search user:', 'bonus-for-woo'); ?></label>
                <select id="search_by" name="search_by">
                    <option value="by_id"><?php _e('by ID', 'bonus-for-woo'); ?></option>
                    <option value="by_email"><?php _e('by email', 'bonus-for-woo'); ?></option>
                    <option value="by_phone"><?php _e('by phone', 'bonus-for-woo'); ?></option>
                </select>
                <input class="bfw-admin-button" type="submit" value="<?php _e('import', 'bonus-for-woo'); ?>"
                       onclick="upload(); return false;">
                <div id="bfw-file-export-result" style="margin-top:10px;"></div>

            </form>
            <style>
                progress {
                    width: 100%;
                    height: 20px;
                    border-radius: 4px;
                    overflow: hidden;
                }
            </style>
            <script type="text/javascript">
                function upload() {
                    let fileInput = document.getElementById("bfw-file-export");
                    let select = document.querySelector('#search_by');
                    let search_by = select.value;

                    if (!fileInput.files.length) return alert("Выберите CSV файл");

                    let formData = new FormData();
                    formData.append("action", "upload-attachment");
                    formData.append("async-upload", fileInput.files[0]);
                    formData.append("name", fileInput.files[0].name);
                    formData.append("_ajax_nonce", "<?php echo wp_create_nonce('media-form'); ?>");

                    let resultBlock = jQuery('#bfw-file-export-result');
                    resultBlock.html('Загрузка файла...');

                    fetch("/wp-admin/async-upload.php", {
                        method: "POST",
                        body: formData
                    }).then(r => r.text()).then(responseText => {
                        jQuery.ajax({
                            type: 'POST',
                            url: "/wp-admin/admin-ajax.php",
                            data: {
                                action: 'bfw_export_bonuses',
                                response: responseText,
                                search_by: search_by,
                                offset: 0
                            },
                            success: function (init) {
                                if (!init.success) {
                                    resultBlock.html('<span style="color:red;">Ошибка инициализации импорта</span>');
                                    return;
                                }
                                const total = init.data.total || 15000;
                                processImport(responseText, search_by, init.data.next_offset, total);
                            }
                        });
                    });
                }

                function processImport(response, search_by, offset, total) {
                    jQuery.ajax({
                        type: 'POST',
                        url: "/wp-admin/admin-ajax.php",
                        data: {
                            action: 'bfw_export_bonuses',
                            response: response,
                            search_by: search_by,
                            offset: offset
                        },
                        success: function (res) {
                            let resultBlock = jQuery('#bfw-file-export-result');

                            if (!res.success) {
                                resultBlock.html('<span style="color:red;">Ошибка импорта</span>');
                                return;
                            }

                            let nextOffset = res.data.next_offset;
                            let done = res.data.done;

                            let percent = Math.round((nextOffset / total) * 100);
                            resultBlock.html('<progress value="' + percent + '" max="100"></progress> ' + percent + '%');

                            if (!done) {
                                processImport(response, search_by, nextOffset, total);
                            } else {
                                resultBlock.html('<span style="color:green;font-size:18px;">Импорт завершён!</span>');
                                jQuery('#bfw-file-export').val('');
                            }
                        }
                    });
                }
            </script>

            <hr>

        </div>

    </div>

</div>