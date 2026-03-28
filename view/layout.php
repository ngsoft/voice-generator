<?php
extend('base');
vite('app/app.css');
foreach (Config::getItem('page.styles') as $style) {
    style(asset($style), true);
}
foreach (Config::getItem('page.scripts') as $script) {
    script(asset($script), false, true);
}
echo $content ?? '';
