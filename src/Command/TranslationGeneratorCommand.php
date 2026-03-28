<?php

namespace Command;

use NGSOFT\Console\Profile\CommandHelper;
use Service\LoggerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Traits\CommandTrait;

#[AsCommand('translator:generate', 'Generate translations')]
class TranslationGeneratorCommand extends Command
{
    use CommandTrait;

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        static $re  = '#__\(["\'`](.+)["\'`]\)#iU';
        $outputFile = resolve_path('%project_root%/lang/messages.en.yml');
        $inputDirs  = [resolve_path('%project_root%/view'), resolve_path('%project_root%/src'), resolve_path('%project_root%/app')];
        $strings    = [];
        $cnt        = 0;

        foreach ($inputDirs as $dir)
        {
            foreach ($this->scanPhpFiles($dir) as $file)
            {
                if (str_contains(strtolower($file), 'jquery'))
                {
                    continue;
                }

                if (preg_match_all($re, @file_get_contents($file), $matches, PREG_PATTERN_ORDER))
                {
                    ++$cnt;

                    $this->logAndPrint(
                        'adding labels from %s%s',
                        [
                            basename($dir), substr($file, strlen($dir)),
                        ]
                    );

                    foreach ($matches[1] as $string)
                    {
                        $string           = stripcslashes($string);
                        $quote            = ! str_contains($string, "'") ? "'" : '"';

                        if ('"' === $quote && str_contains($string, $quote))
                        {
                            $string = str_replace($quote, '&quot;', $string);
                        }
                        $string           = $quote . $string . $quote;
                        $strings[$string] = $string;
                    }
                }
            }
        }
        $content    = '';

        foreach ($strings as $string)
        {
            $content .= sprintf('%s: %s', $string, $string) . "\n";
        }

        $ok         = @file_put_contents($outputFile, $content);

        if ( ! $ok)
        {
            $this->logAndPrint(
                'Cannot save lang/%s',
                [basename($outputFile)],
                LoggerService::ERR
            );
            return Command::FAILURE;
        }

        $io->success($message = str_format('Generated lang/%s (%d lines) from %d files.', [
            basename($outputFile), count($strings), $cnt,
        ]));
        $this->log($message);
        return Command::SUCCESS;
    }

    private function scanPhpFiles($dir)
    {
        $dir = normalize_path($dir);

        foreach (iterate_files($dir) as $file)
        {
            if (str_ends_with($file->getFilename(), '.php'))
            {
                yield normalize_path($file->getPathname());
            }

            if (str_ends_with($file->getFilename(), '.ts'))
            {
                yield normalize_path($file->getPathname());
            }
        }
    }
}
