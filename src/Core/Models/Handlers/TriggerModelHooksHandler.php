<?php

namespace Coretik\Core\Models\Handlers;

use Coretik\Core\Builders\Handler;
use WP_Post;

class TriggerModelHooksHandler extends Handler
{
    public function actions(): void
    {
        \add_action('post_updated', [$this, 'triggerUpdated'], 5, 3);// => updated
        \add_action('save_post_' . $this->builder->getName(), [$this, 'triggerCreated'], 5, 3); // created (update === false)
        \add_action('wp_insert_post', [$this, 'triggerSaved'], 5, 3); //=> saved
        \add_action('delete_post', [$this, 'triggerDelete'], 5, 2);
    }

    public function freeze(): void
    {
        \remove_action('post_updated', [$this, 'triggerUpdated'], 5);
        \remove_action('save_post_' . $this->builder->getName(), [$this, 'triggerCreated'], 5);
        \remove_action('wp_insert_post', [$this, 'triggerSaved'], 5);
        \remove_action('delete_post', [$this, 'triggerDelete'], 5);
    }

    public function triggerCreated(int $post_id, WP_Post $post, bool $update)
    {
        if (!$update) {
            if (!$this->builder->concern($post_id)) {
                return;
            }
            $model = $this->builder->model($post_id, $post);
            $model->trigger('created');
        }
    }

    public function triggerUpdated(int $post_id, WP_Post $post_after)
    {
        if (!$this->builder->concern($post_id)) {
            return;
        }
        $model = $this->builder->model($post_id, $post_after);
        $model->trigger('updated');
    }

    public function triggerSaved(int $post_id, WP_Post $post, bool $update)
    {
        if (!$this->builder->concern($post_id)) {
            return;
        }
        $model = $this->builder->model($post_id, $post);
        $model->trigger('saved');
    }

    public function triggerDelete(int $post_id, WP_Post $post)
    {
        if (!$this->builder->concern($post_id)) {
            return;
        }
        $model = $this->builder->model($post_id, $post);
        $model->trigger('deleted');
    }
}
