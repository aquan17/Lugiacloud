import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

export interface LoginPromptOptions {
    actionLabel?: string;
    redirectUrl?: string;
    title?: string;
    description?: string;
}

const DEFAULT_TITLE = 'Đăng nhập để tiếp tục';
const DEFAULT_DESCRIPTION = 'Vui lòng đăng nhập hoặc tạo tài khoản LugiaCloud để tiếp tục thao tác này.';

const AUTH_PROTECTED_PREFIXES = [
    '/dashboard',
    '/store',
    '/vps',
    '/proxy',
    '/topup',
    '/billing',
    '/invoices',
    '/affiliate',
    '/reseller-api',
    '/profile',
    '/notifications',
    '/vouchers',
    '/admin',
];

export const loginPromptState = reactive({
    open: false,
    title: DEFAULT_TITLE,
    description: DEFAULT_DESCRIPTION,
    actionLabel: '',
    redirectUrl: '',
});

export function isAuthProtectedPath(rawUrl?: string): boolean {
    const url = (rawUrl || '').trim();
    if (!url.startsWith('/') || url.startsWith('//')) return false;
    const pathname = url.split(/[?#]/)[0] || '/';
    return AUTH_PROTECTED_PREFIXES.some(
        (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`)
    );
}

export function openLoginPrompt(options: LoginPromptOptions = {}) {
    loginPromptState.title = options.title || DEFAULT_TITLE;
    loginPromptState.description = options.description || DEFAULT_DESCRIPTION;
    loginPromptState.actionLabel = options.actionLabel || '';
    loginPromptState.redirectUrl = options.redirectUrl || '';
    loginPromptState.open = true;
}

export function closeLoginPrompt() {
    loginPromptState.open = false;
}

export function requireAuthOrVisit(targetUrl: string, actionLabel: string, user: unknown): boolean {
    if (!user) {
        openLoginPrompt({
            actionLabel,
            redirectUrl: targetUrl,
        });
        return false;
    }

    router.visit(targetUrl);
    return true;
}
