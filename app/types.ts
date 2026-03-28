export interface Provider {
    [name: string]: string;
}

export interface Voice {
    lang: string;
    name: string;
    friendlyName: string;
    voiceUri: string;
}

export interface Langs {
    [prefix: string]: string[];
}
