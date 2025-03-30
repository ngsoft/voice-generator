<?php

use NGSOFT\Facades\Container;
use Sql\Driver;
use Sql\MysqlPdoDriver;

call_user_func(function ()
{
    if ($dsn = Env::getItem('DATABASE_URL'))
    {
        $params = parse_dsn($dsn);

        $type   = $params->protocol;

        if ('mysql' === $type)
        {
            $host     = $params->hostname;
            $db       = substr($params->pathname ?? '', 1);
            $user     = $params->username;
            $password = $params->password;
            $port     = $params->port;
            $charset  = $params->search['charset'] ?? null;

            if ($port)
            {
                $host .= ":{$port}";
            }

            $params   = ['host' => $host, 'username' => $user, 'password' => $password, 'database' => $db, 'charset' => $charset];

            Container::set(Driver::class, function () use ($params)
            {
                $connector = new MysqlPdoDriver(constant_get('DEV_ENV', false));

                try
                {
                    $connector->connect($params);
                } catch (Throwable $err)
                {
                    ApplicationLogger::getLogger()->error($err->getMessage());
                }
                return $connector;
            });
        }
    }
});
