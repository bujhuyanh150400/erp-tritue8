export enum ActiveStatus {
    Inactive = 0,
    Active = 1,
}

export const activeStatusOptions = [
    { label: 'Tất cả trạng thái', value: 'all' },
    { label: 'Đang hoạt động', value: ActiveStatus.Active.toString() },
    { label: 'Không hoạt động', value: ActiveStatus.Inactive.toString() },
];

export const activeStatusFormOptions = [
    { label: 'Đang hoạt động', value: ActiveStatus.Active.toString() },
    { label: 'Không hoạt động', value: ActiveStatus.Inactive.toString() },
];

