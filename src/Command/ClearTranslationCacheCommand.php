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

    public function clearCache(bool $log = true): bool
    {
        $ok   = true;
        $root = resolve_path('%var%/lang');

        foreach (iterate_files($root) as $info)
        {
            if (preg_match('#\.php(\.meta|\.meta\.json)?$#', $info->getFilename()))
            {
                if (@unlink($info->getPathname()))
                {
                    $log && $this->logAndPrint('Removed ' . $info->getFilename());
                } else
                {
                    $ok = false;
                    $log && $this->logAndPrint('Could not delete ' . $info->getFilename(), level: LoggerService::ERR);
                }
            }
        }

        return $ok;
    }

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        $this->logAndPrint('Removing translation cache');
        $code = $this->clearCache() ? self::SUCCESS : self::FAILURE;

        if ( ! $code)
        {
            $io->success('Translation cache cleared');
        }

        return $code;
    }
}
