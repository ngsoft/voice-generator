import { global, isEventTarget } from './utils';

const ELEMENT_BINDING_KEY = '_emitter';

type EventListener = {
    type: string;
    listener: (event: Event) => void;
    capture: boolean;
};

type ListenerOptions =
    | {
          capture?: boolean;
          once?: boolean;
      }
    | boolean;

export class EventDispatcher {
    private get _listeners(): EventListener[] {
        // @ts-expect-error
        return this._target[ELEMENT_BINDING_KEY];
    }

    private add(listener: EventListener): void {
        this._listeners.push(listener);
    }

    private remove(listener: EventListener): void {
        const index = this._listeners.indexOf(listener);
        if (index !== -1) {
            this._listeners.splice(index, 1);
        }
    }

    private _target: EventTarget;

    constructor(root: any = null) {
        root ??= global;
        if (typeof root === 'string') {
            root = document.querySelector(root);
        }

        if (!root || !isEventTarget(root)) {
            throw new TypeError('target is not an event target');
        }

        this._target = root as EventTarget;
        if (!root.hasOwnProperty(ELEMENT_BINDING_KEY)) {
            Object.defineProperty(root, ELEMENT_BINDING_KEY, {
                enumerable: false,
                configurable: true,
                writable: true,
                value: [],
            });
        }
    }

    addEventListener(
        type: string,
        listener: (event: Event) => void,
        options: ListenerOptions = false
    ): EventDispatcher {
        const params: { once: boolean; capture: boolean } = { once: false, capture: false };
        if (typeof options === 'boolean') {
            params.capture = options;
        } else {
            params.once = options.once ?? false;
            params.capture = options.capture ?? false;
        }

        const types = type.split(/\s+/);

        for (const type of types) {
            const handler: (event: Event) => void = (event) => {
                if (params.once) {
                    this.removeEventListener(type, listener, params.capture);
                }
                listener.call(this._target, event);
            };
            this.add({
                type,
                listener,
                capture: params.capture,
            });
            this._target.addEventListener(type, handler, params);
        }

        return this;
    }

    removeEventListener(
        type: string,
        listener: null | ((event: Event) => void) = null,
        options: ListenerOptions = false
    ): EventDispatcher {
        const params: { once: boolean; capture: boolean } = { once: false, capture: false };
        if (typeof options === 'boolean') {
            params.capture = options;
        } else {
            params.once = options.once ?? false;
            params.capture = options.capture ?? false;
        }
        const types = type.split(/\s+/);
        for (const type of types) {
            for (const eventListener of this._listeners) {
                if (
                    eventListener.type === type
                    && eventListener.capture === params.capture
                    && (!listener || eventListener.listener === listener)
                ) {
                    this._target.removeEventListener(type, eventListener.listener, eventListener.capture);
                    this.remove(eventListener);
                }
            }
        }
        return this;
    }

    dispatchEvent(type: string | Event, detail: any = null) {
        let event: Event;
        if (type instanceof Event) {
            event = type;
            // @ts-expect-error
            type.detail = detail;
            this._target.dispatchEvent(event);
            return this;
        }

        const types = type.split(/\s+/);
        for (const type of types) {
            event = new Event(type);
            // @ts-expect-error
            event.detail = detail;
            this._target.dispatchEvent(event);
        }
        return this;
    }

    on(type: string, listener: (event: Event) => void, options: ListenerOptions = false) {
        return this.addEventListener(type, listener, options);
    }

    one(type: string, listener: (event: Event) => void, options: ListenerOptions = false) {
        const params: { once: boolean; capture: boolean } = { once: true, capture: false };
        if (typeof options === 'boolean') {
            params.capture = options;
        } else {
            params.capture = options.capture ?? false;
        }
        return this.addEventListener(type, listener, params);
    }

    off(type: string, listener: ((event: Event) => void) | null = null, options: ListenerOptions = false) {
        return this.removeEventListener(type, listener, options);
    }

    trigger(type: string, detail: any = null) {
        return this.dispatchEvent(type, detail);
    }

    mixin(binding: Object) {
        for (const method of ['on', 'one', 'off', 'trigger']) {
            if (!binding.hasOwnProperty(method)) {
                Object.defineProperty(binding, method, {
                    enumerable: false,
                    configurable: true,
                    value(...args: any[]) {
                        // @ts-expect-error
                        this[fn](...args);
                        return binding;
                    },
                });
            }
        }

        return binding;
    }
}

export function emitter(root: any): EventDispatcher {
    return new EventDispatcher(root);
}

const instance = new EventDispatcher();
instance.mixin(emitter);
emitter.mixin = instance.mixin.bind(instance);
export default emitter;
