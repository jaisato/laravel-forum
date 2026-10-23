import type { AuthUser } from '@/types/forum';

/**
 * The signed in member as the starter kit components call it.
 */
export type User = AuthUser;

export type Auth = {
    user: AuthUser | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
