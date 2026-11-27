<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AdminUserPicker from '@/Components/Admin/AdminUserPicker.vue';
import { formatVnd } from '@/Composables/useFormat';
import { askConfirm } from '@/Composables/useConfirmAction';
import {
    ArrowLeft,
    Cpu,
    RefreshCw,
    LifeBuoy,
    UserCheck,
    Save,
    Eye,
    EyeOff,
    Loader2,
    X,
    CalendarClock,
} from 'lucide-vue-next';

const props = defineProps<{
    server: any;
    renewCycles: Array<{ key: string; label: string; months: number; price: number; provider_cost: number }>;
    transactions: Array<any>;
}>();

const syncing = ref(false);
const togglingAuto = ref(false);
const showRootPass = ref(false);
const showIpmiPass = ref(false);
const showReassignModal = ref(false);
const showRenewModal = ref(false);

const renewForm = useForm({
    billing_cycle: 'monthly',
    charge_owner: Boolean(props.server.user),
    admin_note: '',
});
const selectedRenewCycle = computed(
    () => props.renewCycles.find((c) => c.key === renewForm.billing_cycle) || props.renewCycles[0] || null
);

function openRenewModal() {
    renewForm.clearErrors();
    renewForm.billing_cycle = props.renewCycles[0]?.key || 'monthly';
    renewForm.charge_owner = Boolean(props.server.user);
    renewForm.admin_note = '';
    showRenewModal.value = true;
}

function submitRenew() {
    const q = selectedRenewCycle.value;
    const modeText = renewForm.charge_owner
        ? `Trừ ví khách ${props.server.user?.email}: ${formatVnd(q?.price || 0)}`
        : `KHÔNG trừ ví khách (0đ — chỉ trừ vốn gốc ~${formatVnd(q?.provider_cost || 0)} trên CloudSunny)`;
    askConfirm({
        title: `Gia hạn hộ Máy chủ ${props.server.label}`,
        message: `Chu kỳ: ${q?.label || renewForm.billing_cycle}\n• ${modeText}`,
        confirmText: 'Gia hạn ngay',
        variant: 'primary',
        onConfirm: () => {
            renewForm.post(`/admin/servers/${props.server.id}/renew`, {
                preserveScroll: true,
                onSuccess: () => (showRenewModal.value = false),
            });
        },
    });
}

function toggleAutoRenew() {
    togglingAuto.value = true;
    router.post(`/admin/servers/${props.server.id}/auto-renew`, {}, {
        preserveScroll: true,
        onFinish: () => (togglingAuto.value = false),
    });
}

const handoverForm = useForm({
    public_ip: props.server.public_ip || '',
    os_installed: props.server.os_installed || '',
    login_username: props.server.login_username || 'root',
    root_password: props.server.root_password || '',
    ipmi_url: props.server.ipmi_url || '',
    ipmi_username: props.server.ipmi_username || '',
    ipmi_password: props.server.ipmi_password || '',
    handover_note: props.server.handover_note || '',
    status: props.server.raw_status || 'Hoạt động',
});

const reassignForm = useForm({
    user_id: '' as number | '',
    disable_auto_renew: true,
});

function runSync() {
    syncing.value = true;
    router.post(`/admin/servers/${props.server.id}/sync`, {}, {
        preserveScroll: true,
        onFinish: () => (syncing.value = false),
    });
}

function submitHandover() {
    handoverForm.post(`/admin/servers/${props.server.id}/handover`, {
        preserveScroll: true,
    });
}

function submitReassign() {
    if (!reassignForm.user_id) return;
    reassignForm.post(`/admin/servers/${props.server.id}/reassign`, {
        preserveScroll: true,
        onSuccess: () => {
            showReassignModal.value = false;
            reassignForm.user_id = '';
        },
    });
}
</script>

<template>
    <AdminLayout :title="`Admin · Máy chủ vật lý ${server.label}`">
        <Head :title="`Admin · ${server.label}`" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link href="/admin/servers" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-[#0B1437]">
                    <ArrowLeft class="w-4 h-4" />
                    <span>Quay lại Danh sách Máy chủ vật lý</span>
                </Link>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 font-mono font-bold text-slate-700">
                        Local #{{ server.id }} · SRV#{{ server.provider_server_id || '—' }}
                    </span>
                    <span class="px-2.5 py-1 rounded-lg bg-blue-50 border border-blue-200 font-bold text-[#1D6AE5]">
                        Nguồn: {{ server.account_name }}
                    </span>
                </div>
            </div>

            <!-- Hero Summary -->
            <div class="surface-card p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-5">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-[#0B1437] shrink-0">
                            <Cpu class="w-6 h-6" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-black text-[#0B1437]">{{ server.label }}</h1>
                                <StatusBadge :state="server.state" :label="server.status" />
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-xs font-bold">{{ server.plan_name }}</span>
                            </div>
                            <div class="text-xs text-slate-600 font-medium mt-1">
                                {{ server.config.cpu_model }} ({{ server.config.cpu_cores }}C/{{ server.config.cpu_threads }}T) · {{ server.config.ram_gb }}GB {{ server.config.ram_type }} · {{ server.config.storage }} · {{ server.config.port_speed }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <button
                            v-if="server.can_renew && renewCycles.length > 0"
                            type="button"
                            @click="openRenewModal"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer transition"
                        >
                            <CalendarClock class="w-3.5 h-3.5" />
                            <span>Gia hạn hộ</span>
                        </button>
                        <button
                            v-if="server.is_linked"
                            type="button"
                            :disabled="syncing"
                            @click="runSync"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 font-bold text-slate-700 cursor-pointer"
                        >
                            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': syncing }" />
                            <span>Đồng bộ API</span>
                        </button>
                        <button
                            type="button"
                            @click="showReassignModal = true"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 font-bold text-slate-700 cursor-pointer"
                        >
                            <UserCheck class="w-3.5 h-3.5" />
                            <span>Chuyển chủ sở hữu</span>
                        </button>
                        <Link
                            v-if="server.provider_server_id"
                            :href="`/admin/tickets?service_type=server&service_id=${server.provider_server_id}`"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white font-bold transition"
                        >
                            <LifeBuoy class="w-3.5 h-3.5" />
                            <span>Mở Ticket hạ tầng</span>
                        </Link>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 pt-4 border-t border-slate-200/80 text-xs">
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Chủ sở hữu</div>
                        <Link v-if="server.user" :href="`/admin/users/${server.user.id}`" class="block mt-1 group">
                            <div class="font-extrabold text-[#0B1437] group-hover:text-[#1D6AE5] truncate">{{ server.user.name }}</div>
                            <div class="font-mono text-[11px] text-slate-600 group-hover:text-[#1D6AE5] truncate">{{ server.user.email }}</div>
                        </Link>
                        <div v-else class="mt-1 text-slate-400 italic">Đã xóa</div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Doanh thu / Vốn / Lãi</div>
                        <div class="font-mono-num font-extrabold text-sm text-[#0B1437] mt-1">{{ formatVnd(server.paid_amount) }}</div>
                        <div class="font-mono-num text-[11px] mt-0.5 flex items-center gap-1.5">
                            <span class="text-slate-600">Vốn: {{ formatVnd(server.provider_cost) }}</span>
                            <span :class="server.gross_profit >= 0 ? 'text-emerald-700 font-bold' : 'text-rose-600 font-bold'">
                                ({{ server.gross_profit >= 0 ? '+' : '' }}{{ formatVnd(server.gross_profit) }})
                            </span>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Hết hạn &amp; Chu kỳ</div>
                        <div class="font-mono-num font-extrabold text-sm text-[#0B1437] mt-1">{{ server.expires_at || 'Chờ bàn giao' }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5 flex items-center justify-between gap-2">
                            <span>{{ server.billing_cycle_label }} · Auto: <strong>{{ server.is_auto_renew ? 'Bật' : 'Tắt' }}</strong></span>
                            <button
                                v-if="server.state !== 'deleted'"
                                type="button"
                                :disabled="togglingAuto"
                                @click="toggleAutoRenew"
                                class="px-2 py-0.5 rounded border text-[10.5px] font-bold cursor-pointer transition"
                                :class="server.is_auto_renew ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-100'"
                            >
                                {{ server.is_auto_renew ? 'Tắt' : 'Bật' }}
                            </button>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="text-slate-500 font-bold uppercase text-[10.5px]">Đặt lúc / Bàn giao</div>
                        <div class="font-mono-num font-extrabold text-sm text-[#0B1437] mt-1">{{ server.created_at }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">Bàn giao: {{ server.handed_over_at || 'Chưa bàn giao' }}</div>
                    </div>
                </div>
            </div>

            <!-- Handover Credentials Form (Admin fills when CloudSunny delivers IP & IPMI manually) -->
            <form @submit.prevent="submitHandover" class="surface-card p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-5 text-xs">
                <div>
                    <h2 class="text-sm font-extrabold text-[#0B1437]">Bàn giao Thông tin truy cập &amp; IPMI cho khách hàng</h2>
                    <p class="text-slate-500 mt-0.5">
                        CloudSunny bàn giao máy chủ vật lý thủ công qua kênh hỗ trợ. Nhập IP, tài khoản OS và cổng IPMI nhận được vào đây — mật khẩu được mã hóa AES-256 trong DB và hiển thị ngay trong trang quản lý của khách.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Địa chỉ IPv4 chính</label>
                        <input v-model="handoverForm.public_ip" type="text" placeholder="VD: 103.x.x.x" class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono font-bold" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Hệ điều hành đã cài</label>
                        <input v-model="handoverForm.os_installed" type="text" placeholder="VD: Proxmox VE 8 / Ubuntu 22.04" class="w-full px-3 py-2 rounded-xl border border-slate-300" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Trạng thái bàn giao</label>
                        <select v-model="handoverForm.status" class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-white font-bold">
                            <option value="Chờ bàn giao">Chờ bàn giao</option>
                            <option value="Hoạt động">Hoạt động (Đã bàn giao)</option>
                            <option value="Hết hạn">Hết hạn</option>
                            <option value="Đã xóa">Đã xóa / Hủy</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tài khoản đăng nhập OS</label>
                        <input v-model="handoverForm.login_username" type="text" placeholder="root / Administrator" class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Mật khẩu OS (mã hóa DB)</label>
                        <div class="relative">
                            <input
                                v-model="handoverForm.root_password"
                                :type="showRootPass ? 'text' : 'password'"
                                placeholder="Nhập mật khẩu bàn giao..."
                                class="w-full pl-3 pr-9 py-2 rounded-xl border border-slate-300 font-mono"
                            />
                            <button type="button" @click="showRootPass = !showRootPass" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 cursor-pointer">
                                <EyeOff v-if="showRootPass" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-3 border-t border-slate-100">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">IPMI / iDRAC URL</label>
                        <input v-model="handoverForm.ipmi_url" type="text" placeholder="https://ipmi.example.com hoặc IP" class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">IPMI Username</label>
                        <input v-model="handoverForm.ipmi_username" type="text" placeholder="ADMIN" class="w-full px-3 py-2 rounded-xl border border-slate-300 font-mono" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">IPMI Password (mã hóa DB)</label>
                        <div class="relative">
                            <input
                                v-model="handoverForm.ipmi_password"
                                :type="showIpmiPass ? 'text' : 'password'"
                                placeholder="Mật khẩu IPMI..."
                                class="w-full pl-3 pr-9 py-2 rounded-xl border border-slate-300 font-mono"
                            />
                            <button type="button" @click="showIpmiPass = !showIpmiPass" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 cursor-pointer">
                                <EyeOff v-if="showIpmiPass" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Ghi chú bàn giao (hiển thị cho khách hàng)</label>
                    <textarea
                        v-model="handoverForm.handover_note"
                        rows="3"
                        placeholder="VD: Dải IP phụ /29, cấu hình VLAN, thông tin gateway / netmask..."
                        class="w-full px-3 py-2 rounded-xl border border-slate-300"
                    />
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="handoverForm.processing"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer transition disabled:opacity-60"
                    >
                        <Loader2 v-if="handoverForm.processing" class="w-4 h-4 animate-spin" />
                        <Save v-else class="w-4 h-4" />
                        <span>Lưu &amp; Bàn giao cho khách</span>
                    </button>
                </div>
            </form>

            <!-- Transactions Table -->
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 text-xs font-extrabold uppercase tracking-wider text-slate-700">
                    Lịch sử giao dịch của máy chủ này ({{ transactions.length }})
                </div>
                <div v-if="transactions.length === 0" class="p-6 text-center text-xs text-slate-500">Chưa có giao dịch nào.</div>
                <template v-else>
                    <!-- Mobile stacked card view -->
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
                                    <td class="py-3 px-4 text-slate-700">{{ t.description }}</td>
                                    <td class="py-3 px-4 text-right font-mono-num font-bold whitespace-nowrap" :class="t.status === 'refunded' ? 'text-slate-400 line-through' : 'text-[#0B1437]'">{{ formatVnd(t.amount) }}</td>
                                    <td class="py-3 px-4 text-right font-mono-num text-slate-500 whitespace-nowrap">Vốn: {{ formatVnd(t.provider_cost) }}</td>
                                    <td class="py-3 px-4 text-right font-mono-num text-slate-500 whitespace-nowrap">{{ t.created_at }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>

        <!-- Renew Modal -->
        <div v-if="showRenewModal" class="fixed inset-0 z-50 bg-slate-950/50 flex items-center justify-center p-4" @click.self="!renewForm.processing && (showRenewModal = false)">
            <form @submit.prevent="submitRenew" class="w-full max-w-lg rounded-2xl bg-white border border-slate-200 shadow-xl p-6 space-y-4 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div>
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Gia hạn hộ Máy chủ — {{ server.label }}</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Hạn hiện tại: {{ server.expires_at || '—' }} · Ví chủ sở hữu: {{ formatVnd(server.user?.balance || 0) }}</p>
                    </div>
                    <button type="button" @click="!renewForm.processing && (showRenewModal = false)" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Chọn chu kỳ gia hạn (không tính phí cài đặt)</label>
                    <select v-model="renewForm.billing_cycle" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white font-bold">
                        <option v-for="c in renewCycles" :key="c.key" :value="c.key">
                            {{ c.label }} — Giá khách: {{ formatVnd(c.price) }} (Vốn gốc: {{ formatVnd(c.provider_cost) }})
                        </option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button
                        type="button"
                        :disabled="!server.user"
                        @click="renewForm.charge_owner = true"
                        class="p-3 rounded-xl border text-left cursor-pointer transition disabled:opacity-50"
                        :class="renewForm.charge_owner ? 'border-[#1D6AE5] bg-blue-50/70 ring-1 ring-[#1D6AE5]' : 'border-slate-200 hover:bg-slate-50'"
                    >
                        <div class="font-extrabold text-[#0B1437]">Trừ ví chủ sở hữu</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">Trừ {{ formatVnd(selectedRenewCycle?.price || 0) }} &amp; tính doanh thu</div>
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
                    <button type="button" @click="!renewForm.processing && (showRenewModal = false)" class="px-4 py-2 rounded-xl border border-slate-200 font-bold text-slate-600 cursor-pointer">Hủy</button>
                    <button type="submit" :disabled="renewForm.processing || !selectedRenewCycle" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer disabled:opacity-50">
                        <Loader2 v-if="renewForm.processing" class="w-3.5 h-3.5 animate-spin" />
                        <span>Gia hạn Máy chủ</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Reassign Modal -->
        <div v-if="showReassignModal" class="fixed inset-0 z-50 bg-slate-950/50 flex items-center justify-center p-4" @click.self="showReassignModal = false">
            <div class="w-full max-w-md rounded-2xl bg-white border border-slate-200 shadow-xl p-6 space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-extrabold text-[#0B1437]">Chuyển chủ sở hữu Máy chủ vật lý</h3>
                    <button type="button" @click="showReassignModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                </div>
                <AdminUserPicker v-model="reassignForm.user_id" />
                <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                    <input v-model="reassignForm.disable_auto_renew" type="checkbox" class="rounded border-slate-300" />
                    <span>Tắt tự động gia hạn sau khi chuyển</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showReassignModal = false" class="px-4 py-2 rounded-xl border border-slate-200 font-bold text-slate-600 cursor-pointer">Hủy</button>
                    <button
                        type="button"
                        :disabled="!reassignForm.user_id || reassignForm.processing"
                        @click="submitReassign"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer disabled:opacity-50"
                    >
                        <Loader2 v-if="reassignForm.processing" class="w-3.5 h-3.5 animate-spin" />
                        <span>Xác nhận chuyển</span>
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
