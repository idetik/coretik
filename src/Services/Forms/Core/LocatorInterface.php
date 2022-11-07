<?php

namespace Coretik\Services\Forms\Core;

interface LocatorInterface
{
    public function setConfig(ConfigInterface $config): void;
    public function locateTemplate(string $template): ?string;
    public function locatePart(string $template): ?string;
}
