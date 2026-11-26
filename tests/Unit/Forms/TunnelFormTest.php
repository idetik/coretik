<?php

namespace Coretik\Tests\Unit\Forms;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Coretik\Services\Forms\Tunnel\Step\SummaryStep;
use Coretik\Services\Forms\Tunnel\TunnelForm;
use Coretik\Tests\Unit\Forms\Fixtures\ContactStep;
use Coretik\Tests\Unit\Forms\Fixtures\NameStep;
use Coretik\Tests\Unit\Forms\Fixtures\RegistrationTunnel;

class TunnelFormTest extends JsonFormsTestCase
{
    protected function validateStep(TunnelForm $form, int $step, array $data): array
    {
        return $this->submit($form, $data, ['tunnel-action' => 'validate-step', 'step' => (string)$step]);
    }

    protected function validateTunnel(TunnelForm $form, array $data): array
    {
        return $this->submit($form, $data, ['tunnel-action' => 'validate-tunnel']);
    }

    public function testTheRulesAreTheRulesOfTheSteps(): void
    {
        $form = new RegistrationTunnel();

        $this->assertSame(['email', 'newsletter', 'name'], \array_keys($form->getRules()));
        $this->assertTrue($form->hasField('name'));
        $this->assertSame('Email', $form->fieldLabel('email'));
        $this->assertSame('Unknown field', $form->fieldLabel('unknown_field'));
    }

    public function testATunnelWithoutIdThrows(): void
    {
        $this->expectException(\LogicException::class);

        new class () extends TunnelForm {
            protected function buildSteps(): void
            {
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

    public function testStepsNavigation(): void
    {
        $form = new RegistrationTunnel();
        $contact = $form->getStep(ContactStep::class);
        $name = $form->getStep(NameStep::class);
        $summary = $form->getStep(SummaryStep::class);

        $this->assertSame([0, 1, 2], [$contact->getStepIndex(), $name->getStepIndex(), $summary->getStepIndex()]);
        $this->assertSame($name, $form->getStepAt(1));
        $this->assertNull($form->getStepAt(-1));
        $this->assertSame($contact, $form->getStepBefore($name));
        $this->assertNull($form->getStepBefore($contact));
        $this->assertSame($summary, $form->getStepAfter($name));
        $this->assertNull($form->getStepAfter($summary));
        $this->assertSame([$contact], $name->getDependencies());
        $this->assertTrue($name->isTheLastStep(), 'The summary is not counted');
        $this->assertTrue($summary->isSummary());
        $this->assertTrue($contact->isOpen());
        $this->assertFalse($name->isOpen());
    }

    public function testSummaryStepHasFrenchDefaults(): void
    {
        $summary = (new RegistrationTunnel())->getStep(SummaryStep::class);

        $this->assertSame('Récapitulatif', $summary->getTitle());
        $this->assertSame('Envoyer', $summary->getSubmitLabel());
        $this->assertNotEmpty($summary->getInstructions());
    }

    public function testThenBuildsAStepFromTheLastOne(): void
    {
        $form = new RegistrationTunnel();
        $form->then(fn ($last, $steps) => NameStep::make($form)->setTitle('After ' . $last->getTitle() . ' (' . $steps->count() . ')'));

        $this->assertSame('After Récapitulatif (3)', $form->getStepAt(3)->getTitle());
    }

    public function testValidStepAnswersItsValuesAndNotices(): void
    {
        $form = new RegistrationTunnel();
        Actions\expectDone('coretik/forms/tunnel/valid_step_submitted')->once()->with('registration', 1, \Mockery::type('array'), $form);

        $data = $this->validateStep($form, 1, ['email' => 'old@example.org']);

        $this->assertSame(0, $form->runs);
        $this->assertTrue($data['valid']);
        $this->assertSame(['Already registered'], $data['notices']);
        $this->assertSame([
            ['field' => 'email', 'label' => 'Email', 'private' => true, 'value' => 'old@example.org', 'step' => 0],
            ['field' => 'newsletter', 'label' => 'Newsletter', 'private' => false, 'value' => '', 'step' => 0],
        ], $data['values']);
    }

    public function testInvalidStepAnswersItsErrors(): void
    {
        $data = $this->validateStep(new RegistrationTunnel(), 1, ['email' => '']);

        $this->assertFalse($data['valid']);
        $this->assertSame([['field' => 'email', 'messages' => ['Ce champs est requis.'], 'step' => 0]], $data['errors']);
    }

    public function testAStepIsInvalidWhileThePreviousOnesAreInvalid(): void
    {
        $data = $this->validateStep(new RegistrationTunnel(), 2, ['email' => '', 'name' => 'Jane']);

        $this->assertFalse($data['valid']);
        $this->assertSame([['field' => 'email', 'messages' => ['Ce champs est requis.'], 'step' => 0]], $data['errors']);
    }

    public function testValidatingTheSecondStepAnswersTheValuesOfBothSteps(): void
    {
        $data = $this->validateStep(new RegistrationTunnel(), 2, ['email' => 'jane@example.org', 'name' => 'Jane']);

        $this->assertTrue($data['valid']);
        $this->assertSame(['email', 'newsletter', 'name'], \array_column($data['values'], 'field'));
        $this->assertSame([0, 0, 1], \array_column($data['values'], 'step'));
    }

    public function testUnknownStepIsAnError(): void
    {
        $data = $this->validateStep(new RegistrationTunnel(), 9, ['email' => 'jane@example.org']);

        $this->assertFalse($data['valid']);
        $this->assertSame([], $data['errors']);
    }

    public function testWithoutTunnelActionNothingRuns(): void
    {
        $form = new RegistrationTunnel();
        $data = $this->submit($form, ['email' => 'jane@example.org', 'name' => 'Jane']);

        $this->assertSame(0, $form->runs);
        $this->assertFalse($data['valid']);
    }

    public function testValidTunnelRuns(): void
    {
        $form = new RegistrationTunnel();
        Actions\expectDone('coretik/forms/tunnel/valid_form_submitted')->once();

        $data = $this->validateTunnel($form, ['email' => 'jane@example.org', 'newsletter' => '1', 'name' => 'Jane', 'injected' => 'x']);

        $this->assertSame(1, $form->runs);
        $this->assertSame(['email' => 'jane@example.org', 'newsletter' => '1', 'name' => 'Jane'], $form->ranWith);
        $this->assertSame(1, $form->getStep(NameStep::class)->successes);
        $this->assertTrue($data['valid']);
        $this->assertSame([], $data['notices']);
        $this->assertSame([
            ['title' => 'Contact', 'payload' => ['email' => 'jane@example.org', 'newsletter' => '1']],
            ['title' => 'Name', 'payload' => ['name' => 'Jane']],
        ], $form->payload());
    }

    public function testInvalidTunnelAnswersTheErrorsOfEveryStep(): void
    {
        $form = new RegistrationTunnel();

        $data = $this->validateTunnel($form, ['email' => '', 'name' => '']);

        $this->assertSame(0, $form->runs);
        $this->assertFalse($data['valid']);
        $this->assertSame(['email'], \array_column($data['errors'], 'field'), 'The second step is not validated while the first is invalid');
    }

    public function testAnExceptionOfRunIsAGlobalMessage(): void
    {
        $form = new RegistrationTunnel();
        $form->failWith = 'Camp full';

        $data = $this->validateTunnel($form, ['email' => 'jane@example.org', 'name' => 'Jane']);

        $this->assertFalse($data['valid']);
        $this->assertSame(['Camp full'], $data['messages']);
    }

    public function testSuccessTemplateIsSentOnTheFinalSubmission(): void
    {
        $form = (new RegistrationTunnel())->setSuccessTpl('<p>Done</p>');

        $data = $this->validateTunnel($form, ['email' => 'jane@example.org', 'name' => 'Jane']);

        $this->assertSame(['successTpl' => '<p>Done</p>'], $data['data']);
    }

    public function testRateLimitOnlyCountsTheFinalSubmissions(): void
    {
        Filters\expectApplied('coretik/forms/rate_limit')->andReturn(['max' => 1]);

        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($this->validateStep(new RegistrationTunnel(), 1, ['email' => 'jane@example.org'])['valid']);
        }
        $this->assertTrue($this->validateTunnel(new RegistrationTunnel(), ['email' => 'jane@example.org', 'name' => 'Jane'])['valid']);

        $data = $this->validateTunnel(new RegistrationTunnel(), ['email' => 'jane@example.org', 'name' => 'Jane']);
        $this->assertFalse($data['valid']);
        $this->assertSame(['Trop de tentatives, veuillez réessayer dans quelques minutes.'], $data['messages']);
    }

    public function testWrongNonceDoesNotRun(): void
    {
        $form = new RegistrationTunnel();
        $_POST = [
            $form->getFormName() => ['email' => 'jane@example.org', 'name' => 'Jane', 'form_id' => 'registration', 'nonce' => 'forged'],
            'tunnel-action' => 'validate-tunnel',
        ];

        try {
            $form->process();
            $this->fail('No JSON answer sent');
        } catch (Fixtures\JsonSent $sent) {
            $this->assertFalse($sent->data()['valid']);
        }
        $this->assertSame(0, $form->runs);
    }

    public function testCloneStartsFromBlankSteps(): void
    {
        $form = new RegistrationTunnel();
        $form->setCurrentStep(2)->setSuccessTpl('<p>Done</p>');
        $this->validateStep($form, 1, ['email' => 'jane@example.org']);
        $this->assertTrue($form->getStep(ContactStep::class)->isValid());

        $clone = clone $form;

        $this->assertFalse($clone->getStep(ContactStep::class)->isValid());
        $this->assertNotSame($form->getStep(ContactStep::class), $clone->getStep(ContactStep::class));
        $this->assertSame($clone, $clone->getStep(ContactStep::class)->getTunnel());
    }

    public function testRgpdDataCategoriesOfTheSteps(): void
    {
        $this->assertSame(
            ['contact_data' => ['Email'], 'identity' => ['Name']],
            (new RegistrationTunnel())->getRgpdDataCategories()
        );
        $this->assertTrue((new RegistrationTunnel())->getStep(ContactStep::class)->fieldContainsPrivateData('email'));
        $this->assertFalse((new RegistrationTunnel())->getStep(ContactStep::class)->fieldContainsPrivateData('newsletter'));
    }
}
