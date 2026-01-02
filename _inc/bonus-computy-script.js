jQuery(document).ready(function ($) {

    $('body').on('click', '#bfw_remove_cart_point', async function () {
        //$('.remove_points').click() ;
        var currentURL = window.location.href;
        try {
            const response = await fetch("/wp-admin/admin-ajax.php", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'clear_fast_bonus',
                    redirect: currentURL
                }).toString()
            });

            if (!response.ok) {
                throw new Error('Ошибка сети или сервера');
            }

            const data = await response.json();

            if (data && data.data) {
                window.location.href = data.data;
            } else {
                throw new Error('Некорректный ответ сервера');
            }
        } catch (error) {
            console.error('Ошибка при выполнении запроса:', error.message);
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

    /*действие при списании баллов*/
    $(document).on('click', '.write_points', async function () {
        $(this).addClass('loading_button');

        const $form = $('.computy_skidka_form');
        const redirect = $form.find('[name="redirect"]').val();
        const computyInputPoints = $form.find('[name="computy_input_points"]').val();

        try {
            const response = await fetch("/wp-admin/admin-ajax.php", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'computy_trata_points',
                    redirect,
                    computy_input_points: computyInputPoints,
                }),
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            window.location.href = data.data;
        } catch (error) {
            console.error('There was a problem with the fetch operation:', error);
        }
    });

    /*Введение купона */
    $(document).on('submit', '.take_coupon_form', function (e) {
        let form = $(this);
        $(this).addClass('loading_coupon');
        $(".message_coupon").text('');
        e.preventDefault();

        $.ajax({
            type: 'POST',
            url: "/wp-admin/admin-ajax.php",
            data: {
                action: 'bfw_take_coupon_action',
                redirect: $(this).find('[name="redirect"]').val(),
                code_coupon: $(this).find('[name="code_coupon"]').val(),
            },
            success: function (data) {
                form.removeClass('loading_coupon');
                if (data.data.cod === '200') {
                    $(".message_coupon").text(data.data.message);
                    setTimeout(function () {
                        document.location.href = data.data.redirect;
                    }, 2000);

                } else {
                    $(".message_coupon").text(data.data.message);
                }

                //  message_coupon
            },
            error: function (error) {
                console.log(error);

            }
        });
        return false;
    });

    /*действие при удалении баллов*/
    $(document).on('click', '.remove_points', async function () {
        $(this).addClass('loading_button');

        const form = $('.remove_points_form');
        const redirectUrl = form.find('[name="redirect"]').val();

        try {
            const response = await fetch("/wp-admin/admin-ajax.php", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'clear_fast_bonus',
                    redirect: redirectUrl
                }).toString()
            });

            if (!response.ok) {
                throw new Error('Ошибка сети или сервера');
            }

            const data = await response.json();

            if (data && data.data) {
                window.location.href = data.data;
            } else {
                throw new Error('Некорректный ответ сервера');
            }
        } catch (error) {
            console.error('Ошибка при выполнении запроса:', error.message);
        }
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
        let isRequestInProgress = false;
        let lastRequestTime = 0;
        const MIN_REQUEST_INTERVAL = 5000; // Минимальный интервал между запросами - 2 секунды

        // Функция для ограничения частоты вызовов
        const debounce = (func, delay) => {
            let timeout;
            return function () {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        };

        // Безопасный AJAX-запрос с защитой от частых вызовов
        const safePost = (params, callback) => {
            const now = Date.now();

            if (isRequestInProgress || (now - lastRequestTime < MIN_REQUEST_INTERVAL)) {
                return;
            }

            isRequestInProgress = true;
            lastRequestTime = now;

            $.post('/wp-admin/admin-ajax.php', params)
                .done(data => {
                    // console.log('Ответ от сервера:', data);
                    try {
                        callback(data);
                    } catch (e) {
                        console.error('Ошибка обработки ответа:', e);
                    }
                })
                .fail((xhr, status, error) => {
                    console.error("Ошибка запроса:", {
                        status,
                        error,
                        response: xhr.responseText
                    });
                })
                .always(() => {
                    isRequestInProgress = false;
                });
        };


        const observeDOM = () => {
            if (observer) {
                observer.disconnect();
            }

            const target = document.querySelector('.wc-block-cart, .wc-block-checkout');
            if (!target) return;

            observer = new MutationObserver(debounce(() => {
                initBlocks();
            }, 300));

            observer.observe(target, {
                childList: true,
                subtree: true
            });
        };

        const loadSpisanieBlock = () => {
            safePost({
                action: 'woo_blocks_spisanie',
                redirect: window.location.href
            }, (data) => {
                const $existing = $('.bfw-spisanie-blocks');
                const $wrapper = $('.wc-block-cart__totals-title, .wp-block-woocommerce-checkout-order-summary-cart-items-block');

                if ($existing.length) {
                    $existing.html(data);
                } else if ($wrapper.length) {
                    $wrapper.after(`<div class="bfw-spisanie-blocks">${data}</div>`);
                }
            });
        };

        let cashbackTimeout = null;

        const debouncePost = (params, callback, delay = 300) => {
            clearTimeout(cashbackTimeout);
            cashbackTimeout = setTimeout(() => {
                $.post('/wp-admin/admin-ajax.php', params)
                    .done(data => callback(data, null))
                    .fail((xhr, status, error) => callback(null, {
                        status,
                        error,
                        response: xhr.responseText
                    }));
            }, delay);
        };

        const loadCashbackBlock = () => {
            debouncePost({action: 'woo_blocks_cashback'}, (data, err) => {
                if (err) {
                    console.warn('Ошибка кешбэка:', err);
                    return;
                }
                const $existing = $('.bfw-order-cashback-blocks');
                const $wrapper = $('.wc-block-components-totals-footer-item');

                if ($existing.length) {
                    $existing.html(data);
                } else if ($wrapper.length) {
                    $wrapper.after(`<div class="order-cashback bfw-order-cashback-blocks">${data}</div>`);
                }
            }, 300); // 300 мс пауза
        };


        const initBlocks = debounce(() => {
            const hasSpisanieTarget = $('.wc-block-cart__totals-title').length;
            const hasCashbackTarget = $('.wc-block-components-totals-footer-item').length;
            const hasSpisanieTargetCheckout = $('.wp-block-woocommerce-checkout-order-summary-cart-items-block').length;

            if (hasSpisanieTargetCheckout || hasSpisanieTarget) loadSpisanieBlock();
            if (hasCashbackTarget) loadCashbackBlock();
        }, 300);

        // Инициализация
        observeDOM();
        initBlocks();

        // Обработчики событий с debounce
        $('body').on('click', `
            .wc-block-components-chip__remove-icon,
            .wc-block-components-totals-coupon__button .wc-block-components-button__text,
            .wc-block-cart-item__quantity,
            .wc-block-components-totals-coupon__button
        `, debounce(() => {
            $('.bfw-order-cashback-blocks, .bfw-spisanie-blocks').addClass('opacity05');

            setTimeout(() => {
                loadSpisanieBlock();

                loadCashbackBlock();
                $('.bfw-order-cashback-blocks, .bfw-spisanie-blocks').removeClass('opacity05');
            }, 2000);
        }, 500));
    });
})(jQuery);