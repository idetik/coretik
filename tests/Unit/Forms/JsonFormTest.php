<?php

namespace Coretik\Tests\Unit\Forms;

use Coretik\Services\Forms\JsonForm;
use Coretik\Tests\Unit\Forms\Fixtures\NewsletterForm;

class JsonFormTest extends JsonFormsTestCase
{
    public function testTheIdComesFromTheIdConstant(): void
    {
        $this->assertSame('newsletter', (new NewsletterForm())->id());
        $this->assertSame('other', (new NewsletterForm('other'))->id());
    }

    public function testAFormWithoutIdThrows(): void
    {
        $this->expectException(\LogicException::class);

        new class () extends JsonForm {
            public function getRules(): array
            {
                return [];
            }

            protected function isValidContext(): bool
            {
                return true;
            }

            protected function run(): void
            {
            }
        };
    }

    public function testValidSubmissionAnswersTheValues(): void
    {
        $form = new NewsletterForm();

        $data = $this->submit($form, ['email' => 'jane@example.org', 'topic' => '<b>News</b>']);

        $this->assertSame(1, $form->runs);
        $this->assertTrue($data['valid']);
        $this->assertSame([
            ['field' => 'email', 'label' => 'Email', 'private' => true, 'value' => 'jane@example.org'],
            ['field' => 'topic', 'label' => 'Topic', 'private' => false, 'value' => 'News'],
        ], $data['values']);
        $this->assertSame([], $data['data']);
    }

    public function testSuccessTemplateIsSent(): void
    {
        $form = (new NewsletterForm())->setSuccessTpl('<p>Thanks</p>');

        $data = $this->submit($form, ['email' => 'jane@example.org']);

        $this->assertSame(['successTpl' => '<p>Thanks</p>'], $data['data']);
    }

    public function testValidationErrorsAreBoundToTheFields(): void
    {
        $form = new NewsletterForm();

        $data = $this->submit($form, ['email' => '']);

        $this->assertSame(0, $form->runs);
        $this->assertFalse($data['valid']);
        $this->assertSame([['field' => 'email', 'messages' => ['Ce champs est requis.']]], $data['errors']);
        $this->assertSame(['Ce formulaire contient des erreurs, veuillez vérifier votre saisie.'], $data['messages']);
    }

    public function testAnExceptionOfRunIsAGlobalMessage(): void
    {
        $form = new NewsletterForm();
        $form->failWith = 'Service unavailable';

        $data = $this->submit($form, ['email' => 'jane@example.org']);

        $this->assertFalse($data['valid']);
        $this->assertSame([], $data['errors']);
        $this->assertSame(['Service unavailable'], $data['messages']);
    }

    public function testAnExpiredNonceAsksToReload(): void
    {
        $form = new NewsletterForm();

        $data = $this->submit($form, ['email' => 'jane@example.org'], [], 'expired');

        $this->assertSame(0, $form->runs);
        $this->assertFalse($data['valid']);
        $this->assertSame(['Votre session a expiré, veuillez recharger la page puis réessayer.'], $data['messages']);
    }

    public function testSpamDoesNotRun(): void
    {
        $form = new NewsletterForm();

        $data = $this->submit($form, ['email' => 'jane@example.org'], ['form_coretik_confirm' => 'on']);

        $this->assertSame(0, $form->runs);
        $this->assertFalse($data['valid']);
    }

    public function testRateLimitedSubmissionsGetAMessage(): void
    {
        \Brain\Monkey\Filters\expectApplied('coretik/forms/rate_limit')->andReturn(['max' => 1]);

        $this->submit(new NewsletterForm(), ['email' => 'jane@example.org']);
        $form = new NewsletterForm();
        $data = $this->submit($form, ['email' => 'jane@example.org']);

        $this->assertSame(0, $form->runs);
        $this->assertSame(['Trop de tentatives, veuillez réessayer dans quelques minutes.'], $data['messages']);
    }

    public function testRedirectAnswersTheUrl(): void
    {
        $form = new NewsletterForm();
        $form->redirect = 'https://example.org/thanks/';

        $data = $this->submit($form, ['email' => 'jane@example.org']);

        $this->assertTrue($data['valid']);
        $this->assertSame('https://example.org/thanks/', $data['redirect_url']);
    }

    public function testSuccessMessageHasAFrenchDefault(): void
    {
        $form = new NewsletterForm();
        $this->assertSame('Votre demande a bien été envoyée.', $form->successMessage());
        $this->assertSame('Subscribed', $form->setSuccessMessage('Subscribed')->successMessage());
    }

    public function testUniqidIsStableForTheForm(): void
    {
        $form = new NewsletterForm();
        $this->assertStringStartsWith('form-newsletter-', $form->uniqid());
        $this->assertSame($form->uniqid(), $form->uniqid());
        $this->assertNotSame($form->uniqid(), (new NewsletterForm())->uniqid());
    }
}
