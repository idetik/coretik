<?php

namespace Coretik\Services\Assets;

class Loader
{
    protected $base;
    protected $version;

    public function __construct(string $base = '', int $version = 0)
    {
        $this->base = $base;
        $this->version = $version;
    }

    public function url($file, $versioning = false)
    {
        $path = get_theme_file_uri($this->base . $file);
        if ($versioning) {
            $version = $this->version();
            if (false != $version && !empty($version)) {
                $path = str_replace(['.css', '.js'], ['-' . $version . '.css', '-' . $version . '.js'], $path);
            }
        }
        return $path;
    }

    public function path($file)
    {
        return get_theme_file_path($this->base . $file);
    }

    public function version()
    {
        return $this->version;
    }
}
