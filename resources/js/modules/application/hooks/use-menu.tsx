import { Book, CircleUserRound, LayoutDashboard, Users, School } from 'lucide-react';
import { useMemo } from 'react';
import { index as dashboardIndex } from '@/actions/App/Http/Controllers/DashboardController';
import { listRoom } from '@/actions/App/Http/Controllers/RoomController';
import { listStudent } from '@/actions/App/Http/Controllers/StudentController';
import { listSubject } from '@/actions/App/Http/Controllers/SubjectController';
import { listTeacher } from '@/actions/App/Http/Controllers/TeacherController';
import type { IMenu, User } from '@/lib/types';
import { UserRole } from '@/lib/types';
import { isActiveUrl } from '@/lib/utils';

/**
 * Lấy menu theo role của user
 * @param user
 * @param url
 */
export const useMenu: (user: User, url: string) => IMenu[] = (user: User, url: string) => {


    return useMemo(() => {
        switch (user.role) {
            case UserRole.Admin:
               return [
                   {
                       title: 'Bảng điều khiển',
                       url: dashboardIndex().url,
                       icon: <LayoutDashboard />,
                       is_menu: true,
                       active: isActiveUrl('/admin/dashboard',url, true),
                   },
                   {
                       title: 'Học vụ',
                       is_menu: false,
                   },
                   {
                       title: 'Môn học',
                       url: listSubject().url,
                       icon: <Book />,
                       is_menu: true,
                       active: isActiveUrl(['/admin/subject'],url),
                   },
                   {
                       title: 'Lớp học',
                       url: listRoom().url,
                       icon: <School />,
                       is_menu: true,
                       active: isActiveUrl(['/admin/room'],url),
                   },
                   {
                       title: 'Người dùng',
                       is_menu: false,
                   },
                   {
                       title: 'Học sinh',
                       url: listStudent().url,
                       icon: <Users />,
                       is_menu: true,
                       active: isActiveUrl(['/admin/student'],url),
                   },
                   {
                       title: 'Giáo viên',
                       url: listTeacher().url,
                       icon: <CircleUserRound />,
                       is_menu: true,
                       active: isActiveUrl(['/admin/teacher'],url),
                   },
               ];
            default:
                return [];
        }
    }, [url, user.role]);
};
