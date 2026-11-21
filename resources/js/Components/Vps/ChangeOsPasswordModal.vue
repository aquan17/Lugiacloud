<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, KeyRound, CheckCircle2, AlertCircle, Loader2, ShieldCheck } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: boolean;
    vpsId: number;
    vpsLabel: string;
    vpsState: string;
    currentPassword?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', val: boolean): void;
}>();

const passwordForm = useForm({
    password_old: props.currentPassword || '',
    password_new: '',
    password_new_confirmation: '',
});

const passChecks = computed(() => {
    const p = passwordForm.password_new;
    return {
        len: p.length >= 12,
        mixed: /[a-z]/.test(p) && /[A-Z]/.test(p) && /\d/.test(p),
        alnum: p.length > 0 && /^[a-zA-Z0-9]+$/.test(p),
    };
});

const isRunning = computed(() => props.vpsState === 'running');

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            passwordForm.clearErrors();
            passwordForm.password_old = props.currentPassword || '';
            passwordForm.password_new = '';
            passwordForm.password_new_confirmation = '';
        }
    }
);

function closeModal() {
    if (passwordForm.processing) return;
    emit('update:modelValue', false);
}

function submitPassword() {
    if (passwordForm.processing || !isRunning.value) return;
    passwordForm.post(`/vps/${props.vpsId}/password`, {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset('password_new', 'password_new_confirmation');
            emit('update:modelValue', false);
        },
    });
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && props.modelValue) {
        closeModal();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-120 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="modelValue"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55 backdrop-blur-xs"
                role="dialog"
                aria-modal="true"
                aria-labelledby="change-os-password-title"
                @click.self="closeModal"
            >
                <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-3 bg-slate-50/80">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200/80 text-[#1D6AE5] flex items-center justify-center shrink-0">
                                <KeyRound class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 id="change-os-password-title" class="text-base font-extrabold text-[#0B1437] truncate">
                                    Đổi mật khẩu Hệ điều hành
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5 truncate">
                                    Máy chủ {{ vpsLabel }} · Áp dụng cho tài khoản Root / Administrator
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="closeModal"
                            aria-label="Đóng"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 cursor-pointer shrink-0 transition-colors"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Body -->
                    <form @submit.prevent="submitPassword" class="p-6 space-y-4">
                        <div
                            v-if="!isRunning"
                            class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-2.5 text-xs text-amber-900"
                        >
                            <AlertCircle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                            <span>Máy chủ cần đang ở trạng thái <strong>Đang chạy</strong> mới có thể thực hiện đổi mật khẩu OS.</span>
                        </div>

                        <div class="space-y-3.5">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Mật khẩu hiện tại</label>
                                <input
                                    v-model="passwordForm.password_old"
                                    type="text"
                                    required
                                    class="w-full h-10 px-3.5 rounded-xl bg-slate-50 focus:bg-white border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none text-[13px] font-mono transition-colors"
                                />
                                <p v-if="passwordForm.errors.password_old" class="text-xs font-semibold text-rose-600 mt-1">
                                    {{ passwordForm.errors.password_old }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Mật khẩu mới</label>
                                    <input
                                        v-model="passwordForm.password_new"
                                        type="text"
                                        placeholder="VD: LugiaPass2026"
                                        required
                                        class="w-full h-10 px-3.5 rounded-xl bg-white border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none text-[13px] font-mono transition-colors"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Xác nhận mật khẩu mới</label>
                                    <input
                                        v-model="passwordForm.password_new_confirmation"
                                        type="text"
                                        placeholder="Nhập lại mật khẩu mới"
                                        required
                                        class="w-full h-10 px-3.5 rounded-xl bg-white border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none text-[13px] font-mono transition-colors"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Password checklist -->
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs">
                            <span class="inline-flex items-center gap-1.5" :class="passChecks.len ? 'text-emerald-700 font-semibold' : 'text-slate-500'">
                                <CheckCircle2 class="w-3.5 h-3.5" /> ≥ 12 ký tự
                            </span>
                            <span class="inline-flex items-center gap-1.5" :class="passChecks.mixed ? 'text-emerald-700 font-semibold' : 'text-slate-500'">
                                <CheckCircle2 class="w-3.5 h-3.5" /> Chữ hoa + thường + số
                            </span>
                            <span class="inline-flex items-center gap-1.5" :class="passChecks.alnum ? 'text-emerald-700 font-semibold' : 'text-slate-500'">
                                <CheckCircle2 class="w-3.5 h-3.5" /> Không ký tự đặc biệt
                            </span>
                        </div>

                        <p v-if="passwordForm.errors.password_new" class="text-xs font-semibold text-rose-600">
                            {{ passwordForm.errors.password_new }}
                        </p>

                        <!-- Footer -->
                        <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer transition-colors"
                            >
                                Hủy bỏ
                            </button>
                            <button
                                type="submit"
                                :disabled="passwordForm.processing || !isRunning"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0B1437] hover:bg-[#162354] disabled:opacity-45 text-white text-xs font-bold cursor-pointer transition-colors"
                            >
                                <Loader2 v-if="passwordForm.processing" class="w-4 h-4 animate-spin" />
                                <ShieldCheck v-else class="w-4 h-4" />
                                <span>{{ passwordForm.processing ? 'Đang cập nhật...' : 'Lưu mật khẩu mới' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
