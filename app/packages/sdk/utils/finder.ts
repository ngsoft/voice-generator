import type { CallbackFunction } from './types';
import { isElement, isValidSelector, runAsync } from './utils';

type FinderCallback = (elem: Document | Element | ShadowRoot | HTMLElement) => void;
type FinderParams = {
    once?: boolean | undefined | null;
    root?: Document | Element | ShadowRoot | HTMLElement | undefined | null;
};
export default function finder(
    selector: string,
    callback: FinderCallback,
    params: FinderParams = { once: false, root: null }
): CallbackFunction {
    if (!isValidSelector(selector)) {
        throw new TypeError('Invalid selector');
    }

    params = { once: false, root: null, ...params };

    const root = params.root ?? document.documentElement,
        once = params.once ?? false;

    if (!isElement(root)) {
        throw new TypeError('root is not an Element');
    }

    const matches: Set<Document | Element | ShadowRoot | HTMLElement> = new Set(),
        controller: AbortController = new AbortController(),
        signal: AbortSignal = controller.signal,
        checkRemoved = () => {
            if (!matches.size) {
                return;
            }

            const removed = [];
            for (const target of [...matches.values()]) {
                let tmp: any = target,
                    matched = false;
                while (null !== (tmp = tmp.parentElement)) {
                    if (tmp === root) {
                        matched = true;
                        break;
                    }
                }
                if (!matched) {
                    removed.push(target);
                }
            }
            for (const el of removed) {
                matches.delete(el);
            }
        },
        watcher = () => {
            if (signal.aborted) {
                return;
            }
            checkRemoved();
            for (const target of [...root.querySelectorAll(selector)]) {
                if (signal.aborted) {
                    return;
                }
                if (matches.has(target)) {
                    continue;
                }
                matches.add(target);
                runAsync(callback, target, selector);
                if (once) {
                    controller.abort();
                    return;
                }
            }
        };

    signal.onabort = () => {
        observer.disconnect();
    };

    const observer = new MutationObserver(watcher);
    watcher();
    if (!signal.aborted) {
        observer.observe(root, {
            attributes: true,
            childList: true,
            subtree: true,
        });
    }

    return () => {
        if (!signal.aborted) {
            controller.abort();
        }
    };
}

finder.one = (
    selector: string,
    callback: FinderCallback,
    root: Document | Element | ShadowRoot | HTMLElement | null = null
): CallbackFunction => {
    return finder(selector, callback, { root, once: true });
};
/**
 * Find first element matching selector
 */
finder.promise = (
    selector: string,
    root: Document | Element | ShadowRoot | HTMLElement | null = null
): Promise<Document | Element | ShadowRoot | HTMLElement> => {
    return new Promise((resolve) => finder.one(selector, resolve, root));
};

export { finder };
