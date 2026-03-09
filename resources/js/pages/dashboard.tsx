import type { ReactNode } from 'react';
import Layout from '@/components/layouts/admin/layout';

export default function Page() {
    return (
        <>
            test
        </>
    );
}


Page.layout = (page: ReactNode) => (
    <Layout>
        {page}
    </Layout>
);
