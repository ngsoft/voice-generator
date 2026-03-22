<?php

namespace Controller;

use Model\SearchModel;
use OpenApi\Attributes as OA;
use Provider\SynthesisProviderStack;
use SpeechSynthesis\SpeechSynthesisUtterance;
use SpeechSynthesis\SpeechSynthesisVoice;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use View\ErrorResponse;
use View\SuccessResponse;
use View\VoiceListResponse;

#[OA\Tag('Speech Synthesis')]
class SynthesisController extends BaseController
{
    private readonly int $ttl;

    public function __construct(private readonly SynthesisProviderStack $synthesisProviderStack)
    {
        if ($this->ttl = max(0, (int) env_get('SPEECH_SYNTHESIS_TTL')))
        {
            $this->synthesisProviderStack->prune(
                date_create_immutable(
                    date('Y-m-d H:i:s', time() - $this->ttl)
                )
            );
        }
    }

    #[OA\Get('/api/voices', 'List Voices', description: 'Get all available voices', tags: ['Speech Synthesis'], parameters: [
        new OA\HeaderParameter(name: 'X-Api-Key', allowEmptyValue: true),
        new OA\QueryParameter(name: 'search', description: 'Locale of the voice', allowEmptyValue: true),
        new OA\QueryParameter(name: 'limit', description: 'Number of results per page', allowEmptyValue: true),
        new OA\QueryParameter(name: 'page', description: 'page number', allowEmptyValue: true),
        new OA\QueryParameter(name: 'provider', description: 'provider name', allowEmptyValue: true),
    ])]
    #[OA\Response(
        response: 200,
        description: 'ok',
        content: new OA\MediaType(
            'application/json',
            schema: new OA\Schema(VoiceListResponse::class)
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 500, description: 'Internal Error', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 403, description: 'Forbidden', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    public function getvoices(Request $request): JsonResponse
    {
        $provider    = $request->query->get('provider');

        $searchModel = SearchModel::make($request->query->all());

        if ($searchModel->isError())
        {
            return \JsonResponseView::newBadRequest($searchModel->getError())->toResponse();
        }

        $list        = $this->synthesisProviderStack->getVoices($searchModel->getSearch(), $provider);

        if (empty($list))
        {
            return \JsonResponseView::newNotFound()->toResponse();
        }

        return VoiceListResponse::make([
            'voices' => $this->addVoiceUri($searchModel->paginate($list)),
            'total'  => count($list),
            'page'   => $searchModel->getPage(),
            'limit'  => $searchModel->getLimit(),
        ])->toResponse();
    }

    #[OA\Get('/api/providers', 'Voice providers', description: 'Get voice providers list', tags: ['Speech Synthesis'], parameters: [
        new OA\HeaderParameter(name: 'X-Api-Key', allowEmptyValue: true),
    ])]
    #[OA\Response(
        response: 200,
        description: 'ok',
        content: new OA\MediaType(
            'application/json',
            schema: new OA\Schema(allOf: [
                new OA\Schema(SuccessResponse::class),
                new OA\Schema(properties: [
                    new OA\Property('providers', description: 'provider list', type: 'array', items: new OA\Items(type: 'string')),
                ]),
            ])
        )
    )]
    #[OA\Response(response: 403, description: 'Forbidden', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    public function getProviders(): JsonResponse
    {
        return SuccessResponse::make()->extend(['providers' => $this->synthesisProviderStack->listProviders()])
            ->toResponse();
    }

    #[OA\Get('/api/voice/{provider}/{lang}/{name}', 'Voice informations', description: 'Get voice informations', tags: ['Speech Synthesis'], parameters: [
        new OA\HeaderParameter(name: 'X-Api-Key', allowEmptyValue: true),
        new OA\PathParameter(name: 'provider', example: 'edge'),
        new OA\PathParameter(name: 'lang', example: 'en-US'),
        new OA\PathParameter(name: 'name', example: '0123456789'),
    ])]
    #[OA\Response(
        response: 200,
        description: 'ok',
        content: new OA\MediaType(
            'application/json',
            schema: new OA\Schema(allOf: [
                new OA\Schema(SuccessResponse::class),
                new OA\Schema(SpeechSynthesisVoice::class),
            ])
        )
    )]
    #[OA\Response(response: 403, description: 'Forbidden', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    public function getvoice(string $provider, string $lang, string $name): JsonResponse
    {
        $result = $this->synthesisProviderStack->getVoice($name, $lang, $provider);

        if ( ! $result)
        {
            return \JsonResponseView::newNotFound()->toResponse();
        }

        return SuccessResponse::make()->extend($this->addVoiceUri($result))->toResponse();
    }

    #[OA\Post('/api/speak', 'Generate Synthesis', description: 'Get all available voices', requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
        'application/json',
        schema: new OA\Schema(SpeechSynthesisUtterance::class)
    )), tags: ['Speech Synthesis'], parameters: [
        new OA\HeaderParameter(name: 'X-Api-Key', allowEmptyValue: true),
    ])]
    #[OA\Response(response: 200, description: 'ok', headers: [
        new OA\Header(header: 'X-Media-Duration', description: 'Duration in seconds', schema: new OA\Schema(), allowEmptyValue: true),
        new OA\Header(header: 'X-Media-Time', description: 'Duration in human readable form', schema: new OA\Schema(), allowEmptyValue: true),
    ], content: [
        new OA\MediaType('application/json', schema: new OA\Schema(allOf: [
            new OA\Schema(SuccessResponse::class),
            new OA\Schema(properties: [
                new OA\Property('provider', description: 'Provider name', type: 'string', example: 'edge', nullable: false),
                new OA\Property('voice', description: 'Voice name', nullable: false, oneOf: [new OA\Schema(SpeechSynthesisVoice::class)]),
                new OA\Property('seconds', description: 'Duration in seconds', type: 'float', example: 0.000001, nullable: false),
                new OA\Property('duration', description: 'Duration in time string', type: 'string', example: '00:00:00.000001', nullable: false),
                new OA\Property('mime', description: 'Mime type of the file', type: 'string', example: 'audio/x-wav', nullable: false),
                new OA\Property('identifier', description: 'File identifier', type: 'string', nullable: false),
                new OA\Property('url', description: 'URL to download the file', type: 'string', nullable: false),
                new OA\Property('expires_at', description: 'Expiry datetime', type: 'string', nullable: true),
            ]),
        ])),
        new OA\MediaType('audio/x-wav', schema: new OA\Schema()),
        new OA\MediaType('audio/x-mpeg', schema: new OA\Schema()),
    ])]
    #[OA\Response(response: 401, description: 'Unauthorized', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 500, description: 'Internal Error', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 403, description: 'Forbidden', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    public function speak(Request $request): Response
    {
        $utterance = SpeechSynthesisUtterance::make($this->decodeJsonBody($request, []));

        if ($utterance->isError())
        {
            return \JsonResponseView::newBadRequest($utterance->getError())->toResponse();
        }

        try
        {
            $result = $this->synthesisProviderStack->speak($utterance);

            $accept = $request->headers->get('Accept');

            if (str_contains($accept, 'application/json'))
            {
                $expires = $this->ttl ? date_create_immutable(date('Y-m-d H:i:s', time() + $this->ttl)) : null;

                return $this->getJsonResponse()->addAttributes([
                    'provider'   => $result->provider,
                    'voice'      => $this->addVoiceUri($this->synthesisProviderStack->getVoice($result->voice, $utterance->getLang(), $result->provider)),
                    'seconds'    => $result->duration,
                    'duration'   => $result->getHumanReadableDuration(),
                    'mime'       => $result->content_type,
                    'identifier' => $result->identifier,
                    'url'        => $this->generateUrl('download', ['identifier' => $result->identifier]),
                    'expires_at' => $expires->format(\DateTimeInterface::ATOM),
                ])->toResponse();
            }

            return $result->toFileResponseView()->toResponse();
        } catch (\Throwable $error)
        {
            $this->logError($error);
            return \JsonResponseView::newNotFound()->toResponse();
        }
    }

    #[OA\Get('/api/speak/download/{identifier}', 'Download File', description: 'Download speech synthesis file', tags: ['Speech Synthesis'])]
    #[OA\Response(response: 200, description: 'file to download', content: [
        new OA\MediaType('audio/x-wav', schema: new OA\Schema()),
        new OA\MediaType('audio/x-mpeg', schema: new OA\Schema()),
    ])]
    #[OA\Response(response: 403, description: 'Forbidden', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 404, description: 'Not Found', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    public function download(string $identifier): Response
    {
        $response = $this->synthesisProviderStack->getFile($identifier);

        if ( ! $response)
        {
            return \JsonResponseView::newNotFound()->toResponse();
        }
        return $response->toResponse();
    }

    /**
     * @param null|SpeechSynthesisVoice|SpeechSynthesisVoice[] $voices
     *
     * @return null|array|SpeechSynthesisVoice
     */
    private function addVoiceUri(array|SpeechSynthesisVoice|null $voices): array|SpeechSynthesisVoice|null
    {
        if ( ! $voices)
        {
            return null;
        }

        $many = true;

        if ( ! is_array($voices))
        {
            $voices = [$voices];
            $many   = false;
        }

        foreach ($voices as $voice)
        {
            $uri      = $voice->getVoiceUri();
            $params   = parse_url($uri);
            $provider = $params['scheme'] ?? '';
            $name     = $params['host']   ?? $voice->getName();
            $lang     = $voice->getLang();

            if ($provider && $name)
            {
                $voice->setVoiceInfoUri(
                    $this->generateUrl(
                        'voice',
                        compact('name', 'provider', 'lang')
                    )
                );
            }

            $voice->setVoiceUri($this->generateUrl('speak'));
        }
        return $many ? $voices : array_shift($voices);
    }
}
