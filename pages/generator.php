<?php
defined( 'ABSPATH' ) || exit;
/**
 * Модернизированный генератор правил и условий
 *
 * @version 8.0.0
 */
?>

<style>
    :root {
        --bfw-primary: #2271b1;
        --bfw-bg: #f0f2f5;
        --bfw-paper: #ffffff;
        --bfw-text: #1d2327;
        --bfw-text-muted: #646970;
        --bfw-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }

    .bfw-generator-dashboard {
        margin: 20px 20px 20px 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    /* Navigation */
    .bfw-back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: var(--bfw-text-muted);
        font-weight: 600;
        margin-bottom: 20px;
        transition: color 0.2s;
    }
    .bfw-back-link:hover { color: var(--bfw-primary); }

    /* Header */
    .bfw-gen-header {
        background: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        border: 1px solid #dcdcde;
        box-shadow: var(--bfw-shadow);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .bfw-gen-title h1 { margin: 0 0 5px 0; font-size: 24px; color: var(--bfw-text); }
    .bfw-gen-title p { margin: 0; color: var(--bfw-text-muted); font-size: 14px; }

    /* Paper Document Mockup */
    .bfw-document-viewport {
        background: #e2e8f0;
        padding: 50px 20px;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-height: 600px;
    }

    .bfw-paper-sheet {
        background: var(--bfw-paper);
        width: 100%;
        max-width: 800px;
        padding: 60px 80px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        border-radius: 2px;
        position: relative;
        color: #334155;
        line-height: 1.6;
    }

    .bfw-paper-sheet h1 { text-align: center; color: #1e293b; margin-bottom: 40px; font-size: 28px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; }
    .bfw-paper-sheet h2 { color: #334155; margin-top: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; font-size: 20px; }
    .bfw-paper-sheet ul { padding-left: 20px; list-style-type: square; }
    .bfw-paper-sheet p { margin-bottom: 15px; }

    /* Action Bar */
    .bfw-action-bar {
        position: sticky;
        top: 50px;
        z-index: 100;
        margin-bottom: 20px;
    }

    .bfw-btn-copy {
        background: var(--bfw-primary);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 30px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(34, 113, 177, 0.3);
        transition: all 0.2s;
    }
    .bfw-btn-copy:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(34, 113, 177, 0.4); }
    .bfw-btn-copy:active { transform: translateY(0); }

    .copy-success-tip {
        position: absolute;
        top: -40px;
        background: #2ecc71;
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 12px;
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none;
    }
</style>

<div class="bfw-generator-dashboard">
    <a class="bfw-back-link" href="?page=bonus-for-woo/pages/tools.php">
        <span class="dashicons dashicons-arrow-left-alt"></span> <?php _e('Back to Tools', 'bonus-for-woo'); ?>
    </a>

    <div class="bfw-gen-header">
        <div class="bfw-gen-title">
            <h1><?php _e('Rules and Conditions Generator', 'bonus-for-woo'); ?></h1>
            <p><?php _e('Automatically generated text based on your current plugin settings.', 'bonus-for-woo'); ?></p>
        </div>
        <div class="bfw-action-bar">
            <button class="bfw-btn-copy" id="bfw-copy-doc">
                <span class="dashicons dashicons-admin-page"></span> <?php _e('Copy Text to Clipboard', 'bonus-for-woo'); ?>
                <div class="copy-success-tip" id="copy-tip"><?php _e('Copied!', 'bonus-for-woo'); ?></div>
            </button>
        </div>
    </div>

    <div class="bfw-document-viewport">
        <div class="bfw-paper-sheet" id="bfw-document-content">
            <?php
            $val = BfwSetting::get_all();
            $label_points = !empty($val['label_points']) ? $val['label_points'] : 'баллов';
            $label_point = !empty($val['label_point']) ? $val['label_point'] : 'балл';
            $currency = get_woocommerce_currency_symbol();
            ?>

            <h1>Программа лояльности "<?php echo !empty($val['bonus-points-on-cart']) ? $val['bonus-points-on-cart'] : 'бонусной системы'; ?>"</h1>
            
            <p>Наша система бонусов создана с целью предоставить покупателям выгодные условия, которые помогут им экономить свои деньги.</p>

            <p>Программа лояльности включает в себя возврат части стоимости покупок в виде бонусных <?php echo $label_points; ?>, где каждый <?php echo $label_point; ?> эквивалентен одному рублю. Это означает, что за каждую покупку покупатели получают бонусы, которые могут использовать для будущих расходов, тем самым уменьшая общую стоимость их следующих покупок.</p>
            
            <p>Размер возврата определяется суммой заказа и статусом клиента. Статус клиента зависит от общей суммы его заказов. В зависимости от общей суммы заказов клиенту присваивается соответствующий статус:</p>
            
            <ul>
                <?php
                $table_bfw = BfwRoles::getRoles();
                if ($table_bfw) {
                    foreach ($table_bfw as $bfw) {
                        echo '<li><strong>' . esc_html($bfw->name) . '</strong>: при общей сумме заказов от ' . esc_html($bfw->summa_start) . $currency . '. Начисляется ' . esc_html($bfw->percent) . '% кешбэка.</li>';
                    }
                }
                ?>
            </ul>

            <h2>Отображение <?php echo $label_points; ?> и кешбэка</h2>
            <p>Узнать сколько <?php echo $label_points; ?> на счету можно в личном кабинете во вкладке "<?php echo !empty($val['title-on-account']) ? $val['title-on-account'] : 'Страница бонусов'; ?>" </p>
            
            <?php if (empty($val['hystory-hide'])) : ?>
                <p>Также в этой вкладке можно посмотреть историю списаний и начислений <?php echo $label_points; ?>.</p>
            <?php endif; ?>

            <?php if (!empty($val['bonus-in-price'])) : ?>
                <p>На странице товара указано, сколько вам вернется <?php echo $label_points; ?> за покупку данного товара.</p>
            <?php endif; ?>

            <?php if (!empty($val['cashback-in-cart'])) : ?>
                <p>При оформлении заказа и в корзине будет показано, сколько бонусных <?php echo $label_points; ?> вы получите за ваш заказ.</p>
            <?php endif; ?>


            <h2>Начисление <?php echo $label_points; ?></h2>
            <p>Кроме начисления <?php echo $label_points; ?> за покупки товаров, предусмотрены другие вознаграждения:</p>
            <ul>
                <?php if (!empty($val['bonus-for-otziv'])) : ?>
                    <li><?php echo $val['bonus-for-otziv'] . ' ' . BfwPoints::pointsLabel((int)$val['bonus-for-otziv']); ?> за отзыв о купленном товаре.</li>
                <?php endif; ?>

                <?php if (BfwRoles::isPro()) : ?>
                    <?php if (!empty($val['points-for-registration'])) : ?>
                        <?php $reg_suffix = !empty($val['register-points-only-referal']) ? ' (только при регистрации по реферальной ссылке)' : ''; ?>
                        <li><?php echo $val['points-for-registration'] . ' ' . BfwPoints::pointsLabel((int)$val['points-for-registration']); ?> начислят за регистрацию в нашем магазине<?php echo $reg_suffix; ?>.</li>
                    <?php endif; ?>

                    <?php if (!empty($val['birthday'])) : ?>
                        <?php
                        $bday_days = !empty($val['how-many-birthday']) ? (int)$val['how-many-birthday'] : 0;
                        if ($bday_days > 0) {
                            $bday_when = 'за ' . $bday_days . ' ' . ($bday_days === 1 ? 'день' : ($bday_days < 5 ? 'дня' : 'дней')) . ' до дня рождения';
                        } else {
                            $bday_when = 'в день вашего рождения';
                        }
                        ?>
                        <li><?php echo $val['birthday'] . ' ' . BfwPoints::pointsLabel((int)$val['birthday']); ?> начислят <?php echo $bday_when; ?> (его можно указать в настройках профиля).</li>
                    <?php endif; ?>

                    <?php if (!empty($val['every_days'])) : ?>
                        <li><?php echo $val['every_days'] . ' ' . BfwPoints::pointsLabel((int)$val['every_days']); ?> начислят за ежедневный вход в личный кабинет.</li>
                    <?php endif; ?>

                    <?php if (!empty($val['cashback_for_first_order'])) : ?>
                        <li>Дополнительный кешбэк <?php echo (int)$val['cashback_for_first_order']; ?>% будет начислен за первый заказ в нашем магазине.</li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <h2>Статус заказа для начисления <?php echo $label_points; ?></h2>
            <?php
            $order_statuses = wc_get_order_statuses();
            $accrual_status_key = 'wc-' . ($val['add_points_order_status'] ?? 'completed');
            $accrual_status_label = $order_statuses[$accrual_status_key] ?? ($val['add_points_order_status'] ?? 'выполнен');
            echo '<p>' . $label_points . ' начисляются на счёт после перевода заказа в статус «' . esc_html($accrual_status_label) . '».</p>';
            ?>

            <h2>Ограничения начисления <?php echo $label_points; ?></h2>
            <?php
                if (!empty($val['cashback-for-shipping'])) echo '<p>Кешбэк за доставку не будет начислен.</p>';
                if (!empty($val['cashback-on-sale-products'])) echo '<p>За товары со скидкой кешбэк не будет начислен.</p>';
                if (!empty($val['yous_coupon_no_cashback'])) echo '<p>Если применен купон, кешбэк не будет начислен.</p>';

                $excluded_payments = $val['exclude-payment-method'] ?? [];
                if (!empty($excluded_payments)) {
                    $all_gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : [];
                    $excluded_labels = [];
                    foreach ($excluded_payments as $gw_id) {
                        $excluded_labels[] = esc_html(isset($all_gateways[$gw_id]) ? $all_gateways[$gw_id]->get_title() : $gw_id);
                    }
                    if (!empty($excluded_labels)) {
                        echo '<p>Кешбэк не будет начислен при оплате следующими способами: ' . implode(', ', $excluded_labels) . '.</p>';
                    }
                }

                if (BfwRoles::isPro()) {
                    $excl_accrual_cats = $val['addkeshback-exclude'] ?? [];
                    if (!empty($excl_accrual_cats)) {
                        $excl_cat_names = [];
                        foreach ($excl_accrual_cats as $cat_id) {
                            $term = get_term_by('id', $cat_id, 'product_cat');
                            if ($term) $excl_cat_names[] = esc_html($term->name);
                        }
                        if (!empty($excl_cat_names)) {
                            echo '<p>За товары из следующих категорий кешбэк не будет начислен: ' . implode(', ', $excl_cat_names) . '.</p>';
                        }
                    }
                }
            ?>

            <h2>Использование баллов</h2>
            <p>В корзине<?php echo !empty($val['spisanie-in-checkout']) ? ' и при оформлении заказа ' : ' '; ?>вы можете использовать баллы для оплаты товаров.</p>

            <h2>Ограничения использования <?php echo $label_points; ?></h2>
            <?php 
                if (!empty($val['spisanie-onsale'])) echo '<p>Вы не можете использовать баллы на покупку товаров со скидкой.</p>';
                if (!empty($val['balls-and-coupon'])) echo '<p>Вы не можете использовать баллы, если применен скидочный купон.</p>';
                
                if (BfwRoles::isPro()) {
                    $max_percent = $val['max-percent-bonuses'] ?? 100;
                    if ($max_percent < 100) {
                        echo '<p>Вы не можете потратить более ' . $max_percent . '% ' . $label_points . ' от суммы заказа.</p>';
                    }

                    $categories = $val['exclude-category-cashback'] ?? '';
                    if (!empty($categories)) {
                        $cat_names = [];
                        foreach ($categories as $cat) {
                            $term = get_term_by('id', $cat, 'product_cat', 'ARRAY_A');
                            if($term) $cat_names[] = $term['name'];
                        }
                        echo '<p>Товары из следующих категорий нельзя оплатить баллами: ' . implode(', ', $cat_names) . '.</p>';
                    }

                    if (!empty($val['yous_balls_no_cashback'])) echo '<p>Если вы используете баллы, то в данном заказе кешбэк начислен не будет.</p>';
                    
                    if (!empty($val['minimal-amount'])) {
                        $extra = !empty($val['minimal-amount-cashback']) ? ' и получения кешбэка' : '';
                        echo '<p>Для списания ' . $label_points . $extra . ' сумма в заказе должна быть не менее ' . $val['minimal-amount'] . $currency . '.</p>';
                    }

                    if (!empty($val['day-inactive'])) {
                        echo '<h2>Сгорание ' . $label_points . '</h2>';
                        $notice_days = !empty($val['day-inactive-notice']) ? (int)$val['day-inactive-notice'] : 0;
                        if ($notice_days > 0) {
                            $days_word = $notice_days === 1 ? 'день' : ($notice_days < 5 ? 'дня' : 'дней');
                            echo '<p>За ' . $notice_days . ' ' . $days_word . ' до сгорания мы пришлём вам уведомление на email.</p>';
                        }
                        echo '<p>При отсутствии покупок более ' . (int)$val['day-inactive'] . ' дней баллы с вашего счёта сгорят.</p>';
                    }

                    if (!empty($val['referal-system'])) {
                        $bonus_type = !empty($val['level-two-referral']) ? 'двухуровневая' : 'стандартная';
                        echo '<h2>Реферальная система</h2>';
                        echo '<p>В нашей программе действует ' . $bonus_type . ' реферальная система. В личном кабинете доступна ссылка, которую вы можете отправить друзьям. Число приглашенных не ограничено.</p>';

                        $min_sum_ref = !empty($val['sum-orders-for-referral']) ? (float)$val['sum-orders-for-referral'] : 0;
                        if ($min_sum_ref > 0) {
                            echo '<p>Кешбэк за приглашённого друга начисляется только при сумме его заказа от ' . $min_sum_ref . $currency . '.</p>';
                        }

                        echo '<p>За покупки приглашённых друзей вы получите ' . ($val['referal-cashback'] ?? 0) . '% кешбэка';
                        echo !empty($val['first-order-referal']) ? ', но только за первую покупку.</p>' : '.</p>';

                        if (!empty($val['level-two-referral']) && !empty($val['level-two-referral-percent'])) {
                            echo '<p>За друзей второго уровня вы получите дополнительные ' . (int)$val['level-two-referral-percent'] . '% кешбэка.</p>';
                        }
                    }
                }

                $rules_url = $val['rulles_url'] ?? '';
                $rules_text = !empty($val['rulles_value']) ? $val['rulles_value'] : 'полными правилами программы лояльности';
                if (!empty($rules_url)) {
                    echo '<h2>Полные правила</h2>';
                    echo '<p>Ознакомиться с <a href="' . esc_url($rules_url) . '">' . esc_html($rules_text) . '</a> можно на отдельной странице.</p>';
                }
            ?>
        </div>
    </div>
</div>

<script>
    document.getElementById('bfw-copy-doc').addEventListener('click', function() {
        const content = document.getElementById('bfw-document-content').innerText;
        const btn = this;
        const tip = document.getElementById('copy-tip');

        navigator.clipboard.writeText(content).then(() => {
            tip.style.opacity = '1';
            btn.style.background = '#2ecc71';
            setTimeout(() => {
                tip.style.opacity = '0';
                btn.style.background = '';
            }, 2000);
        });
    });
</script>