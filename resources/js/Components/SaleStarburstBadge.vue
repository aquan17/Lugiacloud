<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label?: string;
        size?: number;
        variant?: 'red' | 'amber';
    }>(),
    {
        label: '',
        size: 48,
        variant: 'red',
    }
);

// Tạo đa giác tem tròn 16 gai nhọn đều nhau quanh tâm (50, 50)
const starburstPoints = computed(() => {
    const spikes = 16;
    const outerR = 48;
    const innerR = 39;
    const cx = 50;
    const cy = 50;
    const pts: string[] = [];
    for (let i = 0; i < spikes * 2; i++) {
        const r = i % 2 === 0 ? outerR : innerR;
        const angle = (Math.PI * i) / spikes - Math.PI / 2;
        const x = cx + r * Math.cos(angle);
        const y = cy + r * Math.sin(angle);
        pts.push(`${x.toFixed(1)},${y.toFixed(1)}`);
    }
    return pts.join(' ');
});

const gradId = `sb_${Math.random().toString(36).slice(2, 8)}`;
</script>

<template>
    <div
        class="relative inline-flex items-center justify-center shrink-0 select-none"
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-sm" aria-hidden="true">
            <defs>
                <linearGradient :id="gradId" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" :stop-color="variant === 'amber' ? '#FBBF24' : '#EF4444'" />
                    <stop offset="100%" :stop-color="variant === 'amber' ? '#EA580C' : '#B91C1C'" />
                </linearGradient>
            </defs>
            <polygon :points="starburstPoints" :fill="`url(#${gradId})`" />
            <circle
                cx="50"
                cy="50"
                r="34"
                fill="none"
                stroke="rgba(255,255,255,0.35)"
                stroke-width="1.8"
                stroke-dasharray="3 2.5"
            />
        </svg>
        <div
            class="absolute inset-0 flex items-center justify-center font-mono-num font-black tracking-tight leading-none"
            :class="variant === 'amber' ? 'text-[#7C2D12]' : 'text-white'"
        >
            <slot>
                <span :style="{ fontSize: size >= 46 ? '12.5px' : '10.5px' }">{{ label }}</span>
            </slot>
        </div>
    </div>
</template>
