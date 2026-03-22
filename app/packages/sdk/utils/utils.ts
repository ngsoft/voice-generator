import type { CallbackFunction, VariadicFunction } from './types';

type CallbackWithParams = [VariadicFunction, ...any[]] | VariadicFunction;

export const IS_UNSAFE = false,
    IS_BROWSER: boolean = typeof window !== 'undefined' && typeof window.document !== 'undefined',
    IS_TOUCH: boolean = typeof ontouchstart !== 'undefined',
    global = globalThis ?? window,
    JSON: JSON = global.JSON,
    document: Document = global.document;

let undef: undefined,
    ids: Set<string> = new Set(),
    link: HTMLAnchorElement = document.createElement('a');

export const noop: CallbackFunction = () => {},
    identity: VariadicFunction = (x: any): any => x,
    isUndef = (param: any): param is undefined => typeof param === 'undefined',
    isString = (param: any): param is string => typeof param === 'string',
    isNumber = (param: any): param is number => typeof param === 'number',
    isInt = (param: any): param is number => Number.isInteger(param),
    isFloat = (param: any): param is number => isNumber(param) && parseFloat(`${param}`) === param,
    isUnsigned = (param: any): param is number => param >= 0 && isNumber(param),
    isUnsignedInt = (param: any): param is number => param >= 0 && isInt(param),
    isNumericString = (n: any): n is string => isString(n) && /^-?(?:\d+\.)?\d+$/.test(n),
    isNumeric = (n: any): n is number | string => isInt(n) || isFloat(n) || isNumericString(n),
    isBool = (param: any): param is boolean => typeof param === 'boolean',
    isArray = (param: any): param is Array<any> => Array.isArray(param),
    isNull = (param: any): param is null => param === null,
    isObject = (param: any): param is Object => null !== param && typeof param === 'object',
    isEventTarget = (s: any): s is EventTarget => isObject(s) && typeof s.addEventListener === 'function',
    isCallable = (param: any): param is Function => typeof param === 'function',
    isFunction = isCallable,
    isScalar = (param: any): param is number | boolean | string =>
        ['number', 'boolean', 'string'].includes(typeof param),
    capitalize = (param: string): string =>
        param
            .split(/\s/)
            .map((param) => param.charAt(0).toUpperCase() + param.slice(1).toLowerCase())
            .join(' '),
    isBlank = (param: any): boolean => {
        if (isUndef(param) || isNull(param)) {
            return true;
        }
        if (isString(param) || isArray(param)) {
            return param.length === 0;
        }
        if (isNumber(param)) {
            return param === 0;
        }
        if (isObject(param)) {
            return Object.keys(param).length === 0;
        }
        return false;
    },
    /**
     * Get Enum cases
     */
    getEnumCases = (enumType: any): null | { name: string; value: any }[] => {
        const result = [];
        if (typeof enumType === 'object' && null !== enumType) {
            for (const name in enumType) {
                if (!Number.isNaN(Number(name))) {
                    continue;
                }
                result.push({ name, value: enumType[name] });
            }
        }
        return result.length ? result : null;
    },
    sprintf = (format: string, ...args: any[]): string => {
        let i = 0;

        return format.replace(/%[sdifu]/g, (match) => {
            const arg = args[i++];

            switch (match) {
                case '%v':
                    return encode(arg);
                case '%s':
                    return String(arg);
                case '%i':
                case '%d':
                    return parseInt(arg, 10).toString();
                case '%f':
                    return parseFloat(arg).toString();
                case '%u':
                    return Math.abs(parseInt(arg, 10)).toString();
                default:
                    return match;
            }
        });
    },
    runAsync = (callback: any, ...args: any[]): void => {
        if (isFunction(callback)) {
            if (global.requestAnimationFrame) {
                global.requestAnimationFrame(() => callback(...args));
                return;
            }
            setTimeout(() => callback, 0, ...args);
        }
    },
    value = (value: any, ...args: any[]): any => {
        if (isFunction(value)) {
            return value(...args);
        }
        return value;
    },
    tap = (value: any, fn: VariadicFunction): any => {
        fn(value);
        return value;
    },
    tapPromise = (
        value: any,
        onResolve: VariadicFunction = identity,
        onReject: VariadicFunction = (_value, error) => error
    ): Promise<any> => {
        if (value instanceof Promise) {
            return value
                .then((val) => {
                    const result = onResolve(val);
                    if (undef === result) {
                        return val;
                    }
                    return result;
                })
                .catch((error) => onReject(value, error));
        }
        if (value instanceof Error) {
            return tapPromise(Promise.reject(value), onResolve, onReject);
        }
        return tapPromise(Promise.resolve(value), onResolve, onReject);
    },
    isValidSelector = (selector: any) => {
        try {
            return isString(selector) && null === document.createElement('template').querySelector(selector);
        } catch (_error) {}
        return false;
    },
    uuidV4 = (): string => {
        let uuid: string = '';
        for (let i = 0; i < 32; i++) {
            const random = (Math.random() * 16) | 0;
            if (i === 8 || i === 12 || i === 16 || i === 20) {
                uuid += '-';
            }
            uuid += (i === 12 ? 4 : i === 16 ? (random & 3) | 8 : random).toString(16);
        }
        return uuid;
    },
    isElement = (elem: any): boolean => {
        return null !== elem && typeof elem === 'object' && isFunction(elem.querySelector);
    },
    toCamel = (name: string): string => {
        let index;
        while (-1 < (index = name.indexOf('-'))) {
            name = name.slice(0, index) + name.slice(index + 1, index + 2).toUpperCase() + name.slice(index + 2);
        }
        return name;
    },
    toDashed = (name: string): string => name.replace(/([A-Z])/g, (u) => `-${u.toLowerCase()}`),
    isHtml = (param: any): boolean => isString(param) && param.startsWith('<') && param.startsWith('>'),
    isJson = (param: any): param is string => {
        if (!isString(param) || '' === param) {
            return false;
        }
        if (isNumeric(param) || ['true', 'false', 'null'].includes(param)) {
            return true;
        }
        return (
            ['{', '[', '"'].includes((param as string).slice(0, 1))
            && ['}', ']', '"'].includes((param as string).slice(-1))
        );
    },
    decode = (value: any): any => {
        if ([null, undef].includes(value)) {
            return null;
        }
        if (isJson(value)) {
            try {
                return JSON.parse(value);
            } catch (_err) {}
        }
        return value;
    },
    encode = (value: any): any => {
        if (isFunction(value) || isUndef(value)) {
            return value;
        }
        return isString(value) ? value : JSON.stringify(value);
    },
    parseAttributes = (obj: any, name: null | string = null): any[] => {
        let result: any[] = [];
        for (const key in obj) {
            const value = obj[key];
            if (isObject(value)) {
                result = result.concat(parseAttributes(value)).map((item) => [[key, item[0]].join('-'), item[1]]);
                continue;
            }
            result.push([key, encode(value)]);
        }

        return result.map((item) => (name ? [[name, item[0]].join('-'), item[1]] : item));
    },
    validateHtml = (html: any): boolean => isString(html) || isElement(html) || isArray(html),
    uniqid = (): string => {
        let value = '';
        do {
            value = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        } while (ids.has(value));
        ids.add(value);
        return value;
    },
    clone = (obj: Record<string, any> | Array<any>): Record<string, any> | Array<any> => {
        if (Array.isArray(obj)) {
            return Array.from(obj);
        }
        return Object.assign({}, obj);
    },
    cloneRecord = (record: Record<string, any>): Record<string, any> => {
        const result: Record<string, any> = {};
        for (const key in record) {
            if (isObject(record[key])) {
                result[key] = cloneRecord(record[key]);
            } else {
                result[key] = record[key];
            }
        }
        return result;
    },
    html2element = (html: string) => {
        if (html.length) {
            const template: any = createElement('template', html),
                content = template.content;

            if (content.childNodes.length === 0) {
                return;
            } else if (content.childNodes.length > 1) {
                return [...content.childNodes];
            }
            return content.childNodes[0];
        }
        return;
    },
    createElement = (tag: any = 'div', params: any = {}, html: any = null): HTMLElement => {
        if (isObject(tag)) {
            params = tag;
            tag = params.tag ?? 'div';
        }
        if (typeof tag !== 'string') {
            throw new TypeError('tag must be a String');
        }

        if (validateHtml(params)) {
            html = params;
            params = {};
        }
        let callback;
        const elem = isHtml(tag) ? html2element(tag) : document.createElement(tag);
        if (!isElement(elem)) {
            throw new TypeError(`Invalid tag supplied ${tag}`);
        }

        if (isObject(params)) {
            const data = [];

            callback = params.callback;

            if (!validateHtml(html)) {
                html = params.html;
            }

            if (isObject(params.data)) {
                data.push(...parseAttributes(params.data, 'data'));
            }

            if (isObject(params.dataset)) {
                data.push(...parseAttributes(params.dataset, 'data'));
            }

            data.forEach((item) => elem.setAttribute(...item));

            if (isArray(params.class)) {
                params.class = params.class.join(' ');
            }

            for (const attr in params) {
                if (['data', 'dataset', 'html', 'tag', 'callback'].includes(attr)) {
                    continue;
                }

                let value = params[attr];

                if (isString(value)) {
                    const current = elem.getAttribute(attr) ?? '';
                    if (current.length > 0) {
                        value = `${current} ${value}`;
                    }

                    elem.setAttribute(attr, value);
                } else if (isObject(value)) {
                    parseAttributes(value, attr).forEach((item) => elem.setAttribute(...item));
                } else {
                    elem[attr] = value;
                }
            }
        }

        if (validateHtml(html)) {
            if (!isArray(html)) {
                html = [html];
            }

            for (const child of html) {
                if (isElement(child)) {
                    elem.appendChild(child);
                } else {
                    elem.innerHTML += child;
                }
            }
        }

        if (isFunction(callback)) {
            callback(elem);
        }

        return elem;
    },
    element2Html = (elem: any): string | undefined => {
        if (isElement(elem)) {
            elem = [elem];
        }
        if (elem instanceof NodeList) {
            elem = [...elem];
        }
        if (isArray(elem)) {
            return createElement(
                'div',
                elem.map((el: HTMLElement) => el.cloneNode(true))
            ).innerHTML;
        }
        return;
    },
    getUrl = (url: URL | string): URL | undefined => {
        if (link) {
            if (isString(url)) {
                link.href = url as string;
                url = new URL(link.href);
            }
            if (url instanceof URL) {
                return url;
            }
        }

        return;
    },
    selectText = (node: any): void => {
        if ('createTextRange' in document.body) {
            // @ts-expect-error
            const range = document.body.createTextRange();
            range.moveToElementText(node);
            range.select();
        } else if ('getSelection' in global) {
            const selection = window.getSelection(),
                range = document.createRange();
            range.selectNodeContents(node);
            selection?.removeAllRanges();
            selection?.addRange(range);
        } else {
            console.warn('Could not select text in node: Unsupported browser.');
        }
    },
    findMapKey = (map: Map<any, any>, value: any, many: boolean = false) => {
        const result = [];
        for (const [key, val] of map) {
            if (value === val) {
                result.push(key);
                if (!many) {
                    return result[0];
                }
            }
        }
        return many ? result : null;
    },
    sleep = (time: number): Promise<void> => {
        return new Promise((resolve) => setTimeout(resolve, time <= 60 ? time * 1000 : time));
    },
    runLater = (...callbacks: CallbackWithParams[]): (() => any[]) => {
        const pool: Map<VariadicFunction, any[]> = new Map();

        for (let values of callbacks) {
            if (!Array.isArray(values)) {
                values = [values];
            }

            const fn = values.shift();
            if (typeof fn === 'function') {
                pool.set(fn, values);
            }
        }

        return () => {
            const result = [];
            for (const [fn, args] of pool.entries()) {
                result.push(fn(...args));
            }
            pool.clear();
            return result;
        };
    },
    wordWrap = (input: string, maxLength: number): string[] => {
        const punctuation: string[] = ['.', '?', '!'],
            replacements = new Map<string, string>([
                ['Mr.', 'Mr'],
                ['Mrs.', 'Mrs'],
                ['Ms.', 'Ms'],
            ]),
            result: string[] = [''],
            words: string[] = input.split(/[\s\t]+/);

        let index = 0,
            added = false;

        function addLine() {
            if (!added) {
                index++;
                result.push('');
                added = true;
            }
        }

        for (let i = 0; i < words.length; i++) {
            added = false;
            for (const [find, replace] of replacements) {
                if (words[i] === find) {
                    words[i] = replace;
                }
            }
            const word = words[i],
                length = result[index].length;

            if (length + word.length > maxLength) {
                addLine();
            }
            result[index] += ` ${word}`;
            if (punctuation.includes(word.slice(-1))) {
                addLine();
            }
        }
        if (!result.slice(-1)[0].length) {
            result.pop();
        }

        return result;
    },
    mapFindKey = (map: Map<any, any>, subject: any): any => {
        for (const [key, value] of map) {
            if (value === subject) {
                return key;
            }
        }
        return null;
    },
    getValue = (obj: any, subject: string, defaultValue: any = null): any => {
        if (!isObject(obj) || isUndef(obj[subject])) {
            return value(defaultValue);
        }
        return obj[subject];
    },
    pullValue = (obj: any, subject: string, defaultValue: any = null): any => {
        const result = getValue(obj, subject);
        if (null === result) {
            return value(defaultValue);
        }
        delete obj[subject];
        return result;
    };
