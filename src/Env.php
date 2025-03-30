<?php

use Symfony\Component\Dotenv\Dotenv;

class Env extends VariableAccessor
{
    public static function getGroup(string $name, ?string $key = null, bool $normalize = false): mixed
    {
        $values   = $normalized = $subs = [];

        $normName = mb_strtolower($name);

        foreach (array_keys($_ENV) as $envKey)
        {
            $normKey = mb_strtolower($envKey);

            if (str_starts_with($normKey, "{$normName}_"))
            {
                $sub = mb_substr($envKey, mb_strlen($name) + 1);

                if ( ! empty($sub))
                {
                    $values[$sub]       = self::decodeValue($_ENV[$envKey]);
                    $lower              = mb_strtolower($sub);
                    $subs[$lower]       = $sub;
                    $normalized[$lower] = $values[$sub];
                }
            }
        }

        if (isset($key))
        {
            $sub = $subs[mb_strtolower($key)] ?? $key;
            return $values[$sub]              ?? null;
        }

        if ($normalize)
        {
            return $normalized;
        }

        return $values;
    }

    protected static function initialize(): void
    {
        if ( ! isset(self::$storage[__CLASS__]))
        {
            /*
             * Env loader
             */
            (new Dotenv())->loadEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
            self::$storage[__CLASS__] = &$_ENV;
        }
    }
}
