import { formDataToObject } from '../internal/formatting';
import type { IFetchRequest, IFetchResponse, IMiddleware } from '../types';

export class FormDataMiddleware implements IMiddleware<IFetchRequest, Promise<IFetchResponse<any>>> {
    process(
        options: IFetchRequest,
        next: (nextOptions: IFetchRequest) => Promise<IFetchResponse<any>>
    ): Promise<IFetchResponse<any>> {
        if (options.body instanceof FormData) {
            if (!options.method || ['get', 'head'].includes(options.method.toLowerCase())) {
                options.queryParameters = { ...(options.queryParameters ?? {}), ...formDataToObject(options.body) };
                delete options.body;
            } else if (options.headers?.['content-type']) {
                delete options.headers['content-type'];
            }
        }
        return next(options);
    }
}
