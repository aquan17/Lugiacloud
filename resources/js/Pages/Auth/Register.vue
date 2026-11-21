<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ToastStack from '@/Components/ToastStack.vue';
import { ArrowRight, Eye, EyeOff, User, Mail, Phone, Lock, Gift } from 'lucide-vue-next';

const props = defineProps<{
    referralCode?: string | null;
}>();

const showPassword = ref(false);
const showPasswordConfirm = ref(false);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    referral_code: props.referralCode || '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <div class="min-h-screen bg-[#F6F8FA] bg-tech-grid flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Đăng ký tài khoản" />
        <ToastStack />

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <Link href="/" class="flex justify-center">
                <img src="/logo.webp" alt="LugiaCloud" width="144" height="36" decoding="async" class="h-9 w-auto object-contain" />
            </Link>
            <h1 class="mt-6 text-center text-xl font-extrabold text-[#0B1437] tracking-tight">
                Khởi tạo tài khoản LugiaCloud
            </h1>
            <p class="mt-1.5 text-center text-xs text-slate-500">
                Triển khai Cloud VPS &amp; Proxy IPv4 tự động chỉ trong 30 giây
            </p>
        </div>

        <div class="mt-7 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="surface-card p-6 sm:p-8">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Họ tên / Tên hiển thị</label>
                        <div class="relative">
                            <User class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Nguyễn Văn A"
                                class="w-full pl-9 pr-3.5 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5]"
                            />
                        </div>
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Địa chỉ Email</label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                placeholder="you@gmail.com"
                                class="w-full pl-9 pr-3.5 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5]"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Số điện thoại (tùy chọn)</label>
                        <div class="relative">
                            <Phone class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="form.phone"
                                type="tel"
                                placeholder="0912345678"
                                class="w-full pl-9 pr-3.5 py-2.5 rounded-lg border border-slate-300 text-sm font-mono-num focus:outline-none focus:border-[#1D6AE5]"
                            />
                        </div>
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">{{ form.errors.phone }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Mật khẩu</label>
                            <div class="relative">
                                <Lock class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Tối thiểu 8 ký tự"
                                    class="w-full pl-9 pr-9 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5]"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 cursor-pointer transition"
                                >
                                    <EyeOff v-if="showPassword" class="w-4 h-4" />
                                    <Eye v-else class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nhập lại mật khẩu</label>
                            <div class="relative">
                                <Lock class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirm ? 'text' : 'password'"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Xác nhận"
                                    class="w-full pl-9 pr-9 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5]"
                                />
                                <button
                                    type="button"
                                    @click="showPasswordConfirm = !showPasswordConfirm"
                                    :aria-label="showPasswordConfirm ? 'Ẩn mật khẩu xác nhận' : 'Hiện mật khẩu xác nhận'"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 cursor-pointer transition"
                                >
                                    <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
                                    <Eye v-else class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.password" class="text-xs text-rose-600">{{ form.errors.password }}</p>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Mã giới thiệu (nếu có)</label>
                        <div class="relative">
                            <Gift class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="form.referral_code"
                                type="text"
                                placeholder="VD: LUGIA88"
                                class="w-full pl-9 pr-3.5 py-2 rounded-lg border border-slate-200 bg-slate-50 text-xs font-mono-num uppercase focus:outline-none focus:border-[#1D6AE5]"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-sm font-semibold transition-colors disabled:opacity-60 cursor-pointer"
                    >
                        <span>{{ form.processing ? 'Đang khởi tạo...' : 'Tạo tài khoản' }}</span>
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-slate-100 text-center text-xs text-slate-600">
                    Đã có tài khoản?
                    <Link href="/login" class="font-semibold text-[#0B1437] hover:underline ml-1">Đăng nhập</Link>
                </div>
            </div>
        </div>
    </div>
</template>
