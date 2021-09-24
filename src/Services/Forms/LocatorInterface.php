<?php

namespace Coretik\Services\Forms;

interface LocatorInterface
{
    public function setConfig(ConfigInterface $config): void;
    public function locateRules(string $template): ?string;
    public function locateTemplate(string $template): ?string;
    public function locatePart(string $template): ?string;
}
