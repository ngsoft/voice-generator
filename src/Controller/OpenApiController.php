<?php

namespace Controller;

use Interfaces\ActionInterface;
use OpenApi\Annotations\OpenApi;
use Symfony\Component\HttpFoundation\Request;

class OpenApiController extends BaseController implements ActionInterface
{
    public function __construct(private readonly OpenApi $openApi) {}

    public function execute(Request $request): \ResponseView
    {
        $oa = $this->openApi;

        if (str_ends_with($request->getPathInfo(), '.json'))
        {
            return $this->json($oa->toJson());
        }

        if (str_ends_with($request->getPathInfo(), '.yaml'))
        {
            return (new \FileResponseView())
                ->setDisposition('attachment')
                ->setContent($oa->toYaml())
                ->setFileName('openapi.yaml');
        }
        return $this->render('openapi/redoc-site', ['swagger_data' => [
            'spec'   => json_decode($oa->toJson(), true),
            'config' => ['downloadDefinitionUrl' => $this->generatePath('api_doc_download'), 'colorMode' => true],
        ], 'vite_data' => ['force_color_mode' => 'off'], 'base' => $request->getBasePath()]);
    }
}
