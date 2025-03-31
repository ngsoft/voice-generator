<?php

namespace Action;

use Renderer\BaseResponse;
use View\TemplateView;

class ViteAction extends \Action
{
    public function __construct(private readonly TemplateView $templateView) {}

    public function getTemplateView(): TemplateView
    {
        return $this->templateView;
    }

    protected function execute(\Request $request): \Response
    {
        $template = $this->getTemplateView();

        $route    = $request->getUri();
        $base     = getBasePath();

        if ($request->hasAttribute('path'))
        {
            $route = $request->getAttribute('path');
        }

        if (preg_match('#\.\w{3,5}$#', $route))
        {
            return BaseResponse::newResponse()->setResponseCode(404);
        }

        return $template
            ->setTemplate('vite')
            ->setAttributes($request->getAttributes() + ['route' => $route, 'base' => $base]);
    }
}
