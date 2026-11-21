import { reactive } from 'vue';

export interface ConfirmActionOptions {
    title?: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'danger' | 'primary';
    onConfirm: () => void;
}

export const confirmActionState = reactive<{
    open: boolean;
    title: string;
    message: string;
    confirmText: string;
    cancelText: string;
    variant: 'danger' | 'primary';
    onConfirm: (() => void) | null;
}>({
    open: false,
    title: 'Xác nhận thao tác',
    message: '',
    confirmText: 'Xác nhận',
    cancelText: 'Hủy bỏ',
    variant: 'danger',
    onConfirm: null,
});

export function askConfirm(options: ConfirmActionOptions) {
    confirmActionState.title = options.title || (options.variant === 'primary' ? 'Xác nhận thao tác' : 'Xác nhận xóa');
    confirmActionState.message = options.message;
    confirmActionState.confirmText = options.confirmText || (options.variant === 'primary' ? 'Xác nhận' : 'Xóa ngay');
    confirmActionState.cancelText = options.cancelText || 'Hủy bỏ';
    confirmActionState.variant = options.variant || 'danger';
    confirmActionState.onConfirm = options.onConfirm;
    confirmActionState.open = true;
}

export function closeConfirm() {
    confirmActionState.open = false;
    confirmActionState.onConfirm = null;
}

export function runConfirmedAction() {
    const fn = confirmActionState.onConfirm;
    closeConfirm();
    if (fn) fn();
}
