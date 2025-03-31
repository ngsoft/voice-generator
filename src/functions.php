<?php

/** @noinspection ALL */

use NGSOFT\Facades\Container;
use View\TemplateView;

require_once __DIR__ . '/libs/poly.php';
require_once __DIR__ . '/libs/Lockable.php';
require_once __DIR__ . '/libs/CurlHandler.php';
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @return array
 */
function getFileList(string $dir, string $filter = '', DateTimeInterface|int|string $date = 0)
{
    $files = [];

    if ($date instanceof DateTimeInterface)
    {
        $date = $date->getTimestamp();
    }

    if (is_string($date))
    {
        $date = strtotime($date);
    }

    if ( ! is_int($date))
    {
        $date = 0;
    }

    if (is_dir($dir))
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        /**
         * @var string      $path
         * @var SplFileInfo $info
         */
        foreach ($iterator as $info)
        {
            if ( ! $info->isFile())
            {
                continue;
            }

            if ( ! empty($filter) && false === strpos($info->getFilename(), $filter))
            {
                continue;
            }

            if ($info->getMTime() >= $date)
            {
                $files[] = realpath($info->getPathname());
            }
        }
    }
    usort($files, function ($a, $b)
    {
        $numa = $numb = 0;

        if (preg_match('#(\d+)#', basename($a), $matches))
        {
            $numa = intval($matches[1]);
        }

        if (preg_match('#(\d+)#', basename($b), $matches))
        {
            $numb = intval($matches[1]);
        }
        return $numa - $numb;
    });

    return $files;
}

function extends_template(string $template)
{
    Container::get(TemplateView::class)->setExtend($template);
}

function set_attr(string $name, mixed $value): void
{
    Container::get(TemplateView::class)->setAttribute($name, $value);
}

function get_attr(string $name, mixed $default = null): mixed
{
    return Container::get(TemplateView::class)->getAttribute($name, $default);
}

function getBasePath(): string
{
    static $basePath;

    if ( ! isset($basePath))
    {
        $basePath = Env::getItem('APP_BASEPATH');

        if ( ! isset($basePath))
        {
            $basePath = preg_replace('#/index.php.*$#', '', $_SERVER['SCRIPT_NAME'] ?? '');
        }

        if ( ! str_starts_with($basePath, '/'))
        {
            $basePath = "/{$basePath}";
        }

        $basePath = rtrim($basePath, '/');
    }
    return $basePath;
}

if ( ! function_exists('require_secure'))
{
    /**
     * @param string $filename
     */
    function require_secure($filename, array $data = []): mixed
    {
        if (is_file($filename))
        {
            extract($data);
            return include $filename;
        }

        return null;
    }
}

if ( ! function_exists('renderArgs'))
{
    /**
     * Renders arguments as `$prefix.$key="$value"`.
     * Also encodes values to string if value is null or boolean false, it will not be rendered
     * replaces `myArg => true` to `my-arg` and `myArg => true` to ``.
     *
     * @param iterable $arguments
     * @param string   $prefix
     *
     * @return string
     *
     * @throws InvalidArgumentException if one of the arguments is invalid
     *
     * @author Aymeric Anger
     *
     * @example renderArgs(['checked'=>$cond, 'selected'=>$cond]) where $cond is a boolean
     * @example renderArgs(['value'=> "value", "data"=>["jsValue"=>10]]) => `value="value" data-js-value="10"`
     * @example renderArgs(["jsValue"=>10], "data-") => `data-js-value="10"`
     *
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    function renderArgs($arguments, $prefix = '')
    {
        $result = [];

        if ( ! is_string($prefix))
        {
            throw new InvalidArgumentException('$prefix is not a string');
        }

        // is_iterable() for php < 7.1
        if ( ! is_iterable($arguments))
        {
            throw new InvalidArgumentException('$arguments is not iterable');
        }

        foreach ($arguments as $key => $value)
        {
            if (false === $value || null === $value)
            {
                continue;
            }

            // dataset helper
            if ('data' === $key && (is_array($value) || $value instanceof Traversable))
            {
                if ($tmp = renderArgs($value, 'data-'))
                {
                    $result[] = $tmp;
                }
                continue;
            }

            if ( ! is_scalar($value))
            {
                continue;
            }

            if ( ! is_string($key))
            {
                if ( ! is_string($value))
                {
                    continue;
                }
                $key   = $value;
                $value = true;
            }

            $renderKey          = preg_replace_callback(
                '#[A-Z]#',
                function ($matches)
                {
                    return '-' . strtolower($matches[0]);
                },
                lcfirst($prefix . $key)
            );

            if (true === $value)
            {
                $result[$renderKey] = $renderKey;
                continue;
            }

            if ( ! is_string($value))
            {
                $value = json_encode($value);
            }

            $result[$renderKey] = sprintf('%s="%s"', $renderKey, $value);
        }

        return implode(' ', $result);
    }
}

if ( ! function_exists('renderTag'))
{
    /**
     * @param string|Stringable $tagName
     * @param iterable          $arguments
     * @param string|Stringable $innerHtml
     *
     * @return string
     */
    function renderTag($tagName, $arguments = [], $innerHtml = '')
    {
        /**
         * @var string[] $voidElements
         *
         * @see https://developer.mozilla.org/en-US/docs/Glossary/Void_element
         */
        static $voidElements = [
            'area', 'base', 'br', 'col',
            'embed', 'hr', 'img', 'input',
            'link', 'meta', 'param', 'source',
            'track', 'wbr',
        ];

        if (is_object($tagName) && method_exists($tagName, '__toString'))
        {
            $tagName = (string) $tagName;
        }

        if (is_object($innerHtml) && method_exists($innerHtml, '__toString'))
        {
            $innerHtml = (string) $innerHtml;
        }

        if ( ! is_string($tagName))
        {
            throw new InvalidArgumentException('$tagName is not a string');
        }

        if ( ! is_string($innerHtml))
        {
            throw new InvalidArgumentException('$innerHtml is not a string');
        }

        $arguments           = rtrim(' ' . renderArgs($arguments));

        $tagName             = strtolower($tagName);

        if (in_array($tagName, $voidElements))
        {
            return sprintf('<%s%s>', $tagName, $arguments);
        }

        return sprintf('<%s%s>%s</%s>', strtolower($tagName), $arguments, $innerHtml, $tagName);
    }
}

if ( ! function_exists('constant_get'))
{
    /**
     * @param string     $name
     * @param null|mixed $defaultValue
     *
     * @return mixed
     */
    function constant_get($name, $defaultValue = null)
    {
        if ( ! defined($name))
        {
            return value($defaultValue, $name);
        }
        return constant($name);
    }
}

if ( ! function_exists('env_get'))
{
    /**
     * @param string     $name
     * @param null|mixed $defaultValue
     *
     * @return mixed
     */
    function env_get($name, $defaultValue = null)
    {
        if ( ! isset($_ENV[$name]))
        {
            return value($defaultValue, $name);
        }
        return $_ENV[$name];
    }
}

if ( ! function_exists('str_convert_encoding'))
{
    /**
     * Replaces mb_convert_encoding with a better one.
     *
     * @param string $str
     * @param string $encoding
     *
     * @return string
     */
    function str_convert_encoding($str, $encoding = 'UTF-8')
    {
        static $types = null;

        if (null === $types)
        {
            $types = [];

            foreach (mb_list_encodings() as $real)
            {
                $types[strtolower($real)] = $real;
            }
        }

        if ( ! isset($types[strtolower($encoding)]))
        {
            return $str;
        }

        $toEncoding   = $types[strtolower($encoding)];

        if (($currentEncoding = mb_detect_encoding($str, $types, true)) && $currentEncoding !== $toEncoding)
        {
            $str = mb_convert_encoding($str, $toEncoding, $currentEncoding);
        }

        return $str;
    }
}

if ( ! function_exists('getallheaders'))
{
    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * @phan-suppress PhanRedefineFunctionInternal
     *
     * @return array<string,string> the HTTP header key/value pairs
     */
    function getallheaders()
    {
        $headers     = [];

        $copy_server = [
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        ];

        foreach ($_SERVER as $key => $value)
        {
            if ('HTTP_' === substr($key, 0, 5))
            {
                $key = substr($key, 5);

                if ( ! isset($copy_server[$key]) || ! isset($_SERVER[$key]))
                {
                    $key           = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($copy_server[$key]))
            {
                $headers[$copy_server[$key]] = $value;
            }
        }

        if ( ! isset($headers['Authorization']))
        {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
            {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER']))
            {
                $basic_pass               = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST']))
            {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }

        return $headers;
    }
}

if ( ! function_exists('decode_value'))
{
    /**
     * Uses json_decode to convert strings to the right type.
     *
     * @param mixed $value a value to be decoded
     *
     * @return mixed
     */
    function decode_value($value)
    {
        $value = value($value);

        if (is_string($value))
        {
            $decoded = json_decode($value, true);
            return null === $decoded ? $value : $decoded;
        }

        if (is_array($value))
        {
            foreach ($value as &$item)
            {
                $item = decode_value($item);
            }
        }
        return $value;
    }
}

if ( ! function_exists('generate_uid'))
{
    /**
     * @return string
     */
    function generate_uid()
    {
        static $known   = [];

        do
        {
            $uid = uniqid(true);
        } while (in_array($uid, $known));
        return $known[] = $uid;
    }
}

if ( ! function_exists('parse_dsn'))
{
    /**
     * @param string $dsn
     *
     * @return ParsedDsn
     */
    function parse_dsn($dsn)
    {
        static $cache = [];

        if ( ! isset($cache[$dsn]))
        {
            $result             = [];
            $data               = parse_url($dsn);

            if ( ! isset($data['scheme'], $data['host']))
            {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid dsn provided "%s", no protocol or hostname',
                        $dsn
                    )
                );
            }

            $path               = '';

            if (isset($data['path']))
            {
                $path = mb_substr($data['path'], 1);
            }

            if (isset($data['port']))
            {
                $result['port'] = intval($data['port']);
            }

            $result['protocol'] = $data['scheme'];
            $result['hostname'] = $data['host'];
            $result['pathname'] = empty($path) ? null : "/{$path}";

            if (isset($data['user']))
            {
                $result['username'] = $data['user'];
                $result['password'] = '';
            }

            if (isset($data['pass']))
            {
                $result['password'] = $data['pass'];
            }

            $query              = $search = [];

            if (isset($data['query']))
            {
                parse_str($data['query'], $query);
            }

            foreach ($query as $key => $value)
            {
                if ('' === $value)
                {
                    $value = true;
                }

                if (blank($value = decode_value($value)))
                {
                    continue;
                }
                $search[$key] = $value;
            }

            $result['search']   = $search;
            $cache[$dsn]        = $result;
        }

        return ParsedDsn::make($cache[$dsn]);
    }

    class ParsedDsn
    {
        public string $protocol  = '';
        public string $hostname  = '';
        public ?int $port        = null;
        public ?string $pathname = null;
        public ?string $username = null;
        public ?string $password = null;

        /** @var array<string,mixed> */
        public array $search     = [];

        /**
         * @return static
         */
        public static function make(array $data)
        {
            $instance = new static();

            foreach ($data as $key => $value)
            {
                if (property_exists($instance, $key))
                {
                    $instance->{$key} = $value;
                }
            }

            return $instance;
        }
    }
}

if ( ! isset($_SERVER['REMOTE_ADDR']))
{
    $_SERVER['REMOTE_ADDR'] = '::1';
}

if ( ! isset($_SERVER['HTTP_HOST']))
{
    $_SERVER['HTTP_HOST'] = '::1';
}

require_once __DIR__ . '/config.php';
