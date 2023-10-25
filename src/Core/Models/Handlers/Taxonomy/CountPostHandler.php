<?php

namespace Coretik\Core\Models\Handlers\Taxonomy;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;

class CountPostHandler implements HandlerInterface
{
    private $builder;
    protected $statuses;

    public function __construct(array $statuses = ['publish'])
    {
        $this->statuses = $statuses;
    }

    public static function withStatuses(array $statuses): self
    {
        return new static($statuses);
    }

    /**
     * set update_post_term_count_statuses hook
     */
    public function handle(BuilderInterface $builder): void
    {
        $this->builder = $builder;
        \add_filter('update_post_term_count_statuses', [$this, 'statuses'], 10, 2);
    }

    public function freeze(): void
    {
        \remove_filter('update_post_term_count_statuses', [$this, 'statuses'], 10);
    }

    public function statuses($statuses, $taxonomy)
    {
        if ($taxonomy->name !== $this->builder->getName()) {
            return $statuses;
        }

        return $this->statuses;
    }
}
