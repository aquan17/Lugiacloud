<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import ToastStack from '@/Components/ToastStack.vue';
import { MailCheck, RefreshCw, LogOut } from 'lucide-vue-next';

const page = usePage();
const userEmail = computed(() => (page.props.auth as any)?.user?.email || '');
const form = useForm({});
const cooldown = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

function resendEmail() {
    if (form.processing || cooldown.value > 0) return;
    form.post('/email/verification-notification', {
        preserveScroll: true,
        onSuccess: () => {
            cooldown.value = 60;
            if (timer) clearInterval(timer);
            timer = setInterval(() => {
                cooldown.value = Math.max(0, cooldown.value - 1);
                if (cooldown.value <= 0 && timer) {
                    clearInterval(timer);
                    timer = null;
                }
            }, 1000);
        },
    });
}

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <div class="min-h-screen bg-[#F6F8FA] bg-tech-grid flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Xác thực địa chỉ Email" />
        <ToastStack />

        <div class="sm:mx-auto sm:w-full sm:max-w-md space-y-5">
            <Link href="/" class="flex justify-center">
                <img src="/logo.webp" alt="LugiaCloud" width="144" height="36" decoding="async" class="h-9 w-auto object-contain" />
            </Link>

            <div class="surface-card p-7 sm:p-8 text-center space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-center text-[#1D6AE5] mx-auto">
                    <MailCheck class="w-6 h-6" />
                </div>
                <div>
                    <h1 class="text-lg font-extrabold text-[#0B1437]">Xác thực địa chỉ Email</h1>
                    <p class="text-xs text-slate-600 leading-relaxed mt-2">
                        Chúng tôi đã gửi liên kết kích hoạt tới hộp thư
                        <strong v-if="userEmail" class="font-mono text-[#0B1437]">{{ userEmail }}</strong>
                        <span v-else>đăng ký của bạn</span>.
                        Vui lòng mở email (kiểm tra thêm mục <strong>Spam / Quảng cáo</strong>) và bấm vào nút xác thực để kích hoạt tài khoản.
                    </p>
                </div>

                <div class="pt-2 flex flex-col gap-2.5">
                    <button
                        type="button"
                        @click="resendEmail"
                        :disabled="form.processing || cooldown > 0"
                        class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold cursor-pointer transition disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': form.processing }" />
                        <span v-if="form.processing">Đang gửi lại...</span>
                        <span v-else-if="cooldown > 0">Gửi lại sau {{ cooldown }}s</span>
                        <span v-else>Gửi lại email xác nhận</span>
                    </button>
                    <button
                        type="button"
                        @click="router.post('/logout')"
                        class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-xs font-semibold text-slate-600 cursor-pointer transition"
                    >
                        <LogOut class="w-3.5 h-3.5" />
                        <span>Đăng xuất / Đổi tài khoản khác</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
