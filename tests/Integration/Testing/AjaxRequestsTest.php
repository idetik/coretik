<?php

namespace Coretik\Tests\Integration\Testing;

use Coretik\Testing\AjaxRequests;
use Coretik\Tests\Integration\IntegrationTestCase;

/**
 * Coretik\Testing\AjaxRequests: AJAX handlers run as admin-ajax.php does
 */
class AjaxRequestsTest extends IntegrationTestCase
{
    use AjaxRequests;

    public function set_up(): void
    {
        parent::set_up();

        \add_action('wp_ajax_kit_echo', function () {
            \wp_send_json_success(['name' => $_POST['name'] ?? null, 'user' => \get_current_user_id()]);
        });
        \add_action('wp_ajax_nopriv_kit_echo', function () {
            \wp_send_json_error(['message' => 'Logged out'], 403);
        });
        \add_action('wp_ajax_kit_nonce', function () {
            \check_ajax_referer('kit');
            \wp_send_json_success();
        });
        \add_action('wp_ajax_kit_die', fn () => \wp_die('Stopped'));
    }

    public function testReturnsTheJsonOfTheHandler(): void
    {
        $user = $this->subscriber();

        $response = $this->ajax('kit_echo', ['name' => 'Tent']);

        $this->assertSame(['success' => true, 'data' => ['name' => 'Tent', 'user' => $user->ID]], $response);
    }

    public function testLoggedOutRequestRunsTheNoprivHandler(): void
    {
        $this->assertSame(['success' => false, 'data' => ['message' => 'Logged out']], $this->ajax('kit_echo'));
    }

    public function testRequestVariablesAreRestored(): void
    {
        $this->subscriber();
        $_POST = $_REQUEST = ['kept' => 1];

        $this->ajax('kit_echo', ['name' => 'Tent']);

        $this->assertSame(['kept' => 1], $_POST);
        $this->assertSame(['kept' => 1], $_REQUEST);
        $_POST = $_REQUEST = [];
    }

    public function testNonceChecked(): void
    {
        $this->subscriber();

        $this->assertSame(['die' => '-1'], $this->ajax('kit_nonce', ['_ajax_nonce' => 'wrong']));
        $this->assertSame(['success' => true], $this->ajax('kit_nonce', ['_ajax_nonce' => \wp_create_nonce('kit')]));
    }

    public function testWpDieMessageWhenNothingPrinted(): void
    {
        $this->subscriber();

        $this->assertSame(['die' => 'Stopped'], $this->ajax('kit_die'));
        $this->assertFalse(\wp_doing_ajax(), 'Filters removed');
    }

    public function testCaptureJsonRethrowsOtherExceptions(): void
    {
        $level = \ob_get_level();

        try {
            $this->captureJson(function () {
                echo 'partial';
                throw new \LogicException('Bug');
            });
            $this->fail('Exception expected');
        } catch (\LogicException $e) {
            $this->assertSame('Bug', $e->getMessage());
        }

        $this->assertSame($level, \ob_get_level(), 'Output buffers closed');
    }

    public function testCaptureJsonOfAnyHandler(): void
    {
        $this->assertSame(['status' => 'ok'], $this->captureJson(fn () => \wp_send_json(['status' => 'ok'])));
    }
}
