<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    state?: string;
    label?: string;
}>();

const config = computed(() => {
    // Chuẩn hóa state và label về dạng chữ thường không dấu để so khớp chính xác
    const rawState = (props.state || '').trim().toLowerCase();
    const rawLabel = (props.label || '').trim().toLowerCase();
    const combined = `${rawState} ${rawLabel}`.trim();

    // Helper kiểm tra xem nhãn có phải nhãn "bình thường / sẵn sàng" hay không
    const isNormalLabel = rawLabel === 'sẵn sàng' || rawLabel === 'hoạt động' || rawLabel === 'running' || rawLabel === 'active';

    // 1. EXPIRED / OVERDUE: Đỏ san hô (Rose Red)
    // Ưu tiên kiểm tra trạng thái quá hạn trước để tránh nhầm với trạng thái sẵn sàng
    if (
        rawState === 'expired' ||
        rawState === 'overdue' ||
        combined.includes('hết hạn') ||
        combined.includes('quá hạn')
    ) {
        return {
            dot: 'bg-rose-500',
            pill: 'bg-rose-50 text-rose-700 border-rose-200/90',
            pulse: false,
            text: (isNormalLabel || !props.label) ? 'Quá hạn' : props.label,
        };
    }

    // 2. DELETED / TERMINATED / CANCELLED / REFUNDED: Xám nhạt lưu trữ (Zinc/Slate Muted)
    if (
        rawState === 'deleted' ||
        rawState === 'terminated' ||
        rawState === 'cancelled' ||
        rawState === 'rejected' ||
        rawState === 'refunded' ||
        combined.includes('xóa') ||
        combined.includes('xoá') ||
        combined.includes('thu hồi') ||
        combined.includes('đã hủy') ||
        combined.includes('hoàn tiền')
    ) {
        return {
            dot: 'bg-zinc-400',
            pill: 'bg-zinc-100 text-zinc-600 border-zinc-200/90',
            pulse: false,
            text: (isNormalLabel || !props.label) ? 'Đã xóa' : props.label,
        };
    }

    // 3. STOPPED / INACTIVE: Xám trung tính (Slate)
    // Người dùng chủ động tắt máy chủ / ngắt kết nối (bình thường, KHÔNG phải lỗi)
    if (
        rawState === 'stopped' ||
        rawState === 'off' ||
        rawState === 'inactive' ||
        rawState === 'disabled' ||
        rawState === 'paused' ||
        combined.includes('đã tắt') ||
        combined.includes('tắt nguồn') ||
        combined.includes('tạm ngưng') ||
        combined.includes('đang dừng')
    ) {
        return {
            dot: 'bg-slate-400',
            pill: 'bg-slate-100 text-slate-700 border-slate-200/90',
            pulse: false,
            text: (isNormalLabel || !props.label) ? 'Đã tắt' : props.label,
        };
    }

    // 4. PROVISIONING / PROCESSING / REBOOTING / PENDING: Xanh dương (Blue) với hiệu ứng Ping
    if (
        rawState === 'provisioning' ||
        rawState === 'rebooting' ||
        rawState === 'rebuilding' ||
        rawState === 'pending' ||
        rawState === 'processing' ||
        rawState === 'creating' ||
        rawState === 'installing' ||
        combined.includes('khởi tạo') ||
        combined.includes('xử lý') ||
        combined.includes('chờ') ||
        combined.includes('reboot') ||
        combined.includes('đổi ip') ||
        combined.includes('bảo trì') ||
        combined.includes('mở rộng') ||
        combined.includes('nâng cấp') ||
        combined.includes('cài đặt') ||
        combined.includes('cài lại')
    ) {
        return {
            dot: 'bg-blue-600',
            pill: 'bg-blue-50 text-blue-700 border-blue-200/90',
            pulse: true,
            text: (isNormalLabel || !props.label) ? 'Đang khởi tạo' : props.label,
        };
    }

    // 5. WARNING / EXPIRING: Vàng hổ phách (Amber)
    if (
        rawState === 'expiring' ||
        rawState === 'warning' ||
        combined.includes('sắp hết hạn') ||
        combined.includes('cảnh báo')
    ) {
        return {
            dot: 'bg-amber-500',
            pill: 'bg-amber-50 text-amber-800 border-amber-200/90',
            pulse: false,
            text: (isNormalLabel || !props.label) ? 'Sắp hết hạn' : props.label,
        };
    }

    // 6. ACTIVE / RUNNING / PAID / SUCCESS / SẴN SÀNG: Xanh lá (Emerald)
    // Chỉ những dịch vụ thực sự hoạt động tốt mới nhận màu xanh lá
    if (
        rawState === 'running' ||
        rawState === 'active' ||
        rawState === 'completed' ||
        rawState === 'paid' ||
        rawState === 'approved' ||
        rawState === 'success' ||
        rawState === 'online' ||
        combined.includes('hoạt động') ||
        combined.includes('đang chạy') ||
        combined.includes('sẵn sàng') ||
        combined.includes('thành công') ||
        combined.includes('đã duyệt')
    ) {
        return {
            dot: 'bg-emerald-500',
            pill: 'bg-emerald-50 text-emerald-700 border-emerald-200/90',
            pulse: false,
            text: props.label || 'Sẵn sàng',
        };
    }

    // 7. FAILED / ERROR / DANGER: Đỏ cảnh báo (Rose)
    return {
        dot: 'bg-rose-500',
        pill: 'bg-rose-50 text-rose-700 border-rose-200/90',
        pulse: false,
        text: props.label || 'Lỗi kết nối',
    };
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-medium border whitespace-nowrap"
        :class="config.pill"
    >
        <span class="relative flex h-1.5 w-1.5 shrink-0">
            <span
                v-if="config.pulse"
                class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                :class="config.dot"
            />
            <span class="relative inline-flex rounded-full h-1.5 w-1.5" :class="config.dot" />
        </span>
        <span>{{ config.text }}</span>
    </span>
</template>
