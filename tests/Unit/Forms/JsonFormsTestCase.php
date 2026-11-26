<?php

namespace Coretik\Tests\Unit\Forms;

use Brain\Monkey\Functions;
use Coretik\Services\Forms\Form;
use Coretik\Tests\TestCase;
use Coretik\Tests\Unit\Forms\Fixtures\JsonSent;

/**
 * WordPress stubs for the JSON forms: wp_send_json() throws JsonSent, a nonce "valid" is valid.
 */
abstract class JsonFormsTestCase extends TestCase
{
    protected array $transients = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->transients = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        Functions\stubTranslationFunctions();
        Functions\when('wp_send_json')->alias(function ($response, $status = 200) {
            throw new JsonSent($response, $status ?? 200);
        });
        Functions\when('get_transient')->alias(fn ($key) => $this->transients[$key] ?? false);
        Functions\when('set_transient')->alias(function ($key, $value) {
            $this->transients[$key] = $value;
            return true;
        });
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('wp_verify_nonce')->alias(fn ($nonce) => 'valid' === $nonce ? 1 : false);
        Functions\when('sanitize_key')->alias(fn ($key) => \strtolower($key));
        Functions\when('wp_strip_all_tags')->alias(fn ($text) => \strip_tags($text));
        Functions\when('wp_parse_args')->alias(fn ($args, $defaults) => \array_merge($defaults, $args));
        Functions\when('esc_attr')->alias(fn ($text) => \htmlspecialchars((string)$text, ENT_QUOTES));
    }

    protected function tearDown(): void
    {
        $_POST = $_REQUEST = [];
        $_SERVER['REMOTE_ADDR'] = null;
        parent::tearDown();
    }

    /**
     * Posts $data to the form and processes it: the JSON answer sent.
     */
    protected function submit(Form $form, array $data, array $post = [], string $nonce = 'valid'): array
    {
        $_POST = \array_merge([$form->getFormName() => $data + ['form_id' => $form->id(), 'nonce' => $nonce]], $post);

        try {
            $form->process();
        } catch (JsonSent $sent) {
            $this->assertTrue($sent->response['success']);
            return $sent->data();
        }

        $this->fail('No JSON answer sent');
    }
}
