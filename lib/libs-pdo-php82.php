<?php
/**
 * PHP Dev Tools PDO Edition (Supports SQLite)
 * @author Aymeric Anger
 * @version 26.03.5 build on 2026-03-22
 * @noinspection ALL
 */
namespace {

/**
 * Here we load config for every project.
 */
@date_default_timezone_set('Europe/Paris');
@mb_internal_encoding('UTF-8');

/**
 * Here are some functions from symfony/polyfill-php7* and symfony/polyfill-php8*.
 */
if (PHP_VERSION_ID < 70100)
{
    if ( ! function_exists('is_iterable'))
    {
        function is_iterable($var)
        {
            return is_array($var) || $var instanceof \Traversable;
        }
    }
}

if (PHP_VERSION_ID < 70300)
{
    if ( ! class_exists('JsonException', false))
    {
        class JsonException extends \Exception {}
    }

    if ( ! function_exists('hrtime'))
    {
        $startAt = (int) microtime(true);
        function hrtime($asNum = false)
        {
            global $startAt;

            $ns = microtime(false);
            $s  = substr($ns, 11) - $startAt;
            $ns = 1E9 * (float) $ns;

            if ($asNum)
            {
                $ns += $s * 1E9;

                return PHP_INT_SIZE === 4 ? $ns : (int) $ns;
            }

            return [$s, (int) $ns];
        }
    }

    if ( ! function_exists('is_countable'))
    {
        function is_countable($value)
        {
            return is_array($value) || $value instanceof \Countable || $value instanceof \ResourceBundle || $value instanceof \SimpleXmlElement;
        }
    }

    if ( ! function_exists('array_key_first'))
    {
        function array_key_first(array $array)
        {
            foreach ($array as $key => $value)
            {
                return $key;
            }
            return null;
        }
    }

    if ( ! function_exists('array_key_last'))
    {
        function array_key_last(array $array)
        {
            return key(array_slice($array, -1, 1, true));
        }
    }
}

if (PHP_VERSION_ID < 70400)
{
    if ( ! function_exists('mb_str_split'))
    {
        function mb_str_split($string, $split_length = 1, $encoding = null)
        {
            if (null !== $string && ! is_scalar($string) && ! (is_object($string) && method_exists($string, '__toString')))
            {
                trigger_error('mb_str_split() expects parameter 1 to be string, ' . gettype($string) . ' given', E_USER_WARNING);

                return null;
            }

            if (1 > $split_length = (int) $split_length)
            {
                trigger_error('The length of each segment must be greater than zero', E_USER_WARNING);

                return false;
            }

            if (null === $encoding)
            {
                $encoding = mb_internal_encoding();
            }

            if ('UTF-8' === $encoding || in_array(strtoupper($encoding), ['UTF-8', 'UTF8'], true))
            {
                return preg_split("/(.{{$split_length}})/u", $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            }

            $result = [];
            $length = mb_strlen($string, $encoding);

            for ($i = 0; $i < $length; $i += $split_length)
            {
                $result[] = mb_substr($string, $i, $split_length, $encoding);
            }

            return $result;
        }
    }
}

if (PHP_VERSION_ID < 80000)
{
    if ( ! interface_exists('Stringable', false))
    {
        interface Stringable
        {
            /**
             * @return string
             */
            public function __toString();
        }
    }

    if (PHP_VERSION_ID < 70000)
    {
        /**
         * Add compatibility php 5.
         */
        if ( ! interface_exists('Throwable', false))
        {
            interface Throwable extends \Stringable {}
        }

        if ( ! class_exists('Error', false))
        {
            class Error extends \RuntimeException implements \Throwable {}
        }
    }

    if ( ! class_exists('ValueError', false))
    {
        class ValueError extends \Error {}
    }

    if ( ! function_exists('get_debug_type'))
    {
        /**
         * @param mixed $value
         *
         * @return string
         */
        function get_debug_type($value)
        {
            switch (true)
            {
                case null === $value:
                    return 'null';
                case is_bool($value):
                    return 'bool';
                case is_string($value):
                    return 'string';
                case is_array($value):
                    return 'array';
                case is_int($value):
                    return 'int';
                case is_float($value):
                    return 'float';
                case is_object($value):
                    break;
                case $value instanceof \__PHP_Incomplete_Class:
                    return '__PHP_Incomplete_Class';
                default:
                    if (null === $type = @get_resource_type($value))
                    {
                        return 'unknown';
                    }

                    if ('Unknown' === $type)
                    {
                        $type = 'closed';
                    }

                    return "resource ({$type})";
            }

            $class = get_class($value);

            if (false === strpos($class, '@'))
            {
                return $class;
            }

            return (get_parent_class($class) ?: key(class_implements($class)) ?: 'class') . '@anonymous';
        }
    }

    if ( ! function_exists('str_contains'))
    {
        /**
         * @param string $haystack
         * @param string $needle
         *
         * @return bool
         */
        function str_contains($haystack, $needle)
        {
            return '' === $needle || false !== strpos($haystack, $needle);
        }
    }

    if ( ! function_exists('str_starts_with'))
    {
        /**
         * @param string $haystack
         * @param string $needle
         *
         * @return bool
         */
        function str_starts_with($haystack, $needle)
        {
            return 0 === strncmp($haystack, $needle, strlen($needle));
        }
    }

    if ( ! function_exists('str_ends_with'))
    {
        /**
         * @param string $haystack
         * @param string $needle
         *
         * @return bool
         */
        function str_ends_with($haystack, $needle)
        {
            if ('' === $needle || $needle === $haystack)
            {
                return true;
            }

            if ('' === $haystack)
            {
                return false;
            }

            $needleLength = strlen($needle);

            return $needleLength <= strlen($haystack) && 0 === substr_compare($haystack, $needle, -$needleLength);
        }
    }
}

if (PHP_VERSION_ID < 80100)
{
    if ( ! function_exists('array_is_list'))
    {
        /**
         * @param array $array
         *
         * @return bool
         */
        function array_is_list(array $array)
        {
            if ([] === $array || $array === array_values($array))
            {
                return true;
            }

            $nextKey = -1;

            foreach ($array as $k => $v)
            {
                if ($k !== ++$nextKey)
                {
                    return false;
                }
            }

            return true;
        }
    }
}

/**
 * Here are some functions from symfony/polyfill-php8[3-4].
 */
if (PHP_VERSION_ID < 80300)
{
    if ( ! function_exists('json_validate'))
    {
        /**
         * @param string $json
         * @param int    $depth
         * @param int    $flags
         *
         * @return bool
         */
        function json_validate($json, $depth = 512, $flags = 0)
        {
            if (0 !== $flags && defined('JSON_INVALID_UTF8_IGNORE') && JSON_INVALID_UTF8_IGNORE !== $flags)
            {
                throw new \ValueError('json_validate(): Argument #3 ($flags) must be a valid flag (allowed flags: JSON_INVALID_UTF8_IGNORE)');
            }

            if ($depth <= 0)
            {
                throw new \ValueError('json_validate(): Argument #2 ($depth) must be greater than 0');
            }

            if ($depth > 0x7FFFFFFF)
            {
                throw new \ValueError(sprintf('json_validate(): Argument #2 ($depth) must be less than %d', 0x7FFFFFFF));
            }

            json_decode($json, false, $depth, $flags);

            return JSON_ERROR_NONE === json_last_error();
        }
    }

    if ( ! function_exists('mb_str_pad'))
    {
        /**
         * @param string      $string
         * @param int         $length
         * @param string      $pad_string
         * @param int         $pad_type
         * @param null|string $encoding
         *
         * @return string
         */
        function mb_str_pad($string, $length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = null)
        {
            if ( ! in_array($pad_type, [STR_PAD_RIGHT, STR_PAD_LEFT, STR_PAD_BOTH], true))
            {
                throw new \ValueError('mb_str_pad(): Argument #4 ($pad_type) must be STR_PAD_LEFT, STR_PAD_RIGHT, or STR_PAD_BOTH');
            }

            if (null === $encoding)
            {
                $encoding = mb_internal_encoding();
            }

            try
            {
                $validEncoding = @mb_check_encoding('', $encoding);
            } catch (\ValueError $e)
            {
                throw new \ValueError(sprintf('mb_str_pad(): Argument #5 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            // BC for PHP 7.3 and lower
            if ( ! $validEncoding)
            {
                throw new \ValueError(sprintf('mb_str_pad(): Argument #5 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            if (mb_strlen($pad_string, $encoding) <= 0)
            {
                throw new \ValueError('mb_str_pad(): Argument #3 ($pad_string) must be a non-empty string');
            }

            $paddingRequired = $length - mb_strlen($string, $encoding);

            if ($paddingRequired < 1)
            {
                return $string;
            }

            switch ($pad_type)
            {
                case STR_PAD_LEFT:
                    return mb_substr(str_repeat($pad_string, $paddingRequired), 0, $paddingRequired, $encoding) . $string;
                case STR_PAD_RIGHT:
                    return $string . mb_substr(str_repeat($pad_string, $paddingRequired), 0, $paddingRequired, $encoding);
                default:
                    $leftPaddingLength  = floor($paddingRequired / 2);
                    $rightPaddingLength = $paddingRequired - $leftPaddingLength;

                    return mb_substr(str_repeat($pad_string, $leftPaddingLength), 0, $leftPaddingLength, $encoding) . $string . mb_substr(str_repeat($pad_string, $rightPaddingLength), 0, $rightPaddingLength, $encoding);
            }
        }
    }

    if ( ! function_exists('str_increment'))
    {
        /**
         * @param string $string
         *
         * @return string
         */
        function str_increment($string)
        {
            if ('' === $string)
            {
                throw new \ValueError('str_increment(): Argument #1 ($string) cannot be empty');
            }

            if ( ! preg_match('/^[a-zA-Z0-9]+$/', $string))
            {
                throw new \ValueError('str_increment(): Argument #1 ($string) must be composed only of alphanumeric ASCII characters');
            }

            if (is_numeric($string))
            {
                $offset = stripos($string, 'e');

                if (false !== $offset)
                {
                    $char            = $string[$offset];
                    ++$char;
                    $string[$offset] = $char;
                    ++$string;

                    switch ($string[$offset])
                    {
                        case 'f':
                            $string[$offset] = 'e';
                            break;
                        case 'F':
                            $string[$offset] = 'E';
                            break;
                        case 'g':
                            $string[$offset] = 'f';
                            break;
                        case 'G':
                            $string[$offset] = 'F';
                            break;
                    }

                    return $string;
                }
            }

            return ++$string;
        }
    }

    if ( ! function_exists('str_decrement'))
    {
        /**
         * @param string $string
         *
         * @return string
         */
        function str_decrement($string)
        {
            if ('' === $string)
            {
                throw new \ValueError('str_decrement(): Argument #1 ($string) cannot be empty');
            }

            if ( ! preg_match('/^[a-zA-Z0-9]+$/', $string))
            {
                throw new \ValueError('str_decrement(): Argument #1 ($string) must be composed only of alphanumeric ASCII characters');
            }

            if (preg_match('/\A(?:0[aA0]?|[aA])\z/', $string))
            {
                throw new \ValueError(sprintf('str_decrement(): Argument #1 ($string) "%s" is out of decrement range', $string));
            }

            if ( ! in_array(substr($string, -1), ['A', 'a', '0'], true))
            {
                return implode('', array_slice(str_split($string), 0, -1)) . chr(ord(substr($string, -1)) - 1);
            }

            $carry       = '';
            $decremented = '';

            for ($i = strlen($string) - 1; $i >= 0; --$i)
            {
                $char = $string[$i];

                switch ($char)
                {
                    case 'A':
                        if ('' !== $carry)
                        {
                            $decremented = $carry . $decremented;
                            $carry       = '';
                        }
                        $carry = 'Z';

                        break;
                    case 'a':
                        if ('' !== $carry)
                        {
                            $decremented = $carry . $decremented;
                            $carry       = '';
                        }
                        $carry = 'z';

                        break;
                    case '0':
                        if ('' !== $carry)
                        {
                            $decremented = $carry . $decremented;
                            $carry       = '';
                        }
                        $carry = '9';

                        break;
                    case '1':
                        if ('' !== $carry)
                        {
                            $decremented = $carry . $decremented;
                            $carry       = '';
                        }

                        break;
                    default:
                        if ('' !== $carry)
                        {
                            $decremented = $carry . $decremented;
                            $carry       = '';
                        }

                        if ( ! in_array($char, ['A', 'a', '0'], true))
                        {
                            $decremented = chr(ord($char) - 1) . $decremented;
                        }
                }
            }

            return $decremented;
        }
    }
}

if (PHP_VERSION_ID < 80400)
{
    if ( ! function_exists('array_find'))
    {
        /**
         * @param array    $array
         * @param callable $callback
         *
         * @return mixed
         */
        function array_find(array $array, callable $callback)
        {
            foreach ($array as $key => $value)
            {
                if ($callback($value, $key))
                {
                    return $value;
                }
            }

            return null;
        }
    }

    if ( ! function_exists('array_find_key'))
    {
        /**
         * @param array    $array
         * @param callable $callback
         *
         * @return null|int|string
         */
        function array_find_key(array $array, callable $callback)
        {
            foreach ($array as $key => $value)
            {
                if ($callback($value, $key))
                {
                    return $key;
                }
            }

            return null;
        }
    }

    if ( ! function_exists('array_any'))
    {
        /**
         * @param array    $array
         * @param callable $callback
         *
         * @return bool
         */
        function array_any(array $array, callable $callback)
        {
            if (count($array))
            {
                foreach ($array as $key => $value)
                {
                    if ($callback($value, $key))
                    {
                        return true;
                    }
                }
            }

            return false;
        }
    }

    if ( ! function_exists('array_all'))
    {
        /**
         * @param array    $array
         * @param callable $callback
         *
         * @return bool
         */
        function array_all(array $array, callable $callback)
        {
            foreach ($array as $key => $value)
            {
                if ( ! $callback($value, $key))
                {
                    return false;
                }
            }

            return true;
        }
    }

    if ( ! function_exists('mb_ucfirst'))
    {
        /**
         * @param string      $string
         * @param null|string $encoding
         *
         * @return string
         */
        function mb_ucfirst($string, $encoding = null)
        {
            if (null === $encoding)
            {
                $encoding = mb_internal_encoding();
            }

            try
            {
                $validEncoding = @mb_check_encoding('', $encoding);
            } catch (\ValueError $e)
            {
                throw new \ValueError(sprintf('mb_ucfirst(): Argument #2 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            // BC for PHP 7.3 and lower
            if ( ! $validEncoding)
            {
                throw new \ValueError(sprintf('mb_ucfirst(): Argument #2 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            $firstChar = mb_substr($string, 0, 1, $encoding);
            $firstChar = mb_convert_case($firstChar, MB_CASE_TITLE, $encoding);

            return $firstChar . mb_substr($string, 1, null, $encoding);
        }
    }

    if ( ! function_exists('mb_lcfirst'))
    {
        /**
         * @param string      $string
         * @param null|string $encoding
         *
         * @return string
         */
        function mb_lcfirst($string, $encoding = null)
        {
            if (null === $encoding)
            {
                $encoding = mb_internal_encoding();
            }

            try
            {
                $validEncoding = @mb_check_encoding('', $encoding);
            } catch (\ValueError $e)
            {
                throw new \ValueError(sprintf('mb_lcfirst(): Argument #2 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            // BC for PHP 7.3 and lower
            if ( ! $validEncoding)
            {
                throw new \ValueError(sprintf('mb_lcfirst(): Argument #2 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            $firstChar = mb_substr($string, 0, 1, $encoding);
            $firstChar = mb_convert_case($firstChar, MB_CASE_LOWER, $encoding);

            return $firstChar . mb_substr($string, 1, null, $encoding);
        }
    }

    /**
     * @param string      $regex
     * @param string      $string
     * @param null|string $characters
     * @param null|string $encoding
     * @param string      $function
     *
     * @return string
     */
    $mb_internal_trim = function ($regex, $string, $characters, $encoding, $function)
    {
        if (null === $encoding)
        {
            $encoding = mb_internal_encoding();
        }

        try
        {
            $validEncoding = @mb_check_encoding('', $encoding);
        } catch (\ValueError $e)
        {
            throw new \ValueError(sprintf('%s(): Argument #3 ($encoding) must be a valid encoding, "%s" given', $function, $encoding));
        }

        // BC for PHP 7.3 and lower
        if ( ! $validEncoding)
        {
            throw new \ValueError(sprintf('%s(): Argument #3 ($encoding) must be a valid encoding, "%s" given', $function, $encoding));
        }

        if ('' === $characters)
        {
            return null === $encoding ? $string : mb_convert_encoding($string, $encoding);
        }

        if ('UTF-8' === $encoding || in_array(strtolower($encoding), ['utf-8', 'utf8'], true))
        {
            $encoding = 'UTF-8';
        }

        $string = mb_convert_encoding($string, 'UTF-8', $encoding);

        if (null !== $characters)
        {
            $characters = mb_convert_encoding($characters, 'UTF-8', $encoding);
        }

        if (null === $characters)
        {
            $characters = "\\0 \f\n\r\t\v\u{00A0}\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200A}\u{2028}\u{2029}\u{202F}\u{205F}\u{3000}\u{0085}\u{180E}";
        } else
        {
            $characters = preg_quote($characters);
        }

        $string = preg_replace(sprintf($regex, $characters), '', $string);

        if ('UTF-8' === $encoding)
        {
            return $string;
        }

        return mb_convert_encoding($string, $encoding, 'UTF-8');
    };

    if ( ! function_exists('mb_trim'))
    {
        /**
         * @param string      $string
         * @param null|string $characters
         * @param null|string $encoding
         *
         * @return string
         */
        function mb_trim($string, $characters = null, $encoding = null)
        {
            global $mb_internal_trim;
            return $mb_internal_trim('{^[%s]+|[%1$s]+$}Du', $string, $characters, $encoding, __FUNCTION__);
        }
    }

    if ( ! function_exists('mb_ltrim'))
    {
        /**
         * @param string      $string
         * @param null|string $characters
         * @param null|string $encoding
         *
         * @return string
         */
        function mb_ltrim($string, $characters = null, $encoding = null)
        {
            global $mb_internal_trim;
            return $mb_internal_trim('{^[%s]+}Du', $string, $characters, $encoding, __FUNCTION__);
        }
    }

    if ( ! function_exists('mb_rtrim'))
    {
        /**
         * @param string      $string
         * @param null|string $characters
         * @param null|string $encoding
         *
         * @return string
         */
        function mb_rtrim($string, $characters = null, $encoding = null)
        {
            global $mb_internal_trim;
            return $mb_internal_trim('{[%s]+$}Du', $string, $characters, $encoding, __FUNCTION__);
        }
    }
}

if (PHP_VERSION_ID < 80500)
{
    if ( ! function_exists('get_error_handler'))
    {
        /**
         * @return ?callable
         */
        function get_error_handler()
        {
            $handler = set_error_handler(null);
            restore_error_handler();
            return $handler;
        }
    }

    if ( ! function_exists('get_exception_handler'))
    {
        /**
         * @return ?callable
         */
        function get_exception_handler()
        {
            $handler = set_exception_handler(null);
            restore_exception_handler();
            return $handler;
        }
    }

    if ( ! function_exists('array_first'))
    {
        /**
         * @param array $array
         *
         * @return null|mixed
         */
        function array_first(array $array)
        {
            foreach ($array as $value)
            {
                return $value;
            }

            return null;
        }
    }

    if ( ! function_exists('array_last'))
    {
        /**
         * @param array $array
         *
         * @return null|mixed
         */
        function array_last(array $array)
        {
            return count($array) ? current(array_slice($array, -1)) : null;
        }
    }
}

/**
 * @param string $namespace
 * @param string $path
 * @param string $extension
 */
function autoload_register_namespace($namespace, $path, $extension = '.php')
{
    static $sep          = '\\', $pSep = '/';
    $normalizedPath      = rtrim(str_replace($sep, $pSep, $path), $pSep) . $pSep;
    $normalizedNamespace = rtrim($namespace, '\\');
    $extension           = '.' . ltrim($extension, '.');
    $len                 = strlen($normalizedNamespace);

    spl_autoload_register(function ($className) use ($normalizedNamespace, $normalizedPath, $extension, $len, $sep, $pSep)
    {
        if ($normalizedNamespace === substr($className, 0, $len))
        {
            $filename = str_replace($sep, $pSep, substr($className, $len)) . $extension;
            require_secure($normalizedPath . $filename);
        }
    });
}

if ( ! function_exists('renderArgs'))
{
    /**
     * Renders arguments as `$prefix.$key="$value"`.
     * Also encodes values to string if a value is null or boolean false, it will not be rendered
     * replaces `myArg => true` to `my-arg` and `myArg => true` to ``.
     *
     * @param iterable $arguments
     * @param string   $prefix
     *
     * @return string
     *
     * @throws \InvalidArgumentException if one of the arguments is invalid
     *
     * @author Aymeric Anger
     *
     * @example renderArgs(['checked'=>$cond, 'selected'=>$cond]) where $cond is a boolean
     * @example renderArgs(['value'=> "value", "data"=>["jsValue"=>10]]) => `value="value" data-js-value="10"`
     * @example renderArgs(["jsValue"=>10], "data-") => `data-js-value="10"`
     */
    function renderArgs($arguments, $prefix = '')
    {
        $result = [];

        if ( ! is_string($prefix))
        {
            throw new \InvalidArgumentException('$prefix is not a string');
        }

        // is_iterable() for php < 7.1
        if ( ! is_iterable($arguments))
        {
            throw new \InvalidArgumentException('$arguments is not iterable');
        }

        foreach ($arguments as $key => $value)
        {
            if (false === $value || null === $value)
            {
                continue;
            }

            // dataset helper
            if ('data' === $key && (is_array($value) || $value instanceof \Traversable))
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
     * @param string|\Stringable $tagName
     * @param iterable           $arguments
     * @param string|\Stringable $innerHtml
     *
     * @return string
     */
    function renderTag($tagName, $arguments = [], $innerHtml = '')
    {
        /**
         * @see https://developer.mozilla.org/en-US/docs/Glossary/Void_element
         */
        static $voidElements = [
            'area',
            'base',
            'br',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'link',
            'meta',
            'param',
            'source',
            'track',
            'wbr',
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
            throw new \InvalidArgumentException('$tagName is not a string');
        }

        if ( ! is_string($innerHtml))
        {
            throw new \InvalidArgumentException('$innerHtml is not a string');
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

if ( ! function_exists('var_get'))
{
    /**
     * @param int|string $name
     * @param array      $var
     * @param mixed      $defaultValue
     *
     * @return mixed
     */
    function var_get($name, array $var, $defaultValue = null)
    {
        if (isset($var[$name]))
        {
            return $var[$name];
        }
        return value($defaultValue, $name);
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
     * @param bool       $decode       true to get scalar value int|float|bool|array or false to get original value
     *
     * @return mixed
     */
    function env_get($name, $defaultValue = null, $decode = true)
    {
        static $loaded = false;

        // plugin loader (on first usage)
        if ('init' === $name || ! $loaded)
        {
            $loaded = true;

            if ( ! isset($_ENV['env_get_init']))
            {
                // ENV to array conversion (dotenv already loaded once)
                call_user_func(function ()
                {
                    // fix null value for json decoding (or direct access)
                    foreach ($_ENV as $key => $value)
                    {
                        if ('null' === $value)
                        {
                            $_ENV[$key] = $_SERVER[$key] = null;
                        }
                    }
                    // array plugin
                    $symfony              = isset($_ENV['SYMFONY_DOTENV_VARS']);

                    $SYMFONY_DOTENV_VARS  = $symfony ? explode(',', $_ENV['SYMFONY_DOTENV_VARS']) : [];
                    $values               = [];

                    foreach (array_keys($_ENV) as $key)
                    {
                        if (preg_match('#_(\d+)$#', $key, $matches))
                        {
                            $index                   = (int) $matches[1];
                            $newKey                  = substr($key, 0, -strlen($matches[0]));

                            if ( ! isset($values[$newKey]))
                            {
                                $values[$newKey] = [];
                            }

                            if (isset($_ENV[$newKey]) && ! is_array($_ENV[$newKey]))
                            {
                                $values[$newKey] = [$_ENV[$newKey]];
                                unset($_ENV[$newKey]);
                            }

                            $values[$newKey][$index] = $_ENV[$key];

                            // remove previous key
                            unset($_ENV[$key]);
                            $index                   = array_search($key, $SYMFONY_DOTENV_VARS);

                            if (false !== $index)
                            {
                                unset($SYMFONY_DOTENV_VARS[$index]);
                            }

                            if ( ! in_array($newKey, $SYMFONY_DOTENV_VARS))
                            {
                                $SYMFONY_DOTENV_VARS[] = $newKey;
                            }
                        }
                    }

                    foreach ($values as &$value)
                    {
                        if (is_array($value))
                        {
                            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    }

                    $_ENV                 = array_replace($_ENV, $values);

                    if ($symfony)
                    {
                        $_ENV['SYMFONY_DOTENV_VARS'] = implode(',', $SYMFONY_DOTENV_VARS);
                    }

                    $_ENV['env_get_init'] = true;
                });
            }
        }

        $value         = '';

        if (isset($_ENV[$name]))
        {
            $value = $_ENV[$name];
        } elseif (isset($_SERVER[$name]))
        {
            $value = $_SERVER[$name];
        }

        if (in_array($value, ['null', ''], true))
        {
            return value($defaultValue, $name);
        }
        return $decode ? decode_value($value) : $value;
    }
}

if ( ! function_exists('value'))
{
    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @param mixed ...$args
     *
     * @return mixed
     */
    function value($value, $args = [])
    {
        if ($value instanceof \Closure)
        {
            if ( ! is_array($args))
            {
                $args = array_slice(func_get_args(), 1);
            }
            return call_user_func_array($value, $args);
        }

        return $value;
    }
}

if ( ! function_exists('tap'))
{
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param mixed    $value
     * @param callable $func
     *
     * @return mixed
     */
    function tap($value, callable $func)
    {
        call_user_func($func, $value);
        return $value;
    }
}

if ( ! function_exists('require_secure'))
{
    /**
     * @param string $filename
     *
     * @return mixed
     */
    function require_secure($filename)
    {
        if (is_file($filename))
        {
            return include $filename;
        }

        return null;
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
                $normalized         = strtolower($real);

                // BUG: UTF-8 strings detected as UTF-16 subset and japanese conversion
                if (strstr($normalized, 'ucs') || strstr($normalized, 'utf-16'))
                {
                    continue;
                }
                $types[$normalized] = $real;
            }
        }

        $normalized   = strtolower($encoding);

        if ( ! isset($types[$normalized]))
        {
            return $str;
        }

        $toEncoding   = $types[$normalized];

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
            if ('null' === $value)
            {
                return null;
            }
            $decoded = json_decode($value, true);

            if (JSON_ERROR_NONE === json_last_error())
            {
                return $decoded;
            }
            return $value;
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

if ( ! function_exists('is_list'))
{
    /**
     * Checks if value is a list.
     *
     * @param mixed $value
     *
     * @return bool
     */
    function is_list($value)
    {
        if ( ! is_iterable($value) && ! ($value instanceof \ArrayAccess && $value instanceof \Countable))
        {
            return false;
        }

        if (is_array($value))
        {
            return array_is_list($value);
        }

        // Traversable
        if (is_iterable($value))
        {
            $nextKey = -1;

            foreach ($value as $k => $_)
            {
                if ($k !== ++$nextKey)
                {
                    return false;
                }
            }
            return true;
        }

        // Countable
        if (is_countable($value))
        {
            if (0 === count($value))
            {
                return true;
            }

            // ArrayAccess
            try
            {
                set_error_handler(function ($errno, $errstr, $errfile, $errline)
                {
                    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
                });

                for ($offset = 0; $offset < count($value); ++$offset)
                {
                    if ( ! isset($value[$offset]))
                    {
                        return false;
                    }
                }
            } catch (\ErrorException $err)
            {
                return false;
            } finally
            {
                restore_error_handler();
            }
            return true;
        }
        return false;
    }
}

if ( ! function_exists('count_value'))
{
    /**
     * Count the amount of value occurrence in iterable.
     *
     * @param mixed    $value
     * @param iterable $iterable
     *
     * @return int
     */
    function count_value($value, $iterable)
    {
        if ( ! is_iterable($iterable))
        {
            return 0;
        }
        $result = 0;

        foreach ($iterable as $item)
        {
            if ($item === $value)
            {
                ++$result;
            }
        }
        return $result;
    }
}

if ( ! function_exists('generate_uid'))
{
    /**
     * Generates random string from 16 chars to selected length (max 128).
     *
     * @param bool|int $length false: 16, true: 32, even number from 16 to 128
     *
     * @return string
     */
    function generate_uid($length = false)
    {
        static $known   = [];

        if (is_bool($length))
        {
            $length = $length ? 32 : 16;
        } elseif ( ! is_numeric($length))
        {
            $length = 16;
        }
        $length         = (int) $length;

        // needs an even number
        if (0 !== $length % 2)
        {
            ++$length;
        }
        $length         = (int) max(min($length, 128), 16);

        $n              = ceil($length / 13);

        do
        {
            // for php 5
            if ( ! function_exists('random_bytes'))
            {
                $uid = '';

                for ($i = 0; $i < $n; ++$i)
                {
                    $uid .= uniqid();
                }
                $uid = substr($uid, -$length);
            } else
            {
                // php 7.0+
                $uid = bin2hex(random_bytes($length / 2));
            }
        } while (in_array($uid, $known));
        return $known[] = $uid;
    }
}

if ( ! function_exists('remove_prefix'))
{
    /**
     * Removes prefix from string.
     *
     * @param string|\Stringable $input
     * @param string|\Stringable $prefix
     *
     * @return string
     */
    function remove_prefix($input, $prefix)
    {
        $input  = (string) $input;
        $prefix = (string) $prefix;

        if (str_starts_with($input, $prefix))
        {
            return substr($input, strlen($prefix));
        }
        return $input;
    }
}

if ( ! function_exists('remove_suffix'))
{
    /**
     * Removes suffix from string.
     *
     * @param string|\Stringable $input
     * @param string|\Stringable $suffix
     *
     * @return string
     */
    function remove_suffix($input, $suffix)
    {
        $input  = (string) $input;
        $suffix = (string) $suffix;

        if (str_ends_with($input, $suffix))
        {
            return substr($input, 0, -strlen($suffix));
        }
        return $input;
    }
}

if ( ! function_exists('str_format'))
{
    /**
     * Use a python like format for named replacements or vsprintf for indexed ones.
     *
     * @param string $subject
     * @param array  $replacements
     *
     * @return string
     */
    function str_format($subject, array $replacements)
    {
        if ( ! count($replacements))
        {
            return $subject;
        }

        if (array_is_list($replacements) && str_contains($subject, '%'))
        {
            try
            {
                // prevent warnings < PHP 8.0
                return @vsprintf($subject, $replacements) ?: $subject;
            } catch (\ValueError $error)
            {
                // prevents ValueError >= PHP 8.0
                return $subject;
            }
        }

        // uses named parameters (or indexed {1}, {2})
        return preg_replace_callback(
            '#{\h*([\w-]+)\h*}#',
            function ($matches) use ($replacements)
            {
                $key = $matches[1];

                if (is_numeric($key))
                {
                    $key = (int) $key;
                }

                if (isset($replacements[$key]))
                {
                    return $replacements[$key];
                }
                return $matches[0];
            },
            $subject
        );
    }
}

if ( ! function_exists('set_default_error_handler'))
{
    /**
     * Intercepts php warnings and throws errors
     * that can be intercepted.
     *
     * @return null|callable
     *
     * @throws \ErrorException
     */
    function set_default_error_handler()
    {
        static $handler = null;

        if ( ! $handler)
        {
            /**
             * @param int    $errno
             * @param string $errstr
             * @param string $errfile
             * @param int    $errline
             *
             * @return bool
             *
             * @throws \ErrorException
             */
            $handler = static function ($errno, $errstr, $errfile, $errline)
            {
                static $errors = [
                    E_ERROR, E_WARNING, E_PARSE,
                    E_NOTICE, E_CORE_ERROR, E_CORE_WARNING,
                    E_COMPILE_ERROR, E_COMPILE_WARNING, E_USER_ERROR,
                    E_USER_WARNING, E_USER_NOTICE, E_STRICT,
                    E_RECOVERABLE_ERROR, E_DEPRECATED, E_USER_DEPRECATED];

                if ( ! (error_reporting() & $errno))
                {
                    return false;
                }

                if (in_array($errno, $errors, true))
                {
                    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
                }
                return true;
            };
        }

        if (get_error_handler() === $handler)
        {
            return $handler;
        }
        return set_error_handler($handler);
    }
}

if ( ! function_exists('preg_exec'))
{
    /**
     * Perform a regular expression match.
     *
     * @param string $pattern the regular expression
     * @param string $subject the subject
     * @param int    $limit   maximum number of results if set to 0, all results are returned
     *
     * @return array
     */
    function preg_exec($pattern, $subject, $limit = 1)
    {
        preg_valid($pattern, true);

        $limit = max(0, $limit);

        if (preg_match_all($pattern, $subject, $matches, PREG_SET_ORDER) > 0)
        {
            if (0 === $limit)
            {
                $limit = count($matches);
            }

            if (1 === $limit)
            {
                return $matches[0];
            }

            while (count($matches) > $limit)
            {
                array_pop($matches);
            }
            return $matches;
        }

        return [];
    }
}

if ( ! function_exists('preg_test'))
{
    /**
     * Test if the subject matches the pattern.
     *
     * @param string $pattern
     * @param string $subject
     *
     * @return bool
     *
     * @throws \ErrorException
     */
    function preg_test($pattern, $subject)
    {
        preg_valid($pattern, true);
        return preg_match($pattern, $subject) > 0;
    }
}

if ( ! function_exists('preg_valid'))
{
    /**
     * Check if regular expression is valid.
     *
     * @phan-suppress PhanParamSuspiciousOrder
     *
     * @param string $pattern
     * @param bool   $exception
     *
     * @return bool
     *
     * @throws \ErrorException if exception set to true
     */
    function preg_valid($pattern, $exception = false)
    {
        try
        {
            set_default_error_handler();
            return $pattern !== ltrim($pattern, '%#/') && false !== preg_match($pattern, ''); // must be >=0 to be correct
        } catch (\ErrorException $error)
        {
            if ($exception)
            {
                $msg = str_replace('_match', '_valid', $error->getMessage());
                throw new \ErrorException(
                    $msg,
                    0,
                    $error->getSeverity(),
                    '',
                    0,
                    $error
                );
            }
            return false;
        } finally
        {
            restore_error_handler();
        }
    }
}

if ( ! interface_exists('Lockable', false))
{
    interface Lockable
    {
        /**
         * Lock the object.
         */
        public function lock();

        /**
         * Unlock the object.
         */
        public function unlock();

        /**
         * Get the lock status.
         *
         * @return bool
         */
        public function isLocked();
    }
}

/**
 * An application logger than can dynamically write to log file defined as channel
 * Can prefix INFO:, WARN:, ERR: to logs using the right method
 * Can use variadic or array replacements on every php versions between 5.5 and 8.4
 * eg: ApplicationLogger(APP_ID)->info('app started on %s', date('Y-m-d H:i:s'));.
 *
 * @author Aymeric Anger
 */
class ApplicationLogger
{
    const DEFAULT_CHANNEL             = 'app';

    protected $channel                = '';

    protected $prefix                 = '';

    protected $logs                   = [];

    protected $rotationDone           = false;

    protected static $instances       = [];
    protected static $logRoot         = '';
    protected static $rotate          = 0;
    protected static $archiveLocation = '';
    protected static $logDays         = false;
    protected static $backTrace       = true;
    protected static $logOutput       = false;

    public function __construct($channel = self::DEFAULT_CHANNEL)
    {
        $this->channel = $channel;
    }

    /**
     * @return bool
     */
    public static function getLogDays()
    {
        return self::$logDays;
    }

    /**
     * @param bool $logDays
     */
    public static function setLogDays($logDays)
    {
        self::$logDays = (bool) $logDays;
    }

    /**
     * @param string $archiveLocation
     */
    public static function setArchiveLocation($archiveLocation)
    {
        $archiveLocation       = self::normalizePath($archiveLocation) . DIRECTORY_SEPARATOR;
        $umask                 = @umask(0);
        @mkdir($archiveLocation, 0777, true);
        @umask($umask);
        self::$archiveLocation = $archiveLocation;
    }

    /**
     * @return string
     */
    public static function getArchiveLocation()
    {
        if ( ! self::$archiveLocation)
        {
            $pth = constant_get(
                'LOG_PATH_ARCHIVE',
                self::getLogRoot() . 'archives' . DIRECTORY_SEPARATOR
            );
            self::setArchiveLocation($pth);
        }

        return self::$archiveLocation;
    }

    /**
     * @return int
     */
    public static function getRotate()
    {
        return self::$rotate;
    }

    /**
     * @param int $rotate
     */
    public static function setRotate($rotate)
    {
        self::$rotate = max($rotate, 0);
    }

    /**
     * @return string
     */
    public static function getLogRoot()
    {
        if ( ! self::$logRoot)
        {
            $pth = constant_get('LOG_PATH', getcwd() . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR);
            self::setLogRoot($pth);
        }
        return self::$logRoot;
    }

    /**
     * @param string $dir
     */
    public static function setLogRoot($dir)
    {
        $dir           = self::normalizePath($dir) . DIRECTORY_SEPARATOR;
        $umask         = @umask(0);
        @mkdir($dir, 0777, true);
        @umask($umask);
        self::$logRoot = $dir;
    }

    /**
     * @param null|string $channel
     *
     * @return static
     */
    public static function getLogger($channel = null)
    {
        if (empty($channel))
        {
            $channel = self::getDefaultChannel();
        }

        if ( ! isset(self::$instances[$channel]))
        {
            self::$instances[$channel] = new \ApplicationLogger($channel);
        }
        return self::$instances[$channel];
    }

    /**
     * @return bool
     */
    public static function hasBackTrace()
    {
        return self::$backTrace;
    }

    /**
     * @param bool $backTrace
     */
    public static function setBackTrace($backTrace)
    {
        self::$backTrace = $backTrace;
    }

    /**
     * @return bool
     */
    public static function canLogOutput()
    {
        return self::$logOutput;
    }

    /**
     * @param bool $logOutput
     */
    public static function setLogOutput($logOutput)
    {
        self::$logOutput = $logOutput;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return rtrim($this->prefix);
    }

    /**
     * @param string $prefix
     *
     * @return static
     */
    public function setPrefix($prefix)
    {
        if (empty($prefix))
        {
            $this->prefix = '';
            return $this;
        }

        if (rtrim($prefix, ' ') !== $prefix)
        {
            $prefix = rtrim("{$prefix}") . ' ';
        }

        $this->prefix = $prefix;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param string $channel
     *
     * @return static
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * @param string $message
     * @param array  $replacements
     *
     * @return static
     */
    public function log($message, $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $args         = func_get_args();
            array_splice($args, 0, 1);
            $replacements = $args;
        }

        if (count($replacements) > 0 && ! empty($message))
        {
            // encode object value to string if possible
            foreach ($replacements as $key => $value)
            {
                if (is_object($value) && ! method_exists($value, '__toString'))
                {
                    if (false !== $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    {
                        $replacements[$key] = $json;
                    }
                }
            }

            $message = str_format($message, $replacements);
        }

        $file         = $this->getFilename();
        $dir          = dirname($file);
        $umask        = @umask(0);

        if ( ! is_dir($dir))
        {
            @mkdir($dir, 0777, true);
        }

        $metadata     = date('Y/m/d H:i:s');

        if (self::hasBackTrace())
        {
            foreach (@debug_backtrace() as $trace)
            {
                if (__FILE__ !== $trace['file'])
                {
                    $metadata .= sprintf(' %s:%s', basename($trace['file']), $trace['line']);
                    break;
                }
            }
        }

        $this->logs[] = $msg = sprintf("%s %s%s\n", $metadata, $this->prefix, $message);

        @file_put_contents(
            $file,
            $msg,
            FILE_APPEND
        );

        @chmod($file, 0777);

        @umask($umask);

        if (self::canLogOutput())
        {
            echo $msg;
        }

        return $this->rotateLogs();
    }

    /**
     * @param string $message
     * @param array  $replacements
     *
     * @return static
     */
    public function info($message, $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $args         = func_get_args();
            array_splice($args, 0, 1);
            $replacements = $args;
        }
        return $this->log("INFO: {$message}", $replacements);
    }

    /**
     * @param string $message
     * @param array  $replacements
     *
     * @return static
     */
    public function debug($message, $replacements = [])
    {
        if ( ! constant_get('DEV_ENV', false))
        {
            return $this;
        }

        if ( ! is_array($replacements))
        {
            $args         = func_get_args();
            array_splice($args, 0, 1);
            $replacements = $args;
        }
        return $this->log("DEBUG: {$message}", $replacements);
    }

    /**
     * @param string $message
     * @param array  $replacements
     *
     * @return static
     */
    public function error($message, $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $args         = func_get_args();
            array_splice($args, 0, 1);
            $replacements = $args;
        }
        return $this->log("ERR: {$message}", $replacements);
    }

    /**
     * @param string $message
     * @param array  $replacements
     *
     * @return static
     */
    public function warn($message, $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $args         = func_get_args();
            array_splice($args, 0, 1);
            $replacements = $args;
        }

        return $this->log("WARN: {$message}", $replacements);
    }

    /**
     * Normalize pathname.
     *
     * @param string $path
     *
     * @return string
     */
    protected static function normalizePath($path)
    {
        if (empty($path))
        {
            return $path;
        }

        return rtrim(
            preg_replace('#[\\\/]+#', DIRECTORY_SEPARATOR, $path),
            DIRECTORY_SEPARATOR
        );
    }

    /**
     * @return string
     */
    protected static function getDefaultChannel()
    {
        return constant_get('APP_ID', self::DEFAULT_CHANNEL);
    }

    /**
     * @param string $channel
     *
     * @return string
     */
    protected function getRealChannel($channel)
    {
        if ( ! str_ends_with($channel, '-dev') && constant_get('DEV_ENV'))
        {
            $channel .= '-dev';
        }
        return $channel;
    }

    /**
     * @param null|string $channel
     *
     * @return string
     */
    protected function getFilename($channel = null)
    {
        static $filenames = [];

        if ( ! isset($channel))
        {
            $channel = $this->getChannel();

            if (empty($channel))
            {
                $channel = self::getDefaultChannel();
            }
        }

        // if the log channel is in a sub-dir
        $channel          = $this->getRealChannel(trim($channel, '/'));

        if ( ! isset($filenames[$channel]))
        {
            $chan                = $channel;
            $dest                = self::getLogRoot();
            $dir                 = '';

            if (false !== $pos = strrpos($channel, '/'))
            {
                // normalize
                $dir  = ltrim(substr($channel, 0, $pos + 1), '/');
                $chan = substr($channel, $pos + 1);
            }

            $filenames[$channel] = sprintf(
                '%s%s-%s.log',
                $dest . $dir,
                self::getLogDays() ? date('ymd') : date('ym'),
                $chan
            );
        }

        return $filenames[$channel];
    }

    /**
     * @return static
     */
    protected function rotateLogs()
    {
        if ($this->rotationDone)
        {
            return $this;
        }
        $this->rotationDone = true;
        $keep               = self::getRotate();

        if ( ! $keep)
        {
            return $this;
        }
        $orig               = self::getLogRoot();
        $dest               = self::getArchiveLocation();
        $chan               = $this->getRealChannel(trim($this->channel, '/'));
        $dir                = '';

        if (false !== $pos = strrpos($chan, '/'))
        {
            // normalize
            $dir   = substr($chan, 0, $pos + 1);
            $dest .= $dir;
            $chan  = substr($chan, $pos + 1);
            $umask = @umask(0);
            @mkdir($dest, 0777, true);
            @umask($umask);
        }

        $list               = [];

        foreach (glob($orig . $dir . '[0-9][0-9]*.log') as $file)
        {
            if ( ! is_file($file))
            {
                continue;
            }

            if (preg_match('#^\d+(.+)\.log#', basename($file), $matches))
            {
                @list(, $name)          = $matches;
                $name                   = trim($name, '-_');

                if ($name !== $chan)
                {
                    continue;
                }

                $list[filemtime($file)] = $file;
            }
        }

        ksort($list);

        while (count($list) > $keep)
        {
            $file     = array_shift($list);
            $basename = basename($file);
            @rename($file, $dest . $basename);
        }

        return $this;
    }
}
}
namespace Observable{

interface Observable
{
    /**
     * @param Event $event
     *
     * @return Event
     */
    public function dispatchEvent(Event $event);

    /**
     * @param non-empty-string $type
     * @param callable         $listener
     */
    public function addEventListener($type, $listener);

    /**
     * @param non-empty-string $type
     * @param callable         $listener
     */
    public function removeEventListener($type, $listener);
}

class Event
{
    /** @var string */
    protected $type;

    /** @var mixed */
    protected $detail;
    /** @var bool */
    protected $propagationStopped = false;

    /** @var ?Observable */
    protected $observer           = null;

    /**
     * @param non-empty-string $type
     * @param mixed            $detail
     */
    public function __construct($type, $detail = null)
    {
        if ( ! $type || ! is_string($type))
        {
            throw new \InvalidArgumentException('$type must be a non empty string');
        }

        $this->type   = $type;
        $this->detail = $detail;
    }

    /** @return bool */
    final public function isPropagationStopped()
    {
        return $this->propagationStopped;
    }

    /** @return static */
    final public function stopPropagation()
    {
        $this->propagationStopped = true;
        return $this;
    }

    /** @return string */
    final public function getType()
    {
        return $this->type;
    }

    /** @return mixed */
    final public function getDetail()
    {
        return $this->detail;
    }

    /**
     * @param mixed $detail
     *
     * @return static
     */
    final public function setDetail($detail)
    {
        $this->detail = $detail;
        return $this;
    }

    /**
     * @return ?Observable
     */
    final public function getObserver()
    {
        return $this->observer;
    }

    /**
     * @param Observable $observer
     *
     * @return Event
     */
    final public function setObserver(Observable $observer)
    {
        $this->observer = $observer;
        return $this;
    }

    /**
     * @param non-empty-string $type
     * @param mixed            $detail
     *
     * @return static
     */
    public static function newEvent($type, $detail = null)
    {
        return new static($type, $detail);
    }
}

final class EventDispatcher implements Observable
{
    private $listeners = [];

    public function dispatchEvent(Event $event)
    {
        if ( ! $event->isPropagationStopped())
        {
            $event->setObserver($this);
            $type = $event->getType();

            if ( ! empty($this->listeners[$type]))
            {
                krsort($this->listeners[$type]);

                foreach ($this->listeners[$type] as $listenersSortedByPriority)
                {
                    foreach ($listenersSortedByPriority as $listener)
                    {
                        $listener($event);

                        if ($event->isPropagationStopped())
                        {
                            break 2;
                        }
                    }
                }
            }
        }

        return $event;
    }

    /**
     * @param non-empty-string $type
     * @param callable         $listener
     * @param int              $priority greater priority will be executed first
     */
    public function addEventListener($type, $listener, $priority = 100)
    {
        if ( ! $type || ! is_string($type))
        {
            throw new \InvalidArgumentException('$type must be a non empty string');
        }

        if ( ! isset($this->listeners[$type]))
        {
            $this->listeners[$type] = [];
        }

        if ( ! isset($this->listeners[$type][$priority]))
        {
            $this->listeners[$type][$priority] = [];
        }
        $this->listeners[$type][$priority][] = $listener;
    }

    /**
     * @param non-empty-string $type
     * @param callable         $listener
     * @param int              $priority greater priority will be executed first
     */
    public function removeEventListener($type, $listener, $priority = 100)
    {
        if ( ! $type || ! is_string($type))
        {
            throw new \InvalidArgumentException('$type must be a non empty string');
        }

        if ( ! isset($this->listeners[$type]))
        {
            return;
        }

        if ( ! isset($this->listeners[$type][$priority]))
        {
            return;
        }

        $this->listeners[$type][$priority] = array_values(array_filter($this->listeners[$type][$priority], function ($callable) use ($listener)
        {
            return $listener !== $callable;
        }));
    }
}
}
namespace {

class EventListener
{
    /** @var null|\Observable\EventDispatcher */
    protected static $instance = null;

    /**
     * @return \Observable\EventDispatcher
     */
    public static function getInstance()
    {
        if ( ! self::$instance)
        {
            self::$instance = new \Observable\EventDispatcher();
        }

        return self::$instance;
    }

    /**
     * @param non-empty-string $eventType
     * @param callable         $listener
     * @param int              $priority  greater priority will be executed first
     */
    public static function addEventListener($eventType, $listener, $priority = 100)
    {
        self::getInstance()->addEventListener($eventType, $listener, $priority);
    }

    /**
     * @param non-empty-string $eventType
     * @param callable         $listener
     * @param int              $priority  greater priority will be executed first
     */
    public static function removeEventListener($eventType, $listener, $priority = 100)
    {
        self::getInstance()->removeEventListener($eventType, $listener, $priority);
    }

    /**
     * @param non-empty-string $eventType
     * @param mixed            $data
     *
     * @return \Observable\Event
     */
    public static function dispatchEvent($eventType, $data = null)
    {
        return self::getInstance()->dispatchEvent(new \Observable\Event($eventType, $data));
    }
}

class HeaderManager implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @var array<string,string>
     */
    private $names  = [];

    /**
     * @var array<string,string[]>
     */
    private $values = [];

    /**
     * @param array<string,string|string[]> $headers
     *
     * @return static
     */
    public static function of(array $headers)
    {
        $instance = new static();
        return $instance->setHeaders($headers);
    }

    /**
     * @param string $name
     *
     * @return string[]
     */
    public function getHeader($name)
    {
        if ( ! $this->hasHeader($name))
        {
            return [];
        }

        return $this->values[$this->normalizeName($name)];
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getHeaderLine($name)
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @return string
     */
    public function getRawHeaders()
    {
        $str = '';

        foreach ($this->names as $name)
        {
            $str .= sprintf("%s: %s\n", $name, $this->getHeaderLine($name));
        }
        return rtrim($str);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasHeader($name)
    {
        return isset($this->names[$this->normalizeName($name)]);
    }

    /**
     * @param string          $name
     * @param string|string[] $value
     * @param string          ...$otherValues
     *
     * @return static
     */
    public function addHeader($name, $value)
    {
        $values = $this->getHeader($name);

        if ( ! is_array($value))
        {
            $value = array_slice(func_get_args(), 1);
        }

        foreach ($value as $v)
        {
            if ( ! in_array($v, $values))
            {
                $values[] = $v;
            }
        }

        $this->setHeader($name, $values);

        return $this;
    }

    /**
     * @param array<string,string|string[]> $values
     *
     * @return static
     */
    public function setHeaders(array $values)
    {
        $this->names = $this->values = [];

        foreach ($values as $name => $value)
        {
            if ( ! is_array($value))
            {
                $value = [$value];
            }
            $this->setHeader($name, $value);
        }

        return $this;
    }

    /**
     * @param string          $name
     * @param string|string[] $value
     * @param string          ...$otherValues
     *
     * @return static
     */
    public function setHeader($name, $value)
    {
        $norm                = $this->normalizeName($name);
        $real                = $this->getHeaderName($norm);

        if ( ! is_array($value))
        {
            $value = array_slice(func_get_args(), 1);
        }

        $this->names[$norm]  = $real;
        $this->values[$norm] = $value;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function removeHeader($name)
    {
        unset($this->names[$this->normalizeName($name)], $this->values[$this->normalizeName($name)]);
        return $this;
    }

    /**
     * @return \Traversable
     */
    public function getIterator()
    {
        foreach ($this->names as $name)
        {
            yield $name => $this->getHeaderLine($name);
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->names);
    }

    /**
     * @return array<string,string>
     */
    public function toArray()
    {
        return iterator_to_array($this);
    }

    /**
     * @return array<string,string>
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function getHeaderName($name)
    {
        $normalized = $this->normalizeName($name);

        if (isset($this->names[$normalized]))
        {
            return $this->names[$normalized];
        }

        return ucfirst(preg_replace_callback('#-([a-z])#', function ($matches)
        {
            return strtoupper($matches[0]);
        }, $normalized));
    }

    private function normalizeName($name)
    {
        return strtolower($name);
    }
}

class CurlHandler
{
    const METHOD_GET                       = 'GET';
    const METHOD_HEAD                      = 'HEAD';
    const METHOD_POST                      = 'POST';
    const METHOD_PUT                       = 'PUT';
    const METHOD_PATCH                     = 'PATCH';
    const METHOD_DELETE                    = 'DELETE';

    const METHOD_OPTIONS                   = 'OPTIONS';
    const METHOD_CONNECT                   = 'CONNECT';
    const METHOD_TRACE                     = 'TRACE';
    const METHOD_POST_JSON                 = 'POSTJSON';
    const METHOD_PUT_JSON                  = 'PUTJSON';
    const METHOD_PATCH_JSON                = 'PATCHJSON';

    protected static $firefoxVersions      = null;
    protected static $latestFirefoxVersion = '140.0';
    /**
     * @see https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     */
    protected static $REASON_PHRASES       = [
        0   => 'Unassigned',
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * Valid Methods.
     */
    protected static $VALID_METHODS        = [
        self::METHOD_GET,
        self::METHOD_HEAD,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_DELETE,
        self::METHOD_CONNECT,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_PATCH,
    ];

    /**
     * Experimental technology to fetch a long list of urls faster
     * Only supports GET method with no header parsing.
     *
     * @param string[]|\Stringable[] $urls
     *
     * @return \HttpClient\CurlResponse[] returns responses in order of urls
     */
    public static function makeMultiGetRequests(array $urls)
    {
        $multi   = new \HttpClient\CurlMultiRequest();
        $cookies = tempnam(sys_get_temp_dir(), 'curl_multi');

        foreach ($urls as $url)
        {
            $req = (new \HttpClient\CurlRequest());
            $multi->add(
                $req
                    // prevent multi handler to follow using synchronous request
                    ->setOpt(CURLOPT_FOLLOWLOCATION, true)
                    // as headers cannot be defined in that function,
                    // make belief we are in the last firefox version
                    ->setUserAgent(self::generateUserAgent())
                    // cookie support if needed
                    ->setCookieFile($cookies)
                    ->prepare(self::METHOD_GET, $url)
            );
        }

        // make request
        return $multi->execute()->getResults();
    }

    /**
     * @param string|\Stringable                $url
     * @param null|array<string, string>|string $params
     * @param string|\Stringable                $method
     * @param ?array<string, string|string[]>   $headers
     * @param int                               $timeout
     *
     * @return \HttpClient\CurlResponse
     */
    public static function makeHttpRequest($url, $params = null, $method = 'GET', $headers = null, $timeout = 0)
    {
        $req = new \HttpClient\CurlRequest();
        $req->enableHeaderParsing();

        if (is_int($headers))
        {
            $timeout = $headers;
            $headers = null;
        }

        if (is_array($method))
        {
            $headers = $method;
            $method  = 'GET';
        }

        if (is_array($headers))
        {
            $usable = [];

            foreach ($headers as $name => $val)
            {
                // add custom curl options to request
                if ('curl-options' === strtolower($name))
                {
                    if (is_array($val))
                    {
                        $req->setOpts($val);
                    }
                    continue;
                }

                if ('cookie-file' === strtolower($name))
                {
                    $req->setCookieFile($val);
                    continue;
                }
                $usable[$name] = $val;
            }
            $req->setHeaders($usable);
        }

        if ($timeout > 0)
        {
            $req->setTimeout($timeout);
        }

        try
        {
            return $req->fetch($method, $url, $params);
        } finally
        {
            $req->closeHandle();
        }
    }

    /**
     * @param string $method
     * @param bool   $normalize
     *
     * @return bool
     */
    public static function isValidMethod($method, $normalize = true)
    {
        if ($normalize)
        {
            $method = strtoupper($method);
        }
        return in_array($method, self::$VALID_METHODS);
    }

    /**
     * @param null|bool|int|string $version true => random, null|false => latest, int => "$version.0"
     *
     * @return string
     */
    public static function generateUserAgent($version = null)
    {
        /**
         * @see https://wiki.mozilla.org/Release_Management/Product_details
         */
        static $ffListApi = 'https://product-details.mozilla.org/1.0/firefox_history_major_releases.json',
        $ffLastApi        = 'https://product-details.mozilla.org/1.0/firefox_versions.json',
        $template         = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:{version}) Gecko/20100101 Firefox/{version}';

        if ( ! isset(self::$firefoxVersions))
        {
            $cachedFile = sys_get_temp_dir() . '/curl_firefox_versions.json';
            $cachedData = false;
            @mkdir(dirname($cachedFile), 0777, true);

            if (@filemtime($cachedFile) > time() - 3600)
            {
                $cachedData = @file_get_contents($cachedFile);

                if (is_string($cachedData))
                {
                    $cachedData = json_decode($cachedData, true);
                }
            }

            if ( ! $cachedData)
            {
                $versions = [];

                if ($list = self::makeSimpleGetHttpRequest($ffListApi))
                {
                    foreach (array_reverse($list) as $ver => $date)
                    {
                        if (strtotime($date) < strtotime('-3 years'))
                        {
                            continue;
                        }

                        if ( ! preg_match('#^\d+\.\d+$#', $ver))
                        {
                            continue;
                        }

                        if (strtotime($date) < time())
                        {
                            $versions[] = $ver;
                        }
                    }
                }

                $latest   = self::$latestFirefoxVersion;

                if ( ! empty($versions))
                {
                    $latest = $versions[0];
                }

                $data     = self::makeSimpleGetHttpRequest($ffLastApi);

                if ($data)
                {
                    $latest = $data['LATEST_FIREFOX_VERSION'];
                }

                if ( ! empty($versions))
                {
                    $cachedData = [$versions, $latest];
                    @file_put_contents($cachedFile, json_encode($cachedData));
                }
            }

            if ($cachedData)
            {
                list(self::$firefoxVersions, self::$latestFirefoxVersion) = $cachedData;
            }
        }

        if ( ! empty($version))
        {
            if (is_int($version))
            {
                $version = "{$version}.0";
            } elseif (true === $version)
            {
                $version = self::$firefoxVersions[array_rand(self::$firefoxVersions)];
            }
        } else
        {
            $version = self::$latestFirefoxVersion;
        }

        $version          = preg_replace('#^(\d+\.\d+)\D*.*$#', '$1', $version);

        return str_replace('{version}', $version, $template);
    }

    public static function makeSimpleGetHttpRequest($url)
    {
        $json = @file_get_contents(
            $url,
            false,
            stream_context_create([
                'http' => ['method' => 'GET'],
                'ssl'  => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ])
        ) ?: '';

        if (null === $decoded = @json_decode($json, true))
        {
            return $json;
        }
        return $decoded;
    }

    public static function getReasonPhrase($statusCode)
    {
        return isset(self::$REASON_PHRASES[$statusCode]) ? self::$REASON_PHRASES[$statusCode] : self::$REASON_PHRASES[0];
    }
}
}
namespace HttpClient{

class CurlMultiRequest implements \Lockable, \IteratorAggregate, \Countable
{
    /** @var null|\CurlMultiHandle|resource */
    protected $handle;
    protected $closed         = true;
    protected $ready          = false;
    protected $locked         = false;
    /** @var array<string,CurlRequest> */
    protected $curlHandles    = [];
    /** @var ?array<string,CurlResponse> */
    protected $results        = null;
    /** @var ?array<string,CurlRequest> */
    protected $resultRequests = null;

    public function __destruct()
    {
        if ( ! $this->closed)
        {
            @curl_multi_close($this->handle);
        }
    }

    /**
     * @return static
     */
    public function execute()
    {
        if ($this->isLocked() || ! $this->ready)
        {
            throw new \RuntimeException('CurlMultiRequest is locked or requests are not ready yet.');
        }

        $this->ready   = false;
        $this->lock();

        $this->results = $this->resultRequests = [];
        $results       = [];
        $handles       = [];
        $mh            = $this->getHandle();

        $n             = 0;

        foreach ($this->curlHandles as $curlHandle)
        {
            if ($curlHandle->isReady())
            {
                ++$n;
                @curl_multi_add_handle(
                    $mh,
                    $handles[$curlHandle->getUid()] = $curlHandle->getHandle()
                );
            }
        }

        if ( ! $n)
        {
            throw new \RuntimeException('no requests are ready.');
        }

        $active        = null;

        do
        {
            $mrc = curl_multi_exec($mh, $active);
        } while (CURLM_CALL_MULTI_PERFORM == $mrc);

        while ($active && CURLM_OK == $mrc)
        {
            curl_multi_select($mh);
            usleep(90);

            do
            {
                $mrc = curl_multi_exec($mh, $active);
            } while (CURLM_CALL_MULTI_PERFORM == $mrc);

            while ($info = curl_multi_info_read($mh))
            {
                $ch            = $info['handle'];
                curl_multi_remove_handle($mh, $ch);
                $uid           = array_search($ch, $handles, true);
                $req           = $this->curlHandles[$uid];

                $result        = $req->getResult();

                // redirection
                if ($req->isReady())
                {
                    $result = $req->execute();
                }
                $results[$uid] = $result;
            }
        }

        $this->unlock();

        // sort results by request order
        foreach (array_keys($this->curlHandles) as $uid)
        {
            if (isset($results[$uid]))
            {
                $this->results[$uid]        = $results[$uid];
                $this->resultRequests[$uid] = $this->curlHandles[$uid];
                $this->remove($uid);
            }
        }

        return $this->closeHandle();
    }

    /**
     * @return CurlResponse[]
     */
    public function getResults()
    {
        if (empty($this->results))
        {
            return [];
        }

        return $this->results;
    }

    /**
     * @param CurlRequest|string $request
     *
     * @return static
     */
    public function remove($request)
    {
        if ( ! $this->isLocked())
        {
            if ($request instanceof CurlRequest)
            {
                $request = $request->getUid();
            }

            if (is_string($request))
            {
                unset($this->curlHandles[$request]);
            }

            $this->ready = array_any($this->curlHandles, function ($request)
            {
                return $request->isReady();
            });
        }

        return $this;
    }

    public function add(CurlRequest $curlRequest)
    {
        if ( ! $this->isLocked())
        {
            $this->curlHandles[$curlRequest->getUid()] = $curlRequest;

            if ($curlRequest->isReady())
            {
                $this->ready = true;
            }
        }

        return $this;
    }

    /**
     * @param CurlRequest[] $requests
     *
     * @return $this
     */
    public function addMany(array $requests)
    {
        foreach ($requests as $request)
        {
            if ( ! $request instanceof CurlRequest)
            {
                throw new \InvalidArgumentException('$requests must be of type CurlRequest[]');
            }
            $this->add($request);
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function isReady()
    {
        return $this->ready;
    }

    /**
     * @return \CurlMultiHandle|resource
     */
    public function getHandle()
    {
        if ($this->closed)
        {
            $this->handle = @curl_multi_init();
            $this->closed = false;
        }

        return $this->handle;
    }

    /**
     * @return static
     */
    public function closeHandle()
    {
        if ( ! $this->closed)
        {
            @curl_multi_close($this->handle);
            $this->closed = true;
            $this->ready  = false;
            $this->handle = null;
        }

        return $this;
    }

    public function lock()
    {
        $this->locked = true;
    }

    public function unlock()
    {
        $this->locked = false;
    }

    public function isLocked()
    {
        return $this->locked;
    }

    /**
     * @return \Traversable<CurlRequest,CurlResponse>
     */
    public function getIterator()
    {
        if (is_array($this->results))
        {
            foreach ($this->results as $uid => $response)
            {
                yield $this->resultRequests[$uid] => $response;
            }
        }
    }

    public function count()
    {
        return is_array($this->results) ? count($this->results) : 0;
    }
}

/**
 * @property ?resource $file
 * @property string    $uid
 * @property int       $requestCount
 */
class CurlRequest
{
    /**
     * Methods.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods
     */
    const GET                  = 'GET';
    const HEAD                 = 'HEAD';
    const POST                 = 'POST';
    const PUT                  = 'PUT';
    const DELETE               = 'DELETE';
    const CONNECT              = 'CONNECT';
    const OPTIONS              = 'OPTIONS';
    const TRACE                = 'TRACE';
    const PATCH                = 'PATCH';

    /** @var null|\CurlHandle|resource */
    protected $handle          = null;
    protected $closed          = true;
    protected $ready           = false;
    /** @var string */
    protected $uid;

    protected $options         = [];

    /** @var ?resource */
    protected $file            = null;
    protected $initialCount    = 0;

    protected $requestHeaders  = [];
    protected $requestCount    = 0;

    protected $parseHeaders    = false;
    protected $rawHeaders      = '';
    protected $responseHeaders = [];

    /** @var null|CurlResponse */
    protected $previous        = null;

    public function __construct()
    {
        $this->uid     = \generate_uid();
        $this->options = [
            \CURLOPT_ENCODING       => 'gzip,deflate',
            \CURLOPT_AUTOREFERER    => true,
            \CURLOPT_SSL_VERIFYPEER => 0,
        ];
    }

    public function __destruct()
    {
        if ( ! $this->closed)
        {
            @curl_close($this->handle);
        }
    }

    public function __get($name)
    {
        if ( ! $this->__isset($name))
        {
            return null;
        }
        return $this->{$name};
    }

    public function __isset($name)
    {
        return property_exists($this, $name) && null !== $this->{$name};
    }

    public function __set($name, $value) {}

    public function __unset($name) {}

    /**
     * @param string|\Stringable $method
     * @param string|\Stringable $url
     * @param null|array|string  $params
     *
     * @return CurlResponse
     */
    public function fetch($method, $url, $params = null)
    {
        return $this
            ->prepare($method, $url, $params)
            ->execute();
    }

    /**
     * Make a GET request.
     *
     * @param string            $url
     * @param null|array|string $params
     * @param ?array            $headers
     *
     * @return CurlResponse
     */
    public function get($url, $params = null, $headers = null)
    {
        if (is_array($headers))
        {
            $this->setHeaders($headers);
        }
        return $this->fetch(self::GET, $url, $params);
    }

    /**
     * Make a POST request
     * if params are json please set header: "content-type" => "application/json".
     *
     * @param string            $url
     * @param null|array|string $params
     * @param ?array            $headers
     *
     * @return CurlResponse
     */
    public function post($url, $params = null, $headers = null)
    {
        if (is_array($headers))
        {
            $this->setHeaders($headers);
        }

        return $this->fetch(self::POST, $url, $params);
    }

    /**
     * @param string|\Stringable $method
     * @param string|\Stringable $url
     * @param null|array|string  $params
     *
     * @return static
     */
    public function prepare($method, $url, $params = null)
    {
        $url                   = (string) $url;
        $method                = (string) $method;

        $this->previous        = null;
        $this->responseHeaders = [];
        $this->rawHeaders      = '';
        $this->initialCount    = $this->requestCount;

        $json                  = false;
        $requestMethod         = strtoupper($method);

        if (preg_match('#^(.+)JSON$#', $requestMethod, $matches))
        {
            $requestMethod = $matches[1];
            $json          = true;
        }

        if ( ! \CurlHandler::isValidMethod($requestMethod))
        {
            throw new \InvalidArgumentException("Invalid method {$requestMethod}");
        }

        // for faster requests
        $this->unsetOpt(\CURLOPT_HEADERFUNCTION);

        if ($this->parseHeaders)
        {
            $this->setOpt(\CURLOPT_HEADERFUNCTION, $this->generateHeaderFunction());
        }

        $this->setOpt(\CURLOPT_CUSTOMREQUEST, $requestMethod);

        if ( ! $this->getHeader('content-type') && 'GET' !== $requestMethod)
        {
            $this->addHeader('content-type', 'application/x-www-form-urlencoded');

            if ($json)
            {
                $this->addHeader('content-type', 'application/json');
            }
        }

        if (is_array($params))
        {
            $params = $json ? json_encode($params) : http_build_query($params);
        }

        $this->unsetOpt(\CURLOPT_POSTFIELDS);

        if ('GET' === $method && ! $json)
        {
            $this->unsetOpt(\CURLOPT_CUSTOMREQUEST);

            if ( ! empty($params))
            {
                $url .= false !== strpos($url, '?') ? '&' : '?';
                $url .= $params;
            }
        } elseif (is_string($params))
        {
            $this->setOpt(\CURLOPT_POSTFIELDS, $params);
        }

        $this->unsetOpt(\CURLOPT_HTTPHEADER);

        if ( ! empty($this->requestHeaders))
        {
            $this->setOpt(\CURLOPT_HTTPHEADER, $this->makeHeaders());
        }

        $this->setOpt(\CURLOPT_URL, $url);
        $this->setOpt(\CURLOPT_FILE, $this->createFileHandle());
        $ch                    = $this->getHandle();
        curl_reset($ch);

        foreach ($this->options as $name => $value)
        {
            curl_setopt($this->getHandle(), $name, $value);
        }
        $this->ready           = true;
        return $this;
    }

    /**
     * @return CurlResponse
     */
    public function getResult()
    {
        $ch                 = $this->getHandle();
        $info               = curl_getinfo($ch);
        $statusCode         = intval($info['http_code']);
        $success            = 0 !== $statusCode;
        $statusText         = \CurlHandler::getReasonPhrase($statusCode);
        $info['status']     = $statusCode;
        $info['statusText'] = $statusText;
        $info['error']      = [
            curl_errno($ch) => curl_error($ch),
        ];

        $redirections       = ($this->requestCount - $this->initialCount) - 1;

        if ( ! empty($info['redirect_count']))
        {
            $redirections = $info['redirect_count'];
        }

        $resp               = CurlResponse::make([
            'success'      => $success,
            'info'         => $info,
            'stream'       => $this->file,
            'headers'      => $this->responseHeaders,
            'previous'     => $this->previous,
            'redirections' => $redirections,
        ]);

        // prevent infinite loop in execute on multi redirects
        $this->ready        = false;

        // auto redirect (301,302)
        if ( ! empty($info['redirect_url']))
        {
            $this->previous        = $resp;
            // reset data for new request
            $this->rawHeaders      = '';
            $this->responseHeaders = [];
            curl_setopt($ch, \CURLOPT_FILE, $this->file = @fopen('php://temp', 'r+'));
            curl_setopt($ch, \CURLOPT_URL, $info['redirect_url']);
            $this->ready           = true;
        }
        return $resp;
    }

    /**
     * @return null|CurlResponse
     */
    public function execute()
    {
        if ($this->ready)
        {
            $ch = $this->getHandle();

            while (1)
            {
                @set_time_limit(120);
                @curl_exec($ch);

                $resp = $this->getResult();

                // redirection
                if ($this->ready)
                {
                    continue;
                }
                return $resp;
            }
        }
        return null;
    }

    /**
     * @return \CurlHandle|resource
     */
    public function getHandle()
    {
        if ($this->closed)
        {
            $this->handle = curl_init();
            $this->closed = false;
        }
        return $this->handle;
    }

    /**
     * @return static
     */
    public function closeHandle()
    {
        if ( ! $this->closed)
        {
            @curl_close($this->handle);
            $this->closed = true;
            $this->ready  = false;
            $this->uid    = \generate_uid();
            $this->handle = null;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @return int
     */
    public function getRequestCount()
    {
        return $this->requestCount;
    }

    /**
     * @return bool
     */
    public function isReady()
    {
        return $this->ready;
    }

    /**
     * @param int   $option
     * @param mixed $value
     *
     * @return static
     */
    public function setOpt($option, $value)
    {
        $this->options[$option] = $value;
        return $this;
    }

    /**
     * @param array<int,mixed> $options
     *
     * @return static
     */
    public function setOpts(array $options)
    {
        foreach ($options as $option => $value)
        {
            $this->setOpt($option, $value);
        }
        return $this;
    }

    /**
     * @param int $option
     *
     * @return static
     */
    public function unsetOpt($option)
    {
        unset($this->options[$option]);
        return $this;
    }

    /**
     * @param string $file
     *
     * @return static
     */
    public function setCookieFile($file)
    {
        $umask = @umask(0);
        @mkdir(dirname($file), 0777, true);
        @umask($umask);

        if ( ! is_writable(dirname($file)))
        {
            throw new \RuntimeException("Cookie file {$file} cannot be created.");
        }
        $this->setOpt(\CURLOPT_COOKIEFILE, $file);
        return $this->setOpt(\CURLOPT_COOKIEJAR, $file);
    }

    /**
     * @param int $timeout
     *
     * @return static
     */
    public function setTimeout($timeout)
    {
        if (is_int($timeout) && $timeout > 0)
        {
            $this->setOpts([
                \CURLOPT_CONNECTTIMEOUT => $timeout,
                \CURLOPT_TIMEOUT        => $timeout,
            ]);
        }

        return $this;
    }

    /**
     * @param null|bool|string $userAgent
     *
     * @return static
     */
    public function setUserAgent($userAgent = null)
    {
        if (is_int($userAgent) || true === $userAgent || null === $userAgent)
        {
            $userAgent = \CurlHandler::generateUserAgent($userAgent);
        }

        unset($this->requestHeaders['user-agent']);

        if (false === $userAgent)
        {
            unset($this->options[\CURLOPT_USERAGENT]);
            return $this;
        }

        return $this->setOpt(\CURLOPT_USERAGENT, $userAgent);
    }

    /**
     * @return bool
     */
    public function canParseHeaders()
    {
        return $this->parseHeaders;
    }

    /**
     * @param bool $parseHeaders
     *
     * @return static
     */
    public function enableHeaderParsing($parseHeaders = true)
    {
        $this->parseHeaders = false !== $parseHeaders;
        return $this;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getHeader($name)
    {
        if ( ! isset($this->requestHeaders[strtolower($name)]))
        {
            return '';
        }
        return $this->requestHeaders[strtolower($name)];
    }

    /**
     * Erases previous headers and replaces them with provided values.
     *
     * @param array<string,string|string[]> $headers
     *
     * @return static
     */
    public function setHeaders(array $headers)
    {
        $this->requestHeaders = [];
        return $this->addHeaders($headers);
    }

    /**
     * @param array<string,string|string[]> $headers
     *
     * @return static
     */
    public function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value)
        {
            $this->addHeader($name, $value);
        }
        return $this;
    }

    /**
     * @param string          $name
     * @param string|string[] $value
     *
     * @return $this
     */
    public function addHeader($name, $value)
    {
        if ( ! is_array($value))
        {
            $value = array_slice(func_get_args(), 1);
        }

        if (is_string($name))
        {
            $name                        = strtolower($name);

            if ('user-agent' === $name)
            {
                return $this->setOpt(\CURLOPT_USERAGENT, $value[0]);
            }
            $this->requestHeaders[$name] = implode(', ', $value);

            if ('referer' === $name)
            {
                unset($this->options[\CURLOPT_AUTOREFERER]);
            }
        }
        return $this;
    }

    public function removeHeader($name)
    {
        unset($this->requestHeaders[strtolower($name)]);
        return $this;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function getHeaderName($name)
    {
        return ucfirst(preg_replace_callback('#-([a-z])#', function ($matches)
        {
            return strtoupper($matches[0]);
        }, strtolower($name)));
    }

    /**
     * @return string[]
     */
    protected function makeHeaders()
    {
        $headers = [];

        foreach ($this->requestHeaders as $name => $value)
        {
            $headers[] = sprintf('%s: %s', $this->getHeaderName($name), $value);
        }
        return $headers;
    }

    /**
     * @return resource
     */
    protected function createFileHandle()
    {
        if ($this->file)
        {
            @fclose($this->file);
        }
        return $this->file = @fopen('php://temp', 'r+');
    }

    protected function generateHeaderFunction()
    {
        return function ()
        {
            $doNotSplit               = ['set-cookie'];
            $this->rawHeaders .= $raw = func_get_arg(1);
            $len                      = strlen($raw);

            if ( ! empty($line = rtrim($raw)) && preg_match('#^(\H+):\h+(.+)$#', $line, $matches))
            {
                $responseHeaders       = &$this->responseHeaders;
                list(, $name, $values) = $matches;
                $name                  = strtolower($name);

                if ( ! isset($responseHeaders[$name]))
                {
                    $responseHeaders[$name] = [];
                }

                // dates and others
                if (in_array($name, $doNotSplit) || false !== strtotime($values))
                {
                    $responseHeaders[$name][] = trim($values);
                    return $len;
                }

                foreach (explode(',', $values) as $value)
                {
                    $responseHeaders[$name][] = trim($value);
                }
            } elseif (0 === strpos($raw, 'HTTP/'))
            {
                // detects a new request
                $this->responseHeaders = [];
                $this->rawHeaders      = $raw;
                ++$this->requestCount;
            }

            return $len;
        };
    }
}

/**
 * @property string            $body
 * @property int               $status
 * @property string            $statusText
 * @property array<int,string> $error
 */
class CurlResponse
{
    /**
     * @var array<string,mixed>
     */
    public $info           = null;

    public $success        = false;

    public $redirections   = 0;
    protected $contents    = null;
    protected $stream      = null;
    protected $headers     = [];

    /**
     * @var array<string,string>
     */
    protected $headerNames = [];

    protected $previous    = null;

    public function __destruct()
    {
        if ($this->stream)
        {
            @fclose($this->stream);
        }
    }

    public function __get($name)
    {
        if ('body' === $name)
        {
            return $this->getContents();
        }

        if ($this->__isset($name))
        {
            return $this->info[$name];
        }

        return null;
    }

    public function __isset($name)
    {
        if ('body' === $name)
        {
            return true;
        }

        return is_array($this->info) && isset($this->info[$name]);
    }

    public function __set($name, $value) {}

    public function __unset($name) {}

    /**
     * @return ?static
     */
    public function getPrevious()
    {
        return $this->previous;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param string $header
     *
     * @return bool
     */
    public function hasHeader($header)
    {
        return isset($this->headerNames[strtolower($header)]);
    }

    /**
     * @param string $header
     *
     * @return array
     */
    public function getHeader($header)
    {
        $header = strtolower($header);

        if ( ! isset($this->headerNames[$header]))
        {
            return [];
        }

        $header = $this->headerNames[$header];
        return $this->headers[$header];
    }

    /**
     * @param string $header
     *
     * @return string
     */
    public function getHeaderLine($header)
    {
        return implode(', ', $this->getHeader($header));
    }

    /**
     * @return string
     */
    public function getRawHeaders()
    {
        $str = '';

        foreach (array_keys($this->headerNames) as $name)
        {
            $str .= sprintf("%s: %s\n", $this->headerNames[$name], $this->getHeaderLine($name));
        }

        return rtrim($str);
    }

    /**
     * @param array   $data
     * @param ?static $instance
     *
     * @return static
     */
    public static function make(array $data, $instance = null)
    {
        if ( ! isset($instance))
        {
            $instance = new static();
        }

        foreach ($data as $key => $value)
        {
            if (property_exists($instance, $key))
            {
                $instance->{$key} = $value;
            }
        }
        $instance->fixHeaders();
        return $instance;
    }

    /**
     * @return ?string
     */
    public function getContents()
    {
        if ( ! isset($this->contents))
        {
            $this->contents = '';

            if ($this->stream)
            {
                if (-1 !== @fseek($this->stream, 0))
                {
                    $this->contents = stream_get_contents($this->stream);
                }
                @fclose($this->stream);
                $this->stream = null;
            }
        }
        return $this->contents;
    }

    /**
     * @return mixed
     */
    public function getDecodedContents()
    {
        $contents = $this->getContents();

        if (null === ($value = @json_decode($contents, true)))
        {
            $value = $contents;
        }

        return $value;
    }

    protected function fixHeaders()
    {
        if ($this->stream)
        {
            $this->contents = null;
        }

        $this->headerNames = [];

        foreach (array_keys($this->headers) as $lowercased)
        {
            $lowercased                     = strtolower($lowercased);
            $name                           = preg_replace_callback('#-\w#', function ($matches)
            {
                return strtoupper($matches[0]);
            }, ucfirst($lowercased));
            $this->headerNames[$lowercased] = $name;
        }
        $headers           = $this->headers;
        $this->headers     = [];

        foreach ($this->headerNames as $lower => $name)
        {
            $this->headers[$name] = $headers[$lower];
        }
    }
}
}
namespace {

class Mutex
{
    /** @var bool */
    protected $locked   = false;

    /** @var ?array{string,string,int,float} owner,process,pid,timeout */
    protected $lock     = null;

    /** @var string */
    protected $name;

    /** @var string */
    protected $file;

    /** @var int */
    protected $pid;

    /** @var ?string */
    protected $process  = null;

    /** @var float */
    protected $duration;
    /** @var string */
    protected $owner;

    protected $sleep_ms = 250.0;

    /**
     * @param string  $name
     * @param ?float  $duration
     * @param ?string $owner
     */
    public function __construct($name, $duration = 0.0, $owner = null)
    {
        if (empty($name))
        {
            throw new \InvalidArgumentException('You provided an empty lock name.');
        }

        $this->duration = max(0.0, $duration);
        $this->name     = $name;
        $file           = str_replace('\\', '/', $name);

        if ( ! preg_match('#^([a-z]:)?/#i', $file))
        {
            if (preg_match('#^\./#', $file))
            {
                $file = getcwd() . substr($file, 1);
            } else
            {
                $file = sys_get_temp_dir() . '/' . $file;
            }
        }

        if ( ! preg_match('#\.tmp$#i', $file))
        {
            $file .= '.tmp';
        }

        $this->file     = $file;
        $this->pid      = getmypid();
        $this->owner    = $owner ?: uniqid();
        $running        = $this->tasklist();

        if (isset($running[$this->pid]))
        {
            $this->process = $running[$this->pid];
        }
    }

    public function __destruct()
    {
        $this->release();
    }

    /**
     * @return bool
     */
    public function acquire()
    {
        if ($this->locked)
        {
            return true;
        }

        if ($data = $this->read())
        {
            if ($data->owner === $this->owner && $data->pid === $this->pid && $data->process === $this->process)
            {
                return $this->locked = true;
            }

            if ($this->running($data->pid, $data->process))
            {
                return false;
            }

            if ( ! $this->expired($data->timeout))
            {
                return false;
            }
        }

        $max = 3;

        while ($max > 0)
        {
            if ($this->write($this->duration + $this->now()))
            {
                return $this->locked = true;
            }
            --$max;
            $this->wait();
        }

        return false;
    }

    /**
     * Attempt to acquire the lock.
     *
     * @param ?callable $callback
     *
     * @return mixed
     */
    public function get($callback = null)
    {
        $ok = $this->acquire();

        if ($ok && $callback)
        {
            try
            {
                return $callback();
            } finally
            {
                $this->release();
            }
        }

        return $ok;
    }

    /**
     * Attempt to acquire the lock for the given number of seconds.
     *
     * @param float         $seconds
     * @param null|callable $callback
     *
     * @return mixed
     */
    public function block($seconds, $callback = null)
    {
        @set_time_limit(0);

        $timeout = $this->now() + (float) $seconds - ($this->sleep_ms / 1000);

        while ( ! $this->acquire())
        {
            if ($this->expired($timeout))
            {
                throw new \TimedOutMutexException(sprintf('Lock "%s" timed out (%f sec)', $this->name, $seconds));
            }
            $this->wait($this->sleep_ms);
        }

        if ($callback)
        {
            try
            {
                return $callback();
            } finally
            {
                $this->release();
            }
        }
        return true;
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release()
    {
        if ($this->locked)
        {
            $this->forceRelease();
            return true;
        }
        return false;
    }

    /**
     * Returns the current owner of the lock.
     *
     * @return string
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Releases this lock in disregard of ownership.
     */
    public function forceRelease()
    {
        $this->lock   = null;
        $this->locked = false;
        @unlink($this->file);
    }

    /**
     * @param float $timestamp
     *
     * @return bool
     */
    protected function expired($timestamp)
    {
        return $timestamp < $this->now();
    }

    /**
     * @param float|int $ms
     */
    protected function wait($ms = 0)
    {
        if (0 === $ms)
        {
            $ms = 100 + rand(-10, 10);
        }
        usleep($ms * 1000);
    }

    /**
     * @return float
     */
    protected function now()
    {
        if (2 === sscanf(microtime(), '%f %f', $usec, $sec))
        {
            return (float) $sec + (float) $usec;
        }
        return microtime(true);
    }

    /**
     * @return ?object{owner:string,process?:string,pid:int,timeout:float}
     */
    protected function read()
    {
        $data = null;

        if ($this->lock)
        {
            $data = $this->lock;
        } elseif ($content = @file_get_contents($this->file))
        {
            $data = unserialize($content);

            if ($this->owner === $data[0] && $this->process === $data[1] && $this->pid === $data[2])
            {
                $this->lock = $data;
            }
        }

        if ($data)
        {
            return (object) [
                'owner'   => $data[0],
                'process' => $data[1],
                'pid'     => $data[2],
                'timeout' => $data[3],
            ];
        }

        return null;
    }

    /**
     * @param float $timeout
     *
     * @return bool
     */
    protected function write($timeout)
    {
        $mask = @umask(0);

        try
        {
            $dir = dirname($this->file);
            @mkdir($dir, 0777, true);

            if (is_dir($dir) && is_writable($dir))
            {
                $this->lock = [$this->owner, $this->process, $this->pid, $timeout];

                if (@file_put_contents($this->file, serialize($this->lock)))
                {
                    @chmod($this->file, 0777);
                    return true;
                }
            }
        } finally
        {
            @umask($mask);
        }

        return false;
    }

    /**
     * @param int     $pid
     * @param ?string $proc
     *
     * @return bool
     */
    protected function running($pid, $proc = null)
    {
        $running = $this->tasklist();

        if ( ! empty($running[$pid]))
        {
            return ! $proc || is_string(strstr($running[$pid], $proc));
        }
        return false;
    }

    /**
     * @return array<int,string>
     */
    protected function tasklist()
    {
        $result = [];

        if ('\\' === DIRECTORY_SEPARATOR)
        {
            $csv = mb_convert_encoding(trim(shell_exec('TASKLIST /SVC /FO CSV')), 'UTF-8', 'Windows-1252');

            foreach (preg_split('#[\n\r]+#', $csv) as $line)
            {
                @list($proc, $pid) = array_map(
                    function ($val)
                    {
                        $val = trim($val, '" ');

                        if (is_numeric($val))
                        {
                            $val = intval($val);
                        }
                        return $val;
                    },
                    explode(',', $line)
                );

                if (is_int($pid))
                {
                    $result[$pid] = $proc;
                }
            }
        } else
        {
            $data = preg_split('#[\n\r]+#', trim(shell_exec('ps')));
            array_shift($data);

            foreach ($data as $line)
            {
                $segments = preg_split('#\h+#', trim($line));

                if (count($segments) >= 4)
                {
                    $proc = array_slice($segments, -1)[0];
                    $pid  = (int) $segments[0];

                    if ($pid && $proc)
                    {
                        $result[$pid] = $proc;
                    }
                }
            }
        }
        return $result;
    }
}

class TimedOutMutexException extends \RuntimeException {}
}
namespace DataStructure{

interface ReversibleIterator extends \IteratorAggregate
{
    /**
     * @param bool $reversed
     *
     * @return \Traversable
     */
    public function entries($reversed = false);

    /** @return \Traversable */
    public function getReverseIterator();
}

final class SortableIterator implements ReversibleIterator, \Countable, \Lockable
{
    /** @var iterable */
    private $iterator;
    /** @var bool */
    private $static;
    private $keys   = [];
    private $values = [];
    private $locked = false;

    /**
     * @param iterable $iterator The iterable to iterate
     * @param bool     $static   true if the state of the iterator cannot change
     */
    public function __construct($iterator, $static = false)
    {
        if ( ! is_iterable($iterator))
        {
            throw new \InvalidArgumentException(sprintf('$iterator must be iterable, %s given', get_debug_type($iterator)));
        }
        $this->iterator = $iterator;
        $this->static   = false !== $static;
    }

    public function __debugInfo()
    {
        return [];
    }

    /**
     * @param iterable $iterable
     * @param bool     $static
     *
     * @return static
     */
    public static function of($iterable, $static = false)
    {
        return new self($iterable, $static);
    }

    /**
     * @param \ArrayAccess&\Countable|iterable $value
     *
     * @return static
     */
    public static function ofList($value)
    {
        if (is_iterable($value))
        {
            return self::of($value);
        }

        if ( ! ($value instanceof \ArrayAccess && $value instanceof \Countable))
        {
            throw new \InvalidArgumentException(sprintf('$value must be of type ArrayAccess&Countable, %s given', get_debug_type($value)));
        }

        if (\is_list($value))
        {
            $iterator         = new self([], true);

            foreach (Range::of($value) as $offset)
            {
                $iterator->append($offset, $value[$offset]);
            }
            $iterator->locked = true;

            return $iterator;
        }

        throw new \OutOfRangeException(sprintf('%s cannot determine list of keys.', \get_debug_type($value)));
    }

    /**
     * @param mixed $value
     *
     * @return static
     */
    public static function ofString($value)
    {
        $value = (string) $value;
        return new self('' === $value ? [] : mb_str_split($value), true);
    }

    public function entries($reversed = false)
    {
        $offsets = $this->getOffsets();

        if ($reversed)
        {
            $offsets = array_reverse($offsets);
        }
        return $this->yieldOffsets($offsets);
    }

    /** @return \Traversable */
    public function getIterator()
    {
        return $this->entries();
    }

    /** @return \Traversable */
    public function getReverseIterator()
    {
        return $this->entries(true);
    }

    /** @return int */
    public function count()
    {
        return count($this->getOffsets());
    }

    public function lock()
    {
        $this->locked = true;
    }

    public function unlock()
    {
        $this->locked = false;
    }

    public function isLocked()
    {
        return $this->locked;
    }

    /**
     * @param mixed $key
     * @param mixed $value
     *
     * @internal
     */
    private function append($key, $value)
    {
        if ( ! $this->locked)
        {
            $this->keys[]   = $key;
            $this->values[] = $value;
        }
    }

    /**
     * @internal
     */
    private function reset()
    {
        if ( ! $this->static)
        {
            $this->keys   = [];
            $this->values = [];
            $this->locked = false;
        }
    }

    /**
     * @return \Traversable
     *
     * @internal
     */
    private function yieldOffsets(array $offsets)
    {
        foreach ($offsets as $offset)
        {
            yield $this->keys[$offset] => $this->values[$offset];
        }

        $this->reset();
    }

    /**
     * @return array
     *
     * @internal
     */
    private function getOffsets()
    {
        if ( ! $this->locked)
        {
            foreach ($this->iterator as $key => $value)
            {
                $this->append($key, $value);
            }
            $this->locked = true;
        }

        return array_keys($this->keys);
    }
}

final class Range implements ReversibleIterator, \Stringable
{
    /** @var int */
    public $start;
    /** @var null|int */
    public $stop;
    /** @var int */
    public $step;
    /** @var null|int */
    private $length = null;

    /**
     * @param int      $start
     * @param null|int $stop
     * @param int      $step
     */
    public function __construct(
        $start,
        $stop = null,
        $step = 1
    ) {
        if (0 === $step)
        {
            throw new \InvalidArgumentException('Step cannot be 0');
        }

        if (is_null($stop))
        {
            $stop  = $start;
            $start = 0;
        }

        list($this->start, $this->stop, $this->step) = [$start, $stop, $step];
    }

    public function __debugInfo()
    {
        return [
            'params' => $this->__toString(),
            'length' => $this->count(),
        ];
    }

    public function __toString()
    {
        return sprintf('range(%d, %d, %d)', $this->start, $this->stop, $this->step);
    }

    /**
     * Creates a Range.
     *
     * @param int      $start
     * @param null|int $stop
     * @param int      $step
     *
     * @return static
     */
    public static function create($start, $stop = null, $step = 1)
    {
        return new self($start, $stop, $step);
    }

    /**
     * Get a range for a Countable.
     *
     * @param array|\Countable $countable
     *
     * @return self
     */
    public static function of($countable)
    {
        if ( ! is_countable($countable))
        {
            throw new \InvalidArgumentException('$countable is not countable');
        }
        return new self(0, count($countable));
    }

    /**
     * Checks if empty range.
     */
    public function isEmpty()
    {
        return $this->step > 0 ? $this->stop <= $this->start : $this->stop >= $this->start;
    }

    public function count()
    {
        if (is_null($this->length))
        {
            $this->length = 0;

            if ( ! $this->isEmpty())
            {
                list($min, $max, $step) = [$this->start, $this->stop, abs($this->step)];

                if ($min > $max)
                {
                    list($min, $max) = [$max, $min];
                }

                $this->length           = intval(ceil(($max - $min) / $step));
            }
        }

        return $this->length;
    }

    public function entries($reversed = false)
    {
        if ( ! $this->isEmpty())
        {
            if ($reversed)
            {
                for ($offset = -1; $offset >= -$this->count(); --$offset)
                {
                    yield $this->getOffset($offset);
                }
            } else
            {
                for ($offset = 0; $offset < $this->count(); ++$offset)
                {
                    yield $this->getOffset($offset);
                }
            }
        }
    }

    public function getIterator()
    {
        return $this->entries();
    }

    public function getReverseIterator()
    {
        return $this->entries(true);
    }

    /**
     * @param int $offset
     *
     * @return int
     */
    private function getOffset($offset)
    {
        if (0 > $offset)
        {
            $offset += $this->count();
        }
        return $this->start + ($offset * $this->step);
    }
}

abstract class Common implements ReversibleIterator, \Stringable, \Countable, \Lockable
{
    protected $locked = false;

    public function __toString()
    {
        return sprintf('object(%s)', get_class($this));
    }

    /**
     * Lock the object.
     */
    public function lock()
    {
        $this->locked = true;
    }

    /**
     * Unlock the object.
     */
    public function unlock()
    {
        $this->locked = false;
    }

    /**
     * Get the lock status.
     *
     * @return bool
     */
    public function isLocked()
    {
        return $this->locked;
    }

    /** @return \Traversable */
    public function getIterator()
    {
        return $this->entries();
    }

    /** @return \Traversable */
    public function getReverseIterator()
    {
        return $this->entries(true);
    }

    /**
     * Tests if at least one of the elements from the storage pass the test implemented by the callable.
     *
     * @param callable $callback
     *
     * @return bool
     */
    public function some(callable $callback)
    {
        $empty = true;

        foreach ($this->entries() as $key => $value)
        {
            $empty = false;

            if ( ! $callback($value, $key, $this))
            {
                return false;
            }
        }

        return ! $empty;
    }

    /**
     * Tests if all the elements from the storage pass the test implemented by the callable.
     *
     * @param callable $callback
     *
     * @return bool
     */
    public function every(callable $callback)
    {
        foreach ($this->entries() as $key => $value)
        {
            if ( ! $callback($value, $key, $this))
            {
                return false;
            }
        }
        return true;
    }

    /**
     * Runs the given callable for each of the elements.
     *
     * @param callable $callback
     */
    public function each(callable $callback)
    {
        foreach ($this->entries() as $key => $value)
        {
            $callback($value, $key, $this);
        }
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return 0 === $this->count();
    }

    /**
     * Sorts array.
     *
     * @param array $array
     * @param bool  $reversed
     *
     * @return array
     */
    protected function sortArray(array $array, $reversed = false)
    {
        if ($reversed)
        {
            return array_reverse($array);
        }

        return $array;
    }

    /**
     * Helper to be used with __clone() method.
     *
     * @param array $array
     * @param bool  $recursive
     *
     * @return array
     */
    protected function cloneArray(array $array, $recursive = true)
    {
        $result = [];

        foreach ($array as $offset => $value)
        {
            if (is_object($value))
            {
                $result[$offset] = clone $value;
                continue;
            }

            if (is_array($value) && $recursive)
            {
                $result[$offset] = $this->cloneArray($value, $recursive);
                continue;
            }

            $result[$offset] = $value;
        }

        return $result;
    }
}

/**
 * The Map object holds key-value pairs and remembers the original insertion order of the keys.
 * Any value (both objects and primitive values) may be used as either a key or a value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Map JS Map
 */
final class Map extends Common implements \ArrayAccess, \JsonSerializable, \Serializable
{
    /** @var array */
    protected $keys   = [];
    /** @var array */
    protected $values = [];

    /**
     * @param null|iterable $iterable
     */
    public function __construct($iterable = null)
    {
        if (is_iterable($iterable))
        {
            $this->importIterable($iterable);
        }
    }

    public function __debugInfo()
    {
        $result = [];

        foreach ($this->entries() as $key => $value)
        {
            $result[is_scalar($key) ? $key : get_debug_type($key)] = $value;
        }

        return $result;
    }

    public function __serialize()
    {
        return [$this->keys, $this->values, $this->locked];
    }

    public function __unserialize(array $data)
    {
        list($this->keys, $this->values, $this->locked) = $data;
    }

    public function __clone()
    {
        $this->keys   = $this->cloneArray($this->keys);
        $this->values = $this->cloneArray($this->values);
    }

    /**
     * Create a new Map.
     *
     * @return static
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Creates a new instance initialized with an iterable.
     *
     * @param iterable $iterable
     *
     * @return static
     */
    public static function of($iterable)
    {
        return new self($iterable);
    }

    public function serialize()
    {
        return serialize($this->__serialize());
    }

    public function unserialize($data)
    {
        $this->__unserialize(unserialize($data));
    }

    public function jsonSerialize()
    {
        $result = [];

        foreach ($this->entries() as $key => $value)
        {
            $result[] = [$key, $value];
        }

        return $result;
    }

    /**
     * The clear() method removes all elements from a Map object.
     */
    public function clear()
    {
        if ( ! $this->isLocked())
        {
            $this->keys = $this->values = [];
        }
    }

    /**
     * The delete() method removes the specified element from a Map object by key.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function delete($key)
    {
        if ( ! $this->isLocked())
        {
            if (($index = $this->indexOf($key)) > -1)
            {
                unset($this->keys[$index], $this->values[$index]);
                return true;
            }
        }

        return false;
    }

    /**
     * The get() method returns a specified element from a Map object.
     * If the value associated with the provided key is an object,
     * then you will get a reference to that object and any change made
     * to that object will effectively modify it inside the Map object.
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return
            ($index = $this->indexOf($key)) > -1
                ? $this->values[$index]
                : null;
    }

    /**
     * The search() method returns the first key match from a value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function search($value)
    {
        return
            ($index = $this->indexOfValue($value)) > -1
                ? $this->keys[$index]
                : null;
    }

    /**
     * The entries() method returns a new iterator object that contains the [key, value] pairs for each element in the Map object in insertion order.
     *
     * @param mixed $reversed
     */
    public function entries($reversed = false)
    {
        foreach ($this->sortArray(array_keys($this->keys), $reversed) as $offset)
        {
            yield $this->keys[$offset] => $this->values[$offset];
        }
    }

    /**
     * The set() method adds or updates an element with a specified key and a value to a Map object.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return $this
     */
    public function set($key, $value)
    {
        $this->delete($key);
        return $this->append($key, $value);
    }

    /**
     * The add() method adds an element with a specified key and a value to a Map object if it doesn't already exist.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return static
     */
    public function add($key, $value)
    {
        if ($this->has($key))
        {
            return $this;
        }
        return $this->append($key, $value);
    }

    /**
     * The has() method returns a boolean indicating whether an element with the specified key exists or not.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function has($key)
    {
        return $this->indexOf($key) > -1;
    }

    /**
     * The keys() method returns a new iterator object that contains the keys for each element in the Map object in insertion order.
     *
     * @param bool $reversed
     *
     * @return iterable
     */
    public function keys($reversed = false)
    {
        foreach ($this->sortArray($this->keys, $reversed) as $key)
        {
            yield $key;
        }
    }

    /**
     * The values() method returns a new iterator object that contains the values for each element in the Map object in insertion order.
     *
     * @param bool $reversed
     *
     * @return iterable
     */
    public function values($reversed = false)
    {
        foreach ($this->sortArray($this->values, $reversed) as $value)
        {
            yield $value;
        }
    }

    public function count()
    {
        return count($this->keys);
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }

    private function indexOf($key)
    {
        $index = array_search($key, $this->keys, true);
        return false !== $index ? $index : -1;
    }

    private function indexOfValue($value)
    {
        $index = array_search($value, $this->values, true);
        return false !== $index ? $index : -1;
    }

    private function append($key, $value)
    {
        if ( ! $this->isLocked())
        {
            $this->keys[]   = $key;
            $this->values[] = $value;
        }

        return $this;
    }

    /**
     * @param iterable $iterable
     */
    private function importIterable($iterable)
    {
        foreach ($iterable as $item)
        {
            if ( ! is_list($item) || 2 < count($item))
            {
                continue;
            }

            $this->set($item[0], $item[1]);
        }
    }
}

/**
 * The Set object lets you store unique values of any type, whether primitive values or object references.
 */
final class Set extends Common implements ReversibleIterator, \JsonSerializable, \Serializable
{
    /** @var array */
    private $storage = [];

    /**
     * @param null|iterable $iterable
     */
    public function __construct($iterable = null)
    {
        if (is_iterable($iterable))
        {
            $this->importIterable($iterable);
        }
    }

    public function __clone()
    {
        $this->storage = $this->cloneArray($this->storage);
    }

    /**
     * @return array
     */
    public function __serialize()
    {
        return [$this->storage, $this->locked];
    }

    /**
     * @param array $data
     */
    public function __unserialize(array $data)
    {
        list($this->storage, $this->locked) = $data;
    }

    /**
     * Create a new Set.
     *
     * @return static
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Creates a new instance initialized with an iterable.
     *
     * @param iterable $iterable
     *
     * @return static
     */
    public static function of($iterable)
    {
        return new self($iterable);
    }

    /**
     * The has() method returns a boolean indicating whether an element with the specified value exists in a Set object or not.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function has($value)
    {
        return -1 !== $this->indexOf($value);
    }

    /**
     * The add() method appends a new element with a specified value to the end of a Set object.
     *
     * @param mixed $value
     *
     * @return static
     */
    public function add($value)
    {
        if ( ! $this->has($value) && ! $this->isLocked())
        {
            $this->storage[] = $value;
        }
        return $this;
    }

    /**
     * The delete() method removes a specified value from a Set object if it is in the set.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function delete($value)
    {
        if ( ! $this->isLocked())
        {
            $offset = $this->indexOf($value);

            if ($offset > -1)
            {
                unset($this->storage[$offset]);
                return true;
            }
        }

        return false;
    }

    /**
     * The clear() method removes all elements from a Set object.
     */
    public function clear()
    {
        if ( ! $this->isLocked())
        {
            $this->storage = [];
        }
    }

    /**
     * The values() method returns a new Iterator object that contains the values for each element in the Set object in insertion order.
     *
     * @param bool $reversed
     *
     * @return iterable
     */
    public function values($reversed = false)
    {
        foreach ($this->getIndexes($reversed) as $offset)
        {
            yield $this->storage[$offset];
        }
    }

    /**
     * The entries() method returns a new Iterator object that contains an array of [value, value] for each element in the Set object, in insertion order.
     *
     * @param mixed $reversed
     */
    public function entries($reversed = false)
    {
        foreach ($this->getIndexes($reversed) as $offset)
        {
            yield $this->storage[$offset] => $this->storage[$offset];
        }
    }

    public function serialize()
    {
        return serialize($this->__serialize());
    }

    public function unserialize($data)
    {
        $this->__unserialize(unserialize($data));
    }

    public function count()
    {
        return count($this->storage);
    }

    public function jsonSerialize()
    {
        return $this->storage;
    }

    /**
     * @param iterable $iterable
     */
    private function importIterable($iterable)
    {
        foreach ($iterable as $item)
        {
            $this->add($item);
        }
    }

    /**
     * @param mixed $value
     *
     * @return int
     */
    private function indexOf($value)
    {
        $index = array_search($value, $this->storage, true);
        return false !== $index ? $index : -1;
    }

    /**
     * @param bool $reversed
     *
     * @return \Traversable
     */
    private function getIndexes($reversed = false)
    {
        foreach ($this->sortArray(array_keys($this->storage), $reversed) as $index)
        {
            yield $index;
        }
    }
}
}
namespace Sql{

const FETCH_ASSOC = 2;
const FETCH_NUM   = 3;
const FETCH_BOTH  = 4;
const FETCH_OBJ   = 5;

interface Driver
{
    /**
     * Returns the driver type (mysql,sqlite, ...).
     *
     * @return string
     */
    public function type();

    /** @return null|object|resource */
    public function link();

    /**
     * @param string $string
     *
     * @return string
     */
    public function quote($string);

    /** @return bool */
    public function beginTransaction();

    /** @return bool */
    public function rollBack();

    /** @return bool */
    public function commit();

    /**
     * @param array{host: ?string, username: ?string, password: ?string, database: ?string, charset: ?string} $params
     *
     * @return bool
     */
    public function connect(array $params);

    /** @return bool */
    public function close();

    /** @return array{int, string} */
    public function error();

    /**
     * @param string $query
     *
     * @return false|Result
     */
    public function query($query);

    /**
     * @param string $query
     *
     * @return bool
     */
    public function exec($query);

    /** @return int|string */
    public function lastInsertId();

    /**
     * @param string $query
     *
     * @return false|Statement
     */
    public function prepare($query);

    /**
     * @param Statement $statement
     * @param array     $params
     *
     * @return false|Statement
     */
    public function bindParams($statement, array $params);

    /**
     * @param Statement $statement
     *
     * @return false|Result
     */
    public function execute($statement);

    /**
     * @param Result $result
     * @param int{2,3,4,5} $mode
     *
     * @return null|array|object
     */
    public function fetch($result, $mode = FETCH_BOTH);
}

class SqlException extends \Exception
{
    /**
     * @param string $message
     * @param        ...$replacements
     *
     * @return static
     */
    public static function newInstance($message = '', $replacements = [])
    {
        if ( ! is_array($replacements))
        {
            $replacements = array_slice(func_get_args(), 1);
        }

        if ( ! empty($replacements))
        {
            $message = vsprintf($message, $replacements);
        }

        return new static($message);
    }

    public static function cannotConnect($prev = null)
    {
        return new self(
            'Cannot connect to database',
            0,
            $prev
        );
    }

    public static function cannotPrepare($prev = null)
    {
        return new self(
            'Cannot prepare SQL statement, invalid query',
            0,
            $prev
        );
    }

    public static function cannotBind($prev = null)
    {
        return new self(
            'Cannot bind params, invalid number of parameters',
            0,
            $prev
        );
    }

    public static function cannotExecute($prev = null)
    {
        return new self(
            'Cannot execute query',
            0,
            $prev
        );
    }

    public static function cannotFetch($prev = null)
    {
        return new self(
            'Cannot fetch row',
            0,
            $prev
        );
    }

    public static function cannotStartTransaction($prev = null)
    {
        return new self(
            'Cannot start transaction',
            0,
            $prev
        );
    }

    public static function cannotEndTransaction($prev = null)
    {
        return new self(
            'Cannot end transaction',
            0,
            $prev
        );
    }
}

interface Maker
{
    /**
     * @param array<int|string, mixed> $data
     * @param ?static                  $instance
     *
     * @return static
     */
    public static function make(array $data, $instance = null);
}

/**
 * Preload class for constants.
 */
class_exists(Driver::class);

class Statement implements \IteratorAggregate
{
    /** @var Driver */
    protected $driver;

    /** @var \mysqli_stmt|object|\PDOStatement|resource|\SQLite3Stmt|string */
    protected $statement;
    protected $sql    = '';

    /**
     * @var false|Result
     */
    protected $result = false;

    /**
     * @param Driver                 $driver
     * @param object|resource|string $statement
     * @param string                 $sql
     */
    public function __construct(Driver $driver, $statement, $sql = '')
    {
        if ( ! is_object($statement) && ! is_string($statement) && ! is_resource($statement))
        {
            throw new \InvalidArgumentException(sprintf('$statement argument must be a string, resource or object, %s given', get_debug_type($statement)));
        }

        if ( ! is_string($sql))
        {
            throw new \InvalidArgumentException(sprintf('$sql argument must be a string, %s given', get_debug_type($sql)));
        }

        $this->driver    = $driver;
        $this->statement = $statement;
        $this->sql       = $sql;
    }

    public function __debugInfo()
    {
        return [
            'driver'    => get_debug_type($this->driver),
            'statement' => get_debug_type($this->statement),
            'result'    => get_debug_type($this->result),
        ];
    }

    /**
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /** @return Driver */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return \mysqli_stmt|object|\PDOStatement|resource|\SQLite3Stmt|string
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @return false|Result
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Get the last insert id from the driver.
     *
     * @return int|string
     */
    public function lastInsertId()
    {
        return $this->driver->lastInsertId();
    }

    /**
     * @param array $params
     *
     * @return static
     */
    public function bindParams(array $params)
    {
        return $this->driver->bindParams($this, $params) ?: $this;
    }

    /**
     * @param array $bindings
     *
     * @return null|static
     */
    public function execute(array $bindings = [])
    {
        // clears previous result set
        $this->result = false;

        // add mysqli/pdo php 8.2 execute shortcut for php5+
        if (count($bindings))
        {
            if ( ! $this->driver->bindParams($this, $bindings))
            {
                return null;
            }
        }

        if ($this->result = $this->driver->execute($this))
        {
            return $this;
        }
        return null;
    }

    /**
     * Iterates all the results from the set.
     *
     * @param int{2,3,4,5} $mode
     *
     * @return \Traversable
     */
    public function fetch($mode = FETCH_BOTH)
    {
        if ( ! $this->result)
        {
            return new \EmptyIterator();
        }
        return $this->result->fetch($mode);
    }

    /**
     * Returns one row from the set.
     *
     * @param int{2,3,4,5} $mode
     *
     * @return null|array|object
     */
    public function fetchOne($mode = FETCH_BOTH)
    {
        if ( ! $this->result)
        {
            return null;
        }
        return $this->result->fetchOne($mode);
    }

    /**
     * Returns all the results at once.
     *
     * @param int{2,3,4,5} $mode
     *
     * @return array[]|Maker[]|object[]
     */
    public function fetchAll($mode = FETCH_BOTH)
    {
        if ( ! $this->result)
        {
            return [];
        }
        return $this->result->fetchAll($mode);
    }

    /**
     * Returns one column from the next result row.
     *
     * @param int $columnIndex
     *
     * @return mixed
     */
    public function fetchCol($columnIndex = 0)
    {
        if ( ! $this->result)
        {
            return null;
        }
        return $this->result->fetchCol($columnIndex);
    }

    /**
     * Returns list of the selected column from the result rows.
     *
     * @param int $columnIndex
     *
     * @return float[]|int[]|string[]
     */
    public function fetchList($columnIndex = 0)
    {
        if ( ! $this->result)
        {
            return [];
        }
        return $this->result->fetchList($columnIndex);
    }

    /**
     * @template T of Maker
     *
     * @param mixed $className
     *
     * @psalm-param class-string<T>|object<T> $className
     *
     * @return null|T
     */
    public function make($className)
    {
        if ($this->result)
        {
            return $this->result->make($className);
        }
        return null;
    }

    /**
     * @template T of Maker
     *
     * @param mixed $className
     *
     * @psalm-param class-string<T>|object<T> $className
     *
     * @return T[]
     */
    public function makeMany($className)
    {
        if ($this->result)
        {
            return $this->result->makeMany($className);
        }
        return [];
    }

    public function getIterator()
    {
        return $this->fetch(FETCH_ASSOC);
    }
}

/**
 * Preload class for constants.
 */
class_exists(Driver::class);

class Result implements \IteratorAggregate
{
    /** @var Driver */
    protected $driver;

    /** @var null|array|bool|\mysqli_result|object|\PDOStatement|resource|\SQLite3Result */
    protected $result;

    /** @var ?class-string<Maker> */
    protected $maker = null;

    public function __construct(Driver $driver, $result)
    {
        $this->driver = $driver;
        $this->result = $result;
    }

    public function __debugInfo()
    {
        return [
            'driver' => get_debug_type($this->driver),
            'result' => get_debug_type($this->result),
        ];
    }

    /** @return Driver */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @return null|array|bool|\mysqli_result|object|\PDOStatement|resource|\SQLite3Result
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param null|class-string<Maker> $maker
     *
     * @return static
     */
    public function setMaker($maker)
    {
        if (null !== $maker && ( ! is_string($maker) || ! is_subclass_of($maker, Maker::class)))
        {
            throw new \InvalidArgumentException('$maker must implement ' . Maker::class);
        }
        $this->maker = $maker;
        return $this;
    }

    /**
     * Get the last insert id from the driver.
     *
     * @return int|string
     */
    public function lastInsertId()
    {
        return $this->driver->lastInsertId();
    }

    /**
     * Iterates all the results from the set.
     *
     * @param int{2,3,4,5} $mode
     *
     * @return \Traversable
     */
    public function fetch($mode = FETCH_BOTH)
    {
        while (null !== $row = $this->fetchOne($mode))
        {
            yield $this->useMaker($row, $mode);
        }
    }

    /**
     * Returns all the results at once.
     *
     * @param int{2,3,4,5} $mode
     *
     * @return array
     */
    public function fetchAll($mode = FETCH_BOTH)
    {
        return iterator_to_array($this->fetch($mode));
    }

    /**
     * Returns list of the selected column from the result rows.
     *
     * @param int $columnIndex
     *
     * @return float[]|int[]|string[]
     */
    public function fetchList($columnIndex = 0)
    {
        $all = [];

        while (null !== $value = $this->fetchCol($columnIndex))
        {
            $all[] = $value;
        }
        return $all;
    }

    /**
     * Returns one row from the set.
     *
     * @param int{2,3,4,5} $mode
     *
     * @return null|array|object
     */
    public function fetchOne($mode = FETCH_BOTH)
    {
        $result = $this->driver->fetch($this, $mode);
        return empty($result) ? null : $this->useMaker($result, $mode);
    }

    /**
     * Returns one column from the next result row.
     *
     * @param int $columnIndex
     *
     * @return mixed
     */
    public function fetchCol($columnIndex = 0)
    {
        if (null !== ($result = $this->fetchOne(FETCH_NUM)) && isset($result[$columnIndex]))
        {
            return $result[$columnIndex];
        }
        return null;
    }

    /**
     * @template T of Maker
     *
     * @param mixed $className
     *
     * @psalm-param class-string<T>|object<T> $className
     *
     * @return null|T
     */
    public function make($className)
    {
        try
        {
            $orig        = $this->maker;
            $this->maker = null;

            if (is_subclass_of($className, Maker::class, is_string($className)))
            {
                $obj  = is_object($className) ? $className : null;
                $name = is_object($className) ? get_class($className) : $className;

                if ($data = $this->fetchOne())
                {
                    return $name::make($data, $obj);
                }
            }
            return null;
        } finally
        {
            $this->maker = $orig;
        }
    }

    /**
     * @template T of Maker
     *
     * @param mixed $className
     *
     * @psalm-param class-string<T>|object<T> $className
     *
     * @return T[]
     */
    public function makeMany($className)
    {
        try
        {
            $orig        = $this->maker;
            $this->maker = null;
            $result      = [];

            if (is_subclass_of($className, Maker::class, is_string($className)))
            {
                $name = is_object($className) ? get_class($className) : $className;

                foreach ($this->fetch() as $data)
                {
                    $result[] = $name::make($data);
                }
            }
            return $result;
        } finally
        {
            $this->maker = $orig;
        }
    }

    public function getIterator()
    {
        return $this->fetch(FETCH_ASSOC);
    }

    /**
     * @param null|array|object $data
     * @param int               $mode
     *
     * @return null|array|Maker|object
     */
    private function useMaker($data, $mode)
    {
        if (is_array($data) && $this->maker && class_exists($this->maker) && in_array($mode, [FETCH_ASSOC, FETCH_BOTH]))
        {
            $class = $this->maker;
            return $class::make($data);
        }
        return $data;
    }
}

class QueryHelper
{
    /** @var Driver */
    protected $driver;

    /** @var Builder\QueryBuilder */
    protected $builder       = null;

    /** @var bool */
    protected $queryNullable = false;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @return bool
     */
    public function isQueryNullable()
    {
        return $this->queryNullable;
    }

    /**
     * Set Query result null instead of false
     * to keep compatibility with existing code.
     * Useful if able to use null-safe operators (PHP8).
     *
     * @param bool $queryNullable
     *
     * @return static
     */
    public function setQueryNullable($queryNullable)
    {
        $this->queryNullable = false !== $queryNullable;
        return $this;
    }

    /**
     * @return Builder\QueryBuilder
     */
    public function getBuilder()
    {
        if ( ! $this->builder)
        {
            $this->builder = new Builder\QueryBuilder();
            $this->builder->setQueryHelper($this);
        }

        return $this->builder;
    }

    /**
     * Begins a SELECT statement.
     *
     * @param string ...$fields
     *
     * @return Builder\QueryBuilder
     */
    public function select($fields = '*')
    {
        return $this->getBuilder()->select( ! is_array($fields) ? func_get_args() : $fields);
    }

    /**
     * Begins a SELECT COUNT(*) statement.
     *
     * @param ?string $table if defined, will also select table (from)
     * @param ?string $alias
     *
     * @return Builder\QueryBuilder
     */
    public function selectCount($table = null, $alias = null)
    {
        $select = $this->select('COUNT(*)');

        if (is_string($table))
        {
            $select->from($table, $alias);
        }
        return $select;
    }

    /**
     * Begins an UPDATE statement.
     *
     * @param string  $table
     * @param ?string $alias
     *
     * @return Builder\QueryBuilder
     */
    public function update($table, $alias = null)
    {
        return $this->getBuilder()->update($table, $alias);
    }

    /**
     * Begins an UPDATE statement.
     *
     * @param string $table
     *
     * @return Builder\QueryBuilder
     */
    public function insert($table)
    {
        return $this->getBuilder()->insert($table);
    }

    /**
     * Begins a DELETE statement.
     *
     * @param string  $table
     * @param ?string $alias
     *
     * @return Builder\QueryBuilder
     */
    public function delete($table, $alias = null)
    {
        return $this->getBuilder()->delete($table, $alias);
    }

    /**
     * Returns the driver type (mysql,sqlite, ...).
     *
     * @return string
     */
    public function type()
    {
        return $this->driver->type();
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function quote($string)
    {
        return $this->driver->quote($string);
    }

    /** @return bool */
    public function beginTransaction()
    {
        return $this->driver->beginTransaction();
    }

    /** @return bool */
    public function rollBack()
    {
        return $this->driver->rollBack();
    }

    /** @return bool */
    public function commit()
    {
        return $this->driver->commit();
    }

    /**
     * @param array{host: ?string, username: ?string, password: ?string, database: ?string, charset: ?string} $params
     *
     * @return bool
     */
    public function connect(array $params)
    {
        return $this->driver->connect($params);
    }

    /** @return bool */
    public function close()
    {
        return $this->driver->close();
    }

    /** @return array{int, string} */
    public function error()
    {
        return $this->driver->error();
    }

    /**
     * @param string $query
     * @param array  $params
     *
     * @return null|false|Result return null if queryNullable set to true
     */
    public function query($query, array $params = [])
    {
        $result = null;

        if ( ! empty($params))
        {
            if ($stmt = $this->driver->prepare($query))
            {
                $stmt->execute($params);

                if ($stmt->getResult())
                {
                    $result = $stmt->getResult();
                }
            }
        } else
        {
            $result = $this->driver->query($query);
        }

        if ($result instanceof Result)
        {
            return $result;
        }
        return $this->queryNullable ? null : false;
    }

    /**
     * @param string $query
     *
     * @return bool
     */
    public function exec($query)
    {
        return $this->driver->exec($query);
    }

    /** @return int|string */
    public function lastInsertId()
    {
        return $this->driver->lastInsertId();
    }

    /**
     * @param string $query
     *
     * @return false|Statement
     */
    public function prepare($query)
    {
        return $this->driver->prepare($query);
    }

    /**
     * @param Statement $statement
     * @param array     $params
     *
     * @return false|Statement
     */
    public function bindParams($statement, array $params)
    {
        return $this->driver->bindParams($statement, $params);
    }

    /**
     * @param Statement $statement
     *
     * @return null|Result
     */
    public function execute($statement)
    {
        return $this->driver->execute($statement) ?: null;
    }

    /**
     * @param Result $result
     * @param int{2,3,4,5} $mode
     *
     * @return null|array|object
     */
    public function fetch($result, $mode = FETCH_BOTH)
    {
        return $this->driver->fetch($result, $mode);
    }

    /**
     * @return Driver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @param Driver $driver
     *
     * @return static
     */
    public function setDriver(Driver $driver)
    {
        $this->driver = $driver;
        return $this;
    }
}
}
namespace Sql\Builder{

class Expression implements \Countable, \Stringable
{
    const TYPE_AND = 'AND';
    const TYPE_OR  = 'OR';

    /** @var string */
    private $type;
    /** @var static[] */
    private $parts = [];

    /**
     * @param string $type
     * @param array  $parts
     */
    public function __construct($type, array $parts = [])
    {
        if ( ! in_array($type, [self::TYPE_AND, self::TYPE_OR]))
        {
            throw new \InvalidArgumentException("Invalid type {$type}");
        }
        $this->type = $type;
        $this->addMany($parts);
    }

    public function __clone()
    {
        foreach ($this->parts as &$part)
        {
            if ($part instanceof self)
            {
                $part = clone $part;
            }
        }
    }

    public function __toString()
    {
        if (1 === $this->count())
        {
            return sprintf('%s', $this->parts[0]);
        }

        return sprintf('(%s)', implode(
            sprintf(') %s (', $this->type),
            $this->parts
        ));
    }

    /**
     * @param non-empty-string|static $part
     *
     * @return static
     */
    public function add($part)
    {
        if (( ! empty($part) && is_string($part)) || ($part instanceof self && $part->count() > 0))
        {
            $this->parts[] = $part;
        }
        return $this;
    }

    public function addMany(array $parts = [])
    {
        foreach ($parts as $part)
        {
            $this->add($part);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function getParts()
    {
        return $this->parts;
    }

    public function count()
    {
        return count($this->parts);
    }
}

/**
 * Improved version of Doctrine DBAL QueryBuilder v2.5.13, using pure SQL.
 *
 * @see https://github.com/doctrine/dbal/blob/v2.5.13/lib/Doctrine/DBAL/Query/QueryBuilder.php
 */
class QueryBuilder implements \Countable, \Stringable
{
    const SELECT         = 0;
    const DELETE         = 1;
    const UPDATE         = 2;
    const INSERT         = 3;

    const INNER_JOIN     = 'INNER JOIN';
    const LEFT_JOIN      = 'LEFT JOIN';
    const RIGHT_JOIN     = 'RIGHT JOIN';

    private $sql         = null;
    private $params      = [];
    private $extraParams = []; // having clause
    private $type        = self::SELECT;
    /** @var string[] */
    private $fields      = [];
    /** @var array{string, ?string}[] */
    private $tables      = [];
    /** @var array<string,array{type: string, table: string, alias: string, cond: string}[]> */
    private $joins       = [];
    /** @var ?Expression */
    private $where       = null;
    /** @var string[] */
    private $group_by    = [];
    /** @var ?Expression */
    private $having      = null;
    /** @var string[] */
    private $order_by    = [];
    /** @var array<string, mixed> */
    private $values      = [];
    /** @var ?int */
    private $offset      = null;
    /** @var ?int */
    private $limit       = null;
    private $aliases     = [];
    private $joinRef     = [];
    // merged params
    private $allParams   = [];

    /** @var \Sql\QueryHelper */
    private $queryHelper;

    /** @var ?class-string<\Sql\Maker> */
    private $maker       = null;

    public function __clone()
    {
        if (is_object($this->where))
        {
            $this->where = clone $this->where;
        }

        if (is_object($this->having))
        {
            $this->having = clone $this->having;
        }
    }

    public function __toString()
    {
        return $this->getSql();
    }

    /**
     * @param array $params
     *
     * @return null|\Sql\Statement
     */
    public function execute(array $params = [])
    {
        if ($this->queryHelper)
        {
            if ($stmt = $this->queryHelper->prepare($query = $this->getSql()))
            {
                $result = $stmt->execute($this->mergeParams($params, $query));

                if ($this->maker && $stmt->getResult())
                {
                    $result->getResult()->setMaker($this->maker);
                }
                return $result;
            }
        }

        return null;
    }

    /**
     * @param ?class-string<\Sql\Maker> $maker
     *
     * @return static
     */
    public function withMaker($maker)
    {
        if (null !== $maker && ( ! is_string($maker) || ! is_subclass_of($maker, \Sql\Maker::class)))
        {
            throw new \InvalidArgumentException('$maker must implement ' . \Sql\Maker::class);
        }
        $instance        = clone $this;
        $instance->maker = $maker;
        return $instance;
    }

    /**
     * @return \Sql\QueryHelper
     */
    public function getQueryHelper()
    {
        return $this->queryHelper;
    }

    /**
     * @param \Sql\QueryHelper $queryHelper
     *
     * @return static
     */
    public function setQueryHelper(\Sql\QueryHelper $queryHelper)
    {
        $this->queryHelper = $queryHelper;
        return $this;
    }

    /** @return int */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Reset the query builder to starting condition.
     *
     * @return static
     */
    public function clear()
    {
        $this->sql         = null;
        $this->params      = [];
        $this->extraParams = [];
        $this->allParams   = [];
        $this->type        = self::SELECT;
        $this->fields      = [];
        $this->tables      = [];
        $this->joins       = [];
        $this->where       = null;
        $this->group_by    = [];
        $this->having      = null;
        $this->order_by    = [];
        $this->values      = [];
        $this->offset      = null;
        $this->limit       = null;
        $this->aliases     = [];
        $this->joinRef     = [];
        $this->maker       = null;
        return $this;
    }

    /**
     * @return string
     */
    public function getSql()
    {
        if (null === $this->sql)
        {
            $this->sql = '';

            switch ($this->getType())
            {
                case self::INSERT:
                    $sql = $this->getSqlInsert();
                    break;
                case self::UPDATE:
                    $sql = $this->getSqlUpdate();
                    break;
                case self::DELETE:
                    $sql = $this->getSqlDelete();
                    break;
                case self::SELECT:
                default:
                    $sql = $this->getSqlSelect();
            }

            if ($sql)
            {
                $this->sql = $sql;
            }
        }

        return isset($this->sql) ? $this->sql : '';
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->allParams;
    }

    /**
     * Begins a SELECT statement.
     *
     * @param string ...$fields
     *
     * @return static
     */
    public function select($fields = '*')
    {
        if ( ! is_array($fields))
        {
            $fields = func_get_args();
        }

        if (empty($fields))
        {
            $fields = ['*'];
        }
        $instance         = $this->setType(self::SELECT);
        $instance->fields = $fields;
        return $instance;
    }

    /**
     * Begins an UPDATE statement.
     *
     * @param string  $table
     * @param ?string $alias
     *
     * @return static
     */
    public function update($table, $alias = null)
    {
        return $this->setType(self::UPDATE)->from($table, $alias);
    }

    /**
     * Begins an UPDATE statement.
     *
     * @param string $table
     *
     * @return static
     */
    public function insert($table)
    {
        return $this->setType(self::INSERT)->from($table);
    }

    /**
     * Begins a DELETE statement.
     *
     * @param string  $table
     * @param ?string $alias
     *
     * @return static
     */
    public function delete($table, $alias = null)
    {
        return $this->setType(self::DELETE)->from($table, $alias);
    }

    /**
     * select table for statement.
     *
     * @param string  $table
     * @param ?string $alias
     *
     * @return static
     */
    public function from($table, $alias = null)
    {
        if ( ! empty($alias))
        {
            if (isset($this->aliases[$alias]))
            {
                throw \Sql\SqlException::newInstance(
                    "The alias '%s' is already defined for table '%s'.",
                    $alias,
                    $this->aliases[$alias]
                );
            }
            $this->aliases[$alias] = $table;
            $this->joins[$alias]   = [];
        }

        $this->aliases[$table] = $table;
        $this->joins[$table]   = [];
        $this->tables[]        = [$table, $alias];
        return $this->clearSql();
    }

    /**
     * Join a table.
     *
     * @param string $fromAlias
     * @param string $table
     * @param string $alias
     * @param string $cond
     *
     * @return $this
     */
    public function join($fromAlias, $table, $alias, $cond)
    {
        return $this->innerJoin($fromAlias, $table, $alias, $cond);
    }

    /**
     * Join a table.
     *
     * @param string $fromAlias
     * @param string $table
     * @param string $alias
     * @param string $cond
     *
     * @return $this
     */
    public function innerJoin($fromAlias, $table, $alias, $cond)
    {
        if ( ! isset($this->aliases[$fromAlias]))
        {
            throw \Sql\SqlException::newInstance(
                "table alias '%s' is not defined",
                $fromAlias
            );
        }

        $this->aliases[$alias]     = $this->aliases[$table] = $table;
        $this->joins[$alias]       = $this->joins[$table] = [];
        $this->joins[$fromAlias][] = [
            'type'  => self::INNER_JOIN,
            'table' => $table,
            'alias' => $alias,
            'cond'  => $cond,
        ];

        return $this->clearSql();
    }

    /**
     * Left Join a table.
     *
     * @param string $fromAlias
     * @param string $table
     * @param string $alias
     * @param string $cond
     *
     * @return $this
     */
    public function leftJoin($fromAlias, $table, $alias, $cond)
    {
        if ( ! isset($this->aliases[$fromAlias]))
        {
            throw \Sql\SqlException::newInstance(
                "table alias '%s' is not defined",
                $fromAlias
            );
        }

        $this->aliases[$alias]     = $this->aliases[$table] = $table;
        $this->joins[$alias]       = $this->joins[$table] = [];
        $this->joins[$fromAlias][] = [
            'type'  => self::LEFT_JOIN,
            'table' => $table,
            'alias' => $alias,
            'cond'  => $cond,
        ];

        return $this->clearSql();
    }

    /**
     * RightJoin a table.
     *
     * @param string $fromAlias
     * @param string $table
     * @param string $alias
     * @param string $cond
     *
     * @return $this
     */
    public function rightJoin($fromAlias, $table, $alias, $cond)
    {
        if ( ! isset($this->aliases[$fromAlias]))
        {
            throw \Sql\SqlException::newInstance(
                "table alias '%s' is not defined",
                $fromAlias
            );
        }

        $this->aliases[$alias]     = $this->aliases[$table] = $table;
        $this->joins[$alias]       = $this->joins[$table] = [];
        $this->joins[$fromAlias][] = [
            'type'  => self::RIGHT_JOIN,
            'table' => $table,
            'alias' => $alias,
            'cond'  => $cond,
        ];

        return $this->clearSql();
    }

    /**
     * Begins a WHERE clause, removing the previous clauses
     * to add another clause use andWhere or orWhere.
     *
     * @param array<string,mixed>|string|string[] $cond
     *
     * @return static
     */
    public function where($cond)
    {
        if ( ! is_array($cond))
        {
            $cond = func_get_args();
        }
        list($where, $values) = $this->parseWhereCond($cond);
        $this->params         = [];
        $this->where          = new Expression(Expression::TYPE_AND, $where);

        foreach ($values as $value)
        {
            $this->params[] = $value;
        }

        return $this->clearSql();
    }

    /**
     * Adds a AND clause to WHERE statement.
     *
     * @param array<string,mixed>|string|string[] $cond
     *
     * @return static
     */
    public function andWhere($cond)
    {
        if ( ! is_array($cond))
        {
            $cond = func_get_args();
        }

        $current              = $this->where;
        list($where, $values) = $this->parseWhereCond($cond);

        if ( ! $current || Expression::TYPE_AND !== $current->getType())
        {
            array_unshift($where, $current);
            $this->where = new Expression(Expression::TYPE_AND, $where);
        } else
        {
            $current->addMany($where);
        }

        foreach ($values as $value)
        {
            $this->params[] = $value;
        }
        return $this->clearSql();
    }

    /**
     * Adds a OR clause to WHERE statement.
     *
     * @param array<string,mixed>|string|string[] $cond
     *
     * @return static
     */
    public function orWhere($cond)
    {
        if ( ! is_array($cond))
        {
            $cond = func_get_args();
        }
        $current              = $this->where;
        list($where, $values) = $this->parseWhereCond($cond);

        if ( ! $current || Expression::TYPE_OR !== $current->getType())
        {
            array_unshift($where, $current);
            $this->where = new Expression(Expression::TYPE_OR, $where);
        } else
        {
            $current->addMany($where);
        }

        foreach ($values as $value)
        {
            $this->params[] = $value;
        }
        return $this->clearSql();
    }

    /**
     * @param string ...$fields field names
     *
     * @return static
     */
    public function groupBy($fields)
    {
        if (empty($fields))
        {
            return $this;
        }

        if ( ! is_array($fields))
        {
            $fields = func_get_args();
        }

        foreach ($fields as $field)
        {
            $this->group_by[] = $field;
        }
        return $this->clearSql();
    }

    /**
     * Adds a having condition
     * GROUP BY must be used.
     *
     * @param array<string,mixed>|string|string[] $cond
     *
     * @return static
     */
    public function having($cond)
    {
        if ( ! is_array($cond))
        {
            $cond = func_get_args();
        }
        list($having, $values) = $this->parseWhereCond($cond);
        $this->having          = new Expression(Expression::TYPE_AND, $having);
        $this->extraParams     = [];

        foreach ($values as $value)
        {
            $this->extraParams[] = $value;
        }

        return $this->clearSql();
    }

    public function andHaving($cond)
    {
        if ( ! is_array($cond))
        {
            $cond = func_get_args();
        }

        $current               = $this->where;
        list($having, $values) = $this->parseWhereCond($cond);

        if ( ! $current || Expression::TYPE_AND !== $current->getType())
        {
            array_unshift($having, $current);
            $this->having = new Expression(Expression::TYPE_AND, $having);
        } else
        {
            $current->addMany($having);
        }

        foreach ($values as $value)
        {
            $this->extraParams[] = $value;
        }
        return $this->clearSql();
    }

    public function orHaving($cond)
    {
        if ( ! is_array($cond))
        {
            $cond = func_get_args();
        }

        $current               = $this->where;
        list($having, $values) = $this->parseWhereCond($cond);

        if ( ! $current || Expression::TYPE_OR !== $current->getType())
        {
            array_unshift($having, $current);
            $this->having = new Expression(Expression::TYPE_OR, $having);
        } else
        {
            $current->addMany($having);
        }

        foreach ($values as $value)
        {
            $this->extraParams[] = $value;
        }
        return $this->clearSql();
    }

    public function orderBy($fields, $ascending = true)
    {
        static $keywords = [' DESC', ' ASC'];

        if ( ! is_array($fields))
        {
            $fields = [$fields];
        }

        foreach ($fields as $field)
        {
            $sort             = $keywords[intval($ascending)];

            foreach ($keywords as $keyword)
            {
                if (str_ends_with(strtoupper($field), $keyword))
                {
                    $sort = '';
                    break;
                }
            }
            $this->order_by[] = $this->esc($field) . $sort;
        }
        return $this->clearSql();
    }

    public function values(array $values)
    {
        // reset values to prevent errors when count is different
        $this->values = [];

        foreach ($values as $field => $value)
        {
            if ( ! is_string($field))
            {
                throw \Sql\SqlException::newInstance(
                    '$values must be indexed by field name, %d given',
                    $field
                );
            }
            $this->values[$field] = $value;
        }
        return $this->clearSql();
    }

    public function set(array $values)
    {
        return $this->values($values);
    }

    public function limit($limit, $offset = null)
    {
        if ($limit > 0 && is_int($limit))
        {
            $this->limit = $limit;

            if ($offset > 0 && is_int($offset))
            {
                $this->offset = $offset;
            }
        }
        return $this->clearSql();
    }

    public function count()
    {
        return count($this->getParams());
    }

    /**
     * Prevents previous Query builder to be overwritten
     * when performing query using result from next query builder instance.
     *
     * @param int $type
     *
     * @return static
     */
    private function setType($type)
    {
        $clone       = clone $this;
        $clone->clear();
        $clone->type = $type;
        return $clone;
    }

    /**
     * @param array  $params
     * @param string $query
     *
     * @return array
     */
    private function mergeParams(array $params, $query)
    {
        // no provided params, we use the ones from the query builder
        if (empty($params))
        {
            return $this->getParams();
        }

        $queryCount = substr_count($query, '?');
        $existing   = $this->getParams();

        // all params have been provided
        if ($queryCount === count($params))
        {
            return $params;
        }

        // we have less provided params than in query builder
        // we check $existing params that have ? as value and overwrite them using provided $params
        $provided   = count($params);
        $missing    = count(array_filter(
            $existing,
            function ($val)
            {
                return '?' === $val;
            }
        ));

        // we check if all existing '?' values can be replaced
        if ($missing > $provided)
        {
            throw \Sql\SqlException::newInstance(
                "Invalid provided bindings count for '?' token: %d/%d.",
                [max(0, $provided), $missing]
            );
        }

        // we replace missing values
        // using the token `?`
        $index      = 0;
        $result     = [];
        $paramsUsed = [];

        foreach ($existing as $value)
        {
            if ('?' === $value)
            {
                // here we cannot overflow
                // as we already checked count previously
                $result[]     = $params[$index];
                $paramsUsed[] = $index;
                ++$index;
                continue;
            }
            $result[] = $value;
        }

        // some provided params have not been used
        foreach ($params as $i => $value)
        {
            if ( ! in_array($i, $paramsUsed))
            {
                $result[] = $value;
            }
        }

        if (count($result) !== $queryCount)
        {
            throw \Sql\SqlException::newInstance(
                'Invalid bindings count for query: %d/%d.',
                [count($result), $queryCount]
            );
        }
        return $result;
    }

    /**
     * @param array $cond
     *
     * @return array{array,array}
     */
    private function parseWhereCond($cond)
    {
        $values = [];

        $where  = [];

        foreach ($cond as $key => $val)
        {
            if (is_int($key))
            {
                $where[] = $val;
                continue;
            }

            if ( ! str_contains($key, '?'))
            {
                $key = "{$key} = ?";
            }

            $where[]  = $key;
            $values[] = $val;
        }

        return [$where, $values];
    }

    /**
     * @return static
     */
    private function clearSql()
    {
        $this->sql       = null;
        $this->allParams = array_merge($this->params, $this->extraParams);
        return $this;
    }

    private function esc($value)
    {
        if ( ! str_contains($value, '`') && ! str_contains($value, ' ') && ! str_contains($value, '.'))
        {
            return sprintf('`%s`', $value);
        }
        return $value;
    }

    private function getSqlInsert()
    {
        if (empty($this->tables) || empty($this->values))
        {
            return null;
        }

        $query           = [
            'INSERT INTO',
            $this->esc($this->tables[0][0]),
        ];

        $fields          = [];
        $bindings        = [];
        $values          = [];

        foreach ($this->values as $field => $value)
        {
            $fields[]   = $this->esc($field);

            if (is_null($value))
            {
                $values[] = 'NULL';
                continue;
            }
            $values[]   = '?';
            $bindings[] = $value;
        }

        $this->allParams = $bindings;
        $query[]         = sprintf('(%s)', implode(', ', $fields));
        $query[]         = sprintf('VALUES(%s)', implode(', ', $values));
        return implode(' ', $query);
    }

    private function getSqlJoin($ref)
    {
        $sql = [];

        foreach ($this->joins[$ref] as $join)
        {
            $type                  = $join['type'];
            $alias                 = $join['alias'];
            $table                 = $join['table'];
            $cond                  = $join['cond'];

            if (isset($this->joinRef[$alias]))
            {
                throw \Sql\SqlException::newInstance(
                    "The given alias '%s' is not unique in FROM and JOIN clause table. The currently registered aliases are: %s",
                    $alias,
                    implode(', ', array_keys($this->joinRef))
                );
            }
            $sql[]                 = sprintf('%s %s %s ON %s', $type, $this->esc($table), $alias, $cond);
            $this->joinRef[$alias] = true;
        }

        foreach ($this->joins[$ref] as $join)
        {
            $sql[] = $this->getSqlJoin($join['alias']);
        }
        return implode(' ', $sql);
    }

    private function getSqlUpdate()
    {
        if (empty($this->tables) || empty($this->values))
        {
            return null;
        }

        if (empty($alias = $this->tables[0][1]))
        {
            $alias = '';
        }

        $query           = 'UPDATE ' . rtrim($this->esc($this->tables[0][0]) . " {$alias}");

        $fields          = $bindings = [];

        foreach ($this->values as $field => $value)
        {
            $field      = $this->esc($field);

            if (is_null($value))
            {
                $fields[] = "{$field} = NULL";
                continue;
            }

            $fields[]   = "{$field} = ?";
            $bindings[] = $value;
        }

        // switch bindings
        $this->allParams = array_merge($bindings, $this->params);

        $query .= ' SET ' . implode(', ', $fields);

        if ($this->where && count($this->where) > 0)
        {
            $query .= sprintf(' WHERE %s', $this->where);
        }

        return $query;
    }

    private function getSqlDelete()
    {
        if (empty($this->tables) || empty($this->where) || ! count($this->where))
        {
            return null;
        }
        $this->allParams = $this->params;
        return implode(' ', [
            'DELETE FROM',
            $this->esc($this->tables[0][0]),
            sprintf('WHERE %s', $this->where),
        ]);
    }

    private function getSqlSelect()
    {
        if (empty($this->tables) || empty($this->fields))
        {
            return null;
        }

        $this->joinRef   = [];

        $extra           = false;
        $query           = [
            'SELECT',
            implode(', ', $this->fields),
        ];

        // FROM + JOIN
        $tables          = [];

        foreach ($this->tables as list($table, $alias))
        {
            $ref          = $table;
            $sql          = $this->esc($table) . ' ';

            if ($alias)
            {
                $ref = $alias;
                $sql .= "{$alias} ";
            }
            $tables[$ref] = $sql . $this->getSqlJoin($ref);
        }

        $query[]         = sprintf('FROM %s', implode(', ', $tables));

        // WHERE
        if ($this->where && count($this->where) > 0)
        {
            $query[] = sprintf('WHERE %s', $this->where);
        }

        // GROUP BY
        if ( ! empty($this->group_by))
        {
            $query[] = sprintf('GROUP BY %s', implode(', ', $this->group_by));

            // having
            if ($this->having && count($this->having) > 0)
            {
                $query[] = sprintf('HAVING %s', $this->having);
                $extra   = true;
            }
        }

        // ORDER BY
        if ( ! empty($this->order_by))
        {
            $query[] = sprintf('ORDER BY %s', implode(', ', $this->order_by));
        }

        if ($this->limit)
        {
            $limit   = sprintf('LIMIT %d', $this->limit);

            if ($this->offset)
            {
                $limit = sprintf('LIMIT %d, %d', $this->offset, $this->limit);
            }
            $query[] = $limit;
        }

        if ( ! $extra)
        {
            $this->extraParams = [];
        }

        $this->allParams = array_merge($this->params, $this->extraParams);
        return implode(' ', $query);
    }
}
}
namespace Sql{

abstract class BaseDriver implements Driver
{
    const DRIVER_TYPE           = '';

    /** @var null|\mysqli|object|\Pdo|resource|\SQLite3 */
    protected $link             = null;
    protected $throwsOnError    = false;
    private $transactionCounter = 0;

    public function __construct($throwsOnError = false)
    {
        $this->throwsOnError = $throwsOnError;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return null|\mysqli|object|\Pdo|resource|\SQLite3
     */
    public function link()
    {
        return $this->link;
    }

    public function type()
    {
        return static::DRIVER_TYPE;
    }

    final public function beginTransaction()
    {
        if ( ! $this->link)
        {
            return $this->noLink();
        }

        if ( ! $this->transactionCounter++)
        {
            try
            {
                $this->doBeginTransaction();
            } catch (\Exception $err)
            {
                if ($this->throwsOnError)
                {
                    throw SqlException::cannotStartTransaction($err);
                }
                return false;
            } catch (\Throwable $err)
            {
                if ($this->throwsOnError)
                {
                    throw SqlException::cannotStartTransaction($err);
                }
                return false;
            }
        }

        return $this->transactionCounter >= 0;
    }

    final public function rollBack()
    {
        if ( ! $this->link)
        {
            return $this->noLink();
        }

        try
        {
            if ($this->transactionCounter >= 0)
            {
                try
                {
                    return $this->doRollBack();
                } catch (\Exception $err)
                {
                    if ($this->throwsOnError)
                    {
                        throw SqlException::cannotEndTransaction($err);
                    }
                    return false;
                } catch (\Throwable $err)
                {
                    if ($this->throwsOnError)
                    {
                        throw SqlException::cannotEndTransaction($err);
                    }
                    return false;
                }
            }
            return false;
        } finally
        {
            $this->transactionCounter = 0;
        }
    }

    final public function commit()
    {
        if ( ! $this->link)
        {
            return $this->noLink();
        }

        if ( ! --$this->transactionCounter)
        {
            try
            {
                return $this->doCommit();
            } catch (\Exception $err)
            {
                if ($this->throwsOnError)
                {
                    throw SqlException::cannotEndTransaction($err);
                }
                return false;
            } catch (\Throwable $err)
            {
                if ($this->throwsOnError)
                {
                    throw SqlException::cannotEndTransaction($err);
                }
                return false;
            }
        }
        return $this->transactionCounter >= 0;
    }

    public function close()
    {
        $this->link               = null;
        $this->transactionCounter = 0;
        return true;
    }

    public function exec($query)
    {
        return false !== $this->query($query);
    }

    public function prepare($query)
    {
        if ($this->link)
        {
            try
            {
                $stmt = $query;

                if ($this->canPrepare($query))
                {
                    $stmt = $this->link->prepare($query);
                }

                if ($stmt)
                {
                    return new Statement($this, $stmt, $query);
                }
            } catch (\Exception $err)
            {
                if ($this->throwsOnError)
                {
                    throw SqlException::cannotPrepare($err);
                }
            }
            return false;
        }
        return $this->noLink();
    }

    public function error()
    {
        return [-1, 'database connection error'];
    }

    public function lastInsertId()
    {
        if ($this->throwsOnError)
        {
            throw SqlException::cannotConnect();
        }
        return 0;
    }

    /**
     * @return bool
     */
    public function throwsOnError()
    {
        return $this->throwsOnError;
    }

    /**
     * @param bool $throwsOnError
     *
     * @return static
     */
    public function setThrowsOnError($throwsOnError)
    {
        $this->throwsOnError = false !== $throwsOnError;

        return $this;
    }

    abstract protected function doBeginTransaction();

    abstract protected function doRollBack();

    abstract protected function doCommit();

    protected function noLink($value = false)
    {
        if ( ! $this->throwsOnError)
        {
            return $value;
        }

        throw SqlException::cannotConnect();
    }

    protected function assertResult($value)
    {
        if ( ! $value instanceof Result)
        {
            throw new \InvalidArgumentException(sprintf('$result must be an instance of %s, %s given', Result::class, get_debug_type($value)));
        }
    }

    protected function assertStatement($value)
    {
        if ( ! $value instanceof Statement)
        {
            throw new \InvalidArgumentException(sprintf('$statement must be an instance of %s, %s given', Statement::class, get_debug_type($value)));
        }
    }

    protected function assertQueryIsString($value)
    {
        if ( ! is_string($value))
        {
            throw new \InvalidArgumentException(sprintf('$query must be a string, %s given', get_debug_type($value)));
        }
    }

    /**
     * @param string $query
     *
     * @return bool $value
     */
    protected function canPrepare($query)
    {
        $this->assertQueryIsString($query);
        return 0 < $this->parseNumberOfQueryParameters($query);
    }

    /**
     * @param string $query
     *
     * @return int
     */
    protected function parseNumberOfQueryParameters($query)
    {
        return substr_count($query, '?');
    }

    /**
     * @param array{host: ?string, username: ?string, password: ?string, database: ?string, charset: ?string} $params
     *
     * @return array{?string, ?string, ?string, ?string, ?string}
     */
    protected function parseParams(array $params)
    {
        $host
            = $username
            = $password
            = $database
            = $charset
            = null;

        if ( ! empty($params['host']))
        {
            $host = $params['host'];
        }

        if ( ! empty($params['username']))
        {
            $username = $params['username'];
        }

        if ( ! empty($params['password']))
        {
            $password = $params['password'];
        }

        if ( ! empty($params['database']))
        {
            $database = $params['database'];
        }

        if ( ! empty($params['charset']))
        {
            $charset = $params['charset'];
        }

        return [$host, $username, $password, $database, $charset];
    }
}

abstract class BasePdoDriver extends BaseDriver
{
    public function connect(array $params)
    {
        if ($this->link)
        {
            $this->link->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_BOTH);
            $this->link->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            $this->link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return true;
        }
        return $this->noLink();
    }

    public function error()
    {
        if ($this->link)
        {
            $err = $this->link->errorInfo();

            if (isset($err[1]))
            {
                return array_slice($err, 1);
            }
            return [0, ''];
        }
        return parent::error();
    }

    public function lastInsertId()
    {
        if ($this->link)
        {
            try
            {
                return $this->link->lastInsertId();
            } catch (\PDOException $err)
            {
            }
        }

        return parent::lastInsertId();
    }

    public function close()
    {
        parent::close();
        return true;
    }

    public function quote($string)
    {
        if ( ! is_string($string))
        {
            return '';
        }

        if ( ! $this->link)
        {
            return $this->noLink($string);
        }
        return trim($this->link->quote($string) ?: '', "\\'");
    }

    public function query($query)
    {
        if ($this->link)
        {
            try
            {
                if ($result = $this->link->query($query))
                {
                    return new Result($this, $result);
                }
            } catch (\PDOException $err)
            {
                if ($this->throwsOnError)
                {
                    throw SqlException::cannotExecute($err);
                }
            }

            return false;
        }
        return $this->noLink();
    }

    public function bindParams($statement, array $params)
    {
        if ($this->link)
        {
            $this->assertStatement($statement);
            $stmt = $statement->getStatement();

            if ($cnt = count($params))
            {
                try
                {
                    if ( ! $stmt instanceof \PDOStatement)
                    {
                        throw new \InvalidArgumentException('$statement is not a prepared statement, params cannot be bound.');
                    }

                    if ($cnt !== $this->parseNumberOfQueryParameters($statement->getSql()))
                    {
                        throw new \InvalidArgumentException("Number of variables doesn't match number of parameters in prepared statement");
                    }

                    foreach (array_keys($params) as $i)
                    {
                        $refs = [$i + 1, &$params[$i]];

                        switch (get_debug_type($params[$i]))
                        {
                            case 'null':
                                $refs[] = \PDO::PARAM_NULL;
                                break;
                            case 'bool':
                                $refs[] = \PDO::PARAM_BOOL;
                                break;
                            case 'int':
                                $refs[] = \PDO::PARAM_INT;
                                break;
                            default:
                                $refs[] = \PDO::PARAM_STR;
                        }

                        if ( ! call_user_func_array([$stmt, 'bindParam'], $refs))
                        {
                            return false;
                        }
                    }
                } catch (\PDOException $err)
                {
                    if ($this->throwsOnError)
                    {
                        throw SqlException::cannotBind($err);
                    }
                    return false;
                }
            }

            return $statement;
        }

        return $this->noLink();
    }

    public function execute($statement)
    {
        if ($this->link)
        {
            $this->assertStatement($statement);
            $stmt = $statement->getStatement();

            if (is_string($stmt))
            {
                return $this->query($stmt);
            }

            // close the cursor for some compatible drivers
            // repeated uses of the same statement
            $this->closeCursor($stmt);

            try
            {
                if ( ! $stmt->execute())
                {
                    return false;
                }
                return new Result($this, $stmt);
            } catch (\PDOException $err)
            {
                if ($this->throwsOnError)
                {
                    throw SqlException::cannotExecute($err);
                }
            }
            return false;
        }

        return $this->noLink();
    }

    public function fetch($result, $mode = FETCH_BOTH)
    {
        if ($this->link)
        {
            $this->assertResult($result);

            $resp = $result->getResult();

            if ($resp instanceof \PDOStatement)
            {
                switch ($mode)
                {
                    case FETCH_NUM:
                        $method = \PDO::FETCH_NUM;
                        break;
                    case FETCH_ASSOC:
                        $method = \PDO::FETCH_ASSOC;
                        break;
                    case FETCH_OBJ:
                        $method = \PDO::FETCH_OBJ;
                        break;
                    default:
                        $method = \PDO::FETCH_BOTH;
                }

                try
                {
                    if ($row = $resp->fetch($method))
                    {
                        return $row;
                    }
                } catch (\PDOException $err)
                {
                    if ($this->throwsOnError)
                    {
                        throw SqlException::cannotFetch($err);
                    }
                }
            }

            return null;
        }

        return $this->noLink(null);
    }

    protected function doBeginTransaction()
    {
        return $this->link->beginTransaction();
    }

    protected function doRollBack()
    {
        return $this->link->rollBack();
    }

    protected function doCommit()
    {
        return $this->link->commit();
    }

    /**
     * @param \PDOStatement $stmt
     */
    private function closeCursor($stmt)
    {
        try
        {
            $stmt->closeCursor();
        } catch (\PDOException $err)
        {
        }
    }
}

if (extension_loaded('pdo_mysql'))
{
    class MysqlPdoDriver extends BasePdoDriver implements Driver
    {
        const DRIVER_TYPE = 'mysql';

        /**
         * @param array{host: ?string, username: ?string, password: ?string, database: ?string, charset: ?string} $params
         *
         * @return bool
         */
        public function connect(array $params)
        {
            if ($this->link)
            {
                $this->close();
            }

            list($host, $username, $password, $database, $charset) = $this->parseParams($params);

            if ( ! $charset)
            {
                $charset = 'utf8mb4';
            }

            $port                                                  = null;

            if ($host && str_contains($host, ':'))
            {
                @list($host, $_port) = explode(':', $host);

                if (is_numeric($_port))
                {
                    $port = intval($_port);
                }
            }

            // driver is faster using ip on localhost
            if ('localhost' === $host)
            {
                $host = '127.0.0.1';
            }

            try
            {
                $dsn        = 'mysql:host=' . $host;

                if ($port)
                {
                    $dsn .= ';port=' . $port;
                }

                if ($charset)
                {
                    $dsn .= ';charset=' . $charset;
                }

                if ($database)
                {
                    $dsn .= ';dbname=' . $database;
                }

                $link       = new \PDO($dsn, $username, $password, [
                    \PDO::ATTR_TIMEOUT => 5,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);

                $this->link = $link;
            } catch (\PDOException $err)
            {
                if ($this->throwsOnError)
                {
                    throw SqlException::cannotConnect($err);
                }
            }

            return parent::connect($params);
        }
    }
}

if (extension_loaded('pdo_sqlite'))
{
    class SqlitePdoDriver extends BasePdoDriver implements Driver
    {
        const DRIVER_TYPE = 'sqlite';

        /**
         * @param array{database: ?string} $params
         *
         * @return bool
         */
        public function connect(array $params)
        {
            if ($this->link)
            {
                $this->close();
            }

            try
            {
                $db         = ':memory:';

                if (isset($params['database']))
                {
                    $db = $params['database'];
                } elseif ( ! empty($params['host']))
                {
                    $db = $params['host'];
                }

                $conn       = new \PDO('sqlite:' . $db, null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);

                $this->link = $conn;
                parent::connect($params);
                $conn->exec('PRAGMA busy_timeout=5000');
                $conn->exec('PRAGMA foreign_keys=ON');
                $conn->exec('PRAGMA locking_mode=NORMAL');
                $conn->exec('PRAGMA journal_mode=WAL');
            } catch (\PDOException $err)
            {
                if ($this->throwsOnError)
                {
                    throw SqlException::cannotConnect($err);
                }
                return false;
            }

            return true;
        }
    }
}
}
namespace {

class SqlConnector
{
    const DEFAULT_CONNECTION        = 'default';
    const DEFAULT_TYPE              = 'mysql';

    protected static $drivers       = [
        'mysql'  => [\Sql\MysqlPdoDriver::class, \Sql\MysqliDriver::class],
        'sqlite' => [\Sql\SqlitePDODriver::class, \Sql\Sqlite3Driver::class],
    ];

    protected static $type_aliases  = [
        'mariadb' => 'mysql',
        'server'  => 'mysql',
    ];

    /** @var array<string,\Sql\QueryHelper> */
    protected static $connections   = [];

    /** @var array<string,array<string, array{type: string, host?: string, username?: string, password?: string, database?: string}>> */
    protected static $configuration = [];

    /** @var ?bool */
    protected static $throwOnError  = null;

    /**
     * Some scripts detect if false, so we cannot break compatibility, so we add this param.
     *
     * @var bool
     */
    protected static $queryNullable = false;

    /**
     * @phan-suppress PhanTypeMismatchReturnNullable
     *
     * @return bool
     */
    public static function canThrowOnError()
    {
        if ( ! isset(static::$throwOnError))
        {
            return true === constant_get('DEV_ENV');
        }

        return static::$throwOnError;
    }

    /**
     * Set global parameter (override DEV_ENV if set to true).
     *
     * @param null|bool $throwsOnError if null, uses default behaviour
     */
    public static function setThrowOnError($throwsOnError)
    {
        static::$throwOnError = null === $throwsOnError ? null : (bool) $throwsOnError;
    }

    /**
     * @return bool
     */
    public static function isQueryNullable()
    {
        return static::$queryNullable;
    }

    /**
     * @param bool $queryNullable
     */
    public static function setQueryNullable($queryNullable)
    {
        static::$queryNullable = $queryNullable;

        foreach (static::$connections as $helper)
        {
            $helper->setQueryNullable($queryNullable);
        }
    }

    /**
     * @template T of Sql\Driver
     *
     * @param class-string<T>|T $driver
     * @param bool              $append
     */
    public static function addDriver($driver, $append = true)
    {
        if (is_object($driver))
        {
            $driver = get_class($driver);
        }

        if (is_subclass_of($driver, \Sql\Driver::class))
        {
            $type                   = (new $driver())->type();

            if ( ! isset(static::$drivers[$type]))
            {
                static::$drivers[$type] = [];
            }
            $drivers                = array_filter(static::$drivers[$type], function ($className) use ($driver)
            {
                return $className !== $driver;
            });
            static::$drivers[$type] = $append
                ? array_merge($drivers, [$driver])
                : array_merge([$driver], $drivers);
        }
    }

    /**
     * @param mixed $connectionName
     *
     * @return bool
     */
    public static function hasDatabaseConnection($connectionName = self::DEFAULT_CONNECTION)
    {
        return isset(static::$connections[$connectionName]) || ! empty(static::$configuration[$connectionName]);
    }

    /**
     * @param string $connectionName
     *
     * @return bool
     */
    public static function hasDatabaseConfiguration($connectionName = self::DEFAULT_CONNECTION)
    {
        return isset(static::$configuration[$connectionName]);
    }

    /**
     * @param \Sql\QueryHelper $queryHelper
     * @param string           $connectionName
     */
    public static function setConnection($queryHelper, $connectionName = self::DEFAULT_CONNECTION)
    {
        if ($queryHelper instanceof \Sql\QueryHelper)
        {
            static::$connections[$connectionName] = $queryHelper;
        }
    }

    /**
     * @param string $connectionName
     *
     * @return \Sql\QueryHelper
     */
    public static function getConnection($connectionName = self::DEFAULT_CONNECTION)
    {
        if ( ! is_string($connectionName))
        {
            throw new \InvalidArgumentException('Invalid connection name');
        }

        if ( ! isset(static::$connections[$connectionName]))
        {
            if ( ! static::hasDatabaseConfiguration($connectionName))
            {
                throw new \RuntimeException("Configuration for connection {$connectionName} is not defined.");
            }

            $configurations = self::$configuration[$connectionName];
            $key            = static::connectionCacheKey($connectionName);
            $prev           = static::cacheGet($key);
            $list           = [];

            // reorder configurations
            if (isset($prev, $configurations[$prev]))
            {
                $list = [$prev => $configurations[$prev]];
                unset($configurations[$prev]);
            }

            foreach ($configurations as $id => $data)
            {
                $list[$id] = $data;
            }

            foreach ($list as $id => $params)
            {
                // load Driver
                $type   = strtolower($params['type']);

                if (isset(static::$type_aliases[$type]))
                {
                    $type = static::$type_aliases[$type];
                }
                // load first matching driver
                /** @var ?\Sql\Driver $driver */
                $driver = null;

                if ( ! empty(static::$drivers[$type]))
                {
                    foreach (static::$drivers[$type] as $className)
                    {
                        if (class_exists($className))
                        {
                            $driver = new $className(static::canThrowOnError());
                            break;
                        }
                    }
                }

                if ( ! $driver)
                {
                    throw new \RuntimeException(sprintf('Cannot find database driver for connection type %s.', $type));
                }

                try
                {
                    if ($driver->connect($params))
                    {
                        $conn                                        = new \Sql\QueryHelper($driver);
                        $conn->setQueryNullable(static::isQueryNullable());

                        if ($id !== $prev)
                        {
                            static::cacheSet($key, $id);
                        }

                        return static::$connections[$connectionName] = $conn;
                    }
                } catch (\Exception $_)
                {
                }
            }

            throw new \RuntimeException('Cannot connect to the SQL server.');
        }
        return static::$connections[$connectionName];
    }

    /**
     * Legacy Configuration builder.
     *
     * @param string|string[]      $host
     * @param null|string|string[] $user
     * @param null|string|string[] $password
     * @param null|string|string[] $db
     * @param mixed                $name
     * @param string               $type
     */
    public static function setDatabaseConfiguration($host, $user = null, $password = null, $db = null, $name = self::DEFAULT_CONNECTION, $type = self::DEFAULT_TYPE)
    {
        $hosts                        = $host;

        if ( ! is_array($hosts))
        {
            $hosts = [$host];
        }

        $users                        = $user;

        if ( ! is_array($user))
        {
            $users = [$user];
        }

        $passwords                    = $password;

        if ( ! is_array($password))
        {
            $passwords = [$password];
        }

        $databases                    = $db;

        if ( ! is_array($db))
        {
            $databases = [$db];
        }

        // if using the same host with many identifications
        while (count($hosts) < count($users))
        {
            $hosts[] = end($hosts) ?: null;
        }

        // add params to match hosts count
        while (count($hosts) > count($users))
        {
            $users[] = end($users) ?: null;
        }

        while (count($hosts) > count($passwords))
        {
            $passwords[] = end($passwords) ?: null;
        }

        while (count($hosts) > count($databases))
        {
            $databases[] = end($databases) ?: null;
        }

        $config                       = [];

        for ($i = 0; $i < count($hosts); ++$i)
        {
            $data                                        = [
                'type'     => $type,
                'host'     => $hosts[$i],
                'username' => $users[$i],
                'password' => $passwords[$i],
                'database' => $databases[$i],
            ];

            $config[self::encodeSqlConfiguration($data)] = $data;
        }

        static::$configuration[$name] = $config;
    }

    /**
     * Change default connection to another configured one.
     *
     * @param string  $connectionName  Connection name to set as default
     * @param ?string $previousNewName move current default connection to that name, if empty, that connection will be lost
     */
    public static function changeDefaultConnection($connectionName, $previousNewName = null)
    {
        if ( ! is_string($connectionName) || ! static::hasDatabaseConfiguration($connectionName))
        {
            throw new \InvalidArgumentException('Invalid connection name');
        }

        if ( ! empty($previousNewName) && ! is_string($previousNewName))
        {
            throw new \InvalidArgumentException('Invalid previous name');
        }

        // copy current existing configurations+connection (if switching names)
        $current                                           = static::$configuration[$connectionName];
        $connection                                        = isset(static::$connections[$connectionName]) ? static::$connections[$connectionName] : null;

        // copy previous default connection to new name
        if ($previousNewName)
        {
            static::$configuration[$previousNewName] = isset(static::$configuration[static::DEFAULT_CONNECTION])
                ? static::$configuration[static::DEFAULT_CONNECTION]
                : null;
            static::$connections[$previousNewName]   = isset(static::$connections[static::DEFAULT_CONNECTION])
                ? static::$connections[static::DEFAULT_CONNECTION]
                : null;
        }

        // set new default connection
        static::$configuration[static::DEFAULT_CONNECTION] = $current;
        static::$connections[static::DEFAULT_CONNECTION]   = $connection;
    }

    /**
     * @param string|string[] $url  Use doctrine url alternatives
     * @param                 $name
     *
     * @phan-suppress PhanTypeMismatchArgument
     */
    public static function setDatabaseConfigurationUrl($url, $name = self::DEFAULT_CONNECTION)
    {
        $list                         = $url;

        if ( ! is_array($url))
        {
            $list = [$url];
        }

        $previous_db                  = null;
        $config                       = [];

        foreach ($list as $url)
        {
            $url                                         = preg_replace('#^sqlite:/+#', 'sqlite:/', $url);

            $parsed                                      = parse_url($url);

            if (false === $parsed || ! isset($parsed['scheme']))
            {
                throw new \InvalidArgumentException('Invalid connection url: ' . $url);
            }

            $type                                        = strtolower($parsed['scheme']);
            $db                                          = $previous_db;
            $host                                        = '';

            if ( ! empty($parsed['path']))
            {
                $db = $parsed['path'];

                if ( ! empty($parsed['host']))
                {
                    $db = ltrim($db, '/');
                }
            }

            if ( ! empty($parsed['host']))
            {
                $host = $parsed['host'];

                if (isset($parsed['port']))
                {
                    $host .= ':' . $parsed['port'];
                }
            }

            $user                                        = null;
            $password                                    = null;

            if ( ! empty($parsed['user']))
            {
                $user = $parsed['user'];

                if ( ! empty($parsed['pass']))
                {
                    $password = $parsed['pass'];
                }
            }

            if ( ! isset($previous_db))
            {
                $previous_db = $db;
            }

            $data                                        = [
                'type'     => $type,
                'host'     => $host,
                'username' => $user,
                'password' => $password,
                'database' => $db,
            ];
            $config[self::encodeSqlConfiguration($data)] = $data;
        }
        static::$configuration[$name] = $config;
    }

    /**
     * @param string $connectionName
     *
     * @return bool
     */
    public static function closeConnection($connectionName = self::DEFAULT_CONNECTION)
    {
        if ($conn = static::getConnection($connectionName))
        {
            if ($conn->close())
            {
                unset(static::$connections[$connectionName]);
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $connectionName
     *
     * @return bool
     */
    public static function tryConnect($connectionName = self::DEFAULT_CONNECTION)
    {
        try
        {
            return null !== static::getConnection($connectionName);
        } catch (\Exception $err)
        {
        }

        return false;
    }

    /**
     * @param string $connectionName
     *
     * @return string
     */
    protected static function connectionCacheKey($connectionName)
    {
        return sprintf('%s-%s-%s', self::class, getcwd(), $connectionName);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $key
     * @param mixed|T                $default
     *
     * @return mixed|T
     */
    protected static function cacheGet($key, $default = null)
    {
        $file  = self::cacheFile($key);
        $value = @file_get_contents($file);

        if ( ! $value)
        {
            return $default;
        }
        return unserialize($value);
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    protected static function cacheSet($key, $value)
    {
        $file    = self::cacheFile($key);
        $encoded = serialize($value);
        return (bool) @file_put_contents($file, $encoded);
    }

    /**
     * @param string $key
     */
    protected static function cacheDelete($key)
    {
        @unlink(self::cacheFile($key));
    }

    private static function cacheFile($key)
    {
        return sys_get_temp_dir() . '/' . sha1($key) . '.tmp';
    }

    /**
     * Encode SQL Configuration to string to be able to store it as preferred connection.
     *
     * @param array{type:string,host?:string,username?:string,password?:string,database?:string} $config
     *
     * @return string
     */
    private static function encodeSqlConfiguration(array $config)
    {
        return sha1(json_encode($config));
    }
}

class Sql extends \SqlConnector
{
    /**
     * @param string $db
     * @param string $connectionName
     *
     * @return bool
     */
    public static function useDb($db, $connectionName = self::DEFAULT_CONNECTION)
    {
        if ('mysql' === self::getConnection($connectionName)->type())
        {
            return null !== self::easyQuery("USE `{$db}`");
        }
        return false;
    }

    /**
     * @param string $connectionName
     *
     * @return array{int,string}
     */
    public static function getError($connectionName = self::DEFAULT_CONNECTION)
    {
        return self::getConnection($connectionName)->error();
    }

    /**
     * @param string $value
     * @param string $connectionName
     *
     * @return string
     */
    public static function escapeValue($value, $connectionName = self::DEFAULT_CONNECTION)
    {
        return self::getConnection($connectionName)->quote($value);
    }

    /**
     * @param array  $arr
     * @param string $connectionName
     *
     * @return array
     */
    public static function escapeRecursive(array $arr, $connectionName = self::DEFAULT_CONNECTION)
    {
        $result = [];

        foreach ($arr as $key => $value)
        {
            if (is_array($value))
            {
                $result[$key] = self::escapeRecursive($value, $connectionName);
                continue;
            }

            $result[$key] = self::escapeValue($value, $connectionName);
        }
        return $result;
    }

    /**
     * Escape super globales.
     *
     * @param string|true $connectionName if true first available connection will be used
     */
    public static function escapeRequest($connectionName = self::DEFAULT_CONNECTION)
    {
        static $escaped = false;

        if ( ! $escaped || true === $connectionName)
        {
            if (true === $connectionName)
            {
                $connectionName = self::DEFAULT_CONNECTION;
                $configs        = array_keys(self::$configuration);

                if ( ! empty($configs))
                {
                    $connectionName = $configs[0];
                }
            }

            if ( ! is_string($connectionName))
            {
                return;
            }

            if (self::tryConnect($connectionName))
            {
                $escaped  = true;
                $_GET     = self::escapeRecursive($_GET, $connectionName);
                $_POST    = self::escapeRecursive($_POST, $connectionName);
                $_REQUEST = self::escapeRecursive($_REQUEST, $connectionName);
            }
        }
    }

    /**
     * @param string $query
     * @param string $connectionName
     *
     * @return false|\Sql\Statement
     */
    public static function prepare($query, $connectionName = self::DEFAULT_CONNECTION)
    {
        return self::getConnection($connectionName)->prepare($query);
    }

    /**
     * @param $stmt
     * @param $bindings
     *
     * @return null|\Sql\Statement
     */
    public static function bindParams($stmt, $bindings = [])
    {
        if ($stmt instanceof \Sql\Statement)
        {
            return $stmt->bindParams($bindings);
        }

        return null;
    }

    /**
     * @param \Sql\Statement $stmt
     *
     * @return ?\Sql\Statement
     */
    public static function execute($stmt, array $bindings = [])
    {
        if ($stmt instanceof \Sql\Statement && $stmt->execute($bindings))
        {
            return $stmt;
        }
        return null;
    }

    /**
     * @param string $connectionName
     *
     * @return int|string
     */
    public static function getLastInsertId($connectionName = self::DEFAULT_CONNECTION)
    {
        return self::getConnection($connectionName)->lastInsertId();
    }

    /**
     * @param \Sql\Statement|string $stmt
     * @param array                 $bindings
     * @param bool                  $assoc
     * @param string                $connectionName
     *
     * @return \Traversable
     */
    public static function getResults($stmt, array $bindings = [], $assoc = false, $connectionName = self::DEFAULT_CONNECTION)
    {
        if (is_string($stmt))
        {
            $stmt = self::prepare($stmt, $connectionName);
        }

        if ($stmt instanceof \Sql\Statement && $stmt->execute($bindings))
        {
            return $stmt->fetch($assoc ? Sql\FETCH_ASSOC : Sql\FETCH_BOTH);
        }

        return new \EmptyIterator();
    }

    /**
     * /!\ If using foreach getResults() is faster (less memory consumption).
     *
     * @param \Sql\Statement|string $stmt
     * @param array                 $bindings
     * @param bool                  $assoc
     * @param string                $connectionName
     *
     * @return array
     */
    public static function getResultsArray($stmt, array $bindings = [], $assoc = false, $connectionName = self::DEFAULT_CONNECTION)
    {
        return iterator_to_array(self::getResults($stmt, $bindings, $assoc, $connectionName));
    }

    /**
     * @param \Sql\Statement|string $stmt
     * @param array                 $bindings
     * @param bool                  $assoc
     * @param string                $connectionName
     *
     * @return null|array
     */
    public static function findOne($stmt, array $bindings = [], $assoc = false, $connectionName = self::DEFAULT_CONNECTION)
    {
        if (is_string($stmt))
        {
            if ( ! str_contains($stmt, ' limit '))
            {
                $stmt .= ' LIMIT 1';
            }
            $stmt = self::prepare($stmt, $connectionName);
        }

        if ($stmt instanceof \Sql\Statement && $stmt->execute($bindings))
        {
            return $stmt->fetchOne($assoc ? Sql\FETCH_ASSOC : Sql\FETCH_BOTH);
        }
        return null;
    }

    public static function findColumn($stmt, array $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        if (is_string($stmt))
        {
            if ( ! str_contains($stmt, ' limit '))
            {
                $stmt .= ' LIMIT 1';
            }
            $stmt = self::prepare($stmt, $connectionName);
        }

        if ($stmt instanceof \Sql\Statement && $stmt->execute($bindings))
        {
            return $stmt->fetchCol();
        }
        return null;
    }

    /**
     * @param \Sql\Statement|string $query
     * @param array                 $bindings
     * @param string                $connectionName
     *
     * @return null|\Sql\Statement
     */
    public static function easyQuery($query, array $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        $stmt = $query;

        if (is_string($query))
        {
            $stmt = self::prepare($query, $connectionName);
        }
        return self::execute($stmt, $bindings);
    }

    /**
     * @param string              $table
     * @param array<string,mixed> $values
     * @param string              $connectionName
     *
     * @return int|string
     */
    public static function easyInsert($table, $values = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        if ( ! is_string($table) || ! count($values))
        {
            return 0;
        }

        $query    = $table;

        if (0 !== mb_stripos($table, 'insert into'))
        {
            $query = "INSERT INTO {$table}";
        }

        $query    = rtrim($query);
        $bindings = $keys = [];

        foreach (array_keys($values) as $key)
        {
            if ( ! is_string($key))
            {
                return 0;
            }
            $bindings[] = $values[$key];

            if (false === mb_strpos($key, '`'))
            {
                $key = "`{$key}`";
            }

            $keys[$key] = '?';
        }

        $query    = sprintf(
            '%s (%s) VALUES(%s) ',
            $query,
            implode(', ', array_keys($keys)),
            implode(', ', array_values($keys))
        );

        if (self::easyQuery($query, $bindings, $connectionName))
        {
            return self::getLastInsertId($connectionName);
        }

        return 0;
    }

    /**
     * @param string              $table
     * @param array|string        $cond
     * @param array<string,mixed> $values
     * @param string              $connectionName
     *
     * @return bool
     */
    public static function easyUpdate($table, $cond, $values = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        if ( ! is_string($table) || ! count($values))
        {
            return false;
        }

        $query     = sprintf('UPDATE %s SET', $table);

        $bindings  = [];

        foreach (array_keys($values) as $key)
        {
            if ( ! is_string($key))
            {
                return false;
            }

            if (null !== $values[$key])
            {
                $bindings[] = $values[$key];
                $query .= sprintf(' `%s` = ?,', $key);
                continue;
            }
            $query .= sprintf(' `%s` = NULL,', $key);
        }

        $query     = rtrim($query, ',');

        $whereStmt = '';

        if (is_string($cond))
        {
            $whereStmt = ltrim($cond);
        } elseif (is_array($cond) && count($cond) > 0)
        {
            $prev       = key($cond);

            $conditions = [];

            foreach ($cond as $index => $val)
            {
                if (gettype($prev) !== gettype($index))
                {
                    throw new \InvalidArgumentException("Invalid condition index {$index}");
                }
                $prev       = $index;

                if (0 === $index)
                {
                    // ['id = ? AND num = ?', $id, $num]
                    $conditions[] = $val;
                    continue;
                }

                if ( ! is_int($index))
                {
                    if (false !== mb_strpos($index, ' '))
                    {
                        // ['id = ?' => $id ]
                        $conditions[] = $index;
                    } else
                    {
                        // ['id' => $id ]
                        $conditions[] = sprintf('`%s` LIKE ?', $index);
                    }
                }
                $bindings[] = $val;
            }
            $whereStmt  = implode(' AND ', $conditions);
        }

        if (empty($whereStmt))
        {
            return false;
        }

        if (0 !== mb_stripos($whereStmt, 'where '))
        {
            $whereStmt = "WHERE {$whereStmt}";
        }

        $query .= " {$whereStmt}";

        return null !== self::easyQuery($query, $bindings, $connectionName);
    }

    /**
     * @param string                              $table
     * @param array<string,mixed>|string|string[] $cond
     * @param string                              $connectionName
     *
     * @return null|\Sql\Statement
     */
    public static function buildSelectStatement($table, $cond = '', $connectionName = self::DEFAULT_CONNECTION)
    {
        static $except = ['order by ', 'limit ', 'group by ', 'having '];

        if ( ! is_string($table))
        {
            return null;
        }
        $bindings      = [];
        $query         = $table;

        if (0 !== mb_stripos($table, 'select '))
        {
            $query = "SELECT * FROM {$table}";
        }

        $where         = $cond;

        if ( ! empty($where))
        {
            $whereStmt = '';

            if (is_string($where))
            {
                $whereStmt = "WHERE {$where}";

                foreach ($except as $startsWith)
                {
                    if (0 === mb_stripos($where, $startsWith))
                    {
                        $whereStmt = $where;
                        break;
                    }
                }
            } elseif (is_array($where))
            {
                $conditions = [];
                $suffix     = '';

                foreach ($where as $index => $val)
                {
                    if (is_int($index))
                    {
                        foreach ($except as $startsWith)
                        {
                            if (0 === mb_stripos($val, $startsWith))
                            {
                                $suffix .= " {$val}";
                                continue 2;
                            }
                        }
                        // list of conditions
                        $conditions[] = $val;
                        continue;
                    }
                    // index is str
                    $bindings[] = $val;

                    if (false === mb_stripos($index, ' '))
                    {
                        $conditions[] = sprintf('%s LIKE ?', $index);
                    } else
                    {
                        $conditions[] = $index;
                    }
                }

                if ( ! empty($conditions))
                {
                    $whereStmt = rtrim(sprintf('WHERE %s %s', implode(' AND ', $conditions), $suffix));
                }
            }

            $query .= " {$whereStmt}";
            $query     = rtrim($query);
        }

        return self::bindParams(self::prepare($query, $connectionName), $bindings);
    }

    /**
     * @param string                              $table
     * @param array<string,mixed>|string|string[] $cond
     * @param array                               $bindings
     * @param string                              $connectionName
     *
     * @return int
     */
    public static function easyCount($table, $cond = '', $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        $result = 0;

        if (false === mb_stripos($table, 'select '))
        {
            $table = "SELECT COUNT(*) FROM {$table}";
        }

        if ( ! is_array($cond))
        {
            $cond = [$cond];
        }

        // query faster using limit than not (symfony polyfills are very useful)
        $limit  = array_any($cond, function ($line)
        {
            return str_contains(strtolower($line), 'limit ');
        });

        if ( ! $limit)
        {
            $cond[] = 'LIMIT 1';
        }

        if ($stmt = self::buildSelectStatement($table, $cond, $connectionName))
        {
            $result = intval(self::findColumn($stmt, $bindings));
        }

        return $result;
    }

    /**
     * @param string                              $table
     * @param array<string,mixed>|string|string[] $cond
     * @param array                               $bindings
     * @param string                              $connectionName
     *
     * @return array
     */
    public static function easySelect($table, $cond = '', $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        if ($stmt = self::buildSelectStatement($table, $cond, $connectionName))
        {
            if ($result = self::execute(self::bindParams($stmt, $bindings)))
            {
                return $result->fetchAll(Sql\FETCH_ASSOC);
            }
        }
        return [];
    }

    /**
     * @param string                              $table
     * @param array<string,mixed>|string|string[] $cond
     * @param array                               $bindings
     * @param string                              $connectionName
     *
     * @return ?array
     */
    public static function easySelectOne($table, $cond = '', $bindings = [], $connectionName = self::DEFAULT_CONNECTION)
    {
        if ( ! is_array($cond))
        {
            $cond = [$cond];
        }

        // query faster using limit than not
        $limit = array_any($cond, function ($line)
        {
            return str_contains(strtolower($line), 'limit ');
        });

        if ( ! $limit)
        {
            $cond[] = 'LIMIT 1';
        }

        if ($stmt = self::buildSelectStatement($table, $cond, $connectionName))
        {
            if ($result = self::execute(self::bindParams($stmt, $bindings)))
            {
                return $result->fetchOne(Sql\FETCH_ASSOC);
            }
        }
        return null;
    }

    /**
     * @param string               $table
     * @param null|string|string[] $field
     * @param string               $connectionName
     *
     * @return \TableDescriptionField[]
     */
    public static function describeTable($table, $field = null, $connectionName = self::DEFAULT_CONNECTION)
    {
        $driver = self::getConnection($connectionName);
        $type   = $driver->type();

        $result = [];

        if ('mysql' === $type)
        {
            $where    = '';
            $bindings = [];

            $table    = trim($table, '`');

            if (null !== $field)
            {
                if ( ! is_array($field))
                {
                    $field = [$field];
                }

                $cond = [];

                foreach ($field as $f)
                {
                    if (is_string($f))
                    {
                        $f          = trim($f, '`');
                        $cond[]     = '?';
                        $bindings[] = "{$f}";
                    }
                }

                if (count($bindings))
                {
                    $where = sprintf('WHERE Field IN (%s)', implode(', ', $cond));
                }
            }

            foreach (
                self::getResults(
                    self::easyQuery(
                        "SHOW COLUMNS FROM {$table} {$where}",
                        $bindings,
                        $connectionName
                    )
                ) as $item
            ) {
                $result[$item['Field']] = \TableDescriptionField::make($item);
            }
        }
        return $result;
    }
}

class TableDescriptionField
{
    protected $field        = '';
    protected $type         = '';
    protected $null         = 'NO';
    protected $key          = '';
    protected $default      = '';
    protected $extra        = '';

    /**
     * @var string
     */
    protected $fieldType    = null;

    /**
     * @var int
     */
    protected $fieldLength  = null;

    /**
     * @var array<int,mixed>
     */
    protected $fieldChoices = null;

    /**
     * @param string[]   $data
     * @param null|mixed $instance
     *
     * @return self
     */
    public static function make($data = [], $instance = null)
    {
        if ( ! $instance instanceof self)
        {
            $instance = new self();
        }

        foreach ($data as $field => $value)
        {
            $key = mb_strtolower($field);

            if (property_exists($instance, $key))
            {
                $instance->{$key} = $value;
            }
        }
        return $instance;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->field;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getFieldType()
    {
        $this->parseType();
        return $this->fieldType;
    }

    /**
     * @return int
     */
    public function getFieldLength()
    {
        $this->parseType();
        return $this->fieldLength;
    }

    /**
     * @return array
     */
    public function getFieldChoices()
    {
        $this->parseType();
        return $this->fieldChoices;
    }

    /**
     * @return bool
     */
    public function isNullable()
    {
        return 'YES' === $this->null;
    }

    /**
     * @return string
     */
    public function getFieldKey()
    {
        return $this->key;
    }

    /**
     * @return bool
     */
    public function isPrimaryKey()
    {
        return 'PRI' === $this->key;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue()
    {
        $def = $this->default;

        if ('NULL' === $def)
        {
            return null;
        }
        return $this->decode($def);
    }

    public function getExtra()
    {
        return $this->extra;
    }

    protected function decode($value)
    {
        if (is_string($value))
        {
            if ('null' === mb_strtolower($value))
            {
                return null;
            }

            if (mb_strlen($value))
            {
                $decoded = json_decode($value, true);

                if (null === $decoded)
                {
                    $decoded = $value;
                }

                $value   = $decoded;
            }
        }

        return $value;
    }

    protected function parseType()
    {
        if (null !== $this->fieldType)
        {
            return;
        }
        $this->fieldLength  = 0;
        $this->fieldChoices = [];
        $type               = $this->type;
        $len                = -1;

        if (preg_match('#^(.+)\((.+)\)$#', $this->type, $matches))
        {
            list(, $type, $choices) = $matches;

            $choices                = explode(',', $choices);
            $decoded                = [];

            foreach ($choices as $choice)
            {
                $choice    = trim($choice);
                $choice    = trim($choice, "'");
                $decChoice = $this->decode($choice);

                if (null === $decChoice)
                {
                    $decChoice = $choice;
                }

                if (is_string($decChoice))
                {
                    $l = mb_strlen($decChoice);

                    if ($l > $len)
                    {
                        $len = $l;
                    }
                }
                $decoded[] = $decChoice;
            }

            switch (count($decoded))
            {
                case 1:
                    $this->fieldLength  = $decoded[0];
                    break;

                default:
                    $this->fieldChoices = $decoded;
            }
        }

        $this->fieldType    = trim($type);

        if ($len > -1)
        {
            $this->fieldLength = $len;
        }
    }
}
}
namespace Sql\Event{

if (class_exists(\Observable\Event::class))
{
    abstract class EntityEvent extends \Observable\Event
    {
        /**
         * @var bool
         */
        protected $before = true;

        public function __construct(\Sql\ActiveRecord $type, $detail = null)
        {
            parent::__construct(static::class, $type);

            if (is_bool($detail))
            {
                $this->before = $detail;
            }
        }

        /**
         * @phan-suppress PhanParamSignatureMismatch
         *
         * @param \Sql\ActiveRecord $type   Entity that is processed
         * @param ?bool             $detail Event is before/after action
         *
         * @return static
         */
        public static function newEvent($type, $detail = null)
        {
            return new static($type, $detail);
        }

        /**
         * @return bool
         */
        public function isBefore()
        {
            return $this->before;
        }

        /**
         * @template T of \Sql\ActiveRecord
         *
         * @return ?T
         */
        public function getEntity()
        {
            return $this->detail;
        }
    }
}

if (class_exists(EntityEvent::class))
{
    class CreateEntity extends EntityEvent {}
}

if (class_exists(EntityEvent::class))
{
    class DeleteEntity extends EntityEvent {}
}

if (class_exists(EntityEvent::class))
{
    class UpdateEntity extends EntityEvent {}
}
}
namespace Sql{

class ValidationError extends \RuntimeException
{
    /**
     * @param string $message
     * @param mixed  ...$replacements
     *
     * @return static
     */
    public static function make($message, ...$replacements)
    {
        return new static(str_format($message, $replacements));
    }
}

abstract class BaseModel implements Maker, \JsonSerializable, \ArrayAccess
{
    /**
     * Translate db field into model property.
     *
     * @var array<string,string>
     */
    protected static $mapping = [];

    /**
     * Translate model properties field into output mapping.
     *
     * @var array<string,string>
     */
    protected static $output  = [];

    /**
     * Exportable hidden Json Fields.
     *
     * @var string[]
     */
    protected static $hidden  = [];

    /**
     * @return array
     */
    public function export()
    {
        $data   = get_object_vars($this);

        if (empty(static::$hidden))
        {
            return $data;
        }

        // check hidden on all levels
        $hidden = static::$hidden;
        $parent = $this;

        while ($parent = get_parent_class($parent))
        {
            /** @var class-string<static> $parent */
            $hidden = array_merge($hidden, $parent::$hidden);
        }

        foreach (array_keys($data) as $prop)
        {
            if (in_array($prop, $hidden))
            {
                unset($data[$prop]);
            }
        }

        return $data;
    }

    /**
     * @return array
     *
     * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection
     */
    public function toArray()
    {
        $result = [];

        foreach ($this->export() as $property => $value)
        {
            if (isset(static::$output[$property]))
            {
                $property = static::$output[$property];
            }

            if ($value instanceof \BackedEnum)
            {
                $value = $value->value;
            }

            if ($value instanceof \DateTimeInterface)
            {
                $value = $value->format('Y-m-d H:i:s');
            }
            $result[$property] = $value;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @param array   $data
     * @param ?static $instance
     *
     * @return static
     */
    public static function make(array $data = [], $instance = null)
    {
        return static::doMake($data, $instance);
    }

    final public function offsetExists($offset)
    {
        return property_exists($this, "{$offset}");
    }

    final public function offsetGet($offset)
    {
        if (property_exists($this, "{$offset}"))
        {
            return $this->{$offset};
        }
        return null;
    }

    public function offsetSet($offset, $value)
    {
        // read-only
    }

    public function offsetUnset($offset)
    {
        // read-only
    }

    final protected static function doMake(array $data, $instance = null)
    {
        if (false === $instance instanceof static)
        {
            $instance = new static();
        }

        foreach ($data as $prop => $value)
        {
            if ( ! isset($value))
            {
                continue;
            }

            if (isset(static::$mapping[$prop]))
            {
                $prop = static::$mapping[$prop];
            }

            if (property_exists($instance, $prop))
            {
                $instance->{$prop} = $value;
            }
        }
        return $instance;
    }
}

if (class_exists(BaseModel::class))
{
    class_exists(Event\EntityEvent::class);

    class ActiveRecord extends BaseModel
    {
        /**
         * Define custom table name there.
         *
         * @var ?string
         */
        protected static $table                = null;

        /**
         * Define the primary key name.
         *
         * @var string
         */
        protected static $primaryKey           = 'id';

        /**
         * Define the SqlConnector connection name.
         *
         * @var ?string
         */
        protected static $connectionName       = null;

        /**
         * Define special types (classes or bool, after mapping).
         *
         * @var array<string,string>
         */
        protected static $types                = [];

        /**
         * Define nullable mappings.
         *
         * @var string[]
         */
        protected static $nullable             = [];

        /**
         * @var ?\Observable\EventDispatcher
         */
        private static $dispatcher             = null;

        /**
         * @var array{self,class-string<Event\EntityEvent>,callable}[]
         */
        private static $registered_subscribers = [];

        public function __construct()
        {
            $this->initialize();
        }

        /**
         * @phan-suppress PhanTypeComparisonToArray
         */
        public function __destruct()
        {
            $dispatcher = self::getEventDispatcher();

            if ($dispatcher)
            {
                $subscribers = self::$registered_subscribers;
                $matched     = false;

                foreach (self::$registered_subscribers as $index => list($instance, $type, $callable))
                {
                    if ($this === $instance)
                    {
                        unset($subscribers[$index]);
                        $matched = true;
                        $dispatcher->removeEventListener($type, $callable);
                    }
                }

                if ($matched)
                {
                    self::$registered_subscribers = array_values($subscribers);
                }
            }
        }

        /**
         * @phan-suppress PhanTypeMismatchReturnNullable
         *
         * @return string
         */
        public static function tableName()
        {
            if (null !== static::$table)
            {
                return static::$table;
            }
            // detect using class name
            return self::to_snake_case(array_slice(
                explode('\\', static::class),
                -1
            )[0]);
        }

        /** @return string */
        public static function primaryKey()
        {
            return static::$primaryKey;
        }

        /**
         * @param array   $data
         * @param ?static $instance
         *
         * @return static
         */
        public static function make(array $data = [], $instance = null)
        {
            if (empty(static::$types))
            {
                return parent::make($data, $instance);
            }

            if (false === $instance instanceof static)
            {
                $instance = new static();
            }

            $types   = static::$types;
            $mapping = static::$mapping;

            $mapped  = [];

            // first to do is mapping
            foreach ($data as $property => $value)
            {
                if ( ! isset($value))
                {
                    continue;
                }

                if (isset($mapping[$property]))
                {
                    $property = $mapping[$property];
                }

                if (isset($types[$property]))
                {
                    if ('bool' === $types[$property])
                    {
                        $mapped[$property] = (bool) $value;
                        continue;
                    }

                    if ('json' === $types[$property])
                    {
                        $mapped[$property] = json_decode($value, true);

                        if (JSON_ERROR_NONE !== json_last_error())
                        {
                            throw new \RuntimeException("Cannot unserialize JSON for property {$property}");
                        }
                        continue;
                    }

                    if ( ! class_exists($types[$property]))
                    {
                        throw new \LogicException(
                            'class of type "' . $types[$property] . '" does not exist'
                        );
                    }

                    $class = $types[$property];

                    /* Beta feature */
                    if (is_subclass_of($class, self::class))
                    {
                        $value = $class::findByKey($value);
                    } elseif (is_subclass_of($class, \BackedEnum::class))
                    {
                        $value = $class::from($value);
                    } elseif (is_subclass_of($class, \DateTimeInterface::class))
                    {
                        $value = is_string($value) ? new $class($value) : $value;
                    } else
                    {
                        $value = new $class($value);
                    }
                }

                $mapped[$property] = $value;
            }

            return parent::make($mapped, $instance);
        }

        /**
         * @return QueryHelper
         */
        final public static function connection()
        {
            return \SqlConnector::getConnection(static::$connectionName ?: \SqlConnector::DEFAULT_CONNECTION);
        }

        /**
         * Starts a SELECT statement for the current table.
         *
         * @param string ...$fields
         *
         * @phan-suppress PhanTypeMismatchArgumentProbablyReal
         *
         * @return Builder\QueryBuilder
         */
        final public static function select(...$fields)
        {
            return static::connection()
                ->select($fields ?: ['*'])->from(static::tableName())
                ->withMaker( ! $fields ? static::class : null);
        }

        /**
         * Starts an UPDATE statement for the current table.
         *
         * @return Builder\QueryBuilder
         */
        final public static function update()
        {
            return static::connection()->update(static::tableName());
        }

        /**
         * Insert data into the current table using key-value pairs.
         *
         * @param array $values
         * @param bool  $removePrimaryKey Removes primary key from values
         *
         * @return null|int|string primary key
         */
        final public static function insert(array $values, $removePrimaryKey = true)
        {
            if ($removePrimaryKey)
            {
                unset($values[static::primaryKey()], $values[static::getMappedPrimaryKey()]);
            }

            $result = static::connection()
                ->insert(static::tableName())
                ->values($values)
                ->execute();

            if ($result)
            {
                $id = $result->lastInsertId();

                // PDO lastInsertId returns a string
                if (is_numeric($id) && ! str_starts_with((string) $id, '0'))
                {
                    return (int) $id;
                }

                return $id;
            }
            return null;
        }

        /**
         * Delete entity data from the database.
         *
         * @param static $entity
         *
         * @return bool
         */
        final public static function delete(ActiveRecord $entity)
        {
            try
            {
                $ok = false;
                static::dispatchEvent(self::makeEvent($entity, Event\DeleteEntity::class));

                if ($value = $entity->getPrimaryKeyValue())
                {
                    $ok = (bool) static::connection()
                        ->delete(static::tableName())->where([static::primaryKey() => $value])->execute();
                }
            } catch (ValidationError $err)
            {
                return false;
            }

            if ($ok)
            {
                static::dispatchEvent(self::makeEvent($entity, Event\DeleteEntity::class, false));
            }
            return $ok;
        }

        /**
         * Find one entry using key.
         *
         * @param mixed   $value if contains `%` a LIKE query will be used
         * @param ?string $key   if not defined uses the primary key
         *
         * @return ?static
         *
         * @noinspection PhpIncompatibleReturnTypeInspection
         * @noinspection PhpReturnDocTypeMismatchInspection
         */
        final public static function findByKey($value, $key = null)
        {
            if ($value)
            {
                $result = static::select()->where(
                    sprintf(
                        '%s %s ?',
                        $key ?: static::primaryKey(),
                        str_contains($value, '%') ? 'LIKE' : '='
                    )
                )->limit(1)->execute([(string) $value]);

                if ($result)
                {
                    return $result->make(static::class);
                }
            }

            return null;
        }

        /**
         * Find many using condition.
         *
         * @phan-suppress PhanTypeMismatchReturn
         *
         * @param array<string,mixed>|string|string[]       $cond
         * @param null|array<string,string>|string|string[] $sort "asc"|"desc"
         *
         * @return static[]
         */
        final public static function find($cond, $sort = null)
        {
            $qb     = static::select()->where($cond);

            if ( ! empty($sort))
            {
                foreach (self::defineSortFields($sort) as $field => $asc)
                {
                    $qb->orderBy($field, $asc);
                }
            }

            $result = $qb->execute();

            if ($result)
            {
                return $result->makeMany(static::class);
            }
            return [];
        }

        /**
         * Find many using condition.
         *
         * @param array<string,mixed>|string|string[] $cond
         *
         * @return int
         */
        final public static function findCount($cond)
        {
            $qb     = static::select('COUNT(*)')->where($cond);
            $result = $qb->execute();

            if ($result)
            {
                return (int) $result->fetchCol();
            }
            return 0;
        }

        /**
         * Find one using condition.
         *
         * @param array<string,mixed>|string|string[]       $cond
         * @param null|array<string,string>|string|string[] $sort sort fields
         *
         * @return ?static
         *
         * @noinspection PhpIncompatibleReturnTypeInspection
         * @noinspection PhpReturnDocTypeMismatchInspection
         */
        final public static function findOne($cond, $sort = null)
        {
            $qb     = static::select()->where($cond)->limit(1);

            if ( ! empty($sort))
            {
                foreach (self::defineSortFields($sort) as $field => $asc)
                {
                    $qb->orderBy($field, $asc);
                }
            }

            $result = $qb->execute();

            if ($result)
            {
                return $result->make(static::class);
            }
            return null;
        }

        /**
         * @param class-string<Event\EntityEvent>|class-string<Event\EntityEvent>[] $eventType
         * @param callable                                                          $callable
         * @param ?bool                                                             $before
         *
         * @return bool
         */
        final public function subscribe($eventType, $callable, $before = null)
        {
            if ( ! class_exists(Event\EntityEvent::class))
            {
                return false;
            }
            $ok    = false;
            $types = $eventType;

            if ( ! is_array($types))
            {
                $types = [$types];
            }

            foreach ($types as $eventType)
            {
                if ( ! is_subclass_of($eventType, Event\EntityEvent::class))
                {
                    return false;
                }

                if ($dispatcher = static::getEventDispatcher())
                {
                    $listener                       = function ($event) use ($callable, $before)
                    {
                        /** @var Event\EntityEvent $event */
                        if ($event->getEntity() === $this)
                        {
                            if (is_bool($before) && $before !== $event->isBefore())
                            {
                                return;
                            }

                            $callable($event);
                        }
                    };

                    self::$registered_subscribers[] = [$this, $eventType, $listener];
                    $dispatcher->addEventListener($eventType, $listener);
                    $ok                             = true;
                }
            }

            return $ok;
        }

        /**
         * @return mixed
         */
        final public function getPrimaryKeyValue()
        {
            $key = static::getMappedPrimaryKey();
            return $this->{$key};
        }

        /**
         * Checks if entities are the same.
         *
         * @phan-suppress PhanTypeComparisonFromArray
         *
         * @param static $entity
         *
         * @return bool
         */
        final public function equals(ActiveRecord $entity)
        {
            if (null === $entity->getPrimaryKeyValue())
            {
                return false;
            }

            if (get_class($entity) !== static::class)
            {
                return false;
            }

            if ($entity === $this)
            {
                return true;
            }
            return json_encode($entity) === json_encode($this);
        }

        /**
         * Persists entity into storage.
         *
         * @param mixed $silent
         *
         * @return bool
         */
        final public function save($silent = false)
        {
            $id     = null;

            $update = false;

            try
            {
                if ($this->getPrimaryKeyValue())
                {
                    $update = true;
                    static::dispatchEvent(self::makeEvent($this, Event\UpdateEntity::class));
                } else
                {
                    static::dispatchEvent(self::makeEvent($this, Event\CreateEntity::class));
                }
            } catch (ValidationError $err)
            {
                return false;
            }

            $values = get_object_vars($this);
            $data   = [];

            foreach (array_keys($values) as $property)
            {
                $mapping        = array_search($property, static::$mapping) ?: $property;

                if ($mapping === static::primaryKey())
                {
                    $id = $this->{$property};
                    continue;
                }

                $value          = $values[$property];

                $type           = isset(static::$types[$property]) ? static::$types[$property] : null;

                if (null !== $value && 'json' === $type)
                {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                // Beta feature
                if (is_subclass_of($type, self::class) && $value instanceof ActiveRecord)
                {
                    $value = $value->getPrimaryKeyValue();
                }

                // What to do with that? (no JSON type, we cannot decode when making)
                if (is_array($value))
                {
                    continue;
                }

                // ignore field if null and not defined as nullable
                if (null === $value && ! in_array($property, static::$nullable))
                {
                    continue;
                }

                if ($value instanceof \DateTimeInterface)
                {
                    $value = $value->format('Y-m-d H:i:s');
                } elseif ($value instanceof \BackedEnum)
                {
                    $value = $value->value;
                } elseif (is_bool($value))
                {
                    $value = (int) $value;
                } elseif ($value instanceof static)
                {
                    $value = $value->getPrimaryKeyValue();
                } elseif ( ! is_scalar($value) && ! is_null($value))
                {
                    $value = (string) $value;
                }

                $data[$mapping] = $value;
            }
            $ok     = false;

            try
            {
                // insert
                if ( ! $id)
                {
                    if ($id = static::insert($data))
                    {
                        // update data
                        if ($result = static::select()->limit(1)->where([static::primaryKey() => $id])->execute())
                        {
                            // update entity there
                            $result->make($this);
                            $ok = true;
                        }
                    }
                } else
                {
                    // update (no reload as data are the same)
                    $ok = (bool) static::update()
                        ->set($data)
                        ->where([static::primaryKey() => $id])
                        ->execute();
                }
            } catch (\Throwable $err)
            {
                if ( ! $silent)
                {
                    throw $err;
                }
            }

            $ok && static::dispatchEvent(self::makeEvent($this, $update
                ? Event\UpdateEntity::class
                : Event\CreateEntity::class, false));

            return $ok;
        }

        /**
         * Override this method for your lifecycle hooks.
         */
        protected function initialize() {}

        /**
         * @param ?Event\EntityEvent $event
         */
        final protected static function dispatchEvent($event)
        {
            if ($event instanceof Event\EntityEvent && $dispatcher = static::getEventDispatcher())
            {
                $dispatcher->dispatchEvent($event);
            }
        }

        /**
         * @return ?\Observable\EventDispatcher
         */
        final protected static function getEventDispatcher()
        {
            if ( ! class_exists(\Observable\EventDispatcher::class))
            {
                return null;
            }

            if ( ! self::$dispatcher)
            {
                self::$dispatcher = new \Observable\EventDispatcher();
            }
            return self::$dispatcher;
        }

        final protected static function getMappedPrimaryKey()
        {
            $key = static::primaryKey();

            if (isset(static::$mapping[$key]))
            {
                $key = static::$mapping[$key];
            }
            return $key;
        }

        /**
         * Helper that returns a relational entity.
         *
         * @template T of ActiveRecord
         *
         * @param class-string<T> $entityClass
         * @param int|string      $value       primary key value
         *
         * @return ?T
         */
        final protected function findOneRelation($entityClass, $value)
        {
            if ( ! is_subclass_of($entityClass, self::class))
            {
                throw new \InvalidArgumentException(
                    'Entity class ' . $entityClass . ' must be subclass of ' . self::class
                );
            }

            return $entityClass::findByKey($value);
        }

        /**
         * Helper that returns many relational entities.
         *
         * @template T of ActiveRecord
         *
         * @param class-string<T> $entityClass
         * @param int|string      $value       primary key value
         * @param ?string         $field       field name in entity class table
         *
         * @return T[]
         */
        final protected function findManyRelations($entityClass, $value, $field = null)
        {
            if ( ! is_subclass_of($entityClass, self::class))
            {
                throw new \InvalidArgumentException(
                    'Entity class ' . $entityClass . ' must be subclass of ' . self::class
                );
            }

            if ( ! $value)
            {
                return [];
            }

            if ( ! $field)
            {
                $field = sprintf('%s_id', static::tableName());
            }

            return $entityClass::find([$field => $value]);
        }

        /**
         * @param string $input
         *
         * @return string
         */
        final protected static function toCamelCase($input)
        {
            return preg_replace_callback('#[_-](\w)#', function ($matches)
            {
                return mb_strtoupper($matches[1]);
            }, mb_strtolower((string) $input));
        }

        /**
         * @param string $input
         *
         * @return string
         */
        final protected static function to_snake_case($input)
        {
            return preg_replace_callback('#[A-Z]#', function ($matches)
            {
                return '_' . mb_strtolower($matches[0]);
            }, mb_lcfirst($input));
        }

        /**
         * @param null|array<string,string>|string|string[] $sort
         *
         * @return array<string,bool>
         */
        private static function defineSortFields($sort)
        {
            static $keywords = ['desc' => false, 'asc' => true];
            $result          = [];

            if (empty($sort))
            {
                return $result;
            }

            if (is_string($sort))
            {
                $sort = [$sort];
            }

            foreach ($sort as $index => $expression)
            {
                if ( ! is_string($expression))
                {
                    throw new \InvalidArgumentException('Sort field value must be a string, ' . get_debug_type($expression) . ' given');
                }

                $direction      = 'asc';
                $field          = $expression
                                = trim($expression);

                if (is_string($index))
                {
                    $field = trim($index);
                }

                if ($field)
                {
                    $segments = preg_split('#\h+#', $field);
                    $field    = $segments[0];

                    if (count($segments) > 1)
                    {
                        $direction = strtolower(array_pop($segments));
                    }
                }

                if (isset($keywords[strtolower($expression)]))
                {
                    $direction = strtolower($expression);
                }

                $result[$field] = $keywords[$direction];
            }

            return $result;
        }

        /**
         * @param static                          $self
         * @param class-string<Event\EntityEvent> $type
         * @param ?bool                           $before
         *
         * @return ?Event\EntityEvent
         */
        private static function makeEvent($self, $type, $before = true)
        {
            if ( ! class_exists($type))
            {
                return null;
            }
            return $type::newEvent($self, $before);
        }
    }
}
}