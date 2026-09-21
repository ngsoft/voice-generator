<?php

declare(strict_types=1);

use Message\SpeakMessage;
use MessageHandler\SpeakMessageHandler;
use Messenger\PdoStore;
use Messenger\PdoTransport;
use Middleware\AuthorizationMiddleware;
use NGSOFT\Container\Container;
use NGSOFT\Routing\Container\DefaultContainerBuilder;
use NGSOFT\Routing\Interface\UrlGeneratorInterface;
use NGSOFT\Routing\RouteGenerator;
use NGSOFT\Routing\Routing;
use NGSOFT\Vite\Adapter\ViteAdapter;
use NGSOFT\Vite\Adapter\ViteAdapterOptions;
use OpenApi\Annotations\OpenApi;
use Provider\ElevenLabsVoiceProvider;
use Provider\MicrosoftEdgeVoiceProvider;
use Provider\SynthesisProviderStack;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Service\LocaleService;
use Service\LoggerService;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TemplateEngine\Context;
use TemplateEngine\Renderer;

/**
 * Register your services there.
 */
return function (Container $container)
{
    $translator          = Services::getTranslator();
    // console component
    $container->setMany([
        ApplicationLogger::class       => Services::getLogger(),
        Application::class             => new Application(
            env_get('APP_NAME', env_get('APP_ID')),
            env_get('APP_VERSION')
        ),
        ArgvInput::class               => new ArgvInput(),
        ConsoleOutput::class           => new ConsoleOutput(),
        RequestStack::class            => fn (Request $request) => new RequestStack([$request]),
        SessionInterface::class        => fn (Request $request) => $request->hasSession()
            ? $request->getSession()
            : tap($request, fn (Request $request) => $request->setSession(new Session()))->getSession(),
        ViteAdapter::class             => fn (Request $request) => new ViteAdapter(
            resolve_path('%project_root%'),
            resolve_path('%public%'),
            new ViteAdapterOptions(
                buildDirectory: is_dev() ? 'build' : 'assets/app',
                basePath: $request->getBasePath(),
                fixScriptsImports: true,
                fixStylesImports: true,
                hotFile: resolve_path('%public%/build/hot'),
            )
        ),
        Request::class                 => Services::getRequest(),
        LocaleService::class           => $translator,
        Translator::class              => $translator->getTranslator(),
        JsonResponseView::class        => function ()
        {
            return Services::getResponse();
        },
        Context::class                 => fn () => new Context(),
        Renderer::class                => fn (Context $context, Request $request) => (new Renderer(resolve_path('%project_root%/view'), $context))->setAttributes([
            'request'       => $request,
            'base_path'     => rtrim($request->getBasePath(), '/'),
            'head_block'    => '',
            'vite_block'    => '',
            'meta_block'    => '',
            'preload_block' => '',
            'scripts_block' => '',
            'styles_block'  => '',
        ]),
        Routing::class                 => fn (Container $container, LoggerService $logger) => tap(new Routing(), fn (Routing $routing) => $routing
            ->addDefinitions([LoggerInterface::class => fn () => $logger])
            ->setContainerFactory(new DefaultContainerBuilder($container))),
        AuthorizationMiddleware::class => fn () => new AuthorizationMiddleware(
            env_get('API_KEY', '', false),
            env_get('API_KEY', '', false)
        ),
        OpenApi::class                 => fn () => Services::getOpenApi(),
        CacheItemPoolInterface::class  => fn () => new FilesystemTagAwareAdapter(
            env_get('APP_ID', '', false),
            directory: resolve_path(
                '%project_root%/var/cache',
                is_dev() ? 'dev' : 'prod'
            )
        ),
        SynthesisProviderStack::class  => function (Container $container)
        {
            $stack = [$container->get(MicrosoftEdgeVoiceProvider::class)];

            if ($eleven = env_get('ELEVEN_API_KEY', '', false))
            {
                $stack[] = $container->make(ElevenLabsVoiceProvider::class, ['api_key' => $eleven]);
            }
            return new SynthesisProviderStack($stack);
        },
    ]);

    // messenger (async speech synthesis)
    $messengerSerializer = new PhpSerializer();
    $messengerStore      = new PdoStore(
        env_get('MESSENGER_DB_CONNECTION', SqlConnector::DEFAULT_CONNECTION, false),
        'messenger_messages',
        (int) env_get('MESSENGER_REDELIVER_TIMEOUT', 60, false)
    );
    $messengerTransport  = new PdoTransport($messengerSerializer, $messengerStore, 'async');

    $container->setMany([
        PhpSerializer::class       => $messengerSerializer,
        PdoStore::class            => $messengerStore,
        PdoTransport::class        => $messengerTransport,
        MessageBusInterface::class => function (Container $container) use ($messengerTransport)
        {
            // Container exposing the single "async" sender to the SendersLocator.
            $senders         = new class($messengerTransport) implements PsrContainerInterface
            {
                public function __construct(private readonly PdoTransport $transport) {}

                public function get(string $id): mixed
                {
                    return $this->transport;
                }

                public function has(string $id): bool
                {
                    return 'async' === $id;
                }
            };

            // Empty static map → no sender unless a TransportNamesStamp is present (sync by default).
            $sendersLocator  = new SendersLocator([], $senders);

            $handlersLocator = new HandlersLocator([
                SpeakMessage::class => [
                    static fn (SpeakMessage $message) => $container->get(SpeakMessageHandler::class)($message),
                ],
            ]);

            return new MessageBus([
                new SendMessageMiddleware($sendersLocator),
                new HandleMessageMiddleware($handlersLocator),
            ]);
        },
    ]);

    $container->alias(TranslatorInterface::class, Translator::class);
    $container->alias(InputInterface::class, ArgvInput::class);
    $container->alias(OutputInterface::class, ConsoleOutput::class);
    $container->alias(LoggerInterface::class, LoggerService::class);
    $container->alias(UrlGeneratorInterface::class, RouteGenerator::class);
    // cache
    $container->alias(TagAwareCacheInterface::class, CacheItemPoolInterface::class);
    $container->alias(CacheInterface::class, Psr16Cache::class);
};
