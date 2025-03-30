<?php

use NGSOFT\Container\Exceptions\ContainerError;
use Renderer\JsonResponse;

@define('START_TIME', microtime(true));

require_once __DIR__ . '/functions.php';

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

if ( ! constant_get('DEV_ENV'))
{
    error_reporting(0);
}

if ( ! defined('RUNTIME_VERSION'))
{
    define('RUNTIME_VERSION', '1.0.0');

    $fileName = null;

    foreach (debug_backtrace() as $infos)
    {
        if (__FILE__ !== $infos['file'])
        {
            $fileName = $infos['file'];
            break;
        }
    }

    if (isset($fileName))
    {
        try
        {
            $fn = include $fileName;
            $fn();
        } catch (InvalidArgumentException $err)
        {
            $message = $err->getMessage();

            if (preg_match('#^\w+$#', $err->getMessage()))
            {
                $message = sprintf('%s not defined', $message);
            }
            JsonResponse::newBadRequest()->setMessage($message)->render();
        } catch (ContainerError $err)
        {
            ApplicationLogger::getLogger()->error($err->getMessage());

            if ($err->getPrevious())
            {
                ApplicationLogger::getLogger()->error($err->getPrevious()->getMessage());
            }

            JsonResponse::newInternalError()->render();
        } catch (Throwable $err)
        {
            ApplicationLogger::getLogger()->error($err->getMessage());
            JsonResponse::newInternalError()->render();
        }
    }
}
