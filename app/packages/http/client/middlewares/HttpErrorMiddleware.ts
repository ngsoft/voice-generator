import type { IFetchRequest, IFetchResponse, IMiddleware } from '../types';

export class HttpError extends Error {
    constructor(
        message: string,
        public response?: IFetchResponse<any>
    ) {
        super(message);
    }
}

export class HttpErrorMiddleware implements IMiddleware<IFetchRequest, Promise<IFetchResponse<any>>> {
    constructor(
        private errorDetectFn?: (response: IFetchResponse<any>) => boolean,
        private errorMessageFn?: (response: IFetchResponse<any>) => string
    ) {}
    async process(
        options: IFetchRequest,
        next: (nextOptions: IFetchRequest) => Promise<IFetchResponse<any>>
    ): Promise<IFetchResponse<any>> {
        const detect =
            this.errorDetectFn ?? (({ status }: IFetchResponse<any>): boolean => !(status >= 200 && status < 400));

        const resp = await next(options);
        if (detect(resp)) {
            const msg =
                    this.errorMessageFn
                    ?? (({ status }: IFetchResponse<any>): string => {
                        if (status >= 500) {
                            return 'Server Error';
                        }
                        if (status >= 400) {
                            return 'Client Error';
                        }
                        return 'Unsuccessful Request';
                    }),
                message = msg(resp);
            throw new HttpError(message, resp);
        }

        return resp;
    }
}
