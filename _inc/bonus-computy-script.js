jQuery(document).ready(function ($) {
    /*действие при удалении баллов   */
    $('body').on('click', '#bfw_remove_cart_point', async function () {
        const currentURL = window.location.href;

        try {
            const response = await fetch('/wp-json/bfw/v1/clear-fast-bonus', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    // Если требуется nonce (для авторизованных действий):
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    redirect: currentURL
                })
            });

            if (!response.ok) {
                throw new Error('Ошибка сети или сервера');
            }

            const data = await response.json();

            if (data.success && data.data) {
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
                headers: {
                    'Content-Type': 'application/json',
                    // Если используете nonce:
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    redirect: redirectUrl
                })
            });

            if (!response.ok) {
                throw new Error('Ошибка сети или сервера');
            }

            const data = await response.json();

            if (data.success && data.data) {
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
    body.on('click', '#copy_referal', function () {
        let $tmp = $("<input>");
        $("body").append($tmp);
        $tmp.val($('#code_referal').text()).select();
        document.execCommand("copy");
        $('#copy_good').text("Скопировано в буфер обмена");
        setTimeout(function () {
            $("#copy_good").text(" ").empty();
        }, 2000);
        $tmp.remove();
    });
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
                headers: {
                    'Content-Type': 'application/json',
                    // Добавляем nonce для безопасности REST API
                    'X-WP-Nonce': wpApiSettings.nonce // Убедитесь, что передаете nonce через wp_localize_script
                },
                body: JSON.stringify({
                    points: computyInputPoints,
                    redirect: redirect,
                }),
            });

            const result = await response.json();

            if (result.success) {
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
                headers: {
                    'Content-Type': 'application/json',
                    // Используем стандартный Nonce WordPress или ваш bfw_params.nonce
                    'X-WP-Nonce': typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : bfw_params.nonce
                },
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


(function ($) {
    $(() => {
        let observer;
        let isUpdating = false;

        const API_BASE = '/wp-json/bfw/v1';
        const NONCE = typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '';

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
                const response = await fetch(`${API_BASE}${endpoint}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
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

        const initBlocks = debounce(() => {
            if (isUpdating) return;
            loadSpisanieBlock();
            loadCashbackBlock();
        }, 600); // Увеличили debounce

        const observeDOM = () => {
            const container = document.querySelector('.wc-block-cart, .wc-block-checkout');
            if (!container) return;

            observer = new MutationObserver((mutations) => {
                if (isUpdating) return;

                let shouldUpdate = false;
                for (let mutation of mutations) {
                    // Игнорируем изменения, если они произошли ВНУТРИ наших блоков
                    if (mutation.target.closest('.bfw-spisanie-blocks, .bfw-order-cashback-blocks')) {
                        continue;
                    }

                    // Реагируем только если изменился состав элементов (добавились/удалились блоки корзины)
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        shouldUpdate = true;
                        break;
                    }
                }

                if (shouldUpdate) initBlocks();
            });

            observer.observe(container, { childList: true, subtree: true });
        };

        observeDOM();
        // Первый запуск
        setTimeout(initBlocks, 1000);
    });
})(jQuery);