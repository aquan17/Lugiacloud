<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { formatVnd } from '@/Composables/useFormat';
import { X, Globe, AlertCircle, Loader2, Eye, EyeOff, RefreshCw } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: boolean;
    vpsId: number;
    vpsLabel: string;
    vpsState: string;
    currentIp?: string | null;
    currentPassword?: string | null;
    price: number;
    limitPerMonth: number;
    userBalance: number;
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
const insufficientBalance = computed(() => props.userBalance < props.price);

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
    if (form.processing || !isRunning.value || insufficientBalance.value) return;
    form.post(`/vps/${props.vpsId}/change-ip`, {
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
                aria-labelledby="change-ip-modal-title"
                @click.self="closeModal"
            >
                <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-3 bg-slate-50/80">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200/80 text-[#1D6AE5] flex items-center justify-center shrink-0">
                                <Globe class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <h3 id="change-ip-modal-title" class="text-base font-extrabold text-[#0B1437] truncate">
                                    Đổi địa chỉ IPv4 mới
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5 truncate">
                                    Máy chủ {{ vpsLabel }} · IP hiện tại: <span class="font-mono font-semibold text-slate-700">{{ currentIp || '—' }}</span>
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
                            <span>Máy chủ cần ở trạng thái <strong>Đang chạy (Bật)</strong> mới có thể thực hiện đổi địa chỉ IPv4.</span>
                        </div>

                        <!-- Pricing summary -->
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 font-medium">Phí đổi IPv4 (1 lần):</span>
                                <span class="font-mono-num text-sm font-extrabold text-[#1D6AE5]">{{ formatVnd(price) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 font-medium">Số dư ví hiện tại:</span>
                                <span class="font-mono-num font-bold" :class="insufficientBalance ? 'text-rose-600' : 'text-slate-900'">
                                    {{ formatVnd(userBalance) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-[11.5px] text-slate-500 pt-1 border-t border-slate-200/70">
                                <span>Giới hạn tối đa mỗi VPS:</span>
                                <span class="font-semibold text-slate-700">{{ limitPerMonth }} lần / tháng</span>
                            </div>
                        </div>

                        <!-- Current VPS Password -->
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
                            <p class="text-[11.5px] text-slate-500 mt-1.5 leading-relaxed">
                                Hệ thống xác thực mật khẩu trực tiếp trên VPS trước khi cấp IP mới (nếu bạn đã tự đổi mật khẩu trong OS, hãy nhập mật khẩu mới nhất).
                            </p>
                        </div>

                        <!-- Important Notes -->
                        <div class="p-3.5 rounded-xl bg-amber-50/70 border border-amber-200/80 text-[11.5px] text-amber-950 space-y-1 leading-relaxed">
                            <div class="font-bold text-amber-900">Lưu ý quan trọng:</div>
                            <ul class="list-disc list-inside space-y-0.5 text-amber-900/90">
                                <li>Quá trình xác thực có thể mất <strong>10 – 60 giây</strong>, vui lòng không đóng cửa sổ khi đang gửi.</li>
                                <li>Địa chỉ IP cũ sẽ bị thu hồi vĩnh viễn và không thể khôi phục; phí đổi IP không hoàn lại sau khi yêu cầu được tiếp nhận.</li>
                                <li>IP mới sẽ tự động cập nhật trên trang này sau 2 – 5 phút.</li>
                            </ul>
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

                            <Link
                                v-if="insufficientBalance"
                                href="/topup"
                                class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-colors"
                            >
                                Nạp thêm số dư
                            </Link>

                            <button
                                v-else
                                type="submit"
                                :disabled="form.processing || !isRunning"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] disabled:opacity-45 text-white text-xs font-bold cursor-pointer transition-colors"
                            >
                                <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                                <RefreshCw v-else class="w-4 h-4" />
                                <span>{{ form.processing ? 'Đang xác thực & đổi IP...' : `Xác nhận đổi IP (${formatVnd(price)})` }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
