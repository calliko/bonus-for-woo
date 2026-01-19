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
            $price_width_bonuses .= '<div class="how_mach_bonus"><span class="how_mach_bonus_title">' . $hmb_title . '</span> ' . sprintf(__('%1$s %2$s %3$s',
                    'bonus-for-woo'), $upto, $ball, BfwPoints::howLabel($upto, $ball)) . '</div>';
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
        $type = $product->get_type();
        $price = 0;

        $constant_cashback = get_post_meta($product->get_id(), '_constant_cashback_percentage', true);

        if ($type === 'simple') {
            $price = $product->get_sale_price() ?: $product->get_price();
        } elseif ($type === 'variable') {
            $maxPrice = $product->get_variation_sale_price('max', true);
            $minPrice = $product->get_variation_sale_price('min', true);

            $constant_cashback = get_post_meta($product->get_id(), '_constant_cashback_percentage_variation', true);

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

        return ['percent' => $percent, 'price' => $price, 'upto' => $upto];
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

        // Получаем настройки бонусов
        $price_width_bonuses = ''; // Инициализация переменной для бонусов

        // Получаем ID товара
        if (is_object($post) && isset($post->ID)) {
            $id = $post->ID;
        } else {
            // Обработка случая, когда $post не является объектом
            $id = 0; // или другое значение по умолчанию
        }

        if (empty($id)) {
            return $price; // Если ID товара отсутствует, возвращаем исходную цену
        }
        // Получаем информацию о типе продукта
        $productInfo = self::typeProduct($_product);
        $prize = (float)$productInfo['price'];
        $upto = $productInfo['upto'];
        $percent = (float)$productInfo['percent'];

        // Возвращаем результат функции с расчетом бонусов
        $price_width_bonuses = self::bfwPointsInSinglePage($prize, $percent, $id, $price_width_bonuses, $upto,
            $_product);

        if (BfwSetting::get('bonus-in-price-loop') && !is_product()) {
            // Отображать на других страницах, всех кроме страницы товара
            $price .= $price_width_bonuses;
        }

        if (BfwSetting::get('bonus-in-price') && is_product()) {
            $price .= $price_width_bonuses;
        }

        return $price;
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
        int $variation_id = null,
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
        } elseif ($variation_id && $variation = wc_get_product($variation_id)) {
            // Если передан ID вариации и она существует
            if ($variation->is_type('variation') && $variation->get_parent_id() == $product_id) {
                $price = (float)$variation->get_price();
                $constant_cashback = get_post_meta($variation->get_id(), '_constant_cashback_percentage_variation',
                    true) ?? false;
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
            if ($productc->is_on_sale()) {
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
                "SELECT MAX(CAST(percent AS SIGNED)) FROM {$wpdb->prefix}bfw_computy"
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
            $percent = BfwSetting::get('cashback_for_first_order');
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