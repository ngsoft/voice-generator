<?php

/**
 * OpenApi metadata.
 */
$translator = Services::getTranslator();

Config::setMany([
    'openapi.info.title'       => $translator->translate('app_title'),
    'openapi.info.description' => $translator->translate('app_description'),
    'openapi.info.version'     => $translator->translate('app_version'),
    'openapi.licence'          => 'MIT',
    'openapi.licence.url'      => 'https://mit-license.org/2023/license.txt',
    'openapi.paths'            => [
        // load request|response dtos metadata (class names can be used as schema refs)
        resolve_path('%project_root%/src/View'), resolve_path('%project_root%/src/Model'),
        // load metadata from controllers
        resolve_path('%project_root%/src/Controller'),
    ],
]);
