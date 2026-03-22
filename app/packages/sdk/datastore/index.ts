import { type Subscriber, type Unsubscriber, type Updater, type Writable, writable } from 'svelte/store';
import type { ContextMap } from '../environment/types';
import { decode, encode, sprintf, tap, value } from '../utils';
import type { DataStore, Prefixable } from './types';

export type { DataStore, Prefixable } from './types';

let undef: undefined;
const states = new Map<string, Writable<any>>();

abstract class BaseStore implements DataStore, Prefixable {
    private readonly _prefix: string;
    get prefix(): string {
        return this._prefix;
    }

    protected _key(key: string): string {
        return this.prefix + key;
    }

    constructor(prefix: string) {
        if (prefix.length && !prefix.endsWith(':')) {
            prefix += ':';
        }
        this._prefix = prefix;
    }

    updateItem(name: string, updater: Updater<any>): void {
        this.setItem(name, updater(this.getItem(name)));
    }

    subscribe(name: string, subscriber: Subscriber<any>, notifier: () => void = () => {}): Unsubscriber {
        return this.writable(name).subscribe(subscriber, notifier);
    }

    clear(): void {
        for (const key of [...this.keys]) {
            this.removeItem(key);
        }
    }

    get(name: string, defaultValue?: any): any {
        defaultValue ??= null;
        let result: any = this.getItem(name);
        if ([undef, null].includes(result)) {
            result = this.set(name, defaultValue);
        }
        return result;
    }

    set(name: string, newValue: any): any {
        newValue = value(newValue);
        const store = states.get(this._state(name));
        if (store) {
            // store updates storage value
            store.set(newValue);
            return newValue;
        }
        this.setItem(name, newValue);
        return newValue;
    }

    writable(name: string, defaultValue?: any): Writable<any> {
        const state = this._state(this._key(name));
        if (!states.has(state)) {
            states.set(
                state,
                tap(writable(this.get(name, defaultValue)), (store) =>
                    store.subscribe((val: any) => this.setItem(name, val))
                )
            );
        }
        return states.get(state) as Writable<any>;
    }

    get size(): number {
        return this.keys.length;
    }

    protected abstract _state(name: string): string;

    abstract get keys(): string[];

    abstract hasItem(name: string): boolean;

    abstract getItem(name: string, defaultValue?: any): any;

    abstract setItem(name: string, newValue: any): void;

    abstract removeItem(name: string): void;
}

export class LocalStore extends BaseStore implements DataStore, Prefixable {
    private readonly _storage: Storage;

    private readonly _storageType: string;

    get keys(): string[] {
        const result: string[] = [];
        for (let i = 0; i < this._storage.length; ++i) {
            const key = this._storage.key(i) as string;
            if (key.startsWith(this.prefix)) {
                result.push(key.slice(this.prefix.length));
            }
        }
        return result;
    }

    constructor(store: Storage = sessionStorage, prefix: string = '') {
        super(prefix);
        this._storage = store;
        this._storageType = sessionStorage === store ? 'session' : 'local';
    }

    protected _state(name: string): string {
        return sprintf('LocalStore|%s|%s', this._storageType, name);
    }

    writable(name: string, defaultValue?: any): Writable<any> {
        const key = this._key(name),
            state = this._state(key);

        if (!states.has(state)) {
            const store = writable(this.get(name, defaultValue ?? null), (set) => {
                const sync = (event: StorageEvent) => {
                    if (event.storageArea === this._storage && event.key === key) {
                        set(decode(event.newValue));
                    }
                };
                addEventListener('storage', sync);
                return () => removeEventListener('storage', sync);
            });
            store.subscribe((newValue) => this.setItem(name, newValue));
            states.set(state, store);
        }
        return states.get(state) as Writable<any>;
    }

    hasItem(name: string): boolean {
        return this._storage.hasItem(this._key(name));
    }

    getItem(name: string, defaultValue?: any): any {
        return decode(this._storage.getItem(this._key(name))) ?? value(defaultValue ?? null);
    }

    setItem(name: string, newValue: any): void {
        newValue = value(newValue);
        if ([undef, null].includes(newValue)) {
            return this.removeItem(name);
        }
        this._storage.setItem(this._key(name), encode(newValue));
    }

    removeItem(name: string): void {
        this._storage.removeItem(this._key(name));
    }
}

export class MemoryStore extends BaseStore implements DataStore, Prefixable {
    private _storage: ContextMap;

    protected _state(name: string): string {
        return sprintf('MemoryStore|%s', name);
    }

    constructor(storage?: ContextMap, prefix: string = '') {
        super(prefix);
        this._storage = storage ?? new Map<string, any>();
    }

    get keys(): string[] {
        const result: string[] = [];
        for (const key of this._storage.keys()) {
            if (key.startsWith(this.prefix)) {
                result.push(key.slice(this.prefix.length));
            }
        }
        return result;
    }

    hasItem(name: string): boolean {
        return this._storage.has(this._key(name));
    }

    getItem(name: string, defaultValue?: any) {
        return this._storage.get(this._key(name)) ?? value(defaultValue ?? null);
    }

    setItem(name: string, newValue: any): void {
        newValue = value(newValue);
        if ([undef, null].includes(newValue)) {
            this.removeItem(name);
            return;
        }
        this._storage.set(this._key(name), newValue);
    }

    removeItem(name: string): void {
        this._storage.delete(this._key(name));
    }
}
