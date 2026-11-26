<?php

namespace Coretik\Services\Forms\Core;

/**
 * Formats the JSON payload answered by the JSON forms (JsonForm, TunnelForm) and read by the forms scripts
 * (src/Services/Forms/scripts):
 *
 *   { success: true, data: {
 *       valid: bool,
 *       errors:   [{ field, messages: string[], step? }],
 *       values:   [{ field, label, private, value, step? }],
 *       messages: string[],      // global errors, not bound to a field
 *       notices:  string[],      // non blocking warnings (tunnel steps)
 *       redirect_url?: string,
 *       data:     { successTpl?: string, ... }
 *   } }
 *
 * "step" is the 0-based index of the tunnel step.
 */
class JsonResponse
{
    /**
     * @param array<string, \Coretik\Services\Forms\Core\Validation\Constraints\Constraint[]> $errors Validation::getErrors()
     * @return array<int, array{field: string, messages: string[], step?: int}>
     */
    public static function errors(array $errors, ?int $step = null): array
    {
        $json = [];
        foreach ($errors as $field => $violations) {
            $error = [
                'field' => (string)$field,
                'messages' => \array_values(\array_map(fn ($violation) => (string)$violation->getMessage(), (array)$violations)),
            ];
            if (null !== $step) {
                $error['step'] = $step;
            }
            $json[] = $error;
        }
        return $json;
    }

    /**
     * @param array<string, mixed> $values Validated values
     * @param callable(string): string $label Label of a field
     * @param callable(string): bool $private Whether a field holds private data
     * @return array<int, array{field: string, label: string, private: bool, value: mixed, step?: int}>
     */
    public static function values(array $values, callable $label, callable $private, ?int $step = null): array
    {
        $json = [];
        foreach ($values as $field => $value) {
            $field = (string)$field;
            $item = [
                'field' => $field,
                'label' => (string)($label($field) ?: $field),
                'private' => (bool)$private($field),
                'value' => static::publicValue($value),
            ];
            if (null !== $step) {
                $item['step'] = $step;
            }
            $json[] = $item;
        }
        return $json;
    }

    /**
     * Value sent back to the browser: a validated file holds its base64 content, useless client side.
     */
    public static function publicValue(mixed $value): mixed
    {
        if (\is_array($value) && ($value['type'] ?? null) === 'file') {
            unset($value['content']);
        }
        return $value;
    }

    /**
     * Rule "rgpd" => ['private' => true]: the value must not be displayed back as is.
     */
    public static function isPrivate(array $rules, string $field): bool
    {
        return (bool)($rules[$field]['rgpd']['private'] ?? false);
    }
}
