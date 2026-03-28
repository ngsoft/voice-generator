<?php

namespace Controller\Action;

use Controller\BaseController;
use Interfaces\ActionInterface;
use Provider\SynthesisProviderStack;
use Symfony\Component\HttpFoundation\Request;

class HomeAction extends BaseController implements ActionInterface
{
    public function __construct(
        private readonly SynthesisProviderStack $synthesisProviderStack,
    ) {}

    public function execute(Request $request): \ResponseView
    {
        $filter  = env_get('LANG_FILTER', '', false);

        $filters = [];

        if ($filter)
        {
            foreach (explode(',', $filter) as $lang)
            {
                if ($lang = trim(strtolower($lang)))
                {
                    $filters[$lang] = $lang;
                }
            }
        }

        $voices  = $this->synthesisProviderStack->getVoices();
        $langs   = [];
        $result  = [];

        foreach ($voices as $voice)
        {
            $ok    = empty($filters);
            $lang  = $voice->getLang();
            $lower = strtolower($lang);

            if ( ! $ok)
            {
                foreach ($filters as $filter)
                {
                    if (str_contains($lower, $filter))
                    {
                        $ok = true;
                        break;
                    }
                }
            }

            if ($ok)
            {
                $prefix           = explode('-', $lang)[0];
                $langs[$prefix] ??= [];
                $langs[$prefix][] = $voice->getLang();
                $langs[$prefix]   = array_unique($langs[$prefix]);
                sort($langs[$prefix]);
                $result[$lang]  ??= [];
                $result[$lang][]  = $voice;
            }
        }

        return $this->render('page/player', [
            'vite_data' => [
                'base_url'  => rtrim($this->generateUrl('app_index'), '/'),
                'providers' => $this->synthesisProviderStack->listProviders(),
                'voices'    => $result,
                'langs'     => $langs,
            ],
        ]);
    }
}
