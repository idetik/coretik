<?php

namespace Coretik\Core\Models\Handlers\Relationship;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\HandlerInterface;
use Coretik\Core\Models\Interfaces\ModelInterface;

class BelongsToHandler implements HandlerInterface
{
    private $builder;
    private $belongsToBuilder;
    private $pivot;

    public function __construct(string|callable $pivot, null|string|BuilderInterface|ModelInterface $belongsTo = null)
    {
        $this->setPivot($pivot);
        if (!empty($belongsTo)) {
            $this->setBelongsToBuilder($belongsTo);
        }
    }

    public static function withPivot(string|callable $pivot): self
    {
        return new static($pivot);
    }

    public function setBelongsToBuilder(string|BuilderInterface|ModelInterface $belongsTo): self
    {
        $this->belongsToBuilder = app()->schema()->resolve($belongsTo);
        return $this;
    }

    /**
     * Set the metakey contains the stranger parent id or callable that return stranger parent id
     * @param string|callable $pivot
     */
    public function setPivot(string|callable $pivot): self
    {
        $this->pivot = $pivot;
        return $this;
    }

    public function handle(BuilderInterface $builder): void
    {
        $this->builder = $builder;

        if (empty($this->pivot)) {
            return;
        }

        if (\is_string($this->pivot)) {
            \add_action('added_post_meta', [$this, 'mountMetaRelationship'], 10, 4);
            \add_action('updated_post_meta', [$this, 'mountMetaRelationship'], 10, 4);
        } else {
            \add_action('acf/save_post', [$this, 'mountPivotRelationship'], 10);
        }
    }

    public function freeze(): void
    {
        \remove_action('acf/save_post', [$this, 'mountPivotRelationship'], 10);
        \remove_action('updated_post_meta', [$this, 'mountMetaRelationship'], 10);
        \remove_action('added_post_meta', [$this, 'mountMetaRelationship'], 10);
    }

    public function mountMetaRelationship($meta_id, $post_id, $meta_key, $_meta_value)
    {
        if (!$this->builder->concern((int)$post_id)) {
            return;
        }

        if ($meta_key !== $this->pivot) {
            return;
        }

        $model = $this->builder->model((int)$post_id, null, true);
        $belongsToId = (int)$_meta_value;
        $this->mountRelationship($belongsToId, $model);
    }

    public function mountPivotRelationship($post_id)
    {
        if (!$this->builder->concern((int)$post_id)) {
            return;
        }

        $model = $this->builder->model((int)$post_id, null, true);
        $belongsToId = call_user_func($this->pivot, $model);
        $this->mountRelationship($belongsToId, $model);
    }

    protected function mountRelationship($belongsToId, $model)
    {
        if (empty($belongsToId) || !is_int($belongsToId)) {
            return;
        }

        if (!empty($this->belongsToBuilder)) {
            if (!$this->belongsToBuilder->concern($belongsToId)) {
                return;
            }
        }

        $model->setParentId(
            $belongsToId
        );

        $this->freeze();
        $model->save();
        $this->handle($this->builder);
    }
}
