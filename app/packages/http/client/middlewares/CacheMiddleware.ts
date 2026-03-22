import { queryParamsToQueryString } from '../internal/formatting';
import type {
    ICacheEntry,
    ICacheMiddlewareStore,
    IFetchHeaders,
    IFetchRequest,
    IFetchRequestCacheOptions,
    IFetchResponse,
    IMiddleware,
} from '../types';

declare const Response: IFetchResponse<string>;

export class CacheMiddleware implements IMiddleware<IFetchRequest, Promise<IFetchResponse<any>>> {
    protected fallbackStorage: { [index: string]: any } = {};

    /**
     * Constructor.
     *
     * Accepts storage instance.
     *
     * @param {ICacheMiddlewareStore|undefined} storage
     */
    public constructor(protected storage?: ICacheMiddlewareStore) {}

    //noinspection JSMethodCanBeStatic
    /**
     * Returns a Promise that resolves to Response object using cached payload and headers.
     *
     * @param str
     * @return {Promise}
     */
    public unstringifyResponse(str: string): IFetchResponse<any> | null {
        let entry: ICacheEntry;

        try {
            entry = JSON.parse(str);
        } catch (_e) {
            // Data is corrupt or empty CacheMiddleware invalid, proceed normally
            return null;
        }

        // json can be lots of things, we are only interested in real non-null objects.
        if (entry === null || typeof entry !== 'object') {
            return null;
        }

        const response: IFetchResponse<any> = new (Response as any)(entry.value, entry);

        response.cache = {
            fromCache: true,
            timestamp: entry.timestamp,
            age: Date.now() - entry.timestamp,
        };

        return response;
    }

    public stringifyResponse(response: IFetchResponse<any>): Promise<string> {
        const clone: IFetchResponse<any> = response.clone();

        const headers: IFetchHeaders = {};
        if (clone.headers) {
            clone.headers.forEach((value: string, name: string) => (headers[name] = value));
        }

        return clone.text().then((value: string) => {
            return JSON.stringify({
                value,
                type: clone.type,
                url: clone.url,
                status: clone.status,
                statusText: clone.statusText,
                timestamp: Date.now(),
                headers,
            });
        });
    }

    //noinspection JSMethodCanBeStatic
    /**
     * Gets the normalized cache key which is unique to request with respect to uri and params.
     *
     * @param  {IFetchRequest} options
     * @return {string}
     */
    public getCacheKey(options: IFetchRequest): string {
        if (options.cache?.key) {
            return options.cache.key;
        }

        const url: string = (options.baseUrl || '') + (options.url || '');

        let key: string = `${options.method || 'GET'}_${url}`;

        if (options.queryParameters) {
            key += `?${queryParamsToQueryString(options.queryParameters).toString()}`;
        }

        // @todo Maybe hashing?
        return key;
    }

    //noinspection JSMethodCanBeStatic
    public preprocess(cache: IFetchRequestCacheOptions): IFetchRequestCacheOptions {
        // only accept non-null objects, return all else
        if (typeof cache !== 'object' || cache === null) {
            return cache;
        }

        // todo: if explicitly disabled: should we return undefined here?

        // not explicitly disabled, but has a maxAge? implicitly enable
        if (!cache.enable && cache.maxAge && cache.enable !== false && cache.maxAge > 0) {
            cache.enable = true;
        }

        return cache;
    }

    /**
     * Process the request. First try if we have cache and serve right away,
     * else let the next middleware in pipeline be invoked and cache it.
     *
     * @param  {IFetchRequest}                                   options
     * @param  {(FetchRequest) => Promise<FetchResponse<any>>}  next
     * @return {any}
     */
    public process(options: IFetchRequest, next: (nextOptions: IFetchRequest) => Promise<IFetchResponse<any>>): any {
        // CacheMiddleware not configured
        if (!options.cache) {
            return next(options);
        }

        // preprocess cache config and opt out if explicitly disabled
        options.cache = this.preprocess(options.cache);
        if (!options.cache.enable) {
            return next(options);
        }

        const key = this.getCacheKey(options);
        const entry = this.getCache(key);

        // No entry found or corrupt, pass to next and cache the response
        if (!entry || !entry.cache || !entry.cache.timestamp || entry.cache.age === undefined) {
            return next(options).then((response) => this.setCache(key, response));
        }

        // Cache entry expired, pass to next and replace the cached response
        if (options.cache.maxAge && options.cache.maxAge > 0 && options.cache.maxAge < entry.cache.age) {
            return next(options).then((response) => this.setCache(key, response));
        }

        // format and return cached entry
        return entry;
    }

    /**
     * Persists the cache to storage configured, or in memory if that fails.
     *
     * @param   {string}        key
     * @param   {IFetchResponse} response
     *
     * @return  Promise<IFetchResponse<any>>
     */
    public setCache<T>(key: string, response: IFetchResponse<T>): Promise<IFetchResponse<T>> {
        return this.stringifyResponse(response).then((value) => {
            if (this.storage) {
                try {
                    this.storage.setItem(key, value);
                } catch (_e) {
                    this.fallbackStorage[key] = value;
                }
            } else {
                this.fallbackStorage[key] = value;
            }

            return response;
        });
    }

    public getCache(key: string): IFetchResponse<any> | null {
        const entryJson = this.storage?.getItem(key) || (key in this.fallbackStorage && this.fallbackStorage[key]);

        return this.unstringifyResponse(entryJson);
    }
}
