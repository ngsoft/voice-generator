<?php

namespace Service;

use NGSOFT\Vite\Adapter\ViteAdapter;
use NGSOFT\Vite\Adapter\ViteException;

class ViteService implements \Stringable
{
    private readonly array $endpoints;

    private bool $canLoad = true;

    private array $loaded = [];

    public function __construct(
        private readonly ViteAdapter $viteAdapter,
        private readonly LoggerService $logger,
    ) {
        if (empty($endpoints = env_get('APP_VITE_ENDPOINTS')))
        {
            $endpoints = [];
        } elseif ( ! is_array($endpoints))
        {
            $endpoints = [$endpoints];
        }
        $this->endpoints = $endpoints;
    }

    public function __toString(): string
    {
        return $this->getHtml();
    }

    public function getHtml(array|string|null $entrypoint = null, $load_client = false): string
    {
        if ( ! $this->canLoad)
        {
            return '';
        }

        // load assets once
        $entrypoint ??= $this->endpoints;

        if (empty($entrypoint))
        {
            return '';
        }

        $client = empty($this->loaded) || $load_client;
        $load   = [];

        foreach (( ! is_array($entrypoint) ? [$entrypoint] : $entrypoint) as $asset)
        {
            if ( ! in_array($asset, $this->loaded))
            {
                $load[]         = $asset;
                $this->loaded[] = $asset;
            }
        }

        try
        {
            $html = $this->viteAdapter->loadEntryPoints($load);

            // remove multiple client definitions
            if ( ! $client && preg_match('#<script.+@vite/client.+script>\n#i', $html, $capture))
            {
                $html = str_replace($capture[0], '', $html);
            }
            return $html;
        } catch (ViteException $error)
        {
            $this->logger->error(
                'error loading vite entrypoints%s: %s',
                [json_encode($this->endpoints), $error->getMessage()],
            );
            return '';
        }
    }
}
