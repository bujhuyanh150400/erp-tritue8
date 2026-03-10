export interface IValidationErrors {
    [field: string]: string[];
}

interface IErrorAPIServer {
    statusCode: number;
    message: string;
    validateError: IValidationErrors | null;
    rawError: any;
}

export default class ErrorAPIServer implements IErrorAPIServer {
    public statusCode: number;
    public message: string;
    public validateError: IValidationErrors | null;
    public rawError: any;

    constructor(
        statusCode: number,
        message: string,
        rawError: any,
        validateError: IValidationErrors | null = null,
    ) {
        this.statusCode = statusCode;
        this.message = message;
        this.rawError = rawError;
        this.validateError = validateError;
    }
}
