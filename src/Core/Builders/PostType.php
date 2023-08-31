<?php

namespace Coretik\Core\Builders;

use Coretik\Core\Builders\Interfaces\{
    BuilderInterface,
    RegistrableInterface,
    TaxonomiableInterface,
};
use Coretik\Core\Builders\PostType\{
    Args,
    Labels
};
use Coretik\Core\Builders\Traits\Registrable;
use Coretik\Core\Query\Post as Query;

use function Globalis\WP\Cubi\is_frontend;

final class PostType extends BuilderModelable implements RegistrableInterface, TaxonomiableInterface
{
    use Registrable;

    protected $postType;
    protected $args;
    protected $names;

    public function __construct(string $post_type, array $args = [], array $names = [])
    {
        $this->postType = $post_type;

        if (!empty($names)) {
            $this->setNames(
                $names['singular'] ?? '',
                $names['plural'] ?? ''
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

    public function setArgs(array $args = []): self
    {
        $this->args = new Args($args);
        return $this;
    }

    public function setNames(string $singular, string $plural): self
    {
        $this->setSingularName($singular);
        $this->setPluralName($plural);
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
            if ($this->args->has('is_femininus') && $this->args->get('is_femininus')) {
                $this->args->get('labels')->isFemininus();
            }
            $this->args->set('labels', $this->args->get('labels')->all());
        }

        if ($this->args->get('use_archive_page', false)) {
            $rewrite = $this->args->get('rewrite');
            $rewrite['pages'] = false;
            $this->args->set('rewrite', $rewrite);
        }

        $post_type = \register_extended_post_type($this->postType, $this->args->all(), $this->names);

        /**
         * Use archive page
         * Remove default wp archive rewrite rule for {archive_slug}/ in order to load page template instead
         */
        if (!is_frontend() && $this->args()->get('use_archive_page', false)) {
            global $wp_rewrite;
            $archive_slug = true === $post_type->args['has_archive'] ? $post_type->args['rewrite']['slug'] : $post_type->args['has_archive'];
            if ($post_type->args['rewrite']['with_front']) {
                $archive_slug = substr($wp_rewrite->front, 1) . $archive_slug;
            } else {
                $archive_slug = $wp_rewrite->root . $archive_slug;
            }
            unset($wp_rewrite->extra_rules_top["{$archive_slug}/?$"]);
            unset($wp_rewrite->extra_rules_top["{$archive_slug}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$"]);
            \add_rewrite_rule("{$archive_slug}/?$", "index.php?pagename=$archive_slug", 'top');
            \add_rewrite_rule("{$archive_slug}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$", "index.php?pagename=$archive_slug" . '&paged=$matches[1]', 'top');
        }
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
