<?php

/** @noinspection HtmlUnknownTarget */

use NGSOFT\Routing\Interface\UrlGeneratorInterface;
use Service\ViteService;
use TemplateEngine\Context;
use TemplateEngine\EscapeStrategy;
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
    $context = get_context();
    return tap(
        Services::make(ViteService::class)->getHtml(
            $entrypoints,
            ! str_contains($context->getAttribute('vite_block', ''), '@vite/client')
        ),
        fn (string $html) => $context->updateAttribute(
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

/**
 * Create a script tag.
 *
 * @param string            $url
 * @param bool              $defer
 * @param null|false|string $block block name
 *
 * @return string
 */
function script(string $url, bool $defer = false, false|string|null $block = 'scripts'): string
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
            "{$block}_block",
            fn (string $content) => $content . $html,
            ''
        );
    }

    return $html;
}

/**
 * Create a style tag.
 *
 * @param string            $url
 * @param null|false|string $block block name
 *
 * @return string
 */
function style(string $url, false|string|null $block = 'styles'): string
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
        $context->updateAttribute("{$block}_block", fn (string $content) => $content . $html);
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

/**
 * @param string $name Block name
 */
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

/**
 * Ends a block.
 *
 * @return string the block content
 */
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

/**
 * Escape a string.
 */
function escape(string|Stringable $input, EscapeStrategy|string $mode = EscapeStrategy::HTML): string
{
    return match ($mode instanceof EscapeStrategy ? $mode->value : $mode)
    {
        'html_attr', 'html_attr_relaxed' => value(fn () => preg_replace_callback(match ($mode)
        {
            'html_attr'         => '#[^a-zA-Z0-9,\.\-_]#Su',
            'html_attr_relaxed' => '#[^a-zA-Z0-9,\.\-_:@\[\]]#Su',
        }, static function (array $matches)
        {
            $chr = $matches[0];
            $ord = \ord($chr[0]);

            if (($ord <= 0x1F && "\t" != $chr && "\n" != $chr && "\r" != $chr) || ($ord >= 0x7F && $ord <= 0x9F))
            {
                return '&#xFFFD;';
            }

            if (1 === \strlen($chr))
            {
                return match ($ord)
                {
                    34      => '&quot;',
                    38      => '&amp;',
                    60      => '&lt;',
                    62      => '&gt;',
                    default => \sprintf('&#x%02X;', $ord),
                };
            }
            return \sprintf('&#x%04X;', mb_ord($chr, 'UTF-8'));
        }, $input)),
        'html'  => htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'css'   => preg_replace_callback('#[^a-zA-Z0-9]#Su', static function (array $matches)
        {
            $char = $matches[0];
            return \sprintf('\%X ', 1 === \strlen($char) ? \ord($char) : mb_ord($char, 'UTF-8'));
        }, $input),
        'js'    => preg_replace_callback('#[^a-zA-Z0-9,._]#Su', static function (array $matches)
        {
            $char      = $matches[0];

            if (match ($char)
            {
                '\\'    => '\\\\',
                '/'     => '\/',
                "\x08"  => '\b',
                "\x0C"  => '\f',
                "\x0A"  => '\n',
                "\x0D"  => '\r',
                "\x09"  => '\t',
                default => false,
            })
            {
                return $char;
            }

            $codepoint = mb_ord($char, 'UTF-8');

            if (0x10000 > $codepoint)
            {
                return \sprintf('\u%04X', $codepoint);
            }
            $u         = $codepoint - 0x10000;
            $high      = 0xD800 | ($u >> 10);
            $low       = 0xDC00 | ($u & 0x3FF);
            return \sprintf('\u%04X\u%04X', $high, $low);
        }, $input),
        'url'   => rawurlencode($input),
        default => $input,
    };
}

/**
 * Load a view to be included.
 */
function include_view(string $name, array $attributes = []): string
{
    $context  = get_context();
    /** @var Renderer $renderer */
    $renderer = clone $context->getAttribute(Renderer::class);
    return $renderer->renderView($name, array_replace($renderer->getCurrentAttributes() ?? [], $attributes));
}
