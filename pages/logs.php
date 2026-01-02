<?php
/**
 * Страница вывода логов
 */
defined( 'ABSPATH' ) || exit;
require_once 'datatable.php';

echo '<div class="wrap bonus-for-woo-admin">';
echo '<h1>' . __('Logs', 'bonus-for-woo') . '</h1>';
echo '<p></p>';
$date_start = $_GET['date_start'] ?? false;
$date_finish = $_GET['date_finish'] ?? gmdate("Y-m-d");

?>
    <form>
        <input type="hidden" name="page" value="bonus-for-woo/pages/logs.php">
        <label><?php
            echo __('From', 'bonus-for-woo');
            ?>
            <input type="date" id="date_start" name="date_start" value="<?php
            echo esc_html($date_start); ?>" max="<?php
            echo gmdate("Y-m-d"); ?>" onchange="bfwchangestart()"></label>
        <label><?php
            echo __('to', 'bonus-for-woo'); ?>
            <input type="date" id="date_finish" name="date_finish" value="<?php
            echo esc_html($date_finish); ?>" max="<?php
            echo gmdate("Y-m-d"); ?>" <?php
            if (!$date_start) {
                echo 'disabled';
            } ?> ></label>
        <input class="button" type="submit" value="<?php
        echo __('Search', 'bonus-for-woo'); ?>">
        <a class="button"
           href="/wp-admin/admin.php?page=bonus-for-woo%2Fpages%2Flogs.php"><?php
            echo __('Clear', 'bonus-for-woo'); ?></a>
    </form>
    <script>
        function bfwchangestart() {
            let start = document.getElementById('date_start');
            let finish = document.getElementById('date_finish');
            console.log(start.value.length);
            if (start.value.length === 10) {
                finish.disabled = false;
                finish.min = start.value;
            } else {
                finish.disabled = true;
            }

        }
    </script>
    <br>
<?php
if (empty($_GET['date_start'])) {
    echo '<b style="color: red">' . __(
                    'The last 500 entries are displayed.',
                    'bonus-for-woo'
            ) . '</b>';
}
BfwLogs::getListLog($date_start, $date_finish);

echo '</div>';