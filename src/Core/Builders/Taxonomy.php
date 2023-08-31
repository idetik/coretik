<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\{
    RegistrableInterface,
    TaxonomiableInterface,
};
use Coretik\Core\Builders\Taxonomy\Args;
use Coretik\Core\Builders\PostType\Labels;
use Coretik\Core\Builders\Traits\Registrable;
use Coretik\Core\Utils\Arr;
use Coretik\Core\Query\Term as Query;

final class Taxonomy extends BuilderModelable implements RegistrableInterface
{
    use Registrable;

    protected $taxonomy;
    protected $objectType = [];
    protected $args;
    protected $names;
    protected $model;

    public function __construct(string $taxonomy, array|string|TaxonomiableInterface $object_type = [], array $args = [], array $names = [])
    {
        $this->taxonomy = $taxonomy;

        foreach (Arr::wrap($object_type) as $object) {
            $this->for($object);
        }

        if (!empty($names)) {
            $this->setNames(
                $names['singular'] ?? '',
                $names['plural'] ?? '',
                $names['slug'] ?? ''
            );
        }
        $this->setArgs($args);
        parent::__construct();
        $this->querier(function ($mediator) {
            return new Query($mediator);
        });
    }

    public function getType(): string
    {
        return 'taxonomy';
    }

    public function getName(): string
    {
        return $this->taxonomy;
    }

    /**
     * Set target object type (post type)
     * @param string|TaxonomiableInterface $builder_or_id Give a builder object or custom post type identifiant
     */
    public function for(string|TaxonomiableInterface $builder_or_id): self
    {
        if ($builder_or_id instanceof TaxonomiableInterface) {
            $this->addObjectType($builder_or_id);
        } else {
            $this->addStringType($builder_or_id);
        }
        return $this;
    }

    public function addObjectType(TaxonomiableInterface $object)
    {
        $object->addTaxonomy($this);
        $this->objectType[] = $object->getName();
        return $this;
    }

    public function addStringType(string $object)
    {
        $this->objectType[] = $object;
        return $this;
    }

    public function getObjectTypes(): array
    {
        return $this->objectType;
    }

    public function args(): Args
    {
        return $this->args;
    }

    public function setArgs(array $args = []): self
    {
        $this->args = new Args($args);
        return $this;
    }

    public function setNames(string $singular, string $plural, string $slug = ''): self
    {
        $this->setSingularName($singular);
        $this->setPluralName($plural);
        $this->setSlugName($slug);
        return $this;
    }

    public function setSingularName(string $name): self
    {
        $this->names['singular'] = $name;
        return $this;
    }

    public function setPluralName(string $name): self
    {
        $this->names['plural'] = $name;
        return $this;
    }

    public function setSlugName(string $name): self
    {
        $this->names['slug'] = $name;
        return $this;
    }

    public function registerAction(): void
    {
        if ((isset($this->names['plural']) || isset($this->names['singular'])) && empty($this->names['slug'])) {
            $this->names['slug'] = isset($this->names['plural']) ? \sanitize_title($this->names['plural']) : \sanitize_title($this->names['singular']);
        }

        if (empty($this->args->get('labels'))) {
            $this->args->set('labels', new Labels(
                $this->names['singular'] ?? $this->taxonomy,
                $this->names['plural'] ?? $this->taxonomy,
            ));
        }

        if ($this->args->get('labels') instanceof Labels) {
            if ($this->args->has('is_femininus') && $this->args->get('is_femininus')) {
                $this->args->get('labels')->isFemininus();
            }
            $this->args->set('labels', $this->args->get('labels')->all());
        }

        \register_extended_taxonomy($this->taxonomy, $this->objectType, $this->args->all(), $this->names);
    }

    public function wpObject(int $id): ?\WP_Term
    {
        return \get_term($id, $this->taxonomy);
    }

    public function concern(int $objectId): bool
    {
        return (bool)\term_exists($objectId, $this->getName());
    }
}
