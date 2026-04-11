import { HSSelect, type ISelectOptions } from 'preline/non-auto';
import { __ } from '$packages/http';

const defaults: ISelectOptions = {
    hasSearch: true,
    searchLimit: 5,
    dropdownPlacement: null,
    dropdownScope: 'parent',
    dropdownSpace: 10,
    dropdownVerticalFixedPlacement: null,
    toggleTag: `<button type="button" aria-expanded="false"></button>`,
    toggleClasses:
        'advance-select-toggle advance-select-lg rounded-full select-disabled:pointer-events-none select-disabled:opacity-40',
    dropdownClasses: 'advance-select-menu max-h-72 overflow-y-auto pt-0 border',
    optionClasses: 'advance-select-option selected:select-active',
    optionTemplate: `<div class="flex justify-between items-center w-full"><span data-title></span><span class="icon-[lucide--check] shrink-0 size-4 text-primary hidden selected:block"></span></div>`,
    extraMarkup: `<span class="icon-[lucide--chevron-down] shrink-0 size-4 text-base-content absolute top-1/2 inset-e-3 -translate-y-1/2"></span>`,
    searchWrapperClasses: 'sticky top-0 bg-base-100 py-2',
    searchTemplate: `<input type="text" class="input rounded-full!" placeholder="">`,
    searchPlaceholder: `${await __('Search')}...`,
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
        (options ??= {}).placeholder ??= getPlaceholder(element);
        instances.set(element, (result = new HSSelect(element, { ...defaults, ...options })));
    }
    return result;
}
