document.addEventListener('DOMContentLoaded', function () {

    //перерасчет баллов за старые заказы
    let offset = 0;
    let total = 0;
    let processedTotal = 0;

    function step() {
        jQuery.post(ajaxurl, {
            action: 'cashback_recount',
            offset: offset,
            nonce: bfwScriptAdmin.recount_nonce
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

        jQuery.post(ajaxurl, {
            action: 'cashback_prepare',
            nonce: bfwScriptAdmin.recount_nonce
        }, function (res) {
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

    if (document.getElementById('refunded_points_order_status')) {
        new SlimSelect({
            select: '#refunded_points_order_status',
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
                        nonce: bfwScriptAdmin.send_points_nonce
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

/**
 * Функция умной печати таблицы
 */
window.bfwPrintTable = function (e) {
    if (e) e.preventDefault();
    
    const wrap = jQuery('.wrap');
    const table = wrap.find('.wp-list-table').clone();
    const title = wrap.find('h1').first().text();

    // Удаляем ненужные колонки из клона
    table.find('.column-cb, .check-column, .column-actions').remove();
    // Удаляем все формы внутри ячеек (например, кнопки удаления)
    table.find('form').remove();

    const win = window.open('', 'PRINT', 'height=800,width=1000');
    
    win.document.write('<html><head><title>' + title + '</title>');
    win.document.write('<style>');
    win.document.write('body { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; padding: 30px; color: #000; }');
    win.document.write('h1 { font-size: 18pt; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }');
    win.document.write('table { border-collapse: collapse; width: 100%; border: 1px solid #000; }');
    win.document.write('th, td { border: 1px solid #000; padding: 10px 8px; text-align: left; font-size: 10pt; word-break: break-all; }');
    win.document.write('th { background: #f0f0f1; font-weight: bold; }');
    win.document.write('.bfw-status-badge { font-weight: bold; text-transform: uppercase; font-size: 8pt; border: 1px solid #000; padding: 2px 4px; }');
    win.document.write('</style>');
    win.document.write('</head><body>');
    win.document.write('<h1>' + title + '</h1>');
    win.document.write(table[0].outerHTML);
    win.document.write('</body></html>');
    
    win.document.close();
    
    // Небольшая задержка для рендеринга
    setTimeout(function() {
        win.focus();
        win.print();
        win.close();
    }, 300);

    return false;
};



