<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, HardDrive, AlertCircle, Loader2, Eye, EyeOff, Maximize2 } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: boolean;
    vpsId: number;
    vpsLabel: string;
    vpsState: string;
    currentDisk: number;
    currentPassword?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', val: boolean): void;
    (e: 'submitted'): void;
}>();

const showPass = ref(false);

const form = useForm({
    password_vps: props.currentPassword || '',
});

const isRunning = computed(() => props.vpsState === 'running');

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            form.clearErrors();
            form.password_vps = props.currentPassword || '';
            showPass.value = false;
        }
    }
);

function closeModal() {
    if (form.processing) return;
    emit('update:modelValue', false);
}

function submit() {
    if (form.processing || !isRunning.value) return;
    form.post(`/vps/${props.vpsId}/extend-disk`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('submitted');
            emit('update:modelValue', false);
        },
    });
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && props.modelValue) {
        closeModal();
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
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
                aria-labelledby="extend-disk-modal-title"
                @click.self="closeModal"
            >
                <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-3 bg-slate-50/80">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-600 flex items-center justify-center shrink-0">
                                <HardDrive class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 id="extend-disk-modal-title" class="text-base font-extrabold text-[#0B1437] truncate">
                                    Mở rộng phân vùng ổ cứng SSD
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5 truncate">
                                    Máy chủ {{ vpsLabel }} · Tổng dung lượng hiện tại: <strong class="text-slate-700 font-mono-num">{{ currentDisk }} GB</strong>
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
                    <form @submit.prevent="submit" class="p-6 space-y-4">
                        <div
                            v-if="!isRunning"
                            class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-2.5 text-xs text-amber-900"
                        >
                            <AlertCircle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                            <span>Máy chủ cần ở trạng thái <strong>Đang chạy (Bật)</strong> để hệ thống tự động nới phân vùng ổ cứng.</span>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90 text-xs text-slate-700 leading-relaxed">
                            Tính năng <strong>miễn phí</strong> giúp tự động gộp phần dung lượng SSD vừa mua thêm vào phân vùng chính của Windows/Linux mà <strong>không làm mất dữ liệu</strong>. Trong lúc xử lý, máy chủ sẽ tạm bảo trì dưới 5 phút rồi tự khởi động lại.
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-slate-700">Mật khẩu hiện tại của VPS</label>
                                <button
                                    type="button"
                                    @click="showPass = !showPass"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 hover:text-slate-800 cursor-pointer"
                                >
                                    <component :is="showPass ? EyeOff : Eye" class="w-3.5 h-3.5" />
                                    <span>{{ showPass ? 'Ẩn' : 'Hiện' }}</span>
                                </button>
                            </div>
                            <input
                                v-model="form.password_vps"
                                :type="showPass ? 'text' : 'password'"
                                required
                                placeholder="Nhập mật khẩu đăng nhập hiện tại của VPS"
                                class="w-full h-10 px-3.5 rounded-xl bg-white border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none text-[13px] font-mono transition-colors"
                            />
                            <p v-if="form.errors.password_vps" class="text-xs font-semibold text-rose-600 mt-1">
                                {{ form.errors.password_vps }}
                            </p>
                            <p class="text-[11.5px] text-slate-500 mt-1.5">
                                Hệ thống kiểm tra mật khẩu trước khi thực hiện (có thể mất 10 – 60 giây).
                            </p>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                @click="closeModal"
                                :disabled="form.processing"
                                class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer transition-colors disabled:opacity-50"
                            >
                                Hủy bỏ
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing || !isRunning"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0B1437] hover:bg-[#162354] disabled:opacity-45 text-white text-xs font-bold cursor-pointer transition-colors"
                            >
                                <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                                <Maximize2 v-else class="w-4 h-4" />
                                <span>{{ form.processing ? 'Đang gửi yêu cầu...' : 'Mở rộng ổ cứng ngay' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
