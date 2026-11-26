<?php

namespace Coretik\Services\Queue;

/**
 * Marker: Queue::dispatch() queues the job instead of running it now
 */
interface ShouldQueue
{
}
