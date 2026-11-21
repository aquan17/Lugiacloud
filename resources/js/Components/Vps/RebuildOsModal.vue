<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import OsIcon from '@/Components/OsIcon.vue';
import { X, AlertTriangle, Loader2, RefreshCw } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: boolean;
    vpsId: number;
    vpsLabel: string;
    currentOsId: number;
    images: Array<{ id: number; label: string; distro: string; group: string }>;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', val: boolean): void;
}>();

const osFilter = ref<'all' | 'Windows' | 'Linux'>('all');
const confirmedWipe = ref(false);

const rebuildForm = useForm({
    os_id: props.currentOsId || props.images[0]?.id || 7,
});

const filteredImages = computed(() => {
    if (osFilter.value === 'all') return props.images;
    return props.images.filter((img) =>
        osFilter.value === 'Windows'
            ? img.distro === 'windows' || img.group?.toLowerCase().includes('win')
            : img.distro !== 'windows' && !img.group?.toLowerCase().includes('win')
    );
});

function closeModal() {
    if (rebuildForm.processing) return;
    confirmedWipe.value = false;
    emit('update:modelValue', false);
}

function submitRebuild() {
    if (!confirmedWipe.value || rebuildForm.processing) return;
    rebuildForm.post(`/vps/${props.vpsId}/rebuild`, {
        preserveScroll: true,
        onSuccess: () => {
            confirmedWipe.value = false;
            emit('update:modelValue', false);
        },
    });
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55 backdrop-blur-xs"
            @click.self="closeModal"
        >
            <div class="w-full max-w-2xl bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/80">
                    <div>
                        <h3 class="text-base font-extrabold text-[#0B1437]">
                            Cài đặt lại Hệ điều hành — {{ vpsLabel }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Chọn bản cài đặt Windows hoặc Linux sạch cho máy chủ (hoàn tất sau 2–5 phút)
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeModal"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Body -->
                <form @submit.prevent="submitRebuild" class="p-6 space-y-5">
                    <!-- Filter Tabs -->
                    <div class="inline-flex p-1 rounded-xl bg-slate-100 border border-slate-200 text-xs font-bold">
                        <button
                            v-for="tab in [
                                { id: 'all', label: 'Tất cả Hệ điều hành' },
                                { id: 'Windows', label: 'Windows Server / Win 10' },
                                { id: 'Linux', label: 'Linux (Ubuntu / CentOS / Debian)' },
                            ]"
                            :key="tab.id"
                            type="button"
                            @click="osFilter = tab.id as any"
                            class="px-3 py-1.5 rounded-lg cursor-pointer transition-colors"
                            :class="osFilter === tab.id ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- OS Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-[280px] overflow-y-auto pr-1">
                        <button
                            v-for="img in filteredImages"
                            :key="img.id"
                            type="button"
                            @click="rebuildForm.os_id = img.id"
                            class="p-3.5 rounded-xl border text-left flex items-center gap-3 transition-all cursor-pointer"
                            :class="
                                rebuildForm.os_id === img.id
                                    ? 'border-[#1D6AE5] bg-blue-50/50 ring-1 ring-[#1D6AE5]'
                                    : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/60'
                            "
                        >
                            <OsIcon :distro="img.distro" :size="22" />
                            <div class="min-w-0">
                                <div class="text-[13.5px] font-bold text-[#0B1437] truncate">{{ img.label }}</div>
                                <div class="text-[11px] text-slate-500">Bản gốc sạch 64-bit</div>
                            </div>
                        </button>
                    </div>

                    <!-- Destructive Warning & Confirmation -->
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 space-y-3">
                        <div class="flex items-start gap-2.5 text-[13px] text-rose-900 leading-relaxed">
                            <AlertTriangle class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                            <span>
                                <strong>Cảnh báo mất dữ liệu:</strong> Quá trình cài lại Hệ điều hành sẽ format toàn bộ ổ cứng SSD hiện tại. Địa chỉ IPv4 giữ nguyên, mật khẩu mới sẽ tự động cập nhật sau khi cài xong.
                            </span>
                        </div>

                        <label class="flex items-center gap-2.5 text-[13px] font-semibold text-rose-950 cursor-pointer select-none">
                            <input
                                v-model="confirmedWipe"
                                type="checkbox"
                                class="w-4 h-4 rounded border-rose-300 text-rose-600 focus:ring-rose-500 cursor-pointer"
                            />
                            <span>Tôi xác nhận đồng ý xóa toàn bộ dữ liệu cũ để cài lại Hệ điều hành mới.</span>
                        </label>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-2.5 pt-1">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer"
                        >
                            Hủy bỏ
                        </button>
                        <button
                            type="submit"
                            :disabled="!confirmedWipe || rebuildForm.processing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 disabled:opacity-45 text-white text-xs font-bold cursor-pointer transition-colors"
                        >
                            <Loader2 v-if="rebuildForm.processing" class="w-4 h-4 animate-spin" />
                            <RefreshCw v-else class="w-4 h-4" />
                            <span>{{ rebuildForm.processing ? 'Đang gửi lệnh cài đặt...' : 'Bắt đầu Cài đặt lại OS' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
