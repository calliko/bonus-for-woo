<?php

defined('ABSPATH') || exit;

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
     * If a user came via a referral link, save it in a cookie.
     * Ранее ключ сохранялся только для гостей — из-за этого переход по
     * реферальной ссылке уже зарегистрированным покупателем нигде не
     * фиксировался. Теперь ключ сохраняется для всех: гость пронесёт его до
     * регистрации, авторизованный — до оформления заказа.
     *
     * @return void
     * @version 8.5.6
     * @since   1.9.0
     */
    public static function bfwSetCookies(): void
    {
        // В служебных запросах cookie ставить некуда и незачем.
        if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || headers_sent()) {
            return;
        }

        if (empty($_GET['bfwkey'])) {// phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $bfw_referral_key = sanitize_text_field(wp_unslash($_GET['bfwkey']));// phpcs:ignore WordPress.Security.NonceVerification
        $referral_link = trim($bfw_referral_key);

        // Ключ — буквенно-цифровой код с ID пользователя на конце.
        if ($referral_link === '' || strlen($referral_link) > 64 || !preg_match('/^[A-Za-z0-9]+$/', $referral_link)) {
            return;
        }

        $bfw_days_cookies = (int) apply_filters('bfw_referral_cookie_days', 365);
        if ($bfw_days_cookies < 1) {
            $bfw_days_cookies = 1;
        }

        $domain = COOKIE_DOMAIN ?: ($_SERVER['SERVER_NAME'] ?? '');

        setcookie('bfw_ref_cookie_set', $referral_link, array(
            'expires'  => time() + (86400 * $bfw_days_cookies),
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ));

        // Значение доступно в текущем запросе (setcookie не обновляет $_COOKIE).
        $_COOKIE['bfw_ref_cookie_set'] = $referral_link;
    }


    /**
     * Реферер из cookie (ключ из реферальной ссылки) или 0.
     *
     * @return int
     * @version 8.5.6
     */
    public static function getReferrerIdFromCookie(): int
    {
        if (empty($_COOKIE['bfw_ref_cookie_set'])) {
            return 0;
        }

        $key = trim(sanitize_text_field(wp_unslash($_COOKIE['bfw_ref_cookie_set'])));
        if ($key === '' || strlen($key) > 64) {
            return 0;
        }

        return self::getUserIdByReferralKey($key);
    }


    /**
     * Пользователь по его реферальному ключу.
     *
     * @param string $key
     *
     * @return int 0, если не найден
     * @version 8.5.6
     */
    public static function getUserIdByReferralKey(string $key): int
    {
        $key = trim($key);
        if ($key === '') {
            return 0;
        }

        $users = get_users(array(
            'meta_query' => array(
                array(
                    'key'     => 'bfw_points_referral',
                    'value'   => $key,
                    'compare' => '==',
                ),
            ),
            'number'     => 1,
            'fields'     => 'ID',
        ));

        return !empty($users) ? (int) $users[0] : 0;
    }


    /**
     * Очищает cookie реферальной ссылки (одноразовая атрибуция заказа).
     *
     * @return void
     * @version 8.5.6
     */
    public static function clearCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $domain = COOKIE_DOMAIN ?: ($_SERVER['SERVER_NAME'] ?? '');

        setcookie('bfw_ref_cookie_set', '', array(
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ));

        unset($_COOKIE['bfw_ref_cookie_set']);
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
        $retrive_data = trim($cookie_val);

        if (!empty($retrive_data)) {
            $refere_id = self::getUserIdByReferralKey($retrive_data);

            if ($refere_id > 0 && $refere_id !== (int) $user_id) {
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
            'ref-social-max' => 'https://max.ru/:share?text=',
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
     * @param $points float Баллы, которые получит реферал
     * @param $get_referral_invite int Реферер клиента
     * @param int $order_id
     * @param int $level 1 уровень или 2 уровень
     * @param string $reason_key причина для журнала (по умолчанию — по уровню)
     * @return void
     * @version 8.5.6
     */
    public static function addReferralPoints(
        int $customer_user,
        float $points,
        int $get_referral_invite,
        int $order_id,
        int $level = 1,
        string $reason_key = ''
    ): void {
        $BfwPoints = new BfwPoints();
        $points = $BfwPoints->roundPoints($points);

        /*
         * Раньше здесь стояло (int) $points === 0: при дробных баллах
         * (настройка «Не округлять десятичные дроби») начисление 0,5 балла
         * молча отбрасывалось.
         */
        if ($points <= 0) {
            return;
        }

        // Проверка минимальной суммы заказа для начисления реферального кешбэка
        $min_sum = (float) BfwSetting::get('sum-orders-for-referral', 0);
        if ($min_sum > 0 && $order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order && (float) $order->get_total() < $min_sum) {
                return; // Сумма заказа меньше минимальной — не начисляем
            }
        }

        $twolevel = ($level === 2) ? __('(second level)', 'bonus-for-woo') : '';
        $pricinaref = __('Points for referral', 'bonus-for-woo') . $twolevel;

        // Применяем фильтр для проверки необходимости проверки первого заказа
        $check_first_order = apply_filters('bfw_check_first_order_referral_filter', true, $customer_user, $get_referral_invite);

        if ($check_first_order && BfwSetting::get('first-order-referal')) {
            $numorders = wc_get_customer_order_count($customer_user);
            if ($numorders > 1) {
                return;
            }
        }

        /*
         * Начисление через журнал. Ключ (order, id, reason) + user_id реферера
         * гарантирует, что за один заказ реферальные баллы начислятся один раз
         * даже если хук начисления сработал повторно (смена статуса, 1С, ретрай).
         */
        $reason_key = $reason_key !== ''
            ? $reason_key
            : (($level === 2) ? BfwLedger::REASON_REFERRAL_L2 : BfwLedger::REASON_REFERRAL);

        $applied = BfwLedger::apply(
            $get_referral_invite,
            $points,
            $reason_key,
            'order',
            $order_id,
            $pricinaref,
            array('history' => true, 'log' => true)
        );

        if (!$applied) {
            return;
        }

        $referral_points = BfwPoints::getPoints($get_referral_invite);

        if (BfwSetting::get('email-when-order-change')) {
            $text_email = BfwSetting::get('email-when-order-change-referal-text', '');
            $title_email = BfwSetting::get('email-when-order-change-referal-title',
                __('Points accrual', 'bonus-for-woo'));

            $user = get_userdata($get_referral_invite);
            if ($user) {
                $get_referral = get_user_meta($get_referral_invite, 'bfw_points_referral', true);
                $text_email_array = array(
                    '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral),
                    '[user]' => $user->display_name,
                    '[cause]' => $pricinaref,
                    '[points]' => BfwPoints::formatNumber((float) $points),
                    '[total]' => $referral_points
                );
                $message_email = BfwEmail::template($text_email, $text_email_array);
                (new BfwEmail())->getMail($get_referral_invite, '', $title_email, $message_email);
            }
        }
    }


    /**
     * Привязка реферера к конкретному заказу по реферальной ссылке.
     *
     * Работает только если у покупателя ещё НЕТ постоянного пригласившего
     * (bfw_points_referral_invite): иначе заказ оплачивался бы дважды —
     * и как «приглашённый навсегда», и как «пришёл по ссылке».
     *
     * @param WC_Order $order
     *
     * @return void
     * @version 8.5.6
     */
    public static function attachReferrerToOrder($order): void
    {
        if (!BfwSetting::get('referal-system')) {
            return;
        }

        if (!($order instanceof WC_Order)) {
            return;
        }

        $customer_id = (int) $order->get_customer_id();

        // Гостю реферера привяжет регистрация; здесь — только авторизованные.
        if ($customer_id <= 0) {
            return;
        }

        if ((int) get_user_meta($customer_id, 'bfw_points_referral_invite', true) > 0) {
            return;
        }

        $referrer_id = self::getReferrerIdFromCookie();

        if ($referrer_id <= 0 || $referrer_id === $customer_id) {
            return;
        }

        if (!get_userdata($referrer_id)) {
            return;
        }

        $order->update_meta_data('_bfw_referrer_id', $referrer_id);
        // order может быть создан программно (импорт, 1С) — фиксируем сразу.
        if ($order->get_id()) {
            $order->save_meta_data();
        }

        /*
         * По умолчанию вознаграждение разовое: после привязки ссылка
         * «сгорает», чтобы 5% не начислялись за все последующие покупки.
         */
        if (apply_filters('bfw_referral_order_cookie_once', true, $order)) {
            self::clearCookie();
        }
    }


    /**
     * Начисление реферального вознаграждения за конкретный заказ
     * (для покупателей, пришедших по ссылке, но не привязанных навсегда).
     *
     * @param WC_Order $order
     *
     * @return void
     * @version 8.5.6
     */
    public static function maybeAccrueOrderReferral($order): void
    {
        if (!BfwSetting::get('referal-system') || !($order instanceof WC_Order)) {
            return;
        }

        $customer_id = (int) $order->get_customer_id();
        if ($customer_id <= 0) {
            return;
        }

        // Постоянная привязка — начисляет основной блок в addPointsForOrder().
        if ((int) get_user_meta($customer_id, 'bfw_points_referral_invite', true) > 0) {
            return;
        }

        $referrer_id = (int) $order->get_meta('_bfw_referrer_id');
        if ($referrer_id <= 0 || $referrer_id === $customer_id || !get_userdata($referrer_id)) {
            return;
        }
        $percent = (float) apply_filters(
            'bfw_referral_level_percent',
            (float) BfwSetting::get('referal-cashback', 0.0),
            $referrer_id,
            1,
            $order,
            $customer_id
        );

        if ($percent <= 0) {
            return;
        }

        // База — фактически оплаченная сумма за товары (без доставки и налогов).
        $paid_amount = (float) $order->get_total()
            - (float) $order->get_shipping_total()
            - (float) $order->get_total_tax();

        if ($paid_amount <= 0) {
            return;
        }

        $points = BfwPoints::roundPoints($paid_amount * ($percent / 100));

        if ($points <= 0) {
            return;
        }

        self::addReferralPoints(
            $customer_id,
            $points,
            $referrer_id,
            (int) $order->get_id(),
            1,
            BfwLedger::REASON_REFERRAL_ORDER
        );

        /*
         * Признак начисления в самом заказе — для админки и отчётов.
         * Пишем, только если операция действительно есть в журнале
         * (только что применилась или была применена раньше).
         */
        if (BfwLedger::isDuplicate($referrer_id, BfwLedger::REASON_REFERRAL_ORDER, 'order', (int) $order->get_id())
            || !BfwLedger::isAvailable()
        ) {
            $order->update_meta_data('_bfw_referrer_points', $points);
            $order->save();
        }
    }


    /**
     * Отзыв реферального вознаграждения по заказу (при возврате).
     *
     * @param int   $order_id
     * @param float $ratio      доля возврата: 1.0 — полный, иначе пропорционально
     * @param int   $refund_id  ID возврата (0 — отмена статусом)
     *
     * @return float сколько баллов отозвано
     * @version 8.5.6
     */
    public static function revokeReferralForOrder(int $order_id, float $ratio = 1.0, int $refund_id = 0): float
    {
        if ($order_id <= 0 || $ratio <= 0) {
            return 0.0;
        }

        $ratio = min(1, $ratio);

        $operations = BfwLedger::getOperations('order', $order_id, array(
            BfwLedger::REASON_REFERRAL,
            BfwLedger::REASON_REFERRAL_L2,
            BfwLedger::REASON_REFERRAL_ORDER,
        ));

        if (empty($operations)) {
            return 0.0;
        }

        $revoked_total = 0.0;

        foreach ($operations as $operation) {
            $accrued = (float) $operation->delta;

            if ($accrued <= 0) {
                continue;
            }

            $revoke = BfwPoints::roundPoints($accrued * $ratio);

            if ($revoke <= 0) {
                continue;
            }

            $applied = BfwLedger::apply(
                (int) $operation->user_id,
                -$revoke,
                BfwLedger::REASON_REFERRAL_REVOKE,
                'refund',
                $refund_id,
                sprintf(
                    /* translators: %d: order id */
                    __('Referral points revoked for order #%d', 'bonus-for-woo'),
                    $order_id
                )
            );

            if ($applied) {
                $revoked_total += $revoke;
            }
        }

        return BfwPoints::roundPoints($revoked_total);
    }


    /**
     * Отзыв реферального вознаграждения при отмене заказа (по желанию магазина).
     *
     * @param int $order_id
     *
     * @return void
     * @version 8.5.6
     */
    public static function maybeRevokeOnCancel(int $order_id): void
    {
        if (!apply_filters('bfw_referral_revoke_on_cancel', false, $order_id)) {
            return;
        }

        self::revokeReferralForOrder($order_id, 1.0, 0);
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
