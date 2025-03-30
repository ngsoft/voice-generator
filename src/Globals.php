<?php

use function NGSOFT\Filesystem\normalize_path;

class Globals extends VariableAccessor
{
    public static function resolvePath(string $path): string
    {
        return preg_replace_callback('#%(\w+)%#', function ($matches)
        {
            return rtrim(self::getItem($matches[1], ''), DIRECTORY_SEPARATOR);
        }, $path);
    }

    protected static function initialize(): void
    {
        self::$storage[static::class] ??= [
            'project_root'  => normalize_path(dirname(__DIR__)) . DIRECTORY_SEPARATOR,
            'public_path'   => normalize_path(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public') . DIRECTORY_SEPARATOR,
            'template_path' => normalize_path(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates') . DIRECTORY_SEPARATOR,
            'var_path'      => normalize_path(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var') . DIRECTORY_SEPARATOR,
            'cache_path'    => normalize_path(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache') . DIRECTORY_SEPARATOR,
            'log_path'      => normalize_path(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'logs') . DIRECTORY_SEPARATOR,
            'tmp_path'      => normalize_path(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'tmp') . DIRECTORY_SEPARATOR,
        ];
    }
}
