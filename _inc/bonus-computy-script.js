/*
 * Nonce больше не печатается в HTML страницы: под полностраничным кешем он
 * отдавался протухшим, и запросы падали с 403. Получаем его при загрузке.
 *
 * ВАЖНО: nonce приходит асинхронно. Если запрос уходит раньше, чем он
 * получен, заголовок X-WP-Nonce уходит пустым, и WordPress отвечает
 * «Проверка куки не удалась» (rest_cookie_invalid_nonce).
 * Поэтому все запросы ждут один общий промис bfwNoncePromise.
 */
window.bfwNoncePromise = null;

window.bfwGetNonce = function () {
    // Уже есть — отдаём сразу
    if (window.bfwRestNonce) {
        return Promise.resolve(window.bfwRestNonce);
    }

    /*
     * Приоритет — nonce из HTML страницы. Он напечатан под текущего
     * пользователя и не может прийти от гостя. Запрос /nonce нужен только
     * если в HTML ничего нет (например, страница отдалась из кеша раньше).
     */
    if (window.wpApiSettings && window.wpApiSettings.nonce) {
        window.bfwRestNonce = window.wpApiSettings.nonce;
        return Promise.resolve(window.bfwRestNonce);
    }

    // Запрос уже идёт — не дублируем
    if (window.bfwNoncePromise) {
        return window.bfwNoncePromise;
    }

    if (!window.wpApiSettings || !window.wpApiSettings.nonceAjaxUrl) {
        return Promise.resolve('');
    }

    window.bfwNoncePromise = fetch(window.wpApiSettings.nonceAjaxUrl, {
        credentials: 'same-origin',
        cache: 'no-store'   // nonce персональный, кеш тут недопустим
    })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (res) {
            if (!res || !res.nonce) {
                return '';
            }

            /*
             * Сервер сообщает, под каким пользователем выдан nonce.
             * Если он расходится с тем, что считает страница, nonce
             * не подойдёт и WordPress ответит 403. Так бывает, когда
             * ответ отдался из кеша или страница загружена до входа.
             */
            if (typeof res.isLoggedIn !== 'undefined') {
                const pageSays = String((window.wpApiSettings || {}).isLoggedIn || '');
                const nonceSays = String(res.isLoggedIn || '');

                const pageLoggedIn = pageSays === '1' || pageSays === 'true' || pageSays === true;
                const nonceLoggedIn = nonceSays === '1' || nonceSays === 'true' || nonceSays === true;

                if (pageLoggedIn !== nonceLoggedIn) {
                    console.warn('[BFW] nonce выдан под другого пользователя (страница: ' + pageSays + ', сервер: ' + nonceSays + '). Nonce не применён — возможен кеш.');
                    return '';
                }
            }

            window.bfwRestNonce = res.nonce;
            return res.nonce;
        })
        .catch(function () { return ''; })
        .then(function (nonce) {
            // Сбрасываем промис, чтобы при сбое можно было повторить попытку
            if (!nonce) {
                window.bfwNoncePromise = null;
            }
            return nonce;
        });

    return window.bfwNoncePromise;
};

/*
 * Заголовки для REST-запроса. Асинхронные: дожидаются nonce, чтобы
 * не отправлять X-WP-Nonce пустой строкой.
 */
window.bfwRestHeaders = async function (extra) {
    const nonce = await window.bfwGetNonce();
    return Object.assign({}, extra || {}, { 'X-WP-Nonce': nonce });
};

jQuery(document).ready(function ($) {
    // Стартуем загрузку nonce сразу, не дожидаясь первого запроса
    window.bfwGetNonce();

    /*действие при удалении баллов   */
    $('body').on('click', '#bfw_remove_cart_point', async function () {
        const currentURL = window.location.href;

        try {
            const response = await fetch('/wp-json/bfw/v1/clear-fast-bonus', {
                method: 'POST',
                headers: await window.bfwRestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    redirect: currentURL
                })
            });

            if (!response.ok) {
                throw new Error('Ошибка сети или сервера');
            }

            const data = await response.json();

            if (data.success && data.data) {
                // Обновляем блоки кешбэка и списания
                if (typeof window.bfwUpdateBlocks === 'function') {
                    window.bfwUpdateBlocks();
                }
                window.location.href = data.data;
            } else {
                throw new Error('Некорректный ответ сервера');
            }
        } catch (error) {
            console.error('Ошибка при выполнении запроса:', error.message);
        }
    });
    /*действие при удалении баллов   */
    $(document).on('click', '.remove_points', async function () {
        $(this).addClass('loading_button');

        const form = $('.remove_points_form');
        const redirectUrl = form.find('[name="redirect"]').val() || window.location.href;

        try {
            const response = await fetch('/wp-json/bfw/v1/clear-fast-bonus', {
                method: 'POST',
                headers: await window.bfwRestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    redirect: redirectUrl
                })
            });

            if (!response.ok) {
                throw new Error('Ошибка сети или сервера');
            }

            const data = await response.json();

            if (data.success && data.data) {
                // Обновляем блоки кешбэка и списания
                if (typeof window.bfwUpdateBlocks === 'function') {
                    window.bfwUpdateBlocks();
                }
                window.location.href = data.data;
            } else {
                throw new Error('Некорректный ответ сервера');
            }
        } catch (error) {
            console.error('Ошибка при выполнении запроса:', error.message);
            $(this).removeClass('loading_button'); // опционально: сброс состояния
        }
    });

    /*при клике списать баллы*/
    $('body').on('click', '.bfw-spisanie-blocks-button', function () {
        $('.computy_skidka_form').toggleClass('open');
        $('.bfw-spisanie-blocks-button .wc-block-components-panel__button-icon').toggleClass('flipped');

        // Сохраняем состояние в data-атрибуте body
        const isOpen = $('.computy_skidka_form').hasClass('open');
        $('body').data('bfw-panel-open', isOpen);
    });

    // Восстановление состояния после AJAX-загрузки
    $(document).ajaxComplete(function () {
        const shouldBeOpen = $('body').data('bfw-panel-open') || false;
        if (shouldBeOpen) {
            $('.computy_skidka_form').addClass('open');
            $('.bfw-spisanie-blocks-button .wc-block-components-panel__button-icon').addClass('flipped');
        }
    });


    let body = $('body');

    /*Открытие закрытие использование бонусов в корзине*/
    body.on('click', '.computy_skidka_link', function (e) {
        e.stopPropagation();
        $('.computy_skidka_container').show('slow');
        $(this).addClass('show_skidka');
    });
    body.on('click', '.show_skidka', function (e) {
        e.stopPropagation();
        $('.computy_skidka_container').hide('slow');
        $(this).removeClass('show_skidka');
    });
    /*Открытие закрытие использование бонусов в корзине*/

    /*Копирование реферальной ссылки*/
    /*Копирование реферальной ссылки*/
    body.on('click', '#copy_referal', function () {
        const referralText = $('#code_referal').text();

        // Используем современный Clipboard API с fallback
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(referralText)
                .then(function() {
                    $('#copy_good').text("Скопировано в буфер обмена");
                })
                .catch(function(err) {
                    // Fallback для старых браузеров
                    fallbackCopyTextToClipboard(referralText);
                });
        } else {
            // Fallback для очень старых браузеров
            fallbackCopyTextToClipboard(referralText);
        }

        setTimeout(function () {
            $("#copy_good").text(" ").empty();
        }, 2000);
    });

// Вспомогательная функция для fallback
    function fallbackCopyTextToClipboard(text) {
        let $tmp = $("<input>");
        $("body").append($tmp);
        $tmp.val(text).select();
        document.execCommand("copy");
        $tmp.remove();
        $('#copy_good').text("Скопировано в буфер обмена");
    }
    /*Копирование реферальной ссылки*/

    /*действие при списании баллов  */
    $(document).on('click', '.write_points', async function (e) {
        e.preventDefault();
        const $button = $(this);
        $button.addClass('loading_button').prop('disabled', true);

        const $form = $('.computy_skidka_form');
        const redirect = $form.find('[name="redirect"]').val();
        const computyInputPoints = $form.find('[name="computy_input_points"]').val();

        try {
            const response = await fetch("/wp-json/bfw/v1/apply-points", {
                method: 'POST',
                headers: await window.bfwRestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    points: computyInputPoints,
                    redirect: redirect,
                }),
            });

            const result = await response.json();

            if (result.success) {
                // Обновляем блоки кешбэка и списания
                if (typeof window.bfwUpdateBlocks === 'function') {
                    window.bfwUpdateBlocks();
                }
                window.location.href = result.data;
            } else {
                alert(result.message || 'Error');
                $button.removeClass('loading_button').prop('disabled', false);
            }
        } catch (error) {
            console.error('REST API Error:', error);
            $button.removeClass('loading_button').prop('disabled', false);
        }
    });

    /*Введение купона */
    $(document).on('submit', '.take_coupon_form', async function (e) {
        e.preventDefault();

        const $form = $(this);
        const $messageBox = $(".message_coupon");
        const $button = $form.find('button');

        $form.addClass('loading_coupon');
        $messageBox.text('');
        $button.prop('disabled', true);

        const formData = {
            redirect: $form.find('[name="redirect"]').val(),
            code_coupon: $form.find('[name="code_coupon"]').val()
        };

        try {
            const response = await fetch('/wp-json/bfw/v1/activate-coupon', {
                method: 'POST',
                headers: await window.bfwRestHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.cod === '200') {
                $messageBox.css('color', 'green').text(result.message);
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 2000);
            } else {
                $messageBox.css('color', 'red').text(result.message || 'Error');
                $form.removeClass('loading_coupon');
                $button.prop('disabled', false);
            }
        } catch (error) {
            console.error('Coupon activation error:', error);
            $messageBox.text('Server error. Please try again later.');
            $form.removeClass('loading_coupon');
            $button.prop('disabled', false);
        }

        return false;
    });



    $('.checkout_coupon').on('submit', function () {
        $('.remove_points').trigger('click');
    });


    body.on('click', '.woocommerce-remove-coupon', function () {
        $('#computy-bonus-message-cart').show();
    });

    body.on('click', '.computy_skidka_container .button', function () {
        $(document.body).trigger('update_checkout');
    });


});


// Глобальные функции для обновления блоков
window.bfwUpdateBlocks = function() {
    const event = new CustomEvent('bfw-update-blocks');
    document.dispatchEvent(event);
};

(function ($) {
    $(() => {
        let observer;
        let isUpdating = false;

        const API_BASE = '/wp-json/bfw/v1';

        const debounce = (func, delay) => {
            let timeout;
            return function () {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        };

        const fetchBlockData = async (endpoint, params = {}) => {
            try {
                // Дожидаемся nonce: иначе заголовок уходит пустым и WP
                // отвечает «Проверка куки не удалась»
                const headers = await window.bfwRestHeaders({ 'Content-Type': 'application/json' });
                const response = await fetch(`${API_BASE}${endpoint}`, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(params)
                });
                return response.ok ? (await response.json()).html : null;
            } catch (e) { return null; }
        };

        const loadSpisanieBlock = async () => {
            // СТОП, если поле ввода активно
            if ($('.bfw-spisanie-blocks input:focus').length) return;

            isUpdating = true;
            const html = await fetchBlockData('/get-spisanie-html', { redirect: window.location.href });

            if (html) {
                const $wrapper = $('.wc-block-cart__totals-title, .wp-block-woocommerce-checkout-order-summary-cart-items-block');
                let $existing = $('.bfw-spisanie-blocks');

                if ($existing.length) {
                    if ($existing.html() !== html) $existing.html(html);
                } else if ($wrapper.length) {
                    $wrapper.after(`<div class="bfw-spisanie-blocks">${html}</div>`);
                }
            }
            // Даем небольшую задержку перед разблокировкой, чтобы Observer "проморгался"
            setTimeout(() => { isUpdating = false; }, 100);
        };

        const loadCashbackBlock = async () => {
            // debug removed
            isUpdating = true;
            const html = await fetchBlockData('/get-cashback-html');
            if (html) {
                const $wrapper = $('.wc-block-components-totals-footer-item');
                let $existing = $('.bfw-order-cashback-blocks');

                if ($existing.length) {
                    if ($existing.html() !== html) $existing.html(html);
                } else if ($wrapper.length) {
                    $wrapper.after(`<div class="order-cashback bfw-order-cashback-blocks">${html}</div>`);
                }
            }
            setTimeout(() => { isUpdating = false; }, 100);
        };

        const loadPointsOnlyNotice = async () => {
            try {
                const response = await fetch(`${API_BASE}/check-points-only-products`, {
                    method: 'POST',
                    headers: await window.bfwRestHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({})
                });
                const data = await response.json();

                let $existing = $('.bfw-points-only-notice');

                if (data.has_points_only && data.notice_text) {
                    if ($existing.length) {
                        // Просто обновляем текст, если уведомление уже стоит
                        $existing.find('.bfw-points-only-notice__text').text(data.notice_text);
                        return;
                    }

                    const $notice = $(`
                <div class="bfw-points-only-notice wc-block-components-notice-banner is-error" role="alert">
                    <div class="wc-block-components-notice-banner__content bfw-points-only-notice__text">${data.notice_text}</div>
                </div>
            `);

                    // Вставляем ПЕРЕД блоком целиком — вне зоны, которой управляет React
                    const $blockContainer = $('.wc-block-cart, .wc-block-checkout').first();
                    if ($blockContainer.length) {
                        $blockContainer.before($notice);
                    }
                } else {
                    $existing.remove();
                }
            } catch (e) {
                console.error('Error loading points-only notice:', e);
            }
        };

        const isCartOrCheckout = () => {
            return $('body').hasClass('woocommerce-cart') ||
                $('body').hasClass('woocommerce-checkout') ||
                $('.wc-block-cart').length > 0 ||
                $('.wc-block-checkout').length > 0 ||
                $('.wc-block-cart__totals-title').length > 0;
        };

        const initBlocks = debounce(() => {
            if (isUpdating) return;

            if (isCartOrCheckout()) {
                if (typeof wpApiSettings !== 'undefined' && wpApiSettings.isLoggedIn) {
                    loadSpisanieBlock();
                }
                loadCashbackBlock();
                loadPointsOnlyNotice();
            }
        }, 500); // Debounce 500ms для баланса между нагрузкой и отзывчивостью

// Проверяем, что wp.data доступен на странице
        if (typeof wp !== 'undefined' && wp.data) {
            const { subscribe, select } = wp.data;

// Проверяем, что корзина WooCommerce Blocks доступна на странице
            if ( select( 'wc/store/cart' ) ) {
            let previousCart = null;

            subscribe(() => {
                const currentCart = select( 'wc/store/cart' ).getCartData();

                // Если данные корзины еще не загрузились — выходим
                if ( ! currentCart ) {
                    return;
                }

                // Сравниваем по количеству товаров, их количествам и общей сумме
                let cartChanged = false;
                if (!previousCart) {
                    cartChanged = true;
                } else {
                    // Проверяем изменилась ли общая сумма
                    const currentTotal = currentCart.totals ? currentCart.totals.total_price : 0;
                    const previousTotal = previousCart.totals ? previousCart.totals.total_price : 0;

                    if (currentTotal !== previousTotal) {
                        cartChanged = true;
                    } else {
                        // Проверяем изменилось ли количество товаров или их количества
                        const currentItems = currentCart.items ? currentCart.items.length : 0;
                        const previousItems = previousCart.items ? previousCart.items.length : 0;

                        if (currentItems !== previousItems) {
                            cartChanged = true;
                        } else {
                            // Проверяем количества каждого товара
                            for (let i = 0; i < currentItems; i++) {
                                if (currentCart.items[i].quantity !== previousCart.items[i].quantity) {
                                    cartChanged = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if (cartChanged) {
                    previousCart = currentCart;
                    // Вызываем обновление блоков
                    initBlocks();
                }
            });
            }
        }


        
        // Простое обновление кешбэка при смене вариации на основе данных с сервера
        const initVariationCashback = () => {
            // Проверяем есть ли данные о кешбэке вариаций
            if (!window.bfwVariationCashback || !window.bfwVariationCashback.variations) {
                return;
            }
            
            // Для классических вариативных товаров
            if ($('.variations_form').length) {
                const variationForm = $('.variations_form');
                
                // Отслеживаем событие когда WooCommerce находит вариацию
                variationForm.on('found_variation', function(event, variation) {
                    updateCashbackDisplay(variation.variation_id);
                });
                
                // Также отслеживаем изменение атрибутов для надежности
                variationForm.on('change', '.variations select, .variations input', function() {
                    setTimeout(() => {
                        const variationId = $('.variation_id').val();
                        if (variationId) {
                            updateCashbackDisplay(variationId);
                        }
                    }, 100);
                });
            }
        };
        
        const updateCashbackDisplay = (variationId) => {
            const cashbackData = window.bfwVariationCashback;
            if (!cashbackData || !cashbackData.variations) return;

            const variation = cashbackData.variations[variationId];

            /*
             * Обновляем блок ТОЛЬКО основного товара.
             *
             * Раньше здесь было $('.how_mach_bonus').remove() — это стирало
             * бонусы у всех товаров на странице, включая Related Products
             * и Upsells. Плюс $('.price').first() мог вставить блок в цену
             * совершенно другого товара.
             */
            const $mainPrice = getMainProductPriceElement();
            if (!$mainPrice.length) return;

            const $existing = $mainPrice.find('.how_mach_bonus');
            if ($existing.length) {
                $existing.remove();
            }

            if (variation && variation.cashback > 0) {
                const cashbackHtml = '<div class="how_mach_bonus"><span class="how_mach_bonus_title">' +
                                   cashbackData.label + '</span> ' + variation.cashback + ' ' +
                                   getPointsLabel(variation.cashback) + '</div>';

                $mainPrice.append(cashbackHtml);
            }
        };

        /*
         * Блок цены именно основного товара, а не первой карточки на
         * странице. Порядок проверок: контейнер страницы товара, затем
         * обычный .price вне списков товаров.
         */
        const getMainProductPriceElement = () => {
            const selectors = [
                '.product .entry-summary .price',
                '.product .summary .price',
                '.wp-block-woocommerce-product-price .price',
                '.single-product .price'
            ];

            for (const sel of selectors) {
                const $el = $(sel).first();
                if ($el.length) return $el;
            }

            return $();
        };

        const getPointsLabel = (amount) => {
            const absAmount = Math.abs(amount);
            if (absAmount === 1) return window.bfwVariationCashback.pointsLabel || 'балл';
            if (absAmount >= 2 && absAmount <= 4) return 'балла';
            return 'баллов';
        };
        
        initVariationCashback();
        
        // Первый запуск
        setTimeout(initBlocks, 1000);

        // Слушатель для ручного обновления блоков
        document.addEventListener('bfw-update-blocks', function() {
            initBlocks();
        });
    });
})(jQuery);


