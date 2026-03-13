import { debounce } from 'lodash';
import { useEffect, useRef, useMemo } from 'react';

export const useDebounce = (callback: (...args: any[]) => void, delay: number) => {
    const callbackRef = useRef(callback);

    // Cập nhật ref
    useEffect(() => {
        callbackRef.current = callback;
    }, [callback]);

    // Tạo hàm debounce và lưu vào useMemo
    const debouncedFn = useMemo(() => {
        const factory = (...args: any[]) => callbackRef.current(...args);
        // eslint-disable-next-line react-hooks/refs
        return debounce(factory, delay);
    }, [delay]);

    useEffect(() => {
        return () => debouncedFn.cancel();
    }, [debouncedFn]);

    return debouncedFn;
};
