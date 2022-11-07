<?php

namespace Coretik\Services\Forms\Core;

interface ConfigInterface
{
    public function getTemplateDir(): string;
    public function setTemplateDir(string $templateDir): self;
    public function getFormPrefix(): string;
    public function setFormPrefix(string $formPrefix): self;
    public function getCssErrorClass(): string;
    public function setCssErrorClass(string $cssErrorClass): self;
    public function locator(): LocatorInterface;
    public function setLocator(LocatorInterface $locator): self;
}
