<?php

declare(strict_types=1);

use NGSOFT\Container\Container;
use NGSOFT\Routing\Container\DefaultContainerBuilder;
use NGSOFT\Routing\Interface\UrlGeneratorInterface;
use NGSOFT\Routing\RouteGenerator;
use NGSOFT\Routing\Routing;
use NGSOFT\Vite\Adapter\ViteAdapter;
use OpenApi\Annotations\OpenApi;
use Psr\Log\LoggerInterface;
use Service\LocaleService;
use Service\LoggerService;
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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Register your services there.
 */
return function (Container $container)
{
    $translator = Services::getTranslator();
    // console component
    $container->setMany([
        ApplicationLogger::class => Services::getLogger(),
        Application::class       => new Application(
            env_get('APP_NAME', env_get('APP_ID')),
            env_get('APP_VERSION')
        ),
        ArgvInput::class         => new ArgvInput(),
        ConsoleOutput::class     => new ConsoleOutput(),
        RequestStack::class      => fn (Request $request) => new RequestStack([$request]),
        SessionInterface::class  => fn (Request $request) => $request->hasSession()
            ? $request->getSession()
            : tap($request, fn (Request $request) => $request->setSession(new Session()))->getSession(),
        ViteAdapter::class       => fn (Request $request) => (new ViteAdapter(
            resolve_path('%project_root%'),
            resolve_path('%public%')
        ))->setHotFile(resolve_path('%public%', 'build/hot'))
            ->setBasePath($request->getBasePath())
            ->setBuildDirectory('build'),
        Request::class           => Services::getRequest(),
        LocaleService::class     => $translator,
        Translator::class        => $translator->getTranslator(),
        JsonResponseView::class  => function ()
        {
            return Services::getResponse();
        },
        HtmlPage::class          => fn () => Services::getPage(),
        Routing::class           => fn (Container $container, LoggerService $logger) => tap(new Routing(), fn (Routing $routing) => $routing
            ->addDefinitions([LoggerInterface::class => fn () => $logger])
            ->setContainerFactory(new DefaultContainerBuilder($container))),
        OpenApi::class           => fn () => Services::getOpenApi(),
    ]);

    $container->alias(TranslatorInterface::class, Translator::class);
    $container->alias(InputInterface::class, ArgvInput::class);
    $container->alias(OutputInterface::class, ConsoleOutput::class);
    $container->alias(LoggerInterface::class, LoggerService::class);
    $container->alias(UrlGeneratorInterface::class, RouteGenerator::class);
};
