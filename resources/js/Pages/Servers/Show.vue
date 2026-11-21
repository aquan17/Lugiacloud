<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { copyToClipboard, formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import {
    ArrowLeft,
    Cpu,
    HardDrive,
    Network,
    ShieldCheck,
    RefreshCw,
    Copy,
    Check,
    Eye,
    EyeOff,
    Calendar,
    Clock,
    ExternalLink,
    Edit3,
    X,
    Loader2,
} from 'lucide-vue-next';

const props = defineProps<{
    server: any;
    renewCycles: Array<{ key: string; label: string; months: number; price: number; list_price: number; save_percent: number }>;
    balance: number;
}>();

const toast = useToast();
const showRootPass = ref(false);
const showIpmiPass = ref(false);
const copiedKey = ref<string | null>(null);
const syncing = ref(false);

const showRenewModal = ref(false);
const renewForm = useForm({
    billing_cycle: props.renewCycles[0]?.key || 'monthly',
});

const selectedRenewCycle = computed(() => props.renewCycles.find((c) => c.key === renewForm.billing_cycle) || props.renewCycles[0]);

const editingLabel = ref(false);
const labelForm = useForm({
    label: props.server.label || '',
});

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
    syncing.value = true;
    router.post(`/servers/${props.server.id}/sync`, {}, {
        preserveScroll: true,
        onFinish: () => (syncing.value = false),
    });
}

function submitRenew() {
    renewForm.post(`/servers/${props.server.id}/renew`, {
        preserveScroll: true,
        onSuccess: () => (showRenewModal.value = false),
    });
}

function submitLabel() {
    labelForm.patch(`/servers/${props.server.id}/label`, {
        preserveScroll: true,
        onSuccess: () => (editingLabel.value = false),
    });
}
</script>

<template>
    <ClientLayout :title="`Máy chủ vật lý · ${server.label}`">
        <Head :title="server.label" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link href="/servers" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-[#0B1437]">
                    <ArrowLeft class="w-4 h-4" />
                    <span>Quay lại Danh sách Máy chủ vật lý</span>
                </Link>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="syncing"
                        @click="runSync"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer transition"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': syncing }" />
                        <span>Đồng bộ trạng thái</span>
                    </button>
                    <button
                        v-if="server.can_renew && renewCycles.length > 0"
                        type="button"
                        @click="showRenewModal = true"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer transition"
                    >
                        <Calendar class="w-3.5 h-3.5" />
                        <span>Gia hạn máy chủ</span>
                    </button>
                </div>
            </div>

            <!-- Hero Card -->
            <div class="surface-card p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-[#0B1437] shrink-0">
                            <Cpu class="w-6 h-6" />
                        </div>
                        <div>
                            <div v-if="!editingLabel" class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-black text-[#0B1437]">{{ server.label }}</h1>
                                <button type="button" @click="editingLabel = true" class="p-1 rounded text-slate-400 hover:text-[#1D6AE5] cursor-pointer" title="Đổi tên gợi nhớ">
                                    <Edit3 class="w-3.5 h-3.5" />
                                </button>
                                <StatusBadge :state="server.state" :label="server.status" />
                            </div>
                            <form v-else @submit.prevent="submitLabel" class="flex items-center gap-2">
                                <input v-model="labelForm.label" type="text" required class="px-2.5 py-1 rounded-lg border border-slate-300 text-sm font-bold" />
                                <button type="submit" :disabled="labelForm.processing" class="px-2.5 py-1 rounded-lg bg-[#0B1437] text-white text-xs font-bold cursor-pointer">Lưu</button>
                                <button type="button" @click="editingLabel = false" class="p-1 text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                            </form>
                            <div class="text-xs text-slate-600 font-medium mt-1">
                                {{ server.plan_name }} · Datacenter {{ server.config.datacenter || 'HCM / HN' }} · Chu kỳ {{ server.billing_cycle_label }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 text-xs">
                        <div>
                            <div class="text-[10.5px] font-bold uppercase text-slate-500">Ngày hết hạn</div>
                            <div class="font-mono-num font-extrabold text-[#0B1437] mt-0.5">{{ server.expires_at || 'Tính khi bàn giao' }}</div>
                        </div>
                        <button
                            type="button"
                            @click="router.post(`/servers/${server.id}/auto-renew`, {}, { preserveScroll: true })"
                            class="px-3 py-1.5 rounded-xl border text-xs font-bold cursor-pointer transition"
                            :class="server.is_auto_renew ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-slate-50 border-slate-200 text-slate-600'"
                        >
                            Tự động gia hạn: {{ server.is_auto_renew ? 'Bật' : 'Tắt' }}
                        </button>
                    </div>
                </div>

                <!-- Specs Strip -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-4 border-t border-slate-200/80 text-xs">
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div class="text-[10.5px] font-bold uppercase text-slate-500 flex items-center gap-1"><Cpu class="w-3.5 h-3.5 text-[#1D6AE5]" />CPU</div>
                        <div class="font-bold text-[#0B1437] mt-1">{{ server.config.cpu_model }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">{{ server.config.cpu_cores }} Cores / {{ server.config.cpu_threads }} Threads</div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div class="text-[10.5px] font-bold uppercase text-slate-500 flex items-center gap-1"><HardDrive class="w-3.5 h-3.5 text-indigo-600" />RAM &amp; SSD</div>
                        <div class="font-bold text-[#0B1437] mt-1">{{ server.config.ram_gb }} GB {{ server.config.ram_type }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">{{ server.config.storage }} ({{ server.config.raid || 'Non-RAID' }})</div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div class="text-[10.5px] font-bold uppercase text-slate-500 flex items-center gap-1"><Network class="w-3.5 h-3.5 text-emerald-600" />Băng thông</div>
                        <div class="font-bold text-[#0B1437] mt-1">{{ server.config.port_speed }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">{{ server.config.traffic }} · {{ server.config.ip_count || 1 }} IPv4</div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div class="text-[10.5px] font-bold uppercase text-slate-500 flex items-center gap-1"><ShieldCheck class="w-3.5 h-3.5 text-amber-600" />Quản trị phần cứng</div>
                        <div class="font-bold text-[#0B1437] mt-1">{{ server.config.ipmi ? 'Có IPMI / iDRAC' : 'Hỗ trợ kỹ thuật 24/7' }}</div>
                        <div class="text-[11px] text-slate-600 mt-0.5">OS: {{ server.os_installed || 'Theo yêu cầu' }}</div>
                    </div>
                </div>
            </div>

            <!-- Handover Credentials & IPMI -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs">
                <div class="surface-card p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
                    <h2 class="text-sm font-extrabold text-[#0B1437]">Thông tin truy cập Hệ điều hành</h2>

                    <div v-if="!server.has_credentials" class="p-4 rounded-xl bg-amber-50/70 border border-amber-200 text-amber-900 flex items-start gap-2.5 leading-relaxed">
                        <Clock class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                        <div>
                            <strong>Đang trong quá trình lắp đặt &amp; bàn giao phần cứng.</strong><br />
                            Kỹ thuật Datacenter đang triển khai máy chủ vật lý của bạn. Ngay khi hoàn tất, địa chỉ IP, tài khoản quản trị và cổng IPMI sẽ tự động hiển thị tại đây.
                        </div>
                    </div>

                    <div v-else class="space-y-3">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div>
                                <div class="text-[10.5px] font-bold uppercase text-slate-500">Địa chỉ IPv4</div>
                                <div class="font-mono font-black text-sm text-[#0B1437] mt-0.5">{{ server.public_ip || '—' }}</div>
                            </div>
                            <button v-if="server.public_ip" type="button" @click="copyField(server.public_ip, 'IPv4', 'ip')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500 cursor-pointer">
                                <Check v-if="copiedKey === 'ip'" class="w-4 h-4 text-emerald-600" />
                                <Copy v-else class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div>
                                <div class="text-[10.5px] font-bold uppercase text-slate-500">Tài khoản đăng nhập</div>
                                <div class="font-mono font-bold text-[#0B1437] mt-0.5">{{ server.login_username || 'root / Administrator' }}</div>
                            </div>
                            <button v-if="server.login_username" type="button" @click="copyField(server.login_username, 'Tài khoản', 'user')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500 cursor-pointer">
                                <Check v-if="copiedKey === 'user'" class="w-4 h-4 text-emerald-600" />
                                <Copy v-else class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div class="min-w-0">
                                <div class="text-[10.5px] font-bold uppercase text-slate-500">Mật khẩu quản trị</div>
                                <div class="font-mono font-bold text-[#0B1437] mt-0.5 break-all">
                                    {{ server.root_password ? (showRootPass ? server.root_password : '••••••••••••') : '—' }}
                                </div>
                            </div>
                            <div v-if="server.root_password" class="flex items-center gap-1 shrink-0">
                                <button type="button" @click="showRootPass = !showRootPass" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500 cursor-pointer">
                                    <EyeOff v-if="showRootPass" class="w-4 h-4" />
                                    <Eye v-else class="w-4 h-4" />
                                </button>
                                <button type="button" @click="copyField(server.root_password, 'Mật khẩu', 'pass')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500 cursor-pointer">
                                    <Check v-if="copiedKey === 'pass'" class="w-4 h-4 text-emerald-600" />
                                    <Copy v-else class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IPMI / iDRAC Box -->
                <div class="surface-card p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
                    <h2 class="text-sm font-extrabold text-[#0B1437]">Cổng quản trị từ xa IPMI / iDRAC</h2>

                    <div v-if="!server.ipmi_url && !server.ipmi_username" class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 leading-relaxed">
                        Thông tin đăng nhập IPMI / KVM từ xa sẽ được cập nhật tại đây ngay khi hoàn tất bàn giao. Nếu bạn cần hỗ trợ mount ISO hoặc reset phần cứng, vui lòng liên hệ Zalo hỗ trợ.
                    </div>

                    <div v-else class="space-y-3">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80 gap-2">
                            <div class="min-w-0">
                                <div class="text-[10.5px] font-bold uppercase text-slate-500">URL / IP IPMI</div>
                                <div class="font-mono font-bold text-[#1D6AE5] mt-0.5 truncate">{{ server.ipmi_url || '—' }}</div>
                            </div>
                            <div v-if="server.ipmi_url" class="flex items-center gap-1 shrink-0">
                                <a :href="server.ipmi_url.startsWith('http') ? server.ipmi_url : `https://${server.ipmi_url}`" target="_blank" rel="noopener" class="p-1.5 rounded-lg hover:bg-slate-200 text-[#1D6AE5]">
                                    <ExternalLink class="w-4 h-4" />
                                </a>
                                <button type="button" @click="copyField(server.ipmi_url, 'URL IPMI', 'ipmi_url')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500 cursor-pointer">
                                    <Check v-if="copiedKey === 'ipmi_url'" class="w-4 h-4 text-emerald-600" />
                                    <Copy v-else class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div>
                                    <div class="text-[10.5px] font-bold uppercase text-slate-500">IPMI User</div>
                                    <div class="font-mono font-bold text-[#0B1437] mt-0.5">{{ server.ipmi_username || '—' }}</div>
                                </div>
                                <button v-if="server.ipmi_username" type="button" @click="copyField(server.ipmi_username, 'IPMI User', 'ipmi_u')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500 cursor-pointer">
                                    <Check v-if="copiedKey === 'ipmi_u'" class="w-4 h-4 text-emerald-600" />
                                    <Copy v-else class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="min-w-0">
                                    <div class="text-[10.5px] font-bold uppercase text-slate-500">IPMI Password</div>
                                    <div class="font-mono font-bold text-[#0B1437] mt-0.5 truncate">
                                        {{ server.ipmi_password ? (showIpmiPass ? server.ipmi_password : '••••••••') : '—' }}
                                    </div>
                                </div>
                                <div v-if="server.ipmi_password" class="flex items-center gap-1 shrink-0">
                                    <button type="button" @click="showIpmiPass = !showIpmiPass" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500 cursor-pointer">
                                        <EyeOff v-if="showIpmiPass" class="w-3.5 h-3.5" />
                                        <Eye v-else class="w-3.5 h-3.5" />
                                    </button>
                                    <button type="button" @click="copyField(server.ipmi_password, 'IPMI Password', 'ipmi_p')" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500 cursor-pointer">
                                        <Check v-if="copiedKey === 'ipmi_p'" class="w-3.5 h-3.5 text-emerald-600" />
                                        <Copy v-else class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="server.handover_note" class="p-3.5 rounded-xl bg-blue-50/60 border border-blue-200 text-slate-700 whitespace-pre-line">
                        <div class="text-[10.5px] font-bold uppercase text-[#1D6AE5] mb-1">Ghi chú bàn giao từ kỹ thuật</div>
                        {{ server.handover_note }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Renew Modal -->
        <div v-if="showRenewModal" class="fixed inset-0 z-50 bg-slate-950/50 flex items-center justify-center p-4" @click.self="showRenewModal = false">
            <div class="w-full max-w-md rounded-2xl bg-white border border-slate-200 shadow-xl p-6 space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-[#0B1437]">Gia hạn Máy chủ vật lý</h3>
                    <button type="button" @click="showRenewModal = false" class="p-1 rounded text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="c in renewCycles"
                        :key="c.key"
                        type="button"
                        @click="renewForm.billing_cycle = c.key"
                        class="p-3 rounded-xl border text-left transition cursor-pointer"
                        :class="renewForm.billing_cycle === c.key ? 'border-[#1D6AE5] bg-blue-50/50 ring-1 ring-[#1D6AE5]' : 'border-slate-200 hover:border-slate-300'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-[#0B1437]">{{ c.label }}</span>
                            <span v-if="c.save_percent > 0" class="text-[10px] font-black text-emerald-700">-{{ c.save_percent }}%</span>
                        </div>
                        <div class="font-mono-num font-black text-[#1D6AE5] mt-1">{{ formatVnd(c.price) }}</div>
                    </button>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                    <span class="text-slate-600 font-semibold">Số dư ví hiện tại</span>
                    <span class="font-mono-num font-extrabold" :class="balance >= (selectedRenewCycle?.price || 0) ? 'text-emerald-700' : 'text-rose-600'">
                        {{ formatVnd(balance) }}
                    </span>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showRenewModal = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold cursor-pointer">Hủy</button>
                    <button
                        type="button"
                        :disabled="renewForm.processing || balance < (selectedRenewCycle?.price || 0)"
                        @click="submitRenew"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer disabled:opacity-50"
                    >
                        <Loader2 v-if="renewForm.processing" class="w-3.5 h-3.5 animate-spin" />
                        <span>Thanh toán gia hạn ({{ formatVnd(selectedRenewCycle?.price || 0) }})</span>
                    </button>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
