document.addEventListener('DOMContentLoaded', function () {

    //перерасчет баллов за старые заказы
    let offset = 0;
    let total = 0;
    let processedTotal = 0;

    function step() {
        jQuery.post(ajaxurl, {
            action: 'cashback_recount',
            offset: offset,
        }, function (response) {
            offset = response.nextOffset;
            total = response.total;
            processedTotal = response.processedTotal;
            let percent = Math.min(100, Math.round(processedTotal / total * 100));

            jQuery('#progress').text(percent + '%');

            if (offset < total) {
                step();
            } else {
                jQuery('#progress').text(bfwScriptAdmin.done + ' ' + processedTotal);
                jQuery('#startRecalculation').prop('disabled', false);
                jQuery('#startRecalculation').css('opacity', '1');
                // Можно также добавить курсор "недоступно" для наглядности
                jQuery('#startRecalculation').css('cursor', 'default');
                jQuery('#startRecalculation').removeClass('loading_button');
            }
        });
    }

    jQuery('#startRecalculation').on('click', function (e) {
        e.preventDefault();
        jQuery(this).addClass('loading_button');
        jQuery('#progress').text('');
        // Блокируем кнопку на время запроса
        jQuery(this).prop('disabled', true);
        // Устанавливаем opacity 0.5
        jQuery(this).css('opacity', '0.5');
        // Можно также добавить курсор "недоступно" для наглядности
        jQuery(this).css('cursor', 'not-allowed');

        // Предотвращаем действие по умолчанию (если нужно)

        jQuery.post(ajaxurl, {action: 'cashback_prepare'}, function (res) {
            offset = 0;
            total = res.total;

            step();
        });
    });
//перерасчет баллов за старые заказы


    if (document.getElementById('exclude-role')) {
        new SlimSelect({
            select: '#exclude-role',
        });
    }

    if (document.getElementById('exclude-category')) {
        new SlimSelect({
            select: '#exclude-category',
        });
    }

    if (document.getElementById('exclude-payment-method')) {
        new SlimSelect({
            select: '#exclude-payment-method',
        });
    }

    function hideMessage(container) {
        if (!container) return;
        container.style.display = 'none';
    }

    function showMessage(container, message, type) {
        if (!container) return;

        container.innerHTML = `<div class="${type}">${message}</div>`;
        container.style.display = 'block';
    }

    if (document.getElementById('send-points-from-order')) {
        const sendButton = document.getElementById('send-points-from-order');

        if (!sendButton) return;

        sendButton.addEventListener('click', async function (e) {
            e.preventDefault();
            const button = this;
            const orderId = document.getElementById('post_ID').value.trim();
            const responseDiv = document.getElementById('bonus-points-response');
            // const wpnonce = document.getElementById('_wpnonce');
            // Блокируем кнопку на время запроса
            button.disabled = true;
            hideMessage(responseDiv);

            try {
                const response = await fetch("/wp-admin/admin-ajax.php", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'bfw_send_points_from_order',
                        orderId: orderId,
                        //  wpnonce: wpnonce
                    }),
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();
                if (data.success) {
                    const message = `${data.data.message}`;
                    showMessage(responseDiv, message, 'success');
                    // Обновляем страницу через 3 секунды
                    setTimeout(function () {
                        location.reload();
                    }, 3000);
                } else {
                    showMessage(responseDiv, data.data?.message || 'Произошла ошибка', 'error');
                }
                //  window.location.href = data.data;
            } catch (error) {
                console.error('There was a problem with the fetch operation:', error);
            }


        });
    }


});

//Таймер обратного отсчета
function countdown(endDate) {
    let days, hours, minutes, seconds;

    endDate = new Date(endDate).getTime();

    if (isNaN(endDate)) {
        return;
    }

    setInterval(calculate, 1000);

    function calculate() {
        let startDate = new Date().getTime();
        let timeRemaining = parseInt((endDate - startDate) / 1000);

        if (timeRemaining >= 0) {
            days = parseInt(timeRemaining / 86400);
            timeRemaining = (timeRemaining % 86400);
            hours = parseInt(timeRemaining / 3600);
            timeRemaining = (timeRemaining % 3600);
            minutes = parseInt(timeRemaining / 60);
            seconds = parseInt(timeRemaining % 60);

            document.getElementById("days").innerHTML = parseInt(days, 10);
            document.getElementById("hours").innerHTML = ("0" + hours).slice(-2);
            document.getElementById("minutes").innerHTML = ("0" + minutes).slice(-2);
            document.getElementById("seconds").innerHTML = ("0" + seconds).slice(-2);
        } else {
            return;
        }
    }
}

(function () {
    const divElements = document.querySelectorAll(`div.countdown`);
    if (divElements.length > 0) {
        countdown('2024-10-25T23:59:59');
    }
})();


