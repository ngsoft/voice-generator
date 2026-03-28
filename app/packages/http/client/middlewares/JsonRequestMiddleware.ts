import { JSON } from '$sdk';
import { formDataToObject, queryStringToObject } from '../internal/formatting';
import type { IFetchRequest, IFetchResponse, IMiddleware } from '../types';

export class JsonRequestMiddleware implements IMiddleware<IFetchRequest, Promise<IFetchResponse<any>>> {
    private _isJsonString(input: string) {
        if (input.startsWith('{') && input.endsWith('}')) {
            return true;
        }
        return input.startsWith('[') && input.endsWith(']');
    }

    process(
        options: IFetchRequest,
        next: (nextOptions: IFetchRequest) => Promise<IFetchResponse<any>>
    ): Promise<IFetchResponse<any>> {
        /**
         * Replaces body with JSON
         * Fetch does not handle JSON body on GET|HEAD
         */
        if (options.method && !['get', 'head'].includes(options.method.toLowerCase())) {
            let add = false;

            if (options.body instanceof FormData) {
                options.body = JSON.stringify(formDataToObject(options.body));
                add = true;
            } else if (options.body instanceof URLSearchParams) {
                options.body = JSON.stringify(queryStringToObject(options.body));
                add = true;
            } else if ('string' === typeof options.body && this._isJsonString(options.body)) {
                add = true;
            }
            // add content-type
            if (add) {
                (options.headers ??= {})['content-type'] = 'application/json';
            }
        }

        return next(options);
    }
}
