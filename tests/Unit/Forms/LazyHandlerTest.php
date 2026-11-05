<?php

namespace Coretik\Tests\Unit\Forms;

use Brain\Monkey\Functions;
use Coretik\Services\Forms\Core\ConfigInterface;
use Coretik\Services\Forms\Core\Handler;
use Coretik\Tests\TestCase;
use Coretik\Tests\Unit\Forms\Fixtures\LazyContactForm;
use Coretik\Tests\Unit\Forms\Fixtures\LazyCustomActionForm;
use Coretik\Tests\Unit\Forms\Fixtures\LazyPrivateForm;
use Coretik\Tests\Unit\Forms\Fixtures\LazyUndeclaredActionForm;
use Coretik\Tests\Unit\Forms\Fixtures\LazyWrongIdForm;
use Coretik\Tests\Unit\Forms\Fixtures\NoIdForm;

class LazyHandlerTest extends TestCase
{
    /** @var array<string, callable[]> */
    private array $actions = [];

    protected function setUp(): void
    {
        parent::setUp();
        LazyContactForm::$built = 0;
        LazyContactForm::$runnableChecks = 0;
        $this->actions = [];
        Functions\when('add_action')->alias(function ($hook, $callback) {
            $this->actions[$hook][] = $callback;
            return true;
        });
        $_REQUEST = ['action' => 'anything'];
    }

    protected function tearDown(): void
    {
        $_REQUEST = [];
        parent::tearDown();
    }

    private function handler(): Handler
    {
        return new Handler(\Mockery::mock(ConfigInterface::class));
    }

    private function fire(string $hook): void
    {
        foreach ($this->actions[$hook] ?? [] as $callback) {
            $callback();
        }
    }

    public function testAClassNameIsNotBuiltOnRegistration(): void
    {
        $handler = $this->handler();
        $handler->factory(LazyContactForm::class);

        $this->assertTrue($handler->has('lazy-contact'));
        $this->assertFalse($handler->isLoaded('lazy-contact'));
        $this->assertSame(0, LazyContactForm::$built);
    }

    public function testInitHooksTheAjaxActionWithoutBuildingTheForm(): void
    {
        $handler = $this->handler();
        $handler->factory(LazyContactForm::class);

        $handler->init();

        $this->assertArrayHasKey('wp_ajax_form-lazy-contact', $this->actions);
        $this->assertArrayHasKey('wp_ajax_nopriv_form-lazy-contact', $this->actions);
        $this->assertSame(0, LazyContactForm::$built);
    }

    public function testTheFormIsBuiltWhenItsActionIsRequested(): void
    {
        $handler = $this->handler();
        $handler->factory(LazyContactForm::class);
        $handler->init();

        $this->fire('wp_ajax_nopriv_form-lazy-contact');

        $this->assertSame(1, LazyContactForm::$built);
        $this->assertSame(1, LazyContactForm::$runnableChecks, 'The request must be handled');
    }

    public function testAFactoryIsBuiltOnceThenCloned(): void
    {
        $handler = $this->handler();
        $handler->factory(LazyContactForm::class);

        $first = $handler->get('lazy-contact');
        $second = $handler->get('lazy-contact');

        $this->assertNotSame($first, $second);
        $this->assertSame(1, LazyContactForm::$built);
        $this->assertTrue($handler->isLoaded('lazy-contact'));
    }

    public function testASingletonIsShared(): void
    {
        $handler = $this->handler();
        $handler->attach(LazyContactForm::class);

        $this->assertSame($handler->get('lazy-contact'), $handler->get('lazy-contact'));
        $this->assertSame(1, LazyContactForm::$built);
    }

    public function testAPrivateFormIgnoresVisitors(): void
    {
        LazyPrivateForm::$runnableChecks = 0;
        $handler = $this->handler();
        $handler->factory(LazyPrivateForm::class);
        $handler->init();

        $this->fire('wp_ajax_nopriv_form-lazy-private');
        $this->assertSame(0, LazyPrivateForm::$runnableChecks);

        $this->fire('wp_ajax_form-lazy-private');
        $this->assertSame(1, LazyPrivateForm::$runnableChecks);
    }

    public function testACustomAjaxActionIsDeclaredByAConstant(): void
    {
        $handler = $this->handler();
        $handler->factory(LazyCustomActionForm::class);
        $handler->init();

        $this->assertArrayHasKey('wp_ajax_nopriv_custom-action', $this->actions);
        $this->assertArrayNotHasKey('wp_ajax_nopriv_form-lazy-custom', $this->actions);
    }

    public function testAnUndeclaredCustomAjaxActionIsReported(): void
    {
        Functions\expect('_doing_it_wrong')->once();
        LazyUndeclaredActionForm::$runnableChecks = 0;

        $handler = $this->handler();
        $handler->factory(LazyUndeclaredActionForm::class);
        $handler->init();
        $this->fire('wp_ajax_nopriv_form-lazy-undeclared');

        $this->assertSame(0, LazyUndeclaredActionForm::$runnableChecks);
    }

    public function testAMismatchingIdConstantIsRejected(): void
    {
        $handler = $this->handler();
        $handler->factory(LazyWrongIdForm::class);

        $this->expectException(\LogicException::class);
        $handler->get('declared-id');
    }

    public function testAClassWithoutIdConstantIsBuiltRightAway(): void
    {
        NoIdForm::$built = 0;
        $handler = $this->handler();
        $handler->factory(NoIdForm::class);

        $this->assertSame(1, NoIdForm::$built);
        $this->assertTrue($handler->has('no-id'));
    }

    public function testAnInstanceIsStillCloned(): void
    {
        $handler = $this->handler();
        $form = new LazyContactForm();
        $handler->factory($form);

        $this->assertNotSame($form, $handler->get('lazy-contact'));
        $this->assertTrue($handler->isLoaded('lazy-contact'));
    }

    public function testAnUnknownFormThrowsAClearException(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->handler()->get('nope');
    }

    public function testANonFormClassIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->handler()->factory(\stdClass::class);
    }

    public function testNothingIsHookedWithoutRequestData(): void
    {
        $_REQUEST = [];
        $handler = $this->handler();
        $handler->factory(LazyContactForm::class);

        $handler->init();

        $this->assertSame([], \array_filter(\array_keys($this->actions), fn ($hook) => \str_starts_with($hook, 'wp_ajax')));
    }
}
