<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatVnd } from '@/Composables/useFormat';
import {
    Wallet,
    RefreshCw,
    KeyRound,
    CheckCircle2,
    AlertCircle,
    Server,
    Globe,
    Link2,
    Eye,
    EyeOff,
    Save,
    Loader2,
    Info,
} from 'lucide-vue-next';

const props = defineProps<{
    account: {
        id: number;
        label: string;
        api_username: string;
        api_app: string;
        has_secret: boolean;
        credit_vnd: number;
        total_vnd: number;
        is_active: boolean;
        is_full: boolean;
        last_synced_at: string | null;
        sync_error: string | null;
    } | null;
    stats: {
        active_vps: number;
        active_proxies: number;
        api_base: string;
        has_env_credentials: boolean;
        extra_accounts_count: number;
    };
}>();

const syncing = ref(false);
const showSecret = ref(false);

const form = useForm({
    api_username: props.account?.api_username || '',
    api_app: props.account?.api_app || '',
    api_secret: '',
    is_active: props.account ? Boolean(props.account.is_active) : true,
});

watch(
    () => props.account,
    (acc) => {
        if (!acc) return;
        form.api_username = acc.api_username || '';
        form.api_app = acc.api_app || '';
        form.is_active = Boolean(acc.is_active);
    }
);

function syncBalance() {
    if (syncing.value) return;
    syncing.value = true;
    router.post(
        '/admin/accounts/sync-primary',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncing.value = false;
            },
        }
    );
}

function submitConfig() {
    form.put('/admin/accounts/primary', {
        preserveScroll: true,
        onSuccess: () => {
            form.api_secret = '';
            showSecret.value = false;
        },
    });
}
</script>

<template>
    <AdminLayout title="Ví & Kết nối API CloudSunny">
        <Head title="Ví & API CloudSunny" />

        <div class="space-y-6 max-w-5xl">
            <!-- 1. THẺ SỐ DƯ VÍ GỐC CLOUDSUNNY -->
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="p-6 sm:p-7 bg-gradient-to-br from-[#0B1437] via-[#102052] to-[#1D4ED8] text-white">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/12 border border-white/15 text-[11px] font-bold uppercase tracking-wider text-sky-200">
                                    <Wallet class="w-3.5 h-3.5" />
                                    <span>Số dư ví gốc CloudSunny</span>
                                </span>
                                <span
                                    v-if="account && account.is_active"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-400/20 border border-emerald-300/30 text-[11px] font-bold text-emerald-200"
                                >
                                    <CheckCircle2 class="w-3.5 h-3.5" />
                                    <span>Đang kết nối</span>
                                </span>
                                <span
                                    v-else-if="account && !account.is_active"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-400/20 border border-amber-300/30 text-[11px] font-bold text-amber-200"
                                >
                                    <AlertCircle class="w-3.5 h-3.5" />
                                    <span>Đang tạm tắt nhận đơn</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-400/20 border border-rose-300/30 text-[11px] font-bold text-rose-200"
                                >
                                    <AlertCircle class="w-3.5 h-3.5" />
                                    <span>Chưa cấu hình khóa API</span>
                                </span>
                            </div>

                            <div class="text-3xl sm:text-4xl font-black font-mono-num tracking-tight">
                                {{ formatVnd(account?.credit_vnd || 0) }}
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-blue-100/85">
                                <span v-if="account?.api_username">
                                    Tài khoản: <strong class="font-mono text-white">{{ account.api_username }}</strong>
                                </span>
                                <span v-if="account?.total_vnd">
                                    Tổng ví gốc: <strong class="font-mono-num text-white">{{ formatVnd(account.total_vnd) }}</strong>
                                </span>
                                <span>
                                    Đồng bộ gần nhất:
                                    <strong class="font-mono-num text-white">{{ account?.last_synced_at || 'Chưa đồng bộ' }}</strong>
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="syncing || (!account && !stats.has_env_credentials)"
                            @click="syncBalance"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-blue-50 disabled:opacity-50 disabled:cursor-not-allowed text-[#0B1437] text-xs font-extrabold shadow-xs cursor-pointer transition shrink-0"
                        >
                            <RefreshCw class="w-4 h-4 text-[#1D6AE5]" :class="syncing ? 'animate-spin' : ''" />
                            <span>{{ syncing ? 'Đang đồng bộ...' : 'Đồng bộ số dư ngay' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Dải thông số nhanh -->
                <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 bg-white text-xs">
                    <div class="px-5 py-4 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200/80 text-[#1D6AE5] flex items-center justify-center shrink-0">
                            <Server class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="text-slate-500 font-semibold">Cloud VPS đang hoạt động</div>
                            <div class="text-base font-black font-mono-num text-[#0B1437]">{{ stats.active_vps }} máy chủ</div>
                        </div>
                    </div>

                    <div class="px-5 py-4 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-200/80 text-indigo-600 flex items-center justify-center shrink-0">
                            <Globe class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="text-slate-500 font-semibold">Proxy IPv4 đang hoạt động</div>
                            <div class="text-base font-black font-mono-num text-[#0B1437]">{{ stats.active_proxies }} IP</div>
                        </div>
                    </div>

                    <div class="px-5 py-4 flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center shrink-0">
                            <Link2 class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-slate-500 font-semibold">Endpoint API gốc</div>
                            <div class="text-xs font-mono font-bold text-[#0B1437] truncate" :title="stats.api_base">
                                {{ stats.api_base }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cảnh báo lỗi đồng bộ gần nhất (nếu có) -->
                <div
                    v-if="account?.sync_error"
                    class="px-5 py-3.5 border-t border-rose-200 bg-rose-50/80 flex items-start gap-2.5 text-xs text-rose-800"
                >
                    <AlertCircle class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                    <div>
                        <strong class="font-bold">Lỗi đồng bộ gần nhất:</strong>
                        <span class="ml-1">{{ account.sync_error }}</span>
                    </div>
                </div>
            </div>

            <!-- 2. FORM CẤU HÌNH 1 BỘ API KEY CLOUDSUNNY CỐ ĐỊNH -->
            <form
                @submit.prevent="submitConfig"
                class="surface-card rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden"
            >
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200/80 text-[#1D6AE5] flex items-center justify-center shrink-0">
                            <KeyRound class="w-4 h-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-[#0B1437]">Cấu hình khóa API CloudSunny cố định</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Nhập thông tin 1 tài khoản đại lý CloudSunny duy nhất của bạn — Secret Key được mã hóa khi lưu vào cơ sở dữ liệu.
                            </p>
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2.5 text-xs font-bold text-slate-700 cursor-pointer select-none">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="w-4 h-4 rounded border-slate-300 text-[#1D6AE5] focus:ring-[#1D6AE5] cursor-pointer"
                        />
                        <span>Cho phép tự động gọi API tạo / gia hạn đơn</span>
                    </label>
                </div>

                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Email tài khoản CloudSunny</label>
                            <input
                                v-model="form.api_username"
                                type="email"
                                required
                                placeholder="vd: your-account@gmail.com"
                                class="w-full h-10 px-3.5 rounded-xl bg-white border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none text-xs font-mono transition"
                            />
                            <p v-if="form.errors.api_username" class="text-xs font-semibold text-rose-600 mt-1">
                                {{ form.errors.api_username }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">API App Key</label>
                            <input
                                v-model="form.api_app"
                                type="text"
                                required
                                placeholder="Nhập App Key từ CloudSunny"
                                class="w-full h-10 px-3.5 rounded-xl bg-white border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none text-xs font-mono transition"
                            />
                            <p v-if="form.errors.api_app" class="text-xs font-semibold text-rose-600 mt-1">
                                {{ form.errors.api_app }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            API Secret Key
                            <span v-if="account?.has_secret" class="font-medium text-emerald-700 ml-1">(Đã lưu khóa mã hóa)</span>
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.api_secret"
                                :type="showSecret ? 'text' : 'password'"
                                :required="!account?.has_secret"
                                :placeholder="
                                    account?.has_secret
                                        ? 'Để trống nếu giữ nguyên Secret Key hiện tại (hoặc nhập mới để thay đổi)'
                                        : 'Nhập API Secret Key từ CloudSunny'
                                "
                                class="w-full h-10 pl-3.5 pr-10 rounded-xl bg-white border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none text-xs font-mono transition"
                            />
                            <button
                                type="button"
                                @click="showSecret = !showSecret"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer"
                                :title="showSecret ? 'Ẩn Secret Key' : 'Hiện Secret Key'"
                            >
                                <EyeOff v-if="showSecret" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.api_secret" class="text-xs font-semibold text-rose-600 mt-1">
                            {{ form.errors.api_secret }}
                        </p>
                    </div>

                    <!-- Ghi chú cơ chế .env vs lưu trực tiếp -->
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-start gap-2.5 text-xs text-slate-600 leading-relaxed">
                        <Info class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                        <div>
                            <strong class="text-slate-800">Lưu ở đây hay file <code class="font-mono">.env</code>?</strong>
                            Nhập trực tiếp tại form này sẽ được ưu tiên sử dụng và đồng bộ số dư ngay mà không cần khởi động lại server.
                            Nếu bạn muốn lưu cố định qua <code class="font-mono">.env</code>
                            (<code class="font-mono">CLOUDSUNNY_API_USERNAME</code>, <code class="font-mono">CLOUDSUNNY_API_APP_KEY</code>,
                            <code class="font-mono">CLOUDSUNNY_API_SECRET_KEY</code>), hệ thống sẽ tự lấy khi chưa cấu hình ở đây
                            <span v-if="stats.has_env_credentials" class="font-bold text-emerald-700"> (đã phát hiện khóa trong .env)</span>.
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0B1437] hover:bg-[#162354] disabled:opacity-50 text-white text-xs font-bold cursor-pointer transition shadow-xs"
                        >
                            <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                            <Save v-else class="w-4 h-4" />
                            <span>{{ form.processing ? 'Đang lưu & đồng bộ...' : 'Lưu cấu hình & Đồng bộ số dư' }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
