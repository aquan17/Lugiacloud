<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { copyToClipboard } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { askConfirm } from '@/Composables/useConfirmAction';
import {
    Copy,
    Check,
    Eye,
    EyeOff,
    RefreshCw,
    ShieldCheck,
    Lock,
    Webhook,
    KeyRound,
    MessageCircle,
} from 'lucide-vue-next';

const props = defineProps<{
    canUseApi: boolean;
    roleLabel: string;
    discountPercent: number;
    apiKey?: string | null;
    webhookUrl?: string | null;
    baseUrl: string;
}>();

const toast = useToast();
const reveal = ref(false);
const copiedField = ref<'url' | 'key' | null>(null);

const webhookForm = useForm({
    webhook_url: props.webhookUrl || '',
});

const displayToken = computed(() => {
    if (!props.apiKey) return 'YOUR_RESELLER_API_KEY';
    return reveal.value ? props.apiKey : `${props.apiKey.slice(0, 11)}••••••••••••••••••••••••••••`;
});

async function copyField(val: string, field: 'url' | 'key', label: string) {
    if (await copyToClipboard(val)) {
        copiedField.value = field;
        toast.success(`Đã sao chép ${label}`);
        setTimeout(() => {
            if (copiedField.value === field) copiedField.value = null;
        }, 1800);
    }
}

function regenKey() {
    askConfirm({
        title: 'Cấp mới Reseller API Key',
        message: 'Khóa API cũ sẽ bị vô hiệu hóa ngay lập tức trên mọi hệ thống đang kết nối. Bạn có chắc muốn tạo khóa mới?',
        confirmText: 'Cấp mới API Key',
        variant: 'danger',
        onConfirm: () => router.post('/reseller-api/regenerate', {}, { preserveScroll: true }),
    });
}

function saveWebhook() {
    webhookForm.post('/reseller-api/webhook', { preserveScroll: true });
}
</script>

<template>
    <div v-if="canUseApi" class="surface-card overflow-hidden">
        <!-- Header Strip -->
        <div class="px-5 sm:px-6 py-3.5 bg-gradient-to-r from-[#F0F7FF] via-white to-white border-b border-slate-200/90 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold whitespace-nowrap">
                    <ShieldCheck class="w-4 h-4" />
                    <span>Đã mở quyền API ({{ roleLabel }})</span>
                </span>
                <span class="text-[13.5px] text-slate-600">
                    Mức chiết khấu tự động qua API:
                    <strong class="font-mono-num text-[#1D6AE5]">
                        {{ discountPercent > 0 ? `Giảm ${discountPercent}%` : 'Theo giá niêm yết / Sự kiện' }}
                    </strong>
                </span>
            </div>

            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span>Base URL:</span>
                <code class="px-2.5 py-1 rounded-md bg-slate-100 border border-slate-200 font-mono font-semibold text-slate-800">
                    {{ baseUrl }}
                </code>
                <button
                    type="button"
                    @click="copyField(baseUrl, 'url', 'Base URL')"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold cursor-pointer whitespace-nowrap"
                >
                    <Check v-if="copiedField === 'url'" class="w-3.5 h-3.5 text-emerald-600" />
                    <Copy v-else class="w-3.5 h-3.5" />
                    <span>{{ copiedField === 'url' ? 'Đã chép' : 'Copy' }}</span>
                </button>
            </div>
        </div>

        <!-- Symmetrical 2-Column Controls: API Key & Webhook -->
        <div class="p-5 sm:p-6 grid grid-cols-1 lg:grid-cols-2 gap-6 divide-y lg:divide-y-0 lg:divide-x divide-slate-200/80">
            <!-- Left: Secret API Key -->
            <div class="space-y-3 lg:pr-6">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <KeyRound class="w-4 h-4 text-[#1D6AE5]" />
                        <h2 class="text-[15px] font-bold text-[#0B1437]">Khóa xác thực API Key (Bearer Token)</h2>
                    </div>
                    <button
                        type="button"
                        @click="regenKey"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline cursor-pointer whitespace-nowrap"
                    >
                        <RefreshCw class="w-3.5 h-3.5" />
                        <span>Cấp lại khóa mới</span>
                    </button>
                </div>

                <div class="flex items-center gap-2.5">
                    <div class="flex-1 h-10 px-3.5 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between gap-2 font-mono text-[13.5px]">
                        <span class="truncate text-slate-900 select-all">{{ displayToken }}</span>
                        <button
                            type="button"
                            @click="reveal = !reveal"
                            :title="reveal ? 'Ẩn khóa API' : 'Hiện khóa API'"
                            class="text-slate-400 hover:text-slate-700 cursor-pointer shrink-0 p-1"
                        >
                            <EyeOff v-if="reveal" class="w-4 h-4" />
                            <Eye v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <button
                        v-if="apiKey"
                        type="button"
                        @click="copyField(apiKey, 'key', 'API Key')"
                        class="h-10 inline-flex items-center gap-1.5 px-4 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-[13px] font-semibold transition-colors cursor-pointer shrink-0 whitespace-nowrap"
                    >
                        <Check v-if="copiedField === 'key'" class="w-4 h-4" />
                        <Copy v-else class="w-4 h-4" />
                        <span>{{ copiedField === 'key' ? 'Đã chép Key' : 'Sao chép Key' }}</span>
                    </button>
                </div>

                <p class="text-[13px] text-slate-500 leading-relaxed">
                    Đính kèm vào Header <code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 text-xs">Authorization: Bearer &lt;API_KEY&gt;</code> ở mọi request gọi từ máy chủ của bạn.
                </p>
            </div>

            <!-- Right: Webhook Callback -->
            <form @submit.prevent="saveWebhook" class="space-y-3 pt-5 lg:pt-0 lg:pl-6">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Webhook class="w-4 h-4 text-[#1D6AE5]" />
                        <h2 class="text-[15px] font-bold text-[#0B1437]">Đường dẫn nhận sự kiện Webhook</h2>
                    </div>
                    <span class="text-xs font-mono text-slate-400">POST JSON · Timeout 10s</span>
                </div>

                <div class="flex items-center gap-2.5">
                    <input
                        v-model="webhookForm.webhook_url"
                        type="url"
                        placeholder="https://ten-mien-cua-ban.vn/api/webhook/lugiacloud"
                        class="flex-1 h-10 px-3.5 rounded-lg border border-slate-300 bg-white text-[13.5px] font-mono text-slate-900 focus:outline-none focus:border-[#1D6AE5]"
                    />
                    <button
                        type="submit"
                        :disabled="webhookForm.processing"
                        class="h-10 px-4 rounded-lg bg-[#0B1437] hover:bg-[#162354] text-white text-[13px] font-semibold transition-colors cursor-pointer shrink-0 whitespace-nowrap"
                    >
                        {{ webhookForm.processing ? 'Đang lưu...' : 'Lưu Webhook' }}
                    </button>
                </div>

                <p v-if="webhookForm.errors.webhook_url" class="text-[13px] text-rose-600 font-medium">
                    {{ webhookForm.errors.webhook_url }}
                </p>
                <p v-else class="text-[13px] text-slate-500 leading-relaxed">
                    Tự động bắn IP &amp; mật khẩu máy chủ sang hệ thống của bạn ngay khi cài đặt xong Hệ điều hành (<code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 text-xs">vps.created</code>).
                </p>
            </form>
        </div>
    </div>

    <!-- Locked State for Standard Customers -->
    <div
        v-else
        class="surface-card p-6 border-amber-200 bg-gradient-to-r from-amber-50/80 via-white to-white flex flex-col md:flex-row md:items-center justify-between gap-5"
    >
        <div class="flex items-start gap-4 max-w-2xl">
            <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <Lock class="w-5 h-5" />
            </div>
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-base font-extrabold text-[#0B1437]">Tài khoản chưa kích hoạt quyền Đại lý API</h2>
                    <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold whitespace-nowrap">
                        {{ roleLabel }}
                    </span>
                </div>
                <p class="text-[14px] text-slate-600 leading-relaxed">
                    Để nhận khóa <strong>API Key (`sk_...`)</strong> đấu nối bán tự động và hưởng chính sách <strong>chiết khấu giá sỉ cho Đại lý (Reseller)</strong>, vui lòng nhắn trực tiếp cho Admin để được mở quyền ngay. Bạn vẫn có thể xem đầy đủ tài liệu tích hợp chi tiết bên dưới.
                </p>
            </div>
        </div>

        <a
            href="https://zalo.me/0862579104"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[#0068FF] hover:bg-[#0057D9] text-white text-[13.5px] font-bold transition-colors shrink-0 whitespace-nowrap"
        >
            <MessageCircle class="w-4 h-4" />
            <span>Nhắn Zalo mở quyền Đại lý</span>
        </a>
    </div>
</template>
