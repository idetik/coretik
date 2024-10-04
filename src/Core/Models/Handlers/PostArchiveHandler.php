<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Handler;
use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;

class PostArchiveHandler extends Handler
{
    public function handle(BuilderInterface $builder): void
    {
        if (!$builder instanceof ModelableInterface) {
            throw new \Exception('Builder doesn\'t implement ModelableInterface');
        }
        parent::handle($builder);
    }

    public function actions(): void
    {
        \add_action('pre_get_posts', [$this, 'setArchiveQuery']);
    }

    public function freeze(): void
    {
        \remove_action('pre_get_posts', [$this, 'setArchiveQuery']);
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

        $vars = $this->builder->query()->getQueryArgsDefault();
        foreach ($vars as $key => $val) {
            $query->set($key, $val);
        }
        return true;
    }
}
