<?php
title(__('Welcome'));
vite('app/app.ts');
?>

<div class="card w-full preset-filled-surface-100-900 p-10 text-center space-y-12">
    <h1 class="h1"><?= __('Welcome to your website'); ?></h1>
    <p class=""><?= str_format(__('You can edit the file <code class="code">%s</code> to modify that page.'), [__FILE__]); ?></p>

    <p class="flex items-center justify-center mb-4"><?= __('That server is powered by'); ?> <img
            class="inline-block h-6" alt="Vite"
            src="<?= asset('pictures/vite.svg'); ?>">ite
    </p>

    <button type="button" class="btn preset-filled-primary-500"
            onclick="mountDemo()"><?= __('Mount svelte Demo'); ?></button>

</div>
<script>

    function mountDemo() {
        document.querySelector('main').innerHTML = `<div id="app"></div>`;
    }
</script>

