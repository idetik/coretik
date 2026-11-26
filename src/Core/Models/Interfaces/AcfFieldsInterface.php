<?php

namespace Coretik\Core\Models\Interfaces;

/**
 * Models with ACF fields (see the AcfFields trait)
 */
interface AcfFieldsInterface extends MetableInterface
{
    public function getField(string $key);
    public function acfId(): string;
}
