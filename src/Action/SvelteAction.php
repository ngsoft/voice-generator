<?php

namespace Action;

use Renderer\BaseResponse;
use View\TemplateView;

class SvelteAction extends \Action
{
    public function __construct(private readonly TemplateView $templateView) {}

    public function getTemplateView(): TemplateView
    {
        return $this->templateView;
    }

    protected function execute(\Request $request): \Response
    {
        $template = $this->getTemplateView();

        $svelte   = [];
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
            ->setTemplate('svelte')
            ->setAttributes($request->getAttributes() + ['svelte' => $svelte, 'route' => $route, 'base' => $base]);
    }
}
