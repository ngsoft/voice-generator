<?php

namespace Controller\Action;

use Controller\BaseController;
use Interfaces\ActionInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use View\ErrorResponse;
use View\SuccessResponse;

#[OA\Post('/api/translate', 'Translate text', description: 'Translate input text', requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
    'application/json',
    schema: new OA\Schema(properties: [
        new OA\Property('text', description: 'Translate input text', type: 'string', nullable: false),
        new OA\Property('lang', description: 'Translation locale', type: 'string', nullable: true),
    ])
)), tags: ['Core Components'])]
#[OA\Response(
    response: 200,
    description: 'ok',
    content: new OA\MediaType(
        'application/json',
        schema: new OA\Schema(SuccessResponse::class)
    )
)]
#[OA\Response(response: 400, description: 'Bad Request', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
#[OA\Tag('Core Components')]
class TranslateAction extends BaseController implements ActionInterface
{
    public function __construct(private readonly TranslatorInterface $translator) {}

    public function execute(Request $request): \ResponseView
    {
        $text = var_get('text', $body = $this->decodeJsonBody($request, []));

        if ( ! $text)
        {
            return $this->toErrorResponse(400);
        }

        return SuccessResponse::make([
            'message' => $this->translator->trans($text, locale: var_get('lang', $body)),
        ])->toResponseView();
    }
}
