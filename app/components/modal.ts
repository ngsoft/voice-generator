import { __ } from '$packages/http';

let counter = 0;

export async function showModal(
    message:
        | string
        | {
              message: string;
              title?: string;
              confirm?: () => void;
              cancel?: (() => void) | boolean;
          }
): Promise<HTMLDivElement> {
    ++counter;

    const options: {
        message: string;
        title: string;
        confirm?: (() => void) | boolean;
        cancel?: (() => void) | boolean;
    } = {
        message: '',
        title: '',
        confirm: true,
        cancel: false,
    };

    if ('string' === typeof message) {
        options.message = message;
    } else {
        options.message = message.message;
        options.title = message.title ?? '';
        options.confirm = message.confirm ?? true;
        options.cancel = message.cancel ?? true;
    }

    options.message = await __(options.message);
    options.title = await __(options.title);

    const footer = $(`<footer class="mt-6 flex justify-end gap-2"></footer>`).get(0);

    if (options.cancel) {
        const label = await __('Cancel'),
            cancel = $(
                `<button type="button" class="btn px-4 py-2 preset-tonal-secondary dark:text-white rounded">${label}</button>`
            ).get(0);
        $(cancel).on('click keydown', (event: Event) => {
            if (event instanceof KeyboardEvent && event.key !== ' ' && event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            elem.remove();
            if ('function' === typeof options.cancel) {
                options.cancel();
            }
        });
        footer.appendChild(cancel);
    }

    if (options.confirm) {
        const label = await __('Confirm'),
            confirm = $(
                `<button type="button" class="btn px-4 py-2 preset-tonal-secondary dark:text-white rounded">${label}</button>`
            ).get(0);
        $(confirm).on('click keydown', (event: Event) => {
            if (event instanceof KeyboardEvent && event.key !== ' ' && event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            elem.remove();
            if ('function' === typeof options.confirm) {
                options.confirm();
            }
        });
        footer.appendChild(confirm);
    }

    const html = `<div class="fixed inset-0 z-50 grid place-content-center bg-black/50 p-4" role="dialog" id="modal${counter}" aria-modal="true" aria-labelledby="modalTitle${counter}">
        <div class="modal-body w-full max-w-md min-w-sm rounded-lg preset-tonal-surface p-6 pb-2 shadow-lg">
            <div class="flex items-start justify-between">
                <div id="modalTitle${counter}" class="text-xl font-bold h2">${options.title}</div>
                <button type="button" class="modal-closer -me-4 -mt-4 rounded-full p-2 text-gray-400 transition-colors hover:bg-gray-50 hover:text-gray-600 focus:bg-gray-50 focus:text-gray-600 focus:outline-none dark:text-gray-500 dark:focus:bg-gray-800 dark:focus:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-300" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="my-4">
              <p class="text-pretty text-center">${options.message}</p>
            </div>
        </div>
    </div>`;

    const elem = $(html).get(0) as HTMLDivElement,
        escapeListener = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') {
                return;
            }
            removeEventListener('keydown', escapeListener, true);
            event.preventDefault();
            event.stopPropagation();
            elem.remove();

            if ('function' === typeof options.cancel) {
                options.cancel();
            }
        };

    document.body.appendChild(elem);
    $(elem).find('.modal-body').append(footer);

    addEventListener('keydown', escapeListener, true);
    $(elem)
        .find('.modal-closer')
        .on('click keydown', (event: Event) => {
            if (event instanceof KeyboardEvent && event.key !== ' ' && event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            elem.remove();
            if ('function' === typeof options.cancel) {
                options.cancel();
            }
        });
    return elem;
}
