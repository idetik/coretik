<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Models\Traits\AcfFields;
use Coretik\Core\Models\Traits\Relationships;
use Coretik\Core\Models\Adapters\WPCommentAdapter;

class CommentModel extends WPModel
{
    use AcfFields;
    use Relationships;

    public function __construct($initializer = null)
    {
        $this->adapter = new WPCommentAdapter($this);
        $this->dictionnary = new CommentDictionnary();

        switch (true) {
            case $initializer instanceof \WP_Comment:
                $this->id = $initializer->comment_ID;
                $this->wp_object = $initializer;
                break;
            case \is_int($initializer):
                $this->id = $initializer;
                $this->wp_object = $this->adapter->get($initializer);
                break;
            default:
                break;
        }
        parent::__construct();

        $this->on('created', [$this, 'saveMeta']);
        $this->on('updated', [$this, 'saveMeta']);
    }

    public function saveMeta()
    {
        foreach ($this->metaKeys() as $key) {
            if (\property_exists($this, $key)) {
                if (!$this->isProtectedMeta($key)) {
                    $this->adapter->updateMeta($key, $this->castMeta($key, $this->$key));
                }
            }
        }
    }

    public function get(string $prop)
    {
        if ($this->hasMeta($prop)) {
            return $this->meta($prop);
        }
        return parent::get($prop);
    }

    public function avatar(int $size, string $default = '', string $alt = '', array $args = []): string
    {
        return \get_avatar($this->id(), 50, $default, $alt, $args);
    }

    public function author(): string
    {
        return \get_comment_author($this->id());
    }

    public function date(string $format = 'j M Y, G\hi'): string
    {
        return \date_i18n($format, \get_comment_date('U', $this->id()));
    }

    public function content(): string
    {
        return \get_comment_text($this->id());
    }

    public function parentId(): int
    {
        return $this->get('post_parent');
    }

    public function commentParentId(): int
    {
        return $this->get('comment_parent');
    }

    public function children()
    {
        //@todo
    }
}
