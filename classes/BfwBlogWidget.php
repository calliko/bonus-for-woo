<?php

defined('ABSPATH') || exit;

/**
 * Виджет со свежими статьями блога автора на странице настроек.
 *
 * Раньше эти функции были объявлены прямо внутри метода рендера страницы
 * (BfwAdmin::bonus_plugin_options). Два вызова подряд давали
 * «Cannot redeclare function», а сами функции жили в глобальном пространстве
 * имён. Теперь это класс.
 *
 * @since 8.5.2
 */
class BfwBlogWidget
{
    /** Откуда берём статьи */
    const FEED_URL = 'https://computy.ru/blog/tag/bfw/';

    /** Транзиент с результатом разбора */
    const CACHE_KEY = 'bfw_blog_tag_posts';

    /** Сколько статей показываем */
    const LIMIT = 5;

    /**
     * Разбирает ленту статей автора. Результат кешируется на час.
     *
     * @return array{title:string,date:string,link:string}[]|array{error:string}
     * @since 8.5.2
     */
    public static function getPosts(): array
    {
        $cached = get_transient(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(self::FEED_URL, array(
            'timeout'    => 15,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $html = wp_remote_retrieve_body($response);

        if ($html === '') {
            return array('error' => __('The page is empty.', 'bonus-for-woo'));
        }

        $posts = self::parseHtml($html);

        /*
         * Пустой результат тоже кешируем, но ненадолго: иначе страница
         * настроек будет ходить наружу на каждом открытии, если разметка
         * ленты изменилась.
         */
        set_transient(self::CACHE_KEY, $posts, $posts ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS);

        return $posts;
    }

    /**
     * Разбор HTML ленты.
     *
     * @param string $html
     * @return array
     * @since 8.5.2
     */
    private static function parseHtml(string $html): array
    {
        if (!class_exists('DOMDocument')) {
            return array('error' => __('The DOM extension is not available.', 'bonus-for-woo'));
        }

        $dom = new DOMDocument();

        // Подавляем предупреждения парсера на невалидной разметке
        $previous = libxml_use_internal_errors(true);

        /*
         * mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') объявлен
         * устаревшим в PHP 8.2. Кодировку задаём через инструкцию XML в
         * начале строки — так DOMDocument читает документ как UTF-8.
         */
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $articles = $xpath->query("//article[contains(@class, 'loop')]");

        if ($articles === false) {
            return array();
        }

        $posts = array();

        foreach ($articles as $article) {
            if (count($posts) >= self::LIMIT) {
                break;
            }

            $title_node = $xpath->query(".//div[contains(@class, 'kakh3')]/a", $article);
            $title = $title_node->length > 0 ? trim($title_node->item(0)->nodeValue) : '';

            $date_node = $xpath->query(".//span[@itemprop='datePublished']", $article);
            $date = $date_node->length > 0 ? trim($date_node->item(0)->nodeValue) : '';

            if ($date === '') {
                // Разметка блога иногда даёт дату только текстом: «02.09.2026г.»
                if (preg_match('/\d{2}\.\d{2}\.\d{4}г\./u', $article->textContent, $matches)) {
                    $date = $matches[0];
                }
            }

            if ($title === '' || $date === '') {
                continue;
            }

            $posts[] = array(
                'title' => $title,
                'date'  => $date,
                'link'  => $title_node->length > 0 ? (string) $title_node->item(0)->getAttribute('href') : '',
            );
        }

        return $posts;
    }

    /**
     * HTML виджета.
     *
     * @return string
     * @since 8.5.2
     */
    public static function render(): string
    {
        $posts = self::getPosts();

        if (isset($posts['error'])) {
            return '<p style="color:#d63638;">' . esc_html($posts['error']) . '</p>';
        }

        if (empty($posts)) {
            return '<p>' . esc_html__('No articles found.', 'bonus-for-woo') . '</p>';
        }

        $output = '<ul style="list-style:auto; margin:0; padding:0;">';

        foreach ($posts as $post) {
            $title = esc_html($post['title']);
            $date = esc_html($post['date']);
            $link = esc_url($post['link']);

            $output .= '<li>';

            if ($link !== '') {
                $output .= '<a href="' . $link . '" target="_blank" rel="noopener noreferrer">' . $title . '</a>';
            } else {
                $output .= '<strong>' . $title . '</strong>';
            }

            $output .= ' <span style="color:#fff; font-size:12px;">&#128197; ' . $date . '</span>';
            $output .= '</li>';
        }

        return $output . '</ul>';
    }
}
