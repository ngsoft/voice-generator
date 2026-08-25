<?php

namespace Controller\Action;

use Controller\BaseController;
use Interfaces\ActionInterface;
use OpenApi\Attributes as OA;
use Service\LocaleService;
use Symfony\Component\HttpFoundation\Request;
use View\ErrorResponse;
use View\SuccessResponse;

#[OA\Post('/api/translate', 'Translate text', description: 'Translate a message key or text using the application locale catalog.', requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
    'application/json',
    schema: new OA\Schema(required: ['text'], properties: [
        new OA\Property('text', description: 'Message key or text to translate', type: 'string', nullable: false),
        new OA\Property('lang', description: 'Target locale (e.g. en, fr). Uses the request/app locale when omitted.', type: 'string', example: 'fr', nullable: true),
    ])
)), tags: ['Core Components'])]
#[OA\Response(
    response: 200,
    description: 'Translated message',
    content: new OA\MediaType(
        'application/json',
        schema: new OA\Schema(SuccessResponse::class)
    )
)]
#[OA\Response(response: 400, description: 'Bad Request', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
#[OA\Tag(name: 'Core Components', description: 'Shared application utilities.')]
class TranslateAction extends BaseController implements ActionInterface
{
    public function __construct(private readonly LocaleService $localeService) {}

    public function execute(Request $request): \ResponseView
    {
        $this->localeService->setBrowserLocaleFromRequest($request);

        $text   = var_get('text', $body = $this->decodeJsonBody($request, []));

        if ( ! $text)
        {
            return $this->toErrorResponse(400);
        }
        $locale = null;

        if ($force = env_get('APP_LANG_FORCED'))
        {
            $locale = $force;
        }
        return SuccessResponse::make([
            'message' => $this->localeService->translate($text, locale: var_get('lang', $body, $locale)),
        ])->toResponseView();
    }
}
