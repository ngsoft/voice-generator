<main>
    <div class="container-md mx-auto my-20 text-center select-none">
        <div class="text-slate-400 text-[10rem] font-bold"><?= $code ?? 500 ?></div>
        <div
            class="text-red-500 mb-8 font-bold text-lg"><?= isset($reason) ? __($reason) : __("Internal Server Error") ?></div>
        <div class="text-gray-400 text-sm font-light"><?= __("Sorry, something went wrong") ?> :(</div>
    </div>
</main>
<?php
extend('vite');
title(isset($reason) ? __($reason) : __("Internal Server Error"));
status_code($code ?? 500);
