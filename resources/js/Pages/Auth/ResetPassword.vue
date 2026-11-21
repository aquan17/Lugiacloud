<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ToastStack from '@/Components/ToastStack.vue';
import { Eye, EyeOff } from 'lucide-vue-next';

const props = defineProps<{
    token: string;
    email?: string;
}>();

const showPassword = ref(false);
const showPasswordConfirm = ref(false);

const form = useForm({
    token: props.token,
    email: props.email || '',
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <div class="min-h-screen bg-[#F6F8FA] bg-tech-grid flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Đặt lại mật khẩu mới" />
        <ToastStack />

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <Link href="/" class="flex justify-center">
                <img src="/logo.webp" alt="LugiaCloud" width="144" height="36" decoding="async" class="h-9 w-auto object-contain" />
            </Link>
            <h1 class="mt-6 text-center text-xl font-extrabold text-[#0B1437]">Thiết lập mật khẩu mới</h1>

            <div class="surface-card p-6 sm:p-8 mt-6">
                <form @submit.prevent="form.post('/password/reset')" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5]"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Mật khẩu mới</label>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                class="w-full pl-3.5 pr-10 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5]"
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
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Xác nhận mật khẩu</label>
                        <div class="relative">
                            <input
                                v-model="form.password_confirmation"
                                :type="showPasswordConfirm ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                class="w-full pl-3.5 pr-10 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5]"
                            />
                            <button
                                type="button"
                                @click="showPasswordConfirm = !showPasswordConfirm"
                                :aria-label="showPasswordConfirm ? 'Ẩn mật khẩu xác nhận' : 'Hiện mật khẩu xác nhận'"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 cursor-pointer transition"
                            >
                                <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 px-4 rounded-lg bg-[#0B1437] hover:bg-[#162354] text-white text-sm font-semibold cursor-pointer"
                    >
                        Lưu mật khẩu mới
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
