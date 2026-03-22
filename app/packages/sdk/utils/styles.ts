import { twMerge } from 'tailwind-merge';
import { isArray, isObject, isString, value } from './utils';

export class ClassList {
    static of(elem: Element) {
        return new ClassList(elem);
    }

    constructor(private _elem: Element) {}

    private get _classList(): DOMTokenList {
        return this._elem.classList;
    }

    get values(): string[] {
        const result = new Set<string>();
        for (const name of this._classList.values()) {
            result.add(name);
        }
        return [...result.values()];
    }

    add(...names: string[]): ClassList {
        for (const name of listClasses(names)) {
            this._classList.add(name);
        }

        return this;
    }

    toggle(...names: string[]): ClassList {
        for (const name of listClasses(names)) {
            this._classList.toggle(name);
        }
        return this;
    }

    remove(...names: string[]): ClassList {
        for (const name of listClasses(names)) {
            this._classList.remove(name);
        }
        return this;
    }

    contains(...names: string[]): boolean {
        let ok = false;
        for (const name of listClasses(names)) {
            ok = true;
            if (!this._classList.contains(name)) {
                return false;
            }
        }
        return ok;
    }

    each(func: (current: string, list: string[]) => undefined | false): ClassList {
        const list = this.values;
        for (const name of list) {
            const result = value(func, name, list);
            if (false === result) {
                break;
            }
        }
        return this;
    }

    some(func: (current: string, list: string[]) => boolean): boolean {
        const list = this.values;
        for (const name of list) {
            if (value(func, name, list)) {
                return true;
            }
        }
        return false;
    }

    every(func: (current: string, list: string[]) => boolean): boolean {
        const list = this.values;
        for (const name of list) {
            if (!value(func, name, list)) {
                return false;
            }
        }
        return list.length > 0;
    }
}

type ClassValue = string | Record<string, boolean> | boolean | string[] | Record<string, boolean>[];

function mergeNames(names: string, set: Set<string>): void {
    for (const name of names.split(/\s+/)) {
        if (name.length) {
            set.add(name);
        }
    }
}

function mergeClassRecord(record: Record<string, boolean>, set: Set<string>): void {
    for (const names in record) {
        if (record[names]) {
            mergeNames(names, set);
        }
    }
}

function listClasses(...values: ClassValue[]): string[] {
    const result = new Set<string>();

    for (const names of values) {
        if (isString(names)) {
            mergeNames(names, result);
        } else if (isArray(names)) {
            for (const name of names) {
                if (isString(name)) {
                    mergeNames(name, result);
                    continue;
                }
                if (isObject(name)) {
                    mergeClassRecord(name, result);
                }
            }
        } else if (isObject(names)) {
            mergeClassRecord(names as Record<string, boolean>, result);
        }
    }
    return [...result.values()];
}

export function mergeClasses(...values: ClassValue[]): string {
    return listClasses(...values).join(' ');
}

export function mergeTailwind(...values: ClassValue[]): string {
    return twMerge(...listClasses(...values));
}
