<?php

use JetBrains\PhpStorm\NoReturn;
use Ramsey\Uuid\Uuid;
use Service\LoggerService;
use Sql\Driver;
use Sql\QueryHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

@define('APP_START', microtime(true));

/**
 * @return string
 */
function generate_v4()
{
    return Uuid::uuid4()->toString();
}

/**
 * Checks if env is dev.
 *
 * @return bool
 */
function is_dev()
{
    return 'prod' !== env_get('APP_ENV');
}

/**
 * Get script execution time.
 *
 * @param null|float $previous
 * @param int        $precision
 *
 * @return float
 */
function execution_time($previous = null, $precision = 6)
{
    if ( ! isset($previous))
    {
        $previous = constant_get('APP_START');
    }

    return round(microtime(true) - $previous, $precision);
}

/**
 * @param null|float $previous
 * @param int        $precision
 *
 * @return string
 */
function formated_execution_time($previous = null, $precision = 6)
{
    $value = execution_time($previous, $precision);

    if ($value < 1.0)
    {
        return sprintf('%u ms', $value * 1000);
    }

    return sprintf('%.03f sec', $value);
}

/**
 * @param null|int|string $date
 *
 * @return DateTimeImmutable|false
 *
 * @noinspection PhpUnusedLocalVariableInspection
 */
function date_get($date = null)
{
    if ( ! isset($date))
    {
        return date_create_immutable();
    }

    if (is_int($date))
    {
        $date = date('Y-m-d H:i:s', $date);
    }

    if (is_string($date))
    {
        if (str_starts_with($date, '0000'))
        {
            return new ZeroDate($date);
        }

        try
        {
            return date_create_immutable($date);
        } catch (Exception $err)
        {
        }
    }
    return false;
}

/**
 * Resolves PATH using aliases.
 */
function resolve_path(string $path, string ...$join): string
{
    $pth = preg_replace_callback('#%([\w.-]+)%#', function ($matches)
    {
        return rtrim(
            str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                env_get($matches[0], $matches[0])
            ),
            '/'
        );
    }, $path);

    foreach ($join as $part)
    {
        $part = trim(str_replace(DIRECTORY_SEPARATOR, '/', $part), '/');
        $pth  = rtrim($pth, '/') . '/' . $part;
    }

    return $pth;
}

/**
 * Replaces backslashes by slashes and removes trailing slashes.
 *
 * @param string $path
 *
 * @return string
 */
function normalize_path($path)
{
    if (empty($path))
    {
        return $path;
    }
    return rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $path), '/');
}

/**
 * Slugify a string.
 */
function slugify(string $input): string
{
    return preg_replace('#-+#', '-', strtolower(
        trim(
            preg_replace('/[^A-Za-z0-9-]+/', '-', $input)
        )
    ));
}

/**
 * @noinspection PhpReturnDocTypeMismatchInspection
 *
 * @phan-suppress PhanParamTooMany
 *
 * @return never
 */
#[NoReturn]
function render_response(ResponseView $response)
{
    if ($response instanceof JsonResponseView)
    {
        render_json($response);
    }

    @ob_end_clean();

    $request = Services::getRequest();
    $action  = $request->attributes->get('action', $request->query->get(
        'action',
        $request->request->get(
            'action',
            $request->attributes->get('path', 'none')
        )
    ));
    $path    = $request->attributes->get('path');

    $response->toResponse()
        ->prepare($request)
        ->send(false);

    ApplicationLogger::setBackTrace(false);
    Services::getLogger()->log('RESPONSE %s%s[%s][code:%d]', [
        $request->getMethod(),
        $path ? "[path={$path}]" : "[action={$action}]",
        formated_execution_time(),
        $response->getStatusCode(),
    ]);

    exit;
}

/**
 * @param JsonResponseView $view
 *
 * @return never
 *
 * @noinspection PhpReturnDocTypeMismatchInspection
 *
 * @phan-suppress PhanParamTooMany
 */
#[NoReturn]
function render_json($view)
{
    $request = Services::getRequest();

    $content = $view->getContent();
    $code    = $view->getStatusCode();
    // close probable output buffer
    @ob_end_clean();
    $view->toResponse()
        ->prepare($request)->send(false);

    $action  = $request->query->get('action', $request->request->get('action', 'none'));
    $path    = $request->attributes->get('path');

    if ( ! env_get('APP_DEBUG'))
    {
        $content = '';
    }

    ApplicationLogger::setBackTrace(false);
    Services::getLogger()->log('RESPONSE %s%s[%s][code:%d]%s', [
        $request->getMethod(),
        $path ? "[path={$path}]" : "[action={$action}]",
        formated_execution_time(),
        $code,
        $content,
    ]);

    exit;
}

/**
 * Memory efficient file loading (for big files).
 *
 * @param string $path
 * @param int    $chunkSize
 *
 * @return array
 */
function file_get_chunks(string $path, int $chunkSize = 4096): array
{
    if ( ! is_file($path) || ! is_readable($path))
    {
        return [];
    }

    $chunks = [];

    if ($handle = @fopen($path, 'rb'))
    {
        try
        {
            while ( ! feof($handle))
            {
                $chunks[] = fread($handle, $chunkSize);
            }
        } finally
        {
            @fclose($handle);
        }
    }
    return $chunks;
}

function migrate_database(Driver|QueryHelper $driver, ?string $filter = null)
{
    if ($driver instanceof QueryHelper)
    {
        $driver = $driver->getDriver();
    }

    $logger     = new LoggerService(ApplicationLogger::getLogger('migrations')
        ->setPrefix(ApplicationLogger::getLogger()->getPrefix() . ' '));
    $logger->setBacktrace(false);

    if ( ! $driver->link())
    {
        $logger->error('cannot execute migrations: Driver %s not connected', [$driver::class]);

        return false;
    }

    $type       = $driver->type();
    $migrations = [];
    $extension  = ".{$type}.sql";

    foreach (iterate_files(resolve_path('%migrations%')) as $path => $info)
    {
        if ($filter && ! str_contains(strtolower($info->getBasename()), strtolower($filter)))
        {
            continue;
        }

        $path = normalize_path($path);

        if (str_ends_with(strtolower($path), $extension))
        {
            // detect indexed migrations
            if (preg_match('#^(\d+)#i', basename($path), $matches))
            {
                $created = (int) $matches[1];
            } else
            {
                $created = $info->getCTime();
            }

            if ( ! isset($migrations[$created]))
            {
                $migrations[$created] = [];
            }
            $migrations[$created][] = $path;

            sort($migrations[$created], SORT_STRING);
        }

        ksort($migrations);
    }

    if (empty($migrations))
    {
        $logger->error('cannot execute migrations: no %s migrations found.', [$type]);
        return false;
    }

    $ok         = false;

    foreach ($migrations as $files)
    {
        foreach ($files as $file)
        {
            $logger->info('executing migration: %s', [basename($file)]);
            $content = file_get_contents($file);

            $queries = array_filter(array_map(
                'trim',
                preg_split('#;[\r\n]*#', $content)
            ));

            $trigger = null;

            foreach ($queries as $query)
            {
                // sqlite fix triggers
                if ('sqlite' === $type)
                {
                    if (str_starts_with(strtoupper($query), 'CREATE TRIGGER'))
                    {
                        $trigger = $query;
                        continue;
                    }

                    if ($trigger)
                    {
                        if (str_starts_with(strtoupper($query), 'END'))
                        {
                            $query   = "{$trigger};\n{$query}";
                            $trigger = null;
                        } else
                        {
                            $trigger .= $query;
                            continue;
                        }
                    }
                }

                try
                {
                    if ( ! $driver->exec($query))
                    {
                        $logger->error('cannot execute migration: %s, error: %s', [$query, $driver->error()[1]]);
                        return false;
                    }
                } catch (Throwable $error)
                {
                    $logger->error('cannot execute migration: %s, error: %s', [$query, $error->getMessage()]);
                    return false;
                }

                $ok = true;
            }

            if ( ! $ok)
            {
                $logger->warning('migration %s has no queries', [basename($file)]);
            }
        }
    }

    return $ok;
}

/**
 * Scan a directory recursively.
 *
 * @param string $root
 *
 * @return iterable<string,SplFileInfo>
 *
 * @noinspection PhpInconsistentReturnPointsInspection
 */
function iterate_files($root)
{
    if ( ! is_dir($root))
    {
        return [];
    }
    yield from new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );
}

/**
 * load session if not active.
 */
function start_session()
{
    if (PHP_SESSION_ACTIVE !== session_status())
    {
        @session_name(env_get('APP_ID'));
        $set = ! isset($_COOKIE[env_get('APP_ID')]);

        if (@session_start())
        {
            if ($set)
            {
                // override session cookie set without samesite param
                @header(sprintf(
                    'Set-Cookie: %s=%s; SameSite=Lax; Path=/; HttpOnly;%s',
                    env_get('APP_ID'),
                    session_id(),
                    'https' === Services::getRequest()->getScheme() ? ' Secure' : ''
                ));
            }
        }
    }
}

function stop_session()
{
    @session_destroy();
    @header(sprintf(
        'Set-Cookie: %s=%s; Max-Age=-1; SameSite=Lax; Path=/; HttpOnly;%s',
        env_get('APP_ID'),
        '',
        'https' === Services::getRequest()->getScheme() ? ' Secure' : ''
    ));
}

/**
 * Read/Write session variables.
 *
 * @template T of object
 *
 * @param class-string<T>|string $name
 * @param mixed                  $value
 *
 * @return mixed|T new session value
 */
function session($name, $value = '__VALUE_UNDEFINED__')
{
    start_session();

    if ('__VALUE_UNDEFINED__' === $value)
    {
        $value = var_get($name, $_SESSION);
    } else
    {
        $value           = value($value);
        $_SESSION[$name] = $value;
    }
    return $value;
}

/**
 * Gettext replacement using Symfony Translation.
 *
 * @param string $message
 *
 * @return string
 */
function __($message)
{
    $locale = null;

    if ($force = env_get('APP_LANG_FORCED'))
    {
        $locale = $force;
    }

    return Services::getTranslator()->translate($message, locale: $locale);
}

/**
 * Checks if api key is present and defined in headers.
 *
 * @param Request $request
 */
function check_api_key(Request $request)
{
    // X-API-KEY
    $key = env_get('API_KEY', '', false);

    if ($key)
    {
        if ($request->headers->get('X-API-KEY') !== $key)
        {
            Services::getLogger()->error('X-API-KEY not valid');
            throw HttpException::fromStatusCode(401, 'Unauthorized');
        }
    }
}

/**
 * @param Throwable          $exception
 * @param ?ApplicationLogger $logger
 */
function log_exception($exception, $logger = null)
{
    if ( ! $logger)
    {
        $logger = Services::getLogger();
    }

    $backtrace = ApplicationLogger::hasBackTrace();
    ApplicationLogger::setBackTrace(false);
    $logger->error(env_get('APP_DEBUG', false)
        ? sprintf(
            "%s:%d %s(%s)\ntrace: %s",
            basename($exception->getFile()),
            $exception->getLine(),
            get_class($exception),
            $exception->getMessage(),
            $exception->getTraceAsString()
        )
        : sprintf(
            '%s:%d %s(%s)',
            basename($exception->getFile()),
            $exception->getLine(),
            get_class($exception),
            $exception->getMessage()
        ));
    ApplicationLogger::setBackTrace($backtrace);
}
