<?php

namespace Coretik\Testing;

/**
 * wp_die() called during AjaxRequests::captureJson(): stops the handler
 */
class WpDie extends \RuntimeException
{
}
