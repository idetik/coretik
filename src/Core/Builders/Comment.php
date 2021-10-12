<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\RegistrableInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;
use Coretik\Core\Collection;
use Coretik\Core\Query\Comment as Query;

final class Comment extends BuilderModelable
{
    protected $name = 'comments';

    public function __construct()
    {
        parent::__construct();

        $this->querier(function ($mediator) {
            return new Query($mediator);
        });
    }

    public function getType(): string
    {
        return 'comment';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function wpObject(int $id)
    {
        return \get_comment($id);
    }

    public function concern(int $objectId): bool
    {
        return (bool) $this->wpObject($objectId);
    }
}
