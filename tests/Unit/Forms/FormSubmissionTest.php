<?php

namespace Coretik\Tests\Unit\Forms;

use Brain\Monkey\Functions;
use Coretik\Services\Forms\Form;
use Coretik\Tests\TestCase;

class FormSubmissionTest extends TestCase
{
    private array $transients = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->transients = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        Functions\when('get_transient')->alias(fn ($key) => $this->transients[$key] ?? false);
        Functions\when('set_transient')->alias(function ($key, $value) {
            $this->transients[$key] = $value;
            return true;
        });
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('wp_verify_nonce')->alias(fn ($nonce) => 'valid' === $nonce ? 1 : false);
        Functions\when('sanitize_key')->alias(fn ($key) => \strtolower($key));
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('remove_accents')->returnArg();
        Functions\when('wp_strip_all_tags')->alias(fn ($text) => \strip_tags($text));
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SERVER['REMOTE_ADDR'] = null;
        parent::tearDown();
    }

    protected function form(): Form
    {
        return new class ('contact') extends Form {
            public int $runs = 0;

            public function getRules(): array
            {
                return ['message' => ['name' => 'Message']];
            }

            protected function isValidContext(): bool
            {
                return true;
            }

            protected function run(): void
            {
                $this->runs++;
            }
        };
    }

    protected function submit(Form $form, array $data, string $nonce = 'valid', array $post = []): Form
    {
        $_POST = \array_merge([$form->getFormName() => $data + ['form_id' => 'contact', 'nonce' => $nonce]], $post);
        $form->process();
        return $form;
    }

    public function testValidSubmissionRuns(): void
    {
        $this->assertSame(1, $this->submit($this->form(), ['message' => 'Hello'])->runs);
    }

    public function testInvalidNonceDoesNotRun(): void
    {
        $this->assertSame(0, $this->submit($this->form(), ['message' => 'Hello'], 'forged')->runs);
    }

    public function testHoneypotDoesNotRun(): void
    {
        $this->assertSame(0, $this->submit($this->form(), ['message' => 'Hello'], 'valid', ['form_coretik_confirm' => 'on'])->runs);
    }
}
