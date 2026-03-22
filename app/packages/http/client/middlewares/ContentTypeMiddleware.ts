import type { IFetchRequest, IFetchResponse, IMiddleware } from '../types';

export class ContentTypeMiddleware implements IMiddleware<IFetchRequest, Promise<IFetchResponse<any>>> {
    constructor(private contentType: string = 'application/json') {}

    public process(
        options: IFetchRequest,
        next: (nextOptions: IFetchRequest) => Promise<IFetchResponse<any>>
    ): Promise<IFetchResponse<any>> {
        (options.headers ??= {})['content-type'] = this.contentType;
        return next(options);
    }
}
