import { JSON } from '$sdk';
import { formDataToObject, queryStringToObject } from '../internal/formatting';
import type { IFetchRequest, IFetchResponse, IMiddleware } from '../types';
import { ContentTypeMiddleware } from './ContentTypeMiddleware';
import { MockMiddleware } from './MockMiddleware';

export class JsonRequestMiddleware implements IMiddleware<IFetchRequest, Promise<IFetchResponse<any>>> {
    process(
        options: IFetchRequest,
        next: (nextOptions: IFetchRequest) => Promise<IFetchResponse<any>>
    ): Promise<IFetchResponse<any>> {
        /**
         * Replaces body with JSON
         * Fetch does not handle JSON body on GET|HEAD
         */
        if (options.method && !['get', 'head'].includes(options.method.toLowerCase())) {
            // add content-type
            new ContentTypeMiddleware().process(
                options,
                // called but does nothing
                async (_: IFetchRequest): Promise<IFetchResponse<any>> => MockMiddleware.jsonResponse(null)
            );
            if (options.queryParameters) {
                options.body = JSON.stringify(options.queryParameters);
                delete options.queryParameters;
            } /** Experimental feature */ else if (
                ('string' === typeof options.body
                    && options.body.length
                    && !(options.body.startsWith('{') && options.body.endsWith('}')))
                || options.body instanceof URLSearchParams
            ) {
                options.body = JSON.stringify(queryStringToObject(options.body));
            } /** Preferred method if posting form */ else if (options.body instanceof FormData) {
                options.body = JSON.stringify(formDataToObject(options.body));
            }
        }

        return next(options);
    }
}
