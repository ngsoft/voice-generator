<?php

mb_internal_encoding('UTF-8');
date_default_timezone_set(Env::getItem('APP_TIMEZONE', 'Europe/Paris'));

if ('dev' === Env::getItem('APP_ENV', 'prod'))
{
    @define('DEV_ENV', true);
}

ApplicationLogger::setLogRoot(Globals::getItem('log_path'));
ApplicationLogger::setBackTrace(Env::getItem('LOG_BACKTRACE', false));
ApplicationLogger::setRotate(Env::getItem('LOG_ROTATE', 3));
ApplicationLogger::setLogDays(Env::getItem('LOG_DAYS', false));
ApplicationLogger::getLogger()->setPrefix(sprintf('%s [%s]', generate_uid(), $_SERVER['REMOTE_ADDR']));

require_once __DIR__ . '/../config/index.php';
