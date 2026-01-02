<?php
defined( 'ABSPATH' ) || exit;

class BfwSetting
{
    /**
     * @var BfwSetting|null Единственный экземпляр класса (инстанс).
     */
    private static $instance = null;

    /**
     * @var array|null Хранилище для настроек.
     */
    private $options = null;

    /**
     * Имя опции в базе данных.
     */
    const OPTION_NAME = 'bonus_option_name';

    /**
     * Закрытый конструктор, чтобы запретить создание экземпляров извне.
     */
    private function __construct()
    {
        // Мы не загружаем настройки здесь, чтобы сохранить ленивую загрузку (lazy loading).
    }

    /**
     * Глобальная точка доступа (геттер) к единственному экземпляру класса.
     * Если экземпляра нет, он создается.
     *
     * @return BfwSetting
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Получает массив настроек.
     * Вызывает get_option() только один раз для данного экземпляра.
     *
     * @return array
     */
    private function load_options()
    {
        // Проверка и загрузка настроек
        if (is_null($this->options)) {
            $this->options = get_option(self::OPTION_NAME, []);
        }
        return $this->options;
    }

    /**
     * Статический метод для получения всех настроек.
     *
     * @return array
     */
    public static function get_all(): array
    {
        // Получаем единственный экземпляр класса и вызываем его метод
        return self::get_instance()->load_options();
    }

    /**
     * Статический метод для получения конкретной настройки.
     *
     * @param string $key Ключ настройки.
     * @param mixed $default Значение по умолчанию.
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $options = self::get_all();
        return !empty($options[$key]) ? $options[$key] : $default;
    }
}