import { environment } from '@';
import { __ } from '$packages/http';
import { noop, sprintf } from '$sdk';

/**
 * @see https://daisyui.com/components/modal/
 */
let counter = 0;

const template = {
    root: `<dialog id="dialog-modal-%d" class="modal">%s</dialog>`,
    body: `<div class="modal-box select-none p-4 min-h-30">%s</div><form method="dialog" class="modal-backdrop"><button type="reset" name="close" value="false">close</button></form>`,
    title: `<h3 class="text-xl font-bold px-5 mb-6">%s</h3>`,
    message: `<div class="py-4 px-5 my-2 text-center text-lg">%s</div>`,
    action: `<div class="modal-action"><form method="dialog" class="flex gap-x-4 items-center flex-row-reverse">%s</form></div>`,
    confirm: `<button type="submit" name="close" value="true" class="btn btn-primary" tabindex="0">%s</button>`,
    cancel: `<button type="reset" name="close" value="false" class="btn btn-secondary">%s</button>`,
    close: `<form method="dialog"><button type="reset" name="close" value="false" class="btn btn-text btn-circle btn-sm absolute inset-e-3 top-3"><span class="icon-[tabler--x] size-4"></span></button></form>`,
};

type ModalOptions = {
    title?: string;
    message: string;
    confirm?: (() => void) | boolean;
    cancel?: (() => void) | boolean;
    outside?: boolean;
};

type ModalResult = {
    confirmed: boolean;
    result: string;
    dialog: HTMLDialogElement;
};

const defaults: Partial<ModalOptions> = {
    confirm: true,
    cancel: false,
    outside: true,
};

async function buildModal(options: ModalOptions): Promise<HTMLDialogElement> {
    const message = await __(options.message),
        title = await __(options.title ?? ''),
        content = sprintf(template.title, title) + sprintf(template.message, message),
        confirm = sprintf(template.confirm, await __('Confirm')),
        cancel = options.cancel ? sprintf(template.cancel, await __('Cancel')) : '',
        action = sprintf(template.action, confirm + cancel),
        box = sprintf(template.body, content + action + template.close);
    return $(sprintf(template.root, ++counter, box)).get(0) as HTMLDialogElement;
}

export function displayModal(message: string | ModalOptions): Promise<ModalResult> {
    return new Promise((resolve) => {
        (async () => {
            const options: ModalOptions = { ...defaults, ...('string' === typeof message ? { message } : message) },
                dialog = await buildModal(options),
                result: ModalResult = {
                    dialog,
                    confirmed: false,
                    result: 'false',
                },
                onConfirm = 'function' === typeof options.confirm ? options.confirm : noop,
                onCancel = 'function' === typeof options.cancel ? options.cancel : noop;

            $(dialog)
                .on('submit reset', (event) => {
                    event.preventDefault();

                    const form = (event.target as HTMLElement).closest('form');

                    if (!options.outside && $(form).hasClass('modal-backdrop')) {
                        return;
                    }

                    if ('submit' === event.type) {
                        result.confirmed = true;
                        result.result = 'true';
                        const inputValue = $(dialog).find('[name="result"]').val();
                        if (inputValue) {
                            result.result = inputValue;
                        }
                    }
                    dialog.close(result.result);
                })
                .on('close', () => {
                    resolve(result);
                    result.confirmed ? onConfirm() : onCancel();
                });

            environment.document.body.appendChild(dialog);
            options.outside ? dialog.showModal() : dialog.show();
        })();
    });
}
