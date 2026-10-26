<?php

namespace Coretik\Services\Email\Viewer;

/**
 * The emails of an application, as the viewer shows them. Implemented by the theme, which knows its emails and
 * the data to preview them with (context: a sample order, a user…).
 */
interface Catalog
{
    /**
     * @return Entry[]
     */
    public function entries(): array;

    /**
     * Data of the previews, from the query string of the viewer (fields of contextFields()); sanitized scalars
     *
     * @param array<string, string> $query
     */
    public function context(array $query): array;

    /**
     * Fields of the toolbar form (GET), to change the context: HTML, empty for none
     */
    public function contextFields(array $context): string;

    /**
     * The email rendered for this context, null when it cannot be (missing sample data…)
     */
    public function preview(string $key, array $context): ?Preview;

    /**
     * Sends the email of this context to $to only (test), without side effects (no history, no tokens…)
     */
    public function sendTest(string $key, array $context, string $to): bool;
}
