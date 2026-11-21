<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps<{
    endsAt?: string | null;
    compact?: boolean;
}>();

const nowMs = ref(Date.now());
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timer = setInterval(() => {
        nowMs.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const parts = computed(() => {
    if (!props.endsAt) return null;
    const target = new Date(props.endsAt).getTime();
    if (Number.isNaN(target)) return null;
    const diff = Math.max(0, Math.floor((target - nowMs.value) / 1000));
    const days = Math.floor(diff / 86400);
    const hours = Math.floor((diff % 86400) / 3600);
    const mins = Math.floor((diff % 3600) / 60);
    const secs = diff % 60;
    const pad = (n: number) => String(n).padStart(2, '0');
    return {
        days: pad(days),
        hours: pad(hours),
        mins: pad(mins),
        secs: pad(secs),
        expired: diff <= 0,
    };
});
</script>

<template>
    <div v-if="parts && !parts.expired" class="inline-flex items-center gap-1 font-mono-num">
        <template v-if="compact">
            <span class="px-1.5 py-0.5 rounded bg-white/15 text-[11px] font-semibold text-white">
                {{ parts.days }}n {{ parts.hours }}:{{ parts.mins }}:{{ parts.secs }}
            </span>
        </template>
        <template v-else>
            <div class="flex items-center gap-1 text-xs">
                <div class="px-2 py-1 rounded bg-white/12 border border-white/15 text-white font-semibold">
                    {{ parts.days }}<span class="text-[10px] text-white/70 ml-0.5">ngày</span>
                </div>
                <span class="text-white/50">:</span>
                <div class="px-2 py-1 rounded bg-white/12 border border-white/15 text-white font-semibold">
                    {{ parts.hours }}<span class="text-[10px] text-white/70 ml-0.5">giờ</span>
                </div>
                <span class="text-white/50">:</span>
                <div class="px-2 py-1 rounded bg-white/12 border border-white/15 text-white font-semibold">
                    {{ parts.mins }}<span class="text-[10px] text-white/70 ml-0.5">phút</span>
                </div>
                <span class="text-white/50">:</span>
                <div class="px-2 py-1 rounded bg-white/12 border border-white/15 text-white font-semibold">
                    {{ parts.secs }}<span class="text-[10px] text-white/70 ml-0.5">giây</span>
                </div>
            </div>
        </template>
    </div>
</template>
