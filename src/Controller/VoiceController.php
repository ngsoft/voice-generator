<?php

namespace Controller;

use Model\SpeechSynthesisVoice;
use Renderer\BaseResponse;
use Renderer\JsonResponse;
use Symfony\Component\Process\Process;

class VoiceController
{
    private string $voiceRepository;

    public function __construct()
    {
        $this->voiceRepository = \Globals::resolvePath(\Env::getItem('VOICE_REPOSITORY'));
    }

    public function playVoice(\Request $request): \Response
    {
        if (\Env::getItem('PIPER_ENABLED'))
        {
            if (
                ! str_contains($request->getHeader('Content-Type'), '/json')
                || ! $request->getParameter('text'))
            {
                return JsonResponse::newBadRequest();
            }

            $text  = str_replace('"', '', $request->getParameter('text'));

            $path  = $request->getParameter('path');
            $id    = $request->getParameter('id');
            $file  = sprintf('%s/%s.onnx', $this->voiceRepository, $path);
            $uuid  = generate_uid();
            $dest  = str_replace(DIRECTORY_SEPARATOR, '/', \Globals::resolvePath("%tmp_path%/{$uuid}.wav"));

            $piper = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                \Globals::resolvePath('%project_root%/bin/amd64_linux/piper/piper')
            );

            // echo doesn't work well with JSON
            $cmd   = sprintf(
                'echo %s | "%s" -m "%s" -s %d -f "%s" ',
                $text,
                $piper,
                $file,
                $id,
                $dest,
            );

            if ('\\' === \DIRECTORY_SEPARATOR)
            {
                $piper = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    \Globals::resolvePath('%project_root%/bin/amd64_windows/piper/piper.exe')
                );

                // windows needs JSON with an escaped Unicode sequence
                $cmd   = sprintf(
                    'echo %s | "%s" --json-input -m "%s" -s %d -f "%s" ',
                    json_encode(['text' => $text]),
                    $piper,
                    $file,
                    $id,
                    $dest,
                );
            }

            try
            {
                @umask(0);
                @mkdir(dirname($dest), 0777, true);

                $proc = Process::fromShellCommandline($cmd);

                $proc->run();

                if ($proc->isSuccessful() && is_file($dest))
                {
                    return BaseResponse::newResponse()
                        ->setContent(@file_get_contents($dest))
                        ->addHeader('Access-Control-Allow-Origin', '*')
                        ->addHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                        ->setHeader('Content-Type', 'audio/x-wav')
                        ->setHeader('Content-Disposition', sprintf('inline; filename="%s"', basename($dest)));
                }
            } catch (\Throwable $exception)
            {
                \ApplicationLogger::getLogger()->error('piper error: %s', [$exception->getMessage()]);
                return JsonResponse::newInternalError();
            } finally
            {
                // we don't need the file anymore as it has already been loaded in the response as content
                @unlink($dest);
            }
        }

        return JsonResponse::newNotFound();
    }

    public function getAllVoices(): array
    {
        $root  = $this->voiceRepository;

        $files = getFileList($root, '.onnx.json');

        $all   = [];

        if (\Env::getItem('PIPER_ENABLED'))
        {
            foreach ($files as $file)
            {
                $all = [...$all, ...SpeechSynthesisVoice::fromOnnxJson($file)];
            }
        }

        return array_map(fn (SpeechSynthesisVoice $item) => $item->setVoiceUri(
            '/voice/speak' . $item->getVoiceUri()
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
            $add = true;

            if ($lang && ! str_contains(strtolower($entity->getLang()), strtolower($lang)))
            {
                $add = false;
            }

            if ($add)
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
