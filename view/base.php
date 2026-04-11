<!DOCTYPE html>
<html lang="<?= __('app_lang') ?>">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? '' ?></title>
    <base href="<?= $base_path ?? '' ?>/">
    <link rel="shortcut icon" href="<?= asset('favicon.ico') ?>">
    <?= ($meta_block ?? '') . ($preload_block ?? '') . ($styles_block ?? '') . ($head_block ?? '') . ($vite_block ?? '') ?>
</head>
<body class="">
<?= $content ?? '' ?>
<?= ($scripts_block ?? '') ?>
</body>
</html>
