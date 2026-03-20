<?php

/** @noinspection PhpUnnecessaryStringCastInspection */

/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HtmlPage implements Stringable
{
    /** @var ResponseView */
    private $responseView;

    /** @var ?string */
    private $fallback             = null;

    /** @var ?string */
    private $not_found            = null;

    /** @var ?string */
    private $view                 = null;

    /** @var ?string */
    private $doc_type             = null;

    /** @var array<string,string> */
    private array $bodyAttributes = [];

    /** @var string */
    private $body                 = '';

    /** @var string */
    private $charset              = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';

    /** @var ?string */
    private $content              = null;

    /** @var string */
    private $segment              = '';

    /** @var string */
    private $title                = '';

    /** @var object{name:string, content:string}[] */
    private $meta                 = [];

    /** @var string[] */
    private $preload              = [];
    /** @var string[] */
    private $styles               = [];
    /** @var string[] */
    private $scripts              = [];

    /** @var ?string */
    private $icon                 = null;

    /** @var string */
    private $headStart            = '';

    /** @var string */
    private $head                 = '';

    /** @var ?string */
    private $header               = 'header';

    /** @var ?string */
    private $footer               = 'footer';

    /** @var array<string,mixed> */
    private $attributes           = [];

    /** @var string[] */
    private $extends              = [];

    /** @var ?string */
    private $pwd;

    public function __construct()
    {
        $this->pwd          = getcwd();
        $this->responseView = new ResponseView();
        $this->addMeta('viewport', 'width=device-width; initial-scale=1.0');
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    public function setBodyAttribute(string $attribute, bool|float|int|string|null $value)
    {
        unset($this->bodyAttributes[$attribute]);

        if (null !== $value)
        {
            $this->bodyAttributes[$attribute] = $value;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function removeAttribute($name)
    {
        unset($this->attributes[$name]);
        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return static
     */
    public function addAttribute($name, $value)
    {
        if ( ! $this->hasAttribute($name))
        {
            $this->setAttribute($name, $value);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return static
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    /**
     * @param array $attributes
     *
     * @return static
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        if ( ! $this->hasAttribute($name))
        {
            return value($default);
        }
        return $this->attributes[$name];
    }

    /**
     * @return string
     */
    public function getDocType()
    {
        if ( ! isset($this->doc_type))
        {
            $this->setDocType(env_get('APP_LANG'));
        }
        return $this->doc_type;
    }

    public function addExtend(string $view)
    {
        if (is_file("{$view}.php"))
        {
            $this->extends[] = $view;
        }
        return $this;
    }

    public function loadExtend(array $attributes)
    {
        if ($__view__ = $this->extends[0] ?? '')
        {
            array_shift($this->extends);
            $attributes['content'] = $this->segment;
            ob_start();

            try
            {
                extract($attributes);
                @include "{$__view__}.php";
            } catch (Throwable $exception)
            {
                ob_end_clean();
                throw $exception;
            }
            $this->segment         = ob_get_clean();
        }
    }

    /**
     * @param string              $lang
     * @param array<string,mixed> $attributes
     *
     * @return static
     *
     * @noinspection HtmlRequiredLangAttribute
     * @noinspection HtmlUnknownAttribute
     */
    public function setDocType($lang = 'en', array $attributes = [])
    {
        $this->doc_type = sprintf(
            "<!DOCTYPE html>\n<html %s>\n",
            renderArgs(array_replace(
                ['lang' => $lang],
                $attributes,
            ))
        );

        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $content
     *
     * @return static
     */
    public function addMeta($name, $content)
    {
        if ( ! is_string($content))
        {
            $value   = json_encode($content);

            if (JSON_ERROR_NONE !== json_last_error())
            {
                $value = "{$content}";
            }
            $content = $value;
        }
        $this->meta[] = (object) compact('name', 'content');
        return $this;
    }

    /**
     * @return null|string
     */
    public function getFallback()
    {
        return $this->fallback;
    }

    /**
     * @param null|string $fallback
     *
     * @return static
     */
    public function setFallback($fallback)
    {
        $this->fallback = $fallback;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getNotFound()
    {
        return $this->not_found;
    }

    /**
     * @param null|string $not_found
     *
     * @return static
     */
    public function setNotFound($not_found)
    {
        $this->not_found = $not_found;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @param null|string $view
     *
     * @return static
     */
    public function setView($view)
    {
        $this->view = $view;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getFooter()
    {
        return $this->footer;
    }

    /**
     * @param null|string $footer
     *
     * @return static
     */
    public function setFooter($footer)
    {
        $this->footer = $footer;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @param string $header
     *
     * @return static
     */
    public function setHeader($header)
    {
        $this->header = $header;
        return $this;
    }

    /**
     * @param string $style
     *
     * @return static
     *
     * @noinspection HtmlUnknownTarget
     */
    public function addStyle($style)
    {
        $this->styles[]  = sprintf(
            '<link rel="stylesheet" type="text/css" href="%s">',
            $this->addTimestamp($style)
        );

        $this->preload[] = sprintf(
            '<link rel="preload" as="style" href="%s">',
            $style
        );
        return $this;
    }

    /**
     * @param string $script
     * @param bool   $defer
     *
     * @return static
     *
     * @noinspection HtmlUnknownTarget
     */
    public function addScript($script, $defer = false)
    {
        $this->scripts[] = sprintf(
            '<script type="text/javascript" src="%s"%s></script>',
            $this->addTimestamp($script),
            $defer ? ' defer' : ''
        );
        $this->preload[] = sprintf(
            '<link rel="preload" as="script" href="%s">',
            $script
        );
        return $this;
    }

    /**
     * @param string $title
     *
     * @return static
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return ?string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @param string $icon
     *
     * @return static
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @param string|Stringable $code
     * @param bool              $start
     *
     * @return static
     */
    public function addHead($code, $start = false)
    {
        if ( ! $start)
        {
            $this->head .= (string) $code;
        } else
        {
            $this->headStart .= (string) $start;
        }

        return $this;
    }

    /**
     * @param string|Stringable $code
     * @param bool              $prepend
     *
     * @return static
     */
    public function addBody($code, $prepend = false)
    {
        if ($prepend)
        {
            $this->body = ((string) $code) . $this->body;
            return $this;
        }
        $this->body .= (string) $code;
        return $this;
    }

    /**
     * @param int $statusCode
     *
     * @return static
     */
    public function setStatusCode(int $statusCode)
    {
        $this->responseView->setStatusCode($statusCode);
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->responseView->getStatusCode();
    }

    /**
     * @return string
     */
    public function render(array $attributes = [], bool $force = false)
    {
        if (null === $this->content || $force)
        {
            @ob_start();
            $this->content = null;

            try
            {
                $this->loadView($this->view, $attributes);
            } catch (NotFoundHttpException $exception)
            {
                if ($this->not_found)
                {
                    $this->fallback = $this->not_found;
                }
                $this->logErrorAndFallback($exception, $attributes);
            } catch (Throwable $error)
            {
                $this->fallback = 'error/500';
                $this->logErrorAndFallback($error, $attributes);
            } finally
            {
                $this->content = @ob_get_clean() ?: '';
            }
        }

        return $this->content;
    }

    /**
     * @return ResponseView
     */
    public function getResponse(array $attributes = [], bool $force = false)
    {
        return $this->responseView->setContent($this->render($attributes, $force));
    }

    /**
     * @noinspection PhpReturnDocTypeMismatchInspection
     *
     * @return never
     *
     * @noinspection PhpNoReturnAttributeCanBeAddedInspection
     */
    public function display(array $attributes = [], bool $force = false)
    {
        render_response($this->getResponse($attributes, $force));
    }

    private function logErrorAndFallback(Throwable $exception, array $attributes = [])
    {
        tap(ApplicationLogger::hasBackTrace(), function (bool $backtrace) use ($exception)
        {
            ApplicationLogger::setBackTrace(false);
            Services::getLogger()->error(env_get('APP_DEBUG', false)
                ? sprintf(
                    "%s:%d %s(%s)\ntrace: %s",
                    basename($exception->getFile()),
                    $exception->getLine(),
                    get_class($exception),
                    $exception->getMessage(),
                    $exception->getTraceAsString()
                )
                : sprintf(
                    '%s:%d %s(%s)',
                    basename($exception->getFile()),
                    $exception->getLine(),
                    get_class($exception),
                    $exception->getMessage()
                ));
            ApplicationLogger::setBackTrace($backtrace);
        });

        @chdir($this->pwd);
        @ob_end_clean();
        @ob_start();

        if ($this->fallback)
        {
            $this->loadView($this->fallback, $attributes);
        } else
        {
            $this->setStatusCode(500);
        }
    }

    /**
     * @param string $input
     *
     * @return string
     */
    private function addTimestamp($input)
    {
        static $timestamp = null;

        if ( ! is_dev())
        {
            return $input;
        }

        if ( ! $timestamp)
        {
            $timestamp = time();
        }
        $input .= str_contains($input, '?') ? '&' : '?';

        return "{$input}_={$timestamp}";
    }

    /**
     * @param ?string             $name
     * @param array<string,mixed> $attributes
     *
     * @noinspection HtmlUnknownTarget
     *
     * @phan-suppress PhanUnusedVariable
     */
    private function loadView($name = null, $attributes = [])
    {
        try
        {
            @chdir(resolve_path('%project_root%/view'));

            if ( ! $name)
            {
                $name = $this->view;
            }

            $attributes = array_replace($this->attributes, $attributes);
            // do not remove, that var is injected in the template (if no content attribute defined)
            $content    = '';

            if ($name)
            {
                // if setting an attribute $name
                $__view__      = $name;
                ob_start();

                try
                {
                    extract($attributes);
                    @include "{$__view__}.php";
                } catch (Throwable $exception)
                {
                    ob_end_clean();
                    throw $exception;
                }
                $this->segment = ob_get_clean();
                $this->loadExtend($attributes);
            }

            $content    = $this->segment;

            echo $this->getDocType() . "<head>\n{$this->charset}\n";

            echo $this->headStart;

            if ($this->icon)
            {
                printf("<link rel=\"shortcut icon\" href=\"%s\">\n", $this->icon);
            }

            foreach (array_unique($this->preload) as $html)
            {
                echo "{$html}\n";
            }

            printf("<title>%s</title>\n", $this->title);

            foreach ($this->meta as $meta)
            {
                printf("<meta name=\"%s\" content=\"%s\" />\n", $meta->name, $meta->content);
            }

            foreach (array_unique($this->styles) as $html)
            {
                echo "{$html}\n";
            }

            echo $this->head;

            echo "</head>\n";
            $bodyArgs   = rtrim(' ' . renderArgs($this->bodyAttributes));
            echo "<body{$bodyArgs}>\n";

            if ( ! $this->body)
            {
                if ($this->header && is_file("{$this->header}.php"))
                {
                    echo "<header>\n";
                    require_secure("{$this->header}.php");
                    echo "</header>\n";
                }

                echo "<main>\n{$content}\n</main>\n";

                if ($this->footer && is_file("{$this->footer}.php"))
                {
                    echo "<footer>\n";
                    require_secure("{$this->footer}.php");
                    echo "</footer>\n";
                }
            } else
            {
                echo $this->body . "\n";
            }

            foreach (array_unique($this->scripts) as $html)
            {
                echo "{$html}\n";
            }
            echo "</body>\n</html>";
        } finally
        {
            @chdir($this->pwd);
        }
    }
}
