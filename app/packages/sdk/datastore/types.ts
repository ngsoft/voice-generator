import type { Subscriber, Unsubscriber, Updater, Writable } from 'svelte/store';

export interface DataStore {
    readonly size: number;
    readonly keys: string[];

    hasItem: (name: string) => boolean;
    getItem: (name: string, defaultValue?: any) => any;
    setItem: (name: string, value: any) => void;
    removeItem: (name: string) => void;
    clear: () => void;

    updateItem: (name: string, updater: Updater<any>) => void;
    writable: (name: string, defaultValue?: any) => Writable<any>;
    subscribe: (name: string, subscriber: Subscriber<any>, notifier: () => void) => Unsubscriber;
    /** Get and update storage value, also notify subscribers (if any) if new value */
    get(name: string, defaultValue?: any): any;
    /** Set new value and notify subscribers(if any)*/
    set(name: string, newValue: any): any;
}

export interface Prefixable {
    readonly prefix: string;
}
