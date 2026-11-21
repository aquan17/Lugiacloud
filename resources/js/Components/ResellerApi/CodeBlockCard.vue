<script setup lang="ts">
import { ref } from 'vue';
import { Copy, Check } from 'lucide-vue-next';
import { copyToClipboard } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { CODE_LANGUAGES, type CodeLang } from '@/Data/resellerApiSpec';

const props = withDefaults(
    defineProps<{
        title: string;
        code: string;
        copyLabel?: string;
        tone?: 'code' | 'request' | 'success' | 'danger';
        badge?: string;
        lang?: CodeLang;
        showLangTabs?: boolean;
    }>(),
    {
        copyLabel: 'nội dung',
        tone: 'code',
        showLangTabs: false,
    }
);

const emit = defineEmits<{
    (e: 'update:lang', val: CodeLang): void;
}>();

const toast = useToast();
const copied = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;

async function handleCopy() {
    if (await copyToClipboard(props.code)) {
        copied.value = true;
        toast.success(`Đã sao chép ${props.copyLabel}`);
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            copied.value = false;
        }, 1800);
    }
}

const textToneClass: Record<string, string> = {
    code: 'text-slate-100',
    request: 'text-sky-300',
    success: 'text-emerald-300',
    danger: 'text-rose-300',
};
</script>

<template>
    <div
        :data-lang-code-card="showLangTabs ? '1' : undefined"
        class="rounded-xl overflow-hidden border border-slate-800 bg-[#0B1437] shadow-xs"
    >
        <div class="px-4 py-2.5 bg-[#0F1B46] border-b border-white/10 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-300 truncate">{{ title }}</span>
                <span
                    v-if="badge"
                    class="whitespace-nowrap px-2 py-0.5 rounded-md text-[11px] font-mono font-bold"
                    :class="tone === 'danger' ? 'bg-rose-500/20 text-rose-300 border border-rose-400/25' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/25'"
                >
                    {{ badge }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <div v-if="showLangTabs && lang" class="inline-flex p-0.5 rounded-lg bg-slate-950/60 border border-white/10">
                    <button
                        v-for="item in CODE_LANGUAGES"
                        :key="item.id"
                        type="button"
                        @click="emit('update:lang', item.id)"
                        class="px-2.5 py-1 rounded-md text-xs font-semibold transition-colors cursor-pointer whitespace-nowrap"
                        :class="lang === item.id ? 'bg-[#1D6AE5] text-white shadow-2xs' : 'text-slate-400 hover:text-white'"
                    >
                        {{ item.label }}
                    </button>
                </div>

                <button
                    type="button"
                    @click="handleCopy"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-semibold transition-colors cursor-pointer whitespace-nowrap"
                    :class="
                        copied
                            ? 'bg-emerald-500/20 border-emerald-400/40 text-emerald-300'
                            : 'bg-white/5 hover:bg-white/10 border-white/10 text-slate-200'
                    "
                >
                    <Check v-if="copied" class="w-3.5 h-3.5" />
                    <Copy v-else class="w-3.5 h-3.5" />
                    <span>{{ copied ? 'Đã chép' : 'Sao chép' }}</span>
                </button>
            </div>
        </div>

        <pre
            class="p-4 sm:p-5 text-[13px] sm:text-[13.5px] font-mono leading-[1.7] overflow-x-auto"
            :class="textToneClass[tone]"
        ><code>{{ code }}</code></pre>
    </div>
</template>
