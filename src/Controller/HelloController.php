<?php

namespace Controller;

use Interfaces\ActionInterface;
use OpenApi\Attributes as OA;
use Record\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use View\ErrorResponse;
use View\HelloResponse;

#[OA\Tag('Hello World', 'says hello')]
#[OA\Get('/api/hello/{name}', '/api/hello', description: 'says hello', tags: ['Hello World'], parameters: [
    new OA\HeaderParameter(name: 'X-Api-Key', allowEmptyValue: true),
    new OA\PathParameter(name: 'name', description: 'Name to be displayed', required: false),
    new OA\QueryParameter(name: 'name', description: 'Name to be displayed', allowEmptyValue: true),
])]
#[OA\Response(response: 200, description: 'ok', content: new OA\MediaType('application/json', schema: new OA\Schema(HelloResponse::class)))]
#[OA\Response(response: 401, description: 'Unauthorized', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
#[OA\Response(response: 500, description: 'Internal Error', content: new OA\MediaType('application/json', schema: new OA\Schema(ErrorResponse::class)))]
class HelloController extends BaseController implements ActionInterface
{
    private ?string $name = null;

    public function hello(Request $request, ?string $name = null): Response
    {
        $this->name = $name;
        return $this->execute($request)->toResponse();
    }

    public function execute(Request $request): \ResponseView
    {
        if (str_starts_with($request->getPathInfo(), '/api/'))
        {
            check_api_key($request);
        }

        $name = $this->name ?: __('world');

        $name = mb_convert_case(
            urldecode(trim(str_replace(
                '/',
                ' ',
                $request->query->get('name', $name)
            ))),
            MB_CASE_TITLE
        );

        /** @var ?User $user */
        $user = null;

        try
        {
            $user = User::findOne(['login LIKE ?' => $name]) ?? User::findOne(['full_name LIKE ?' => $name]);
        } catch (\Throwable $exception)
        {
            $this->logError($exception, $this->getLogger());
        }

        if ($user)
        {
            $name = $user?->getFullName() ?? $name;
        }

        if ( ! str_starts_with($request->getPathInfo(), '/api/'))
        {
            return $this->render('page/hello', compact('name', 'user'));
        }

        return $this->json(HelloResponse::make([
            'message'  => __('Hello') . " {$name}!",
            'page_url' => $this->generateUrl('hello', ['name' => $this->name ?? '']),
            'url'      => $this->generateUrl('api_hello', ['name' => $this->name ?? '']),
        ])->setUser($user?->addMeta('test', 42)));
    }
}
