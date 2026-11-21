<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue';
import axios from 'axios';
import { Search, Loader2, Check, X } from 'lucide-vue-next';

export interface PickedUser {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
}

const props = defineProps<{
    modelValue: number | '';
    placeholder?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', val: number | ''): void;
}>();

const query = ref('');
const results = ref<PickedUser[]>([]);
const loading = ref(false);
const hasSearched = ref(false);
const selected = ref<PickedUser | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let abortCtrl: AbortController | null = null;

watch(
    () => props.modelValue,
    (val) => {
        if (!val) {
            selected.value = null;
        }
    }
);

watch(query, (val) => {
    const trimmed = val.trim();
    if (debounceTimer) clearTimeout(debounceTimer);
    if (abortCtrl) abortCtrl.abort();

    if (trimmed.length === 0) {
        results.value = [];
        hasSearched.value = false;
        loading.value = false;
        return;
    }

    loading.value = true;
    debounceTimer = setTimeout(async () => {
        abortCtrl = new AbortController();
        try {
            const { data } = await axios.get('/admin/users/search', {
                params: { q: trimmed },
                signal: abortCtrl.signal,
            });
            results.value = Array.isArray(data?.users) ? data.users : [];
            hasSearched.value = true;
        } catch (err: any) {
            if (axios.isCancel(err) || err?.code === 'ERR_CANCELED') return;
            results.value = [];
            hasSearched.value = true;
        } finally {
            loading.value = false;
        }
    }, 200);
});

function pickUser(u: PickedUser) {
    selected.value = u;
    emit('update:modelValue', u.id);
}

function clearSelection() {
    selected.value = null;
    emit('update:modelValue', '');
}

onUnmounted(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
    if (abortCtrl) abortCtrl.abort();
});
</script>

<template>
    <div class="space-y-2.5 text-xs">
        <div v-if="selected" class="p-3 rounded-xl bg-blue-50/70 border border-blue-200 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <div class="font-bold text-[#0B1437] truncate">
                    #{{ selected.id }} — {{ selected.name }}
                </div>
                <div class="font-mono text-[11px] text-[#1D6AE5] truncate">
                    {{ selected.email }}
                </div>
            </div>
            <button
                type="button"
                @click="clearSelection"
                title="Chọn tài khoản khác"
                class="p-1 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-white cursor-pointer shrink-0"
            >
                <X class="w-4 h-4" />
            </button>
        </div>

        <div class="relative">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
                v-model="query"
                type="text"
                :placeholder="placeholder || 'Gõ email, tên, SĐT, ID hoặc IP dịch vụ của khách...'"
                class="w-full pl-8 pr-8 py-2 rounded-xl border border-slate-300 bg-white text-xs font-medium focus:outline-none focus:border-[#1D6AE5]"
            />
            <Loader2 v-if="loading" class="w-3.5 h-3.5 text-[#1D6AE5] animate-spin absolute right-3 top-1/2 -translate-y-1/2" />
        </div>

        <div v-if="results.length > 0" class="max-h-52 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100 bg-white">
            <button
                v-for="u in results"
                :key="u.id"
                type="button"
                @click="pickUser(u)"
                class="w-full px-3 py-2 text-left hover:bg-blue-50/60 transition flex items-center justify-between gap-2 cursor-pointer"
                :class="modelValue === u.id ? 'bg-blue-50' : ''"
            >
                <div class="min-w-0">
                    <div class="font-bold text-[#0B1437] truncate">#{{ u.id }} — {{ u.name }}</div>
                    <div class="font-mono text-[11px] text-slate-600 truncate">
                        {{ u.email }}<span v-if="u.phone"> · {{ u.phone }}</span>
                    </div>
                </div>
                <Check v-if="modelValue === u.id" class="w-4 h-4 text-[#1D6AE5] shrink-0" />
            </button>
        </div>

        <div v-else-if="hasSearched && !loading" class="text-[11px] text-rose-600 font-semibold px-1">
            Không tìm thấy tài khoản nào khớp từ khóa "{{ query }}".
        </div>
        <div v-else-if="!selected" class="text-[11px] text-slate-500 px-1">
            Nhập tối thiểu 1 ký tự (email, tên, ID hoặc IP máy chủ) để tra cứu nhanh tối đa 15 kết quả.
        </div>
    </div>
</template>
