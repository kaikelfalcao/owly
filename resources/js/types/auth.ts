export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
};

export type Organization = {
    id: number;
    name: string;
    timezone: string;
};

export type Auth = {
    user: User;
    organization: Organization | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
