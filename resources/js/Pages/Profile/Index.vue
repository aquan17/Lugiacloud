<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { formatVnd } from '@/Composables/useFormat';
import { Eye, EyeOff, Loader2 } from 'lucide-vue-next';

defineProps<{
    metrics: {
        active_vps: number;
        total_proxies: number;
        total_deposited: number;
    };
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);

const showCurrentPass = ref(false);
const showNewPass = ref(false);
const showConfirmPass = ref(false);

const profileForm = useForm({
    name: user.value?.name || '',
    phone: user.value?.phone || '',
});

const passForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <ClientLayout title="Hồ sơ & Bảo mật">
        <Head title="Hồ sơ tài khoản" />

        <div class="max-w-4xl space-y-6">
            <div class="surface-card grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 text-xs rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Hạng tài khoản</div>
                    <div class="text-lg font-black text-[#0B1437] mt-1.5">{{ user?.role_label }}</div>
                    <div v-if="user?.discount_percent > 0" class="text-xs text-emerald-700 font-bold mt-1">
                        Chiết khấu VPS -{{ user.discount_percent }}% · Proxy -{{ user.proxy_discount_percent }}%
                    </div>
                </div>
                <div class="p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Dịch vụ đang quản lý</div>
                    <div class="text-lg font-black font-mono-num text-[#0B1437] mt-1.5">
                        {{ metrics.active_vps }} VPS · {{ metrics.total_proxies }} Proxy
                    </div>
                </div>
                <div class="p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Tổng tiền đã nạp</div>
                    <div class="text-lg font-black font-mono-num text-slate-900 mt-1.5">{{ formatVnd(metrics.total_deposited) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <form @submit.prevent="profileForm.put('/profile')" class="surface-card p-6 space-y-4 rounded-2xl border border-slate-200/90 shadow-xs">
                    <h2 class="text-sm font-extrabold text-[#0B1437]">Thông tin cá nhân</h2>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Email đăng nhập</label>
                        <input :value="user?.email" disabled class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700 cursor-not-allowed" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Họ tên hiển thị</label>
                        <input v-model="profileForm.name" type="text" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-900 focus:outline-none focus:border-[#1D6AE5] focus:ring-1 focus:ring-[#1D6AE5]" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Số điện thoại liên hệ</label>
                        <input v-model="profileForm.phone" type="tel" placeholder="0912345678" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-mono-num font-bold text-slate-900 focus:outline-none focus:border-[#1D6AE5] focus:ring-1 focus:ring-[#1D6AE5]" />
                        <p v-if="profileForm.errors.phone" class="mt-1 text-xs font-bold text-rose-600">{{ profileForm.errors.phone }}</p>
                    </div>
                    <button type="submit" :disabled="profileForm.processing" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold cursor-pointer transition shadow-xs active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed">
                        <Loader2 v-if="profileForm.processing" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ profileForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}</span>
                    </button>
                </form>

                <form
                    @submit.prevent="passForm.put('/profile/password', { onSuccess: () => passForm.reset() })"
                    class="surface-card p-6 space-y-4 rounded-2xl border border-slate-200/90 shadow-xs"
                >
                    <h2 class="text-sm font-extrabold text-[#0B1437]">Đổi mật khẩu đăng nhập</h2>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Mật khẩu hiện tại</label>
                        <div class="relative">
                            <input v-model="passForm.current_password" :type="showCurrentPass ? 'text' : 'password'" required class="w-full pl-3.5 pr-10 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-900 focus:outline-none focus:border-[#1D6AE5]" />
                            <button
                                type="button"
                                @click="showCurrentPass = !showCurrentPass"
                                :aria-label="showCurrentPass ? 'Ẩn mật khẩu hiện tại' : 'Hiện mật khẩu hiện tại'"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 cursor-pointer transition"
                            >
                                <EyeOff v-if="showCurrentPass" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <p v-if="passForm.errors.current_password" class="mt-1 text-xs font-bold text-rose-600">{{ passForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Mật khẩu mới</label>
                        <div class="relative">
                            <input v-model="passForm.password" :type="showNewPass ? 'text' : 'password'" required class="w-full pl-3.5 pr-10 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-900 focus:outline-none focus:border-[#1D6AE5]" />
                            <button
                                type="button"
                                @click="showNewPass = !showNewPass"
                                :aria-label="showNewPass ? 'Ẩn mật khẩu mới' : 'Hiện mật khẩu mới'"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 cursor-pointer transition"
                            >
                                <EyeOff v-if="showNewPass" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <p v-if="passForm.errors.password" class="mt-1 text-xs font-bold text-rose-600">{{ passForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Xác nhận mật khẩu mới</label>
                        <div class="relative">
                            <input v-model="passForm.password_confirmation" :type="showConfirmPass ? 'text' : 'password'" required class="w-full pl-3.5 pr-10 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-900 focus:outline-none focus:border-[#1D6AE5]" />
                            <button
                                type="button"
                                @click="showConfirmPass = !showConfirmPass"
                                :aria-label="showConfirmPass ? 'Ẩn mật khẩu xác nhận' : 'Hiện mật khẩu xác nhận'"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 cursor-pointer transition"
                            >
                                <EyeOff v-if="showConfirmPass" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    <button type="submit" :disabled="passForm.processing" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer transition shadow-xs active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed">
                        <Loader2 v-if="passForm.processing" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ passForm.processing ? 'Đang cập nhật...' : 'Cập nhật mật khẩu' }}</span>
                    </button>
                </form>
            </div>
        </div>
    </ClientLayout>
</template>
