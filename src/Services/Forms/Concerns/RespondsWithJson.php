<?php

namespace Coretik\Services\Forms\Concerns;

use Coretik\Services\Forms\Core\JsonResponse;

/**
 * JSON answers read by the forms scripts (src/Services/Forms/scripts): see Core\JsonResponse for the payload.
 *
 * For a class extending Coretik\Services\Forms\AsyncForm.
 */
trait RespondsWithJson
{
    protected ?string $uniqid = null;

    /**
     * Unique id of the form in the page (id attribute, ids of the fields...).
     */
    public function uniqid(): string
    {
        if (empty($this->uniqid)) {
            $this->uniqid = \uniqid('form-' . $this->id() . '-');
        }
        return $this->uniqid;
    }

    /**
     * View data shared by the JSON forms: "json_api" tells the templates to print the JSON containers
     * (parts/json-form-open) instead of the HTML refresh of the AsyncForm.
     */
    protected function jsonViewData(array $data = []): array
    {
        return \array_merge($data, [
            'json_api' => true,
            'uniqid' => $this->uniqid(),
        ]);
    }

    /**
     * Sends the JSON answer and stops the request (wp_send_json() dies).
     */
    public function respond(array $data = [], int $http_status_code = 200): void
    {
        \wp_send_json(
            [
                'success' => true,
                'data' => $data,
            ],
            $http_status_code,
            JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * The browser goes to $url (window.location) once the submission is valid.
     */
    protected function redirectTo(string $url, int $status = 200, array $data = []): void
    {
        $this->respond([
            'valid' => true,
            'redirect_url' => $url,
            'data' => $data,
        ]);
    }

    /**
     * Global errors (not bound to a field): exceptions thrown by run(), rate limit, expired nonce...
     * Displayed as plain text by the scripts.
     *
     * @return string[]
     */
    protected function globalMessages(): array
    {
        $messages = $this->view_data['errors'] ?? [];

        switch ($this->submission_result['error'] ?? null) {
            case 'Too many submissions':
                $messages = [\__('Trop de tentatives, veuillez réessayer dans quelques minutes.', 'coretik')];
                break;
            case 'Wrong / empty nonce':
                // Typically a page served from a cache older than the nonce lifetime
                $messages = [\__('Votre session a expiré, veuillez recharger la page puis réessayer.', 'coretik')];
                break;
        }

        return \array_values(\array_unique(\array_filter((array)$messages, 'is_string')));
    }

    /**
     * @param array<string, \Coretik\Services\Forms\Core\Validation\Constraints\Constraint[]> $errors
     */
    protected function formatErrors(array $errors, ?int $step = null): array
    {
        return JsonResponse::errors($errors, $step);
    }

    protected function formatValues(array $values, ?int $step = null): array
    {
        return JsonResponse::values(
            $values,
            fn (string $field) => (string)$this->fieldLabel($field),
            fn (string $field) => $this->fieldContainsPrivateData($field),
            $step
        );
    }

    /**
     * Rule "rgpd" => ['private' => true]: the value must not be displayed back as is.
     */
    public function fieldContainsPrivateData(string $field): bool
    {
        return JsonResponse::isPrivate($this->getRules(), $field);
    }
}
