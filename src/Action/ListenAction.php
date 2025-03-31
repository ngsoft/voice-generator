<?php

namespace Action;

use Controller\VoiceController;
use View\TemplateView;

class ListenAction extends ViteAction
{
    public function __construct(TemplateView $templateView, private readonly VoiceController $voiceController)
    {
        parent::__construct($templateView);
    }

    protected function execute(\Request $request): \Response
    {
        parent::execute($request);
        return $this
            ->getTemplateView()
            ->setTemplate('pages/listen')->addAttribute(
                'voices',
                $this->voiceController->getAllVoices()
            );
    }
}
