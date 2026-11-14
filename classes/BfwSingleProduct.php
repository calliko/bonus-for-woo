<?php

defined('ABSPATH') || exit;

/**
 * Class for displaying bonus points on the product page
 * Класс отображения бонусных баллов на странице товара
 *
 * Class BfwSingleProduct
 *
 * @version 6.4.0
 */
class BfwSingleProduct
{

    /**
     * Common method for standard and shortcode output
     * Общий метод для стандартного вывода и вывода шорткодом
     *
     * @param float $prize Цена товара
     * @param float $percent Процент кешбэка
     * @param int $id Идентификатор продукта
     * @param string $price_width_bonuses Строка для вывода цен с бонусами
     * @param mixed $upto До
     * @param object $product Объект продукта
     *
     * @return string Возвращает строку с информацией о бонусах
     * @version 6.4.0
     */
    public static function bfwPointsInSinglePage(
        float $prize,
        float $percent,
        int $id,
        string $price_width_bonuses,
        $upto,
        object $product
    ): string {
        $userid = get_current_user_id();
        $ball = BfwSingleProduct::cashbackFromOneProduct($id, $userid)['amount'];
        $ball = apply_filters('bfw_cashback_in_product', $ball);
        $ball = BfwPoints::roundPoints($ball); //Округляем, если нужно

        // Если нет бонусов, возвращаем исходную строку
        if (empty($ball)) {
            return $price_width_bonuses;
        }


        if (BfwSetting::get('cashback-on-sale-products')) {
            //Если товар со скидкой, то за него кешбэк не получим
            $productc = wc_get_product($id);
            if ($productc && $productc->is_on_sale()) {
                return $price_width_bonuses;
            }
        }


        $tovars = array();
        // Проверяем, есть ли исключенные товары для кешбэка
        if (BfwRoles::isPro() && BfwSetting::get('exclude-tovar-cashback')) {
            $exclude_tovar = BfwSetting::get('exclude-tovar-cashback', '');
            $tovars = apply_filters('bfw-excluded-products-filter', explode(",", $exclude_tovar), $exclude_tovar);
        }

        $categoriexs = BfwSetting::get('exclude-category-cashback', 'not');

        $hmb_title = BfwSetting::get('how-mach-bonus-title', __('Cashback:', 'bonus-for-woo'));


        // Проверяем, является ли продукт и категория исключенным
        $isExcluded = in_array($id, $tovars) || has_term($categoriexs, 'product_cat', $id);

        if (BfwSetting::get('addkeshback-exclude') || !$isExcluded) {
            /* translators: %1$s: "up to" text, %2$s: points amount, %3$s: points label */
            $price_width_bonuses .= '<div class="how_mach_bonus"><span class="how_mach_bonus_title">' . $hmb_title . '</span> ' . sprintf(__('%1$s %2$s %3$s',
                    'bonus-for-woo'), $upto, BfwPoints::formatNumber($ball), BfwPoints::howLabel($upto, $ball)) . '</div>';
        }


        // Проверяем, нужно ли добавлять реферальные ссылки
        if (BfwRoles::isPro() && BfwSetting::get('ref-links-on-single-page') && BfwRoles::isInvalve($userid) && is_user_logged_in()) {
            if ((int)BfwSetting::get('ref-links-on-single-page') === 1 && is_product()) {
                $get_referral = get_user_meta($userid, 'bfw_points_referral', true);
                $url = esc_url(get_permalink(get_the_ID()) . '?bfwkey=' . $get_referral);
                $description = get_bloginfo('description');
                $title = $product->get_title();
                $refer = BfwReferral::bfwSocialLinks($url, $title, $description);
                $price_width_bonuses .= $refer;
            }
        }

        return $price_width_bonuses;
    }


    /**
     * We get information about the product type
     * Получаем информацию о типе продукта
     *
     * @param $product
     *
     * @return array
     * @version 6.4.0
     */
    public static function typeProduct($product): array
    {
        $upto = '';
       // $type = $product->get_type();
        $price = 0;

        $product_id = (int) $product->get_id();

        /*
         * Кеш в пределах запроса: get_variation_sale_price() для вариативных
         * товаров стоит заметно дорого, а вызывается он несколько раз за
         * одно формирование цены (сначала max, потом min). На странице с
         * десятком карточек это суммируется.
         */
        static $cache = [];
        $cache_key = $product_id . '|' . (int) is_user_logged_in() . '|' . get_current_user_id();
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $constant_cashback = get_post_meta($product_id, '_constant_cashback_percentage', true);

        if ($product->is_type('simple')) {
            $price = $product->get_sale_price() ?: $product->get_price();
        } elseif ($product->is_type('variable')) {
            $maxPrice = $product->get_variation_sale_price('max', true);
            $minPrice = $product->get_variation_sale_price('min', true);

            $constant_cashback = get_post_meta($product_id, '_constant_cashback_percentage_variation', true);

            $price = $maxPrice;

            if ($maxPrice !== $minPrice && !BfwSetting::get('bonus-in-price-upto')) {
                $upto = __('up to', 'bonus-for-woo');
            }
        }

        if (is_user_logged_in()) {
            $userid = get_current_user_id();//  id пользователя
            $getRole = BfwRoles::getRole($userid);
            $percent = $getRole['percent'];


            if ((float)$constant_cashback > 0) {
                $percent = $constant_cashback;
            }

            if ($percent === 0 && BfwRoles::isInvalve($userid)) {
                $percent = BfwRoles::maxPercent();
                $upto = __('up to', 'bonus-for-woo');
            }
        } else {
            //Если не зарегистрирован, то максимальны кешбэк до
            $percent = BfwRoles::maxPercent();
            $upto = empty(BfwSetting::get('bonus-in-price-upto')) ? __('up to', 'bonus-for-woo') : '';
        }

        return $cache[$cache_key] = ['percent' => $percent, 'price' => $price, 'upto' => $upto];
    }


    /**
     * Standard method for displaying the price of a product with bonuses
     * Стандартный метод вывода цены товара с бонусами
     *
     * @param   $price - цена товара
     * @param   $_product - объект продукта
     *
     * @return string - строка с ценой и бонусами
     * @version 6.4.0
     */
    public static function ballsAfterProductPriceAll($price, $_product): string
    {
        global $post;
        global $product;

        $id = (is_object($_product) && method_exists($_product, 'get_id'))
            ? (int) $_product->get_id()
            : ((is_object($post) && isset($post->ID)) ? (int) $post->ID : 0);

        if (empty($id)) {
            return $price; // Если ID товара отсутствует, возвращаем исходную цену
        }

        /*
         * ГЛАВНЫЙ ТОВАР СТРАНИЦЫ или карточка в списке?
         *
         * is_product() возвращает true для ВСЕЙ страницы товара, поэтому
         * внутри Related Products / Upsells он тоже true. Из-за этого
         * тяжёлый расчёт по вариациям выполнялся для каждой карточки:
         * 8 рекомендаций × 30–50 вариаций = сотни вызовов на одно открытие
         * страницы (до 8,5 с).
         *
         * Определяем именно «это товар, для которого открыта страница»:
         * его объект равен глобальному $product (который WooCommerce
         * выставляет в цикле основного запроса).
         */
        $is_main_product = self::isMainProductPage($_product);

        // Облегчённый режим для карточек-рекомендаций: без get_available_variations()
        if ($is_main_product) {
            $productInfo = self::typeProduct($_product);
            $bonuses = self::bfwPointsInSinglePage(
                (float) $productInfo['price'],
                (float) $productInfo['percent'],
                $id,
                '',
                $productInfo['upto'],
                $_product
            );

            if (BfwSetting::get('bonus-in-price')) {
                $price .= $bonuses;

                // Данные для JS — ТОЛЬКО для основного товара
                if ($_product->is_type('variable')) {
                    $price .= self::variationCashbackScript($_product, (float) $productInfo['percent']);
                }
            }

            return $price;
        }

        /*
         * Карточка в цикле (Related, Upsells, архив, шорткоды).
         * Кешбэк считаем по цене товара — это дёшево и не требует
         * перебора вариаций. Для вариативных берётся минимальная цена,
         * как и раньше в typeProduct().
         */
        $productInfo = self::typeProduct($_product);
        $bonuses = self::bfwPointsInSinglePage(
            (float) $productInfo['price'],
            (float) $productInfo['percent'],
            $id,
            '',
            $productInfo['upto'],
            $_product
        );

        /*
         * Когда показывать бонусы у карточек.
         *
         * Раньше на странице товара они выводились по настройке
         * bonus-in-price — той же, что и для основного товара. Именно из-за
         * этого настройка «в списках» не работала, а карточки Related
         * Products получали тяжёлый расчёт.
         *
         * Теперь: на странице товара карточки списков управляются
         * настройкой bonus-in-price-loop, на остальных страницах — ей же.
         */
        if (BfwSetting::get('bonus-in-price-loop')) {
            $price .= $bonuses;
        }

        return $price;
    }

    /**
     * Основной товар ли это для текущей страницы.
     *
     * Отличает товар, ради которого открыта страница, от карточек в
     * списках (Related Products, Upsells, архив). Тяжёлые расчёты
     * (get_available_variations по всем вариациям) нужны только первому.
     *
     * @param mixed $_product
     *
     * @return bool
     * @since 8.5.5
     */
    public static function isMainProductPage($_product): bool
    {
        if (!is_product()) {
            return false;
        }

        if (!is_object($_product) || !method_exists($_product, 'get_id')) {
            return false;
        }

        $queried_id = get_queried_object_id();
        if ($queried_id && (int) $_product->get_id() === (int) $queried_id) {
            return true;
        }

        // Запасные варианты: глобальный $product или get_the_ID()
        global $product;

        if (is_object($product) && method_exists($product, 'get_id')
            && (int) $product->get_id() === (int) $_product->get_id()) {
            return true;
        }

        $current = get_the_ID();

        return $current && (int) $current === (int) $_product->get_id();
    }

    /**
     * Формирует JS-данные о кешбэке вариаций основного товара.
     *
     * Вызывается один раз за страницу — только для товара, для которого
     * она открыта. Раньше вызывался для каждой карточки в Related Products,
     * и записи перезаписывали друг друга в window.bfwVariationCashback.
     *
     * @param WC_Product $product
     * @param float $cashback_percent
     *
     * @return string
     * @since 8.5.5
     */
    public static function variationCashbackScript($product, float $cashback_percent): string
    {
        // Не дублируем блок, если хук сработал повторно на том же товаре
        static $done = [];

        $product_id = (int) $product->get_id();
        if (isset($done[$product_id])) {
            return '';
        }
        $done[$product_id] = true;

        $user_id = get_current_user_id();
        if (!$user_id) {
            $user_id = -1;
        }

        $variations_data = [];

        foreach ($product->get_available_variations() as $variation_data) {
            $variation = wc_get_product($variation_data['variation_id']);
            if (!$variation) {
                continue;
            }

            $variation_cashback = self::cashbackFromOneProduct($product_id, $user_id, $variation->get_id());

            $variations_data[$variation_data['variation_id']] = [
                'price'    => (float) $variation->get_price(),
                'cashback' => BfwPoints::roundPoints($variation_cashback['amount']),
                'percent'  => (float) $variation_cashback['percent'],
            ];
        }

        return '<script type="text/javascript">
                    window.bfwVariationCashback = ' . wp_json_encode([
                'productId'  => $product_id,
                'percent'    => $cashback_percent,
                'variations' => $variations_data,
                'label'      => BfwSetting::get('how-mach-bonus-title', __('Cashback:', 'bonus-for-woo')),
                'pointsLabel' => BfwPoints::pointsLabel(1, false),
            ]) . ';
                </script>';
    }


    /**
     * Method: output by shortcode
     * Метод: вывод шорткодом
     *
     * @param $product
     *
     * @return string|void
     * @version 6.4.0
     */
    public static function ballsAfterProductPriceShortcode($product)
    {
        // Проверяем, что мы не находимся в админке и находимся на странице продукта
        if (!current_user_can('manage_options') && is_product()) {
            global $post;
            global $product;

            $price_width_bonuses = ''; // Инициализируем переменную для бонусов
            $id = $product->get_id(); // Получаем ID товара

            // Получаем информацию о товаре
            $product_info = self::typeProduct($product);
            $price = $product_info['price']; // Цена товара
            $upto = $product_info['upto'];
            $percent = $product_info['percent']; // Процент

            // Возвращаем результат функции с расчетом бонусов
            return self::bfwPointsInSinglePage($price, $percent, $id, $price_width_bonuses, $upto, $product);
        }

        // Если находимся в админке или не на странице продукта, возвращаем сообщение об ошибке
        if (current_user_can('manage_options') || !is_product()) {
            return __('Use shortcode [bfw_cashback_in_product] only on product page!', 'bonus-for-woo');
        }
    }

    

    /**
     * Какой кешбэк получит клиент с данного товара.
     *
     * @param int $product_id
     * @param int $user_id
     * @param int | null $variation_id
     * @param null $oldPrice
     * @return array
     * @version 7.6.0
     */
    public static function cashbackFromOneProduct(
        int $product_id,
        int $user_id = -1,
        ?int $variation_id = null,
        $oldPrice = null
    ): array {
        $Role = new BfwRoles();
        $constant_cashback = false;
//Цена продукта
        $product = wc_get_product($product_id);
        if (!$product) {
            return [
                'amount' => 0.0,
                'percent' => 0 // Добавьте это
            ];
        }
        // Если это вариация - просто возвращаем ее цену
        if ($product->is_type('variation')) {
            $price = (float)$product->get_price();
            $constant_cashback = get_post_meta($product->get_id(), '_constant_cashback_percentage_variation', true) ?? false;
        } elseif ($variation_id && $variation = wc_get_product($variation_id)) {
            // Если передан ID вариации и она существует
            if ($variation->is_type('variation') && $variation->get_parent_id() == $product_id) {
                $price = (float)$variation->get_price();
                $constant_cashback = get_post_meta($variation->get_id(), '_constant_cashback_percentage_variation',
                    true) ?? false;
            } else {
                // Если вариация не соответствует товару, используем базовую цену
                $price = (float)$product->get_price();
                $constant_cashback = get_post_meta($product_id, '_constant_cashback_percentage', true) ?? false;
            }
        } elseif ($product->is_type('variable')) {
            $constant_cashback = get_post_meta($product->get_id(), '_constant_cashback_percentage_variation',
                true) ?? false;
            // Если это вариативный товар - возвращаем минимальную цену
            $price = (float)$product->get_variation_price('min', true);
        } else {
            $constant_cashback = get_post_meta($product_id, '_constant_cashback_percentage', true) ?? false;
            // Для всех остальных типов товаров
            $price = (float)$product->get_price();
        }
        if (isset($oldPrice)) {
            //Если указана старая цена
            $price = $oldPrice;
        }
        if (empty($price)) {
            return [
                'amount' => 0.0,
                'percent' => 0
            ];
        }

//есть ли исключения у продукта
        $tovars = array();
        // Проверяем, есть ли исключенные товары для кешбэка
        if (BfwRoles::isPro() && BfwSetting::get('exclude-tovar-cashback')) {
            $exclude_tovar = BfwSetting::get('exclude-tovar-cashback', '');
            $tovars = apply_filters('bfw-excluded-products-filter', explode(",", $exclude_tovar), $exclude_tovar);
        }
        $categoriexs = BfwSetting::get('exclude-category-cashback', 'not');

        // Проверяем, является ли продукт и категория исключенным
        $isExcluded = in_array($product_id, $tovars) || has_term($categoriexs, 'product_cat', $product_id);

        if (!BfwSetting::get('addkeshback-exclude') && $isExcluded) {
            return [
                'amount' => 0.0,
                'percent' => 0
            ];
        }


        if (BfwSetting::get('cashback-on-sale-products')) {
            //Если товар со скидкой, то за него кешбэк не получим
            $productc = wc_get_product($product_id);
            if ($productc && $productc->is_on_sale()) {
                return [
                    'amount' => 0.0,
                    'percent' => 0
                ];
            }
        }


//процент кешбэка пользователя
        if ($user_id === -1) {
            $user_id = get_current_user_id();
            if (empty($user_id)) {

                return [
                    'amount' => 0.0,
                    'percent' => 0
                ];
            }
        }

        //Если пользователь не залогинен, то максимальный кешбэк
        if (!is_user_logged_in() && $user_id < 1) {
            global $wpdb;

            $percent = (float)$wpdb->get_var(
                "SELECT MAX(percent) FROM {$wpdb->prefix}bfw_computy"
            );
            $percent = empty($percent) ? 0 : $percent;
        } else {
            $percent = $Role->getRole($user_id)['percent'];
        }


        if ($constant_cashback) {
            $percent = (float)$constant_cashback;
        }

        //Если товар со 100% кешбэком
        if (BfwSetting::get('buy_balls-cashback')) {
            if ((int)BfwSetting::get('buy_balls-cashback') === $product_id) {
                $percent = 100;
            }
        }


        if (BfwSetting::get('cashback_for_first_order') && (new BfwFunctions())->get_customer_order_count($user_id) === 0) {
            update_user_meta($user_id, 'first_order', 'yes');
            $percent = BfwSetting::get('cashback_for_first_order');
        }else{
          $first =  get_user_meta($user_id, 'first_order', true);
            if(!empty($first) && $first =='yes'){
                $percent = BfwSetting::get('cashback_for_first_order');
            }
        }


        if (!$Role->isInvalve($user_id) && is_user_logged_in()) {
            $cashback_amount = 0.0;//есть ли исключения у пользователя
            return [
                'amount' => $cashback_amount,
                'percent' => $percent
            ];
        }

//процент кешбэка продукта

        $cashback_amount = $price * ($percent / 100);

        return [
            'amount' => $cashback_amount,
            'percent' => $percent // Добавьте это
        ];
    }


}
