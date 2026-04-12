<main>
    <div class="mx-auto flex flex-col items-center select-none">
        <div
            class="w-64 h-64 mx-auto sm:h-96 sm:w-96"><?= render_asset("assets/pictures/404.svg") ?></div>
        <h1 class="font-black text-7xl my-4"><?= __("Page not found") ?></h1>

        <p class="max-w-xl px-4 text-center mt-4">
            <?= __("Oops!! The page you're looking for doesn't exist on this site. Discover more") ?>
            <a class="underline" href="<?= path('app_index') ?>"><?= __("here") ?></a>
        </p>
    </div>
</main>
<?php
extend('site');
title(__("Not found"));
status_code(404);
