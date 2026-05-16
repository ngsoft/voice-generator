<?php

namespace Controller\Action;

use Controller\BaseController;
use Controller\SynthesisController;
use Interfaces\ActionInterface;
use Provider\SynthesisProviderStack;
use Service\LocaleService;
use Symfony\Component\HttpFoundation\Request;

class HomeAction extends BaseController implements ActionInterface
{
    public function __construct(
        private readonly SynthesisProviderStack $synthesisProviderStack,
        private readonly SynthesisController $synthesisController,
        private readonly LocaleService $localeService,
    ) {}

    public function execute(Request $request): \ResponseView
    {
        $this->localeService->setBrowserLocaleFromRequest($request);
        $voices = $this->synthesisController->filterAndSortVoices(
            $this->synthesisProviderStack->getVoices(),
            env_get('LANG_FILTER', '', false)
        );

        $langs  = [];
        $result = [];

        foreach ($voices as $voice)
        {
            $lang             = $voice->getLang();
            $prefix           = explode('-', $lang)[0];
            $langs[$prefix] ??= [];
            $langs[$prefix][] = $lang;
            $langs[$prefix]   = array_unique($langs[$prefix]);
            sort($langs[$prefix]);
            $result[$lang]  ??= [];
            $result[$lang][]  = $voice;
            $this->synthesisController->addVoiceUri($voice);
        }

        return $this->render('page/player-form', [
            'vite_data' => [
                'base_url'  => rtrim($this->generateUrl('app_index'), '/'),
                'providers' => $this->synthesisProviderStack->listProviders(),
                'voices'    => $result,
                'langs'     => $langs,
            ],
        ]);
    }
}
