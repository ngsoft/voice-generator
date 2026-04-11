<?php


require_once __DIR__ . '/../../../src/init.php';

$module = normalize_path(dirname(__DIR__, 3) . '/node_modules/flyonui/theme');

$theme = (object)[
    'template' => "%s {\n%s}",
    'prefix' => ':root:has(input.theme-controller[value={theme}]:checked),[data-theme="{theme}"]'
];

$files = iterate_files(resolve_path(__DIR__, 'themes'));

$export_file = resolve_path(__DIR__, 'index.css');
$exports = [];

foreach ($files as $file) {

    if ('css' !== $file->getExtension()) {
        continue;
    }
    $path = normalize_path($file->getPathname());
    $name = preg_replace('#\.\w+$#', '', $file->getFilename());


    $exists = resolve_path($module, $file->getFilename());

    if (is_file($exists)) {
        @unlink($path);
        printf("removed %s\n", $file->getFilename());
        continue;
    }

    $exports[] = sprintf("@import '%s';", './themes/' . $file->getFilename());
    $content = file_get_contents($path);
    $prefix = str_format($theme->prefix, ['theme' => $name]);
    if (str_contains($content, $prefix)) {
        printf("ignoring %s\n", $file->getFilename());
        continue;
    }

    $new = sprintf($theme->template, $prefix, $content);
    @file_put_contents($path, $new);
}

@file_put_contents($export_file, implode("\n", $exports));
