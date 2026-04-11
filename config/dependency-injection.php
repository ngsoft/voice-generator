<?php

declare(strict_types=1);

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
    $translator = Services::getTranslator();
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

    $container->alias(TranslatorInterface::class, Translator::class);
    $container->alias(InputInterface::class, ArgvInput::class);
    $container->alias(OutputInterface::class, ConsoleOutput::class);
    $container->alias(LoggerInterface::class, LoggerService::class);
    $container->alias(UrlGeneratorInterface::class, RouteGenerator::class);
    // cache
    $container->alias(TagAwareCacheInterface::class, CacheItemPoolInterface::class);
    $container->alias(CacheInterface::class, Psr16Cache::class);
};
