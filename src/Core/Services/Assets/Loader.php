<?php

namespace Coretik\Services\Assets;

class Loader
{
    protected $assetPath;
    protected $assetUrl;
    protected $assetVersion;

    public function __construct(string $path, string $url, $version_path = false)
    {
        $this->assetPath = $path;
        $this->assetUrl = $url;

        if ($version_path) {
            if (!file_exists($version_path)) {
                $this->assetVersion = false;
            } else {
                $this->assetVersion = \intval(\file_get_contents($version_path));
            }
        }
    }

    public function url($file, $versioning = false)
    {
        $path = $this->assetUrl . $file;

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
        return $this->assetPath . $file;
    }

    public function version()
    {
        return $this->assetVersion;
    }
}
