<?php

namespace Action;

use Controller\EdgeVoicesController;
use Controller\PiperVoiceController;
use View\TemplateView;

class ListenAction extends ViteAction
{
    public function __construct(
        TemplateView $templateView,
        private readonly PiperVoiceController $voiceController,
        private readonly EdgeVoicesController $edgeVoicesController
    ) {
        parent::__construct($templateView);
    }

    protected function execute(\Request $request): \Response
    {
        $voices = [
            ...$this->voiceController->getAllVoices(),
            ...$this->edgeVoicesController->getAllVoices(),
        ];
        parent::execute($request);
        return $this
            ->getTemplateView()
            ->setTemplate('pages/listen')->addAttribute(
                'voices',
                $voices
            );
    }
}
