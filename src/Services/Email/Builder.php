<?php

namespace Coretik\Services\Email;

use Globalis\WP\Cubi;
use Pelago\Emogrifier\CssInliner;

class Builder
{
    protected $stylesheetPath;
    protected $basePath;
    protected $css;

    public function __construct(string $stylesheet_path, string $base_path)
    {
        $this->stylesheetPath = $stylesheet_path;
        $this->basePath = $base_path;
    }

    public function basePath()
    {
        return $this->basePath;
    }

    public function stylesheetPath()
    {
        return $this->stylesheetPath;
    }
}
