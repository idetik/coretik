<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Collection;
use Coretik\Core\Models\Interfaces\DictionnaryInterface;

class PostDictionnary extends Collection implements DictionnaryInterface
{
    public function __construct()
    {
        parent::__construct([
            'ID', // (int) The post ID. If equal to something other than 0, the post with that ID will be updated. Default 0.
            'post_author', // (int) The ID of the user who added the post. Default is the current user ID.
            'post_date', // (string) The date of the post. Default is the current time.
            'post_date_gmt', // (string) The date of the post in the GMT timezone. Default is the value of $post_date.
            'post_content', // (mixed) The post content. Default empty.
            'post_content_filtered', // (string) The filtered post content. Default empty.
            'post_title', // (string) The post title. Default empty.
            'post_excerpt', // (string) The post excerpt. Default empty.
            'post_status', // (string) The post status. Default 'draft'.
            'post_type', // (string) The post type. Default 'post'.
            'comment_status', // (string) Whether the post can accept comments. Accepts 'open' or 'closed'. Default is the value of 'default_comment_status' option.
            'ping_status', // (string) Whether the post can accept pings. Accepts 'open' or 'closed'. Default is the value of 'default_ping_status' option.
            'post_password', // (string) The password to access the post. Default empty.
            'post_name', // (string) The post name. Default is the sanitized post title when creating a new post.
            'to_ping', // (string) Space or carriage return-separated list of URLs to ping. Default empty.
            'pinged', // (string) Space or carriage return-separated list of URLs that have been pinged. Default empty.
            'post_modified', // (string) The date when the post was last modified. Default is the current time.
            'post_modified_gmt', // (string) The date when the post was last modified in the GMT timezone. Default is the current time.
            'post_parent', // (int) Set this for the post it belongs to, if any. Default 0.
            'menu_order', // (int) The order the post should be displayed in. Default 0.
            'post_mime_type', // (string) The mime type of the post. Default empty.
            'guid', // (string) Global Unique ID for referencing the post. Default empty.
            'post_category', // (array) Array of category IDs. Defaults to value of the 'default_category' option.
            // 'tags_input', // (array) Array of tag names, slugs, or IDs. Default empty.
            'tax_input', // (array) Array of taxonomy terms keyed by their taxonomy name. Default empty.
            // 'meta_input', // (array) Array of post meta values keyed by their post meta key. Default empty.
        ]);
    }
}
