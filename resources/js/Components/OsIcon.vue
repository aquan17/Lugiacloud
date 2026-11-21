<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        distro?: string;
        size?: number;
    }>(),
    {
        distro: 'linux',
        size: 22,
    }
);

const key = computed(() => (props.distro || 'linux').toLowerCase());
</script>

<template>
    <!-- Ubuntu -->
    <svg v-if="key.includes('ubuntu')" :width="size" :height="size" viewBox="0 0 24 24" fill="none" class="shrink-0">
        <circle cx="12" cy="12" r="10.5" fill="#E95420" />
        <circle cx="12" cy="12" r="4.6" stroke="#FFF" stroke-width="1.7" />
        <circle cx="6.2" cy="12" r="1.5" fill="#FFF" />
        <circle cx="14.9" cy="7" r="1.5" fill="#FFF" />
        <circle cx="14.9" cy="17" r="1.5" fill="#FFF" />
    </svg>

    <!-- Windows Server / Windows 10 -->
    <svg v-else-if="key.includes('win')" :width="size" :height="size" viewBox="0 0 24 24" fill="none" class="shrink-0">
        <rect x="2.5" y="2.5" width="8.8" height="8.8" rx="1.2" fill="#0078D4" />
        <rect x="12.7" y="2.5" width="8.8" height="8.8" rx="1.2" fill="#0078D4" />
        <rect x="2.5" y="12.7" width="8.8" height="8.8" rx="1.2" fill="#0078D4" />
        <rect x="12.7" y="12.7" width="8.8" height="8.8" rx="1.2" fill="#0078D4" />
    </svg>

    <!-- Debian -->
    <svg v-else-if="key.includes('debian')" :width="size" :height="size" viewBox="0 0 24 24" fill="none" class="shrink-0">
        <circle cx="12" cy="12" r="10.5" fill="#A81D33" />
        <path d="M13.2 6.8c-3.1-.3-5.6 2.1-5.5 5.2.1 2.9 2.5 5.1 5.3 4.9 2.3-.2 4-2.1 3.9-4.3-.1-1.8-1.6-3.1-3.4-3-1.4.1-2.4 1.2-2.3 2.6.1 1 .9 1.8 1.9 1.7" stroke="#FFF" stroke-width="1.7" stroke-linecap="round" />
    </svg>

    <!-- AlmaLinux -->
    <svg v-else-if="key.includes('alma')" :width="size" :height="size" viewBox="0 0 24 24" fill="none" class="shrink-0">
        <rect x="2" y="2" width="20" height="20" rx="5" fill="#0F4266" />
        <circle cx="12" cy="8.2" r="2.4" fill="#FF4F4F" />
        <circle cx="15.8" cy="12" r="2.4" fill="#F4C10F" />
        <circle cx="12" cy="15.8" r="2.4" fill="#22C55E" />
        <circle cx="8.2" cy="12" r="2.4" fill="#38BDF8" />
    </svg>

    <!-- CentOS -->
    <svg v-else-if="key.includes('centos')" :width="size" :height="size" viewBox="0 0 24 24" fill="none" class="shrink-0">
        <rect x="2.5" y="2.5" width="19" height="19" rx="4.5" fill="#932279" />
        <path d="M12 5.5L18.5 12 12 18.5 5.5 12 12 5.5Z" stroke="#FFF" stroke-width="1.6" />
        <circle cx="12" cy="12" r="2" fill="#EAB308" />
    </svg>

    <!-- Docker -->
    <svg v-else-if="key.includes('docker')" :width="size" :height="size" viewBox="0 0 24 24" fill="none" class="shrink-0">
        <rect x="2" y="2" width="20" height="20" rx="5" fill="#2496ED" />
        <rect x="6" y="10" width="2.4" height="2.2" fill="#FFF" />
        <rect x="9" y="10" width="2.4" height="2.2" fill="#FFF" />
        <rect x="12" y="10" width="2.4" height="2.2" fill="#FFF" />
        <rect x="9" y="7.3" width="2.4" height="2.2" fill="#FFF" />
        <rect x="12" y="7.3" width="2.4" height="2.2" fill="#FFF" />
        <path d="M5 13.2h11.5c1.2 0 2.1-.5 2.6-1.3.5.8.3 2-.5 2.7-.8 2.2-3 3.4-6.4 3.4-3.8 0-6.5-1.7-7.2-4.8Z" fill="#FFF" />
    </svg>

    <!-- aaPanel / Web Stack -->
    <svg v-else-if="key.includes('aapanel')" :width="size" :height="size" viewBox="0 0 24 24" fill="none" class="shrink-0">
        <rect x="2" y="2" width="20" height="20" rx="5" fill="#15803D" />
        <path d="M7 15.5L10.5 8.5L14 15.5M8.2 13.5H12.8M15.8 10.5V15.5M15.8 8.3V8.4" stroke="#FFF" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>

    <!-- Vietnam Flag Badge -->
    <svg v-else-if="key === 'vn' || key.includes('vietnam')" :width="size" :height="size" viewBox="0 0 24 24" fill="none" class="shrink-0">
        <rect x="1.5" y="4" width="21" height="16" rx="2.5" fill="#DA251D" />
        <path d="M12 7.6L13.15 11.05H16.8L13.85 13.18L14.98 16.65L12 14.52L9.02 16.65L10.15 13.18L7.2 11.05H10.85L12 7.6Z" fill="#FFCD00" />
    </svg>

    <!-- Generic Linux Kernel -->
    <svg v-else :width="size" :height="size" viewBox="0 0 24 24" fill="none" class="shrink-0">
        <rect x="2" y="2" width="20" height="20" rx="5" fill="#0F172A" />
        <path d="M7.5 9L10.5 12L7.5 15M12.5 15H16.5" stroke="#38BDF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
