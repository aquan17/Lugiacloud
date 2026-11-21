<script setup lang="ts">
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';
import { CheckCircle2, AlertCircle, Info, X } from 'lucide-vue-next';

const page = usePage();
const { toasts, success, error, info, remove } = useToast();

watch(
    () => page.props.flash as { success?: string; error?: string; info?: string } | undefined,
    (flash) => {
        if (!flash) return;
        if (flash.success) success(flash.success);
        if (flash.error) error(flash.error);
        if (flash.info) info(flash.info);
    },
    { immediate: true, deep: true }
);
</script>

<template>
    <div class="fixed top-5 right-5 z-[120] flex flex-col gap-2 max-w-sm w-full pointer-events-none px-4 sm:px-0 no-print">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="-translate-y-1 opacity-0"
        >
            <div
                v-for="item in toasts"
                :key="item.id"
                class="pointer-events-auto flex items-start gap-2.5 px-3.5 py-3 rounded-lg border shadow-lg bg-[#0F172A] text-white border-slate-800 text-xs leading-relaxed"
            >
                <CheckCircle2 v-if="item.type === 'success'" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" />
                <AlertCircle v-else-if="item.type === 'error'" class="w-4 h-4 text-rose-400 shrink-0 mt-0.5" />
                <Info v-else class="w-4 h-4 text-sky-400 shrink-0 mt-0.5" />
                <span class="flex-1 font-medium">{{ item.message }}</span>
                <button @click="remove(item.id)" class="text-slate-400 hover:text-white transition-colors">
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
