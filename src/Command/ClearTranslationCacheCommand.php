<?php

namespace Command;

use NGSOFT\Console\Profile\CommandHelper;
use Service\LoggerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Traits\CommandTrait;

#[AsCommand('translator:cache:clear', 'Clear translation cache')]
class ClearTranslationCacheCommand extends Command
{
    use CommandTrait;

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        $code = Command::SUCCESS;
        $root = resolve_path('%var%/lang');
        $this->logAndPrint('Removing translation cache from %s', [$root]);

        foreach (iterate_files($root) as $info)
        {
            if (preg_match('#\.php(\.meta|\.meta\.json)?$#', $info->getFilename()))
            {
                if (@unlink($info->getPathname()))
                {
                    $this->logAndPrint('Removed ' . $info->getFilename());
                } else
                {
                    $code = Command::FAILURE;
                    $this->logAndPrint('Could not delete ' . $info->getFilename(), level: LoggerService::ERR);
                }
            }
        }

        if ( ! $code)
        {
            $io->success('Translation cache cleared');
        }

        return $code;
    }
}
