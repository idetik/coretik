<?php

namespace Coretik\Services\Email\Viewer;

/**
 * An email listed by the viewer
 */
final class Entry
{
    /**
     * @param string $key identifier, used in the viewer URLs
     * @param string $group heading of the list ("Socle", "Compte"…), entries without group come first
     * @param string $description what the email is for
     * @param string $trigger when it is sent ("Automatique : mot de passe oublié", "Manuel : dossier"…)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $group = '',
        public readonly string $description = '',
        public readonly string $trigger = '',
    ) {
    }
}
