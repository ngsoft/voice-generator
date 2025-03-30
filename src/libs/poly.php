<?php
/** @noinspection ALL */
namespace {

/**
 * Here are some functions from symfony/polyfill-php8[3-4]
 */

if (PHP_VERSION_ID < 80300) {
    if (!function_exists('json_validate')) {
        /**
         * @param string $json
         * @param int $depth
         * @param int $flags
         * @return bool
         */
        function json_validate($json, $depth = 512, $flags = 0)
        {
            if (0 !== $flags && defined('JSON_INVALID_UTF8_IGNORE') && JSON_INVALID_UTF8_IGNORE !== $flags) {
                throw new ValueError('json_validate(): Argument #3 ($flags) must be a valid flag (allowed flags: JSON_INVALID_UTF8_IGNORE)');
            }

            if ($depth <= 0) {
                throw new ValueError('json_validate(): Argument #2 ($depth) must be greater than 0');
            }

            if ($depth > 0x7FFFFFFF) {
                throw new ValueError(sprintf('json_validate(): Argument #2 ($depth) must be less than %d', 0x7FFFFFFF));
            }

            json_decode($json, null, $depth, $flags);

            return JSON_ERROR_NONE === json_last_error();
        }
    }

    if (!function_exists('mb_str_pad')) {

        /**
         * @param string $string
         * @param int $length
         * @param string $pad_string
         * @param int $pad_type
         * @param string|null $encoding
         * @return string
         */
        function mb_str_pad($string, $length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = null)
        {
            if (!in_array($pad_type, [STR_PAD_RIGHT, STR_PAD_LEFT, STR_PAD_BOTH], true)) {
                throw new ValueError('mb_str_pad(): Argument #4 ($pad_type) must be STR_PAD_LEFT, STR_PAD_RIGHT, or STR_PAD_BOTH');
            }

            if (null === $encoding) {
                $encoding = mb_internal_encoding();
            }

            try {
                $validEncoding = @mb_check_encoding('', $encoding);
            } catch (ValueError $e) {
                throw new ValueError(sprintf('mb_str_pad(): Argument #5 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            // BC for PHP 7.3 and lower
            if (!$validEncoding) {
                throw new ValueError(sprintf('mb_str_pad(): Argument #5 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            if (mb_strlen($pad_string, $encoding) <= 0) {
                throw new ValueError('mb_str_pad(): Argument #3 ($pad_string) must be a non-empty string');
            }

            $paddingRequired = $length - mb_strlen($string, $encoding);

            if ($paddingRequired < 1) {
                return $string;
            }

            switch ($pad_type) {
                case STR_PAD_LEFT:
                    return mb_substr(str_repeat($pad_string, $paddingRequired), 0, $paddingRequired, $encoding) . $string;
                case STR_PAD_RIGHT:
                    return $string . mb_substr(str_repeat($pad_string, $paddingRequired), 0, $paddingRequired, $encoding);
                default:
                    $leftPaddingLength = floor($paddingRequired / 2);
                    $rightPaddingLength = $paddingRequired - $leftPaddingLength;

                    return mb_substr(str_repeat($pad_string, $leftPaddingLength), 0, $leftPaddingLength, $encoding) . $string . mb_substr(str_repeat($pad_string, $rightPaddingLength), 0, $rightPaddingLength, $encoding);
            }
        }
    }
    if (!function_exists('str_increment')) {
        /**
         * @param string $string
         * @return string
         */
        function str_increment($string)
        {
            if ('' === $string) {
                throw new ValueError('str_increment(): Argument #1 ($string) cannot be empty');
            }

            if (!preg_match('/^[a-zA-Z0-9]+$/', $string)) {
                throw new ValueError('str_increment(): Argument #1 ($string) must be composed only of alphanumeric ASCII characters');
            }

            if (is_numeric($string)) {
                $offset = stripos($string, 'e');
                if (false !== $offset) {
                    $char = $string[$offset];
                    ++$char;
                    $string[$offset] = $char;
                    ++$string;

                    switch ($string[$offset]) {
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
    if (!function_exists('str_decrement')) {
        /**
         * @param string $string
         * @return string
         */
        function str_decrement($string)
        {
            if ('' === $string) {
                throw new ValueError('str_decrement(): Argument #1 ($string) cannot be empty');
            }

            if (!preg_match('/^[a-zA-Z0-9]+$/', $string)) {
                throw new ValueError('str_decrement(): Argument #1 ($string) must be composed only of alphanumeric ASCII characters');
            }

            if (preg_match('/\A(?:0[aA0]?|[aA])\z/', $string)) {
                throw new ValueError(sprintf('str_decrement(): Argument #1 ($string) "%s" is out of decrement range', $string));
            }

            if (!in_array(substr($string, -1), ['A', 'a', '0'], true)) {
                return implode('', array_slice(str_split($string), 0, -1)) . chr(ord(substr($string, -1)) - 1);
            }

            $carry = '';
            $decremented = '';

            for ($i = strlen($string) - 1; $i >= 0; --$i) {
                $char = $string[$i];

                switch ($char) {
                    case 'A':
                        if ('' !== $carry) {
                            $decremented = $carry . $decremented;
                            $carry = '';
                        }
                        $carry = 'Z';

                        break;
                    case 'a':
                        if ('' !== $carry) {
                            $decremented = $carry . $decremented;
                            $carry = '';
                        }
                        $carry = 'z';

                        break;
                    case '0':
                        if ('' !== $carry) {
                            $decremented = $carry . $decremented;
                            $carry = '';
                        }
                        $carry = '9';

                        break;
                    case '1':
                        if ('' !== $carry) {
                            $decremented = $carry . $decremented;
                            $carry = '';
                        }

                        break;
                    default:
                        if ('' !== $carry) {
                            $decremented = $carry . $decremented;
                            $carry = '';
                        }

                        if (!in_array($char, ['A', 'a', '0'], true)) {
                            $decremented = chr(ord($char) - 1) . $decremented;
                        }
                }
            }

            return $decremented;
        }
    }
}

if (PHP_VERSION_ID < 80400) {
    if (!function_exists('array_find')) {
        /**
         * @param array $array
         * @param callable $callback
         * @return mixed
         */
        function array_find(array $array, callable $callback)
        {
            foreach ($array as $key => $value) {
                if ($callback($value, $key)) {
                    return $value;
                }
            }

            return null;
        }
    }

    if (!function_exists('array_find_key')) {
        /**
         * @param array $array
         * @param callable $callback
         * @return int|string|null
         */
        function array_find_key(array $array, callable $callback)
        {
            foreach ($array as $key => $value) {
                if ($callback($value, $key)) {
                    return $key;
                }
            }

            return null;
        }
    }

    if (!function_exists('array_any')) {
        /**
         * @param array $array
         * @param callable $callback
         * @return bool
         */
        function array_any(array $array, callable $callback)
        {
            if (count($array)) {
                foreach ($array as $key => $value) {
                    if ($callback($value, $key)) {
                        return true;
                    }
                }
            }


            return false;
        }
    }

    if (!function_exists('array_all')) {
        /**
         * @param array $array
         * @param callable $callback
         * @return bool
         */
        function array_all(array $array, callable $callback)
        {
            foreach ($array as $key => $value) {
                if (!$callback($value, $key)) {
                    return false;
                }
            }

            return true;
        }
    }
    if (!function_exists('mb_ucfirst')) {
        /**
         * @param string $string
         * @param string|null $encoding
         * @return string
         */
        function mb_ucfirst($string, $encoding = null)
        {
            if (null === $encoding) {
                $encoding = mb_internal_encoding();
            }

            try {
                $validEncoding = @mb_check_encoding('', $encoding);
            } catch (ValueError $e) {
                throw new ValueError(sprintf('mb_ucfirst(): Argument #2 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            // BC for PHP 7.3 and lower
            if (!$validEncoding) {
                throw new ValueError(sprintf('mb_ucfirst(): Argument #2 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            $firstChar = mb_substr($string, 0, 1, $encoding);
            $firstChar = mb_convert_case($firstChar, MB_CASE_TITLE, $encoding);

            return $firstChar . mb_substr($string, 1, null, $encoding);
        }
    }

    if (!function_exists('mb_lcfirst')) {
        /**
         * @param string $string
         * @param string|null $encoding
         * @return string
         */
        function mb_lcfirst($string, $encoding = null)
        {
            if (null === $encoding) {
                $encoding = mb_internal_encoding();
            }

            try {
                $validEncoding = @mb_check_encoding('', $encoding);
            } catch (ValueError $e) {
                throw new ValueError(sprintf('mb_lcfirst(): Argument #2 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            // BC for PHP 7.3 and lower
            if (!$validEncoding) {
                throw new ValueError(sprintf('mb_lcfirst(): Argument #2 ($encoding) must be a valid encoding, "%s" given', $encoding));
            }

            $firstChar = mb_substr($string, 0, 1, $encoding);
            $firstChar = mb_convert_case($firstChar, MB_CASE_LOWER, $encoding);

            return $firstChar . mb_substr($string, 1, null, $encoding);
        }
    }

    /**
     * @param string $regex
     * @param string $string
     * @param string|null $characters
     * @param string|null $encoding
     * @param string $function
     * @return string
     */
    $mb_internal_trim = function ($regex, $string, $characters, $encoding, $function) {
        if (null === $encoding) {
            $encoding = mb_internal_encoding();
        }

        try {
            $validEncoding = @mb_check_encoding('', $encoding);
        } catch (ValueError $e) {
            throw new ValueError(sprintf('%s(): Argument #3 ($encoding) must be a valid encoding, "%s" given', $function, $encoding));
        }

        // BC for PHP 7.3 and lower
        if (!$validEncoding) {
            throw new ValueError(sprintf('%s(): Argument #3 ($encoding) must be a valid encoding, "%s" given', $function, $encoding));
        }

        if ('' === $characters) {
            return null === $encoding ? $string : mb_convert_encoding($string, $encoding);
        }

        if ('UTF-8' === $encoding || in_array(strtolower($encoding), ['utf-8', 'utf8'], true)) {
            $encoding = 'UTF-8';
        }

        $string = mb_convert_encoding($string, 'UTF-8', $encoding);

        if (null !== $characters) {
            $characters = mb_convert_encoding($characters, 'UTF-8', $encoding);
        }

        if (null === $characters) {
            $characters = "\\0 \f\n\r\t\v\u{00A0}\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200A}\u{2028}\u{2029}\u{202F}\u{205F}\u{3000}\u{0085}\u{180E}";
        } else {
            $characters = preg_quote($characters);
        }

        $string = preg_replace(sprintf($regex, $characters), '', $string);

        if ('UTF-8' === $encoding) {
            return $string;
        }

        return mb_convert_encoding($string, $encoding, 'UTF-8');
    };

    if (!function_exists('mb_trim')) {
        /**
         * @param string $string
         * @param string|null $characters
         * @param string|null $encoding
         * @return string
         */
        function mb_trim($string, $characters = null, $encoding = null)
        {
            global $mb_internal_trim;
            return $mb_internal_trim('{^[%s]+|[%1$s]+$}Du', $string, $characters, $encoding, __FUNCTION__);
        }
    }

    if (!function_exists('mb_ltrim')) {
        /**
         * @param string $string
         * @param string|null $characters
         * @param string|null $encoding
         * @return string
         */
        function mb_ltrim($string, $characters = null, $encoding = null)
        {
            global $mb_internal_trim;
            return $mb_internal_trim('{^[%s]+}Du', $string, $characters, $encoding, __FUNCTION__);
        }
    }

    if (!function_exists('mb_rtrim')) {
        /**
         * @param string $string
         * @param string|null $characters
         * @param string|null $encoding
         * @return string
         */
        function mb_rtrim($string, $characters = null, $encoding = null)
        {
            global $mb_internal_trim;
            return $mb_internal_trim('{[%s]+$}Du', $string, $characters, $encoding, __FUNCTION__);
        }
    }
}

}