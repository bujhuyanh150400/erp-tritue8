import axios from 'axios';
import type { IValidationErrors } from '@/lib/types';
import ErrorAPIServer from '@/lib/types/error-api';

export const client = axios.create({
    timeout: 30000, // Set a timeout for requests (30 seconds)
    headers: {
        'X-Requested-With': 'XMLHttpRequest', // Bắt buộc để Laravel biết đây là request AJAX
        Accept: 'application/json',
    },
    withCredentials: true, // Quan trọng nếu bạn dùng Sanctum SPA authentication
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});


client.interceptors.response.use(
    (response) => response,
    (error) => {
        const errorResponse = error.response;
        const errorData = error.response?.data;
        //Nếu có lỗi trả ra từ server
        if (errorResponse && errorData) {
            let messageError: string | null | undefined = errorData.message;
            let statusCodeResponse: number | null | undefined = errorResponse?.status;

            if (!messageError){
                messageError = 'Lỗi không xác định, vui lòng liên hệ với quản trị viên';
            }
            if (!statusCodeResponse) statusCodeResponse = 0;

            if (statusCodeResponse === 422) {
                const errorValidate: IValidationErrors = errorData.errors;
                return Promise.reject(
                    new ErrorAPIServer(
                        statusCodeResponse,
                        messageError,
                        errorResponse,
                        errorValidate,
                    ),
                );
            } else if (statusCodeResponse === 401) {
                window.location.href = '/login';
            } else if (statusCodeResponse === 419) {
                console.warn(
                    'CSRF token mismatch. Đang tải lại trang để lấy token mới...',
                );
                window.location.reload();
            } else {
                return Promise.reject(
                    new ErrorAPIServer(
                        statusCodeResponse,
                        messageError,
                        errorResponse,
                    ),
                );
            }
        } else if (error.request) {
            return Promise.reject(
                new ErrorAPIServer(
                    400,
                    "Có lỗi xảy ra với yêu cầu của bạn, vui lòng liên hệ với quản trị viên",
                    errorResponse,
                ),
            );
        } else {
            return Promise.reject(
                new ErrorAPIServer(
                    500,
                    "Có lỗi xảy ra với hệ thống, vui lòng liên hệ với quản trị viên",
                    errorResponse,
                ),
            );
        }
    },
);
