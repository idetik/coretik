<?php

namespace Coretik\Services\Email\Viewer;

use Coretik\Services\Email\Email;

/**
 * Rendered email shown by the viewer: envelope (recipient, headers, subject) and HTML
 */
final class Preview
{
    /**
     * @param string|string[] $to
     * @param string[] $headers "Name: value" lines, as given to wp_mail()
     */
    public function __construct(
        public readonly string $subject,
        public readonly string|array $to,
        public readonly string $html,
        public readonly array $headers = [],
    ) {
    }

    /**
     * From an email ready to send (Email::render(): CSS inlined)
     */
    public static function fromEmail(Email $email): static
    {
        return new static($email->getSubject(), $email->to ?? '', $email->render(), (array)$email->headers);
    }

    /**
     * Value of a header, case insensitive: header('From') => "Name <address>"
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $header) {
            [$headerName, $value] = array_pad(explode(':', (string)$header, 2), 2, '');
            if (0 === strcasecmp(trim($headerName), $name)) {
                return trim($value);
            }
        }

        return null;
    }

    public function recipients(): string
    {
        return implode(', ', (array)$this->to);
    }
}
