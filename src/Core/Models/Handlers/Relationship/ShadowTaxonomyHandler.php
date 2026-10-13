<?php

namespace Coretik\Core\Models\Handlers\Relationship;

use Coretik\Core\Builders\Handler;
use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Coretik\Core\Exception\UnhandledException;
use Coretik\Core\Models\Interfaces\ModelInterface;

/**
 * Post to post relationship (1, n) mounted in a hidden taxonomy, when the post_parent column is already used.
 * One term per parent post (slug = parent id), the child post gets the term of its parent.
 * Term relationships are indexed: hasMany(), hasOne(), belongsTo() and hasManyThrough() use them, never a meta query.
 *
 * PostType::make('registration')
 *     ->handler(BelongsToHandler::withPivot('player_id')->setBelongsToBuilder('player')) // post_parent
 *     ->handler(ShadowTaxonomyHandler::withPivot('camp_id')->setBelongsToBuilder('camp')) // hidden taxonomy
 *
 * @phpstan-consistent-constructor
 */
class ShadowTaxonomyHandler extends Handler implements BelongsToRelationInterface
{
    /**
     * Taxonomy names, keyed by parent post type then child post type
     *
     * @var array<string, array<string, string>>
     */
    protected static array $taxonomies = [];

    /**
     * Registered shadow taxonomies, keyed by taxonomy name
     *
     * @var array<string, array{parent: string, child: string, pivot: string}>
     */
    protected static array $shadows = [];

    private (BuilderInterface&ModelableInterface)|null $belongsToBuilder = null;
    private $pivot;
    private ?string $taxonomy = null;

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

    /**
     * The hidden taxonomy linking a parent post type to a child post type, null without shadow relationship
     */
    public static function taxonomyBetween(string $parentPostType, string $childPostType): ?string
    {
        return static::$taxonomies[$parentPostType][$childPostType] ?? null;
    }

    /**
     * Registered shadow taxonomies, keyed by taxonomy name: parent and child post types, pivot meta key ('' for a callable)
     *
     * @return array<string, array{parent: string, child: string, pivot: string}>
     */
    public static function shadowTaxonomies(): array
    {
        return static::$shadows;
    }

    /**
     * The parent post id of a child post, read from its terms (object terms cache)
     */
    public static function parentIdOf(int $childId, string $taxonomy): int
    {
        $terms = \get_the_terms($childId, $taxonomy);
        return \is_array($terms) && !empty($terms) ? (int)\current($terms)->slug : 0;
    }

    /**
     * The term of a parent post, created on demand
     */
    public static function termIdOf(int $parentId, string $taxonomy, bool $create = false): int
    {
        $term = \get_term_by('slug', (string)$parentId, $taxonomy);
        if ($term instanceof \WP_Term) {
            return $term->term_id;
        }

        if (!$create) {
            return 0;
        }

        $inserted = \wp_insert_term((string)$parentId, $taxonomy, ['slug' => (string)$parentId]);
        return \is_wp_error($inserted) ? 0 : (int)$inserted['term_id'];
    }

    /**
     * Set the post type the posts belong to
     */
    public function belongsToBuilder(): ?BuilderInterface
    {
        return $this->belongsToBuilder;
    }

    public function pivot(): string|callable
    {
        return $this->pivot;
    }

    public function setBelongsToBuilder(string|BuilderInterface|ModelInterface $belongsTo): self
    {
        $builder = coretik()->schema()->resolve($belongsTo);
        if (!$builder instanceof ModelableInterface) {
            throw new UnhandledException(\sprintf('ShadowTaxonomyHandler requires a modelable parent builder, %s given.', $builder->getName()));
        }
        $this->belongsToBuilder = $builder;
        return $this;
    }

    /**
     * Set the metakey contains the parent id or callable that return the parent id
     */
    public function setPivot(string|callable $pivot): self
    {
        $this->pivot = $pivot;
        return $this;
    }

    /**
     * Set the hidden taxonomy name, "{parent}_{child}" by default (32 characters max)
     */
    public function setTaxonomy(string $taxonomy): self
    {
        $this->taxonomy = $taxonomy;
        return $this;
    }

    public function taxonomy(): string
    {
        return $this->taxonomy ?? \substr(\sanitize_key($this->belongsToBuilder->getName() . '_' . $this->builder->getName()), 0, 32);
    }

    public function actions(): void
    {
        if (empty($this->belongsToBuilder) || 'post' !== $this->belongsToBuilder->getType() || 'post' !== $this->builder->getType()) {
            throw new UnhandledException('ShadowTaxonomyHandler requires a post type builder and a post type parent builder.');
        }

        static::$taxonomies[$this->belongsToBuilder->getName()][$this->builder->getName()] = $this->taxonomy();
        static::$shadows[$this->taxonomy()] = [
            'parent' => $this->belongsToBuilder->getName(),
            'child' => $this->builder->getName(),
            'pivot' => \is_string($this->pivot) ? $this->pivot : '',
        ];

        if (\did_action('init')) {
            $this->registerTaxonomy();
        } else {
            \add_action('init', [$this, 'registerTaxonomy'], 1);
        }

        if (\is_string($this->pivot)) {
            \add_action('added_post_meta', [$this, 'mountMetaRelationship'], 10, 4);
            \add_action('updated_post_meta', [$this, 'mountMetaRelationship'], 10, 4);
            \add_action('deleted_post_meta', [$this, 'unmountMetaRelationship'], 10, 3);
        } else {
            \add_action('acf/save_post', [$this, 'mountPivotRelationship'], 10);
        }

        \add_action('before_delete_post', [$this, 'deleteParentTerm'], 10);
    }

    public function freeze(): void
    {
        \remove_action('added_post_meta', [$this, 'mountMetaRelationship'], 10);
        \remove_action('updated_post_meta', [$this, 'mountMetaRelationship'], 10);
        \remove_action('deleted_post_meta', [$this, 'unmountMetaRelationship'], 10);
        \remove_action('acf/save_post', [$this, 'mountPivotRelationship'], 10);
        \remove_action('before_delete_post', [$this, 'deleteParentTerm'], 10);
    }

    public function registerTaxonomy(): void
    {
        if (\taxonomy_exists($this->taxonomy())) {
            return;
        }

        \register_taxonomy($this->taxonomy(), [$this->builder->getName()], [
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'show_tagcloud' => false,
            'show_admin_column' => false,
            'hierarchical' => false,
            'query_var' => false,
            'rewrite' => false,
        ]);
    }

    public function mountMetaRelationship($meta_id, $post_id, $meta_key, $_meta_value)
    {
        if ($meta_key !== $this->pivot || !$this->builder->concern((int)$post_id)) {
            return;
        }

        $this->mountRelationship((int)$post_id, (int)$_meta_value);
    }

    public function unmountMetaRelationship($meta_ids, $post_id, $meta_key)
    {
        if ($meta_key !== $this->pivot || !$this->builder->concern((int)$post_id)) {
            return;
        }

        $this->mountRelationship((int)$post_id, 0);
    }

    public function mountPivotRelationship($post_id)
    {
        if (!\is_numeric($post_id) || !$this->builder->concern((int)$post_id)) {
            return;
        }

        $model = $this->builder instanceof ModelableInterface ? $this->builder->model((int)$post_id, null, true) : null;
        $this->mountRelationship((int)$post_id, (int)\call_user_func($this->pivot, $model));
    }

    /**
     * @param int $parentId 0 to detach the child post
     */
    protected function mountRelationship(int $childId, int $parentId): void
    {
        $taxonomy = $this->taxonomy();

        if (empty($parentId)) {
            \wp_set_object_terms($childId, [], $taxonomy);
            return;
        }

        if (!$this->belongsToBuilder->concern($parentId) || static::parentIdOf($childId, $taxonomy) === $parentId) {
            return;
        }

        $termId = static::termIdOf($parentId, $taxonomy, true);
        if (!empty($termId)) {
            \wp_set_object_terms($childId, [$termId], $taxonomy);
        }
    }

    /**
     * The children lose their relationship with a deleted parent
     */
    public function deleteParentTerm($post_id): void
    {
        if (!$this->belongsToBuilder->concern((int)$post_id)) {
            return;
        }

        $termId = static::termIdOf((int)$post_id, $this->taxonomy());
        if (!empty($termId)) {
            \wp_delete_term($termId, $this->taxonomy());
        }
    }
}
