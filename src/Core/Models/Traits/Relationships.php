<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Models\Interfaces\ModelInterface;
use Coretik\Core\Models\Exceptions\CannotResolveException;
use Coretik\Core\Exception\ContainerValueNotFoundException;
use Coretik\Core\Exception\UnhandledException;
use Coretik\Core\Collection;
use Coretik\Core\Models\Wp\PostModel;
use Coretik\Core\Models\Wp\TermModel;
use Coretik\Core\Models\Wp\UserModel;
use Coretik\Core\Models\Wp\CommentModel;
use Coretik\Core\Models\Handlers\Relationship\ShadowTaxonomyHandler;
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
        return $this->parentOf($this, $this->resolveBuilder($builder));
    }

    /**
     * The $builder object which $child belongs to, read from the child columns (post_parent or shadow taxonomy, post_author, term relationships...)
     */
    protected function parentOf(ModelInterface $child, BuilderInterface&ModelableInterface $builder): ?ModelInterface
    {
        try {
            switch (true) {
                case $child instanceof PostModel:
                    // Support post, taxonomy, user
                    return match ($builder->getType()) {
                        'post' => !empty($parent_id = $this->postParentId($child, $builder)) && $builder->concern($parent_id) ? $builder->model($parent_id) : null,
                        'taxonomy' => $child->term($builder->getName()),
                        'user' => $builder->concern((int)$child->post_author) ? $builder->model((int)$child->post_author) : null,
                        default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'PostModel', $builder->getType()))
                    };

                case $child instanceof TermModel:
                    // Support taxonomy
                    return match ($builder->getType()) {
                        'taxonomy' => !empty($parent_id = $child->parentId()) ? $builder->model($parent_id) : null,
                        default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'PostModel', $builder->getType()))
                    };

                case $child instanceof UserModel:
                    // Support none
                    return match ($builder->getType()) {
                        default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'UserModel', $builder->getType()))
                    };

                case $child instanceof CommentModel:
                    // Support comment, user, post
                    return match ($builder->getType()) {
                        'comment' => !empty($parent_id = $child->parentId()) ? $builder->model($parent_id) : null,
                        'post' => $builder->concern($child->comment_post_ID) ? $builder->model($child->comment_post_ID) : null,
                        'user' => !empty($child->user_id) && $builder->concern((int)$child->user_id) ? $builder->model($child->user_id) : null,
                        default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', 'CommentModel', $builder->getType()))
                    };

                default:
                    throw new UnhandledException(\sprintf('Relationship not supported for %s.', $child::class));
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
                    'post' => $this->postChildrenOf($builder->query(), $builder, $this->name(), [$this->id()])->all()->$getter(),
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
                    'post' => $this->postChildrenOf($builder->query(), $builder, $this->name(), [$this->id()])->limit(1)->first(),
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

    /**
     * Distant children through an intermediate builder: $this -> hasMany($through) -> hasMany($related).
     * Each hop uses an indexed column (post_parent, post_author, term relationships, comment_post_ID...), never a meta query:
     * the intermediate ids are queried first (ids only), then the related objects with a "__in" parameter.
     *
     * e.g. user -> posts by post_author -> children posts by post_parent:
     * $tutor->hasManyThrough(Registration::class, Player::class)
     */
    protected function hasManyThrough(
        string|BuilderInterface|ModelInterface $related,
        string|BuilderInterface|ModelInterface $through,
        bool $collect = true
    ): Collection|QuerierInterface {
        $related = $this->resolveBuilder($related);
        $through = $this->resolveBuilder($through);

        $query = $this->queryChildrenOf($related, $through, $this->childrenIds($through));

        return $collect ? $query->collection() : $query;
    }

    protected function hasOneThrough(
        string|BuilderInterface|ModelInterface $related,
        string|BuilderInterface|ModelInterface $through
    ): ?ModelInterface {
        return $this->hasManyThrough($related, $through, false)->limit(1)->first();
    }

    /**
     * Distant parent through an intermediate builder: $this -> belongsTo($through) -> belongsTo($related).
     * Reads the parent columns of already loaded objects, no query except the models loading.
     *
     * e.g. registration -> player (post_parent) -> tutor (post_author):
     * $registration->belongsToThrough(Tutor::class, Player::class)
     */
    protected function belongsToThrough(
        string|BuilderInterface|ModelInterface $related,
        string|BuilderInterface|ModelInterface $through
    ): ?ModelInterface {
        $intermediate = $this->belongsTo($through);
        if (empty($intermediate)) {
            return null;
        }

        return $this->parentOf($intermediate, $this->resolveBuilder($related));
    }

    /**
     * Many to many through a pivot post type: $this -> hasMany($pivot) -> parents of the pivots in $related.
     * The pivot belongs to $this and to $related, with post_parent or a shadow taxonomy (see ShadowTaxonomyHandler).
     * Two indexed queries: the pivots with their parent ids only (no post loaded), then the related posts by primary key.
     * The related posts are ordered as their pivots, each one once.
     *
     * e.g. camp -> registrations (shadow taxonomy) -> players (registration post_parent):
     * $camp->belongsToMany(Player::class, Registration::class)
     * player -> registrations (post_parent) -> camps (registration shadow taxonomy):
     * $player->belongsToMany(Camp::class, Registration::class)
     *
     * @param callable|null $constraint Called with the pivots query, e.g. fn ($query) => $query->set('post_status', 'publish')
     */
    protected function belongsToMany(
        string|BuilderInterface|ModelInterface $related,
        string|BuilderInterface|ModelInterface $pivot,
        ?callable $constraint = null,
        bool $collect = true
    ): Collection|QuerierInterface {
        $related = $this->resolveBuilder($related);
        $pivot = $this->resolveBuilder($pivot);

        if ('post' !== $pivot->getType() || 'post' !== $related->getType()) {
            throw new UnhandledException(sprintf('Relationship belongsToMany not supported with %s pivot and %s builder type.', $pivot->getType(), $related->getType()));
        }

        $pivots = $this->hasMany($pivot, false);
        if (!empty($constraint)) {
            $constraint($pivots);
        }

        $ids = $this->pivotsParentIds($pivots, $pivot, $related);

        // An empty "__in" parameter is ignored by WordPress: in([]) returns no object
        $query = $related->query()->all()->in($ids);
        if (!empty($ids)) {
            $query->set('orderby', 'post__in');
        }

        return $collect ? $query->collection() : $query;
    }

    /**
     * The $related parent ids of the queried pivots, in the pivots order, each one once
     *
     * @return int[]
     */
    protected function pivotsParentIds(QuerierInterface $pivots, BuilderInterface $pivot, BuilderInterface $related): array
    {
        $taxonomy = ShadowTaxonomyHandler::taxonomyBetween($related->getName(), $pivot->getName());

        if (empty($taxonomy)) {
            // ID and post_parent columns only
            $rows = $pivots->set('fields', 'id=>parent')->set('no_found_rows', true)->results();
            $ids = \array_map(fn ($row) => (int)$row->post_parent, $rows);
        } else {
            $pivotIds = \array_map('intval', $pivots->set('fields', 'ids')->set('no_found_rows', true)->results());
            if (empty($pivotIds)) {
                return [];
            }

            // One query on term relationships for every pivot, then the term slug (parent id) is read from the cache
            \update_object_term_cache($pivotIds, $pivot->getName());
            $ids = \array_map(fn ($pivotId) => ShadowTaxonomyHandler::parentIdOf($pivotId, $taxonomy), $pivotIds);
        }

        return \array_values(\array_unique(\array_filter($ids)));
    }

    /**
     * Ids of the $builder objects which are children of $this (see hasMany()), without loading the models
     *
     * @return int[]
     */
    protected function childrenIds(BuilderInterface&ModelableInterface $builder): array
    {
        $children = $this->hasMany($builder, false);

        // Terms of a post are read from the object terms cache
        if ($children instanceof Collection) {
            return $children->map(fn (ModelInterface $model) => $model->id())->values()->all();
        }

        match ($builder->getType()) {
            'post' => $children->set('fields', 'ids')->set('no_found_rows', true),
            'user' => $children->set('fields', 'ID')->set('count_total', false),
            'comment' => $children->set('fields', 'ids')->set('hierarchical', false)->set('no_found_rows', true),
            default => $children->set('fields', 'ids'),
        };

        return \array_map('intval', $children->results());
    }

    /**
     * Query the $related objects which are children of the given $parent objects, with an indexed "__in" parameter
     *
     * @param int[] $parentIds
     */
    protected function queryChildrenOf(
        BuilderInterface&ModelableInterface $related,
        BuilderInterface&ModelableInterface $parent,
        array $parentIds
    ): QuerierInterface {
        $query = $related->query()->all();

        // An empty "__in" parameter is ignored by WordPress: it would return every object
        if (empty($parentIds)) {
            return $query->in([]);
        }

        return match ($parent->getType() . '>' . $related->getType()) {
            'post>post' => $this->postChildrenOf($query, $related, $parent->getName(), $parentIds),
            'post>comment' => $query->set('post__in', $parentIds),
            'post>taxonomy' => $query->set('object_ids', $parentIds),
            'user>post' => $query->set('author__in', $parentIds),
            'user>comment' => $query->set('author__in', $parentIds),
            'taxonomy>post' => $query->whereTax($parent->getName(), $parentIds, 'IN', 'term_id', false),
            'comment>comment' => $query->childOf($parentIds),
            default => throw new UnhandledException(sprintf('Relationship not supported between %s and %s builder type.', $parent->getType(), $related->getType()))
        };
    }

    /**
     * The parent id of a child post: its post_parent, or its term of a shadow taxonomy (see ShadowTaxonomyHandler)
     */
    protected function postParentId(PostModel $child, BuilderInterface $parent): int
    {
        $taxonomy = ShadowTaxonomyHandler::taxonomyBetween($parent->getName(), $child->name());
        return $taxonomy ? ShadowTaxonomyHandler::parentIdOf($child->id(), $taxonomy) : $child->parentId();
    }

    /**
     * Filter a post query on the given parents: post_parent, or the terms of a shadow taxonomy (see ShadowTaxonomyHandler)
     *
     * @param int[] $parentIds
     */
    protected function postChildrenOf(QuerierInterface $query, BuilderInterface $related, string $parentPostType, array $parentIds): QuerierInterface
    {
        $taxonomy = ShadowTaxonomyHandler::taxonomyBetween($parentPostType, $related->getName());
        if (empty($taxonomy)) {
            return $query->childOf(1 === \count($parentIds) ? \current($parentIds) : $parentIds);
        }

        // One term per parent, its slug is the parent id
        return $query->whereTax($taxonomy, \array_map('strval', $parentIds), 'IN', 'slug', false);
    }

    protected function resolveBuilder(string|BuilderInterface|ModelInterface $builder): BuilderInterface&ModelableInterface
    {
        if ($builder instanceof ModelInterface) {
            $builder = match (true) {
                $builder instanceof PostModel => coretik()->schema($builder->name(), 'post'),
                $builder instanceof TermModel => coretik()->schema($builder->name(), 'taxonomy'),
                $builder instanceof CommentModel => coretik()->schema($builder->name(), 'comment'),
                $builder instanceof UserModel => coretik()->schema($builder->name(), 'user'),
                default => coretik()->schema($builder->name())
            };
        }

        if (\is_string($builder)) {
            $builder = coretik()->schema()->get($builder);
        }

        if (empty($builder)) {
            throw new ContainerValueNotFoundException();
        }

        if (!$builder instanceof ModelableInterface || !$builder instanceof BuilderInterface) {
            throw new UnhandledException(\sprintf('Relationships require a modelable builder, %s given.', \get_debug_type($builder)));
        }

        return $builder;
    }
}
