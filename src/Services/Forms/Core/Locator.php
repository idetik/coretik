<?php

namespace Coretik\Services\Forms\Core;

/**
 * Locates the form templates in the theme (Config::getTemplateDir(), "templates/forms/" by default).
 * A template missing in the theme falls back to the default views of Coretik (src/Services/Forms/views),
 * e.g. tunnel-form, parts/json-form-open, steps/summary: copy one in the theme to override it.
 */
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

    public function locateTemplate(string $template): ?string
    {
        $template_path = $this->config->getTemplateDir() . $template . '.php';
        return locate_template($template_path) ?: static::locateDefault($template);
    }

    public function locatePart(string $part): ?string
    {
        $part_path = $this->config->getTemplateDir() . $part . '.php';
        return locate_template($part_path) ?: static::locateDefault($part);
    }

    /**
     * Default view shipped by Coretik, or an empty string (as locate_template()).
     */
    public static function locateDefault(string $template): string
    {
        $file = static::defaultViewsDir() . '/' . $template . '.php';
        return (!\str_contains($template, '..') && \is_file($file)) ? $file : '';
    }

    public static function defaultViewsDir(): string
    {
        return \dirname(__DIR__) . '/views';
    }
}
