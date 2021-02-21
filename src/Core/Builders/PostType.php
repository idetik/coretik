<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\BuilderInterface;
use Coretik\Core\Builders\Interfaces\RegistrableInterface;
use Coretik\Core\Builders\Interfaces\TaxonomiableInterface;
use Coretik\Core\Builders\PostType\Args;
use Coretik\Core\Builders\PostType\Labels;
use Coretik\Core\Query\Post as Query;

final class PostType extends BuilderModelable implements RegistrableInterface, TaxonomiableInterface
{
    use Traits\Registrable;

    protected $postType;
    protected $args;
    protected $names;

    public function __construct(string $post_type, array $args = [], array $names = [])
    {
        $this->postType = $post_type;
        $this->args = new Args($args);
        $this->names = $names;
        parent::__construct();
        $this->handler(new \Coretik\Core\Models\Handlers\Guard());
        $this->querier(function ($mediator) {
            return new Query($mediator);
        });
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

    public function registerAction(): void
    {
        if ((isset($this->names['plural']) || isset($this->names['singular'])) && empty($this->names['slug'])) {
            $this->names['slug'] = isset($this->names['plural']) ? \sanitize_title($this->names['plural']) : \sanitize_title($this->names['singular']);
        }

        if (empty($this->args->get('labels'))) {
            $this->args->set('labels', new Labels(
                $this->names['singular'] ?? $this->postType,
                $this->names['plural'] ?? $this->postType,
            ));
        }

        if ($this->args->get('labels') instanceof Labels) {
            $this->args->set('labels', $this->args->get('labels')->all());
        }

        \register_extended_post_type($this->postType, $this->args->all(), $this->names);
    }

    public function wpObject(int $id)
    {
        return \get_post($id);
    }
}
