<?php

defined( 'ABSPATH' ) || exit;

/**
 * Класс реферальной системы
 *
 * @version 5.10.0
 * @since   5.10.0
 */
class BfwReferral
{


    /**
     * Generate referral code
     * Генерация реферального кода
     *
     * @return  string
     * @throws Exception
     * @version 4.4.0
     * @since   1.9.0
     */
    public static function bfw_create_referal_code(): string
    {
        $userid = get_current_user_id();
        $length = 10;
        $refkey = '';
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $characters_length = strlen($characters);

        for ($i = 0; $i < $length; $i++) {
            $key = random_int(0, $characters_length - 1);
            $refkey .= $characters[$key];
        }

        return $refkey . $userid;
    }


    /**
     * If an unregistered user came via a referral link, we save it in the session
     * Если незарегистрированный зашел по реферальной ссылке, то сохраняем ее в сессию
     *
     * @return void
     * @version 6.6.2
     *     *
     * @since   1.9.0
     */
    public static function bfwSetCookies(): void
    {
        if (!is_user_logged_in()) {

            $bfw_days_cookies = 365;
            $domain = COOKIE_DOMAIN ?? $_SERVER['SERVER_NAME'];
            if (!empty($_GET['bfwkey'])) {// phpcs:ignore WordPress.Security.NonceVerification
                $bfw_referral_key = sanitize_text_field(wp_unslash($_GET['bfwkey']));// phpcs:ignore WordPress.Security.NonceVerification
                $referral_link = trim($bfw_referral_key);// phpcs:ignore WordPress.Security.NonceVerification
                setcookie('bfw_ref_cookie_set', $referral_link, time() + (86400 * $bfw_days_cookies), '/', $domain,
                    true);
            }
        }
    }


    /**
     * User registration action
     * Действие при регистрации пользователя
     *
     * @param int $user_id
     *
     * @return void
     * @throws Exception
     * @version 5.10.0
     * @since   1.9.0
     */
    public static function registerInvate(int $user_id): void
    {
        // Генерируем реферальную ссылку
        $referral_key = self::bfw_create_referal_code();
        update_user_meta($user_id, 'bfw_points_referral', $referral_key);

        // У которого в куках есть bfw_ref_cookie_set
        $cookie_val = isset($_COOKIE['bfw_ref_cookie_set'])
            ? sanitize_text_field(wp_unslash($_COOKIE['bfw_ref_cookie_set']))
            : '';
        $retrive_data = $cookie_val;

        if (!empty($retrive_data)) {
            $args = array(); // Добавляем инициализацию массива $args
            $args['meta_query'] = array(
                array(
                    'key' => 'bfw_points_referral',
                    'value' => trim($retrive_data),
                    'compare' => '==',
                ),
            );

            $refere_data = get_users($args);

            if (!empty($refere_data)
                && isset($refere_data[0])
            ) { // Проверяем наличие данных и первого элемента
                $refere_id = $refere_data[0]->data->ID;
                update_user_meta(
                    $user_id,
                    'bfw_points_referral_invite',
                    $refere_id
                );
            }
        }
    }


    /**
     * Social Links Output
     * Вывод социальных ссылок
     *
     * @param string $url
     * @param string $title
     * @param string $description
     *
     * @return string
     * @version 5.10.0
     */
    public static function bfwSocialLinks(string $url, string $title, string $description): string
    {
        $options = get_option('bonus_option_name');
        $socialLinks = [
            'ref-social-vk' => 'https://vk.com/share.php?url=',
            'ref-social-fb' => 'https://www.facebook.com/sharer/sharer.php?u=',
            'ref-social-tw' => 'https://twitter.com/share?url=',
            'ref-social-tg' => 'https://telegram.me/share/url?url=',
            'ref-social-whatsapp' => 'https://api.whatsapp.com/send?text=',
            'ref-social-viber' => 'viber://forward?text='
        ];

        $urlEncoded = urlencode($url);
        $titleEncoded = urlencode($title . ' ' . $description);

        $links = array_map(function ($key, $baseLink) use ($options, $urlEncoded, $titleEncoded) {
            if (empty($options[$key])) {
                return '';
            }

            $link = $baseLink . $urlEncoded;
            if ($key === 'ref-social-tg') {
                $link .= '&text=' . $titleEncoded;
            }

            $iconClass = 'bfw_ref_icon_' . substr($key, 11);
            return '<a rel="nofollow" target="_blank" href="' . $link . '" class="bfw_social_link_item ' . $iconClass . '"></a>';
        }, array_keys($socialLinks), $socialLinks);

        $links = array_filter($links); // Удаляем пустые элементы

        return '<div class="bfw_social_links">' . implode('', $links) . '</div>';
    }


    /**
     * Add points to the referrer (inviter, sponsor)
     * Добавляем баллы рефереру (пригласителю, спонсору)
     *
     * @param $customer_user int id клиента
     * @param $percent_for_referral int Процент, который получит реферал
     * @param $total float Общая сумма заказа
     * @param $computy_point_fast float Баллы, которые использует покупатель
     * @param $get_referral_invite int Реферер клиента
     * @return void
     * @deprecated
     * @version 5.10.0
     */
    public static function addReferralPointsOLD(
        int $customer_user,
        int $percent_for_referral,
        float $total,
        float $computy_point_fast,
        int $get_referral_invite
    ): void {
        $BfwPoints = new BfwPoints();
        $referral_point_new = ($total - $computy_point_fast) * ($percent_for_referral / 100);
        $referral_point_new = $BfwPoints->roundPoints($referral_point_new);

        $old_point_referral = $BfwPoints->getPoints($get_referral_invite);
        $referral_points = $old_point_referral + $referral_point_new;

        if ((int)$referral_points !== 0) {
            $pricinaref = __('Points for referral', 'bonus-for-woo');
            $text_email = BfwSetting::get('email-when-order-change-referal-text', '');
            $title_email = BfwSetting::get('email-when-order-change-referal-title',
                __('Points accrual', 'bonus-for-woo'));

            $user = get_userdata($get_referral_invite);
            $get_referral = get_user_meta($get_referral_invite, 'bfw_points_referral', true);
            $text_email_array = array(
                '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral),
                '[user]' => $user->display_name,
                '[cause]' => $pricinaref,
                '[points]' => $referral_point_new,
                '[total]' => $referral_points
            );
            $message_email = BfwEmail::template($text_email, $text_email_array);


            if (BfwSetting::get('first-order-referal')) {
                $numorders = wc_get_customer_order_count($customer_user);
                if ($numorders == 1) {
                    $BfwPoints->updatePoints($get_referral_invite, $referral_points);
                    BfwHistory::add_history($get_referral_invite, '+', $referral_point_new, '0', $pricinaref);
                    if (BfwSetting::get('email-when-order-change')) {
                        (new BfwEmail())->getMail($get_referral_invite, '', $title_email, $message_email);
                    }
                }

            } else {
                $BfwPoints->updatePoints($get_referral_invite, $referral_points);
                BfwHistory::add_history($get_referral_invite, '+', $referral_point_new, '0', $pricinaref,
                    $customer_user);
                if (BfwSetting::get('email-when-order-change')) {
                    (new BfwEmail())->getMail($get_referral_invite, '', $title_email, $message_email);
                }
            }

        }
    }

    /**
     * Add points to the referrer (inviter, sponsor)
     * Добавляем баллы рефереру (пригласителю, спонсору)
     *
     * @param $customer_user int id клиента
     * @param $points float Баллы, которые получит реферал
     * @param $get_referral_invite int Реферер клиента
     * @param int $order_id
     * @return void
     * @version 7.0.0
     */
    public static function addReferralPoints(
        int $customer_user,
        float $points,
        int $get_referral_invite,
        int $order_id
    ): void {
        $BfwPoints = new BfwPoints();
        $points = $BfwPoints->roundPoints($points);
        $old_point_referral = $BfwPoints->getPoints($get_referral_invite);
        $referral_points = $old_point_referral + $points;

        if ((int)$points !== 0) {
            $pricinaref = __('Points for referral', 'bonus-for-woo');
            $text_email = BfwSetting::get('email-when-order-change-referal-text', '');
            $title_email = BfwSetting::get('email-when-order-change-referal-title',
                __('Points accrual', 'bonus-for-woo'));

            $user = get_userdata($get_referral_invite);
            $get_referral = get_user_meta($get_referral_invite, 'bfw_points_referral', true);
            $text_email_array = array(
                '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral),
                '[user]' => $user->display_name,
                '[cause]' => $pricinaref,
                '[points]' => $points,
                '[total]' => $referral_points
            );
            $message_email = BfwEmail::template($text_email, $text_email_array);


            if (BfwSetting::get('first-order-referal')) {
                $numorders = wc_get_customer_order_count($customer_user);
                if ($numorders == 1) {
                    $BfwPoints->updatePoints($get_referral_invite, $referral_points);
                    BfwHistory::add_history($get_referral_invite, '+', $points, $order_id, $pricinaref);
                    if (BfwSetting::get('email-when-order-change')) {
                        (new BfwEmail())->getMail($get_referral_invite, '', $title_email, $message_email);
                    }
                }

            } else {
                $BfwPoints->updatePoints($get_referral_invite, $referral_points);
                BfwHistory::add_history($get_referral_invite, '+', $points, $order_id, $pricinaref, $customer_user);
                if (BfwSetting::get('email-when-order-change')) {
                    (new BfwEmail())->getMail($get_referral_invite, '', $title_email, $message_email);
                }
            }

        }
    }


    /**
     * Очищаем ссылку на пригласителя у приглашённых при удалении пользователя
     *
     * @param $deleted_user_id
     * @return void
     * @version 7.4.4
     */
    public static function bfw_clear_referral_invites_on_delete($deleted_user_id)
    {
        if (empty($deleted_user_id)) {
            return;
        }
        $users = get_users(array(
            'meta_key' => 'bfw_points_referral_invite',
            'meta_value' => (string)$deleted_user_id,
            'number' => -1,
            'fields' => 'ID',
        ));
        if (!empty($users)) {
            foreach ($users as $uid) {
                delete_user_meta($uid, 'bfw_points_referral_invite');
            }
        }
    }
}
