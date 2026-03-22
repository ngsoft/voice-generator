import { basePath, tap } from '$sdk';
import {
    Client,
    ContentTypeMiddleware,
    FormDataMiddleware,
    HttpErrorMiddleware,
    type IFetchRequest,
    Service,
    WrapMiddleware,
} from './client';

export class Sdk extends Service {
    public static instance?: Sdk;

    public static getInstance(baseUrl?: string): Sdk {
        return (this.instance ??= this.createClient(baseUrl ?? basePath()));
    }

    public static createClient(baseUrl: string): Sdk {
        const client = tap(new Client({ baseUrl }), (c: Client) => {
            c.addMiddleware(new HttpErrorMiddleware());
            c.addMiddleware(new WrapMiddleware());
            c.addMiddleware(new ContentTypeMiddleware());
            c.addMiddleware(new FormDataMiddleware());
        });
        return new Sdk(client);
    }

    async request<T = any>(url: string, options?: IFetchRequest & { data?: Record<string, any> }): Promise<T> {
        (options ??= {}).url = url;
        if (options.data) {
            options.method ??= 'POST';
            options.body = JSON.stringify(options.data);
            (options.headers ??= {})['content-type'] = 'application/json';
            delete options.data;
        }
        return await this.client.process(options);
    }
}
