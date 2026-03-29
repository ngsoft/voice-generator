import { HSSelect, type ISelectOptions } from 'preline/non-auto';

const defaults: ISelectOptions = {
    hasSearch: true,
    dropdownPlacement: null,
    dropdownScope: 'parent',
    dropdownSpace: 10,
    dropdownVerticalFixedPlacement: null,
    toggleTag: `<button type="button" aria-expanded="false"></button>`,
    toggleClasses:
        'select !py-2 px-4 hs-select-disabled:pointer-events-none hs-select-disabled:opacity-50 relative py-3 ps-4 pe-9 flex gap-x-2 text-nowrap w-full cursor-pointer bg-surface-50-950 text-surface-950-50 rounded-lg text-start',
    dropdownClasses:
        '!mt-1 !pt-0 z-50 w-full max-h-72 p-1 space-y-0.5 rounded-lg overflow-hidden overflow-y-auto bg-surface-50-950 border border-gray-200 dark:border-neutral-700',
    optionClasses:
        'py-2 px-4 w-full text-sm text-surface-950-50 cursor-pointer hover:bg-surface-100-900 rounded-lg focus:outline-hidden focus:bg-surface-100-900 border-0',
    optionTemplate: `<div class="flex justify-between items-center w-full"><span data-title></span><span class="hidden hs-selected:block"><svg class="shrink-0 size-3.5 text-blue-600 dark:text-blue-500 " xmlns="http:.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span></div>`,
    searchWrapperClasses: 'px-2 sticky top-0 bg-surface-50-950 text-surface-950-50',
    searchTemplate: `<input type="text" class="input" placeholder="toto...">`,
    searchPlaceholder: ' ',
};

const instances = new Map<HTMLSelectElement, HSSelect>();

function getPlaceholder(element: HTMLSelectElement): string | undefined {
    const first = element.options[0];
    if (first && !first.value) {
        return first.innerText;
    }
    return undefined;
}

export function getSelect(element: HTMLSelectElement, options?: Partial<ISelectOptions>): HSSelect {
    let result = instances.get(element);
    if (!result) {
        $(
            element
        ).after(`<span class="absolute top-[9px] right-1 h-full grid w-8 place-content-center text-gray-700 dark:text-gray-500">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="size-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"></path>
          </svg>
        </span>`);
        (options ??= {}).placeholder ??= getPlaceholder(element);
        instances.set(element, (result = new HSSelect(element, { ...defaults, ...options })));
    }
    return result;
}
