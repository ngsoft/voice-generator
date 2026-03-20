<?php

use NGSOFT\Routing\Interface\UrlGeneratorInterface;
use Service\ViteService;

/**
 * Load assets from a public path.
 *
 * @param string $path
 * @param bool   $absolute
 *
 * @return string
 */
function asset($path, $absolute = false)
{
    static $cache = [], $root = null, $localPath = null, $dir = '/assets', $base = '';

    if ( ! $root)
    {
        $request = Services::getRequest();
        $base    = rtrim($request->getBasePath(), '/');
        $root    = rtrim($request->getSchemeAndHttpHost() . $base, '/');
    }

    if ( ! $localPath)
    {
        $localPath = resolve_path('%public%', $dir);
    }

    $prefix       = $absolute ? $root : $base;

    if (env_get('WEBP_ALTERNATIVE'))
    {
        $webpPath = preg_replace('#\.(png|jpg|jpeg)$#', '.webp', $path);

        if (
            $path !== $webpPath
            && is_file($localPath . '/' . ltrim($webpPath, '/'))
        ) {
            Services::getLogger()->debug('%s => %s', [
                basename($path),
                basename($webpPath),
            ]);
            $path = $webpPath;
        }
    }

    // minified versions
    if (env_get('MIN_ALTERNATIVE') && ! preg_match('#\.min\.\w+$#i', $path))
    {
        $newPath = preg_replace('#\.(\w+)$#', '.min.$1', $path);

        if (is_file($localPath . '/' . ltrim($newPath, '/')))
        {
            Services::getLogger()->debug('%s => %s', [
                basename($path),
                basename($newPath),
            ]);
            $path = $newPath;
        }
    }

    $local        = $localPath . '/' . ltrim($path, '/');

    if (is_dev() && ! is_file($local))
    {
        Services::getLogger()->warn('cannot find asset file %s in %s', [$path, $local]);
    }

    if ( ! isset($cache[$prefix . $path]))
    {
        $cache[$prefix . $path] = $prefix . $dir . '/' . ltrim($path, '/');
    }

    return $cache[$prefix . $path];
}

/**
 * Load Asset from public path.
 *
 * @param string $asset
 *
 * @return string
 */
function render_asset($asset)
{
    static $dir = '/assets';

    if ($asset && is_file($path = resolve_path('%public%', $dir, $asset)))
    {
        return @file_get_contents($path) ?: '';
    }
    return '';
}

function vite(array|string|null $entrypoints = null): ViteService
{
    return Services::make(ViteService::class)->load($entrypoints);
}

/**
 * @param string $title
 * @param bool   $withSuffix
 */
function title($title, $withSuffix = true)
{
    if ($withSuffix)
    {
        $title = rtrim(sprintf('%s - %s', $title, __(env_get('APP_TITLE', '', false))), ' -');
    }
    Services::getPage()->setTitle($title);
}

/**
 * @param string $src
 */
function script($src)
{
    Services::getPage()->addScript($src);
}

/**
 * @param string $src
 */
function style($src)
{
    Services::getPage()->addStyle($src);
}

/**
 * @param bool $prepend
 */
function to_body($prepend = false)
{
    $content = @ob_get_clean();

    if (false !== $content)
    {
        Services::getPage()->addBody($content, $prepend);
        @ob_start();
    }
}

/**
 * @param bool $prepend
 */
function to_head($prepend = false)
{
    $content = @ob_get_clean();

    if (false !== $content)
    {
        Services::getPage()->addHead($content, $prepend);
        @ob_start();
    }
}

/**
 * Read/Write view attributes.
 *
 * @param string $attr
 * @param mixed  $value
 *
 * @return mixed
 */
function attr($attr, $value = '__VALUE_UNDEFINED__')
{
    if ('__VALUE_UNDEFINED__' === $value)
    {
        $value = Services::getPage()->getAttribute($attr);
    } else
    {
        $value = value($value);
        Services::getPage()->setAttribute($attr, $value);
    }
    return $value;
}

/**
 * @param string $view
 */
function extend($view)
{
    Services::getPage()->addExtend($view);
}

/**
 * @param int $code
 */
function status_code($code)
{
    Services::getPage()->setStatusCode($code);
}

/**
 * Generate route path.
 */
function path(string $route, array $params = [], array $query = [])
{
    return Services::make(UrlGeneratorInterface::class)
        ->generate($route, $params, $query);
}

/**
 * Generate route url.
 */
function url(string $route, array $params = [], array $query = [])
{
    return Services::make(UrlGeneratorInterface::class)
        ->generate($route, $params, $query, UrlGeneratorInterface::ABSOLUTE_URL);
}
