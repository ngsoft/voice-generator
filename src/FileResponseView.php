<?php

use Service\MimeService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class FileResponseView extends ResponseView
{
    /** @var string */
    protected $fileName    = '';

    protected $file        = '';
    protected $disposition = 'inline';

    public function getDisposition(): string
    {
        return $this->disposition;
    }

    public function setDisposition(string $disposition): static
    {
        if (in_array($disposition, ['inline', 'attachment']))
        {
            $this->disposition = $disposition;
        }
        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        if (is_file($fileName) && ! $this->file)
        {
            $this->file = $fileName;
        }
        $this->fileName = $fileName;
        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): static
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @return Response
     */
    public function toResponse()
    {
        static $mimeService = new MimeService();
        $content            = $this->content;
        $file               = $this->file;
        $name               = $this->fileName ?: $file;

        if ( ! $content && is_file($file))
        {
            $this->setContentType($mimeService->fromFileName($file));
            return (new BinaryFileResponse($file, $this->getStatusCode(), $this->getAllHeaders()))
                ->setContentDisposition($this->disposition, basename($name));
        }

        if ($content)
        {
            $this->setContentType($mimeService->fromContent($content));

            if ($name)
            {
                $this->addHeader('Content-Disposition', sprintf('%s; filename="%s"', $this->disposition, basename($name)));
            }
            return parent::toResponse();
        }

        return JsonResponseView::newNotFound()->toResponse();
    }
}
