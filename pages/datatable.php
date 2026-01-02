<?php

if (determine_locale() === 'ru_RU') {
    $language = ' language: {
                        "sProcessing":   "Подождите...",
                        "sLengthMenu":   "Показать _MENU_ записей",
                        "sZeroRecords":  "Записи отсутствуют.",
                        "sInfo":         "Записи с _START_ до _END_ из _TOTAL_ записей",
                        "sInfoEmpty":    "Записи с 0 до 0 из 0 записей",
                        "sInfoFiltered": "(отфильтровано из _MAX_ записей)",
                        "sInfoPostFix":  "",
                        "sSearch":       "Поиск:",
                        "sUrl":          "",
                        "oPaginate": {
                            "sFirst": "Первая",
                            "sPrevious": "Предыдущая",
                            "sNext": "Следующая",
                            "sLast": "Последняя"
                        },
                        "oAria": {
                            "sSortAscending":  ": активировать для сортировки столбца по возрастанию",
                            "sSortDescending": ": активировать для сортировки столбцов по убыванию"
                        }}';
} elseif (determine_locale() === 'uk') {
    $language = ' language: {
                        "sProcessing":   "Зачекайте...",
                        "sLengthMenu":   "Выдобразити _MENU_ записів",
                        "sZeroRecords":  "Записи відсутні.",
                        "sInfo":         "Записи від _START_ до _END_ з _TOTAL_ записів",
                        "sInfoEmpty":    "Записи від 0 до 0 з 0 записів",
                        "sInfoFiltered": "(відфільтровано з _MAX_ записів)",
                        "sInfoPostFix":  "",
                        "sSearch":       "Пошук:",
                        "sUrl":          "",
                        "oPaginate": {
                            "sFirst": "Перша",
                            "sPrevious": "Попередня",
                            "sNext": "Наступна",
                            "sLast": "Остання"
                        },
                        "oAria": {
                            "sSortAscending":  ": активировать для сортировки столбца по возрастанию",
                            "sSortDescending": ": активировать для сортировки столбцов по убыванию"
                        }}';
} else {
    $language = '';
}
?>
    <script>
        jQuery(document).ready(function () {
            jQuery('#table-history-points').DataTable(
                {
                    responsive: true,
                    <?php if(current_user_can('manage_options')
                    && BfwRoles::isPro()){?>
                    dom: 'Bfrtip',
                    buttons: [
                        'excel', 'pdf', 'print'
                    ],
                    <?php } ?>
                    <?php if(!current_user_can('manage_options')){?>
                    'sDom': '"top"i',

                    <?php } ?>
                    <?php  echo $language; ?>
                }
            );
        });

    </script>
<?php


wp_register_style(
        'datatables.min.css',
        BONUS_COMPUTY_PLUGIN_URL . '_inc/datatables/datatables.min.css',
        array(),
        BONUS_COMPUTY_VERSION
);
wp_register_script(
        'jquery.dataTables.min.js',
        BONUS_COMPUTY_PLUGIN_URL . '_inc/datatables/jquery.dataTables.min.js',
        array(),
        BONUS_COMPUTY_VERSION,
        true
);
wp_register_script(
        'dataTables.responsive.min.js',
        BONUS_COMPUTY_PLUGIN_URL
        . '_inc/datatables/Responsive-2.2.5/js/dataTables.responsive.min.js',
        array(),
        BONUS_COMPUTY_VERSION,
        true
);
wp_register_style(
        'datatablesres.min.css',
        BONUS_COMPUTY_PLUGIN_URL
        . '_inc/datatables/Responsive-2.2.5/css/responsive.dataTables.min.css',
        array(),
        BONUS_COMPUTY_VERSION
);

wp_enqueue_style('datatables.min.css');
wp_enqueue_script('jquery.dataTables.min.js');
wp_enqueue_script('dataTables.responsive.min.js');
wp_enqueue_style('datatablesres.min.css');


wp_register_style(
        'button.min.css',
        BONUS_COMPUTY_PLUGIN_URL
        . '_inc/datatables/buttons/buttons.dataTables.min.css',
        array(),
        BONUS_COMPUTY_VERSION
);
wp_register_script(
        'button-js1',
        BONUS_COMPUTY_PLUGIN_URL . '_inc/datatables/buttons/buttons.html5.min.js',
        array('jquery'),
        BONUS_COMPUTY_VERSION,
        true
);
wp_register_script(
        'button-js2',
        BONUS_COMPUTY_PLUGIN_URL . '_inc/datatables/buttons/buttons.print.min.js',
        array(),
        BONUS_COMPUTY_VERSION,
        true
);
wp_register_script(
        'button-js3',
        BONUS_COMPUTY_PLUGIN_URL
        . '_inc/datatables/buttons/dataTables.buttons.min.js',
        array(),
        BONUS_COMPUTY_VERSION,
        true
);
wp_register_script(
        'button-js4',
        BONUS_COMPUTY_PLUGIN_URL . '_inc/datatables/buttons/jszip.min.js',
        array(),
        BONUS_COMPUTY_VERSION,
        true
);
wp_register_script(
        'button-js5',
        BONUS_COMPUTY_PLUGIN_URL . '_inc/datatables/buttons/pdfmake.min.js',
        array(),
        BONUS_COMPUTY_VERSION,
        true
);
wp_register_script(
        'button-js6',
        BONUS_COMPUTY_PLUGIN_URL . '_inc/datatables/buttons/vfs_fonts.js',
        array(),
        BONUS_COMPUTY_VERSION,
        true
);


wp_enqueue_style('button.min.css');

wp_enqueue_script('button-js2');
wp_enqueue_script('button-js3');
wp_enqueue_script('button-js4');
wp_enqueue_script('button-js5');
wp_enqueue_script('button-js6');
wp_enqueue_script('button-js1');
