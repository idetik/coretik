<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Models\Traits\AcfFields;
use Coretik\Core\Models\Traits\Taxonomy;
use Coretik\Core\Models\Traits\Relationships;
use Coretik\Core\Models\Adapters\WPPostAdapter;
use Coretik\Core\Query\Post as Query;
use Coretik\Core\Models\Exceptions\UndefinedMetaKeyException;

class PostModel extends WPModel
{
    use AcfFields;
    use Taxonomy;
    use Relationships;

    public function __construct($initializer = null, $mediator = null)
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
                if (!empty($mediator)) {
                    $this->name = $mediator->getName();
                }
                break;
        }
        parent::__construct();
    }

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
                    $changes['meta_input'][$this->resolveMetaKey($key)] = $this->castMeta($key, $this->$key);
                }
            }
        }

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

    public function title(): string
    {
        return \get_the_title($this->id);
    }

    public function permalink(): string
    {
        try {
            $url = $this->meta('redirect');
            if (empty($url)) {
                $url = \get_permalink($this->id);
            }
        } catch (UndefinedMetaKeyException $e) {
            $url = \get_permalink($this->id);
        }

        return $url;
    }

    public function parentId(): int
    {
        return $this->get('post_parent');
    }

    public function setParentId(int $id): self
    {
        $this->post_parent = $id;
        if (isset($this->wp_object)) {
            $this->wp_object->post_parent = $id;
        }
        return $this;
    }

    public function thumbnailFallbackId()
    {
        return \apply_filters('coretik/postModel/thumbnail_fallback_id', false);
    }

    public function hasThumbnailFallback()
    {
        return $this->thumbnailFallbackId() !== false;
    }

    public function thumbnailId(): int
    {
        if (!\has_post_thumbnail($this->id())) {
            if ($this->hasThumbnailFallback()) {
                return $this->thumbnailFallbackId();
            }
        }
        return \get_post_thumbnail_id($this->id());
    }

    public function thumbnailUrl($size): string
    {
        if (!\has_post_thumbnail($this->id())) {
            if ($this->hasThumbnailFallback()) {
                return \wp_get_attachment_image_url($this->thumbnailFallbackId(), $size);
            }
        }
        return \get_the_post_thumbnail_url($this->id(), $size);
    }

    public function excerpt(): string
    {
        return \get_the_excerpt($this->id());
    }

    public function content($more_link_text = null, $strip_teaser = false): string
    {
        $content = \get_the_content($more_link_text, $strip_teaser);
        /**
         * Filters the post content.
         *
         * @since 0.71
         *
         * @param string $content Content of the current post.
         */
        $content = \apply_filters('the_content', $content);
        $content = \str_replace(']]>', ']]&gt;', $content);
        return $content;
    }

    public function get(string $prop)
    {
        if ($this->hasMeta($prop)) {
            return $this->meta($prop);
        }
        return parent::get($prop);
    }
}
