<nav class="navbar rounded-box shadow-base-300/20 shadow-sm">
    <div class="w-full md:container md:mx-auto md:flex md:items-center md:gap-2">
        <div class="flex items-center justify-between">
            <div class="navbar-start items-center justify-between max-md:w-full min-w-60">
                <a class="link text-xl font-bold no-underline capitalize"
                   href="<?= url('app_index') ?>"><?= __('app_title') ?></a>
                <div class="flex md:hidden gap-2">
                    <?= include_view('components/color-theme') ?>
                    <?php if ($display_nav ?? false): ?>
                        <div class="tooltip">
                            <button type="button"
                                    class="collapse-toggle tooltip-toggle btn btn-outline btn-secondary btn-sm btn-square"
                                    data-collapse="#default-navbar-collapse" aria-controls="default-navbar-collapse"
                                    aria-label="Toggle navigation">
                                <span class="icon-[tabler--menu-2] collapse-open:hidden size-4"></span>
                                <span class="icon-[tabler--x] collapse-open:block hidden size-4"></span>
                            </button>
                            <span class="tooltip-content tooltip-shown:opacity-100 tooltip-shown:visible"
                                  role="tooltip">
                            <span class="tooltip-body"><?= __("Menu") ?></span>
                        </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div id="default-navbar-collapse"
             class="md:navbar-end collapse hidden grow basis-full overflow-hidden transition-[height] duration-300 max-md:w-full max-md:flex-col-reverse md:items-center">
            <?php if ($display_nav ?? false): ?>
                <ul class="menu md:menu-horizontal gap-2 p-0 text-base max-md:mt-2 md:me-4">
                    <li><a href="<?= url('app_index') ?>"><?= __('Speech Synthesis Player') ?></a></li>
                    <li><a href="<?= url('api_doc') ?>"><?= __('Api Documentation') ?></a></li>
                </ul>
            <?php endif; ?>
            <div class="max-md:hidden"><?= include_view('components/color-theme') ?></div>
        </div>
    </div>
</nav>

