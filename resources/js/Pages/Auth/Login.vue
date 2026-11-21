<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ToastStack from '@/Components/ToastStack.vue';
import { ArrowRight, Eye, EyeOff, Lock, Mail } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="min-h-screen bg-[#F6F8FA] bg-tech-grid flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Đăng nhập hệ thống" />
        <ToastStack />

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <Link href="/" class="flex justify-center">
                <img src="/logo.webp" alt="LugiaCloud" width="144" height="36" decoding="async" class="h-9 w-auto object-contain" />
            </Link>
            <h1 class="mt-6 text-center text-xl font-extrabold text-[#0B1437] tracking-tight">
                Đăng nhập Bảng điều khiển
            </h1>
            <p class="mt-1.5 text-center text-xs text-slate-500">
                Quản lý Cloud VPS, Proxy IPv4 và chứng từ thanh toán của bạn
            </p>
        </div>

        <div class="mt-7 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="surface-card p-6 sm:p-8">
                <div v-if="status" class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Địa chỉ Email</label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="name@company.com"
                                class="w-full pl-9 pr-3.5 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-semibold text-slate-700">Mật khẩu</label>
                            <Link href="/password/reset" class="text-xs font-medium text-[#1D6AE5] hover:underline">
                                Quên mật khẩu?
                            </Link>
                        </div>
                        <div class="relative">
                            <Lock class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full pl-9 pr-10 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 cursor-pointer transition"
                            >
                                <EyeOff v-if="showPassword" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-xs text-slate-600 select-none cursor-pointer">
                        <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                        <span>Duy trì đăng nhập trên thiết bị này</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg bg-[#0B1437] hover:bg-[#162354] text-white text-sm font-semibold transition-colors disabled:opacity-60 cursor-pointer"
                    >
                        <span>{{ form.processing ? 'Đang xác thực...' : 'Đăng nhập' }}</span>
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-slate-100 text-center text-xs text-slate-600">
                    Chưa có tài khoản LugiaCloud?
                    <Link href="/register" class="font-semibold text-[#1D6AE5] hover:underline ml-1">
                        Khởi tạo tài khoản mới
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
