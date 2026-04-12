<?php

/** @noinspection PhpPrivateFieldCanBeLocalVariableInspection */

namespace TemplateEngine;

use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/helpers.php';

class Renderer
{
    private static ?Context $currentContext = null;

    private Context $context;
    private readonly string $storage;
    private readonly string $cwd;

    private array $stack                    = [];
    private string $current_content         = '';
    private ?string $current_file           = null;
    private ?array $current_attributes      = null;
    private ?Context $previous_context      = null;

    public function __construct(
        string $storage,
        ?Context $context = null,
    ) {
        $this->context = $context ?? new Context();
        $this->storage = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $storage), '/');
        $this->cwd     = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', getcwd()), '/');
        self::$currentContext ??= $this->context;
    }

    public function __clone(): void
    {
        $this->context = clone $this->context;
        $this->resetContext();
    }

    /**
     * Returns current view attributes.
     */
    public function getCurrentAttributes(): ?array
    {
        return $this->current_attributes;
    }

    /**
     * Get Current context
     * Context is accessible to functions when loading views.
     */
    public static function getCurrentContext(): Context
    {
        if ( ! self::$currentContext)
        {
            throw new \LogicException('Context is not available');
        }
        return self::$currentContext;
    }

    /**
     * Set new Context and returns previous one.
     */
    public static function setCurrentContext(Context $context): ?Context
    {
        return tap(self::getCurrentContext(), fn () => self::$currentContext = $context);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function renderView(string $view, array $attributes = []): string
    {
        $this->stack = [$view];
        return $this->doRender($attributes);
    }

    public function render(string $view, array $attributes = []): Response
    {
        $content = $this->renderView($view, $attributes);
        return new Response(
            $content,
            $this->getContext()->getAttribute('status_code', 200),
            $this->getContext()->headers->all()
        );
    }

    public function hasAttribute(string|\Stringable $name): bool
    {
        return $this->context->hasAttribute($name);
    }

    public function removeAttribute(string|\Stringable $name): static
    {
        $this->context->removeAttribute($name);
        return $this;
    }

    public function addAttribute(string|\Stringable $name, mixed $value): static
    {
        $this->context->addAttribute($name, $value);

        return $this;
    }

    /**
     * @param \Generator&iterable<string|\Stringable,mixed> $attributes
     */
    public function addAttributes(iterable $attributes): static
    {
        $this->context->addAttributes($attributes);
        return $this;
    }

    public function setAttribute(string|\Stringable $name, mixed $value): static
    {
        $this->context->setAttribute($name, $value);
        return $this;
    }

    /**
     * @param iterable<string|\Stringable,mixed> $attributes
     */
    public function setAttributes(iterable $attributes): static
    {
        $this->context->setAttributes($attributes);
        return $this;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->context->getAttribute($name, $default);
    }

    private function doRender(array $attributes): string
    {
        $this->current_content    = '';
        $this->current_attributes = $attributes;

        while (null !== $view = array_shift($this->stack))
        {
            $this->current_file     = resolve_path($this->storage, $view);

            if ( ! str_ends_with($this->current_file, '.php'))
            {
                $this->current_file .= '.php';
            }

            if ( ! is_file($this->current_file))
            {
                throw new \RuntimeException("Cannot find view '{$view}'");
            }

            $this->previous_context = self::$currentContext;
            self::$currentContext   = $this->context;
            $this->context->setAttribute(Renderer::class, $this);
            $attributes             = array_replace(
                $this->context->getAttributes(),
                $this->current_attributes,
                ['content' => $this->current_content]
            );

            try
            {
                @ob_start();
                @chdir(dirname($this->current_file));
                extract($attributes);
                @include $this->current_file;
            } catch (\Throwable $error)
            {
                $this->resetContext();
                @ob_end_clean();
                @chdir($this->cwd);
                throw $error;
            }

            // check for start_block() without end_block()
            if ($this->context->hasAttribute('current_block_name'))
            {
                // close all buffers
                do
                {
                    $tmp = @ob_end_clean();
                } while (false !== $tmp);
                throw new \RuntimeException(
                    sprintf(
                        'Block "%s" has not been closed',
                        $this->context->getAttribute('current_block_name')
                    )
                );
            }

            @chdir($this->cwd);
            $this->current_content  = @ob_get_clean() ?: '';

            // view extends view
            if ($this->context->hasAttribute('next-view'))
            {
                $this->stack[] = $this->context->pullAttribute('next-view');
            }
        }

        if ($this->previous_context)
        {
            self::$currentContext   = $this->previous_context;
            $this->previous_context = null;
        }
        return $this->current_content;
    }

    private function resetContext(): void
    {
        $this->context->removeAttribute('current_block_name');
        $this->context->removeAttribute('next-view');
    }
}
