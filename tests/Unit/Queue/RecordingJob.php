<?php

namespace Coretik\Tests\Unit\Queue;

use Coretik\Services\Queue\ShouldQueue;

/**
 * Queued by dispatch()
 */
class RecordingJob extends TestJob implements ShouldQueue
{
}
