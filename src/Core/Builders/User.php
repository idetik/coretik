<?php

/**
 * Todo:
 * 1/ Remove all
 * 2/ Iterate UserTypes Model & save
 * 3/ Set cache buster
 */

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\RegistrableInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;
use Coretik\Core\Collection;
use Coretik\Core\Builders\Users\Registry;
use Coretik\Core\Query\User as Query;

final class User extends BuilderModelable
{
    protected $name = 'users';

    public function __construct()
    {
        parent::__construct();

        $this->querier(function ($mediator) {
            return new Query($mediator);
        });
    }

    public function __sleep()
    {
        return ['name', 'label', 'caps'];
    }

    public function getType(): string
    {
        return 'user';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function wpObject(int $id)
    {
        return \get_user_by('id', $id);
    }

    public function concern(int $objectId): bool
    {
        return (bool) $this->wpObject($objectId);
    }
}
