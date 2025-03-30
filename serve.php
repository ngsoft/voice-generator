<?php
/**
 * Adds GZip encoding to php serve response.
 */

use League\MimeTypeDetection\FinfoMimeTypeDetector;

@define('DEV_ENV', true);

$_ENV['APP_BASEPATH'] = '';

$root                 = __DIR__ . '/public';
$logReq               = $req = explode('?', $_SERVER['REQUEST_URI'] ?? '')[0];
$ext                  = '';
$gz ??= str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') ? '.gz' : '';
$compress             = ! empty($gz);

if (preg_match('#([.].+)$#', $req, $matches))
{
    list(, $ext) = $matches;
}

if ($ext)
{
    if (is_file($root . $req))
    {
        // load script
        if ('.php' === $ext)
        {
            require_once $root . $req;

            exit;
        }

        require_once __DIR__ . '/src/functions.php';
        $detector = new FinfoMimeTypeDetector();
        $mime     = $detector->detectMimeTypeFromPath($root . $req);

        header('Content-Type: ' . $mime);
        header('Vary: Accept-Encoding');
        // cache 1 week
        header('Cache-Control: max-age=604800, must-revalidate');

        if ($compress && is_file($root . $req . $gz))
        {
            $req      = $req . $gz;
            $compress = false;
            header('Content-Encoding: gzip');
        }
        $contents = file_get_contents($root . $req);

        if ($compress)
        {
            header('Content-Encoding: gzip');
            $contents = gzencode($contents, 9);
        }
        header('Content-Length: ' . strlen($contents));

        ApplicationLogger::getLogger()->log(
            "REQUEST: GET %s ext='%s', gz=%d, mime='%s'",
            [
                $logReq,  $ext,
                ! empty($gz),  $mime,
            ]
        );
        echo $contents;

        exit;
    }
}

if ($compress)
{
    // Captures output and then compress it, renders it and exit
    ob_start();

    register_shutdown_function(function ()
    {
        header_remove('Content-Length');
        header_remove('X-Powered-By');

        $contents = ob_get_clean();
        $contents = gzencode($contents, 9);
        header('Content-Length: ' . strlen($contents));
        header('Vary: Accept-Encoding');
        header('Content-Encoding: gzip');
        echo $contents;

        exit;
    });
}

/**
 * Loads Application router.
 */
include $root . '/index.php';
