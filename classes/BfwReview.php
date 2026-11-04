<?php

defined('ABSPATH') || exit;

/**
 * Adding bonuses when a product review is added
 * Добавление бонусов когда добавлен отзыв о товаре
 *
 */
class BfwReview
{

    /**
     * If the review is approved
     * Если одобрен отзыв
     *
     * @param  $statuses
     *
     * @return mixed
     * @version 6.4.0
     */
    public static function bfw_paid_is_paid_status($statuses)
    {
        $order_status = BfwSetting::get('add_points_order_status', 'completed');
        $order_status = apply_filters('bfw_add_points_order_status_filter', $order_status);

        $statuses[] = $order_status;
        return $statuses;
    }

    /**
     * If the review is approved, callback
     * Если одобрен отзыв колбэк
     *
     * @param $comment
     *
     * @return void
     * @version 8.1.3
     */
    public static function bfwoo_approve_comment_callback($comment): void
    {
        $user_id = (int) $comment->user_id;

        if (!$user_id || !BfwSetting::get('bonus-for-otziv')) {
            return;
        }

        $bonusfor_otziv_raw = (float) BfwSetting::get('bonus-for-otziv');
        if ($bonusfor_otziv_raw <= 0) {
            return;
        }

        if (get_post_type($comment->comment_post_ID) !== 'product') {
            return;
        }

        /*
         * Идемпотентность по конкретному отзыву.
         *
         * Раньше начисление привязывалось к COUNT(*) одобренных отзывов === 1.
         * Одобрить > отклонить (баллы списались) > одобрить снова давало
         * COUNT===1 повторно, то есть позволяло бесконечно «докручивать»
         * баллы одним и тем же отзывом. Теперь признак хранится на самом
         * комментарии и снимается только при возврате.
         */
        if (get_comment_meta($comment->comment_ID, '_bfw_review_points_awarded', true) === '1') {
            return;
        }

        add_filter('woocommerce_order_is_paid_statuses', array('BfwReview', 'bfw_paid_is_paid_status'));

        $bought_product = wc_customer_bought_product($comment->comment_author_email, $user_id, $comment->comment_post_ID);
        $bought_product = apply_filters('bfw_bought_product', $bought_product);

        if (!$bought_product) {
            return;
        }

        // Начисляем только за первый отзыв на этот товар
        global $wpdb;
        $count_comment = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(comment_ID) FROM ' . $wpdb->prefix . 'comments
             WHERE user_id = %d AND comment_post_ID = %d AND comment_approved = "1"',
            $user_id,
            $comment->comment_post_ID
        ));

        if ($count_comment !== 1) {
            return;
        }

        $points = BfwPoints::roundPoints($bonusfor_otziv_raw);
        $cause = __('For review', 'bonus-for-woo');

        $applied = BfwLedger::apply(
            $user_id,
            $points,
            BfwLedger::REASON_REVIEW,
            'review',
            (int) $comment->comment_ID,
            $cause
        );

        if (!$applied) {
            return;
        }

        update_comment_meta($comment->comment_ID, '_bfw_review_points_awarded', '1');

        $new_balance = BfwPoints::getPoints($user_id);

        if (!BfwSetting::get('email-when-review')) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            BfwLogs::addLog('error', $user_id, 'Email not sent: invalid user or email');
            return;
        }

        $text_email = BfwSetting::get('email-when-review-text', '');
        $title_email = BfwSetting::get('email-when-review-title', __('Points accrual', 'bonus-for-woo'));

        $get_referral = get_user_meta($user_id, 'bfw_points_referral', true);
        $text_email_array = array(
            '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral),
            '[user]' => $user->display_name,
            '[cause]' => $cause,
            '[points]' => BfwPoints::formatNumber((float) $points),
            '[total]' => $new_balance,
        );
        $message_email = BfwEmail::template($text_email, $text_email_array);

        (new BfwEmail())->getMail($user_id, '', $title_email, $message_email);
    }


    /**
     * If an approved review is rejected, removes points
     * Если одобренный отзыв отклонен, удаляет баллы
     *
     * @param $comment
     *
     * @return void
     * @version 6.4.0
     */
    public static function bfwoo_unapproved_comment_callback($comment): void
    {
        // 1. Ранняя валидация
        if (!$comment || empty($comment->user_id) || get_post_type($comment->comment_post_ID) !== 'product') {
            return;
        }
        $user_id = (int) $comment->user_id;
        $bonusfor_otziv_new = BfwPoints::roundPoints((float) BfwSetting::get('bonus-for-otziv'));
        if ($bonusfor_otziv_new <= 0) {
            return;
        }

        /*
         * Возврат делаем только если за ЭТОТ отзыв баллы действительно
         * начислялись (метка ставится при начислении). Раньше условие
         * опиралось на COUNT(*)===0 и могло списать баллы, заработанные
         * другим отзывом, либо не списать вовсе.
         */
        if (get_comment_meta($comment->comment_ID, '_bfw_review_points_awarded', true) !== '1') {
            return;
        }

        $applied = BfwLedger::apply(
            $user_id,
            -$bonusfor_otziv_new,
            BfwLedger::REASON_REVIEW_REVOKE,
            'review',
            (int) $comment->comment_ID,
            sprintf(__('Return of %s for Product Review', 'bonus-for-woo'), BfwPoints::pointsLabel(5))
        );

        if (!$applied) {
            return;
        }

        delete_comment_meta($comment->comment_ID, '_bfw_review_points_awarded');

        $computy_user_point = BfwPoints::getPoints($user_id);
        /* translators: %s: points label */
        $cause = sprintf(__('Return of %s for Product Review', 'bonus-for-woo'), BfwPoints::pointsLabel(5));
        /* translators: %s: points label */
        $title_email = sprintf(__('Return of %s', 'bonus-for-woo'), BfwPoints::pointsLabel(5));
        /* translators: %1$s: number of points, %2$s: points label */
        $info_email = sprintf(__('Your product review has been rejected. %1$s %2$s are canceled.',
            'bonus-for-woo'), $bonusfor_otziv_new, BfwPoints::pointsLabel(5));
        $message_email = '<p>' . esc_html($info_email) . '</p>';
        $message_email .= '<p>' . __('Cause', 'bonus-for-woo') . ': ' . esc_html($cause) . '</p>';
        /* translators: %s: points label */
        $message_email .= '<p>' . sprintf(__('The sum of your %s is now', 'bonus-for-woo'),
                BfwPoints::pointsLabel(5)) . ': <b>' . $computy_user_point . ' ' . BfwPoints::pointsLabel($computy_user_point) . '</b></p>';

        if (BfwSetting::get('email-when-review')) {
            (new BfwEmail())->getMail($user_id, '', $title_email, $message_email);
        }
    }

    /**
     * Премодерация отзывов о товарах (точечно).
     *
     * Раньше для этого включалась глобальная опция WordPress comment_moderation,
     * действовавшая на ВСЕ комментарии сайта. Теперь: баллы за отзывы включены —
     * только отзывы на товары уходят в ожидание; выключены — поведение сайта
     * не меняется.
     *
     * @param int|string $approved
     * @param array      $commentdata
     * @return int|string
     * @version 8.5.0
     */
    public static function moderateProductReviews($approved, $commentdata)
    {
        if (!BfwSetting::get('bonus-for-otziv')) {
            return $approved;
        }

        // Только новые отзывы на товары
        if (empty($commentdata['comment_post_ID']) || get_post_type($commentdata['comment_post_ID']) !== 'product') {
            return $approved;
        }

        // Уже одобрено вручную или это правка существующего — не трогаем
        if (0 === (int) $approved || 'approve' === $approved) {
            return $approved;
        }

        return 0;
    }

    /**
     * Display text above the review title
     * Вывод текста над отзывом: оставьте отзыв и получите 20 баллов.
     *
     * @return void
     * @version 6.4.0
     */
    public static function liveReviewAndPoint(): void
    {
        $points = (int)BfwSetting::get('bonus-for-otziv', 0);

        if (is_user_logged_in() && is_product() && $points) {
            global $product;
            $current_user = wp_get_current_user();

            if (wc_customer_bought_product($current_user->user_email, $current_user->ID, $product->get_id())) {
                echo '<p class="bfw_leave_review">' . __('Leave a review and receive',
                        'bonus-for-woo') . ' ' . $points . ' ' . BfwPoints::pointsLabel($points) . '</p>';
            }
        }
    }
}
