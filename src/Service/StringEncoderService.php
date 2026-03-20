<?php

namespace Service;

readonly class StringEncoderService
{
    /**
     * Encrypt string using a pbkdf2 passphrase.
     */
    public static function encrypt(string $raw, string $passphrase): string
    {
        static $keyLength = 40, $iterations = 10000, $cipher = 'aes-256-cbc';

        if ( ! in_array($cipher, openssl_get_cipher_methods()))
        {
            throw new \RuntimeException("Cipher {$cipher} not supported.");
        }
        $salt             = openssl_random_pseudo_bytes(12);
        $generated_key    = openssl_pbkdf2($passphrase, $salt, $keyLength, $iterations, 'sha256');
        $ivlen            = openssl_cipher_iv_length($cipher);
        $iv               = openssl_random_pseudo_bytes($ivlen);
        $enc              = openssl_encrypt($raw, $cipher, $generated_key, iv: $iv);
        $data             = [$cipher, base64_encode($salt), base64_encode($iv), $enc];
        return self::urlsafeB64Encode(json_encode($data));
    }

    /**
     * Decrypt string using a pbkdf2 passphrase.
     */
    public static function decrypt(string $encoded, string $passphrase): string
    {
        static $keyLength                     = 40, $iterations = 10000;

        $json                                 = self::urlsafeB64Decode($encoded);

        if ( ! $json || ! is_array($array = json_decode($json, true)))
        {
            throw new \RuntimeException('Unable to decode string.');
        }

        if (4 !== count($array))
        {
            throw new \RuntimeException('Invalid encoded data.');
        }

        list($cipher, $saltB64, $ivB64, $enc) = $array;
        $generated_key                        = openssl_pbkdf2($passphrase, base64_decode($saltB64), $keyLength, $iterations, 'sha256');

        if ( ! in_array($cipher, openssl_get_cipher_methods()))
        {
            throw new \RuntimeException("Cipher {$cipher} not supported.");
        }
        return openssl_decrypt($enc, $cipher, $generated_key, iv: base64_decode($ivB64));
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     *
     * @return string A decoded string
     */
    public static function urlsafeB64Decode(string $input): string
    {
        $remainder = strlen($input) % 4;

        if ($remainder)
        {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        $input     = strtr($input, '-_', '+/');

        return base64_decode($input);
    }

    /**
     * Encode a string with URL-safe Base64.
     *
     * @param string $input The string you want encoded
     *
     * @return string The base64 encode of what you passed in
     */
    public static function urlsafeB64Encode(string $input): string
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }
}
