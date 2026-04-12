<div class="theme-selector cursor-pointer" data-value="auto">
    <span class="sr-only"><?= __('Select your color mode') ?></span>
    <div class="tooltip" data-value="auto">
        <button role="radio" name="dark-mode-value" value="off"
                class="tooltip-toggle btn btn-outline btn-secondary btn-sm btn-square wave waves-light">
            <span class="icon-[tabler--brightness-filled] size-5"></span>
        </button>
        <span class="tooltip-content tooltip-shown:opacity-100 tooltip-shown:visible" role="tooltip">
            <span class="tooltip-body"><?= __("System theme") ?></span>
        </span>
    </div>

    <div class="tooltip" data-value="on">
        <button role="radio" name="dark-mode-value" value="auto"
                class="tooltip-toggle btn btn-outline btn-secondary btn-sm btn-square wave waves-light">
            <span class="icon-[tabler--moon] size-5"></span>
        </button>
        <span class="tooltip-content tooltip-shown:opacity-100 tooltip-shown:visible" role="tooltip">
            <span class="tooltip-body"><?= __("Dark theme") ?></span>
        </span>
    </div>

    <div class="tooltip" data-value="off">
        <button role="radio" name="dark-mode-value" value="on"
                class="tooltip-toggle btn btn-outline btn-secondary btn-sm btn-square wave waves-light">
            <span class="icon-[tabler--sun] size-5"></span>
        </button>
        <span class="tooltip-content tooltip-shown:opacity-100 tooltip-shown:visible" role="tooltip">
            <span class="tooltip-body"><?= __("Light theme") ?></span>
        </span>
    </div>

</div>

