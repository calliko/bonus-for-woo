<?php

defined( 'ABSPATH' ) || exit;

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
     * @version 6.4.0
     */
    public static function bfwoo_approve_comment_callback($comment): void
    {

        if (BfwSetting::get('bonus-for-otziv')) {
            $bonusfor_otziv_new = BfwSetting::get('bonus-for-otziv');
            $computy_user_point = BfwPoints::getPoints($comment->user_id) + $bonusfor_otziv_new;

            if (get_post_type($comment->comment_post_ID) === 'product') {
                add_filter('woocommerce_order_is_paid_statuses', array('BfwReview', 'bfw_paid_is_paid_status'));


                $bought_product = wc_customer_bought_product($comment->comment_author_email, $comment->user_id,
                    $comment->comment_post_ID);
                $bought_product = apply_filters('bfw_bought_product', $bought_product);

                if ($bought_product) {
                    // вы уже покупали этот товар ранее.

                    global $wpdb;
                    $count_comment = $wpdb->get_var($wpdb->prepare('SELECT COUNT(comment_ID) FROM ' . $wpdb->prefix . 'comments WHERE user_id = %d AND comment_post_ID = %d AND comment_approved ="1" ',
                        $comment->user_id, $comment->comment_post_ID));


                    if ((int)$count_comment === 1) {/*если количество отзывов у этого товара у этого клиента 0, то добавим баллы*/
                        BfwPoints::updatePoints($comment->user_id, $computy_user_point);//добавляем баллы клиенту
                        $cause = __('For review', 'bonus-for-woo');
                        /*В историю*/
                        BfwHistory::add_history($comment->user_id, '+', $bonusfor_otziv_new, '0', $cause);

                        /*email*/

                        /*шаблонизатор письма*/

                        $text_email = BfwSetting::get('email-when-review-text', '');
                        $title_email = BfwSetting::get('email-when-review-title',
                            __('Points accrual', 'bonus-for-woo'));

                        $user = get_userdata($comment->user_id);
                        $get_referral = get_user_meta($comment->user_id, 'bfw_points_referral', true);
                        $text_email_array = array(
                            '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral),
                            '[user]' => $user->display_name,
                            '[cause]' => $cause,
                            '[points]' => $bonusfor_otziv_new,
                            '[total]' => $computy_user_point
                        );
                        $message_email = BfwEmail::template($text_email, $text_email_array);
                        /*шаблонизатор письма*/


                        if (BfwSetting::get('email-when-review')) {
                            (new BfwEmail())->getMail($comment->user_id, '', $title_email, $message_email);
                        }
                        /*email*/
                    }
                }
            }
        }
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
        $bonusfor_otziv_new = (float)BfwSetting::get('bonus-for-otziv');
        $computy_user_point = BfwPoints::getPoints($comment->user_id) - $bonusfor_otziv_new;
        if (get_post_type($comment->comment_post_ID) === 'product') {
            global $wpdb;
            $count_comment = $wpdb->get_var($wpdb->prepare('SELECT COUNT(comment_ID) FROM ' . $wpdb->prefix . 'comments WHERE user_id = %d AND comment_post_ID = %d AND comment_approved ="1"',
                $comment->user_id, $comment->comment_post_ID));

            $bought_product = wc_customer_bought_product($comment->comment_author_email, $comment->user_id,
                $comment->comment_post_ID);
            if ($count_comment === 0 && $bought_product) {/*Если количество одобренных у этого товара у этого клиента 1, то удаляем баллы*/

                BfwPoints::updatePoints($comment->user_id, $computy_user_point);//Удаляем баллы клиенту
                $cause = sprintf(__('Return of %s for Product Review', 'bonus-for-woo'), BfwPoints::pointsLabel(5));
                /*В историю*/
                BfwHistory::add_history($comment->user_id, '-', $bonusfor_otziv_new, '0', $cause);

                /*email*/
                $title_email = sprintf(__('Return of %s', 'bonus-for-woo'), BfwPoints::pointsLabel(5));
                $info_email = sprintf(__('Your product review has been rejected. %1$s %2$s are canceled.',
                    'bonus-for-woo'), $bonusfor_otziv_new, BfwPoints::pointsLabel(5));
                $message_email = '<p>' . $info_email . '</p>';
                $message_email .= '<p>' . __('Cause', 'bonus-for-woo') . ': ' . $cause . '</p>';
                $message_email .= '<p>' . sprintf(__('The sum of your %s is now', 'bonus-for-woo'),
                        BfwPoints::pointsLabel(5)) . ': <b>' . $computy_user_point . ' ' . BfwPoints::pointsLabel($computy_user_point) . '</b></p>';


                if (BfwSetting::get('email-when-review')) {
                    (new BfwEmail())->getMail($comment->user_id, '', $title_email, $message_email);
                }
                /*email*/
            }
        }
    }

    /**
     * Display text above the review title
     * Вывод текста над заголовком отзыва: оставьте отзыв и получите 20 баллов.
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
