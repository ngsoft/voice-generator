<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Symfony/DotEnv loader.
 *
 * @author Aymeric Anger
 */

use Service\LocaleService;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

call_user_func(function ()
{
    @chdir(dirname(__DIR__));
    $env             = dirname(__DIR__) . '/.env';

    $files           = [$env, "{$env}.local"];
    $app_env_files   = ["{$env}.%s", "{$env}.%s.local"];
    $dot             = new Dotenv();

    // load .env(.local|)

    foreach ($files as $file)
    {
        is_file($file) && $dot->load($file);
    }

    $_ENV            = array_replace([
        // App
        'APP_ENV'              => 'dev',
        'APP_DEBUG'            => 'false',
        'APP_ID'               => 'app',
        'APP_LANG'             => 'en',
        'APP_TITLE'            => 'Your site title',
        'APP_DESCRIPTION'      => 'Your site description',
        'APP_VERSION'          => sprintf('%02d.%02d.0', date('y'), date('m')),
        'DATABASE_URL_DEFAULT' => '0',
        'MIN_ALTERNATIVE'      => 'true',
        'WEBP_ALTERNATIVE'     => 'true',
    ], $_ENV);

    // load .env.(dev|prod)(.local|)
    $app_env         = $_ENV['APP_ENV'];

    foreach ($app_env_files as $file)
    {
        $file = sprintf($file, $app_env);
        is_file($file) && $dot->load($file);
    }

    $_ENV['APP_ENV'] = $app_env;

    foreach (array_keys($_ENV) as $key)
    {
        if ('null' === $_ENV[$key])
        {
            unset($_ENV[$key], $_SERVER[$key]);
        }

        // no float (set semver to %d.%d.%d)
        if ('APP_VERSION' === $key)
        {
            if (preg_match('#^\d+\.\d+$#', $_ENV[$key]))
            {
                $_ENV[$key] .= '.0';
            }
        }
    }

    $root            = normalize_path(dirname(__DIR__));
    $var             = "{$root}/var";
    $_ENV            = array_replace([
        // dirs/files
        '%project_root%' => "{$root}/",
        '%public%'       => "{$root}/public/",
        '%var%'          => "{$var}/",
        '%log%'          => "{$var}/log/",
        '%data%'         => "{$var}/data/",
        '%config%'       => "{$root}/config/",
        '%migrations%'   => "{$root}/migrations/",
    ], $_ENV);

    // initating environment
    env_get('init');

    // debug mode
    @ini_set('display_errors', 0);
    @error_reporting(0);

    if ('true' === $_ENV['APP_DEBUG'])
    {
        @error_reporting(24575);

        if (constant_get('PHP_VERSION_ID') < 80400)
        {
            // E_ALL & ~E_DEPRECATED & ~E_STRICT
            @error_reporting(22527);
        }
        @ini_set('display_errors', 1);
    }

    @define('APP_ID', $_ENV['APP_ID']);

    if ('dev' === $_ENV['APP_ENV'])
    {
        @define('DEV_ENV', true);
    }
});

// init db connections
call_user_func(function ()
{
    $resolve_urls = function ($urls)
    {
        $url_list = $urls;

        if (is_string($urls))
        {
            $url_list = [$urls];
        }
        $result   = [];

        foreach ($url_list as $url)
        {
            $result[] = preg_replace_callback('#%([\w.-]+)%#', function ($matches)
            {
                return env_get($matches[0], $matches[0]);
            }, $url);
        }
        return $result;
    };

    SqlConnector::setThrowOnError(true);
    SqlConnector::setQueryNullable(true);

    // Default connection
    $urls         = env_get('DATABASE_URL', []);

    if ( ! empty($urls))
    {
        SqlConnector::setDatabaseConfigurationUrl($resolve_urls($urls));
    }

    // Alternative connections
    foreach ($_ENV as $property => $value)
    {
        $ok = false;

        if (is_array($value))
        {
            $ok = true;
        }

        if (is_string($value) && preg_match('#^\w+:#', $value))
        {
            $ok = true;
        }

        if ($ok && preg_match('#^DATABASE_URL_([\w-]+)$#', $property, $matches))
        {
            SqlConnector::setDatabaseConfigurationUrl(
                $resolve_urls($value),
                str_replace('_', '-', mb_strtolower($matches[1]))
            );
        }
    }
});

// init request
// Create Request
call_user_func(function ()
{
    $cli     = false;

    // fix html var dumper on cli env
    if (in_array(php_sapi_name(), ['cli', 'phpdbg', 'embed']))
    {
        $_SERVER['VAR_DUMPER_FORMAT'] = 'cli';
        $_SERVER['FORCE_COLOR']       = '1';
        $cli                          = true;
    }

    $_SERVER = array_replace([
        'SERVER_NAME'          => 'localhost',
        'SERVER_PORT'          => 80,
        'HTTP_HOST'            => 'localhost',
        'HTTP_USER_AGENT'      => 'Symfony',
        'HTTP_ACCEPT'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
        'HTTP_ACCEPT_CHARSET'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
        'REMOTE_ADDR'          => '127.0.0.1',
        'SERVER_ADDR'          => '127.0.0.1',
        'SCRIPT_NAME'          => '',
        'SCRIPT_FILENAME'      => '',
        'SERVER_PROTOCOL'      => 'HTTP/1.1',
        'REQUEST_TIME'         => time(),
        'REQUEST_TIME_FLOAT'   => microtime(true),
    ], $_SERVER);

    $request = Request::createFromGlobals();

    if ( ! $cli)
    {
        @session_name(env_get('APP_ID'));
        $request->setSession(new Session());
    }
    Services::setRequest($request);
});
// Global Config
require_secure(resolve_path('%config%/config.php'));

// Translations
call_user_func(function ()
{
    Services::setTranslator(
        LocaleService::loadDir(
            resolve_path('%project_root%/lang/'),
            ! is_dev() ? resolve_path('%var%/lang/') : null
        )
    );
});

// init logger
call_user_func(function ()
{
    ApplicationLogger::setLogRoot(resolve_path('%log%'));
    ApplicationLogger::setRotate(5);
    ApplicationLogger::setLogDays(true);
    Services::setLogger(ApplicationLogger::getLogger());

    $cli     = in_array(php_sapi_name(), ['cli', 'phpdbg', 'embed']);

    $request = Services::getRequest();
    $logger  = Services::getLogger();

    $var     = $cli ? array_slice($_SERVER['argv'], 1) : $_REQUEST;
    $path    = rtrim($request->getPathInfo(), '/');

    if (empty($path))
    {
        $path = '/';
    }

    $request->attributes->set('path', $path);

    $action  = $request->attributes->get('action', $request->query->get(
        'action',
        $request->request->get('action', '')
    ));

    $json    = false;

    if (str_contains($request->headers->get('content-type'), '/json'))
    {
        $json_var = json_encode(
            $json_decoded = json_decode($request->getContent(), true),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ('null' !== $json_var)
        {
            $request->attributes->set('json', $json_decoded);
            $var  = $json_var;
            $json = true;
        }
    }

    $ip      = $request->getClientIp();

    if ($forwarded = $request->headers->get('x-forwarded-for', ''))
    {
        $ips = array_filter(preg_split('#[ ,]+#', $forwarded));
        // last proxy request ip
        $ip  = end($ips);
    }

    $bt      = $logger::hasBackTrace();
    $logger::setBackTrace(false);
    $logger->setPrefix('[' . $ip . '] ' . substr(generate_v4(), 0, 16) . ' ');

    if ( ! $cli)
    {
        $logger->log(
            'REQUEST %s%s%s',
            [$request->getMethod(),
                ! $action ? "[path={$path}]" : "[action={$action}]",
                empty($var) ? '' : ($json ? "JSON({$var})" : json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_SLASHES))]
        );
    } else
    {
        $logger->log(
            'COMMAND [%s]%s START',
            [basename($request->getScriptName()),
                empty($var) ? '' : json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_SLASHES)]
        );
    }

    $logger::setBackTrace($bt);

    if ($cli)
    {
        register_shutdown_function(function () use ($logger, $request, $var)
        {
            $logger::setBackTrace(false);
            $logger->log('COMMAND [%s]%s COMPLETE: [%s][code:%d]', [
                basename($request->getScriptName()),
                empty($var) ? '' : json_encode($var, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_SLASHES),
                formated_execution_time(),
                Config::getItem('command.execution.status', 0),
            ]);
        });
    }
});
