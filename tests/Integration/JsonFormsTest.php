<?php

namespace Coretik\Tests\Integration;

use Coretik\Services\Forms\Core\Handler;
use Coretik\Tests\Integration\Fixtures\Forms\ContactJsonForm;
use Coretik\Tests\Integration\Fixtures\Forms\SignupTunnel;

/**
 * JSON forms and tunnels in WordPress: AJAX actions of the lazy forms, nonces, default views, fetcher.
 * Fixtures: tests/Integration/Fixtures/Forms, registered in tests/Integration/bootstrap.php.
 */
class JsonFormsTest extends IntegrationTestCase
{
    public function tear_down(): void
    {
        $_POST = $_REQUEST = [];
        parent::tear_down();
    }

    /**
     * Runs a handler answering with wp_send_json() as admin-ajax.php does: stopped at wp_die(), the JSON printed is returned.
     */
    protected function captureJson(callable $handler): array
    {
        $die = fn () => function ($message) {
            throw new \RuntimeException(\is_scalar($message) ? (string)$message : '', 4242);
        };
        \add_filter('wp_doing_ajax', '__return_true');
        \add_filter('wp_die_ajax_handler', $die, 1);

        $level = \ob_get_level();
        \ob_start();
        try {
            $handler();
        } catch (\RuntimeException $e) {
            if (4242 !== $e->getCode()) {
                throw $e;
            }
        } finally {
            $output = '';
            while (\ob_get_level() > $level) {
                $output = \ob_get_clean() . $output;
            }
            \remove_filter('wp_doing_ajax', '__return_true');
            \remove_filter('wp_die_ajax_handler', $die, 1);
        }

        $json = \json_decode($output, true);
        $this->assertIsArray($json, 'No JSON answer: ' . $output);
        return $json;
    }

    /**
     * Posts to the AJAX action of a form, with a valid nonce
     */
    protected function post(string $formId, array $values, array $post = []): array
    {
        $form = \coretik()->forms()->get($formId);
        $_POST = $_REQUEST = \array_merge([
            'action' => 'form-' . $formId,
            $form->getFormName() => $values + [
                'form_id' => $formId,
                'nonce' => \wp_create_nonce('coretik_form_submit_' . $formId),
            ],
        ], $post);

        return $this->captureJson(fn () => Handler::handleAsyncRequest($form));
    }

    public function testLazyFormsListenToTheirAjaxAction(): void
    {
        $this->assertTrue(\coretik()->forms()->has('signup'));
        $this->assertFalse(\coretik()->forms()->isLoaded('signup'));

        $_REQUEST = ['action' => 'form-contact-json'];
        \coretik()->forms()->init();
        $this->assertNotFalse(\has_action('wp_ajax_nopriv_form-contact-json'));

        $_POST = $_REQUEST = [
            'action' => 'form-contact-json',
            'coretik-form-contact-json' => [
                'message' => 'Hello',
                'form_id' => 'contact-json',
                'nonce' => \wp_create_nonce('coretik_form_submit_contact-json'),
            ],
        ];
        $json = $this->captureJson(fn () => \do_action('wp_ajax_nopriv_form-contact-json'));

        $this->assertTrue($json['data']['valid']);
        $this->assertSame(['Hello'], \get_option('contact_messages'));
    }

    public function testJsonFormAnswersTheErrorsPerField(): void
    {
        $json = $this->post(ContactJsonForm::ID, ['message' => '']);

        $this->assertTrue($json['success']);
        $this->assertFalse($json['data']['valid']);
        $this->assertSame('message', $json['data']['errors'][0]['field']);
        $this->assertFalse(\get_option('contact_messages'));
    }

    public function testJsonFormWithForgedNonceDoesNotRun(): void
    {
        $form = \coretik()->forms()->get(ContactJsonForm::ID);
        $_POST = $_REQUEST = [$form->getFormName() => ['message' => 'Hello', 'form_id' => 'contact-json', 'nonce' => 'forged']];

        $json = $this->captureJson(fn () => Handler::handleAsyncRequest($form));

        $this->assertFalse($json['data']['valid']);
        $this->assertNotEmpty($json['data']['messages']);
        $this->assertFalse(\get_option('contact_messages'));
    }

    public function testTunnelValidatesAStepThenTheWholeTunnel(): void
    {
        $step = $this->post(SignupTunnel::ID, ['email' => 'jane@example.org'], ['tunnel-action' => 'validate-step', 'step' => '1']);
        $this->assertTrue($step['data']['valid']);
        $this->assertSame([['field' => 'email', 'label' => 'Email', 'private' => false, 'value' => 'jane@example.org', 'step' => 0]], $step['data']['values']);
        $this->assertFalse(\get_option('signups'), 'A step validation does not run the tunnel');

        $invalid = $this->post(SignupTunnel::ID, ['email' => 'not an email'], ['tunnel-action' => 'validate-tunnel']);
        $this->assertFalse($invalid['data']['valid']);
        $this->assertSame([0], \array_column($invalid['data']['errors'], 'step'));

        $final = $this->post(SignupTunnel::ID, ['email' => 'jane@example.org'], ['tunnel-action' => 'validate-tunnel']);
        $this->assertTrue($final['data']['valid']);
        $this->assertSame([['email' => 'jane@example.org']], \get_option('signups'));
    }

    public function testTunnelIsRenderedByTheDefaultViews(): void
    {
        $html = \coretik()->forms()->get(SignupTunnel::ID)->view([], true);

        $this->assertStringContainsString('data-form-tunnel', $html);
        $this->assertStringContainsString('data-form-id="signup"', $html);
        $this->assertStringContainsString('data-ajax-action="form-signup"', $html);
        $this->assertStringContainsString('data-current-step="1"', $html);
        $this->assertStringContainsString('name="coretik-form-signup[nonce]"', $html);
        $this->assertStringContainsString('name="form_coretik_confirm"', $html, 'Default honeypot');
        $this->assertSame(2, \preg_match_all('/<fieldset[^>]*\sdata-form-step\s/', $html), 'One fieldset per step');
        $this->assertStringContainsString('data-form-step-summary', $html);
        $this->assertStringContainsString('data-form-navigation', $html);
        $this->assertStringContainsString('data-summary-step="1"', $html);
        $this->assertStringContainsString('data-next-step', $html);
        $this->assertStringContainsString('Your <strong>email</strong>', $html);
        $this->assertStringContainsString('data-success-message="Votre demande a bien été enregistrée."', $html);
        $this->assertStringEndsWith('</form>', \trim($html));

        $document = new \DOMDocument();
        $this->assertTrue(@$document->loadHTML('<?xml encoding="utf-8"?>' . $html), 'Valid HTML');
        $this->assertSame(1, $document->getElementsByTagName('form')->length);
    }

    public function testJsonFormOpeningPartPrintsTheJsonContainers(): void
    {
        $html = \coretik()->forms()->get(ContactJsonForm::ID)->view([], true);

        $this->assertStringContainsString(' data-form ', \preg_replace('/\s+/', ' ', $html));
        $this->assertStringNotContainsString('data-form-tunnel', $html);
        $this->assertStringContainsString('data-form-errors', $html);
        $this->assertStringContainsString('data-form-notices', $html);
        $this->assertStringContainsString('data-success-message="Votre demande a bien été envoyée."', $html);
        $this->assertStringContainsString('</form>', $html);
    }

    public function testFetcherRendersOnlyTheAllowedForms(): void
    {
        \coretik()->get('forms.fetcher')->listen();

        $_POST = $_REQUEST = ['action' => 'fetch_form', 'formId' => 'signup'];
        $json = $this->captureJson(fn () => \do_action('wp_ajax_nopriv_fetch_form'));
        $this->assertTrue($json['success']);
        $this->assertStringContainsString('data-form-tunnel', $json['data']['form']);
        $this->assertArrayHasKey('modals', $json['data']);

        $_POST = $_REQUEST = ['action' => 'fetch_form', 'formId' => 'contact-json'];
        $json = $this->captureJson(fn () => \do_action('wp_ajax_nopriv_fetch_form'));
        $this->assertFalse($json['success']);
        $this->assertSame('Form not found', $json['data']);
    }
}
