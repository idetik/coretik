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
        Functions\when('esc_attr')->alias(fn ($text) => \htmlspecialchars((string)$text, ENT_QUOTES));
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

    public function testRateLimitIsDisabledByDefault(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(1, $this->submit($this->form(), ['message' => 'Hello'])->runs);
        }
    }

    public function testRateLimitBlocksSubmissionsOverMax(): void
    {
        \Brain\Monkey\Filters\expectApplied('coretik/forms/rate_limit')->andReturn(['max' => 2]);

        $this->assertSame(1, $this->submit($this->form(), ['message' => 'Hello'])->runs);
        $this->assertSame(1, $this->submit($this->form(), ['message' => 'Hello'])->runs);

        $form = $this->submit($this->form(), ['message' => 'Hello']);
        $this->assertSame(0, $form->runs);
        $this->assertSame('Too many submissions', $form->getSubmissionResult()['error']);
    }

    public function testRateLimitIsPerClientIp(): void
    {
        \Brain\Monkey\Filters\expectApplied('coretik/forms/rate_limit')->andReturn(['max' => 1]);

        $this->assertSame(1, $this->submit($this->form(), ['message' => 'Hello'])->runs);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.11';
        $this->assertSame(1, $this->submit($this->form(), ['message' => 'Hello'])->runs);
    }

    public function testRateLimitWindowExpires(): void
    {
        \Brain\Monkey\Filters\expectApplied('coretik/forms/rate_limit')->andReturn(['max' => 1, 'window' => 60]);

        $this->submit($this->form(), ['message' => 'Hello']);
        foreach ($this->transients as $key => $hits) {
            $this->transients[$key]['start'] -= 61;
        }

        $this->assertSame(1, $this->submit($this->form(), ['message' => 'Hello'])->runs);
    }

    public function testBlacklistIsDisabledByDefault(): void
    {
        $this->assertSame(1, $this->submit($this->form(), ['message' => 'Casino night'])->runs);
    }

    public function testBlacklistMatchesWholeWords(): void
    {
        \Brain\Monkey\Filters\expectApplied('coretik/forms/blacklist/enabled')->andReturn(true);

        $this->assertSame(0, $this->submit($this->form(), ['message' => 'Best casino online'])->runs);
        $this->assertSame(1, $this->submit($this->form(), ['message' => 'Cocktail chez Hitchcock'])->runs);
        $this->assertSame(0, $this->submit($this->form(), ['message' => 'ivan@yandex.ru'])->runs);
    }

    public function testBlacklistIsFilterable(): void
    {
        \Brain\Monkey\Filters\expectApplied('coretik/forms/blacklist/enabled')->andReturn(true);
        \Brain\Monkey\Filters\expectApplied('coretik/forms/blacklist')->andReturn(['crypto']);

        $this->assertSame(0, $this->submit($this->form(), ['message' => ['nested' => 'Buy CRYPTO now']])->runs);
    }

    public function testGetValueEscapesSubmittedStringsAndArrays(): void
    {
        $form = $this->form();
        $_POST = [$form->getFormName() => [
            'message' => '"><script>x</script>',
            'choices' => ['a' => '<b>', 'b' => ['c' => '"quoted"']],
        ]];

        $this->assertSame('&quot;&gt;&lt;script&gt;x&lt;/script&gt;', $form->getValue('message'));
        $this->assertSame(['a' => '&lt;b&gt;', 'b' => ['c' => '&quot;quoted&quot;']], $form->getValue('choices'));
    }

    public function testGetValueReadsNestedPaths(): void
    {
        $form = $this->form();
        $_POST = [$form->getFormName() => ['address' => ['a' => ['b' => ['c' => 'deep']]]]];

        $this->assertSame('deep', $form->getValue('address[a][b][c]'));
        $this->assertSame('none', $form->getValue('address[a][x]', 'none'));
        $this->assertSame('none', $form->getValue('missing', 'none'));
    }

    public function testDisabledBooleanConstraintIsIgnored(): void
    {
        $form = new class ('contact') extends Form {
            public int $runs = 0;

            public function getRules(): array
            {
                return ['email' => ['name' => 'Email', 'constraints' => ['email' => false, 'phone' => false]]];
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

        $this->assertSame(1, $this->submit($form, ['email' => 'not an email'])->runs);
    }
}
