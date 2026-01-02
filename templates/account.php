<?php
/**
 * Шаблон страницы бонусов в аккаунте клиента
 *
 * @version      4.6.0
 */

defined('ABSPATH') || exit;
?>

<div class="bfw_user_account_content">
    <h2><?php
        /*
         * Заголовок страницы бонусов в аккаунте
         * */
        do_action('bfw_account_title'); ?></h2>

    <?php
    /*
     * Вывод основной информации: статус, процент кешбэка, количество бонусных баллов, сколько дней до сгорания
     * */
    do_action('bfw_account_basic_info'); ?>

    <?php
    /*
     * Ввод купонов (только для PRO-версии)
     * */
    echo do_shortcode('[bfw_coupon_form]'); ?>

    <?php
    /*
     * Прогресс бар
     * */
    do_action('bfw_account_progress'); ?>


    <?php
    /*
     * Реферальная система (только для PRO-версии)
     *
     * @version 5.5.0
     * */
    echo apply_filters('bfw_account_referal', '');
    ?>


    <br> <br>

    <?php
    /*
     * История начисления баллов
     * */
    do_action('bfw_account_history');
    ?>

    <?php do_action('computy_copyright') ?>
</div>
