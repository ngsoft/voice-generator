<?php

use NGSOFT\Container\Container;
use NGSOFT\Routing\Routing;
use OpenApi\Annotations\OpenApi;
use OpenApi\Generator;
use OpenApi\Processors\OperationId;
use Service\LocaleService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Site Global Access Services.
 * Register your singleton services there.
 *
 * @abstract Cannot be instantiated
 */
abstract class Services
{
    /**
     * @var null|Request
     */
    private static $request;

    /**
     * @var null|ApplicationLogger
     */
    private static $logger;

    /**
     * @var null|JsonResponseView
     */
    private static $jsonResponseView;

    /**
     * @var null|Container
     */
    private static $container = null;

    /**
     * @var null|LocaleService
     */
    private static $translator;

    /**
     * @var null|OpenApi
     */
    private static $openApi   = null;

    /**
     * @return Request
     */
    public static function getRequest()
    {
        return self::$request;
    }

    /**
     * @return ApplicationLogger
     */
    public static function getLogger()
    {
        return self::$logger;
    }

    /**
     * @return JsonResponseView
     */
    public static function getResponse()
    {
        if ( ! isset(self::$jsonResponseView))
        {
            self::$jsonResponseView = new JsonResponseView();
        }
        return self::$jsonResponseView;
    }

    /**
     * Loads service from Container.
     *
     * @template T
     *
     * @param class-string<T> $id
     * @param mixed           ...$params
     *
     * @return T
     */
    public static function make(string $id, mixed ...$params)
    {
        if (empty($params))
        {
            return self::getContainer()->get($id);
        }
        return self::getContainer()->make($id, $params);
    }

    public static function getContainer(): Container
    {
        if ( ! isset(self::$container))
        {
            self::$container = tap(
                new Container(),
                fn (Container $container) => require_secure(resolve_path('%config%/dependency-injection.php'))($container)
            );
        }
        return self::$container;
    }

    /**
     * @return LocaleService
     */
    public static function getTranslator()
    {
        return self::$translator;
    }

    /**
     * @return Routing
     */
    public static function getRouter()
    {
        static $file = '%config%/router.php', $config = null;
        $router      = self::make(Routing::class);
        $router->getRouter()->setRequest(self::getRequest());

        if ( ! isset($config))
        {
            $config = require_secure(resolve_path($file));

            if ($config instanceof Closure)
            {
                $router->addConfiguration($config);
            }
        }

        return $router;
    }

    /**
     * @return OpenApi
     */
    public static function getOpenApi()
    {
        if ( ! self::$openApi)
        {
            if ( ! is_writable($data = resolve_path('%data%')))
            {
                throw new RuntimeException("directory {$data} is not writable");
            }

            call_user_func(
                function ($file, $cfg)
                {
                    // check modifications on cfg
                    if (@filemtime($file) <= @filemtime($cfg))
                    {
                        require_once $cfg;
                        $request = self::getRequest();
                        $meta    = [
                            '<?php',
                            'namespace OpenApi\Metadata;',
                            'use OpenApi\Attributes as OA;',
                            sprintf(
                                '#[OA\Info(version: "%s", description: "%s", title: "%s")]',
                                Config::getItem('openapi.info.version', '1.0'),
                                Config::getItem('openapi.info.description', 'My Application'),
                                Config::getItem('openapi.info.title', 'app')
                            ),
                            sprintf('#[OA\Server("%s")]', $request->getSchemeAndHttpHost() . $request->getBasePath()),
                            sprintf(
                                '#[OA\License("%s", url: "%s")]',
                                Config::getItem('openapi.licence', 'The Unlicense'),
                                Config::getItem('openapi.licence.url', 'https://unlicense.org/UNLICENSE')
                            ),
                            'class OpenApiMetadata {}',
                        ];
                        @mkdir(dirname($file), 0777, true);
                        @file_put_contents($file, implode("\n", $meta));
                    }

                    require_once $file;
                },
                $file = resolve_path($data, 'openapi', is_dev() ? 'dev' : 'prod', 'OpenApiMetadata.php'),
                resolve_path('%config%/openapi.php')
            );

            $generator = self::make(Generator::class);
            $generator->getProcessorPipeline()->remove(OperationId::class);

            try
            {
                @ob_start();
                self::$openApi = $generator->generate([
                    $file, ...Config::getItem('openapi.paths', []),
                ]);
            } finally
            {
                $warnings = @ob_get_clean();

                if ( ! empty($warnings))
                {
                    $warnings = strip_tags($warnings);
                    $warnings = preg_replace('#\r\n#', "\n", $warnings);

                    foreach (explode("\n", $warnings) as $message)
                    {
                        if ( ! empty(trim($message)))
                        {
                            self::getLogger()->warn(preg_replace('#^Warning:\h+#i', '', trim($message)));
                        }
                    }
                }
            }
        }

        return self::$openApi;
    }

    public static function setTranslator(LocaleService $translator)
    {
        if ( ! isset(self::$translator))
        {
            self::$translator = $translator;
        }
    }

    public static function setRequest(Request $request)
    {
        if ( ! isset(self::$request))
        {
            self::$request = $request;
        }
    }

    public static function setLogger(ApplicationLogger $logger)
    {
        if ( ! isset(self::$logger))
        {
            self::$logger = $logger;
        }
    }
}
