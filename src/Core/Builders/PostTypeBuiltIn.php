<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\TaxonomiableInterface;
use Coretik\Core\Builders\PostType\Args;
use Coretik\Core\Query\Post as Query;

final class PostTypeBuiltIn extends BuilderModelable implements TaxonomiableInterface
{
    protected $postType;
    protected $args;

    public function __construct(string $post_type)
    {
        $this->postType = $post_type;
        $object = \get_post_type_object($post_type);
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

    public function register(): void
    {
        //
    }

    public function getType(): string
    {
        return 'post';
    }

    public function getName(): string
    {
        return $this->postType;
    }

    public function args(): Args
    {
        return $this->args;
    }

    public function addTaxonomy(BuilderInterface $taxonomy)
    {
        $taxonomies = $this->args->get('taxonomies');
        $taxonomies[] = $taxonomy->getName();
        $this->args->set('taxonomies', $taxonomies);
    }

    public function taxonomies(): array
    {
        return $this->args->get('taxonomies');
    }

    public function wpObject(int $id)
    {
        return \get_post($id);
    }

    public function concern(int $objectId): bool
    {
        return \get_post_type($objectId) === $this->getName();
    }
}
