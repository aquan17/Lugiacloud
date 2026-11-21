<script setup lang="ts">
import { useMutationGuard } from '@/Composables/useMutationGuard';
import { ShieldCheck } from 'lucide-vue-next';

const guard = useMutationGuard();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="guard.visible"
                role="status"
                aria-live="assertive"
                aria-busy="true"
                class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-slate-950/35 backdrop-blur-[2px] cursor-wait select-none"
            >
                <div class="w-full max-w-[360px] rounded-2xl bg-white border border-slate-200/90 shadow-2xl p-6 text-center">
                    <!-- Brand Emblem + Smooth Dual-Ring Spinner -->
                    <div class="relative w-14 h-14 mx-auto flex items-center justify-center">
                        <span class="absolute inset-0 rounded-full border-[2.5px] border-slate-100" />
                        <span
                            class="absolute inset-0 rounded-full border-[2.5px] border-transparent border-t-[#1D6AE5] border-r-[#1D6AE5]/40 animate-spin"
                        />
                        <img
                            src="/bird-icon.webp"
                            alt="LugiaCloud"
                            width="28"
                            height="28"
                            class="w-7 h-7 object-contain relative z-10"
                        />
                    </div>

                    <!-- Contextual Title & Description -->
                    <h3 class="mt-4 text-[15px] font-extrabold text-[#0B1437] tracking-tight">
                        {{ guard.title }}
                    </h3>
                    <p class="mt-1.5 text-xs font-medium text-slate-600 leading-relaxed">
                        {{ guard.subtitle }}
                    </p>

                    <!-- Smooth Indeterminate Progress Track -->
                    <div class="mt-4 h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-[#1D6AE5] lugia-progress-bar" />
                    </div>

                    <!-- Transaction Protection Badge for Critical Actions -->
                    <div
                        v-if="guard.isCritical"
                        class="mt-3.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200/80 text-[11px] font-semibold text-emerald-800"
                    >
                        <ShieldCheck class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                        <span>Đang bảo vệ giao dịch · Không tải lại trang</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
