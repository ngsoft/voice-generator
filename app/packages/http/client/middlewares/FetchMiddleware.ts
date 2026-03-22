import { combineUrlWithBaseUrl, combineUrlWithQueryParameters } from '../internal/formatting';
import type { IFetchRequest, IFetchResponse, IMiddleware } from '../types';

//noinspection TsLint
declare const fetch: (url: string, options: any) => Promise<IFetchResponse<any>>;

export class FetchMiddleware implements IMiddleware<IFetchRequest, Promise<IFetchResponse<any>>> {
    constructor(public defaultOptions: IFetchRequest = {}) {}

    public preprocess(options: IFetchRequest): IFetchRequest {
        // Merge with the defaults
        options = Object.assign({}, this.defaultOptions, options);
        // Construct target Uri
        options.url = combineUrlWithBaseUrl(options.url, options.baseUrl);
        // Append query parameters
        options.url = combineUrlWithQueryParameters(options.url, options.queryParameters);
        return options;
    }

    public process(
        options: IFetchRequest /*, next: (nextOptions: FetchRequest) => Promise<IFetchResponse<any>>*/
    ): Promise<IFetchResponse<any>> {
        // validate and transform options
        options = this.preprocess(options);
        // fire fetch request
        return fetch(options.url as string, options);
    }
}
