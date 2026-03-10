"use client";
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import type { ReactNode } from 'react';

export const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 1000 * 60 * 5, // Dữ liệu được coi là "tươi" trong 5 phút (không tự động gọi lại API)
            refetchOnWindowFocus: false, // Tắt tính năng tự động gọi lại API khi người dùng chuyển tab/quay lại trình duyệt
            retry: 1, // Nếu API lỗi, chỉ thử gọi lại 1 lần thay vì 3 lần như mặc định
        },
    },
});


export const QueryProvider = ({ children }: { children: ReactNode }) => {

    return (
        <QueryClientProvider client={queryClient}>
            {children}
            <ReactQueryDevtools
                initialIsOpen={false}
                buttonPosition="bottom-right"
            />
        </QueryClientProvider>
    );
};
