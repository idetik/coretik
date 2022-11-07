<?php

namespace Coretik\Services\Forms;

use Coretik\Services\Forms\Core\ConfigInterface;
use Coretik\Services\Forms\Core\LocatorInterface;
use Coretik\Services\Forms\Core\Locator;

class Config implements ConfigInterface
{
    protected $templateDir = 'templates/forms/';
    protected $formPrefix = 'coretik';
    protected $cssClassError = 'form-group-error';
    protected LocatorInterface $locator;

    public function getTemplateDir(): string
    {
        return $this->templateDir;
    }

    public function setTemplateDir(string $templateDir): self
    {
        $this->templateDir = $templateDir;
        return $this;
    }

    public function getFormPrefix(): string
    {
        return $this->formPrefix;
    }

    public function setFormPrefix(string $formPrefix): self
    {
        $this->formPrefix = $formPrefix;
        return $this;
    }

    public function getCssErrorClass(): string
    {
        return $this->cssClassError;
    }

    public function setCssErrorClass(string $cssClassError): self
    {
        $this->cssClassError = $cssClassError;
        return $this;
    }

    public function locator(): LocatorInterface
    {
        if (empty($this->locator)) {
            $this->setLocator(new Locator($this));
        }
        return $this->locator;
    }

    public function setLocator(LocatorInterface $locator): self
    {
        $locator->setConfig($this);
        $this->locator = $locator;
        return $this;
    }
}
