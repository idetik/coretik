<?php

namespace Coretik\Services\Email;

use Pelago\Emogrifier\CssInliner;

class Builder
{
    protected $stylesheetPath;
    protected $basePath;

    public function basePath()
    {
        return $this->basePath;
    }

    public function stylesheetPath()
    {
        return $this->stylesheetPath;
    }

    public function setBasePath(string $basePath)
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function setStylesheetPath(string $stylesheetPath)
    {
        $this->stylesheetPath = $stylesheetPath;
        return $this;
    }
}
