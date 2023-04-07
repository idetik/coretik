<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Exceptions\CannotResolveException;
use Coretik\Core\Exception\ContainerValueNotFoundException;
use Coretik\Core\Exception\UnhandledException;
use Coretik\Core\Collection;
use Illuminate\Support\LazyCollection;
use Coretik\Core\Models\Wp\PostModel;
use Coretik\Core\Models\Wp\TermModel;
use Coretik\Core\Models\Wp\UserModel;
use Coretik\Core\Models\Wp\CommentModel;
use Coretik\Core\Query\Interfaces\QuerierInterface;

trait Relationships
{
    /**
     * @todo
     * return Relationship Object
     * with attach|save|associate method to set relation
     */
    protected function belongsTo(string|BuilderInterface|ModelInterface $builder): ?ModelInterface
    {
        $builder = $this->resolveBuilder($builder);
        try {
            switch (true) {
                case $this instanceof PostModel:
                    // Support post, taxonomy, user
                    return match ($builder->getType()) {
                        'post' => !empty($parent_id = $this->parentId()) ? $builder->model($parent_id) : null,
                        'taxonomy' => $this->term($builder->getName()),
                        'user' => $builder->concern((int)$this->post_author) ? $builder->model((int)$this->post_author) : null,
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
                        'post' => $builder->concern($this->comment_post_ID) ? $builder->model($this->comment_post_ID) : null,
                        'user' => !empty($this->user_id) && $builder->concern((int)$this->user_id) ? $builder->model($this->user_id) : null,
                        default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'CommentModel', $builder->getType()))
                    };

                default:
                    return $builder->model($parent_id);
            }
        } catch (CannotResolveException $e) {
            return null;
        }
    }

    protected function hasMany(string|BuilderInterface|ModelInterface $builder, bool $collect = true): Collection|QuerierInterface
    {
        $builder = $this->resolveBuilder($builder);

        $getter = $collect ? 'collection' : 'querier';

        switch (true) {
            case $this instanceof PostModel:
                // Support post, taxonomy, comment
                return match ($builder->getType()) {
                    'post' => $builder->query()->childOf($this->id())->all()->$getter(),
                    'taxonomy' => $this->terms($builder->getName()),
                    'comment' => $builder->query()->set('post_id', $this->id())->all()->$getter(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'PostModel', $builder->getType()))
                };

            case $this instanceof TermModel:
                // Support post, taxonomy
                return match ($builder->getType()) {
                    'post' => $builder->query()->whereTax($this->taxonomy, [$this->id()])->all()->$getter(),
                    'taxonomy' => $builder->query()->childOf($this->id())->all()->$getter(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'TermModel', $builder->getType()))
                };

            case $this instanceof UserModel:
                // Support post, comment
                return match ($builder->getType()) {
                    'post' => $builder->query()->set('author', $this->id())->all()->$getter(),
                    'comment' => $builder->query()->set('user_id', $this->id())->all()->$getter(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'UserModel', $builder->getType()))
                };

            case $this instanceof CommentModel:
                // Support comment
                return match ($builder->getType()) {
                    'comment' => $builder->query()->childOf($this->id())->all()->$getter(),
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'CommentModel', $builder->getType()))
                };

            default:
                return $builder->query()->childOf($this->id())->all()->$getter();
        }
    }

    protected function hasOne(string|BuilderInterface|ModelInterface $builder): ?ModelInterface
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
                    'user' => !empty($this->user_id) && $builder->concern((int)$this->user_id) ? $builder->model($this->user_id) : null,
                    default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'CommentModel', $builder->getType()))
                };

            default:
                return $builder->query()->childOf($this->id())->limit(1)->first();
        }
    }

    protected function resolveBuilder(string|BuilderInterface|ModelInterface $builder): BuilderInterface
    {
        if ($builder instanceof ModelInterface) {
            $builder = match (true) {
                $builder instanceof PostModel => app()->schema($builder->name(), 'post'),
                $builder instanceof TermModel => app()->schema($builder->name(), 'taxonomy'),
                $builder instanceof CommentModel => app()->schema($builder->name(), 'comment'),
                $builder instanceof UserModel => app()->schema($builder->name(), 'user'),
                default => app()->schema($builder->name())
            };
        }

        if ($builder instanceof BuilderInterface) {
            return $builder;
        }

        if (!empty(($object = app()->schema()->get($builder)))) {
            return $object;
        }

        throw new ContainerValueNotFoundException();
    }
}
