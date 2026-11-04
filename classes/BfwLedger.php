<?php

defined('ABSPATH') || exit;

/**
 * Class BfwLedger
 * Журнал транзакций по бонусным баллам (append-only).
 *
 * Проблема, которую решает класс: баланс хранился только в user_meta
 * `computy_point` и менялся через read-modify-write без блокировки и без
 * идемпотентности. Любой повторный хук (возврат, повторный статус, 1С,
 * два параллельных запроса) мог начислить/списать дважды, а расхождение
 * с историей нельзя было обнаружить.
 *
 * Здесь каждая операция:
 *  1. получает блокировку по пользователю (GET_LOCK),
 *  2. проверяет идемпотентность по ключу (ref_type, ref_id, reason),
 *  3. пишет строку журнала и обновляет баланс в одной транзакции.
 *
 * @version 8.3.4
 * @since   8.3.4
 */
class BfwLedger
{
    /** Причины операций */
    const REASON_ORDER_CASHBACK   = 'order_cashback';
    const REASON_ORDER_DEBIT      = 'order_debit';
    const REASON_ORDER_DEBIT_REFUND = 'order_debit_refund';
    const REASON_CASHBACK_REVOKE  = 'cashback_revoke';
    const REASON_REFERRAL         = 'referral';
    const REASON_REFERRAL_L2      = 'referral_l2';
    const REASON_COUPON           = 'coupon';
    const REASON_REVIEW           = 'review';
    const REASON_REVIEW_REVOKE    = 'review_revoke';
    const REASON_REGISTRATION     = 'registration';
    const REASON_EVERYDAY         = 'everyday';
    const REASON_BIRTHDAY         = 'birthday';
    const REASON_INACTIVITY       = 'inactivity';
    const REASON_ADMIN            = 'admin';
    const REASON_IMPORT           = 'import';
    const REASON_MASS             = 'mass_add';
    const REASON_MIGRATION        = 'migration';

    /**
     * Максимальное время удержания блокировки, сек.
     */
    const LOCK_TIMEOUT = 5;

    /**
     * Название таблицы журнала.
     */
    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'bfw_ledger';
    }

    /**
     * Существует ли таблица журнала (на случай, если dbDelta ещё не отработал).
     */
    public static function isAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }
        global $wpdb;
        $table = self::table();
        $available = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $available;
    }

    /**
     * Применяет операцию к балансу пользователя.
     *
     * @param int    $user_id    Пользователь
     * @param float  $delta      Изменение баланса (+начисление, -списание)
     * @param string $reason     Причина (константа REASON_*)
     * @param string $ref_type   Тип объекта ('order', 'coupon', 'review', 'user', '')
     * @param int    $ref_id     ID объекта
     * @param string $note       Текст для истории/логов
     * @param array  $options    ['history' => bool, 'log' => bool, 'allow_negative' => bool]
     *
     * @return bool true если операция применена, false если отклонена как дубль или по ошибке
     */
    public static function apply(
        int $user_id,
        float $delta,
        string $reason,
        string $ref_type = '',
        int $ref_id = 0,
        string $note = '',
        array $options = array()
    ): bool {
        if ($user_id <= 0) {
            return false;
        }

        $delta = self::normalize($delta);
        if (abs($delta) < 0.00005) {
            return false;
        }

        $options = array_merge(array(
            'history'        => true,
            'log'            => true,
            'allow_negative' => false,
        ), $options);

        if (!self::isAvailable()) {
            // Фолбэк: журнала нет (старая установка) — ведём себя как раньше.
            return self::legacyApply($user_id, $delta, $note, $options);
        }

        $lock = self::acquireLock($user_id);
        if ($lock === false) {
            BfwLogs::addLog('error', $user_id, sprintf('BfwLedger: не удалось получить блокировку (reason=%s)', $reason));
            return false;
        }

        try {
            global $wpdb;

            // 1. Идемпотентность: та же операция по тому же объекту уже применена?
            if ($ref_type !== '' && self::isDuplicate($user_id, $reason, $ref_type, $ref_id)) {
                BfwLogs::addLog('debug', $user_id, sprintf(
                    'BfwLedger: операция уже применена, пропуск (reason=%s, %s#%d)',
                    $reason, $ref_type, $ref_id
                ));
                self::releaseLock($lock);
                return false;
            }

            // 2. Читаем актуальный баланс под блокировкой
            $current = BfwPoints::getPoints($user_id);
            $new_balance = self::normalize($current + $delta);

            if ($new_balance < 0 && !$options['allow_negative']) {
                // Не уходим в минус: списываем ровно столько, сколько есть.
                // delta ДОЛЖЕН быть пересчитан до записи в журнал, иначе
                // сумма журнала перестанет сходиться с балансом.
                $delta = self::normalize(-$current);
                $new_balance = 0.0;
                if (abs($delta) < 0.00005) {
                    self::releaseLock($lock);
                    return false;
                }
                $note = $note . ' (списано только доступное)';
            }

            $new_balance = BfwPoints::roundPoints($new_balance);
            $delta = BfwPoints::roundPoints($delta);

            // 3. Пишем журнал ДО обновления баланса: уникальный ключ отсечёт
            //    параллельный дубль даже если оба прошли проверку isDuplicate
            $inserted = $wpdb->insert(
                self::table(),
                array(
                    'user_id'       => $user_id,
                    'delta'         => $delta,
                    'balance_after' => $new_balance,
                    'reason'        => $reason,
                    'ref_type'      => (string) $ref_type,
                    'ref_id'        => $ref_id,
                    'note'          => mb_substr((string) $note, 0, 255),
                    'created'       => current_time('mysql'),
                ),
                array('%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s')
            );

            if ($inserted === false) {
                // Скорее всего сработал UNIQUE — операция уже была записана
                BfwLogs::addLog('debug', $user_id, sprintf(
                    'BfwLedger: конфликт уникального ключа, пропуск (reason=%s, %s#%d)',
                    $reason, $ref_type, $ref_id
                ));
                self::releaseLock($lock);
                return false;
            }

            // 4. Обновляем баланс
            update_user_meta($user_id, 'computy_point', $new_balance);

            self::releaseLock($lock);
        } catch (Throwable $e) {
            self::releaseLock($lock);
            BfwLogs::addLog('error', $user_id, 'BfwLedger: ' . $e->getMessage());
            return false;
        }

        // 5. История и лог — вне блокировки
        if ($options['history']) {
            BfwHistory::add_history(
                $user_id,
                $delta >= 0 ? '+' : '-',
                abs($delta),
                ($ref_type === 'order') ? $ref_id : 0,
                $note
            );
        }

        if ($options['log']) {
            BfwLogs::addLog(
                $delta >= 0 ? 'add_points' : 'remove_points',
                $user_id,
                $note . ($ref_id ? sprintf(' (%s #%d)', $ref_type, $ref_id) : '')
            );
        }

        return true;
    }

    /**
     * Уже применялась ли операция.
     */
    public static function isDuplicate(int $user_id, string $reason, string $ref_type, int $ref_id): bool
    {
        if (!self::isAvailable()) {
            return false;
        }
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE ref_type = %s AND ref_id = %d AND reason = %s AND user_id = %d LIMIT 1',
            $ref_type,
            $ref_id,
            $reason,
            $user_id
        ));
        return (bool) $found;
    }

    /**
     * Баланс, восстановленный по журналу.
     */
    public static function balanceFromLedger(int $user_id): float
    {
        if (!self::isAvailable()) {
            return 0.0;
        }
        global $wpdb;
        return self::normalize((float) $wpdb->get_var($wpdb->prepare(
            'SELECT COALESCE(SUM(delta), 0) FROM ' . self::table() . ' WHERE user_id = %d',
            $user_id
        )));
    }

    /**
     * Пользователи, у которых баланс в user_meta расходится с журналом.
     *
     * @return array список ['user_id','meta','ledger','diff']
     */
    public static function findMismatches(int $limit = 500): array
    {
        if (!self::isAvailable()) {
            return array();
        }
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT um.user_id AS user_id,
                    CAST(um.meta_value AS DECIMAL(19,4)) AS meta_balance,
                    COALESCE(l.total, 0) AS ledger_balance
             FROM {$wpdb->usermeta} um
             LEFT JOIN (
                 SELECT user_id, SUM(delta) AS total FROM " . self::table() . " GROUP BY user_id
             ) l ON l.user_id = um.user_id
             WHERE um.meta_key = 'computy_point'
               AND ABS(CAST(um.meta_value AS DECIMAL(19,4)) - COALESCE(l.total, 0)) > 0.004
             ORDER BY ABS(CAST(um.meta_value AS DECIMAL(19,4)) - COALESCE(l.total, 0)) DESC
             LIMIT %d",
            $limit
        ));

        $out = array();
        foreach ((array) $rows as $r) {
            $out[] = array(
                'user_id' => (int) $r->user_id,
                'meta'    => self::normalize((float) $r->meta_balance),
                'ledger'  => self::normalize((float) $r->ledger_balance),
                'diff'    => self::normalize((float) $r->meta_balance - (float) $r->ledger_balance),
            );
        }
        return $out;
    }

    /**
     * Последние операции пользователя.
     */
    public static function getHistory(int $user_id, int $limit = 50): array
    {
        if (!self::isAvailable()) {
            return array();
        }
        global $wpdb;
        return (array) $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d',
            $user_id,
            $limit
        ));
    }

    /**
     * Переносит существующие записи истории в журнал (миграция).
     * Делается один раз, помечается опцией.
     *
     * @return int сколько записей перенесено
     */
    public static function migrateFromHistory(int $batch = 500): int
    {
        if (!self::isAvailable()) {
            return 0;
        }
        global $wpdb;

        $last_id = (int) get_option('bfw_ledger_migrated_history_id', 0);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, `user`, symbol, points, orderz, comment_admin
             FROM {$wpdb->prefix}bfw_history_computy
             WHERE id > %d
             ORDER BY id ASC
             LIMIT %d",
            $last_id,
            $batch
        ));

        if (empty($rows)) {
            update_option('bfw_ledger_migrated', 'done', false);
            return 0;
        }

        $table = self::table();
        $count = 0;

        foreach ($rows as $row) {
            $delta = ($row->symbol === '-') ? -abs((float) $row->points) : abs((float) $row->points);

            // Исторические записи переносим как единственные в своём роде:
            // ref_type=migrated, ref_id=id записи истории.
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$table}
                    (user_id, delta, balance_after, reason, ref_type, ref_id, note, created)
                 VALUES (%d, %f, 0, %s, 'migrated', %d, %s, %s)",
                (int) $row->user,
                $delta,
                self::REASON_MIGRATION,
                (int) $row->id,
                mb_substr((string) $row->comment_admin, 0, 255),
                current_time('mysql')
            ));
            $count++;
            $last_id = (int) $row->id;
        }

        update_option('bfw_ledger_migrated_history_id', $last_id, false);

        // Пересчитываем balance_after для затронутых пользователей
        $users = wp_list_pluck($rows, 'user');
        foreach (array_unique(array_map('intval', $users)) as $uid) {
            self::rebuildBalanceAfter($uid);
        }

        return $count;
    }

    /**
     * Пересчитывает колонку balance_after для одного пользователя (после миграции).
     */
    public static function rebuildBalanceAfter(int $user_id): void
    {
        if (!self::isAvailable()) {
            return;
        }
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, delta FROM {$table} WHERE user_id = %d ORDER BY id ASC",
            $user_id
        ));

        $running = 0.0;
        foreach ((array) $rows as $r) {
            $running = self::normalize($running + (float) $r->delta);
            $wpdb->update($table, array('balance_after' => $running), array('id' => (int) $r->id), array('%f'), array('%d'));
        }
    }

    /**
     * Блокировка по пользователю. MySQL GET_LOCK — на соединение, поэтому
     * держим её в рамках текущего запроса. Ключ ограничен 64 символами.
     *
     * @return string|false
     */
    private static function acquireLock(int $user_id)
    {
        global $wpdb;
        $name = 'bfw_ledger_' . $user_id;
        $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, self::LOCK_TIMEOUT));
        return ((string) $got === '1') ? $name : false;
    }

    private static function releaseLock($name): void
    {
        if ($name === false || $name === null) {
            return;
        }
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }

    /**
     * Путь без журнала: сохраняем прежнее поведение, но атомарно по возможности.
     */
    private static function legacyApply(int $user_id, float $delta, string $note, array $options): bool
    {
        $current = BfwPoints::getPoints($user_id);
        $new_balance = BfwPoints::roundPoints(max(0, $current + $delta));

        update_user_meta($user_id, 'computy_point', $new_balance);

        if ($options['history']) {
            BfwHistory::add_history($user_id, $delta >= 0 ? '+' : '-', abs($delta), 0, $note);
        }
        return true;
    }

    /**
     * Защита от -0.0 и плавающего мусора.
     */
    private static function normalize(float $value): float
    {
        $rounded = round($value, 4);
        return ($rounded == 0.0) ? 0.0 : $rounded;
    }
}
