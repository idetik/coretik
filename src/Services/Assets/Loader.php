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
        $file = ltrim($this->base . $file, '/');
        if (empty($file)) {
            $url = get_stylesheet_directory_uri();
        } elseif (file_exists(get_stylesheet_directory() . '/' . $file)) {
            $url = get_stylesheet_directory_uri() . '/' . $file;
        } else {
            $url = get_template_directory_uri() . '/' . $file;
        }

        if ($versioning) {
            $version = $this->version();
            if (false != $version && !empty($version)) {
                $url = str_replace(['.css', '.js'], ['-' . $version . '.css', '-' . $version . '.js'], $url);
            }
        }
        return $url;
    }

    public function path($file)
    {
        $file = ltrim($this->base . $file, '/');
        if (empty($file)) {
            $path = get_stylesheet_directory();
        } elseif (file_exists(get_stylesheet_directory() . '/' . $file)) {
            $path = get_stylesheet_directory() . '/' . $file;
        } else {
            $path = get_template_directory() . '/' . $file;
        }
        return $path;
    }

    public function version()
    {
        return $this->version;
    }
}
