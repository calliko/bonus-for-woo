/*Списание баллов в редакторе заказа*/
jQuery(document).ready(function ($) {
    // Находим контейнер кнопки "Добавить позиции"
    var addItemsButton = $('.post-type-shop_order button.add-line-item');

    // Создаем свою кнопку
    var customButton = $('<button>', {
        type: 'button',
        class: 'button bfw-write-points-button',
        text: 'Списать баллы'
    });

    // Добавляем кнопку рядом с "Добавить позиции"
    addItemsButton.after(customButton);

    // Создаем модальное окно
    var modal = $('<div>', {
        id: 'custom-modal',
        style: 'display:none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.5); z-index: 10000;'
    });

    // Добавляем текст и поле ввода
    modal.append(
        $('<p>', {text: 'Сколько баллов списать?'}),
        $('<input>', {type: 'number', id: 'points-input', style: 'width: 100%; padding: 5px;'}),
        $('<button>', {type: 'button', id: 'submit-points', text: 'Применить', style: 'margin-top: 10px;'})
    );

    // Добавляем модальное окно в DOM
    $('body').append(modal);

    // Обработчик клика по кнопке "Списать баллы"
    customButton.on('click', function () {
        modal.show(); // Показываем модальное окно
    });

    // Обработчик клика по кнопке "Применить"
    $('#submit-points').on('click', function () {
        var points = $('#points-input').val(); // Получаем значение из поля ввода

        if (points && !isNaN(points)) {
            // Отправляем данные на сервер через AJAX
            $.ajax({
                url: ajaxurl, // AJAX-адрес WordPress
                type: 'POST',
                data: {
                    action: 'deduct_points', // Название действия
                    order_id: woocommerce_admin_meta_boxes.post_id, // ID заказа
                    points: points // Количество баллов
                },
                success: function (response) {
                    if (response.success) {
                        alert('Баллы успешно списаны!');
                        modal.hide(); // Скрываем модальное окно
                        location.reload(); // Перезагружаем страницу, если нужно
                    } else {
                        alert('Ошибка: ' + response.data);
                    }
                }
            });
        } else {
            alert('Пожалуйста, введите корректное количество баллов.');
        }
    });

    // Закрытие модального окна при клике вне его области
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#custom-modal').length && !$(e.target).closest('.bfw-write-points-button').length) {
            modal.hide();
        }
    });
});


jQuery(document).ready(function ($) {
    $(document).on('click', '.remove-coupon', function () {
        var couponCode = $(this).data('code');
        var orderId = woocommerce_admin_meta_boxes.post_id;
        // Отправляем данные на сервер
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'track_coupon_removal',
                order_id: orderId,
                coupon_code: couponCode
            },
            success: function (response) {
                console.log(response);
            }
        });
    });
});