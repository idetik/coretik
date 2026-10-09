<?php

/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Coretik\Core;

use ArrayAccess;
use InvalidArgumentException;
use Coretik\Core\Exception\ContainerValueNotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Pimple\Container as PimpleContainer;
use Coretik\Services\UX\Table;
use Coretik\Services\SchemaViewer\SchemaViewer;
use Coretik\Services\Templating\Wrapper as TemplateWrapper;
use Coretik\Services\Modals\Container as Modals;
use Coretik\Services\Notices\Container as Notices;
use Coretik\Services\Notices\Observers\Admin as NoticesAdminObserver;
use Coretik\Services\Notices\Observers\WPCli as NoticesWPCliObserver;
use Coretik\Services\Notices\Factory as NoticeFactory;

/**
 * Default DI container is Pimple.
 *
 * App expects a container that implements Psr\Container\ContainerInterface
 * with these service keys configured and ready for use:
 *
 *  `settings`          an array or instance of \ArrayAccess
 *  `schema`            an instance of ContainerInterface
 *  `option`            an instance of Models\Wp\Option
 *  ** an callable with the signature: function($request, $response, $exception)
 */
class Container extends PimpleContainer implements ContainerInterface
{
    /**
     * @param array $values The parameters or objects.
     */
    public function __construct(array $values = [])
    {
        parent::__construct($values);

        $defaults = [];
        $defaults['schema'] = function ($container) {
            return new Schema();
        };
        $defaults['option'] = $this->factory(function ($container) {
            return new Models\Wp\Option();
        });
        $defaults['ux.table'] = $this->factory(function ($container) {
            return new Table();
        });
        $defaults['schemaViewer'] = $this->factory(function ($container) {
            return new SchemaViewer();
        });
        $defaults['templating.wrapper'] = function ($container) {
            return new TemplateWrapper();
        };
        $defaults['modals'] = function ($container) {
            return new Modals();
        };
        $defaults['notices.container'] = function ($container) {
            $notice_container = new Notices();
            $notice_container->attach(new NoticesAdminObserver());
            $notice_container->attach(new NoticesWPCliObserver());
            return $notice_container;
        };
        $defaults['notices'] = function ($container) {
            return new NoticeFactory($container->get('notices.container'));
        };

        foreach ($defaults as $id => $value) {
            // Values given to the constructor take precedence over defaults
            if (!$this->offsetExists($id)) {
                $this[$id] = $value;
            }
        }

        $settings = ['text-domain' => 'coretik'];
        if (!$this->offsetExists('settings')) {
            $this['settings'] = $settings;
        } elseif (\is_array($this->raw('settings'))) {
            $this['settings'] = \array_merge($settings, $this->raw('settings'));
        }

        \do_action('coretik/container/construct', $this);
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return mixed
     *
     * @throws InvalidArgumentException         Thrown when an offset cannot be found in the Pimple container
     * @throws ContainerValueNotFoundException  No entry was found for this identifier.
     */
    public function get(string $id)
    {
        if (!$this->offsetExists($id)) {
            throw new ContainerValueNotFoundException(sprintf('Identifier "%s" is not defined.', $id));
        }
        try {
            return $this->offsetGet($id);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        }
    }

    /**
     * Tests whether an exception needs to be recast for compliance with psr/container.  This will be if the
     * exception was thrown by Pimple.
     *
     * @param InvalidArgumentException $exception
     *
     * @return bool
     */
    private function exceptionThrownByContainer(InvalidArgumentException $exception)
    {
        $trace = $exception->getTrace()[0];

        return $trace['class'] === PimpleContainer::class && $trace['function'] === 'offsetGet';
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return boolean
     */
    public function has(string $id): bool
    {
        return $this->offsetExists($id);
    }

    /**
     * @param string $name
     *
     * @return mixed
     *
     * @throws InvalidArgumentException         Thrown when an offset cannot be found in the Pimple container
     * @throws ContainerValueNotFoundException  No entry was found for this identifier.
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return $this->has($name);
    }
}
