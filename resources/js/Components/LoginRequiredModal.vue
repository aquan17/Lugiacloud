<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Lock, LogIn, UserPlus, X, Gift, ShieldCheck } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        actionLabel?: string;
        redirectUrl?: string;
    }>(),
    {
        title: 'Đăng nhập để tiếp tục',
        description: 'Vui lòng đăng nhập hoặc tạo tài khoản LugiaCloud để tiếp tục thao tác này.',
        actionLabel: '',
        redirectUrl: '',
    }
);

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const page = usePage();
const welcomeOffer = computed(() => (page.props.promotions as any)?.welcome_offer);

const loginHref = computed(() => {
    if (props.redirectUrl && props.redirectUrl.startsWith('/')) {
        return `/login?redirect=${encodeURIComponent(props.redirectUrl)}`;
    }
    return '/login';
});

const registerHref = computed(() => {
    if (props.redirectUrl && props.redirectUrl.startsWith('/')) {
        return `/register?redirect=${encodeURIComponent(props.redirectUrl)}`;
    }
    return '/register';
});

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && props.open) {
        emit('close');
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0B1437]/55 backdrop-blur-[2px]"
                role="dialog"
                aria-modal="true"
                aria-labelledby="login-required-modal-title"
                @click.self="emit('close')"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2"
                >
                    <div
                        class="relative w-full max-w-md rounded-2xl bg-white border border-slate-200/90 shadow-2xl overflow-hidden"
                    >
                        <!-- Close button -->
                        <button
                            type="button"
                            @click="emit('close')"
                            aria-label="Đóng thông báo"
                            class="absolute top-4 right-4 w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 inline-flex items-center justify-center transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>

                        <div class="p-6">
                            <!-- Icon + Heading -->
                            <div class="flex items-start gap-3.5 pr-8">
                                <div class="w-11 h-11 rounded-xl bg-blue-50 border border-blue-200/80 text-[#1D6AE5] flex items-center justify-center shrink-0">
                                    <Lock class="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 id="login-required-modal-title" class="text-lg font-extrabold text-[#0B1437] leading-snug">
                                        {{ title }}
                                    </h3>
                                    <p class="text-xs sm:text-[13px] text-slate-600 mt-1 leading-relaxed">
                                        {{ description }}
                                    </p>
                                </div>
                            </div>

                            <!-- Selected action summary -->
                            <div
                                v-if="actionLabel"
                                class="mt-4 px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center gap-2.5 text-xs"
                            >
                                <ShieldCheck class="w-4 h-4 text-[#1D6AE5] shrink-0" />
                                <div class="min-w-0">
                                    <span class="text-slate-500">Thao tác đang chọn: </span>
                                    <span class="font-bold text-[#0B1437]">{{ actionLabel }}</span>
                                </div>
                            </div>

                            <!-- Welcome offer pill if enabled -->
                            <div
                                v-if="welcomeOffer?.enabled"
                                class="mt-3 px-3.5 py-2 rounded-xl bg-amber-50/90 border border-amber-200/80 flex items-center gap-2 text-xs text-amber-900"
                            >
                                <Gift class="w-4 h-4 text-amber-600 shrink-0" />
                                <span>
                                    Chưa có tài khoản? Đăng ký mới nhận ngay ưu đãi
                                    <strong class="font-extrabold text-amber-950">giảm {{ welcomeOffer.percent }}%</strong> đơn đầu tiên.
                                </span>
                            </div>

                            <!-- Action buttons -->
                            <div class="mt-5 flex flex-col sm:flex-row gap-2.5">
                                <Link
                                    :href="loginHref"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs sm:text-[13px] font-bold shadow-xs transition-colors"
                                >
                                    <LogIn class="w-4 h-4" />
                                    <span>Đăng nhập</span>
                                </Link>
                                <Link
                                    :href="registerHref"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs sm:text-[13px] font-bold transition-colors"
                                >
                                    <UserPlus class="w-4 h-4" />
                                    <span>Đăng ký miễn phí</span>
                                </Link>
                            </div>

                            <button
                                type="button"
                                @click="emit('close')"
                                class="mt-3 w-full py-2 rounded-xl text-xs font-semibold text-slate-500 hover:text-slate-800 hover:bg-slate-100/80 transition-colors cursor-pointer"
                            >
                                Để sau, ở lại trang này
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
