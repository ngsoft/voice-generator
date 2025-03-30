<?php

use NGSOFT\Container\ContainerInterface;
use Observable\Event;
use Psr\Cache\CacheItemPoolInterface;
use Sql\Driver;
use Sql\QueryHelper;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use View\TemplateView;

function addEntityEvent(string $method): Closure
{
    return function (Event $event) use ($method)
    {
        $entity = $event->getDetail();

        if ($entity instanceof Entity)
        {
            if (method_exists($entity, $method))
            {
                call_user_func([$entity, $method]);
            }
        }
    };
}

return function (ContainerInterface $container)
{
    $container->setMany([
        MailerInterface::class        => fn (ContainerInterface $container) => $container->make(Mailer::class),
        TransportInterface::class     => function (ContainerInterface $container)
        {
            $transport = new NullTransport();

            if ($dsn = Env::getItem('MAILER_DSN'))
            {
                $transport = $container->make(GmailTransportFactory::class)->create(Dsn::fromString($dsn));
            }
            return $transport;
        },
        CacheItemPoolInterface::class => function ()
        {
            $adapters   = [new ArrayAdapter()];

            if (Env::getItem('REDIS_ENABLED', false))
            {
                if ($server = Env::getItem('REDIS_SERVER'))
                {
                    try
                    {
                        RedisAdapter::createConnection($server, ['timeout' => 5]);
                        $adapters[] = new RedisAdapter(RedisAdapter::createConnection($server));
                    } catch (Throwable)
                    {
                    }
                }
            }

            $adapters[] = new PhpFilesAdapter(Env::getItem('APP_ENV', 'dev'), 0, Globals::getItem('cache_path'));

            return new ChainAdapter($adapters);
        },
        QueryHelper::class            => function (ContainerInterface $container)
        {
            $conn = (new QueryHelper($container->get(Driver::class)))
                ->addEventListener('insert:before', addEntityEvent('onInsert'))
                ->addEventListener('insert:after', addEntityEvent('onAfterInsert'))
                ->addEventListener('update:before', addEntityEvent('onUpdate'))
                ->addEventListener('update:after', addEntityEvent('onAfterUpdate'))
                ->addEventListener('delete', addEntityEvent('onDelete'));

            $ok   = migrateEntities(
                require_secure(__DIR__ . '/entities.php'),
                $conn
            );

            if ( ! $ok)
            {
                throw new RuntimeException('cannot migrate entities');
            }

            return $conn;
        },
        'routes'                      => fn () => require __DIR__ . '/router.php',
        TemplateView::class           => fn () => new TemplateView(),
        ApplicationLogger::class      => ApplicationLogger::getLogger(),
    ]);
};
