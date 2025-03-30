<!doctype html>
<html lang="<?= $locale ??= 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= trim($title ?? Env::getItem('APP_TITLE', 'My App')); ?></title>
    <meta name="description" content="<?= Env::getItem('APP_DESCRIPTION', 'My App'); ?>">
    <?= ($stylesheets ?? '') . ($head ?? ''); ?>
</head>
<?php
$contents               ??= '';

if (str_contains($contents, '<body')):
    echo $contents;
else : ?>
    <body>
    <?= ($heading ?? '') . $contents . ($footer ?? '') . ($scripts ?? ''); ?>
    </body>
<?php endif; ?>
</html>
