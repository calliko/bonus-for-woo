<?php

defined('ABSPATH') || exit;

/**
 * Client Account Class
 * Класс аккаунта клиентов
 *
 * @version 6.4.0
 */
class BfwAccount
{

    /**
     * Account Bonus Page Title
     * Заголовок страницы бонусов в аккаунте
     *
     * @return void
     * @version 6.4.0
     */
    public static function accountTitle(): void
    {
        echo esc_html(BfwSetting::get('title-on-account', __('Bonus page', 'bonus-for-woo')));
    }

    /**
     * Displaying client status
     * Вывод статуса клиента
     *
     * @return string
     * @version 6.4.0
     */
    public static function getStatus(): string
    {
        $roles = BfwRoles::getRole(get_current_user_id());
        return $roles['name'];
    }

    /**
     * Display of cashback percentage
     * Вывод процента кешбэка
     *
     * @return string
     * @version 6.4.0
     */
    public static function getCashback(): string
    {
        $roles = BfwRoles::getRole(get_current_user_id());
        return $roles['percent'] . '%';
    }


    /**
     * Display basic information: status, cashback percentage, number of bonus points
     * Вывод основной информации: статус, процент кешбэка, количество бонусных баллов
     *
     * @param array $shortcode
     * @throws DateMalformedStringException
     * @version 7.1.2
     */
    public static function accountBasicInfo($shortcode = [])
    {
        // Обработка параметров шорткода
        $shortcode = shortcode_atts([
                'view' => 'false'
        ], $shortcode, 'bfw_cart_user');

        // Получаем ID текущего пользователя
        $userId = get_current_user_id();

        // Если пользователь не участвует в бонусной системе
        if (!BfwRoles::isInvalve($userId)) {
            echo '<p class="bfw_not_participant">' . esc_html(__('You do not participate in the bonus system.',
                            'bonus-for-woo')) . '</p>';
            return;
        }

        // Загружаем настройки из опций
        $options = BfwSetting::get_all();

        // Текстовые метки
        $titleStatus = $options['title-my-status-on-account'] ?? __('My status', 'bonus-for-woo');
        $titlePercent = $options['my-procent-on-account'] ?? __('My cashback percentage', 'bonus-for-woo');
        $titleBonusPoints = $options['bonus-points-on-cart'] ?? __('Bonus points', 'bonus-for-woo');

        // Активируем ежедневные начисления и обновляем роль
        BfwPoints::addEveryDays($userId);
        BfwRoles::updateRole($userId);

        // Получаем данные пользователя
        $role = BfwRoles::getRole($userId);
        $points = BfwPoints::getPoints($userId);
        $roundedPoints = BfwPoints::roundPoints($points);
        $pointLabel = BfwPoints::pointsLabel($points);

        // Формируем HTML
        $output = self::renderCardHeader($options, $role['slug']);
        $output .= self::renderStatusRow($titleStatus, $role['name']);
        $output .= self::renderPercentRow($titlePercent, (float)$role['percent']);

        $output .= self::renderPointsRow($roundedPoints, $pointLabel, $options, $userId, $titleBonusPoints);


        $output .= '</div>';

        // Возвращаем или выводим результат
        if ($shortcode['view'] === 'true') {
            return $output;
        } else {
            echo wp_kses_post($output);
        }
    }

    /**
     * Шапка карточки
     *
     * @param array $options
     * @param null $slug ||слаг статуса
     * @return string
     * @version 7.1.2
     */
    private static function renderCardHeader(array $options, $slug = null): string
    {
        $card = '<div class="bfw-card ' . $slug . '">';
        if (!empty($options['rulles_url'])) {
            $card .= sprintf(
                    '<a target="_blank" href="%s" class="card_link_rulles" title="%s">%s</a>',
                    esc_url($options['rulles_url']),
                    esc_html($options['rulles_value']),
                    esc_html($options['rulles_value'])
            );
        }
        return $card;
    }

    /** Строка статуса
     *
     * @param string $label
     * @param string $value
     * @return string
     * @version 7.1.2
     */
    private static function renderStatusRow(string $label, string $value): string
    {
        return sprintf(
                '<div class="bonus_computy_account bfw-account_status_name">
            <span class="title_bca">%s:</span>
            <span class="value_bca"> %s</span>
         </div>',
                esc_html($label),
                esc_html($value)
        );
    }


    /** Строка процента
     *
     * @param string $label
     * @param float $percent
     * @return string
     * @version 7.1.2
     */
    private static function renderPercentRow(string $label, float $percent): string
    {
        // Автоматически форматируем число: убираем лишние нули
        $formattedPercent = (float)(string)$percent === (float)(int)$percent
                ? (int)$percent
                : rtrim(rtrim(sprintf('%.2f', $percent), '0'), '.');

        return sprintf(
                '<div class="bonus_computy_account bfw-account_percent">
            <span class="title_bca">%s:</span>
            <span class="value_bca">%s%%</span>
        </div>',
                esc_html($label),
                esc_html($formattedPercent)
        );
    }

    /** Строка с бонусными баллами и уведомлением о сроке действия
     *
     * @param float $points
     * @param string $label
     * @param array $options
     * @param int $userId
     * @param string $tooltipTitle
     * @return string
     * @version 7.1.2
     */
    private static function renderPointsRow(
            float $points,
            string $label,
            array $options,
            int $userId,
            string $tooltipTitle
    ): string {
        $output = sprintf(
                '<div class="bonus_computy_account bfw-account_count_points">
            <span class="value_bca">%s %s</span>',
                number_format($points, 0, '', ' '),
                esc_html($label)
        );

        $daysLeft = BfwRoles::isPro() ? self::calculatePointsExpirationDays($options, $userId, $points) : 0;
        if ($daysLeft > 0) {
            $dayText = self::formatDays($daysLeft);
            $output .= sprintf(
                    '<div class="bfw-account_expire_points bfw-help-tip danger" data-tip="%s %s %s %s">
                %s %s
             </div>',
                    esc_attr($tooltipTitle),
                    esc_attr(__('will expire after', 'bonus-for-woo')),
                    number_format($daysLeft, 0, '', ' '),
                    esc_attr($dayText),
                    number_format($daysLeft, 0, '', ' '),
                    esc_html($dayText)
            );
        }

        $output .= '</div>';
        return $output;
    }


    /** Расчёт дней до истечения срока баллов
     *
     * @param array $options
     * @param int $userId
     * @param float $points
     * @return int
     * @version 7.1.2
     */
    private static function calculatePointsExpirationDays(array $options, int $userId, float $points): int
    {
        if (
                !empty($options['burn_point_in_account']) ||
                !isset($options['day-inactive']) ||
                $options['day-inactive'] <= 0 ||
                $points <= 0
        ) {
            return 0;
        }

        try {
            $customer = new WC_Customer($userId);
            $lastOrder = $customer->get_last_order();

            if ($lastOrder) {
                $orderDate = $lastOrder->get_date_created();
                $dateCreated = new DateTime($orderDate->format('Y-m-d H:i:s'));
            } else {
                $userData = get_userdata($userId);
                $dateCreated = new DateTime($userData->user_registered);
            }

            $now = new DateTime('now');
            $daysSinceLastActivity = $now->diff($dateCreated)->days;
            return max(0, $options['day-inactive'] - $daysSinceLastActivity);
        } catch (Exception $e) {
            // error_log('Error calculating expiration days: ' . $e->getMessage());
            return 0;
        }
    }

    /** Склонение слова "день"
     *
     * @param int $days
     * @return string
     * @version 7.1.2
     */
    private static function formatDays(int $days): string
    {
        $text = __('days', 'bonus-for-woo');
        if (get_bloginfo('language') === 'ru-RU') {
            $text = BfwFunctions::declination($days, 'день', 'дня', 'дней');
        }
        return $text;
    }

    /**
     * Display number of points.
     * Вывод количества баллов.
     *
     * @return float
     * @version 6.4.0
     */
    public static function getPoints(): float
    {
        return BfwPoints::getPoints(get_current_user_id());
    }

    /**
     * Display coupons (PRO version only)
     * Ввод купонов(только для PRO-версии)
     *
     * @return string
     */
    public static function accountCoupon(): string
    {
        if (BfwRoles::isPro() && BfwSetting::get('coupon-system') && BfwRoles::isInvalve(get_current_user_id())) {

            $bonus_text = mb_strtolower(BfwSetting::get('bonus-points-on-cart'));
            if ($bonus_text) {
                $coupon_message =
                        /* translators: %s will be replaced with the bonus points amount (lowercase) */
                        sprintf(__('Enter coupon code to take %s.', 'bonus-for-woo'), $bonus_text);

                return '<div class="bonus_computy_account add_coupon">
<div class="title computy_skidka_link">' . esc_html($coupon_message) . '</div>
<div class="computy_skidka_container coupon_form" style="display: none;">
<form class="take_coupon_form" action="' . esc_url(admin_url("admin-post.php")) . '" method="post">
 <input type="hidden" name="action" value="bfw_take_coupon_action" />
 <input type="hidden" name="redirect" value="' . esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))) . 'bonuses">
<input class="input-text" type="text" name="code_coupon" placeholder="' . __('Coupon code', 'bonus-for-woo') . '" required>
<input type="submit" class="code_coupon_submit" value="' . __('To take', 'bonus-for-woo') . '"><div class="message_coupon"></div>
</form>
</div>
</div>';
            }
        }
        return '';
    }


    /**
     * Вывод информации о прогрессе пользователя в бонусной системе
     *
     * @return void
     * @version 7.1.2
     */
    public static function accountProgress(): void
    {
        $userId = get_current_user_id();
        $bfwRoles = new BfwRoles();
        $bfwPoints = new BfwPoints();

        // Проверяем участие пользователя и наличие следующего статуса
        if (!$bfwRoles::isInvalve($userId)) {
            return;
        }

        $nextRole = $bfwRoles::getNextRole($userId);
        if (!in_array($nextRole['status'], ['next', 'max', 'no'])) {
            return;
        }

        // Получаем данные
        $totalOrdersSum = $bfwPoints::getSumUserOrders($userId);
        $rolesList = $bfwRoles::getRoles();
        $currentRole = $bfwRoles::getRole($userId)['name'];
        $percentEarned = $nextRole['percent-zarabotannogo'] ?? 0;

        // Формируем HTML
        $output = self::renderProgressBar($rolesList, $totalOrdersSum, $currentRole);
        $output .= self::renderProgressDetails($nextRole, $totalOrdersSum, $percentEarned);
        $output .= self::renderStatusMessage($nextRole);

        echo $output;
    }

    /** Рендер прогресс-бара (линейка статусов)
     *
     * @param array $roles
     * @param float $totalSum
     * @param string $currentRole
     * @return string
     * @version 7.1.2
     */
    private static function renderProgressBar(array $roles, float $totalSum, string $currentRole): string
    {
        $progressBar = '<ol class="bfw-progress-bar">';

        foreach ($roles as $role) {
            $isComplete = $role->summa_start < $totalSum ? ' is-complete' : '';
            $isActive = $currentRole === $role->name ? ' is-active' : '';
            $progressBar .= "<li class=\"{$isComplete}{$isActive}\"><span>{$role->name}</span></li>";
        }

        $progressBar .= '</ol>';
        return $progressBar;
    }

    /** Блок с деталями прогресса (полоса загрузки + текст)
     *
     * @param array $nextRole
     * @param float $totalSum
     * @param float $percentEarned
     * @return string
     * @version 7.1.2
     */
    private static function renderProgressDetails(array $nextRole, float $totalSum, float $percentEarned): string
    {
        $width = $nextRole['status'] !== 'max' ? $percentEarned : 100;
        $textPositionStyle = $percentEarned < 10 ? 'left:6px;' : 'right:6px;';

        $currentRoleName = $nextRole['name'] ?? '';
        $currencySymbol = get_woocommerce_currency_symbol();
        $roundedTotal = $totalSum ? BfwPoints::roundPoints($totalSum) . $currencySymbol : '';

        ob_start(); ?>
        <div class="bfw-progressbar-block">
            <style>
                #bfw-progressbar > div {
                    width: <?php echo esc_attr($width) ?>%;
                }

                #bfw-progressbar > div span {
                <?php echo esc_attr($textPositionStyle) ?>
                }
            </style>

            <div class="bfw-progressbar-title">
                <div class="bfw-progressbar-title-one"><?php echo esc_html($nextRole['current_role_name'] ?? '') ?></div>
                <div class="bfw-progressbar-title-two"><?php echo esc_html($nextRole['status'] !== 'max' ? $currentRoleName : '') ?></div>
            </div>

            <div id="bfw-progressbar">
                <div><span><?php echo esc_html($roundedTotal) ?></span></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Сообщение под прогресс-баром
     *
     * @param array $nextRole
     * @return string
     */
    private static function renderStatusMessage(array $nextRole): string
    {

        switch ($nextRole['status']) {
            case 'next':
                $template = BfwSetting::get('remaining-amount',
                        __('Up to [percent]% cashback and «[status]» status, you have [sum] left to spend.',
                                'bonus-for-woo'));

                $replacements = [
                        '[percent]' => $nextRole['percent'] ?? 0,
                        '[status]' => $nextRole['name'] ?? '',
                        '[sum]' => ($nextRole['sum'] ?? 0) . ' ' . get_woocommerce_currency_symbol() . '</b>'
                ];

                $message = str_replace(
                        array_keys($replacements),
                        array_values($replacements),
                        $template
                );

                return '<small class="remaining-amount">' . wp_kses_post($message) . '</small>';

            case 'max':
                return '<small class="remaining-amount">' . __('You have the maximum cashback!',
                                'bonus-for-woo') . '</small>';

            case 'no':
                return '<small class="remaining-amount">' . __('At the moment, the bonus system is not available.',
                                'bonus-for-woo') . '</small>';

            default:
                return '';
        }
    }

    /**
     * Referral system (only for PRO version)
     * Реферальная система (только для PRO-версии)
     *
     * @return string
     * @throws Exception
     * @version 6.3.4
     */
    public static function accountReferral(): string
    {
        $userId = get_current_user_id();
        if (!BfwRoles::isInvalve($userId)) {
            return '';
        }

        if (!BfwSetting::get('referal-system')) {
            return '';
        }

        $bfwReferral = new BfwReferral();
        $bfwPoints = new BfwPoints();

        // Handle referral code
        $referralCode = get_user_meta($userId, 'bfw_points_referral', true);
        if (empty($referralCode)) {
            $referralCode = $bfwReferral::bfw_create_referal_code();
            update_user_meta($userId, 'bfw_points_referral', $referralCode);
        }

        // Handle referral invite count
        $referralInviteCount = get_user_meta($userId, 'bfw_points_referral_invite', true);
        if (empty($referralInviteCount)) {
            update_user_meta($userId, 'bfw_points_referral_invite', 0);
        }

        // Get direct referrals
        $directReferrals = self::getDirectReferrals($userId);
        $directReferralCount = count($directReferrals);

        // Get second level referrals if enabled
        $secondLevelReferralCount = 0;

        if (BfwSetting::get('level-two-referral')) {
            $secondLevelReferralCount = self::getSecondLevelReferralCount($directReferrals);
        }

        // Check order total requirement
        $requiredOrderTotal = BfwSetting::get('sum-orders-for-referral', 0.0);
        $userOrderTotal = $bfwPoints::getSumUserOrders($userId);

        if ($userOrderTotal < $requiredOrderTotal) {
            $remainingAmount = $requiredOrderTotal - $userOrderTotal;
            return self::renderRemainingAmountMessage($remainingAmount);
        }

        return self::renderReferralInfo(
                $referralCode,
                $directReferralCount,
                $secondLevelReferralCount,
                BfwSetting::get('ref-social-qrcode', false)
        );
    }

    private static function getDirectReferrals(int $userId): array
    {
        return get_users([
                'meta_query' => [
                        [
                                'key' => 'bfw_points_referral_invite',
                                'value' => $userId,
                                'compare' => '=',
                                'type' => 'NUMERIC'
                        ]
                ],
                'fields' => 'ID' // Only get what we need
        ]);
    }

    private static function getSecondLevelReferralCount(array $directReferrals): int
    {
        $count = 0;
        foreach ($directReferrals as $referral) {
            $args = [
                    'meta_query' => [
                            [
                                    'key' => 'bfw_points_referral_invite',
                                    'value' => $referral->ID,
                                    'compare' => '=='
                            ]
                    ]
            ];
            $count += count(get_users($args));
        }
        return $count;
    }

    private static function renderRemainingAmountMessage(float $remainingAmount): string
    {
        return sprintf(
                '<small class="remaining-amount">%s %s %s</small>',
                __('To start using the referral system, you need to buy goods for', 'bonus-for-woo'),
                $remainingAmount,
                get_woocommerce_currency_symbol()
        );
    }

    private static function renderReferralInfo(
            string $referralCode,
            int $directReferralCount,
            int $secondLevelReferralCount,
            bool $showQrCode
    ): string {
        $url = esc_url(site_url() . '?bfwkey=' . $referralCode);
        $title = get_bloginfo('name');
        $description = get_bloginfo('description');
        $bfwReferral = new BfwReferral();

        $output = sprintf(
                '<div class="bonus_computy_account bfw-account_referral">
            <span class="title_bca">%s</span> 
            <code id="code_referal" class="value_bca">%s</code> 
            <span title="%s" id="copy_referal"></span>
            <span id="copy_good"></span>
        </div>',
                __('My referral link', 'bonus-for-woo'),
                $url,
                __('Copy link', 'bonus-for-woo')
        );

        $output .= $bfwReferral::bfwSocialLinks($url, $title, $description);

        if ($showQrCode) {
            $output .= self::renderQrCode($referralCode, $url);
        }

        $output .= sprintf(
                '<div class="bonus_computy_account">
            <span class="title_bca">%s</span> 
            <span class="value_bca">%d %s</span>
        </div>',
                __('You invited', 'bonus-for-woo'),
                $directReferralCount,
                __('people', 'bonus-for-woo')
        );

        if ($secondLevelReferralCount > 0) {
            $output .= sprintf(
                    '<div class="bonus_computy_account">
                <span class="title_bca">%s</span> 
                <span class="value_bca">%d %s</span>
            </div>',
                    __('Your friends invited', 'bonus-for-woo'),
                    $secondLevelReferralCount,
                    __('people', 'bonus-for-woo')
            );
        }

        return $output;
    }

    private static function renderQrCode(string $referralCode, string $url): string
    {
        // Sanitize referral code to prevent directory traversal
        $referralCode = preg_replace('/[^a-zA-Z0-9_-]/', '', $referralCode);
        if (empty($referralCode)) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'] . '/qrcodes/';
        $upload_url = $upload_dir['baseurl'] . '/qrcodes/';

        if (!file_exists($upload_path)) {
            wp_mkdir_p($upload_path);
        }

        $qrCodePath = $upload_path . $referralCode . '.png';
        if (!file_exists($qrCodePath)) {
            require_once BONUS_COMPUTY_PLUGIN_DIR . '_inc/phpqrcode/qrlib.php';
            try {
                QRcode::png($url, $qrCodePath, 'L', '6px');
                chmod($qrCodePath, 0644); // Set proper permissions
            } catch (Exception $e) {
                error_log('QR Code generation failed: ' . $e->getMessage());
                return '';
            }
        }

        return sprintf(
                '<img class="bfw-qrcode" width="200" src="%s" alt="%s">',
                esc_url($upload_url . $referralCode . '.png'),
                esc_attr__('Referral QR Code', 'bonus-for-woo')
        );
    }


    /**
     * Referral link output
     * Вывод реферальной ссылки
     *
     * @return string|null
     * @version 6.3.4
     */
    public static function getReferralLink(): ?string
    {
        $userid = get_current_user_id();
        $get_referral = get_user_meta($userid, 'bfw_points_referral', true);

        $requiredOrderTotal = BfwSetting::get('sum-orders-for-referral', 0.0);
        $userOrderTotal = BfwPoints::getSumUserOrders($userid);

        if (!empty($get_referral) && $userOrderTotal >= $requiredOrderTotal) {
            return '<div class="bonus_computy_account bfw-account_referral"><span class="title_bca">' . __('My referral link',
                            'bonus-for-woo') . ':</span> <code id="code_referal" class="value_bca">' . esc_url(site_url() . '?bfwkey=' . $get_referral) . '</code> <span  title="' . __('Copy link',
                            'bonus-for-woo') . '"  id="copy_referal"></span><span id="copy_good"></span> </div>';
        }

        return null;
    }

    /**
     * Create a link in the menu woocommerce account bonus system
     * Создаем ссылку в меню woocommerce account бонусная система
     *
     * @param $menu_links
     *
     * @return array
     * @version 6.3.4
     */
    public static function bonusesLink($menu_links): array
    {

        $poryadok = BfwSetting::get('poryadok-in-account', 4);
        $title_page = BfwSetting::get('title-on-account', __('Bonus page', 'bonus-for-woo'));
        if (empty($title_page)) {
            $title_page = __('Bonus page', 'bonus-for-woo');
        }
        $menu_links = array_slice($menu_links, 0, $poryadok,
                        true) + array('bonuses' => $title_page) + array_slice($menu_links, $poryadok, null, true);
        $menu_links['bonuses'] = $title_page;

        return $menu_links;
    }

    /**
     * Points accrual history
     * История начисления баллов
     *
     * @return void
     * @version 6.3.4
     */
    public static function accountHistory(): void
    {
        if (!BfwSetting::get('hystory-hide')) {
            echo BfwHistory::getHistory(get_current_user_id());
        }

    }

    /**
     * Output of a link to the terms of the bonus system
     * Вывод ссылки на условия бонусной системы
     *
     * @return void
     * @version 7.6.0
     */
    public static function accountRules(): void
    {
        if (BfwSetting::get('rulles_url')) {
            echo '<a class="bfw_link_rulles" href="' . esc_url(BfwSetting::get('rulles_url')) . '">' . esc_html(BfwSetting::get('rulles_value')) . '</a>';
        }
    }


    /**
     * Adding points when registering a user
     * Добавление баллов при регистрации пользователя
     *
     * @param $user_id int
     *
     * @return void
     * @version 6.4.0
     */
    public static function actionPointsForRegistrationBfw(int $user_id): void
    {
        if (!empty($user_id)) {
            // Не начисляем ежедневные баллы в день регистрации
            update_user_meta($user_id, 'points_every_day', gmdate('d'));

            $bfwRoles = new BfwRoles();
            $bfwRoles::updateRole($user_id, false);

            $pointsForReg = BfwSetting::get('points-for-registration', 0);
            $allowPoints = 1;

            // Только для рефералов
            if (BfwSetting::get('register-points-only-referal')) {
                $cookieVal = isset($_COOKIE['bfw_ref_cookie_set']) ? sanitize_text_field(wp_unslash($_COOKIE['bfw_ref_cookie_set'])) : '';
                if (empty($cookieVal)) {
                    $allowPoints = 0;
                }
            }

            // Начисление баллов
            if ($allowPoints === 1 && $pointsForReg > 0) {
                $bfwPoints = new BfwPoints();
                $bfwPoints::updatePoints($user_id, $pointsForReg);
                /* translators: %s will be replaced with the name of the bonus points. */
                $reason = sprintf(__('%s for registration.', 'bonus-for-woo'), $bfwPoints::pointsLabel(5));

                // Записываем в историю
                $bfwHistory = new BfwHistory();
                $bfwHistory::clearAllHistoryUser($user_id);
                $bfwHistory::add_history($user_id, '+', $pointsForReg, '0', $reason);

                $user = get_userdata($user_id);

                // Шаблон письма
                $textEmail = BfwSetting::get('email-when-register-text', '');
                $titleEmail = BfwSetting::get('email-when-register-title',
                        __('Bonus points have been added to you!', 'bonus-for-woo'));
                $referralLink = get_user_meta($user_id, 'bfw_points_referral', true);
                $textEmailArray = array(
                        '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $referralLink),
                        '[user]' => $user->display_name,
                        '[points]' => $pointsForReg,
                        '[total]' => $pointsForReg,
                        '[cause]' => $reason
                );
                $messageEmail = (new BfwEmail())::template($textEmail, $textEmailArray);

                // Отправляем email клиенту

                if (BfwSetting::get('email-when-register')) {
                    (new BfwEmail())->getMail($user_id, '', $titleEmail, $messageEmail);
                }
            }
        }
    }


    /**
     * Birthday field
     * Поле дня рождения
     *
     * @return void
     * @version 6.4.0
     */
    public static function bfwDobAccountDetails(): void
    {

        if (BfwSetting::get('birthday')) {
            $user = wp_get_current_user();
            $disabled = '';
            if (!empty(esc_attr($user->dob))) {
                $disabled = 'disabled';
            }

            ?>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="dob"><?php esc_html_e('Date of birth', 'bonus-for-woo'); ?>
                    <span class="bfw-help-tip faq"
                          data-tip="<?php esc_html_e('Required to earn bonus points.', 'bonus-for-woo'); ?>"></span>
                </label>
                <input type="date"
                       class="woocommerce-Input woocommerce-Input--text input-text"
                       name="dob" id="dob" value="<?php
                echo esc_attr($user->dob); ?>" <?php
                echo esc_attr($disabled); ?>/>
            </p>
            <?php
        }
    }

    /**
     * Saving the birthday field
     * Сохранение поля дня рождения
     *
     * @param $user_id int
     *
     * @return void
     * @version 6.4.0
     */
    public static function bfwDobSaveAccountDetails(int $user_id): void
    {
        if (isset($_POST['dob'])) {
            update_user_meta($user_id, 'dob', sanitize_text_field($_POST['dob']));
        }
    }

    /**
     * Display text above the registration form
     * Вывод текста над формой регистрации
     *
     * @return void
     * @version 6.4.0
     */
    public static function formRegister(): void
    {
        $pointsForRegistration = BfwSetting::get('points-for-registration');

        if ($pointsForRegistration && !BfwSetting::get('register-points-only-referal')) {
            echo '<div class="bfw-in-register-form">' . sprintf(__('Register and get %1$d %2$s.', 'bonus-for-woo'),
                            $pointsForRegistration, BfwPoints::pointsLabel($pointsForRegistration)) . '</div>';
        }
    }


    /**
     * Adding points when a user logs in
     * Добавление баллов при авторизации пользователя
     *
     * @param $user_login !!!нельзя удалять параметр!!!
     * @param $user
     *
     * @return void
     * @version 6.4.0
     */
    public static function addBallWhenUserLogin($user_login, $user): void
    {
        //Начисление ежедневных баллов за первый вход
        (new BfwPoints())::addEveryDays($user->ID);
    }

    /**
     * Add the "bonuses" endpoint
     * Добавляем конечную точку bonuses
     *
     * @return void
     * @version 6.4.0
     */
    public static function bonusesAddEndpoint(): void
    {
        add_rewrite_endpoint('bonuses', EP_PAGES);
    }

    /**
     * Adding content to the bonus page
     * Добавляем контент на страницу бонусов
     *
     * @return void
     * @version 6.4.0
     */
    public static function accountContent(): void
    {
        //Если есть шаблон в теме, то используем его
        if (file_exists(get_stylesheet_directory() . '/bonus-for-woo/account.php')) {
            get_template_part('bonus-for-woo/account');
        } else {
            require_once BONUS_COMPUTY_PLUGIN_DIR . '/templates/account.php';
        }
    }

    /**
     * Account output via shortcode
     * Вывод аккаунта через шорткод
     *
     * @return string
     * @version 6.4.0
     */
    public static function accountContentShortcode(): string
    {
        ob_start();
        if (file_exists(get_stylesheet_directory() . '/bonus-for-woo/account.php')) {
            get_template_part('bonus-for-woo/account');
        } else {
            require_once BONUS_COMPUTY_PLUGIN_DIR . '/templates/account.php';
        }
        $string = ob_get_contents();

        ob_end_clean();
        return $string;
    }


    /**
     * Saving changes to the client profile
     * Сохранение изменений в профиле клиента
     *
     * @param int $user_id
     *
     * @return void
     * @version 7.1.2
     */
    public static function profileUserUpdate(int $user_id): void
    {
        // Verify current user can edit the user
        if (!current_user_can('edit_user', $user_id) ||
                (get_current_user_id() !== $user_id && !current_user_can('edit_users'))) {
            return;
        }

        $roles = new BfwRoles();
        $points = new BfwPoints();
        $history = new BfwHistory();
        $email = new BfwEmail();

        if ($roles::isPro()) {
            self::handleOfflineOrderPrice($user_id, $points);
            self::updateDobMeta($user_id);
            self::updateReferralLink($user_id);
        }

        // Обработка изменения баллов
        if (isset($_POST['computy_input_points'])) {
            self::processPointsChange($user_id, $points, $history, $email);
        }
    }

    /**
     * Обработка сохранения оффлайн-заказа
     */
    private static function handleOfflineOrderPrice(int $user_id, BfwPoints $points): void
    {
        if (!empty($_POST['bfw_offline_order_price'])) {
            $points->addOfflineOrder(sanitize_text_field($_POST['bfw_offline_order_price']), $user_id);
            wp_safe_redirect('/wp-admin/user-edit.php?user_id=' . $user_id);
            exit;
        }
    }

    /**
     * Обновление даты рождения пользователя
     */
    private static function updateDobMeta(int $user_id): void
    {
        if (isset($_POST['dob'])) {
            update_user_meta($user_id, 'dob', sanitize_text_field($_POST['dob']));
        }
    }

    /**
     * Обновление реферальной ссылки
     */
    private static function updateReferralLink(int $user_id): void
    {
        if (empty($_POST['bfw-referall-link'])) {
            return;
        }

        $new_referral = sanitize_text_field($_POST['bfw-referall-link']);
        $current_referral = get_user_meta($user_id, 'bfw_points_referral', true);

        if ($new_referral === $current_referral) {
            return;
        }

        $args = [
                'meta_query' => [
                        [
                                'key' => 'bfw_points_referral',
                                'value' => trim($new_referral),
                                'compare' => '=='
                        ]
                ]
        ];

        $referees = get_users($args);

        if (empty($referees)) {
            update_user_meta($user_id, 'bfw_points_referral', $new_referral);
        }
    }

    /**
     * Обработка изменения количества баллов
     */
    private static function processPointsChange(
            int $user_id,
            BfwPoints $points,
            BfwHistory $history,
            BfwEmail $email
    ): void {
        // Проверка на наличие цены заказа, если есть - перенаправляем
        if (!empty($_POST['bfw_offline_order_price'])) {
            wp_safe_redirect('/wp-admin/user-edit.php?user_id=' . $user_id);
            exit;
        }

        $new_points = (float)sanitize_text_field($_POST['computy_input_points']);
        $reason = sanitize_text_field($_POST['prichinaizmeneniya'] ?? __('Not specified.', 'bonus-for-woo'));
        $old_points = $points::getPoints($user_id);

        if ($new_points === $old_points) {
            return;
        }

        $diff = abs($new_points - $old_points);
        $action = $new_points > $old_points ? '+' : '-';

        // Добавление истории
        $history::add_history($user_id, $action, $diff, '0', $reason);

        // Отправка email
        self::sendPointsChangeEmail($user_id, $email, $new_points, $old_points, $diff, $reason);

        // Обновление баллов
        $points::updatePoints($user_id, $new_points);
    }

    /**
     * Отправка уведомления об изменении баллов
     */
    private static function sendPointsChangeEmail(
            int $user_id,
            BfwEmail $email,
            float $new_points,
            float $old_points,
            float $diff,
            string $reason
    ): void {
        $user = get_userdata($user_id);
        $referral_link = get_user_meta($user_id, 'bfw_points_referral', true);

        $is_addition = $new_points > $old_points;
        $title_key = $is_addition ? 'email-change-admin-title' : 'email-change-admin-title-spisanie';
        $text_key = $is_addition ? 'email-change-admin-text' : 'email-change-admin-text-spisanie';

        $title_email = BfwSetting::get($title_key, __('Bonus points update', 'bonus-for-woo'));
        $text_email = BfwSetting::get($text_key, '');

        $replace_data = [
                '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $referral_link),
                '[user]' => $user->display_name,
                '[points]' => $diff,
                '[total]' => $new_points,
                '[cause]' => $reason
        ];

        $message_email = $email::template($text_email, $replace_data);

        if (BfwSetting::get('email-change-admin')) {
            $email->getMail($user_id, '', $title_email, $message_email);
        }
    }

}
