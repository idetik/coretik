<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Models\Traits\AcfFields;
use Coretik\Core\Models\Adapters\WPPostAdapter;
use Coretik\Core\Query\Post as Query;

class PostModel extends WPModel
{
    use AcfFields;

    public function __construct($initializer = null)
    {
        $this->adapter = new WPPostAdapter($this);
        $this->dictionnary = new PostDictionnary();

        switch (true) {
            case $initializer instanceof \WP_Post:
                $this->id = $initializer->ID;
                $this->wp_object = $initializer;
                $this->name = $initializer->post_type;
                break;
            case \is_int($initializer):
                $this->id = $initializer;
                $this->wp_object = $this->adapter->get($initializer);
                $this->name = $this->wp_object->post_type;
                break;
            default:
                break;
        }
        parent::__construct();
    }

    // public function newBaseQueryBuilder()
    // {
    //     return new Query();
    // }

    /**
     * Add post type specifics data changes to the model
     */
    public function changes(): array
    {
        $changes = parent::changes();
        $changes['meta_input'] = [];
        foreach ($this->metaKeys() as $key) {
            if (\property_exists($this, $key)) {
                if (!$this->isProtectedMeta($key)) {
                    $changes['meta_input'][$key] = $this->castMeta($key, $this->$key);
                }
            }
        }
        // @todo taxonomies
        // ['tax_input']
        return $changes;
    }

    /**
     * Force post type to the current model
     */
    public function create(): self
    {
        $this->post_type = $this->name();
        return parent::create();
    }

    public function wpPostType(): \WP_Post_Type
    {
        return \get_post_type_object($this->name());
    }

    public function postStatus(): string
    {
        return \get_post_status($this->id());
    }

    // public function slug(): string
    // {
    //     return $this->wp_object->post_name;
    // }

    public function title(): string
    {
        return \get_the_title($this->id);
    }

    public function permalink(): string
    {
        return \get_permalink($this->id);
    }

    // @todo fallback
    public function thumbnailId(): int
    {
        return \get_post_thumbnail_id($this->id());
    }

    public function excerpt(): string
    {
        return get_the_excerpt($this->id());
    }

    public function get(string $prop)
    {
        if ($this->hasMeta($prop)) {
            return $this->meta($prop);
        }
        return parent::get($prop);
    }
}
