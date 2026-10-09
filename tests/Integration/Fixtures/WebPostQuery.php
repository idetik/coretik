<?php

namespace Coretik\Tests\Integration\Fixtures;

use Coretik\Core\Query\Post;

/**
 * PHPUnit runs in CLI, where all statuses are queried: simulates a web request.
 */
class WebPostQuery extends Post
{
    protected function isCli(): bool
    {
        return false;
    }
}
