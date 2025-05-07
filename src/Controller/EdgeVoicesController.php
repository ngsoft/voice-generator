<?php

/** @noinspection PhpClassCanBeReadonlyInspection */

namespace Controller;

use Afaya\EdgeTTS\Service\EdgeTTS;
use Model\SpeechSynthesisVoice;
use Renderer\BaseResponse;
use Renderer\JsonResponse;

class EdgeVoicesController
{
    public function __construct(
        private readonly EdgeTTS $edgeTTS,
    ) {}

    public function playVoice(\Request $request): \Response
    {
        if (
            ! str_contains($request->getHeader('Content-Type'), '/json')
            || ! $request->getParameter('text'))
        {
            return JsonResponse::newBadRequest();
        }
        $text = $request->getParameter('text');

        $path = $request->getParameter('path');
        $uuid = generate_uid();
        $dest = str_replace(DIRECTORY_SEPARATOR, '/', \Globals::resolvePath("%tmp_path%/{$uuid}"));
        $lame = "{$dest}.mp3";
        $pcm  = "{$dest}.wav";

        try
        {
            @umask(0);
            @mkdir(dirname($dest), 0777, true);
            $this->edgeTTS->synthesize($text, $path);
            $this->edgeTTS->toFile($dest);

            if (\Env::getItem('CONVERT_PCM') && ! \AudioConverter::convert($lame, $pcm))
            {
                @unlink($pcm);
            }

            if (is_file($pcm))
            {
                return BaseResponse::newResponse()
                    ->setContent(@file_get_contents($pcm))
                    ->addHeader('Access-Control-Allow-Origin', '*')
                    ->addHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                    ->setHeader('Content-Type', 'audio/x-wav')
                    ->setHeader(
                        'Content-Disposition',
                        sprintf('inline; filename="%s"', basename($pcm))
                    );
            }

            if (is_file($lame))
            {
                return BaseResponse::newResponse()
                    ->setContent(@file_get_contents($lame))
                    ->addHeader('Access-Control-Allow-Origin', '*')
                    ->addHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                    ->setHeader('Content-Type', 'audio/x-mpeg')
                    ->setHeader(
                        'Content-Disposition',
                        sprintf('inline; filename="%s"', basename($lame))
                    );
            }
        } catch (\Throwable $exception)
        {
            \ApplicationLogger::getLogger()->error('edge error: %s', [$exception->getMessage()]);
            return JsonResponse::newInternalError();
        } finally
        {
            if (\Env::getItem('REMOVE_AUDIO_FILES'))
            {
                // we don't need the file anymore as it has already been loaded in the response as content
                @unlink($lame);
                @unlink($pcm);
            }
        }

        return JsonResponse::newNotFound();
    }

    public function getAllVoices(): array
    {
        /** @var array{Name:string,ShortName:string,Gender:string,locale:string,FriendlyName:string}[] $voices */
        $voices = $this->edgeTTS->getVoices();

        $all    = [];

        foreach ($voices as $voice)
        {
            $all[] = (new SpeechSynthesisVoice())
                ->setName($voice['FriendlyName'])
                ->setLang(str_replace('-', '_', $voice['Locale']))
                ->setVoiceUri('/' . $voice['ShortName']);
        }

        return array_map(fn (SpeechSynthesisVoice $item) => $item->setVoiceUri(
            '/edge/voice/speak' . $item->getVoiceUri()
        ), $all);
    }

    public function listVoices(\Request $request): \Response
    {
        $lang   = $request->getParameter('lang');
        $result = [];

        /** @var SpeechSynthesisVoice $entity */
        foreach ($this->getAllVoices() as $entity)
        {
            $entity->setVoiceUri(
                $this->getVoiceUrl(
                    $request,
                    $entity->getVoiceUri()
                )
            );

            if ( ! ($lang && ! str_contains(strtolower($entity->getLang()), strtolower($lang))))
            {
                $result[] = $entity;
            }
        }

        return JsonResponse::newResponse()->setData($result);
    }

    protected function getVoiceUrl(\Request $request, string $uri): string
    {
        $https = ! empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']);

        if (\Env::getItem('FORCE_HTTPS', false))
        {
            $https = true;
        }

        return sprintf(
            '%s://%s%s%s',
            $https ? 'https' : 'http',
            $_SERVER['HTTP_HOST'],
            $request->getAttribute('basepath', ''),
            $uri
        );
    }
}
