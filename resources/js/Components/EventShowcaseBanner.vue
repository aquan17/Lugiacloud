<script setup lang="ts">
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import CountdownClock from '@/Components/CountdownClock.vue';
import { copyToClipboard } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { isAuthProtectedPath, openLoginPrompt } from '@/Composables/useLoginPrompt';
import { Copy, ArrowRight, Sparkles, Gift } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        banners?: Array<{
            id: number;
            eyebrow?: string;
            title: string;
            subtitle?: string;
            cta_text?: string;
            cta_url?: string;
            coupon_code_display?: string;
            image_desktop?: string;
            image_mobile?: string;
            bg_from?: string;
            bg_to?: string;
            ends_at?: string;
            badge_text?: string;
        }>;
        previewMode?: 'auto' | 'desktop' | 'mobile';
    }>(),
    {
        previewMode: 'auto',
    }
);

const page = usePage();
const toast = useToast();
const user = computed(() => (page.props.auth as any)?.user);

async function copyCode(code: string) {
    if (await copyToClipboard(code)) {
        toast.success(`Đã sao chép mã ưu đãi ${code}`);
    }
}

function navigateCta(rawUrl?: string, actionLabel?: string) {
    const url = (rawUrl || '#pricing').trim();
    if (/^https?:\/\//i.test(url)) {
        window.open(url, '_blank', 'noopener,noreferrer');
        return;
    }
    if (url.startsWith('#') || url.startsWith('/#')) {
        const hash = url.replace(/^\//, '');
        const onHome = page.url === '/' || page.url.startsWith('/?') || page.url.startsWith('/#');
        if (onHome) {
            const el = document.querySelector(hash);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth' });
                return;
            }
        }
        router.visit(`/${hash}`);
        return;
    }
    if (!user.value && isAuthProtectedPath(url)) {
        openLoginPrompt({
            actionLabel: actionLabel || 'Tiếp tục xem ưu đãi & sử dụng dịch vụ',
            redirectUrl: url,
        });
        return;
    }
    router.visit(url);
}
</script>

<template>
    <div v-if="banners && banners.length > 0" class="space-y-4">
        <div
            v-for="b in banners"
            :key="b.id"
            class="relative overflow-hidden rounded-2xl border border-blue-900/30 text-white shadow-md"
            :style="{ background: `linear-gradient(120deg, ${b.bg_from || '#0B1437'} 0%, ${b.bg_to || '#1D6AE5'} 100%)` }"
        >
            <template v-if="b.image_desktop || b.image_mobile">
                <button type="button" @click="navigateCta(b.cta_url, b.cta_text || b.title)" class="block w-full text-left relative cursor-pointer">
                    <img
                        v-if="previewMode === 'desktop'"
                        :src="b.image_desktop || b.image_mobile"
                        :alt="b.title"
                        class="w-full aspect-[4/1] max-h-[320px] object-cover block"
                    />
                    <img
                        v-else-if="previewMode === 'mobile'"
                        :src="b.image_mobile || b.image_desktop"
                        :alt="b.title"
                        class="w-full aspect-[16/9] max-h-[240px] object-cover block"
                    />
                    <template v-else>
                        <img
                            :src="b.image_desktop || b.image_mobile"
                            :alt="b.title"
                            class="w-full aspect-[4/1] max-h-[320px] object-cover hidden sm:block"
                        />
                        <img
                            :src="b.image_mobile || b.image_desktop"
                            :alt="b.title"
                            class="w-full aspect-[16/9] max-h-[240px] object-cover sm:hidden"
                        />
                    </template>
                </button>
            </template>

            <div v-else class="bg-navy-grid relative px-5 py-5 sm:px-8 sm:py-7 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div class="pointer-events-none absolute -right-16 -top-16 w-64 h-64 rounded-full bg-sky-400/15 blur-2xl" />

                <div class="relative z-10 max-w-2xl space-y-2.5">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span
                            v-if="b.eyebrow || b.badge_text"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-400/15 border border-amber-300/30 text-[11px] font-bold tracking-wide uppercase text-amber-300"
                        >
                            <Sparkles class="w-3.5 h-3.5" />
                            <span>{{ b.eyebrow || b.badge_text }}</span>
                        </span>
                        <CountdownClock v-if="b.ends_at" :ends-at="b.ends_at" />
                    </div>

                    <h3 class="text-base sm:text-xl font-extrabold tracking-tight text-white leading-snug">
                        {{ b.title }}
                    </h3>
                    <p v-if="b.subtitle" class="text-xs sm:text-sm text-blue-100/90 leading-relaxed">
                        {{ b.subtitle }}
                    </p>
                </div>

                <div class="relative z-10 flex flex-wrap items-center gap-2.5 sm:gap-3 shrink-0">
                    <button
                        v-if="b.coupon_code_display"
                        type="button"
                        @click="copyCode(b.coupon_code_display)"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-950/40 hover:bg-slate-950/60 border border-dashed border-amber-300/50 text-xs font-mono-num text-white transition-colors cursor-pointer"
                        title="Nhấn để sao chép mã giảm giá"
                    >
                        <Gift class="w-3.5 h-3.5 text-amber-300" />
                        <span class="text-blue-200 font-sans">Mã:</span>
                        <span class="font-bold tracking-wider text-amber-300">{{ b.coupon_code_display }}</span>
                        <Copy class="w-3.5 h-3.5 text-blue-200" />
                    </button>

                    <button
                        type="button"
                        @click="navigateCta(b.cta_url, b.cta_text || b.title)"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white text-[#0B1437] hover:bg-blue-50 text-xs sm:text-sm font-bold shadow-xs transition-colors cursor-pointer"
                    >
                        <span>{{ b.cta_text || 'Xem ưu đãi' }}</span>
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
