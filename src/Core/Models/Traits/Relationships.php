<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Exceptions\CannotResolveException;
use Coretik\Core\Exception\ContainerValueNotFoundException;
use Coretik\Core\Exception\UnhandledException;
use Coretik\Core\Collection;
use Coretik\Core\Models\Wp\PostModel;
use Coretik\Core\Models\Wp\TermModel;
use Coretik\Core\Models\Wp\UserModel;
use Coretik\Core\Models\Wp\CommentModel;

trait Relationships
{
    protected function belongsTo(string|BuilderInterface $builder): ?ModelInterface
    {
        $builder = $this->resolveBuilder($builder);
        try {
            switch (true) {
                case $this instanceof PostModel:
                    // Support post, taxonomy, user
                    return match ($builder->getType()) {
                        'post' => !empty($parent_id = $this->parentId()) ? $builder->model($parent_id) : null,
                        'taxonomy' => $this->term($builder->getName()),
                        'user' => $builder->model((int)$this->post_author),
                        default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'PostModel', $builder->getType()))
                    };
    
                case $this instanceof TermModel:
                    // Support taxonomy
                    return match ($builder->getType()) {
                        'taxonomy' => !empty($parent_id = $this->parentId()) ? $builder->model($parent_id) : null,
                        default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'PostModel', $builder->getType()))
                    };
    
                case $this instanceof UserModel:
                    // Support none
                    return match ($builder->getType()) {
                        default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'UserModel', $builder->getType()))
                    };
    
                case $this instanceof CommentModel:
                    // Support comment, user, post
                    return match ($builder->getType()) {
                        'comment' => !empty($parent_id = $this->parentId()) ? $builder->model($parent_id) : null,
                        'post' => $builder->model($this->comment_post_ID),
                        'user' => !empty($this->user_id) ? $builder->model($this->user_id) : null,
                        default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'CommentModel', $builder->getType()))
                    };
    
                default:
                    return $builder->model($parent_id);
            }
        } catch (CannotResolveException $e) {
            return null;
        }
    }

    protected function hasMany(string|BuilderInterface $builder): Collection
    {
        $builder = $this->resolveBuilder($builder);

        switch (true) {
            case $this instanceof PostModel:
                // Support post, taxonomy, comment
                return match ($builder->getType()) {
                    'post' => $builder->query()->childOf($this->id())->all()->collection(),
                    'taxonomy' => $this->terms($builder->getName()),
                    'comment' => $builder->query()->set('post_id', $this->id())->all()->collection(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'PostModel', $builder->getType()))
                };

            case $this instanceof TermModel:
                // Support post, taxonomy
                return match ($builder->getType()) {
                    'post' => $builder->query()->whereTax($this->taxonomy, [$this->id()])->all()->collection(),
                    'taxonomy' => $builder->query()->childOf($this->id())->all()->collection(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'TermModel', $builder->getType()))
                };

            case $this instanceof UserModel:
                // Support post, comment
                return match ($builder->getType()) {
                    'post' => $builder->query()->set('author', $this->id())->all()->collection(),
                    'comment' => $builder->query()->set('user_id', $this->id())->all()->collection(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'UserModel', $builder->getType()))
                };

            case $this instanceof CommentModel:
                // Support comment
                return match ($builder->getType()) {
                    'comment' => $builder->query()->childOf($this->id())->all()->collection(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'CommentModel', $builder->getType()))
                };

            default:
                return $builder->query()->childOf($this->id())->all()->collection();
        }
    }

    protected function hasOne(string|BuilderInterface $builder): ?ModelInterface
    {
        $builder = $this->resolveBuilder($builder);

        switch (true) {
            case $this instanceof PostModel:
                // Support PostModel, TermModel, Comment model
                return match ($builder->getType()) {
                    'post' => $builder->query()->childOf($this->id())->limit(1)->first(),
                    'taxonomy' => $this->term($builder->getName()),
                    'comment' => $builder->query()->set('post_id', $this->id())->limit(1)->first(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'PostModel', $builder->getType()))
                };

            case $this instanceof TermModel:
                // Support post, taxonomy
                return match ($builder->getType()) {
                    'post' => $builder->query()->whereTax($this->taxonomy, [$this->id()])->limit(1)->first(),
                    'taxonomy' => $builder->query()->childOf($this->id())->limit(1)->first(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'PostModel', $builder->getType()))
                };

            case $this instanceof UserModel:
                // Support post, comment
                return match ($builder->getType()) {
                    'post' => $builder->query()->set('author', $this->id())->limit(1)->first(),
                    'comment' => $builder->query()->set('user_id', $this->id())->limit(1)->first(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'UserModel', $builder->getType()))
                };

            case $this instanceof CommentModel:
                // Support comment, user, post
                return match ($builder->getType()) {
                    'comment' => $builder->query()->childOf($this->id())->limit(1)->first(),
                    'user' => !empty($this->user_id) ? $builder->model($this->user_id) : null,
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'CommentModel', $builder->getType()))
                };

            default:
                return $builder->query()->childOf($this->id())->limit(1)->first();
        }
    }

    protected function resolveBuilder(string|BuilderInterface $builder): BuilderInterface
    {
        if ($builder instanceof BuilderInterface) {
            return $builder;
        }

        if (!empty(($object = app()->schema()->get($builder)))) {
            return $object;
        }

        throw new ContainerValueNotFoundException;
    }
}
