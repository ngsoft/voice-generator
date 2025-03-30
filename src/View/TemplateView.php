<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace View;

use Renderer\BaseResponse;
use Traits\HasAttributes;

use function NGSOFT\Filesystem\normalize_path;

class TemplateView extends BaseResponse
{
    use HasAttributes;

    private string $extension = '.php';
    private string $layout    = '';
    private string $extend    = '';
    private string $path;

    private array $templates  = [];

    public function __construct(?string $path = null, private string $template = '')
    {
        $path ??= \Globals::getItem('template_path');
        $this->path = normalize_path($path) . DIRECTORY_SEPARATOR;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): static
    {
        $this->template = $template;
        return $this;
    }

    public function getContent(): string
    {
        return $this->fetch($this->template);
    }

    public function fetch(string $template, array $data = [], bool $layout = false): string
    {
        $this->setContentType('text/html; charset=utf-8');

        $output = $this->fetchTemplate($template, $data);

        while ( ! empty($this->extend))
        {
            $template         = $this->extend;
            $this->extend     = '';
            $data['contents'] = $output;
            $output           = $this->fetchTemplate($template, $data);
        }

        if ($layout && $this->layout && ! in_array($this->layout, $this->templates))
        {
            $data['contents'] = $output;
            $output           = $this->fetchTemplate($this->layout, $data);
        }
        return $output;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): static
    {
        $this->extension = $extension;
        return $this;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function setLayout(string $layout): static
    {
        if ($this->extension && str_ends_with($layout, $this->extension))
        {
            $layout = substr($layout, 0, -strlen($this->extension));
        }
        $this->layout = $layout;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getExtend(): string
    {
        return $this->extend;
    }

    public function setExtend(string $extend): static
    {
        if ($this->extension && str_ends_with($extend, $this->extension))
        {
            $extend = substr($extend, 0, -strlen($this->extension));
        }
        $this->extend = $extend;
        return $this;
    }

    private function fetchTemplate(string $name, array $data): string
    {
        if (str_ends_with($name, $this->extension))
        {
            $name = substr($name, 0, -strlen($this->extension));
        }

        if ( ! $name)
        {
            return '';
        }

        if (in_array($name, $this->templates))
        {
            throw new \RuntimeException(sprintf(
                'Template "%s" already been loaded.',
                $name
            ));
        }

        $file = $this->path . $name . $this->extension;

        if ( ! is_file($file))
        {
            throw new \RuntimeException(sprintf(
                'Template "%s" does not exists.',
                $name
            ));
        }

        $data = array_replace($this->attributes, $data);
        ob_start();

        try
        {
            require_secure($file, $data);
            $this->templates[] = $name;
        } catch (\Throwable $e)
        {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }
}
