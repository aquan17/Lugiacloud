<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { confirmActionState, closeConfirm, runConfirmedAction } from '@/Composables/useConfirmAction';
import { AlertTriangle, HelpCircle, X } from 'lucide-vue-next';

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && confirmActionState.open) {
        closeConfirm();
    }
}

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-120 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="confirmActionState.open"
                class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/55 backdrop-blur-xs"
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-action-modal-title"
                @click.self="closeConfirm"
            >
                <div class="relative w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden p-6">
                    <button
                        type="button"
                        @click="closeConfirm"
                        aria-label="Đóng"
                        class="absolute top-4 right-4 w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 inline-flex items-center justify-center transition-colors cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>

                    <div class="flex items-start gap-3.5 pr-8">
                        <div
                            class="w-10 h-10 rounded-xl border flex items-center justify-center shrink-0"
                            :class="
                                confirmActionState.variant === 'danger'
                                    ? 'bg-rose-50 border-rose-200 text-rose-600'
                                    : 'bg-blue-50 border-blue-200 text-[#1D6AE5]'
                            "
                        >
                            <AlertTriangle v-if="confirmActionState.variant === 'danger'" class="w-5 h-5" />
                            <HelpCircle v-else class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 id="confirm-action-modal-title" class="text-base font-extrabold text-[#0B1437] leading-snug">
                                {{ confirmActionState.title }}
                            </h3>
                            <p class="text-xs sm:text-[13px] text-slate-600 mt-1.5 leading-relaxed whitespace-pre-line">
                                {{ confirmActionState.message }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            @click="closeConfirm"
                            class="px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer transition-colors"
                        >
                            {{ confirmActionState.cancelText }}
                        </button>
                        <button
                            type="button"
                            @click="runConfirmedAction"
                            class="px-4 py-2 rounded-xl text-white text-xs font-bold cursor-pointer transition-colors shadow-xs"
                            :class="
                                confirmActionState.variant === 'danger'
                                    ? 'bg-rose-600 hover:bg-rose-700'
                                    : 'bg-[#1D6AE5] hover:bg-[#1557C0]'
                            "
                        >
                            {{ confirmActionState.confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
