<?php

namespace Coretik\Tests\Unit\Forms\Fixtures;

/**
 * Thrown by the wp_send_json() stub: the JSON answer stops the request, as wp_die() does.
 */
class JsonSent extends \RuntimeException
{
    public function __construct(public array $response, public int $status = 200)
    {
        parent::__construct('JSON sent');
    }

    public function data(): array
    {
        return $this->response['data'] ?? [];
    }
}
