<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\TaxonomiableInterface;
use Coretik\Core\Builders\Taxonomy\Args;
use Coretik\Core\Builders\PostType\Labels;
use Coretik\Core\Utils\Arr;
use Coretik\Core\Query\Term as Query;

final class TaxonomyBuiltIn extends BuilderModelable
{
    protected $taxonomy;
    protected $objectType = [];
    protected $args;
    protected $names;
    protected $model;

    public function __construct(string $taxonomy)
    {
        $this->taxonomy = $taxonomy;

        $object = \get_taxonomy($taxonomy);

        $this->objectType = $object->object_type;
        $args = \get_object_vars($object);
        $args['labels'] = \get_object_vars($args['labels']);
        $args['labels']['singular'] = $args['labels']['singular_name'];
        $args['labels']['plural'] = $args['labels']['name'];
        $this->args = new Args($args);
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

    public function register(): void
    {
        //
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
