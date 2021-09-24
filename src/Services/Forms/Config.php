<?php

namespace Coretik\Services\Forms;

class Config implements ConfigInterface
{
    protected $templateDir = 'templates/forms/';
    protected $formFile = 'form.php';
    protected $formRulesFile = 'rules.php';
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

    public function getFormFile(): string
    {
        return $this->formFile;
    }

    public function setFormFile(string $formFile): self
    {
        $this->formFile = $formFile;
        return $this;
    }

    public function getFormRulesFile(): string
    {
        return $this->formRulesFile;
    }

    public function setFormRulesFile(string $formRulesFile): self
    {
        $this->formRulesFile = $formRulesFile;
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
