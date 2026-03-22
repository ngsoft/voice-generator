import { isObject, isString, sprintf, uniqid } from '.';

interface Rules {
    [property: string]: string;
}

interface Style {
    [selector: string]: Rules | Style;
}

function clone(obj: any): any {
    const result: any = {};

    if (isObject(obj)) {
        for (const param of Object.keys(obj) as string[]) {
            if (isObject(obj[param])) {
                result[param] = clone(obj[param]);
                continue;
            }
            result[param] = obj[param];
        }
    }

    return result;
}

function merge(base: Style, ...others: Style[]): Style {
    const result: Style = {};

    for (const selector in base) {
        result[selector] = {};
        for (const param in base[selector]) {
            // media query
            if (isObject(base[selector][param])) {
                result[selector][param] = clone(base[selector][param]);
                continue;
            }
            result[selector][param] = base[selector][param];
        }
    }

    for (const selector in others) {
        result[selector] ??= {};
        for (const param in others[selector]) {
            // media query
            if (isObject(others[selector][param])) {
                result[selector][param] = merge(
                    (result[selector][param] ?? {}) as Style,
                    others[selector][param] as Style
                );
                continue;
            }
            result[selector][param] = others[selector][param];
        }
    }

    return result;
}

function makeCss(style: Style): string {
    let css: string = '';
    for (const selector of Object.keys(style)) {
        css += sprintf('%s {', selector);

        for (const param of Object.keys(style[selector])) {
            // media query
            if (isObject(style[selector][param])) {
                css += makeCss(style[selector] as Style);
                break;
            }
            css += sprintf('%s: %s;', param, style[selector][param]);
        }

        css += '}';
    }

    return css;
}

export class StyleInjector {
    private _ref: HTMLStyleElement;
    private _id: string;
    constructor(_document?: Document) {
        _document ??= document;
        this._id = uniqid();
        this._ref = _document.createElement('style') as HTMLStyleElement;
        this._ref.setAttribute('id', `style-injector-${this._id}`);
        _document.getElementsByTagName('head')[0].appendChild(this._ref);
    }

    get css(): string {
        return this._ref.innerText;
    }

    inject(style: string | Style, ...others: Style[]) {
        if (!isString(style)) {
            style = makeCss(style);
        }

        if (others.length) {
            style += makeCss(merge(others[0], ...others.slice(1)));
        }

        if ((style = style.trim())) {
            this._ref.innerHTML += `${style}\n`;
        }
    }

    merge(base: Style, ...others: Style[]): Style {
        return merge(base, ...others);
    }

    toCss(style: Style): string {
        return makeCss(style);
    }
}
