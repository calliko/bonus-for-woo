<?php

defined('ABSPATH') || exit;

/**
 * Status class
 * Класс статусов
 *
 * Class Roles
 *
 * @version 6.4.0
 */
class BfwRoles
{

    /**
     * Adding status
     * Добавления статуса
     *
     * @param $name string
     * @param $percent
     * @param $summaStart float
     *
     * @return void
     * @version 6.4.3
     */
    public static function addRole(string $name, $percent, float $summaStart): void
    {
        global $wpdb;
        // Удаление лишних преобразований
        $slug = trim(preg_replace("/\s+/", ' ', wp_strip_all_tags($name)));
        $slug = function_exists('mb_strtolower') ? mb_strtolower($slug) : strtolower($slug);
        $slug = str_replace(" ", "-", $slug);

        // Транслитерация
        $transliterationMap = array(
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'j',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shch',
            'ы' => 'y',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            'ъ' => '',
            'ь' => ''
        );

        $slug = strtr($slug, $transliterationMap);
        $table_name = $wpdb->prefix . 'bfw_computy';

        // Проверка существующего имени
        $existing_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_name WHERE name = %s", $name));

        $existing_sumaStart = $wpdb->get_row($wpdb->prepare("SELECT summa_start, name FROM $table_name WHERE summa_start = %s",
            $summaStart));
        $message = '';
        $message_type = 'notice-warning';
        if (!empty($existing_name)) {
            $message = __('Status',
                    'bonus-for-woo') . ' <b>' . esc_html($name) . '</b> ' . __('is already in use. Please enter another status name.',
                    'bonus-for-woo');
        } elseif ($summaStart < 0) {
            $message = __('The accumulated amount cannot be less than 0', 'bonus-for-woo');
        } elseif (empty($name) || empty($slug)) {
            $message = __('The status name cannot be empty.', 'bonus-for-woo');
        } elseif ($existing_sumaStart) {
            $message = sprintf(__('This amount of orders is in the <b>%s</b> status, change it to another amount.',
                'bonus-for-woo'), $existing_sumaStart->name);
        } else {
            $inserted = $wpdb->insert($table_name,
                array('name' => $name, 'slug' => $slug, 'percent' => $percent, 'summa_start' => $summaStart),
                array("%s", "%s", "%s", "%s"));
            if ($inserted) {
                $message_type = 'notice-success';
                $message = __('Status', 'bonus-for-woo') . '<b> ' . esc_html($name) . '</b> ' . __('added',
                        'bonus-for-woo') . '.';
            } else {
                $message_type = 'notice-error';
                $message = __('Error adding status. Please try again.', 'bonus-for-woo');
            }
        }

        if (!empty($message)) {
            echo '<div id="message" class="notice ' . $message_type . ' is-dismissible"><p>' . $message . '</p></div>';
        }
    }


    /**
     * Обновление статуса админом
     *
     * @param string $name
     * @param $percent
     * @param float $summaStart
     *
     * @return void
     * @version 6.4.3
     */
    public static function updateStatus(string $name, float $percent, float $summaStart): void
    {
        global $wpdb;
        $updated = $wpdb->update($wpdb->prefix . "bfw_computy",
            array('percent' => $percent, 'summa_start' => $summaStart), array('name' => $name));

        if ($updated === false) {
            echo '<div id="message" class="notice notice-error is-dismissible"><p>' . __('Error updating status. Please try again.',
                    'bonus-for-woo') . '</p></div>';
        } else {
            echo '<div id="message" class="notice notice-success is-dismissible"><p>' . __('Status',
                    'bonus-for-woo') . '<b> ' . esc_html($name) . '</b> ' . __('updated',
                    'bonus-for-woo') . '.</p></div>';
        }
    }


    /**
     * Удаление статуса
     *
     * @param int $id
     * @param string $name
     *
     * @return void
     * @version 6.4.3
     */
    public static function deleteStatus(int $id, string $name): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bfw_computy';
        $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id = %d", $id));

        echo '<div id="message" class="notice notice-warning is-dismissible">
	<p>' . __('Status', 'bonus-for-woo') . ' <b>' . $name . '</b> ' . __('deleted', 'bonus-for-woo') . '.</p>
</div>';
    }

    /**
     * Display all statuses
     * Вывод всех статусов
     *
     * @return mixed
     * @version 6.4.0
     */
    public static function getRoles()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT *,summa_start FROM  {$wpdb->prefix}bfw_computy ORDER BY summa_start + 0 asc");
    }


    /**
     * Display user status
     * Вывод статуса пользователя
     *
     * @param int $userId
     *
     * @return array
     * @version 6.3.5
     */
    public static function getRole(int $userId): array
    {
        global $wpdb;
        $status_id = get_user_meta($userId, 'bfw_status', true);
        $user_info = get_userdata($userId);
        if (empty($user_info)) {
            return ['name' => __('User deleted', 'bonus-for-woo'), 'percent' => 0, 'slug' => 'no_status'];
        }

        //$role = $user_info->roles[0] ?? $user_info->roles[1];
        // Получаем первую роль пользователя безопасно
        $roles = is_array($user_info->roles) ? $user_info->roles : [];
        $role = reset($roles); // получаем первую роль


        $exclude_role = BfwSetting::get('exclude-role', array());

        if (in_array($role, (array)$exclude_role) || empty($status_id)) {
            return ['name' => __('No status', 'bonus-for-woo'), 'percent' => 0, 'slug' => 'no_status'];
        }

        $you_role = $wpdb->get_row($wpdb->prepare("SELECT name, percent, slug FROM {$wpdb->prefix}bfw_computy WHERE id = %d",
            $status_id));

        return [
            'name' => $you_role->name ?? __('Client', 'bonus-for-woo'),
            'percent' => $you_role->percent ?? 0,
            'slug' => $you_role->slug ?? 'client'
        ];
    }


    /**
     * Method to update user status
     * Метод обновления статуса пользователя
     * (только для участников бонусной программы)
     *
     * @param int $userId
     * @param bool $sendEmail
     *
     * @return void
     * @version 6.3.4
     */
    public static function updateRole(int $userId, bool $sendEmail = true): void
    {
        $total_all = BfwPoints::getSumUserOrders($userId);
        global $wpdb;
        $allrole = $wpdb->get_results("SELECT id, name, percent, summa_start FROM " . $wpdb->prefix . "bfw_computy ORDER BY summa_start+0 ASC");
        if (!empty($allrole)) {
            $status_id = null;
            $status_name = null;
            $status_percent = null;
            foreach ($allrole as $bfw) {
                if ($total_all >= $bfw->summa_start) {
                    $status_id = $bfw->id;
                    $status_name = $bfw->name;
                    $status_percent = $bfw->percent;
                } else {
                    break;
                }
            }
            if (!empty($status_id)) {
                if ((int)$status_id !== (int)get_user_meta($userId, 'bfw_status', true)) {
                    update_user_meta($userId, 'bfw_status', $status_id);
                    if (BfwSetting::get('email-when-status-chenge') && !empty($sendEmail)) {
                        $text_email = BfwSetting::get('email-when-status-chenge-text', '');
                        $title_email = BfwSetting::get('email-when-status-chenge-title',
                            __('Changing your status', 'bonus-for-woo'));
                        $user = get_userdata($userId);
                        $get_referral = get_user_meta($userId, 'bfw_points_referral', true);
                        $text_email_array = [
                            '[referral-link]' => esc_url(site_url() . '?bfwkey=' . $get_referral),
                            '[user]' => $user->display_name,
                            '[role]' => $status_name,
                            '[cashback]' => $status_percent
                        ];
                        $message_email = BfwEmail::template($text_email, $text_email_array);
                        (new BfwEmail())->getMail($userId, '', $title_email, $message_email);
                    }
                }
            } else {
                delete_user_meta($userId, 'bfw_status');
            }


        }
    }


    /**
     * How much money do you need to spend before reaching the next status?
     * Сколько надо потратить денег до следующего статуса
     *
     * @param int $userId
     *
     * @return array
     * @version 6.3.4
     */
    public static function getNextRole(int $userId): array
    {
        global $wpdb;

        $bfw_computy_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . "bfw_computy");

        if ($bfw_computy_count === 0) {
            return ['status' => 'no'];
        }

        $table_bfw = $wpdb->get_results("SELECT name, percent, summa_start FROM " . $wpdb->prefix . "bfw_computy ORDER BY summa_start+0 ASC",
            ARRAY_A);

        $total_all = max(BfwPoints::getSumUserOrders($userId), 0);

        $next_status = '';
        $next_cash = 0;
        $summa = 0;

        foreach ($table_bfw as $bfw) {
            if ($total_all < $bfw['summa_start']) {
                $next_cash = $bfw['percent'];
                $next_status = $bfw['name'];
                $summa = $bfw['summa_start'];
                break;
            }
        }

        if ($next_status) {
            $ostatok = $summa - $total_all;
            return [
                'percent-zarabotannogo' => 100 * $total_all / $summa,
                'sum' => $ostatok,
                'name' => $next_status,
                'percent' => $next_cash,
                'status' => 'next'
            ];
        }

        return ['status' => 'max'];
    }


    /**
     * Checking whether the user is participating in the bonus system
     * Проверка участвует ли пользователь в бонусной системе
     *
     * @param int $userId
     *
     * @return bool
     * @version 7.1.4
     */
    public static function isInvalve(int $userId): bool
    {
        if ($userId === 0) {
            return false;
        }

        $user_info = get_userdata($userId);
        if (!$user_info) {
            return false;
        }

        $roles = $user_info->roles;
        if (empty($roles)) {
            return false;
        }

        $exclude_roles = BfwSetting::get('exclude-role', array());

        // Проверка: если есть пересечения, значит пользователь исключён
        foreach ($roles as $role) {
            if (in_array($role, $exclude_roles, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check for pro
     * Проверка на про
     *
     * @return bool
     * @version 6.3.4
     */
    public static function isPro(): bool
    {
        return get_option(base64_decode('Ym9udXMtZm9yLXdvby1wcm8=')) === base64_decode('YWN0aXZl');
    }


    /**
     * Find the maximum percentage of statuses
     * Находим максимальный процент статусов
     *
     * @return float
     * @version 6.3.4
     */
    public static function maxPercent(): float
    {
        global $wpdb;
        $max_percent = $wpdb->get_var("SELECT MAX(CAST(percent AS DECIMAL(10, 2))) FROM {$wpdb->prefix}bfw_computy");
        return (float)$max_percent;
    }


    /**
     * Ability for managers to customize the plugin
     * Возможность менеджерам настраивать плагин
     *
     * @param $roles
     *
     * @return array
     * @version 6.3.4
     */
    public static function bfwManagerRoleEditCapabilities($roles): array
    {
        global $wpdb;
        $table_bfw = $wpdb->get_results("SELECT slug FROM {$wpdb->prefix}bfw_computy ORDER BY summa_start + 0 ASC");

        foreach ($table_bfw as $bfw) {
            $roles[] = $bfw->slug;
        }

        return array_merge($roles, ['subscriber', 'customer']);
    }

}
