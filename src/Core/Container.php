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

        $this['schema'] = function ($container) {
            return new Schema();
        };
        $this['option'] = $this->factory(function ($container) {
            return new Models\Wp\Option();
        });
        $this['ux.table'] = $this->factory(function ($container) {
            return new Table();
        });
        $this['schemaViewer'] = $this->factory(function ($container) {
            return new SchemaViewer();
        });
        $this['templating.wrapper'] = function ($container) {
            return new TemplateWrapper();
        };
        $this['modals'] = function ($container) {
            return new Modals();
        };
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
