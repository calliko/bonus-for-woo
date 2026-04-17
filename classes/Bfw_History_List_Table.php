<?php

defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Класс таблицы истории баллов
 */
class Bfw_History_List_Table extends WP_List_Table
{

    public function __construct()
    {
        parent::__construct([
            'singular' => 'bfw_history',
            'plural'   => 'bfw_histories',
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

        $date_start = $_GET['date_start'] ?? false;
        $date_finish = $_GET['date_finish'] ?? false;

        $where_clauses = [];
        if ($date_start) {
            $where_clauses[] = $wpdb->prepare("date >= %s", $date_start . ' 00:00:00');
        }
        if ($date_finish) {
            $where_clauses[] = $wpdb->prepare("date <= %s", $date_finish . ' 23:59:59');
        } elseif ($date_start) {
            // Если указано только начало, ограничиваем концом сегодняшнего дня по времени сайта
            $where_clauses[] = $wpdb->prepare("date <= %s", current_time('Y-m-d') . ' 23:59:59');
        }

        $where = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

        $orderby = (!empty($_GET['orderby'])) ? esc_sql($_GET['orderby']) : 'date';
        $order = (!empty($_GET['order'])) ? esc_sql($_GET['order']) : 'DESC';

        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}bfw_history_computy {$where}");

        $offset = ($current_page - 1) * $per_page;

        $query = "SELECT * FROM {$wpdb->prefix}bfw_history_computy {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $data = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));

        $this->items = $data;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    /**
     * Определение колонок
     */
    public function get_columns()
    {
        return [
            'id'            => '№',
            'date'          => __('Date', 'bonus-for-woo'),
            'user_info'     => __('Client', 'bonus-for-woo'),
            'points'        => BfwPoints::pointsLabel(5),
            'event'         => __('Event', 'bonus-for-woo'),
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
            <a href="<?php echo wp_nonce_url(add_query_arg('bfw_export', 'history'), 'bfw_export_action'); ?>" class="button bfw-export-btn bfw-excel-btn">
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
     */
    public function get_sortable_columns()
    {
        return [
            'date'   => ['date', true],
            'points' => ['points', false],
        ];
    }

    /**
     * Отрисовка колонки ID
     */
    public function column_id($item)
    {
        return $item->id;
    }

    /**
     * Отрисовка колонки Дата
     */
    public function column_date($item)
    {
        return date_i18n('d.m.Y H:i', strtotime($item->date));
    }

    /**
     * Отрисовка колонки Пользователь
     */
    public function column_user_info($item)
    {
        $user = get_userdata($item->user);
        if (!$user) {
            return sprintf(__('Deleted user #%d', 'bonus-for-woo'), $item->user);
        }

        $name = !empty($user->first_name) ? $user->first_name . ' ' . $user->last_name : $user->user_login;
        $role = BfwRoles::getRole($item->user);

        return sprintf(
            '<strong><a href="%s">%s</a></strong><br><small>%s | %s</small>',
            get_edit_user_link($item->user),
            esc_html($name),
            esc_html($user->user_email),
            esc_html($role['name'] ?? '')
        );
    }

    /**
     * Отрисовка колонки Баллы
     */
    public function column_points($item)
    {
        $color = $item->symbol === '+' ? '#23CE48' : ($item->symbol === '-' ? '#FF001D' : '');
        return sprintf(
            '<span style="color:%s; font-weight:bold;">%s%s</span>',
            esc_attr($color),
            esc_html($item->symbol),
            BfwPoints::roundPoints($item->points)
        );
    }

    /**
     * Отрисовка колонки Событие
     */
    public function column_event($item)
    {
        $event_text = '';
        if ($item->orderz != '0') {
            $event_text = sprintf(
                '<a href="%s">%s №%s</a> ',
                admin_url('post.php?post=' . $item->orderz . '&action=edit'),
                __('Order', 'bonus-for-woo'),
                $item->orderz
            );
        }
        return $event_text . esc_html($item->comment_admin);
    }

    /**
     * Отрисовка колонки Действия
     */
    public function column_actions($item)
    {
        $nonce = wp_create_nonce('bfw_action_history');
        return sprintf(
            '<form method="post" onsubmit="return confirm(\'%s\');">
                %s
                <input type="hidden" name="bfw_delete_post_history_points" value="%d">
               <button title="%s" type="submit" class="bfw-btn bfw-btn-danger" style="padding: 6px 12px;">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
            
            </form>',
            esc_js(__('Are you sure you want to remove this entry from your reward points history?', 'bonus-for-woo')),
            wp_nonce_field('bfw_action_history', 'bfw_nonce_history', true, false),
            $item->id,
            esc_attr(__('Delete', 'bonus-for-woo'))
        );
    }
}
