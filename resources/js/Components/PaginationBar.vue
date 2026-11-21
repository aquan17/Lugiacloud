<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links?: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}>();

function cleanLabel(raw: string): string {
    return raw
        .replace(/&laquo;\s*Previous/i, '← Trước')
        .replace(/Next\s*&raquo;/i, 'Sau →')
        .replace(/&laquo;/g, '←')
        .replace(/&raquo;/g, '→');
}
</script>

<template>
    <div v-if="links && links.length > 3" class="flex flex-wrap items-center justify-end gap-1 pt-4">
        <template v-for="(link, idx) in links" :key="idx">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="px-3 py-1.5 rounded-md text-xs font-medium border transition-colors font-mono-num"
                :class="
                    link.active
                        ? 'bg-[#0B1437] text-white border-[#0B1437]'
                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50 hover:border-slate-300'
                "
            >
                {{ cleanLabel(link.label) }}
            </Link>
            <span
                v-else
                class="px-2.5 py-1.5 rounded-md text-xs text-slate-400 border border-transparent select-none"
            >
                {{ cleanLabel(link.label) }}
            </span>
        </template>
    </div>
</template>
