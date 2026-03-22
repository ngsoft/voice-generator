import type { AttributeAccessor } from './types';
import { decode, encode, isArray, isObject, isString, isValidSelector, toDashed, value } from './utils';

function hasDataset(elem: any) {
    return isObject(elem) && !!elem.dataset;
}

function _elem(elem: any): HTMLElement[] {
    if (hasDataset(elem)) {
        return [elem];
    } else if (isValidSelector(elem)) {
        elem = [...document.querySelectorAll(elem)];
    } else if (elem instanceof NodeList) {
        elem = [...elem];
    }
    if (isArray(elem)) {
        return elem.filter((elem) => hasDataset(elem));
    }
    return [];
}

class _AttributeAccessor implements AttributeAccessor {
    private _prefix: string = '';
    private _elem: HTMLElement[] = [];

    constructor(elem: any, prefix: string = '') {
        this._prefix = prefix;
        this._elem = _elem(elem);
    }

    get(attribute: string, defaultValue: any = null): any {
        if (attribute.startsWith(this._prefix)) {
            attribute = attribute.slice(this._prefix.length);
        }

        let result,
            _attribute = this._prefix + toDashed(attribute);

        if (this._elem.length) {
            result = decode(this._elem[0].getAttribute(_attribute));
        }
        return result ?? value(defaultValue);
    }

    set(attribute: string, newValue: any): AttributeAccessor {
        if (attribute.startsWith(this._prefix)) {
            attribute = attribute.slice(this._prefix.length);
        }
        let encoded = encode(value(newValue)) ?? null,
            _attribute = this._prefix + toDashed(attribute);

        if ([null, 'null'].includes(encoded)) {
            this.remove(attribute);
            encoded = null;
        }
        if (isString(encoded)) {
            for (const elem of this._elem) {
                elem.setAttribute(_attribute, encoded);
            }
        }
        return this;
    }

    remove(attribute: string): AttributeAccessor {
        if (attribute.startsWith(this._prefix)) {
            attribute = attribute.slice(this._prefix.length);
        }

        const _attribute = this._prefix + toDashed(attribute);

        for (const elem of this._elem) {
            elem.removeAttribute(_attribute);
        }
        return this;
    }
}

function _attr(
    accessor: _AttributeAccessor,
    qualifiedName: string | Record<string, any>,
    newValue: any = null
): AttributeAccessor | any {
    if (isString(qualifiedName)) {
        return newValue === null ? accessor.get(qualifiedName) : accessor.set(qualifiedName, newValue);
    }
    for (const key of Object.keys(qualifiedName)) {
        accessor.set(key, qualifiedName[key]);
    }

    return accessor;
}

export function attr(
    elem: any,
    qualifiedName: null | string | Record<string, any> = null,
    newValue: any = null
): AttributeAccessor | any {
    const accessor = new _AttributeAccessor(elem, '');
    if (null === qualifiedName) {
        return accessor;
    }

    return _attr(accessor, qualifiedName, newValue);
}

export function dataset(
    elem: any,
    qualifiedName: null | string | Record<string, any> = null,
    newValue: any = null
): AttributeAccessor | any {
    const accessor = new _AttributeAccessor(elem, 'data-');
    if (null === qualifiedName) {
        return accessor;
    }

    return _attr(accessor, qualifiedName, newValue);
}
