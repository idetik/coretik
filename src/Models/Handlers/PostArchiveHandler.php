<?php

namespace Coretik\Models\Handlers;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;

class PostArchiveHandler implements HandlerInterface
{
    private $builder;

    public function handle(BuilderInterface $builder): void
    {
        if (!$builder instanceof ModelableInterface) {
            throw new \Exception('Builder doesn\'t implement ModelableInterface');
        }
        $this->builder = $builder;
        // \add_action('pre_get_posts', [$this, 'setArchiveQuery']);
    }

    public function freeze(): void
    {
    }

    public function setArchiveQuery($query)
    {
        if (!\is_post_type_archive($this->builder->getName())) {
            return false;
        }

        if (\is_admin()) {
            return false;
        }

        if (!$query->is_main_query()) {
            return false;
        }
        
        $vars = $this->builder->newQuery()::defaultArgs();
        foreach ($vars as $key => $val) {
            $query->set($key, $val);
        }
        return true;
    }
}
