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

#[OA\Tag(name: 'Speech Synthesis', description: 'List voices, synthesize speech, and download generated audio.')]
#[OA\SecurityScheme(
    securityScheme: 'ApiKeyAuth',
    type: 'apiKey',
    name: 'X-Api-Key',
    in: 'header',
    description: 'API key from API_KEY. Required when API_KEY is set. Same-origin Authorization: Bearer may override via web session.'
)]
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

    #[OA\Get(
        path: '/api/voices',
        operationId: 'listVoices',
        summary: 'List Voices',
        description: 'List available speech synthesis voices, with optional locale, provider, and pagination filters. Protected by GLOBAL_ACL and X-Api-Key (API_KEY) when configured.',
        security: [['ApiKeyAuth' => []]],
        tags: ['Speech Synthesis'],
        parameters: [
            new OA\HeaderParameter(name: 'X-Api-Key', description: 'API key (API_KEY). Required when API_KEY is set.', required: false, allowEmptyValue: true, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'search', description: 'Filter voices by locale or name substring', required: false, allowEmptyValue: true, schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'limit', description: 'Number of results per page', required: false, allowEmptyValue: true, schema: new OA\Schema(type: 'integer', example: 10, minimum: 1)),
            new OA\QueryParameter(name: 'page', description: 'Page number (1-based)', required: false, allowEmptyValue: true, schema: new OA\Schema(type: 'integer', example: 1, minimum: 1)),
            new OA\QueryParameter(name: 'provider', description: 'Filter by provider name (e.g. edge)', required: false, allowEmptyValue: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'ok',
        content: new OA\MediaType(
            'application/json',
            schema: new OA\Schema(VoiceListResponse::class)
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized (missing/invalid X-Api-Key)', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 500, description: 'Internal Error', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 403, description: 'Forbidden (GLOBAL_ACL)', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
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

        $list        = $this->filterAndSortVoices($list, env_get('LANG_FILTER', '', false));

        return VoiceListResponse::make([
            'voices' => $this->addVoiceUri($searchModel->paginate($list)),
            'total'  => count($list),
            'page'   => $searchModel->getPage(),
            'limit'  => $searchModel->getLimit(),
        ])->toResponse();
    }

    #[OA\Get(
        path: '/api/providers',
        operationId: 'listProviders',
        summary: 'List providers',
        description: 'List configured speech synthesis providers and their descriptions. Protected by GLOBAL_ACL and X-Api-Key (API_KEY) when configured.',
        security: [['ApiKeyAuth' => []]],
        tags: ['Speech Synthesis'],
        parameters: [
            new OA\HeaderParameter(name: 'X-Api-Key', description: 'API key (API_KEY). Required when API_KEY is set.', required: false, allowEmptyValue: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'ok',
        content: new OA\MediaType(
            'application/json',
            schema: new OA\Schema(allOf: [
                new OA\Schema(SuccessResponse::class),
                new OA\Schema(properties: [
                    new OA\Property('providers', description: 'provider list', type: 'array', items: new OA\Items(properties: [
                        new OA\Property('name', description: 'provider name', type: 'string'),
                        new OA\Property('description', description: 'provider description', type: 'string'),
                    ], type: 'object')),
                ]),
            ])
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized (missing/invalid X-Api-Key)', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 403, description: 'Forbidden (GLOBAL_ACL)', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    public function getProviders(): JsonResponse
    {
        $providers = [];

        foreach ($this->synthesisProviderStack->listProviders() as $name => $description)
        {
            $providers[] = compact('name', 'description');
        }
        return SuccessResponse::make()->extend(compact('providers'))
            ->toResponse();
    }

    #[OA\Get(
        path: '/api/voice/{provider}/{lang}/{name}',
        operationId: 'getVoice',
        summary: 'Voice informations',
        description: 'Get details for a single voice identified by provider, language, and name. Protected by GLOBAL_ACL and X-Api-Key (API_KEY) when configured.',
        security: [['ApiKeyAuth' => []]],
        tags: ['Speech Synthesis'],
        parameters: [
            new OA\HeaderParameter(name: 'X-Api-Key', description: 'API key (API_KEY). Required when API_KEY is set.', required: false, allowEmptyValue: true, schema: new OA\Schema(type: 'string')),
            new OA\PathParameter(name: 'provider', description: 'Voice provider name', required: true, schema: new OA\Schema(type: 'string'), example: 'edge'),
            new OA\PathParameter(name: 'lang', description: 'BCP 47 language tag of the voice', required: true, schema: new OA\Schema(type: 'string'), example: 'en-US'),
            new OA\PathParameter(name: 'name', description: 'Voice name / identifier', required: true, schema: new OA\Schema(type: 'string'), example: 'en-US-AvaMultilingualNeural'),
        ]
    )]
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
    #[OA\Response(response: 401, description: 'Unauthorized (missing/invalid X-Api-Key)', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 403, description: 'Forbidden (GLOBAL_ACL)', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
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

    #[OA\Post(
        path: '/api/speak',
        operationId: 'generateSynthesis',
        summary: 'Generate Synthesis',
        description: 'Synthesize speech from text. With Accept: application/json, returns metadata and a download URL; otherwise returns the audio file. Protected by GLOBAL_ACL and X-Api-Key (API_KEY) when configured.',
        security: [['ApiKeyAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            'application/json',
            schema: new OA\Schema(SpeechSynthesisUtterance::class)
        )),
        tags: ['Speech Synthesis'],
        parameters: [
            new OA\HeaderParameter(name: 'X-Api-Key', description: 'API key (API_KEY). Required when API_KEY is set.', required: false, allowEmptyValue: true, schema: new OA\Schema(type: 'string')),
            new OA\HeaderParameter(name: 'Accept', description: 'Prefer application/json for metadata, or an audio/* type for the binary file', required: false, schema: new OA\Schema(type: 'string', example: 'application/json')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Synthesized audio metadata (JSON) or binary audio file', headers: [
        new OA\Header(header: 'X-Media-Duration', description: 'Duration in seconds', schema: new OA\Schema(type: 'number', format: 'float'), allowEmptyValue: true),
        new OA\Header(header: 'X-Media-Time', description: 'Duration in human-readable form (HH:MM:SS.microseconds)', schema: new OA\Schema(type: 'string'), allowEmptyValue: true),
    ], content: [
        new OA\MediaType('application/json', schema: new OA\Schema(allOf: [
            new OA\Schema(SuccessResponse::class),
            new OA\Schema(properties: [
                new OA\Property('provider', description: 'Provider name', type: 'string', example: 'edge', nullable: false),
                new OA\Property(property: 'voice', ref: SpeechSynthesisVoice::class, description: 'Voice details'),
                new OA\Property('seconds', description: 'Duration in seconds', type: 'number', format: 'float', example: 0.000001, nullable: false),
                new OA\Property('duration', description: 'Duration in time string', type: 'string', example: '00:00:00.000001', nullable: false),
                new OA\Property('mime', description: 'Mime type of the file', type: 'string', example: 'audio/x-wav', nullable: false),
                new OA\Property('identifier', description: 'File identifier', type: 'string', nullable: false),
                new OA\Property('url', description: 'URL to download the file', type: 'string', nullable: false),
                new OA\Property('expires_at', description: 'Expiry datetime', type: 'string', nullable: true),
            ]),
        ])),
        new OA\MediaType('audio/x-wav', schema: new OA\Schema(type: 'string', format: 'binary')),
        new OA\MediaType('audio/x-mpeg', schema: new OA\Schema(type: 'string', format: 'binary')),
    ])]
    #[OA\Response(response: 401, description: 'Unauthorized (missing/invalid X-Api-Key)', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 500, description: 'Internal Error', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 400, description: 'Bad Request', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
    #[OA\Response(response: 403, description: 'Forbidden (GLOBAL_ACL)', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
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
                    'expires_at' => $expires?->format(\DateTimeInterface::ATOM),
                ])->toResponse();
            }

            return $result->toFileResponseView()->toResponse();
        } catch (\Throwable $error)
        {
            $this->logError($error);
            return \JsonResponseView::newNotFound()->toResponse();
        }
    }

    #[OA\Get(
        path: '/api/speak/download/{identifier}',
        operationId: 'downloadFile',
        summary: 'Download File',
        description: 'Download a previously generated speech synthesis audio file by its identifier. Protected by GLOBAL_ACL only (no X-Api-Key).',
        security: [],
        tags: ['Speech Synthesis'],
        parameters: [
            new OA\PathParameter(name: 'identifier', description: 'File identifier returned by POST /api/speak', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Audio file to download', content: [
        new OA\MediaType('audio/x-wav', schema: new OA\Schema(type: 'string', format: 'binary')),
        new OA\MediaType('audio/x-mpeg', schema: new OA\Schema(type: 'string', format: 'binary')),
    ])]
    #[OA\Response(response: 403, description: 'Forbidden (GLOBAL_ACL)', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
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
     * @param SpeechSynthesisVoice[] $voices
     * @param string                 $filter
     *
     * @return array
     */
    public function filterAndSortVoices(array $voices, string $filter = ''): array
    {
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

        $byLang  = [];

        foreach ($voices as $voice)
        {
            $lang  = $voice->getLang();
            $lower = strtolower($lang);
            $ok    = empty($filters) || array_any($filters, fn (string $filter) => str_contains($lower, $filter));

            if ($ok)
            {
                $byLang[$lang] ??= [];
                $byLang[$lang][] = $voice;
            }
        }
        ksort($byLang);

        $result  = [];

        foreach ($byLang as $list)
        {
            $result = array_merge($result, $list);
        }

        return $result;
    }

    /**
     * @param null|SpeechSynthesisVoice|SpeechSynthesisVoice[] $voices
     *
     * @return null|SpeechSynthesisVoice|SpeechSynthesisVoice[]
     *
     * @psalm-return ($voices is SpeechSynthesisVoice[] ? SpeechSynthesisVoice[] : SpeechSynthesisVoice|null)
     */
    public function addVoiceUri(array|SpeechSynthesisVoice|null $voices): array|SpeechSynthesisVoice|null
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
