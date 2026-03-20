<?php

use Command\ClearTranslationCacheCommand;
use Command\HelloCommand;
use Command\TranslationGeneratorCommand;
use NGSOFT\Console\ConsoleApplication;

/**
 * Register your commands there.
 */
return function (ConsoleApplication $app)
{
    $app->add(HelloCommand::class);
    $app->add(TranslationGeneratorCommand::class);
    $app->add(ClearTranslationCacheCommand::class);
};
