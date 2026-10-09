<?php

namespace Coretik\Core\Query;

use Coretik\Core\Query\Adapters\WPPostAdapter as QueryAdapter;
use Coretik\Core\Builders\Interfaces\ModelableInterface;
use Globalis\WP\Cubi;

class Post extends Query
{
    const PRIMARY_KEY = 'ID';

    public function newQueryBuilderInstance(array $defaultArgs = [])
    {
        $args = \array_merge($this->getQueryArgsDefault(), $defaultArgs);
        return new QueryAdapter($args);
    }

    public function results(): array
    {
        return $this->get()->posts;
    }

    public function total(): int
    {
        // found_posts is not computed with no_found_rows
        return \max((int)$this->get()->found_posts, $this->count());
    }

    public function getQueryArgsDefault()
    {
        return [
            'post_status' => $this->defaultPostStatus(),
            'orderby' => 'post_date',
            'order' => 'DESC',
            'post_type' => $this->mediator->getName()
        ];
    }

    /**
     * Only published posts are queried by default, except for back-office contexts.
     * AJAX requests are public (admin-ajax.php is_admin() too): other statuses require the edit_posts capability of the post type.
     */
    protected function defaultPostStatus()
    {
        if (\wp_doing_ajax()) {
            $status = $this->currentUserCanEditPosts() ? $this->statusNotTrashed() : 'publish';
        } elseif (\is_admin() || \wp_doing_cron() || $this->isCli()) {
            $status = $this->statusNotTrashed();
        } else {
            $status = 'publish';
        }

        return \apply_filters('coretik/query/post/default_status', $status, $this->mediator->getName());
    }

    protected function currentUserCanEditPosts(): bool
    {
        $postType = \get_post_type_object($this->mediator->getName());
        return \current_user_can($postType->cap->edit_posts ?? 'edit_posts');
    }

    protected function isCli(): bool
    {
        return Cubi\is_cli();
    }

    public function statusNotTrashed()
    {
        return [
            "publish",
            "future",
            "draft",
            "pending",
            "private",
            "request-pending",
            "request-confirmed",
            "request-completed",
        ];
    }
}
