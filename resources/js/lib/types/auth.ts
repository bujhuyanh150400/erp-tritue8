export enum UserRole {
    Admin = 0,
    Teacher = 1,
    Staff = 2,
    Student = 3,
}
export type User = {
    id: number;
    username: string;
    role: UserRole;
    created_at: string;
    updated_at: string;
};


export type Auth = {
    user: User;
};
