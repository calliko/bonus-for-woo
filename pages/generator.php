<?php
defined( 'ABSPATH' ) || exit;
/**
 * Страница генератора условий для страницы условий бонусных баллов
 *
 * @since  5.0.0
 * @version 5.0.0
 */

?>
<div class="wrap bonus-for-woo-admin">
    <a class="title" href="?page=bonus-for-woo/pages/tools.php"><h2>← <?php echo __('Tools', 'bonus-for-woo'); ?></h2>
    </a>
    <?php echo '<h1>Генератор условий бонусной системы лояльности</h1>'; ?>
    <p>Данный генератор создает текст правил условий вашей лояльной системы на основе настроек плагина Bonus for Woo.
        Все, что вам надо - скопировать текст, отредактировать по своему усмотрению и вставить на страницу например,
        "Правила бонусной программы".</p>
    <hr>
    <div class="bfw-generator-wrap">
        <?php
        $val = BfwSetting::get_all();
        ?>
        <div style="background: #fff;padding: 10px 20px;">
            <h1>Программа лояльности "<?php if (!empty($val['bonus-points-on-cart'])) {
                    echo $val['bonus-points-on-cart'];
                } else {
                    echo 'бонусной системы';
                } ?>"</h1>
            <p>Наша система бонусов создана с целью предоставить покупателям выгодные условия, которые помогут им
                экономить свои деньги.</p>

            <p>Программа лояльности включает в себя возврат части стоимости покупок в виде бонусных
                <?php if (!empty($val['label_points'])) {
                    echo $val['label_points'];
                } else {
                    echo 'баллов';
                } ?>, где каждый
                <?php if (!empty($val['label_point'])) {
                    echo $val['label_point'];
                } else {
                    echo 'балл';
                } ?> эквивалентен одному рублю.
                Это означает, что за каждую покупку покупатели получают бонусы, которые могут использовать для будущих
                расходов, тем самым уменьшая общую стоимость их следующих покупок..</p>
            <p>Размер возврата <?php if (!empty($val['label_points'])) {
                    echo $val['label_points'];
                } else {
                    echo 'баллов';
                } ?> определяется суммой заказа и статусом клиента. Статус клиента зависит от общей суммы его заказов.
                В зависимости от общей суммы заказов
                клиенту присваивается соответствующий статус:</p>
            <ul>
                <?php

                $table_bfw = BfwRoles::getRoles();
                if ($table_bfw) {
                    foreach ($table_bfw as $bfw) {
                        echo '<li>- ' . $bfw->name . ': при общей сумме заказов ' . $bfw->summa_start . get_woocommerce_currency_symbol() . '. Начисляется ' . $bfw->percent . '% кешбэка.</li>';
                    }
                }
                ?>
            </ul>

            <h2>Отображение <?php if (!empty($val['label_points'])) {
                    echo $val['label_points'];
                } else {
                    echo 'баллов';
                } ?> и кешбэка</h2>
            <p>Узнать сколько <?php if (!empty($val['label_points'])) {
                    echo $val['label_points'];
                } else {
                    echo 'баллов';
                } ?> на счету можно в личном
                кабинете во вкладке "<?php if (!empty($val['title-on-account'])) {
                    echo $val['title-on-account'];
                } else {
                    echo 'Страница бонусов';
                } ?> " </p>
            <?php if (empty($val['hystory-hide'])) { ?>
                <p>Так же в этой вкладке можно посмотреть историю списаний и
                    начислений <?php if (!empty($val['label_points'])) {
                        echo $val['label_points'];
                    } else {
                        echo 'баллов';
                    } ?>.</p>
            <?php } ?>
            <?php if (!empty($val['bonus-in-price'])) { ?>
                <p>На странице товара указано сколько вам вернется <?php if (!empty($val['label_points'])) {
                        echo $val['label_points'];
                    } else {
                        echo 'баллов';
                    } ?> за покупку данного товара.</p>
            <?php } ?>
            <?php if (!empty($val['cashback-in-cart'])) { ?>
                <p>При оформлении заказа и в корзине будет показано, сколько
                    бонусных <?php if (!empty($val['label_points'])) {
                        echo $val['label_points'];
                    } else {
                        echo 'баллов';
                    } ?> вы получите за ваш заказ.</p>
            <?php } ?>


            <h2>Начисление <?php if (!empty($val['label_points'])) {
                    echo $val['label_points'];
                } else {
                    echo 'баллов';
                } ?></h2>
            <p>Кроме начисления <?php if (!empty($val['label_points'])) {
                    echo $val['label_points'];
                } else {
                    echo 'баллов';
                } ?> за покупки товаров,
                предусмотрены другие вознаграждения:<br>
                <?php
                if (!empty($val['bonus-for-otziv'])) { ?>
                    <?php echo $val['bonus-for-otziv'] . ' ' . BfwPoints::pointsLabel($val['bonus-for-otziv']); ?>
                    за отзыв о купленном товаре.<br>
                <?php }

                if (BfwRoles::isPro()) {

                    if (!empty($val['points-for-registration'])) { ?>
                        <?php echo $val['points-for-registration'] . ' ' . BfwPoints::pointsLabel($val['points-for-registration']); ?>
                        начислят за регистрацию в нашем магазине.<br>
                    <?php }

                    if (!empty($val['birthday'])) { ?>
                        <?php echo $val['birthday'] . ' ' . BfwPoints::pointsLabel($val['birthday']); ?>
                        начислят в день вашего рождения, который
                        вы укажите в настройках профиля.<br>
                    <?php }
                    if (!empty($val['every_days'])) { ?>
                        <?php echo $val['every_days'] . ' ' . BfwPoints::pointsLabel($val['birthday']); ?>
                        начислят за ежедневный вход в личный кабинет.<br>
                    <?php }


                }
                ?>
            </p>
            <h2>Ограничения начисления <?php if (!empty($val['label_points'])) {
                    echo $val['label_points'];
                } else {
                    echo 'баллов';
                } ?></h2>
            <?php if (!empty($val['cashback-for-shipping'])) {
                echo '<p>Кешбэк за доставку не будет начислен.</p>';
            }
            if (!empty($val['cashback-on-sale-products'])) {
                echo '<p>За товары со скидкой кешбэк не будет начислен.</p>';
            }
            if (!empty($val['yous_coupon_no_cashback'])) {
                echo '<p>Если применен купон кешбэк не будет начислен.</p>';
            }


            ?>
            <h2>Использование баллов</h2>
            <p>В корзине
                <?php if (!empty($val['spisanie-in-checkout'])) {
                    echo ' и в оформлении заказа ';
                } ?>
                вы можете использовать баллы для покупки товаров.</p>

            <h2>Ограничения использования <?php if (!empty($val['label_points'])) {
                    echo $val['label_points'];
                } else {
                    echo 'баллов';
                } ?></h2>
            <?php if (!empty($val['spisanie-onsale'])) {
                echo '<p>Вы не можете использовать баллы на покупку товаров со скидкой.</p>';
            } ?>
            <?php if (!empty($val['balls-and-coupon'])) {
                echo '<p>Вы не можете использовать баллы если применен скидочный купон.</p>';
            }
            if (BfwRoles::isPro()) {
                $max_percenet_bonuses = $val['max-percent-bonuses'] ?? 100;
                if ($max_percenet_bonuses < 100) {
                    echo '<p> Вы не можете потратить более ' . $val['max-percent-bonuses'] . '% ' . $val['label_points'] . ' от суммы заказа.</p>';
                }


                $categoriexs = $val['exclude-category-cashback'] ?? '';
                if (!empty($categoriexs)) {
                    echo '<p>Товары из категорий: ';
                    foreach ($categoriexs as $cat) {
                        $term = get_term_by('id', $cat, 'product_cat', 'ARRAY_A');
                        echo $term['name'] . ',';
                    }
                    echo ' за кешбэк не приобрести.</p>';
                }
                if (!empty($val['yous_balls_no_cashback'])) {
                    echo '<p>Если вы используете баллы, то в данном заказе кешбэка не будет.</p>';
                }
                if (!empty($val['minimal-amount'])) {
                    $a = '';
                    if (!empty($val['minimal-amount-cashback'])) {
                        $a = 'и получения кешбэка';
                    }


                    echo '<p>Для траты ' . $val['label_points'] . ' ' . $a . ' сумма в заказе должна быть не менее ' . $val['minimal-amount'] . get_woocommerce_currency_symbol() . '.</p>';

                }
                if (!empty($val['day-inactive'])) {
                    echo '<h2>Сгорание ' . $val['label_points'] . '</h2>
                <p>При отсутствии покупок более ' . $val['day-inactive'] . ' дней, баллы с вашего счета сгорят.</p>';
                }

                if (!empty($val['referal-system'])) {
                    $referal_cashback = $val['referal-cashback'] ?? 0;
                    $d = '';
                    if (!empty($val['level-two-referral'])) {
                        $d = 'двухуровневая';
                    }

                    echo '<h2>Реферальная система</h2>
                <p>Так же в нашей бонусной программе есть ' . $d . ' реферальная система. В личном кабинете будет реферальная ссылка,
                которую вы можете отправить вашим друзьям личными сообщениями в мессенджерах и СМС, а также выкладывая в социальных сетях. Число приглашенных не ограничено.</p>';
                    echo '<p>За покупки ваших приглашенных друзей, вы будете получать ' . $referal_cashback . '% кешбэка в виде ' . $val['label_points'];

                    if (!empty($val['first-order-referal'])) {
                        echo ', но только за первую покупку.';
                    } else {
                        echo '.</p>';
                    }
                    if (!empty($val['level-two-referral'])) {
                        if (!empty($val['referal-cashback-two-level'])) {
                            echo '<p>За друзей второго уровня вы получите ' . $val['referal-cashback-two-level'] . '% кешбэка.</p>';
                        }
                    }
                }
            }
            ?>
        </div>
    </div>
</div>