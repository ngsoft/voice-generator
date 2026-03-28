<?php

namespace Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\Loader\CsvFileLoader;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Loader\PoFileLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

class LocaleService
{
    /**
     * @var Translator
     */
    private $translator;
    /**
     * @var array
     */
    private $locales       = [];

    /**
     * @var null|string
     */
    private $locale;

    /**
     * @var null|mixed|string
     */
    private $defaultLocale;

    /**
     * @var string
     */
    private $browserLocale = 'en_us';

    /**
     * @param Translator $translator
     * @param string[]   $locales    translator defined locales
     * @param ?Request   $request    request to read browser prefs
     */
    public function __construct(
        Translator $translator,
        array $locales,
        ?Request $request = null
    ) {
        $this->translator    = $translator;
        $this->locales       = $locales;
        $this->defaultLocale = env_get('APP_LANG');

        if ($request)
        {
            $this->setBrowserLocaleFromRequest($request);
        }
    }

    /**
     * @param array|string $locations
     * @param null|string  $cacheLocation
     *
     * @return static
     */
    public static function loadDir($locations, $cacheLocation = null)
    {
        $translator = new Translator(
            env_get('APP_LANG', ''),
            new MessageFormatter(),
            $cacheLocation
        );

        if ($cacheLocation && ! file_exists($cacheLocation))
        {
            $mask = @umask(0);
            @mkdir($cacheLocation, 0777, true);
            @chmod($cacheLocation, 0777);
            @umask($mask);
        }

        $locales    = ['en'];

        if ( ! empty($locations))
        {
            $extensions    = [
                'php'  => PhpFileLoader::class,
                'csv'  => CsvFileLoader::class,
                'yaml' => YamlFileLoader::class,
                'yml'  => YamlFileLoader::class,
                'json' => JsonFileLoader::class,
                'po'   => PoFileLoader::class,
            ];
            $extensionList = '(' . implode(
                '|',
                array_keys($extensions)
            ) . ')';

            $re            = sprintf('#(\w+)\.%s$#i', $extensionList);
            $reDomain      = sprintf('#^([\w-]+)\.(\w+)\.%s$#i', $extensionList);
            $rePath        = sprintf('#(\w+)/([\w-]+)\.%s$#i', $extensionList);
            $loaded        = [];
            $resources     = [];

            if ( ! is_array($locations))
            {
                $locations = [$locations];
            }

            foreach ($locations as $dir)
            {
                if ( ! is_dir($dir))
                {
                    continue;
                }

                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $dir,
                        \FilesystemIterator::SKIP_DOTS
                    )
                );

                /** @var \SplFileInfo $info */
                foreach ($iterator as $info)
                {
                    if ( ! $info->isFile())
                    {
                        continue;
                    }

                    $path         = normalize_path($info->getPathname());
                    $relativePath = substr($path, strlen($dir));

                    $domain
                                  = $ext
                                  = $locale
                                  = null;

                    if (preg_match($rePath, $relativePath, $matches))
                    {
                        list(, $locale, $domain, $ext) = $matches;
                    } elseif (preg_match($reDomain, $relativePath, $matches))
                    {
                        list(, $domain, $locale, $ext) = $matches;
                    } elseif (preg_match($re, $relativePath, $matches))
                    {
                        list(, $locale, $ext) = $matches;
                        $domain               = 'messages';
                    }

                    if ($domain && $ext && $locale)
                    {
                        $domain      = strtolower($domain);
                        $ext         = strtolower($ext);
                        $locales[]   = $locale;
                        $loaded[]    = $ext;
                        $resources[] = [$ext, $path, $locale, $domain];
                    }
                }
            }

            $loaded        = array_values(array_unique($loaded));

            // add loaders
            foreach ($loaded as $ext)
            {
                $class = $extensions[$ext];

                $translator->addLoader($ext, new $class());
            }

            foreach ($resources as $resource)
            {
                $translator->addResource(...$resource);
            }
        }
        return new static($translator, array_values(array_unique($locales)));
    }

    public function getTranslator(): Translator
    {
        return $this->translator;
    }

    /**
     * @param Request $request
     *
     * @return static
     */
    public function setBrowserLocaleFromRequest(Request $request)
    {
        $this->browserLocale = strtolower($request->getPreferredLanguage($this->locales ?: null));
        return $this;
    }

    /**
     * @param bool $detect
     *
     * @return string
     */
    public function getLang($detect = true)
    {
        if ($this->locale)
        {
            return $this->locale;
        }

        if ($detect && in_array($this->browserLocale, $this->locales))
        {
            return $this->browserLocale;
        }

        return $this->defaultLocale;
    }

    /**
     * @param null|string $locale
     *
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        if ( ! isset($this->locale))
        {
            return $this->getBrowserLocale() ?: $this->getDefaultLocale();
        }
        return $this->locale;
    }

    /**
     * @return string
     */
    public function getBrowserLocale()
    {
        return $this->browserLocale;
    }

    /**
     * @return string
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    /**
     * @param string      $id
     * @param array       $params
     * @param null|string $domain
     * @param null|string $locale
     *
     * @return string
     */
    public function translate($id, array $params = [], $domain = null, $locale = null)
    {
        if ( ! isset($locale))
        {
            $locale = $this->getLocale();
        }

        return $this->translator->trans($id, $params, $domain, $locale);
    }
}
