<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Models\Traits\AcfFields;
use Coretik\Core\Models\Adapters\WPTermAdapter;
use Coretik\Core\Models\Traits\Relationships;

class TermModel extends WPModel
{
    use Relationships;
    use AcfFields;

    public function __construct($initializer = null, $mediator = null)
    {
        $this->adapter = new WPTermAdapter($this);
        $this->dictionnary = new TermDictionnary();

        switch (true) {
            case $initializer instanceof \WP_Term:
                $this->id = $initializer->term_id;
                $this->wp_object = $initializer;
                $this->name = $initializer->taxonomy;
                break;
            case \is_int($initializer):
                $this->id = $initializer;
                $this->wp_object = $this->adapter->get($initializer);
                $this->name = $this->wp_object->taxonomy;
                break;
            default:
                if (!empty($mediator)) {
                    $this->name = $mediator->getName();
                }
                break;
        }
        parent::__construct();

        if (!empty($this->name())) {
            $this->on('created', [$this, 'saveMeta']);
            $this->on('updated', [$this, 'saveMeta']);
        }
    }

    public function setName(string $value): self
    {
        parent::setName($value);

        if (!empty($this->name())) {
            $this->on('created', [$this, 'saveMeta']);
            $this->on('updated', [$this, 'saveMeta']);
            $this->on('saved', function ($savedModel) {
                $this->wp_object = $this->adapter->get($savedModel->id());
            });
        }

        return $this;
    }

    public function saveMeta()
    {
        foreach ($this->metaKeys() as $key) {
            if (\property_exists($this, $key)) {
                if (!$this->isProtectedMeta($key)) {
                    $this->adapter->updateMeta($this->resolveMetaKey($key), $this->castMeta($key, $this->$key));
                }
            }
        }
    }

    public function title(): string
    {
        return $this->wp_object->name;
    }

    public function setTitleAttribute($value): void
    {
        if (!isset($this->wp_object)) {
            $this->wp_object = new \stdClass();
        }
        $this->wp_object->name = $value;
    }

    public function permalink(): string
    {
        return \get_term_link($this->wp_object);
    }

    public function parentId(): int
    {
        return $this->get('parent');
    }

    public function parent(): self
    {
        return app()->schema($this->name(), 'taxonomy')->model($this->parentId());
    }

    public function setParentId(int $id): self
    {
        $this->parent = $id;
        if (isset($this->wp_object)) {
            $this->wp_object->parent = $id;
        }
        return $this;
    }

    public function get(string $prop)
    {
        if ($this->hasMeta($prop)) {
            return $this->meta($prop);
        }
        return parent::get($prop);
    }
}
