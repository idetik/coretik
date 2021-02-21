<?php

namespace Coretik\Core\Models;

use Coretik\Core\Models\Model;
use Coretik\Core\Models\Traits\Metable;
use Coretik\Core\Models\Adapters\WPPostAdapter;
use Coretik\Core\Builders\Interfaces\ModelableInterface;

// @todo utility ? Uses model classes ?
class Anonymous extends Model
{
    protected $mediator;

    use Metable;

    public function __construct(ModelableInterface $mediator, $initializer = null)
    {
        $this->mediator = $mediator;

        switch ($mediator->getType()) {
            case 'post':
                $this->adapter = new Adapters\WPPostAdapter($this);
                break;
            case 'user':
                $this->adapter = new Adapters\WPUserAdapter($this);
                break;
            case 'taxonomy':
                $this->adapter = new Adapters\WPTermAdapter($this);
                break;
        }

        if (! \is_null($initializer)) {
            $this->initialize();
        }
        parent::__construct();
    }

    public function initialize($initializer)
    {
        switch (true) {
            case $initializer instanceof \WP_Post:
                $this->id = $initializer->ID;
                $this->wp_object = $initializer;
                $this->name = $initializer->post_type;
                // $this->adapter = new Adapters\WPPostAdapter();
                break;
            case $initializer instanceof \WP_User:
                $this->id = $initializer->user_id;
                $this->wp_object = $initializer;
                $this->name = 'user';
                // $this->adapter = new Adapters\WPUserAdapter();
                break;
            case $initializer instanceof \WP_Term:
                $this->id = $initializer->term_id;
                $this->wp_object = $initializer;
                $this->name = $initializer->taxonomy;
                $this->adapter = new Adapters\WPTermAdapter();
                break;
            case $initializer instanceof \Closure:
                $callable = $initializer->bindTo($this);
                $callable();
            case \is_int($initializer):
                    $this->initialize($mediator->wpObject($initializer));
                break;
            default:
                throw new \Exception("Unable to initialize model with type of initializer : " . get_class($initializer));
                break;
        }
    }
}
