/**
 * Combines a url with a baseUrl.
 */
export function combineUrlWithBaseUrl(url: string | null | undefined, baseUrl: string | null | undefined): string {
    return url && url.indexOf('://') > -1 ? url : (baseUrl ?? '') + (url ?? '');
}
/**
 * Decode string to scalar type
 */
function decode(value: string): any {
    try {
        return JSON.parse(value);
    } catch (_) {
        return value;
    }
}
/**
 * Converts query string to object
 */
export function queryStringToObject(queryParams: URLSearchParams | string): Record<string, any> {
    if ('string' === typeof queryParams) {
        queryParams = new URLSearchParams(queryParams);
    }

    const re = /^([\w-]+)((?:\[([\w-]*)])*)[\w-]*$/,
        repair = (obj: Record<string, any>): any[] | Record<string, any> => {
            const keys = Object.keys(obj);
            if (keys.every((key) => !Number.isNaN(Number(key)))) {
                return Object.values(obj);
            }
            return obj;
        },
        iterate = (obj: Record<string, any>): any[] | Record<string, any> => {
            for (const key in obj) {
                if (typeof obj[key] !== 'object') {
                    continue;
                }
                obj[key] = iterate(obj[key]);
            }

            return repair(obj);
        };
    const result: Record<string, any> = {};
    for (const [key, value] of queryParams) {
        if (!value.length) {
            continue;
        }
        const val = decode(value),
            parsed = re.exec(key);

        let obj = result;

        if (parsed) {
            let prop: string = parsed[1],
                subs = parsed[2].length ? parsed[2].slice(1, -1).split('][') : [];

            if (!prop?.length) {
                continue;
            }

            if (!subs.length) {
                result[prop] = val;
                continue;
            }

            do {
                obj[prop] ??= {};
                obj = obj[prop as string];
                const _key = subs.shift();
                prop = _key?.length ? _key : String(Object.keys(obj).length);
            } while (subs.length > 0);
            obj[prop] = val;
        }
    }

    return iterate(result);
}

export function queryParamsToQueryString(queryParams?: Record<string, string | string[]>): URLSearchParams {
    if (!queryParams) {
        return new URLSearchParams();
    }
    const map: [string, string][] = [],
        keys = Object.keys(queryParams);
    for (const prop of keys) {
        if (!Array.isArray(queryParams[prop])) {
            queryParams[prop] = [queryParams[prop]];
        }
        const name = queryParams[prop].length > 1 ? `${prop}[]` : prop;
        for (const value of queryParams[prop]) {
            map.push([name, value]);
        }
    }
    return new URLSearchParams(map);
}

export function formDataToObject(form: FormData, decodeString: boolean = false): Record<string, string | string[]> {
    const params: Record<string, string | string[]> = {};
    for (const [key, value] of form.entries()) {
        const str = decodeString ? decode(value.toString()) : value.toString();

        if ('undefined' === typeof params[key]) {
            params[key] = str;
            continue;
        } else if ('string' === typeof params[key]) {
            params[key] = [params[key], str];
            continue;
        }
        params[key].push(str);
    }

    return params;
}

export function formDataToQueryString(form: FormData): URLSearchParams {
    return queryParamsToQueryString(formDataToObject(form));
}

export function combineUrlWithQueryParameters(
    url: string,
    queryParameters?: Record<string, string | string[]>
): string {
    if (typeof url !== 'string') {
        throw new TypeError('url must be string');
    }

    const queryString = queryParameters && queryParamsToQueryString(queryParameters).toString();

    // return the untouched url
    if (!queryString) {
        return url;
    }

    // combine url with query string
    return url + (url && url.indexOf('?') !== -1 ? `&${queryString}` : `?${queryString}`);
}
