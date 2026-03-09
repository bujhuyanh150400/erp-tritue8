import { Link } from '@inertiajs/react';
import type { FC } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { IBreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: IBreadcrumbItem[];
};
export const AppHeader: FC<Props> = ({ breadcrumbs }) => {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
            <div className="flex items-center gap-2 px-4">
                <SidebarTrigger className="-ml-1" />
                <Separator
                    orientation="vertical"
                    className="mr-2 data-vertical:h-4 data-vertical:self-auto"
                />
                <Breadcrumb>
                    <BreadcrumbList>
                        {breadcrumbs &&
                            breadcrumbs.length > 0 &&
                            breadcrumbs.map((breadcrumb, index) => (
                                <div
                                    className="inline-flex items-center gap-2"
                                    key={index}
                                >
                                    <BreadcrumbItem>
                                        {!breadcrumb.href ? (
                                            <BreadcrumbPage>
                                                {breadcrumb.title}
                                            </BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbPage
                                                className={'hover:opacity-60'}
                                            >
                                                <Link href={breadcrumb.href}>
                                                    {breadcrumb.title}
                                                </Link>
                                            </BreadcrumbPage>
                                        )}
                                    </BreadcrumbItem>
                                    {index < breadcrumbs.length - 1 && (
                                        <BreadcrumbSeparator className="hidden md:block" />
                                    )}
                                </div>
                            ))}
                    </BreadcrumbList>
                </Breadcrumb>
            </div>
        </header>
    );
};
