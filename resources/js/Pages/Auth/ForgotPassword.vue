<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ToastStack from '@/Components/ToastStack.vue';
import { Mail, ArrowLeft } from 'lucide-vue-next';

const form = useForm({ email: '' });
</script>

<template>
    <div class="min-h-screen bg-[#F6F8FA] bg-tech-grid flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Khôi phục mật khẩu" />
        <ToastStack />

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <Link href="/" class="flex justify-center">
                <img src="/logo.webp" alt="LugiaCloud" width="144" height="36" decoding="async" class="h-9 w-auto object-contain" />
            </Link>
            <h1 class="mt-6 text-center text-xl font-extrabold text-[#0B1437]">Khôi phục mật khẩu</h1>
            <p class="mt-1.5 text-center text-xs text-slate-500">
                Nhập email đăng ký để nhận liên kết đặt lại mật khẩu mới
            </p>

            <div class="surface-card p-6 sm:p-8 mt-6">
                <form @submit.prevent="form.post('/password/email')" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Địa chỉ Email</label>
                        <div class="relative">
                            <Mail class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                placeholder="name@company.com"
                                class="w-full pl-9 pr-3.5 py-2.5 rounded-lg border border-slate-300 text-sm focus:outline-none focus:border-[#1D6AE5]"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 px-4 rounded-lg bg-[#0B1437] hover:bg-[#162354] text-white text-sm font-semibold disabled:opacity-60 cursor-pointer"
                    >
                        {{ form.processing ? 'Đang gửi...' : 'Gửi liên kết khôi phục' }}
                    </button>
                </form>

                <div class="mt-5 text-center">
                    <Link href="/login" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-[#0B1437]">
                        <ArrowLeft class="w-3.5 h-3.5" />
                        <span>Quay lại đăng nhập</span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
