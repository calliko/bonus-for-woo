<?php

defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Класс таблицы купонов
 */
class Bfw_Coupons_List_Table extends WP_List_Table
{

    public function __construct()
    {
        parent::__construct([
            'singular' => 'bfw_coupon',
            'plural'   => 'bfw_coupons',
            'ajax'     => false,
        ]);
    }

    /**
     * Подготовка данных
     */
    public function prepare_items()
    {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $allowed_orderby = ['id', 'created', 'sum', 'code', 'status'];
        $orderby = (!empty($_GET['orderby']) && in_array($_GET['orderby'], $allowed_orderby))
                ? sanitize_text_field($_GET['orderby'])
                : 'id';
        $order = (!empty($_GET['order'])) ? esc_sql($_GET['order']) : 'DESC';

        $where = '';
        if (!empty($_REQUEST['s'])) {
            $search = esc_sql($_REQUEST['s']);
            $where = $wpdb->prepare(" WHERE code LIKE %s OR comment_admin LIKE %s", '%' . $search . '%', '%' . $search . '%');
        }

        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}bfw_coupons_computy {$where}");

        $offset = ($current_page - 1) * $per_page;

        $query = "SELECT * FROM {$wpdb->prefix}bfw_coupons_computy {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

        $data = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));

        $this->items = $data;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    /**
     * Колонки
     */
    public function get_columns()
    {
        return [
            'cb'            => '<input type="checkbox" />',
            'code'          => __('Coupon code', 'bonus-for-woo'),
            'sum'           => __('Sum', 'bonus-for-woo'),
            'created'       => __('Create date', 'bonus-for-woo'),
            'comment_admin' => __('Comment admin', 'bonus-for-woo'),
            'usage'         => __('Client', 'bonus-for-woo'),
            'status'        => __('Status', 'bonus-for-woo'),
            'actions'       => __('Action', 'bonus-for-woo'),
        ];
    }

    public function extra_tablenav($which)
    {
        if ($which !== 'top') {
            return;
        }
        ?>
        <div class="alignleft actions bfw-export-wrapper">
            <a href="<?php echo wp_nonce_url(add_query_arg('bfw_export', 'coupons'), 'bfw_export_action'); ?>" class="button bfw-export-btn bfw-excel-btn">
                <span class="dashicons dashicons-media-spreadsheet"></span> Excel (CSV)
            </a>
            <a href="javascript:void(0);" onclick="return bfwPrintTable(event);" class="button bfw-export-btn bfw-print-btn">
                <span class="dashicons dashicons-printer"></span> <?php _e('Print', 'bonus-for-woo'); ?>
            </a>

        </div>
        <?php
    }


    /**
     * Сортируемые колонки
     *
     * @return array
     */
    public function get_sortable_columns():array
    {
        return [
            'created' => ['created', true],
            'sum'     => ['sum', false],
            'id'      => ['id', false],
           'status'      => ['status', false],
        ];
    }

    /**
     * Чекбокс для массовых действий
     */
    public function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%d" />', $item->id);
    }

    /**
     * Отрисовка колонки Код
     */
    public function column_code($item): string
    {
        $reusable = ($item->reusable == 1) ? ' <span title="' . esc_attr(__('Reusable', 'bonus-for-woo')) . '" class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>' : '';
        return '<strong>' . esc_html($item->code) . '</strong>' . $reusable;
    }

    /**
     * Сумма
     */
    public function column_sum($item): string
    {
        return '<strong>' . BfwPoints::roundPoints($item->sum) . '</strong>';
    }

    /**
     * Дата создания
     */
    public function column_created($item)
    {
        return date_i18n('d.m.Y H:i', strtotime($item->created));
    }

    /**
     * Комментарий
     */
    public function column_comment_admin($item)
    {
        return esc_html($item->comment_admin);
    }

    /**
     * Использование
     */
    public function column_usage($item): string
    {
        if ($item->reusable == 1) {
            return __('Coupon usage:', 'bonus-for-woo') . ' ' . BfwCoupons::countUsedReusableCoupon($item->id);
        } elseif (!empty($item->user)) {
            $user = get_userdata($item->user);
            $name = !empty($user->first_name) ? $user->first_name . ' ' . $user->last_name : ($user->user_login ?? 'unknown');
            return sprintf(
                '<a href="%s" target="_blank">%s</a><br><small>%s</small>',
                get_edit_user_link($item->user),
                esc_html($name),
                esc_html($item->date_use)
            );
        }
        return '-';
    }

    /**
     * Статус
     */
    public function column_status($item):string
    {
        $status_labels = [
            'active'   => ['label' => __('Active', 'bonus-for-woo'), 'color' => '#46b450'],
            'noactive' => ['label' => __('Not active', 'bonus-for-woo'), 'color' => '#dc3232'],
            'used'     => ['label' => __('Used', 'bonus-for-woo'), 'color' => '#999'],
        ];

        $status = $status_labels[$item->status] ?? ['label' => $item->status, 'color' => '#000'];

        return sprintf(
            '<span class="bfw-status-badge" style="background:%s; color:#fff; padding:2px 8px; border-radius:10px; font-size:11px;">%s</span>',
            $status['color'],
            $status['label']
        );
    }

    /**
     * Действия
     */
    public function column_actions($item): string
    {
        $actions = [];
        $nonce = wp_create_nonce('bfw_action_coupon');

        if ($item->status === 'active') {
            $actions['deactivate'] = sprintf(
                '<form method="post" style="display:inline;">
                    %s
                    <input type="hidden" name="status_coupon" value="active">
                    <input type="hidden" name="bfw_edit_status_coupon" value="%d">
                    <button title="%s" class="bfw-btn bfw-btn-outline bfw-edit-btn" style="padding: 5px 12px;" data-id="1" data-name="стартовый" data-percent="1" data-summa="0">
                                            <span class="dashicons dashicons-controls-pause"></span>
                                        </button>
                </form>',
                wp_nonce_field('bfw_action_coupon', 'bfw_nonce_coupon', true, false),
                $item->id,
                __('Deactivate', 'bonus-for-woo')
            );
        } elseif ($item->status === 'noactive') {
            $actions['activate'] = sprintf(
                '<form method="post" style="display:inline;">
                    %s
                    <input type="hidden" name="status_coupon" value="noactive">
                    <input type="hidden" name="bfw_edit_status_coupon" value="%d">
                    <button title="%s" class="bfw-btn bfw-btn-outline bfw-edit-btn" style="padding: 5px 12px;" data-id="1" data-name="стартовый" data-percent="1" data-summa="0">
                                            <span class="dashicons dashicons-controls-play"></span>
                                        </button>
                </form>',
                wp_nonce_field('bfw_action_coupon', 'bfw_nonce_coupon', true, false),
                $item->id,
                __('Activate', 'bonus-for-woo')
            );
        }

        $actions['delete'] = sprintf(
            '<form method="post" style="display:inline;" onsubmit="return confirm(\'%s\');">
                %s
                <input type="hidden" name="bfw_delete_coupon" value="%d">
                <button title="%s" type="submit" class="bfw-btn bfw-btn-danger" style="padding: 6px 12px;">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
              
            </form>',
            esc_js(__('Are you sure you want to delete this coupon?', 'bonus-for-woo')),
            wp_nonce_field('bfw_action_coupon', 'bfw_nonce_coupon', true, false),
            $item->id,
            __('Delete', 'bonus-for-woo')
        );

        return implode(' ', $actions);
    }
}
