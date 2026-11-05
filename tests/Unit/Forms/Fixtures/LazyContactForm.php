<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

use Coretik\Services\Forms\Core\Asyncable;
use Coretik\Services\Forms\Core\ConfigInterface;
use Coretik\Services\Forms\Core\Handlable;

/**
 * Lazy form fixture: counts how many times it is built and how many requests it handles.
 */
class LazyContactForm implements Asyncable
{
    const ID = 'lazy-contact';

    public static int $built = 0;
    public static int $runnableChecks = 0;

    public function __construct()
    {
        static::$built++;
    }

    public function id()
    {
        return static::ID;
    }

    public function isRunnable()
    {
        static::$runnableChecks++;
        return false; // handleAsyncRequest() would exit()
    }

    public function process()
    {
    }

    public function getRules(): array
    {
        return [];
    }

    public function setConfigIfNotDefined(ConfigInterface $config): Handlable
    {
        return $this;
    }

    public function public(): bool
    {
        return true;
    }

    public function endpoint(): string
    {
        return '/wp-admin/admin-ajax.php';
    }

    public function wpAjaxAction(): string
    {
        return 'form-' . $this->id();
    }

    public function view($data = [], bool $return = false)
    {
    }
}
