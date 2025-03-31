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

        // echo don't work well with json
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

            // windows needs json with escaped Unicode sequence
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
                    ->setHeader('Content-Type', 'audio/x-wav')
                    ->setHeader('Content-Disposition', sprintf('inline; filename="%s"', basename($dest)));
            }
        } catch (\Throwable $exception)
        {
            \ApplicationLogger::getLogger()->error('piper error: %s', [$exception->getMessage()]);
            return JsonResponse::newInternalError();
        } finally
        {
            // we don't nee the file anymore as it has already been loaded in the response as content
            @unlink($dest);
        }

        return JsonResponse::newNotFound();
    }

    public function listVoices(\Request $request): \Response
    {
        $lang  = $request->getParameter('lang');

        $root  = $this->voiceRepository;

        $files = getFileList($root, '.onnx.json');

        $all   = $result = [];

        foreach ($files as $file)
        {
            $all = [...$all, ...SpeechSynthesisVoice::fromOnnxJson($file)];
        }

        /** @var SpeechSynthesisVoice $entity */
        foreach ($all as $entity)
        {
            $entity->setVoiceUri(
                $this->getVoiceUrl(
                    $request,
                    '/voice/speak'
                    . $entity->getVoiceUri()
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
        return sprintf(
            '%s://%s%s%s',
            ( ! empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS'])) ? 'https' : 'http',
            $_SERVER['HTTP_HOST'],
            $request->getAttribute('basepath', ''),
            $uri
        );
    }
}
