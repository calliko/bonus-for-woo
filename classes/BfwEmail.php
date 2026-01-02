<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class BfwEmail
 * Класс отправки электронных писем
 *
 * @version 6.4.0
 * @since 2.2.0
 */
class BfwEmail
{

    /**
     * Method of sending a message about points
     * Метод отправки сообщения о баллах
     *
     * @param int $user_id
     * @param string $subject
     * @param string $title
     * @param string $message
     *
     * @version 6.4.0
     * @since 2.5.1
     */
    public function getMail(int $user_id, string $subject, string $title, string $message): void
    {
        $from_name = get_option('woocommerce_email_from_name', get_bloginfo('name'));
        $from_address = get_option('woocommerce_email_from_address', get_option('admin_email'));

        $headers = array('From: ' . $from_name . ' <' . $from_address . '>', 'content-type: text/html',);

        if (empty($subject)) {
            $subject = sprintf(__('Reward %s notification', 'bonus-for-woo'),
                    (new BfwPoints())::pointsLabel(5)) . ' ' . $from_name;
        }

        if (empty($title)) {
            $title = $subject;
        }

        $messages = self::getHeaderMail($title) . $message . self::getFooterMail();

        BfwLogs::addLog('message', $user_id, $message);

        if (!BfwSetting::get('email-my-methode')) {
            $user = get_userdata($user_id);
            if ($user && $user->user_email) {
                wp_mail($user->user_email, $subject, $messages, $headers);
            }
        } else {
            do_action('bfw_my_methode_get_mail', $message, $user_id);
        }
    }


    /**
     * Letter header template
     * Шаблон шапки письма
     *
     * @param string $title
     *
     * @return string
     *
     * @version 5.8.7
     */
    public static function getHeaderMail(string $title): string
    {
        $background_color = get_option("woocommerce_email_background_color");
        $base_color = get_option("woocommerce_email_base_color");
        $body_bg_color = get_option("woocommerce_email_body_background_color");
        $text_color = get_option("woocommerce_email_text_color");

        $template_path = get_stylesheet_directory() . '/bonus-for-woo/email_header.php';
        if (!file_exists($template_path)) {
            $template_path = BONUS_COMPUTY_PLUGIN_DIR . '/templates/email_header.php';
        }

        ob_start();
        include $template_path;
        $mail_header = ob_get_clean();

        return strtr($mail_header, [
            '{background_color}' => $background_color,
            '{woocommerce_email_base_color}' => $base_color,
            '{woocommerce_email_text_color}' => $text_color,
            '{woocommerce_email_body_background_color}' => $body_bg_color,
            '{title}' => $title
        ]);
    }

    /**
     * Email Footer Template
     * Шаблон подвала письма
     *
     * @return string
     * @version 5.8.7
     */
    public static function getFooterMail(): string
    {
        $site_title = get_bloginfo('name');
        $site_url = $_SERVER['SERVER_NAME'];
        $footer_text = get_option('woocommerce_email_footer_text');
        $description = strtr($footer_text,
            ['{site_title}' => $site_title, '{site_url}' => $site_url, '{WooCommerce}' => 'WooCommerce']);

        $template_path = get_stylesheet_directory() . '/bonus-for-woo/email_footer.php';
        if (!file_exists($template_path)) {
            $template_path = BONUS_COMPUTY_PLUGIN_DIR . '/templates/email_footer.php';
        }

        ob_start();
        include $template_path;
        $output = ob_get_clean();

        return strtr($output, ['{description}' => $description]);
    }


    /**
     * Forming a letter template
     * Формирование шаблона письма
     *
     * @param string $text_email Шаблон настроек
     * @param array $text_email_array
     *
     * @return string
     * @version 2.5.1
     */
    public static function template(string $text_email, array $text_email_array): string
    {
        ob_start();
        echo wpautop($text_email);
        $get_contents = ob_get_contents();
        ob_end_clean();
        return strtr($get_contents, $text_email_array);
    }


}
