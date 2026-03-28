<?php

// env vars

return [
    'app_lang' => 'en',
    'app_env' => env_get('APP_ENV'),
    'app_id' => env_get('APP_ID'),
    'app_version' => env_get('APP_VERSION'),
    'app_title' => env_get('APP_TITLE', 'Your site title'),
    'app_description' => env_get('APP_DESCRIPTION', 'Your site description'),

];
