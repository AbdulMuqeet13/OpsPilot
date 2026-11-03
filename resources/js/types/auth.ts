export type UserRole = 'admin' | 'agent';

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    roleLabel: string;
};

export type Auth = {
    user: AuthUser | null;
    can: {
        manageSettings: boolean;
    };
};
