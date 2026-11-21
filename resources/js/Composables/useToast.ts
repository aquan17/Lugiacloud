import { reactive } from 'vue';

export interface ToastItem {
    id: number;
    type: 'success' | 'error' | 'info';
    message: string;
}

const state = reactive<{ items: ToastItem[] }>({
    items: [],
});

let nextId = 1;

export function useToast() {
    function push(message: string, type: ToastItem['type'] = 'success', duration = 3400) {
        if (!message) return;
        const id = nextId++;
        state.items.push({ id, type, message });
        setTimeout(() => {
            remove(id);
        }, duration);
    }

    function remove(id: number) {
        const idx = state.items.findIndex((t) => t.id === id);
        if (idx !== -1) state.items.splice(idx, 1);
    }

    return {
        toasts: state.items,
        success: (msg: string) => push(msg, 'success'),
        error: (msg: string) => push(msg, 'error', 4800),
        info: (msg: string) => push(msg, 'info'),
        remove,
    };
}
