<?php

namespace Coretik\Testing;

/**
 * Runs AJAX handlers in integration tests, as admin-ajax.php does: wp_die() (wp_send_json_*(), check_ajax_referer())
 * stops the handler, the JSON printed is returned. The HTTP status is not observable under PHPUnit (headers already
 * sent): check the payload.
 */
trait AjaxRequests
{
    /**
     * Runs the wp_ajax_{$action} handlers, wp_ajax_nopriv_{$action} when no user is logged in
     *
     * @param array $post $_POST (and $_REQUEST) of the request, restored afterwards
     * @return array decoded JSON, or ['die' => wp_die() message] when nothing printed
     */
    protected function ajax(string $action, array $post = []): array
    {
        $previous = [$_POST, $_REQUEST];
        $_POST = $_REQUEST = $post;
        $hook = (\is_user_logged_in() ? 'wp_ajax_' : 'wp_ajax_nopriv_') . $action;

        try {
            return $this->captureJson(fn () => \do_action($hook));
        } finally {
            [$_POST, $_REQUEST] = $previous;
        }
    }

    /**
     * Runs a handler answering with wp_send_json*() (admin-ajax.php, admin-post.php, webhook…): stopped at wp_die(),
     * the JSON printed is returned
     *
     * @return array decoded JSON, or ['die' => wp_die() message] when nothing printed
     */
    protected function captureJson(callable $handler): array
    {
        $die = fn () => function ($message) {
            throw new WpDie(\is_scalar($message) ? (string)$message : '');
        };
        \add_filter('wp_doing_ajax', '__return_true');
        \add_filter('wp_die_ajax_handler', $die, 1);

        $level = \ob_get_level();
        \ob_start();
        $message = null;
        try {
            $handler();
        } catch (WpDie $e) {
            $message = $e->getMessage();
        } finally {
            $output = '';
            while (\ob_get_level() > $level) {
                $output = \ob_get_clean() . $output;
            }
            \remove_filter('wp_doing_ajax', '__return_true');
            \remove_filter('wp_die_ajax_handler', $die, 1);
        }

        $json = \json_decode($output, true);
        return \is_array($json) ? $json : ['die' => (string)$message];
    }
}
