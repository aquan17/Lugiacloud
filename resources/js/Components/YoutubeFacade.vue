<script setup lang="ts">
import { ref, computed } from 'vue';
import { Play } from 'lucide-vue-next';

const props = defineProps<{
    youtubeId: string;
    title?: string;
}>();

const activated = ref(false);
const thumbSrc = computed(() => `https://i.ytimg.com/vi/${props.youtubeId}/hqdefault.jpg`);
const embedUrl = computed(
    () => `https://www.youtube-nocookie.com/embed/${props.youtubeId}?autoplay=1&rel=0`
);
</script>

<template>
    <div class="relative w-full aspect-video rounded-2xl overflow-hidden bg-slate-950 border border-slate-200 shadow-sm">
        <iframe
            v-if="activated"
            :src="embedUrl"
            :title="title || 'Video hướng dẫn LugiaCloud'"
            class="w-full h-full border-0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
        />
        <button
            v-else
            type="button"
            @click="activated = true"
            class="group relative w-full h-full block cursor-pointer text-left"
            :aria-label="`Phát video: ${title || 'Video hướng dẫn'}`"
        >
            <img
                :src="thumbSrc"
                :alt="title || 'Video hướng dẫn LugiaCloud'"
                loading="lazy"
                decoding="async"
                class="w-full h-full object-cover opacity-90 group-hover:scale-[1.02] transition-transform duration-300"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/75 via-slate-950/20 to-transparent" />
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="w-16 h-16 rounded-full bg-red-600 group-hover:bg-red-700 text-white flex items-center justify-center shadow-xl group-hover:scale-105 transition-all">
                    <Play class="w-7 h-7 fill-white ml-1" />
                </span>
            </div>
            <div class="absolute bottom-3.5 left-4 right-4 flex items-center justify-between gap-2 text-xs text-white/95 font-semibold">
                <span class="truncate">{{ title || 'Xem video hướng dẫn trực tiếp' }}</span>
                <span class="px-2 py-0.5 rounded-md bg-black/55 text-[11px] font-bold shrink-0">YouTube</span>
            </div>
        </button>
    </div>
</template>
