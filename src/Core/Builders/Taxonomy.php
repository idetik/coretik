<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\RegistrableInterface;
use Coretik\Core\Builders\Interfaces\TaxonomiableInterface;
use Coretik\Core\Builders\Taxonomy\Args;
use Coretik\Core\Builders\PostType\Labels;
use Coretik\Core\Utils\Arr;
use Coretik\Core\Query\Term as Query;

final class Taxonomy extends BuilderModelable implements RegistrableInterface
{
    use Traits\Registrable;

    protected $taxonomy;
    protected $objectType = [];
    protected $args;
    protected $names;
    protected $model;

    public function __construct(string $taxonomy, $object_type, array $args = [], array $names = [])
    {
        $this->taxonomy = $taxonomy;
        
        foreach (Arr::wrap($object_type) as $object) {
            if ($object_type instanceof TaxonomiableInterface) {
                $this->addObjectType($object_type);
            } elseif (\is_string($object_type)) {
                $this->objectType[] = $object_type;
            }
        }

        $this->args = new Args($args);
        $this->names = $names;
        parent::__construct();
        $this->querier(function ($mediator) {
            return new Query($mediator);
        });
    }

    public function addObjectType(TaxonomiableInterface $object)
    {
        $object->addTaxonomy($this);
        $this->objectType[] = $object->getName();
    }

    public function getType(): string
    {
        return 'taxonomy';
    }

    public function getName(): string
    {
        return $this->taxonomy;
    }

    public function getObjectTypes(): array
    {
        return $this->objectType;
    }

    public function args(): Args
    {
        return $this->args;
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
            $this->args->set('labels', $this->args->get('labels')->all());
        }

        \register_extended_taxonomy($this->taxonomy, $this->objectType, $this->args->all(), $this->names);
    }

    public function wpObject(int $id): \WP_Term
    {
        return \get_term($id, $this->taxonomy);
    }

    public function concern(int $objectId): bool
    {
        return $this->wpObject($objectId)->name === $this->getName();
    }
}
