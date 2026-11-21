<script setup lang="ts">
defineProps<{
    modelValue: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'toggle'): void;
}>();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :disabled="disabled"
        @click="emit('toggle')"
        :title="
            disabled
                ? 'Không khả dụng cho dịch vụ đã ngừng hoạt động'
                : modelValue
                  ? 'Đang bật: Tự động gia hạn trước 24h khi ví đủ số dư (nhấn để tắt)'
                  : 'Đang tắt: Nhấn để bật tự động gia hạn trước 24h'
        "
        class="inline-flex items-center gap-1.5 group select-none focus:outline-none disabled:opacity-45 disabled:cursor-not-allowed cursor-pointer"
    >
        <span
            class="relative inline-flex h-4 w-7 shrink-0 items-center rounded-full p-0.5 transition-colors duration-200"
            :class="modelValue ? 'bg-emerald-500 group-hover:bg-emerald-600' : 'bg-slate-300 group-hover:bg-slate-400'"
        >
            <span
                class="pointer-events-none inline-block h-3 w-3 rounded-full bg-white shadow-xs transition-transform duration-200"
                :class="modelValue ? 'translate-x-3' : 'translate-x-0'"
            />
        </span>
        <span
            class="text-[11px] font-semibold transition-colors whitespace-nowrap"
            :class="modelValue ? 'text-emerald-700' : 'text-slate-500 group-hover:text-slate-700'"
        >
            Tự gia hạn
        </span>
    </button>
</template>
