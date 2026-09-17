export type User = {
    id?: number;
    id_user?: number;
    name?: string;
    nama_lengkap?: string;
    username?: string;
    email?: string;
    avatar?: string;
    role?: string | null;
    id_sekolah?: number | null;
    sekolah?: {
        id_sekolah: number;
        kode_sekolah: string | null;
        nama_sekolah: string | null;
    } | null;
    email_verified_at?: string | null;
    two_factor_enabled?: boolean;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
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
