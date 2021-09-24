<?php

namespace Coretik\Services\Forms;

class Locator implements LocatorInterface
{
    private $config;

    public function __construct(ConfigInterface $config)
    {
        $this->setConfig($config);
    }

    public function setConfig(ConfigInterface $config): void
    {
        $this->config = $config;
    }

    public function locateRules(string $template): ?string
    {
        $rules_path = $this->config->getTemplateDir() . $template . '/' . $this->config->getFormRulesFile();
        return locate_template($rules_path);
    }

    public function locateTemplate(string $template): ?string
    {
        $template_path = $this->config->getTemplateDir() . $template . '/' . $this->config->getFormFile();
        return locate_template($template_path);
    }

    public function locatePart(string $part): ?string
    {
        $part_path = $this->config->getTemplateDir() . $part . '.php';
        return locate_template($part_path);
    }
}
