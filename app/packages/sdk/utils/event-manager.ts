import { runAsync } from './utils';

export class EventManagerEvent {
    constructor(
        private _type: string,
        private _detail: any = null
    ) {}

    get type(): string {
        return this._type;
    }

    get detail(): any {
        return this._detail;
    }
}

interface Listener {
    listener: (ev: EventManagerEvent) => void;
    handler: (ev: EventManagerEvent) => void;
    disabler: () => void;
}

interface Listeners {
    [type: string]: Set<Listener>;
}

export class EventManager {
    private _listeners: Listeners = {};

    constructor(private _async: boolean = false) {}

    addEventListener(
        type: string,
        listener: (ev: EventManagerEvent) => undefined | false,
        once: boolean = false
    ): () => void {
        this._listeners[type] ??= new Set<Listener>();
        const manager: any = { listener };
        manager.disabler = (): void => {
            this._listeners[type].delete(manager as Listener);
        };
        manager.handler = (ev: EventManagerEvent): void => {
            once && manager.disabler();
            this._async ? runAsync(manager.listener, ev) : manager.listener(ev);
        };
        this._listeners[type].add(manager as Listener);
        return (): void => {
            manager.disabler();
        };
    }

    removeEventListener(type: string, listener: ((ev: EventManagerEvent) => void) | null = null): EventManager {
        if (this._listeners[type]) {
            if (null === listener) {
                this._listeners[type].clear();
            }
            this._listeners[type];
            for (const obj of [...this._listeners[type].values()]) {
                if (obj.listener === listener) {
                    obj.disabler();
                }
            }
        }
        return this;
    }

    dispatchEvent(type: string, detail: any = null): EventManagerEvent {
        const event = new EventManagerEvent(type, detail);
        for (const manager of [...(this._listeners[type]?.values() ?? [])]) {
            manager.handler(event);
        }
        return event;
    }
}
