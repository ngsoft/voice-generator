import type { IFetchRequest, IFetchResponse, IMiddleware } from '../types';

export class WrapMiddleware implements IMiddleware<IFetchRequest, Promise<IFetchResponse<any>>> {
    public async process(
        options: IFetchRequest,
        next: (nextOptions: IFetchRequest) => Promise<IFetchResponse<any>>
    ): Promise<IFetchResponse<any>> {
        (options.headers ??= {}).accept = 'application/json, */*;q=0.8';
        const response = await next(options);
        return await response.json();
    }
}
