<?php

/** @noinspection HtmlUnknownTarget */

use NGSOFT\Routing\Interface\UrlGeneratorInterface;
use Service\ViteService;
use TemplateEngine\Context;
use TemplateEngine\Renderer;

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
    static $cache = [], $root = null, $localPath = null, $base = '';

    if ( ! $root)
    {
        $request = Services::getRequest();
        $base    = rtrim($request->getBasePath(), '/');
        $root    = rtrim($request->getSchemeAndHttpHost() . $base, '/');
    }

    if ( ! $localPath)
    {
        $localPath = resolve_path('%public%');
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
        $cache[$prefix . $path] = $prefix . '/' . ltrim($path, '/');
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
    if ($asset && is_file($path = resolve_path('%public%', $asset)))
    {
        return @file_get_contents($path) ?: '';
    }
    return '';
}

function vite(array|string|null $entrypoints = null): string
{
    return tap(
        Services::make(ViteService::class)->getHtml($entrypoints),
        fn (string $html) => get_context()->updateAttribute(
            'vite_block',
            fn (string $content) => "{$content}{$html}",
            ''
        )
    );
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
    Services::make(Renderer::class)->setAttribute(
        'page_title',
        $title
    );
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
        $value = get_context()->getAttribute($attr);
    } else
    {
        get_context()->setAttribute($attr, $value = value($value));
    }
    return $value;
}

/**
 * @param string $view
 */
function extend($view)
{
    if (get_context()->hasAttribute('next-view'))
    {
        throw new RuntimeException('A view cannot extends more than one view');
    }
    get_context()->addAttribute(
        'next-view',
        $view
    );
}

/**
 * @param int $code
 *
 * @return int
 */
function status_code($code)
{
    Services::make(Renderer::class)->setAttribute(
        'status_code',
        $code
    );
    return $code;
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

function get_context(): Context
{
    return Renderer::getCurrentContext();
}

function script(string $url, bool $defer = false, bool $block = false): string
{
    $context = get_context();
    $context->updateAttribute(
        'preload_block',
        fn (string $content) => $content . renderTag('link', [
            'rel'  => 'preload',
            'as'   => 'script',
            'href' => $url,
        ]) . "\n",
        ''
    );

    if (is_dev())
    {
        $url .= str_contains($url, '?') ? '&' : '?';
        $url .= '_=' . time();
    }
    $html    = renderTag('script', ['src' => $url, 'defer' => $defer]) . "\n";

    if ($block)
    {
        $context->updateAttribute(
            'scripts_block',
            fn (string $content) => $content . $html,
            ''
        );
    }

    return $html;
}

function style(string $url, bool $block = false): string
{
    $context = get_context();
    $context->updateAttribute(
        'preload_block',
        fn (string $content) => $content . renderTag('link', [
            'rel'  => 'preload',
            'as'   => 'style',
            'href' => $url,
        ]) . "\n",
        ''
    );

    if (is_dev())
    {
        $url .= str_contains($url, '?') ? '&' : '?';
        $url .= '_=' . time();
    }
    $html    = renderTag('link', ['href' => $url, 'rel' => 'stylesheet', 'type' => 'text/css']) . "\n";

    if ($block)
    {
        $context->updateAttribute('styles_block', fn (string $content) => $content . $html);
    }
    return $html;
}

function set_meta(string $name, string $content)
{
    get_context()->updateAttribute(
        'meta_block',
        fn (string $html) => $html . renderTag(
            'meta',
            ['name' => $name, 'content' => $content]
        ) . "\n",
        ''
    );
}

function start_block(string $name)
{
    $context = get_context();

    if ($context->hasAttribute('current_block_name'))
    {
        throw new RuntimeException('A block named "' . $context->getAttribute('current_block_name') . '" is currently defined');
    }

    $context->setAttribute('current_block_name', $name);
    @ob_start();
}

function end_block(): string
{
    $context = get_context();

    if ( ! $context->hasAttribute('current_block_name'))
    {
        return '';
    }
    $name    = $context->pullAttribute('current_block_name');
    $content = @ob_get_contents() ?: '';
    $context->setAttribute("block_{$name}", $content);
    return $content;
}
