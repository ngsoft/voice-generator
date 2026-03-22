export interface IMiddleware<IN, OUT> {
    process(options: IN, next?: (nextOptions: IN) => OUT): OUT;
}

export interface IFetchHeaders {
    [key: string]: string;

    forEach?: any;
}

// Represents nature of cache entry that is persisted into the configured engine.
export interface ICacheEntry {
    value: string; // text representation of response payload
    type: string; // the response type.
    url: string; // the url that responded original cache response
    status: number; // http response status
    statusText: string; // http response status text. eg: OK
    timestamp: number; // create timestamp in milliseconds: +Date.now()
    headers: { [index: string]: any }; // response headers
}

// Represents cache options for each Request
export interface IFetchRequestCacheOptions {
    enable: boolean;
    maxAge?: number; // cache TTL in seconds, defaults to 0 = forever
    key?: string; // optionally a specific key to be used in place of the generated hash
}

// Represents cache flags for Response
export interface IFetchResponseCacheOptions {
    fromCache?: boolean; // marks a result as being from the cache
    timestamp?: number; // create timestamp in milliseconds: +Date.now()
    age?: number; // the actual age in seconds
}

// Represents the storage engine functionality
export interface ICacheMiddlewareStore {
    setItem(key: string, value: string): void;

    getItem(key: string): string | undefined;

    removeItem(key: string): void;
}

export interface IFetchResponse<T> {
    body: string;
    bodyUsed: boolean;
    headers?: IFetchHeaders;
    ok: boolean;
    status: number;
    statusText: string;
    type: string;
    url: string;
    cache?: IFetchResponseCacheOptions; // signifies that response has been reconstructed from cache
    json: () => Promise<T>;
    text: () => Promise<string>;
    clone: () => IFetchResponse<T>;
}

export type TCredentials = 'include' | 'omit' | 'same-origin';

export interface IFetchRequest {
    url?: string;
    credentials?: TCredentials;
    baseUrl?: string;
    method?: string;
    queryParameters?: Record<string, string | string[]>;
    headers?: IFetchHeaders;
    body?: BodyInit;
    cache?: IFetchRequestCacheOptions;
}

export type ServiceRequest = IFetchRequest & {};

export interface IMockMiddlewareHandler<IN, OUT> {
    match: (options: IN) => boolean | undefined;
    resultFactory: (options: IN) => OUT;
    delay?: number;
}
