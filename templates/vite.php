<?php
extends_template('layout');
[$jsList, $cssList, $server] = getViteEntryPoints();
$app                                    ??= [];
$base                                   ??= '';
/* @block head */
ob_start(); ?>
<link rel="icon" href="<?= $app['icon'] ??= "{$base}/favicon.svg"; ?>">
<?php

// preload
foreach ($cssList as $uri) :?>
    <link rel="preload stylesheet" href="<?= $uri; ?>" as="style" media="all" crossorigin>
<?php endforeach;

foreach ($jsList as $uri) :?>
    <link rel="modulepreload" href="<?= $uri; ?>" as="script" crossorigin>
<?php endforeach;

foreach ($jsList as $uri) :?>
    <script type="module" src="<?= $uri; ?>"></script>
<?php endforeach;

echo $head    ?? '';
set_attr('head', ob_get_clean());
/* @block body */ ?>
<body data-route="<?= $route            ??= '/'; ?>">
<?= $contents ?? ''; ?>
<script>
    function viteScriptError() {
        document.body.innerHTML = `<h1>Vite isn't loaded</h1>
        <p>You did not build your vite application or did not launch vite server.</p>`;
    }
    <?php if(empty($jsList)):?>
    viteScriptError();
    <?php endif; ?>
</script>
<?php if ($server) : ?>
    <script type="module" src="<?= $server; ?>/build/@vite/client" onerror="viteScriptError()"></script>
<?php endif; ?>
</body>
