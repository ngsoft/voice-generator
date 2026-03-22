import { isArray, isFunction, value } from './utils';

export class ArrayUtils {
    static of(array: any): ArrayUtils {
        if (!isArray(array)) {
            return new ArrayUtils([]);
        }
        return new ArrayUtils(array);
    }

    constructor(private _array: any[]) {}

    first(defaultValue: any = null): any {
        if (!this._array.length) {
            return value(defaultValue);
        }
        return this._array[0];
    }

    last(defaultValue: any = null): any {
        if (!this._array.length) {
            return value(defaultValue);
        }
        return this._array[this._array.length - 1];
    }

    remove(subject: any): number {
        let fn = (item: any) => item === subject;
        if (isFunction(subject)) {
            fn = subject;
        }
        const index = this._array.findIndex(fn);
        if (index !== -1) {
            this._array.splice(index, 1);
        }
        return this._array.length;
    }
}
