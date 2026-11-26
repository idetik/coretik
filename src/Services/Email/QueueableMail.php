<?php

namespace Coretik\Services\Email;

/**
 * Mail that can be sent in the background (Mailer::queue()): stored as ids and scalars, rebuilt when the job runs.
 * Keep models out of the payload: load them again in fromPayload().
 */
interface QueueableMail
{
    /**
     * @return array<string, mixed> JSON-serializable data
     */
    public function toPayload(): array;

    /**
     * Null when the mail has no object anymore (order deleted…): the job fails without sending
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): ?static;
}
