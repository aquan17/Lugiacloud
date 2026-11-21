<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import OsIcon from '@/Components/OsIcon.vue';
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
    Play,
    Square,
    RotateCw,
    RefreshCw,
    Terminal,
    KeyRound,
    HardDrive,
    UserCheck,
    LifeBuoy,
    ShieldAlert,
    Loader2,
    X,
    Disc,
} from 'lucide-vue-next';

const props = defineProps<{
    vps: any;
    images: Array<{ id: number; label: string; distro: string; group: string }>;
    transactions: Array<any>;
}>();

const toast = useToast();
const showPass = ref(false);
const copiedKey = ref<string | null>(null);
const busyAction = ref<string | null>(null);

async function copyField(val: string | null | undefined, label: string, key: string) {
    if (!val) return;
    if (await copyToClipboard(val)) {
        toast.success(`Đã sao chép ${label}`);
        copiedKey.value = key;
        setTimeout(() => {
            if (copiedKey.value === key) copiedKey.value = null;
        }, 1600);
    }
}

function runSync() {
    busyAction.value = 'sync';
    router.post(`/admin/instances/${props.vps.id}/sync`, {}, {
        preserveScroll: true,
        onFinish: () => (busyAction.value = null),
    });
}

function runPower(action: 'boot' | 'shutdown' | 'reboot') {
    const labelMap = { boot: 'Bật nguồn', shutdown: 'Tắt nguồn', reboot: 'Khởi động lại' };
    askConfirm({
        title: `${labelMap[action]} VPS ${props.vps.label}`,
        message: `Xác nhận gửi lệnh ${labelMap[action].toLowerCase()} cho máy chủ ${props.vps.label} (${props.vps.public_ip || 'Chờ IP'})?`,
        confirmText: labelMap[action],
        variant: action === 'shutdown' ? 'danger' : 'primary',
        onConfirm: () => {
            busyAction.value = action;
            router.post(`/admin/instances/${props.vps.id}/power`, { action }, {
                preserveScroll: true,
                onFinish: () => (busyAction.value = null),
            });
        },
    });
}

// --- Modal Đổi mật khẩu OS ---
const showPassModal = ref(false);
const passForm = useForm({
    password_old: props.vps.root_password || '',
    password_new: '',
});
function openPassModal() {
    passForm.clearErrors();
    passForm.password_old = props.vps.root_password || '';
    passForm.password_new = '';
    showPassModal.value = true;
}
function submitPassword() {
    passForm.post(`/admin/instances/${props.vps.id}/password`, {
        preserveScroll: true,
        onSuccess: () => (showPassModal.value = false),
    });
}

// --- Modal Cài lại OS ---
const showRebuildModal = ref(false);
const rebuildForm = useForm({
    os_id: props.vps.os_id || props.images[0]?.id || 1,
});
function submitRebuild() {
    askConfirm({
        title: `Cài đặt lại OS cho ${props.vps.label}`,
        message: 'Toàn bộ dữ liệu trên ổ cứng VPS sẽ bị xóa sạch để cài mới Hệ điều hành. Xác nhận tiếp tục?',
        confirmText: 'Cài lại OS ngay',
        variant: 'danger',
        onConfirm: () => {
            rebuildForm.post(`/admin/instances/${props.vps.id}/rebuild`, {
                preserveScroll: true,
                onSuccess: () => (showRebuildModal.value = false),
            });
        },
    });
}

// --- Modal Mở rộng ổ cứng ---
const showExtendModal = ref(false);
const extendForm = useForm({
    password_vps: props.vps.root_password || '',
});
function openExtendModal() {
    extendForm.clearErrors();
    extendForm.password_vps = props.vps.root_password || '';
    showExtendModal.value = true;
}
function submitExtendDisk() {
    extendForm.post(`/admin/instances/${props.vps.id}/extend-disk`, {
        preserveScroll: true,
        onSuccess: () => (showExtendModal.value = false),
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
    reassignForm.post(`/admin/instances/${props.vps.id}/reassign`, {
        preserveScroll: true,
        onSuccess: () => {
            showReassignModal.value = false;
            reassignForm.user_id = '';
        },
    });
}

// --- Modal Hủy & Hoàn tiền VPS (Check-Refund -> Cancel-Refund) ---
const showRefundModal = ref(false);
const checkingRefund = ref(false);
const refundPreview = ref<any>(null);
const refundError = ref<string | null>(null);
const cancelRefundForm = useForm({
    customer_refund_amount: 0,
    refund_note: '',
});

async function openCancelRefundModal() {
    showRefundModal.value = true;
    checkingRefund.value = true;
    refundPreview.value = null;
    refundError.value = null;
    cancelRefundForm.reset();

    try {
        const { data } = await axios.post(`/admin/instances/${props.vps.id}/check-refund`);
        if (data?.ok) {
            refundPreview.value = data.data;
            cancelRefundForm.customer_refund_amount = 0;
        } else {
            refundError.value = data?.message || 'Không thể kiểm tra điều kiện hủy & hoàn tiền.';
        }
    } catch (err: any) {
        refundError.value = err?.response?.data?.message || 'Không thể kiểm tra điều kiện hoàn tiền từ hạ tầng gốc.';
    } finally {
        checkingRefund.value = false;
    }
}

function confirmCancelRefund() {
    if (!refundPreview.value || cancelRefundForm.processing) return;
    const custAmt = Number(cancelRefundForm.customer_refund_amount || 0);
    askConfirm({
        title: `XÁC NHẬN HỦY & HOÀN TIỀN VPS ${props.vps.label}`,
        message:
            `VPS sẽ bị HỦY VĨNH VIỄN ngay trên nhà cung cấp và không thể hoàn tác.\n` +
            `• Hoàn về ví đại lý CloudSunny: ${formatVnd(refundPreview.value.refund_amount)}\n` +
            `• Cộng hoàn vào ví khách hàng: ${custAmt > 0 ? formatVnd(custAmt) : '0đ (không hoàn ví khách)'}`,
        confirmText: 'Xác nhận Hủy & Hoàn tiền',
        variant: 'danger',
        onConfirm: () => {
            cancelRefundForm.post(`/admin/instances/${props.vps.id}/cancel-refund`, {
                preserveScroll: true,
                onSuccess: () => {
                    showRefundModal.value = false;
                },
            });
        },
    });
}
</script>

<template>
    <AdminLayout :title="`Chi tiết VPS #${vps.id} — ${vps.label}`">
        <Head :title="`Admin · VPS ${vps.label}`" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link href="/admin/instances" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-[#0B1437]">
                    <ArrowLeft class="w-4 h-4" />
                    <span>Quay lại Danh sách VPS</span>
                </Link>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 font-mono font-bold text-slate-700">
                        Local #{{ vps.id }} · PVS#{{ vps.provider_vps_id || '—' }}
                    </span>
                    <span class="px-2.5 py-1 rounded-lg bg-blue-50 border border-blue-200 font-bold text-[#1D6AE5]">
                        Nguồn: {{ vps.account_name }}
                    </span>
                </div>
            </div>

            <!-- Hero Command Card -->
            <div class="surface-card p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-5">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0">
                            <OsIcon :distro="vps.os_distro" :size="28" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h2 class="text-xl font-black text-[#0B1437] truncate">{{ vps.label }}</h2>
                                <StatusBadge :state="vps.state" :label="vps.status" />
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-xs font-bold">{{ vps.plan_name }}</span>
                            </div>
                            <div class="text-xs text-slate-600 font-medium mt-1">
                                {{ vps.os_label }} · {{ vps.cpu }} vCPU · {{ vps.ram }} GB RAM · {{ vps.disk }} GB SSD
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            v-if="vps.is_linked && vps.state === 'running'"
                            :href="`/admin/instances/${vps.id}/console`"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold transition"
                        >
                            <Terminal class="w-3.5 h-3.5" />
                            <span>VNC Console</span>
                        </a>

                        <div v-if="vps.is_linked && !vps.is_deleted" class="inline-flex items-center p-1 rounded-xl bg-slate-100 border border-slate-200 gap-1">
                            <button type="button" :disabled="!!busyAction" @click="runPower('boot')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white hover:bg-emerald-50 text-emerald-700 text-xs font-bold cursor-pointer">
                                <Play class="w-3.5 h-3.5" /><span>Bật</span>
                            </button>
                            <button type="button" :disabled="!!busyAction" @click="runPower('reboot')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold cursor-pointer">
                                <RotateCw class="w-3.5 h-3.5" /><span>Reboot</span>
                            </button>
                            <button type="button" :disabled="!!busyAction" @click="runPower('shutdown')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white hover:bg-amber-50 text-amber-700 text-xs font-bold cursor-pointer">
                                <Square class="w-3.5 h-3.5" /><span>Tắt</span>
                            </button>
                            <button type="button" :disabled="!!busyAction" @click="runSync" title="Đồng bộ trạng thái live" class="p-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-600 cursor-pointer">
                                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': busyAction === 'sync' }" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4 Admin KPI Strips -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 pt-4 border-t border-slate-200/80 text-xs">
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Chủ sở hữu</div>
                        <Link v-if="vps.user" :href="`/admin/users/${vps.user.id}`" class="block mt-1 group">
                            <div class="font-extrabold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors truncate">{{ vps.user.name }}</div>
                            <div class="font-mono text-[11px] text-slate-600 group-hover:text-[#1D6AE5] transition-colors truncate">{{ vps.user.email }}</div>
                        </Link>
                        <div v-else class="mt-1 text-slate-400 italic">Đã xóa</div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Doanh thu / Vốn / Lãi</div>
                        <div class="font-mono-num font-extrabold text-sm text-[#0B1437] mt-1">{{ formatVnd(vps.paid_amount) }}</div>
                        <div class="font-mono-num text-[11px] mt-0.5 flex items-center gap-2">
                            <span class="text-slate-600">Vốn: {{ formatVnd(vps.provider_cost) }}</span>
                            <span :class="vps.gross_profit >= 0 ? 'text-emerald-700 font-bold' : 'text-rose-600 font-bold'">
                                ({{ vps.gross_profit >= 0 ? '+' : '' }}{{ formatVnd(vps.gross_profit) }})
                            </span>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Thời hạn &amp; Chu kỳ</div>
                        <div class="font-mono-num font-extrabold text-sm text-[#0B1437] mt-1">{{ vps.expires_at || '—' }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">
                            {{ vps.billing_cycle_label }} · Auto-renew: <strong>{{ vps.is_auto_renew ? 'Bật' : 'Tắt' }}</strong>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Khởi tạo lúc</div>
                        <div class="font-mono-num font-extrabold text-sm text-[#0B1437] mt-1">{{ vps.created_at || '—' }}</div>
                        <div class="font-mono text-[11px] text-slate-500 mt-0.5 truncate">Order: {{ vps.provider_order_id || '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left (7 cols) -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="surface-card p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-extrabold text-[#0B1437] flex items-center gap-2">
                                <KeyRound class="w-4 h-4 text-[#1D6AE5]" />
                                <span>Thông tin đăng nhập máy chủ</span>
                            </h3>
                            <button
                                v-if="vps.public_ip && vps.root_password"
                                type="button"
                                @click="copyField(`${vps.public_ip}|${vps.login_username}|${vps.root_password}`, 'IP|User|Pass', 'full')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer"
                            >
                                <component :is="copiedKey === 'full' ? Check : Copy" class="w-3.5 h-3.5" />
                                <span>Copy IP|User|Pass</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="text-slate-500 font-semibold mb-1">Địa chỉ IPv4</div>
                                <div class="flex items-center justify-between gap-2 font-mono font-extrabold text-[#0B1437]">
                                    <span class="truncate">{{ vps.public_ip || 'Đang cấp phát...' }}</span>
                                    <button type="button" @click="copyField(vps.public_ip, 'IPv4', 'ip')" class="text-slate-400 hover:text-[#1D6AE5] cursor-pointer">
                                        <component :is="copiedKey === 'ip' ? Check : Copy" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="text-slate-500 font-semibold mb-1">Username</div>
                                <div class="flex items-center justify-between gap-2 font-mono font-extrabold text-[#0B1437]">
                                    <span class="truncate">{{ vps.login_username }}</span>
                                    <button type="button" @click="copyField(vps.login_username, 'Username', 'user')" class="text-slate-400 hover:text-[#1D6AE5] cursor-pointer">
                                        <component :is="copiedKey === 'user' ? Check : Copy" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="text-slate-500 font-semibold mb-1">Password</div>
                                <div class="flex items-center justify-between gap-2 font-mono font-extrabold text-[#0B1437]">
                                    <span class="truncate">{{ showPass ? vps.root_password || '—' : '••••••••••••' }}</span>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <button type="button" @click="showPass = !showPass" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                                            <component :is="showPass ? EyeOff : Eye" class="w-3.5 h-3.5" />
                                        </button>
                                        <button type="button" @click="copyField(vps.root_password, 'Password', 'pass')" class="text-slate-400 hover:text-[#1D6AE5] cursor-pointer">
                                            <component :is="copiedKey === 'pass' ? Check : Copy" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                        <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 text-xs font-extrabold uppercase tracking-wider text-slate-700">
                            Lịch sử giao dịch gắn với VPS này ({{ transactions.length }})
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <tbody class="divide-y divide-slate-200/80">
                                    <tr v-if="transactions.length === 0">
                                        <td class="py-8 text-center text-slate-500">Chưa có giao dịch ghi nhận.</td>
                                    </tr>
                                    <tr v-for="t in transactions" :key="t.id">
                                        <td class="py-3 px-4 font-mono font-bold text-[#0B1437] whitespace-nowrap">{{ t.code }}</td>
                                        <td class="py-3 px-4 text-slate-700">
                                            <div>{{ t.description }}</div>
                                            <span v-if="t.status === 'refunded'" class="text-[11px] font-bold text-rose-600">Đã hoàn tiền</span>
                                        </td>
                                        <td class="py-3 px-4 text-right font-mono-num font-extrabold text-[#0B1437] whitespace-nowrap">{{ formatVnd(t.amount) }}</td>
                                        <td class="py-3 px-4 text-right font-mono-num text-slate-500 whitespace-nowrap">{{ t.created_at }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="surface-card p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-3 text-xs">
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Công cụ quản trị kỹ thuật</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <button type="button" @click="showReassignModal = true" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 font-bold text-slate-800 cursor-pointer transition">
                                <UserCheck class="w-4 h-4 text-[#1D6AE5]" /><span>Chuyển chủ sở hữu</span>
                            </button>

                            <Link
                                v-if="vps.provider_vps_id"
                                :href="`/admin/tickets?service_type=vps&service_id=${vps.provider_vps_id}`"
                                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 font-bold text-slate-800 transition"
                            >
                                <LifeBuoy class="w-4 h-4 text-[#1D6AE5]" /><span>Mở Ticket báo lỗi</span>
                            </Link>

                            <button v-if="vps.is_linked && !vps.is_deleted" type="button" @click="openPassModal" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 font-bold text-slate-800 cursor-pointer transition">
                                <KeyRound class="w-4 h-4 text-slate-600" /><span>Đổi mật khẩu OS</span>
                            </button>

                            <button v-if="vps.is_linked && !vps.is_deleted" type="button" @click="showRebuildModal = true" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 font-bold text-slate-800 cursor-pointer transition">
                                <Disc class="w-4 h-4 text-slate-600" /><span>Cài đặt lại OS</span>
                            </button>

                            <button v-if="vps.is_linked && !vps.is_deleted" type="button" @click="openExtendModal" class="sm:col-span-2 inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 font-bold text-emerald-800 cursor-pointer transition">
                                <HardDrive class="w-4 h-4" /><span>Mở rộng phân vùng SSD ({{ vps.disk }} GB)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Admin-only Cancel & Refund VPS -->
                    <div v-if="vps.is_linked && !vps.is_deleted" class="surface-card p-5 sm:p-6 rounded-2xl border border-rose-200 bg-rose-50/40 space-y-3 text-xs">
                        <div class="flex items-center gap-2 text-rose-800">
                            <ShieldAlert class="w-4 h-4" />
                            <h3 class="text-sm font-extrabold">Hủy &amp; Hoàn tiền VPS (Chỉ Admin)</h3>
                        </div>
                        <p class="text-slate-700 leading-relaxed">
                            Kiểm tra chính xác số tiền nhà cung cấp hoàn về ví đại lý (trong 48h hoàn 100%, quá 48h hoàn theo số ngày còn lại), sau đó Admin tự chọn có cộng hoàn vào ví khách hàng hay không.
                        </p>
                        <button type="button" @click="openCancelRefundModal" class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold cursor-pointer transition">
                            Kiểm tra hoàn tiền &amp; Hủy VPS...
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <Teleport to="body">
            <div v-if="showPassModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showPassModal = false">
                <form @submit.prevent="submitPassword" class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-6 space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Đổi mật khẩu OS — {{ vps.label }}</h3>
                        <button type="button" @click="showPassModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Mật khẩu hiện tại</label>
                        <input v-model="passForm.password_old" type="text" required class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono" />
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Mật khẩu mới (≥ 12 ký tự: hoa, thường, số)</label>
                        <input v-model="passForm.password_new" type="text" required placeholder="VD: LugiaAdmin2026" class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono" />
                        <p v-if="passForm.errors.password_new" class="mt-1 text-rose-600 font-semibold">{{ passForm.errors.password_new }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showPassModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-bold cursor-pointer">Hủy</button>
                        <button type="submit" :disabled="passForm.processing" class="px-4 py-2 rounded-xl bg-[#0B1437] text-white font-bold cursor-pointer disabled:opacity-50">
                            {{ passForm.processing ? 'Đang đổi...' : 'Lưu mật khẩu mới' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showRebuildModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showRebuildModal = false">
                <form @submit.prevent="submitRebuild" class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-6 space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Cài đặt lại Hệ điều hành</h3>
                        <button type="button" @click="showRebuildModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Chọn Hệ điều hành mới</label>
                        <select v-model="rebuildForm.os_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white font-semibold">
                            <option v-for="img in images" :key="img.id" :value="img.id">{{ img.label }}</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showRebuildModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-bold cursor-pointer">Hủy</button>
                        <button type="submit" :disabled="rebuildForm.processing" class="px-4 py-2 rounded-xl bg-rose-600 text-white font-bold cursor-pointer disabled:opacity-50">Cài lại OS</button>
                    </div>
                </form>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showExtendModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showExtendModal = false">
                <form @submit.prevent="submitExtendDisk" class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-6 space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Mở rộng phân vùng SSD</h3>
                        <button type="button" @click="showExtendModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Mật khẩu hiện tại của VPS</label>
                        <input v-model="extendForm.password_vps" type="text" required class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showExtendModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-bold cursor-pointer">Hủy</button>
                        <button type="submit" :disabled="extendForm.processing" class="px-4 py-2 rounded-xl bg-[#0B1437] text-white font-bold cursor-pointer disabled:opacity-50">
                            {{ extendForm.processing ? 'Đang gửi...' : 'Thực hiện Extend Disk' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showReassignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showReassignModal = false">
                <form @submit.prevent="submitReassign" class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-200">
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Chuyển chủ sở hữu VPS</h3>
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

        <Teleport to="body">
            <div v-if="showRefundModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="!cancelRefundForm.processing && (showRefundModal = false)">
                <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden text-xs">
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-extrabold text-[#0B1437]">Hủy &amp; Hoàn tiền VPS — {{ vps.label }}</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Kiểm tra trực tiếp số tiền hoàn từ nhà cung cấp trước khi xác nhận</p>
                        </div>
                        <button type="button" @click="!cancelRefundForm.processing && (showRefundModal = false)" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div v-if="checkingRefund" class="py-8 flex flex-col items-center justify-center gap-2 text-slate-600">
                            <Loader2 class="w-6 h-6 animate-spin text-[#1D6AE5]" />
                            <span class="font-semibold">Đang kiểm tra công thức hoàn tiền từ hạ tầng...</span>
                        </div>

                        <div v-else-if="refundError" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 font-semibold leading-relaxed">
                            {{ refundError }}
                        </div>

                        <template v-else-if="refundPreview">
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-slate-600">Hoàn về ví đại lý CloudSunny:</span>
                                    <span class="font-mono-num text-sm font-black text-emerald-700">{{ formatVnd(refundPreview.refund_amount) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-600">Khách đã thanh toán cho VPS này:</span>
                                    <span class="font-mono-num font-bold text-[#0B1437]">{{ formatVnd(refundPreview.customer_paid_amount) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-600">Mốc {{ refundPreview.full_refund_hours }} giờ:</span>
                                    <span class="font-bold" :class="refundPreview.in_full_refund_window ? 'text-emerald-700' : 'text-amber-700'">
                                        {{ refundPreview.in_full_refund_window ? 'Trong hạn hoàn 100%' : 'Hoàn theo số ngày còn lại' }}
                                    </span>
                                </div>
                                <div v-if="refundPreview.formula" class="pt-2 border-t border-slate-200 text-[11.5px] text-slate-700 leading-relaxed">
                                    <strong>Công thức gốc:</strong> {{ refundPreview.formula }}
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    Đã hủy hôm nay: <strong>{{ refundPreview.used_today }}</strong> / {{ refundPreview.daily_limit ?? 'Không giới hạn' }} VPS
                                </div>
                            </div>

                            <div class="space-y-3 pt-1">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="font-bold text-slate-800">Số tiền hoàn vào ví khách hàng (VNĐ)</label>
                                        <div class="flex gap-1.5">
                                            <button type="button" @click="cancelRefundForm.customer_refund_amount = 0" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 font-bold text-slate-700 cursor-pointer">0đ</button>
                                            <button type="button" @click="cancelRefundForm.customer_refund_amount = refundPreview.refund_amount" class="px-2 py-0.5 rounded bg-blue-50 hover:bg-blue-100 font-bold text-[#1D6AE5] cursor-pointer">Bằng tiền gốc</button>
                                            <button type="button" @click="cancelRefundForm.customer_refund_amount = refundPreview.customer_paid_amount" class="px-2 py-0.5 rounded bg-emerald-50 hover:bg-emerald-100 font-bold text-emerald-700 cursor-pointer">100% đơn khách</button>
                                        </div>
                                    </div>
                                    <input v-model.number="cancelRefundForm.customer_refund_amount" type="number" min="0" step="1000" class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono-num font-bold text-sm" />
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Ghi chú hoàn tiền (nếu cộng ví khách &gt; 0đ)</label>
                                    <input v-model="cancelRefundForm.refund_note" type="text" placeholder="VD: Hỗ trợ hủy VPS theo yêu cầu khách" class="w-full px-3 py-2 rounded-xl border border-slate-300" />
                                </div>
                            </div>
                        </template>

                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="!cancelRefundForm.processing && (showRefundModal = false)" class="px-4 py-2 rounded-xl border border-slate-300 font-bold cursor-pointer">Đóng</button>
                            <button
                                v-if="refundPreview"
                                type="button"
                                :disabled="cancelRefundForm.processing"
                                @click="confirmCancelRefund"
                                class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white font-bold cursor-pointer inline-flex items-center gap-1.5"
                            >
                                <Loader2 v-if="cancelRefundForm.processing" class="w-3.5 h-3.5 animate-spin" />
                                <span>Hủy VPS &amp; Nhận hoàn tiền</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
