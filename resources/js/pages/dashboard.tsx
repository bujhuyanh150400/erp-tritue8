import type { ReactNode } from 'react';
import Layout from '@/components/layouts/admin/layout';

export default function Page() {
    return <>Bảng điều khiển</>;
}

Page.layout = (page: ReactNode) => (
    <Layout breadcrumbs={[{ title: 'Bảng điều khiển' }]}>{page}</Layout>
);
