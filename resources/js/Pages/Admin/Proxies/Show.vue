<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AdminUserPicker from '@/Components/Admin/AdminUserPicker.vue';
import { copyToClipboard, formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { askConfirm } from '@/Composables/useConfirmAction';
import {
    ArrowLeft,
    Copy,
    Check,
    Eye,
    EyeOff,
    RefreshCw,
    KeyRound,
    ArrowLeftRight,
    UserCheck,
    LifeBuoy,
    Trash2,
    Shuffle,
    Loader2,
    X,
    CalendarClock,
} from 'lucide-vue-next';

const props = defineProps<{
    proxy: any;
    renewQuotes: Array<{ key: string; label: string; months: number; price: number; provider_cost: number }>;
    transactions: Array<any>;
}>();

const toast = useToast();
const showPass = ref(false);
const copiedKey = ref<string | null>(null);
const busyAction = ref<string | null>(null);

const isResidential = computed(() => props.proxy.category === 'residential');
const targetProto = computed<'HTTP' | 'SOCKS5'>(() => (props.proxy.protocol === 'SOCKS5' ? 'HTTP' : 'SOCKS5'));

async function copyField(val: string | number | null | undefined, label: string, key: string) {
    if (!val) return;
    if (await copyToClipboard(String(val))) {
        toast.success(`Đã sao chép ${label}`);
        copiedKey.value = key;
        setTimeout(() => {
            if (copiedKey.value === key) copiedKey.value = null;
        }, 1600);
    }
}

function runSync() {
    busyAction.value = 'sync';
    router.post(`/admin/proxies/${props.proxy.id}/sync`, {}, {
        preserveScroll: true,
        onFinish: () => (busyAction.value = null),
    });
}

function confirmChangeType() {
    askConfirm({
        title: `Chuyển Proxy #${props.proxy.id} sang ${targetProto.value}`,
        message: `Xác nhận đổi giao thức Proxy #${props.proxy.id} từ ${props.proxy.protocol} sang ${targetProto.value}?`,
        confirmText: `Chuyển sang ${targetProto.value}`,
        variant: 'primary',
        onConfirm: () => {
            busyAction.value = 'type';
            router.post(`/admin/proxies/${props.proxy.id}/change-type`, { type: targetProto.value }, {
                preserveScroll: true,
                onFinish: () => (busyAction.value = null),
            });
        },
    });
}

function confirmSyncIp() {
    if (!isResidential.value) return;
    askConfirm({
        title: `Đổi IP mới cho Proxy Dân cư #${props.proxy.id}`,
        message: 'Gửi lệnh yêu cầu nhà cung cấp cấp IP dân cư mới cho Proxy này (5–10 giây)?',
        confirmText: 'Đổi IP mới',
        variant: 'primary',
        onConfirm: () => {
            busyAction.value = 'ip';
            router.post(`/admin/proxies/${props.proxy.id}/sync-ip`, {}, {
                preserveScroll: true,
                onFinish: () => (busyAction.value = null),
            });
        },
    });
}

function confirmDelete() {
    askConfirm({
        title: `Xóa Proxy #${props.proxy.id}`,
        message: `Xác nhận xóa Proxy ${props.proxy.ip || '#' + props.proxy.id} khỏi hệ thống?`,
        confirmText: 'Xóa Proxy',
        variant: 'danger',
        onConfirm: () => router.delete(`/admin/proxies/${props.proxy.id}`),
    });
}

// --- Modal Đổi User/Pass ---
const showCredsModal = ref(false);
const credsForm = useForm({
    username: props.proxy.username || '',
    password: props.proxy.password || '',
});
function openCredsModal() {
    credsForm.clearErrors();
    credsForm.username = props.proxy.username || '';
    credsForm.password = props.proxy.password || '';
    showCredsModal.value = true;
}
function randomCreds() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    let pass = '';
    for (let i = 0; i < 12; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
    credsForm.username = 'lg' + Math.random().toString(36).substring(2, 9);
    credsForm.password = pass;
}
function submitCreds() {
    credsForm.post(`/admin/proxies/${props.proxy.id}/credentials`, {
        preserveScroll: true,
        onSuccess: () => (showCredsModal.value = false),
    });
}

// --- Modal Chuyển chủ sở hữu ---
const showReassignModal = ref(false);
const reassignForm = useForm({
    user_id: '' as number | '',
    disable_auto_renew: true,
});
function submitReassign() {
    if (!reassignForm.user_id) return;
    reassignForm.post(`/admin/proxies/${props.proxy.id}/reassign`, {
        preserveScroll: true,
        onSuccess: () => {
            showReassignModal.value = false;
            reassignForm.user_id = '';
        },
    });
}

function toggleAutoRenew() {
    busyAction.value = 'autorenew';
    router.post(`/admin/proxies/${props.proxy.id}/auto-renew`, {}, {
        preserveScroll: true,
        onFinish: () => (busyAction.value = null),
    });
}

// --- Modal Gia hạn hộ Proxy ---
const showRenewModal = ref(false);
const renewForm = useForm({
    billing_cycle: 'monthly',
    charge_owner: Boolean(props.proxy.user),
    admin_note: '',
});
const selectedRenewQuote = computed(
    () => props.renewQuotes.find((q) => q.key === renewForm.billing_cycle) || props.renewQuotes[0] || null
);
function openRenewModal() {
    renewForm.clearErrors();
    renewForm.billing_cycle = props.renewQuotes[0]?.key || 'monthly';
    renewForm.charge_owner = Boolean(props.proxy.user);
    renewForm.admin_note = '';
    showRenewModal.value = true;
}
function submitRenew() {
    const q = selectedRenewQuote.value;
    const modeText = renewForm.charge_owner
        ? `Trừ ví khách ${props.proxy.user?.email}: ${formatVnd(q?.price || 0)}`
        : `KHÔNG trừ ví khách (0đ — chỉ trừ vốn gốc ~${formatVnd(q?.provider_cost || 0)} trên CloudSunny)`;
    askConfirm({
        title: `Gia hạn hộ Proxy #${props.proxy.id}`,
        message: `Chu kỳ: ${q?.label || renewForm.billing_cycle}\n• ${modeText}`,
        confirmText: 'Gia hạn ngay',
        variant: 'primary',
        onConfirm: () => {
            renewForm.post(`/admin/proxies/${props.proxy.id}/renew`, {
                preserveScroll: true,
                onSuccess: () => (showRenewModal.value = false),
            });
        },
    });
}
</script>

<template>
    <AdminLayout :title="`Chi tiết Proxy #${proxy.id}`">
        <Head :title="`Admin · Proxy #${proxy.id}`" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link href="/admin/proxies" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-[#0B1437]">
                    <ArrowLeft class="w-4 h-4" />
                    <span>Quay lại Danh sách Proxy</span>
                </Link>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 font-mono font-bold text-slate-700">
                        Local #{{ proxy.id }} · PRX#{{ proxy.provider_proxy_id || '—' }}
                    </span>
                    <span class="px-2.5 py-1 rounded-lg bg-blue-50 border border-blue-200 font-bold text-[#1D6AE5]">
                        Nguồn: {{ proxy.account_name }}
                    </span>
                </div>
            </div>

            <!-- Hero Summary -->
            <div class="surface-card p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-5">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-xl font-black text-[#0B1437]">{{ proxy.product_name }}</h2>
                            <StatusBadge :state="proxy.state" :label="proxy.status" />
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-mono text-xs font-bold">{{ proxy.protocol }}</span>
                        </div>
                        <div class="font-mono text-xs text-slate-600 font-semibold mt-1">
                            {{ proxy.ip ? `${proxy.ip}:${proxy.port}` : 'Đang cấp phát IP...' }}
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="proxy.connection_string"
                            type="button"
                            @click="copyField(proxy.connection_string, 'Chuỗi kết nối', 'conn')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer"
                        >
                            <component :is="copiedKey === 'conn' ? Check : Copy" class="w-3.5 h-3.5" />
                            <span>Copy IP:Port:User:Pass</span>
                        </button>
                        <button
                            v-if="proxy.is_linked"
                            type="button"
                            :disabled="!!busyAction"
                            @click="runSync"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold cursor-pointer"
                        >
                            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': busyAction === 'sync' }" />
                            <span>Đồng bộ live</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 pt-4 border-t border-slate-200/80 text-xs">
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Chủ sở hữu</div>
                        <Link v-if="proxy.user" :href="`/admin/users/${proxy.user.id}`" class="block mt-1 group">
                            <div class="font-extrabold text-[#0B1437] group-hover:text-[#1D6AE5] truncate">{{ proxy.user.name }}</div>
                            <div class="font-mono text-[11px] text-slate-600 group-hover:text-[#1D6AE5] truncate">{{ proxy.user.email }}</div>
                        </Link>
                        <div v-else class="mt-1 text-slate-400 italic">Đã xóa</div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Loại Proxy</div>
                        <div class="font-extrabold text-sm text-[#0B1437] mt-1">{{ isResidential ? 'Dân cư VN (Residential)' : 'Datacenter VN' }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">Chu kỳ: {{ proxy.billing_cycle_label }}</div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Đã thu khách</div>
                        <div class="font-mono-num font-extrabold text-sm text-[#0B1437] mt-1">{{ formatVnd(proxy.paid_amount) }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5 flex items-center justify-between gap-2">
                            <span>Auto: <strong>{{ proxy.is_auto_renew ? 'Bật' : 'Tắt' }}</strong></span>
                            <button
                                v-if="proxy.state !== 'deleted'"
                                type="button"
                                :disabled="busyAction === 'autorenew'"
                                @click="toggleAutoRenew"
                                class="px-2 py-0.5 rounded border text-[10.5px] font-bold cursor-pointer transition"
                                :class="proxy.is_auto_renew ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-100'"
                            >
                                {{ proxy.is_auto_renew ? 'Tắt' : 'Bật' }}
                            </button>
                        </div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Hết hạn</div>
                        <div class="font-mono-num font-extrabold text-sm text-[#0B1437] mt-1">{{ proxy.expires_at || '—' }}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Tạo lúc: {{ proxy.created_at || '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left (7 cols): Auth & Transactions -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="surface-card p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 text-xs">
                        <h3 class="text-sm font-extrabold text-[#0B1437] flex items-center gap-2">
                            <KeyRound class="w-4 h-4 text-[#1D6AE5]" />
                            <span>Thông số kết nối &amp; xác thực</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="text-slate-500 font-semibold mb-1">IPv4 &amp; Port ({{ proxy.protocol }})</div>
                                <div class="flex items-center justify-between font-mono font-extrabold text-[#0B1437]">
                                    <span>{{ proxy.ip }}:{{ proxy.port }}</span>
                                    <button type="button" @click="copyField(`${proxy.ip}:${proxy.port}`, 'IP:Port', 'ipport')" class="text-slate-400 hover:text-[#1D6AE5] cursor-pointer">
                                        <component :is="copiedKey === 'ipport' ? Check : Copy" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="text-slate-500 font-semibold mb-1">Username</div>
                                <div class="flex items-center justify-between font-mono font-extrabold text-[#0B1437]">
                                    <span class="truncate">{{ proxy.username || '—' }}</span>
                                    <button type="button" @click="copyField(proxy.username, 'Username', 'user')" class="text-slate-400 hover:text-[#1D6AE5] cursor-pointer">
                                        <component :is="copiedKey === 'user' ? Check : Copy" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="sm:col-span-2 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="text-slate-500 font-semibold mb-1">Password</div>
                                <div class="flex items-center justify-between font-mono font-extrabold text-[#0B1437]">
                                    <span>{{ showPass ? proxy.password || '—' : '••••••••••••' }}</span>
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" @click="showPass = !showPass" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                                            <component :is="showPass ? EyeOff : Eye" class="w-3.5 h-3.5" />
                                        </button>
                                        <button type="button" @click="copyField(proxy.password, 'Password', 'pass')" class="text-slate-400 hover:text-[#1D6AE5] cursor-pointer">
                                            <component :is="copiedKey === 'pass' ? Check : Copy" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                        <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 text-xs font-extrabold uppercase tracking-wider text-slate-700">
                            Giao dịch liên quan ({{ transactions.length }})
                        </div>

                        <div v-if="transactions.length === 0" class="py-8 text-center text-xs text-slate-500">
                            Chưa có giao dịch riêng gắn với Proxy này.
                        </div>

                        <template v-else>
                            <!-- Mobile stacked card view (không cuộn ngang) -->
                            <div class="sm:hidden divide-y divide-slate-200/80 text-xs">
                                <div v-for="t in transactions" :key="`m-${t.id}`" class="p-4 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 font-mono font-bold text-[11px] text-[#0B1437]">{{ t.code }}</span>
                                            <span v-if="t.status === 'refunded'" class="px-1.5 py-0.5 rounded bg-rose-50 border border-rose-200 text-[10.5px] font-bold text-rose-600">Đã hoàn tiền</span>
                                        </div>
                                        <span class="font-mono-num font-black text-sm shrink-0" :class="t.status === 'refunded' ? 'text-slate-400 line-through' : 'text-[#0B1437]'">
                                            {{ formatVnd(t.amount) }}
                                        </span>
                                    </div>
                                    <div class="text-slate-700 leading-relaxed break-words">{{ t.description }}</div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-500 pt-0.5">
                                        <span v-if="t.provider_cost > 0" class="font-mono-num">Vốn: {{ formatVnd(t.provider_cost) }}</span>
                                        <span v-else>&nbsp;</span>
                                        <span class="font-mono-num">{{ t.created_at }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop table view -->
                            <div class="hidden sm:block overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <tbody class="divide-y divide-slate-200/80">
                                        <tr v-for="t in transactions" :key="t.id" class="hover:bg-slate-50/70">
                                            <td class="py-3 px-4 font-mono font-bold text-[#0B1437] whitespace-nowrap">{{ t.code }}</td>
                                            <td class="py-3 px-4 text-slate-700">
                                                <div>{{ t.description }}</div>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span v-if="t.provider_cost > 0" class="text-[11px] font-mono-num text-slate-500">Vốn: {{ formatVnd(t.provider_cost) }}</span>
                                                    <span v-if="t.status === 'refunded'" class="text-[11px] font-bold text-rose-600">Đã hoàn tiền</span>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-right font-mono-num font-extrabold whitespace-nowrap" :class="t.status === 'refunded' ? 'text-slate-400 line-through' : 'text-[#0B1437]'">{{ formatVnd(t.amount) }}</td>
                                            <td class="py-3 px-4 text-right font-mono-num text-slate-500 whitespace-nowrap">{{ t.created_at }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Right (5 cols): Admin Actions -->
                <div class="lg:col-span-5 space-y-6 text-xs">
                    <div class="surface-card p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-3">
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Thao tác quản trị Proxy</h3>
                        <button
                            v-if="proxy.is_linked && proxy.state !== 'deleted' && renewQuotes.length > 0"
                            type="button"
                            @click="openRenewModal"
                            class="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer transition"
                        >
                            <CalendarClock class="w-4 h-4" /><span>Gia hạn hộ Proxy</span>
                        </button>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <button v-if="proxy.is_linked && proxy.state !== 'deleted'" type="button" @click="openCredsModal" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 font-bold text-slate-800 cursor-pointer">
                                <KeyRound class="w-4 h-4 text-[#1D6AE5]" /><span>Đổi User / Pass</span>
                            </button>

                            <button v-if="proxy.is_linked && proxy.state !== 'deleted'" type="button" :disabled="!!busyAction" @click="confirmChangeType" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 font-bold text-slate-800 cursor-pointer">
                                <ArrowLeftRight class="w-4 h-4 text-[#1D6AE5]" /><span>Đổi sang {{ targetProto }}</span>
                            </button>

                            <button v-if="proxy.is_linked && isResidential && proxy.state !== 'deleted'" type="button" :disabled="!!busyAction" @click="confirmSyncIp" class="sm:col-span-2 inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 font-bold text-emerald-800 cursor-pointer">
                                <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': busyAction === 'ip' }" /><span>Cấp IP Dân cư mới</span>
                            </button>

                            <button type="button" @click="showReassignModal = true" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 font-bold text-slate-800 cursor-pointer">
                                <UserCheck class="w-4 h-4 text-[#1D6AE5]" /><span>Chuyển chủ sở hữu</span>
                            </button>

                            <Link
                                v-if="proxy.provider_proxy_id"
                                :href="`/admin/tickets?service_type=proxy&service_id=${proxy.provider_proxy_id}`"
                                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 font-bold text-slate-800"
                            >
                                <LifeBuoy class="w-4 h-4 text-[#1D6AE5]" /><span>Mở Ticket báo lỗi</span>
                            </Link>
                        </div>
                    </div>

                    <div class="surface-card p-5 rounded-2xl border border-rose-200 bg-rose-50/40 space-y-3">
                        <h3 class="text-sm font-extrabold text-rose-800">Xóa Proxy khỏi hệ thống</h3>
                        <button type="button" @click="confirmDelete" class="w-full py-2.5 rounded-xl border border-rose-300 bg-white hover:bg-rose-600 text-rose-700 hover:text-white font-bold cursor-pointer inline-flex items-center justify-center gap-1.5 transition">
                            <Trash2 class="w-3.5 h-3.5" /><span>Xóa bản ghi Proxy</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Gia hạn hộ Proxy -->
        <Teleport to="body">
            <div v-if="showRenewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="!renewForm.processing && (showRenewModal = false)">
                <form @submit.prevent="submitRenew" class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-6 space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 class="text-sm font-extrabold text-[#0B1437]">Gia hạn hộ Proxy #{{ proxy.id }}</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Hạn hiện tại: {{ proxy.expires_at || '—' }} · Ví chủ sở hữu: {{ formatVnd(proxy.user?.balance || 0) }}</p>
                        </div>
                        <button type="button" @click="!renewForm.processing && (showRenewModal = false)" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Chọn chu kỳ gia hạn</label>
                        <select v-model="renewForm.billing_cycle" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white font-bold">
                            <option v-for="q in renewQuotes" :key="q.key" :value="q.key">
                                {{ q.label }} — Giá khách: {{ formatVnd(q.price) }} (Vốn gốc: {{ formatVnd(q.provider_cost) }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button
                            type="button"
                            :disabled="!proxy.user"
                            @click="renewForm.charge_owner = true"
                            class="p-3 rounded-xl border text-left cursor-pointer transition disabled:opacity-50"
                            :class="renewForm.charge_owner ? 'border-[#1D6AE5] bg-blue-50/70 ring-1 ring-[#1D6AE5]' : 'border-slate-200 hover:bg-slate-50'"
                        >
                            <div class="font-extrabold text-[#0B1437]">Trừ ví chủ sở hữu</div>
                            <div class="text-[11px] text-slate-600 mt-0.5">Trừ {{ formatVnd(selectedRenewQuote?.price || 0) }} &amp; tính doanh thu</div>
                        </button>
                        <button
                            type="button"
                            @click="renewForm.charge_owner = false"
                            class="p-3 rounded-xl border text-left cursor-pointer transition"
                            :class="!renewForm.charge_owner ? 'border-amber-500 bg-amber-50/70 ring-1 ring-amber-500' : 'border-slate-200 hover:bg-slate-50'"
                        >
                            <div class="font-extrabold text-[#0B1437]">Không trừ ví khách (0đ)</div>
                            <div class="text-[11px] text-slate-600 mt-0.5">Chỉ gọi CloudSunny, ghi sổ vốn gốc</div>
                        </button>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Ghi chú nội bộ (tùy chọn)</label>
                        <input v-model="renewForm.admin_note" type="text" placeholder="VD: Khách chuyển khoản trực tiếp" class="w-full px-3 py-2 rounded-xl border border-slate-300" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="!renewForm.processing && (showRenewModal = false)" class="px-4 py-2 rounded-xl border border-slate-300 font-bold cursor-pointer">Hủy</button>
                        <button type="submit" :disabled="renewForm.processing || !selectedRenewQuote" class="px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50">
                            <Loader2 v-if="renewForm.processing" class="w-3.5 h-3.5 animate-spin" />
                            <span>Gia hạn Proxy</span>
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Modal Đổi User/Pass -->
        <Teleport to="body">
            <div v-if="showCredsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showCredsModal = false">
                <form @submit.prevent="submitCreds" class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-6 space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Đổi tài khoản Proxy #{{ proxy.id }}</h3>
                        <button type="button" @click="showCredsModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" @click="randomCreds" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 text-[#1D6AE5] font-bold cursor-pointer">
                            <Shuffle class="w-3 h-3" /><span>Tạo ngẫu nhiên</span>
                        </button>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Username mới</label>
                        <input v-model="credsForm.username" type="text" required minlength="5" maxlength="100" class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono" />
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Password mới</label>
                        <input v-model="credsForm.password" type="text" required minlength="5" maxlength="100" class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showCredsModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-bold cursor-pointer">Hủy</button>
                        <button type="submit" :disabled="credsForm.processing" class="px-4 py-2 rounded-xl bg-[#1D6AE5] text-white font-bold cursor-pointer inline-flex items-center gap-1.5">
                            <Loader2 v-if="credsForm.processing" class="w-3.5 h-3.5 animate-spin" /><span>Lưu thay đổi</span>
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Modal Chuyển chủ sở hữu -->
        <Teleport to="body">
            <div v-if="showReassignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showReassignModal = false">
                <form @submit.prevent="submitReassign" class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-200">
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Chuyển chủ sở hữu Proxy</h3>
                        <button type="button" @click="showReassignModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                    </div>
                    <AdminUserPicker v-model="reassignForm.user_id" />
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="reassignForm.disable_auto_renew" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                        <span>Tắt tự động gia hạn sau khi chuyển</span>
                    </label>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showReassignModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-bold cursor-pointer">Hủy</button>
                        <button type="submit" :disabled="reassignForm.processing || !reassignForm.user_id" class="px-4 py-2 rounded-xl bg-[#1D6AE5] text-white font-bold cursor-pointer disabled:opacity-50">Xác nhận chuyển</button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AdminLayout>
</template>
