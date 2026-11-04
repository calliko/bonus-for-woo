<?php

defined('ABSPATH') || exit;

/**
 * Class BfwLicense
 * Проверка лицензии по криптографической подписи сервера.
 *
 * Почему так:
 *  - Раньше статус PRO хранился строкой в wp_options и проверялся сравнением
 *    get_option(...) === 'active'. Достаточно было одного SQL-запроса или
 *    любого плагина, чтобы включить платный режим. Обфускация base64_decode
 *    ничего не защищала — ключ декодировался одной командой.
 *  - Теперь решение принимается по ПОДПИСИ сервера лицензий: сервер
 *    подписывает payload своим приватным ключом, плагин проверяет вшитым
 *    публичным. Изменить payload (домен, план, срок) без приватного ключа
 *    невозможно — подпись перестанет сходиться.
 *
 * Модель доверия (важно понимать):
 *  - Это защищает от включения PRO без ключа через wp_options/SQL/ADMIN UI.
 *  - НЕ защищает от правки самого PHP-кода: код исполняется на сервере
 *    клиента, и тот, кто может править файлы, отключит проверку. От этого
 *    защиты не существует; барьер — только хеш-контроль файлов.
 *
 * Режим "не ломать честных клиентов":
 *  - посл��дний валидный токен кешируется; без сети плагин работает на нём;
 *  - отключаемся только когда токен старше GRACE_DAYS.
 *
 * @version 8.5.0
 * @since   8.5.0
 */
class BfwLicense
{
    /**
     * Публичный ключ сервера лицензий (боевой, р. computy.ru).
     *
     * Соответствует приватному ключу на сервере:
     *   D:/OSPanel/home/.secrets/bfw_license_private.pem (вне webroot)
     * Публичный ключ можно раздавать — он только проверяет подпись.
     */
    const PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwQCKX+nW/ewn+owANlft
6ponr6KqX6W9IHkElYVBiSVAwsRl5c/IOOqn+fqazdOKnPa7sLwIWCpUnbST7/h/
Rl/rJXoIFa3VhPEOZUJS7HajsGbIuiAO482cbyZe3oJxlpZlKBLj5ihPh67W4P+1
fzAWc8dnAlJFClaAktBGkCAahmk4lJbjHrQ+fZm16mmohEh4wgMvh3LFi2qEQC0i
c5nDSnRr3NQn//fnKSDaNwdILJN4349eNTIZloZf+GsLOYtgrM1F8oxNvI0iQxNQ
rb/BRAvnfAhMyrFQVS2MlC6QjI1UkEy3aU1EK1s3Gvz2OvHvjYjI+HtU54Q9jeAv
NQIDAQAB
-----END PUBLIC KEY-----
PEM;

    /**
     * Идентификатор продукта на сервере лицензий (поле `event` в таблице donate).
     * НЕ версия — иначе сервер ищет лицензию по номеру версии.
     */
    const PLUGIN_SLUG = 'bonus-for-woo';

    /** Эндпоинт проверки/выдачи токена. */
    const ENDPOINT = 'https://computy.ru/API/api.php';

    /** Токен перепроверяется не чаще, чем раз в столько секунд (7 дней). */
    const RECHECK_INTERVAL = 604800;

    /** Сколько живёт токен без успешной перепроверки, прежде чем PRO отключится. */
    const GRACE_DAYS = 30;

    /** Ключи хранения. */
    const OPTION_TOKEN = 'bfw_license_token';
    const OPTION_LAST_CHECK = 'bfw_license_last_check';
    const OPTION_LAST_RESULT = 'bfw_license_last_result';

    /**
     * Кэш результата в пределах запроса.
     *
     * @var bool|null
     */
    private static $status_cache = null;

    /**
     * Активна ли лицензия (PRO).
     *
     * Заменяет прежнее сравнение get_option('bonus-for-woo-pro') === 'active'.
     *
     * @return bool
     * @version 8.5.0
     */
    public static function isActive(): bool
    {
        if (self::$status_cache !== null) {
            return self::$status_cache;
        }

        // Разработчикам/тестам нужен обход без сети
        if (defined('BFW_LICENSE_BYPASS') && BFW_LICENSE_BYPASS) {
            return self::$status_cache = true;
        }

        $token = (string) get_option(self::OPTION_TOKEN, '');
        if ($token === '') {
            return self::$status_cache = false;
        }

        $result = self::verifyToken($token, self::currentDomain());
        if (!$result['ok']) {
            return self::$status_cache = false;
        }

        /*
         * Токен валиден. Если он давно не подтверждался сервером — работает
         * grace-период, после чего считаем лицензию недействительной.
         * Это защита от схемы «активировал один раз и заблокировал сеть».
         */
        $last_check = (int) get_option(self::OPTION_LAST_CHECK, 0);
        $grace_seconds = self::GRACE_DAYS * DAY_IN_SECONDS;

        if ($last_check > 0 && (time() - $last_check) > $grace_seconds) {
            return self::$status_cache = false;
        }

        return self::$status_cache = true;
    }

    /**
     * Требуется ли повторная проверка у сервера.
     *
     * @return bool
     * @version 8.5.0
     */
    public static function needsRemoteCheck(): bool
    {
        $last = (int) get_option(self::OPTION_LAST_CHECK, 0);

        // Проверяемся в админке и не чаще интервала
        if (!is_admin() && !wp_doing_ajax()) {
            return false;
        }

        return (time() - $last) >= self::RECHECK_INTERVAL;
    }

    /**
     * Проверяет токен локально — без сети.
     *
     * Доверять payload можно только после проверки подписи: до этого это
     * просто текст, который мог изменить кто угодно.
     *
     * @param string $token    Токен вида "payload.signature" в base64url
     * @param string $domain   Ожидаемый домен
     *
     * @return array{ok:bool,reason:string,plan?:string,expires?:int}
     * @version 8.5.0
     */
    public static function verifyToken(string $token, string $domain): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return array('ok' => false, 'reason' => 'malformed');
        }

        $json = self::base64UrlDecode($parts[0]);
        $signature = self::base64UrlDecode($parts[1]);

        if ($json === false || $signature === false || $json === '' || $signature === '') {
            return array('ok' => false, 'reason' => 'bad_encoding');
        }

        // ГЛАВНАЯ ПРОВЕРКА: подпись сервера
        $verified = openssl_verify($json, $signature, self::PUBLIC_KEY, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return array('ok' => false, 'reason' => 'bad_signature');
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return array('ok' => false, 'reason' => 'bad_payload');
        }

        // Привязка к домену: токен другого сайта не подходит
        $token_domain = (string) ($payload['domain'] ?? '');
        if ($token_domain !== '' && $token_domain !== $domain) {
            return array('ok' => false, 'reason' => 'domain_mismatch');
        }

        // Срок действия, указанный сервером
        $expires = (int) ($payload['expires'] ?? 0);
        if ($expires > 0 && $expires < time()) {
            return array('ok' => false, 'reason' => 'expired', 'expires' => $expires);
        }

        return array(
            'ok'      => true,
            'reason'  => 'valid',
            'plan'    => (string) ($payload['plan'] ?? ''),
            'expires' => $expires,
        );
    }

    /**
     * Запрашивает у сервера новый токен по ключу активации.
     *
     * @param string $license_key Ключ, введённый админом
     *
     * @return array{ok:bool,reason:string,error?:string}
     * @version 8.5.0
     */
    public static function activate(string $license_key): array
    {
        $license_key = trim($license_key);
        if ($license_key === '') {
            return array('ok' => false, 'reason' => 'empty_key');
        }

        $response = wp_remote_post(self::ENDPOINT, array(
            'timeout' => 15,
            'body'    => array(
                'action'         => 'license_activate',
                'key'            => $license_key,
                'site'           => self::currentDomain(),
                // Имя продукта — им индексируется лицензия в базе.
                // Версия идёт отдельным полем, иначе сервер искал бы event='8.5.0'.
                'plugin'         => self::PLUGIN_SLUG,
                'plugin_version' => BONUS_COMPUTY_VERSION,
            ),
        ));

        if (is_wp_error($response)) {
            return array('ok' => false, 'reason' => 'network', 'error' => $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return array('ok' => false, 'reason' => 'http_' . $code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        /*
         * Сервер отвечает HTTP 200 и на отказ: {"status":"ERROR","error":2}.
         * Раньше проверялся только код ответа, поэтому отказ выглядел как
         * «unexpected response» и админ не понимал, что не так с ключом.
         */
        if (is_array($data) && isset($data['status']) && $data['status'] === 'ERROR') {
            $codes = array(
                1 => 'empty_key',
                2 => 'invalid_key',
                3 => 'wrong_domain',
                4 => 'limit_reached',
                5 => 'unavailable',
            );
            $reason = $codes[(int) ($data['error'] ?? 0)] ?? 'unexpected_response';

            return array(
                'ok'      => false,
                'reason'  => $reason,
                'error'   => (string) ($data['message'] ?? ''),
            );
        }

        // Не JSON — заглушка хостера, не приговор лицензии
        if (!is_array($data) || empty($data['token'])) {
            return array('ok' => false, 'reason' => 'unexpected_response');
        }

        // Токен принимаем только с валидной подписью — даже пришедший «от сервера»
        $check = self::verifyToken((string) $data['token'], self::currentDomain());
        if (!$check['ok']) {
            return array('ok' => false, 'reason' => 'bad_token_' . $check['reason']);
        }

        update_option(self::OPTION_TOKEN, (string) $data['token'], false);
        update_option(self::OPTION_LAST_CHECK, time(), false);
        update_option(self::OPTION_LAST_RESULT, 'active', false);

        self::$status_cache = null;

        return array('ok' => true, 'reason' => 'activated');
    }

    /**
     * Периодическая перепроверка токена у сервера.
     *
     * При любой сетевой проблеме НЕ отключаем PRO: просто откладываем
     * следующую попытку. Отключение — только по grace-периоду.
     *
     * @return void
     * @version 8.5.0
     */
    public static function maybeRemoteCheck(): void
    {
        if (!self::needsRemoteCheck()) {
            return;
        }

        $token = (string) get_option(self::OPTION_TOKEN, '');
        if ($token === '') {
            return;
        }

        $response = wp_remote_post(self::ENDPOINT, array(
            'timeout' => 10,
            'body'    => array(
                'action'         => 'license_check',
                'token'          => $token,
                'site'           => self::currentDomain(),
                'plugin'         => self::PLUGIN_SLUG,
                'plugin_version' => BONUS_COMPUTY_VERSION,
            ),
        ));

        // Сеть недоступна — оставляем как есть, PRO продолжает работать
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return;
        }

        // Обновлённый токен от сервера — принимаем только с валидной подписью
        if (!empty($data['token']) && is_string($data['token'])) {
            $check = self::verifyToken($data['token'], self::currentDomain());
            if ($check['ok']) {
                update_option(self::OPTION_TOKEN, $data['token'], false);
                update_option(self::OPTION_LAST_CHECK, time(), false);
                update_option(self::OPTION_LAST_RESULT, 'active', false);
                self::$status_cache = null;
                return;
            }
        }

        // Сервер явно сказал, что лицензия отозвана
        if (isset($data['status']) && $data['status'] === 'REVOKED') {
            update_option(self::OPTION_LAST_RESULT, 'revoked', false);
            delete_option(self::OPTION_TOKEN);
            self::$status_cache = null;
            return;
        }

        // Успешное подтверждение без смены токена — продлеваем срок
        if (isset($data['status']) && $data['status'] === 'OK') {
            update_option(self::OPTION_LAST_CHECK, time(), false);
        }
    }

    /**
     * Домен сайта в нормализованном виде (для привязки токена).
     *
     * @return string
     * @version 8.5.0
     */
    public static function currentDomain(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = strtolower((string) $host);
        return preg_replace('/^www\./', '', $host);
    }

    /**
     * Сбрасывает всё состояние лицензии (для деактивации/отладки).
     *
     * @return void
     * @version 8.5.0
     */
    public static function reset(): void
    {
        delete_option(self::OPTION_TOKEN);
        delete_option(self::OPTION_LAST_CHECK);
        delete_option(self::OPTION_LAST_RESULT);
        delete_option('bonus-for-woo-pro');
        self::$status_cache = null;
    }

    /**
     * base64url decode (RFC 7515), безопасный.
     *
     * @param string $value
     * @return string|false
     */
    private static function base64UrlDecode(string $value)
    {
        $value = strtr($value, '-_', '+/');
        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($value, true);
    }
}
