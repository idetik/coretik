<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Collection;
use Coretik\Core\Models\Interfaces\DictionnaryInterface;

class CommentDictionnary extends Collection implements DictionnaryInterface
{
    public function __construct()
    {
        parent::__construct([
            'comment_ID', //(int)
            'comment_post_ID', //(int) ID of the post the comment is associated with.
            'comment_author', //(string)
            'comment_author_email', //(string)
            'comment_author_url', //(string)
            'comment_author_IP', //(string) Comment author IP address (IPv4 format).
            'comment_date', //(string) Comment date in YYYY-MM-DD HH:MM:SS format.
            'comment_date_gmt', //(string) Comment GMT date in YYYY-MM-DD HH::MM:SS format.
            'comment_content', //(string)
            'comment_karma', //(int) Comment karma count.
            'comment_approved', //(string) Comment approval status.
            'comment_agent', //(string) Comment author HTTP user agent.
            'comment_type', //(string)
            'comment_parent', //(int)
            'user_id', //(int)
            'comment_meta', //(array) Array of key/value pairs to be stored in commentmeta for the new.
        ]);
    }
}
