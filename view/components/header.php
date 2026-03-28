<span class="logo ps-4 capitalize"><?= trim($title ?? env_get('APP_TITLE', 'My App', false)); ?></span>
<div class="ms-auto select-none">
    <input id="dark-mode-switch" type="checkbox" role="switch" class="inset round mb-0">
    <label for="dark-mode-switch" class="font-medium">Dark Mode</label>
</div>
