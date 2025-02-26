<?php

namespace Coretik\Services\Assets;

use function Globalis\WP\Cubi\str_ends_with;

class Loader
{
    protected $base;
    protected $version;
    protected $handleFamily;
    protected $useScriptVersion = false;
    protected $useStyleVersion = false;

    public function __construct(string $base = '', int $version = 0, string $handler = '')
    {
        $this->base = $base;
        $this->version = $version;
        $this->handleFamily = !empty($handler) ? $handler : sanitize_title(\get_option('blogname'), 'coretik');

        if (defined('ASSETS_VERSIONING_SCRIPTS')) {
            $this->useScriptVersion = ASSETS_VERSIONING_SCRIPTS;
        }

        if (defined('ASSETS_VERSIONING_STYLES')) {
            $this->useStyleVersion = ASSETS_VERSIONING_STYLES;
        }
    }

    public function url($file, ?bool $versioning = null)
    {
        $file = ltrim($this->base . $file, '/');
        if (empty($file)) {
            $url = get_stylesheet_directory_uri();
        } elseif (file_exists(get_stylesheet_directory() . '/' . $file)) {
            $url = get_stylesheet_directory_uri() . '/' . $file;
        } else {
            $url = get_template_directory_uri() . '/' . $file;
        }

        if ($versioning ?? $this->useScriptVersion) {
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

    public function family()
    {
        return $this->handleFamily;
    }

    public function enqueueScript(string $handleItem, string $file, array $deps = [], $ver = null, $in_footer = true, $async = true)
    {
        $handle = sprintf('%s/%s', $this->handleFamily, $handleItem);
        \wp_enqueue_script(
            $handle,
            $this->url($file, $this->useScriptVersion),
            $deps,
            $ver,
            $in_footer
        );

        if ($async) {
            \add_filter('script_loader_tag', function ($tag, $scriptHandle, $src) use ($handle) {
                if ($scriptHandle === $handle) {
                    return static::makeAsyncTag($tag);
                }
                return $tag;
            }, 10, 3);
        }
    }

    public function enqueueScriptModule(string $handleItem, string $file, array $deps = [], $ver = null, $in_footer = true, $async = true)
    {
        if (!\function_exists('wp_enqueue_script_module')) {
            $this->enqueueScript($handleItem, $file, $deps, $ver, $in_footer, $async);
            \add_filter('script_loader_tag', function ($tag, $scriptHandle, $src) use ($handleItem) {
                if (str_ends_with($scriptHandle, $handleItem)) {
                    return static::makeModularTag($tag);
                }
                return $tag;
            }, 10, 3);
        } else {
            $handle = sprintf('%s/%s', $this->handleFamily, $handleItem);
            \wp_enqueue_script_module(
                $handle,
                $this->url($file, $this->useScriptVersion),
                $deps,
                $ver,
            );
        }

    }

    public function enqueueModularScript(string $handleItem, string $file, array $deps = [], $ver = null, $in_footer = true, $async = true)
    {
        $this->enqueueScriptModule($handleItem, $file, $deps, $ver, $in_footer, $async);
    }

    public function enqueueNoModularScript(string $handleItem, string $file, array $deps = [], $ver = null, $in_footer = true, $async = true)
    {
        $this->enqueueScript($handleItem, $file, $deps, $ver, $in_footer, $async);
        \add_filter('script_loader_tag', function ($tag, $scriptHandle, $src) use ($handleItem) {
            if (str_ends_with($scriptHandle, $handleItem)) {
                return static::makeNoModularTag($tag);
            }
            return $tag;
        }, 10, 3);
    }

    public function enqueueStyle(string $handleItem, string $file, array $deps = [], $ver = null, $media = 'all')
    {
        \wp_enqueue_style(
            sprintf('%s/%s', $this->handleFamily, $handleItem),
            $this->url($file, $this->useStyleVersion),
            $deps,
            $ver,
            $media
        );
    }

    public function enqueueDeferredStyle(string $handleItem, string $file, array $deps = [], $ver = null)
    {
        \add_filter('style_loader_tag', function ($tag, $scriptHandle, $src) use ($handleItem) {
            if (str_ends_with($scriptHandle, $handleItem)) {
                return static::makeDeferredStyleTag($tag);
            }
            return $tag;
        }, 10, 3);
        $this->enqueueStyle($handleItem, $file, $deps, $ver);
    }

    public static function makeAsyncTag($tag)
    {
        $tag = str_replace('src=', 'async src=', $tag);
        return $tag;
    }

    public static function makeModularTag($tag)
    {
        $tag = str_replace('src=', 'type="module" src=', $tag);
        return $tag;
    }

    public static function makeNoModularTag($tag)
    {
        $tag = str_replace('src=', 'nomodule src=', $tag);
        return $tag;
    }

    public static function makeDeferredStyleTag($tag)
    {
        $tag = str_replace('rel="stylesheet"', 'rel="preload" as="style" onload="this.onload=null;this.rel=\"stylesheet\"', $tag);
        return $tag;
    }
}
