<?php
defined( 'ABSPATH' ) || exit;

/**
 * Страница купонов
 *
 * @version 4.1.0
 */

/*Обработчик запроса добавления купона*/
if (isset($_POST['bfw_computy_add_coupon_ajax'])) {
    if ($_POST['bfw_computy_add_coupon_ajax'] === 'bfw_computy_add_coupon_ajax') {
        global $wpdb;
        $code = _sanitize_text_fields($_POST['code']);
        $sum = _sanitize_text_fields($_POST['sum']);
        $comment_admin = _sanitize_text_fields($_POST['comment_admin']);
        $status = _sanitize_text_fields($_POST['status']);
        $reusable = $_POST['reusable'] ?? 0;

        if ($wpdb->get_results(
                $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "bfw_coupons_computy  WHERE `code` = %s", $code))) {
            echo '<div id="message" class="notice notice-warning is-dismissible">
<p>' . sprintf(__('Coupon <b>%s</b> is already being used. Enter another code or change the existing one.',
                            'bonus-for-woo'), $code) . '
	</p></div>';
        } else {
            BfwCoupons::addCoupon($code, $sum, $comment_admin, $status, $reusable);
            echo '<div id="message" class="notice notice-success is-dismissible">
	<p>' . __('Coupon', 'bonus-for-woo') . ' <b>' . $code . '</b> ' . __(
                            'added',
                            'bonus-for-woo'
                    ) . '.</p>
</div>';
        }
    } elseif ($_POST['bfw_computy_ajax'] === 'editrolehidden') {
        global $wpdb;
        $percent_role = sanitize_text_field($_POST['percent_role']);
        $summa_start = sanitize_text_field($_POST['summa_start']);
        $name_role = sanitize_text_field($_POST['name_role']);
        $wpdb->update(
                $wpdb->prefix . "bfw_computy",
                array('percent' => $percent_role, 'summa_start' => $summa_start),
                array('name' => $name_role)
        );
    }
}


/*Обработчик запроса удаления купона*/
if (isset($_POST['bfw_delete_coupon'])) {
    BfwCoupons::deleteCoupon($_POST['bfw_delete_coupon']);
    echo '<div id="message" class="notice notice-warning is-dismissible">
	<p>' . __('deleted', 'bonus-for-woo') . '.</p></div>';
}
/*Обработчик запроса удаления купона*/

/*Обработчик запроса изменения статуса купона*/
if (isset($_POST['bfw_edit_status_coupon'])) {
    if (sanitize_text_field($_POST['status_coupon']) == 'active') {
        $status = 'noactive';
    } else {
        $status = 'active';
    }
    BfwCoupons::editStatusCoupon(
            $_POST['bfw_edit_status_coupon'],
            $status
    );
    echo '<div id="message" class="notice notice-warning is-dismissible">
	<p>' . __('Coupon status changed', 'bonus-for-woo') . '.</p></div>';
}
/*Обработчик запроса изменения статуса купона*/

if (determine_locale() === 'ru_RU') {
    $language = ' language: {
                        "sProcessing":   "Подождите...",
                        "sLengthMenu":   "Показать _MENU_ купонов",
                        "sZeroRecords":  "Купоны отсутствуют.",
                        "sInfo":         "Купоны с _START_ до _END_ из _TOTAL_ купонов",
                        "sInfoEmpty":    "Купоны с 0 до 0 из 0 купонов",
                        "sInfoFiltered": "(отфильтровано из _MAX_ купонов)",
                        "sInfoPostFix":  "",
                        "sSearch":       "Поиск:",
                        "sUrl":          "",
                        "oPaginate": {
                            "sFirst": "Первая",
                            "sPrevious": "Предыдущая",
                            "sNext": "Следующая",
                            "sLast": "Последняя"
                        },
                        "oAria": {
                            "sSortAscending":  ": активировать для сортировки столбца по возрастанию",
                            "sSortDescending": ": активировать для сортировки столбцов по убыванию"
                        }}';
} else {
    $language = '';
}
?>
<script>
    jQuery(document).ready(function () {
        jQuery('#table-coupons').DataTable(
            {
                <?php  echo $language; ?>
            }
        );
    });

</script>
<?php
wp_register_style(
        'datatables.min.css',
        BONUS_COMPUTY_PLUGIN_URL . '_inc/datatables/datatables.min.css',
        array(),
        BONUS_COMPUTY_VERSION
);
wp_register_script(
        'jquery.dataTables.min.js',
        BONUS_COMPUTY_PLUGIN_URL . '_inc/datatables/jquery.dataTables.min.js',
        array(),
        BONUS_COMPUTY_VERSION
);

wp_enqueue_style('datatables.min.css');
wp_enqueue_script('jquery.dataTables.min.js');

?>
<div class="wrap bonus-for-woo-admin">
    <?php
    echo '<h1>' . sprintf(
                    __('Coupons for %s ', 'bonus-for-woo'),
                    BfwPoints::pointsLabel(5)
            ) . '</h1>';
    ?>
    <hr>
    <div class="bfw_texts_wrap">
        <div class="bfw_text">
            <h3><?php
                echo __('Add a new coupon', 'bonus-for-woo'); ?></h3>
            <form method="post" action="" id="add_coupon_form">
                <input type="hidden" id="bfw_computy_add_coupon_ajax"
                       name="bfw_computy_add_coupon_ajax"
                       value="bfw_computy_add_coupon_ajax">
                <table class="form">
                    <tbody>
                    <tr style="display: flex;gap: 10px;">
                        <td class="table-bfw">
                            <label for="add_coupon_form_code"><b><?php
                                    echo __('Сode coupon', 'bonus-for-woo'); ?></b></label>
                            <input type="text" id="add_coupon_form_code"
                                   name="code" value="" placeholder="" required><br>
                            <label for="add_coupon_form_sum"><b><?php
                                    echo __('Number of points', 'bonus-for-woo'); ?></b></label>
                            <input min="1" type="number"
                                   id="add_coupon_form_sum" name="sum" value=""
                                   placeholder="300" required>
                        </td>
                        <td class="table-bfw">
                            <label for="add_coupon_form_comment_admin"><b><?php
                                    echo __(
                                            'Comment admin',
                                            'bonus-for-woo'
                                    ); ?></b></label>
                            <textarea style="width: 300px; height: 93px;"
                                      id="add_coupon_form_comment_admin"
                                      name="comment_admin"
                                      placeholder="<?php echo __('Only the administrator can see it.',
                                              'bonus-for-woo'); ?>"></textarea>
                        </td>
                    </tr>

                    </tbody>
                </table>
                <input type="submit" name="submit" class="button button-primary"
                       value="<?php
                       echo __('Add coupon', 'bonus-for-woo'); ?>">

                <select name="status">
                    <option value="noactive"><?php
                        echo __('Not active', 'bonus-for-woo'); ?></option>
                    <option value="active"><?php echo __('Active', 'bonus-for-woo'); ?></option>
                </select>

                <label><input type="checkbox" name="reusable" value="1"> <?php echo __('Reusable', 'bonus-for-woo'); ?>
                </label>

            </form>
        </div>

        <div class="bfw_text">
            <h3><?php
                echo __('Description', 'bonus-for-woo'); ?></h3>
            <p><?php
                echo __('Create a coupon in the form and it will appear in the list of coupons.', 'bonus-for-woo'); ?>
                <br>
                <?php echo __('The customer can only use active coupons.', 'bonus-for-woo'); ?><br>
                <?php echo __('Coupon can only be used once.', 'bonus-for-woo'); ?><br>
                <?php echo __('Once a coupon has been used, it cannot be activated. Only delete.', 'bonus-for-woo'); ?>
                <br>
                <?php echo __('If the "Reusable" box is checked, then all users can use it.', 'bonus-for-woo'); ?>
            </p>


        </div>

        <div class="bfw_text">
            <h3><?php
                echo __('Import coupons', 'bonus-for-woo'); ?></h3>
            <p><?php
                echo __(
                        'To create multiple coupons, create a csv file and import it using the form below.',
                        'bonus-for-woo'
                ); ?><br>
                <?php
                echo __('Possible statuses', 'bonus-for-woo') ?>: active,
                noactive, used
            </p>
            <a href="<?php
            echo BONUS_COMPUTY_PLUGIN_URL; ?>templates/coupons_bfw.csv"
               download=""><i class="exporticon"></i>
                <?php
                echo __('Example of CSV file', 'bonus-for-woo'); ?></a>
            <br><br>
            <?php
            echo '<form action="' . admin_url("admin-post.php") . '" class="bfw_export_bonuses"  method="post"  enctype="multipart/form-data">
                        <input type="hidden" name="action" value="bfw_import_coupons" />
                        <lable for="bfw-file-export">' . __(
                            'Upload CSV file',
                            'bonus-for-woo'
                    ) . '<br></lable>
                        <input name="file" type="file" id="bfw-file-export" required>';

            echo '<input class="button button-primary" type="submit" value="'
                    . __(
                            'import',
                            'bonus-for-woo'
                    ) . '" onclick="upload();return false;">
                        <div id="bfw-file-export-result"></div>
                    </form>';
            ?>
            <script type="text/javascript">
                function upload() {
                    let fileExtension = ['csv'];
                    if (jQuery.inArray(jQuery('#bfw-file-export').val().split('.').pop().toLowerCase(), fileExtension) === -1) {
                        jQuery('#bfw-file-export-result').html('<span style="color:red"><?php echo __(
                                'You can only use csv file format!',
                                'bonus-for-woo'
                        );?></span>');
                    } else {
                        jQuery('.bfw_export_bonuses').addClass('bfv_uplouads');
                        let formData = new FormData();
                        formData.append("action", "upload-attachment");
                        let fileInputElement = document.getElementById("bfw-file-export");

                        formData.append("async-upload", fileInputElement.files[0]);
                        formData.append("name", fileInputElement.files[0].name);
                        <?php $my_nonce = wp_create_nonce('media-form'); ?>
                        formData.append("_wpnonce", "<?php echo $my_nonce; ?>");
                        let xhr = new XMLHttpRequest();
                        xhr.onreadystatechange = function () {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                //  console.log(xhr.responseText);
                                jQuery.ajax({
                                    type: 'POST',
                                    url: "/wp-admin/admin-ajax.php",
                                    data: {
                                        action: 'bfw_export_coupons',
                                        response: xhr.responseText,
                                    },
                                    success: function (data) {
                                        console.log(data);
                                        if (data === 'good') {
                                            jQuery('#bfw-file-export-result').html('<span style="color:green;font-size: 20px;"><?php echo __(
                                                    'Import completed successfully!',
                                                    'bonus-for-woo'
                                            );?></span>');
                                            jQuery('.bfw_export_bonuses').removeClass('bfv_uplouads');
                                            jQuery('#bfw-file-export').val('');
                                            setTimeout(function () {
                                                location.reload();
                                            }, 1000);
                                        }
                                    },
                                    error: function (error) {
                                        console.log(error);
                                    }
                                });
                            }
                        }
                        xhr.open("POST", "/wp-admin/async-upload.php", true);
                        xhr.send(formData);
                    }
                }
            </script>
        </div>

    </div>

    <h2><?php
        echo __('Coupons list', 'bonus-for-woo'); ?></h2>
    <?php
    BfwCoupons::getListCoupons();
    ?>
</div>
