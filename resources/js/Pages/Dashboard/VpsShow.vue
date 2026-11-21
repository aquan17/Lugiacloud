<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import OsIcon from '@/Components/OsIcon.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import RebuildOsModal from '@/Components/Vps/RebuildOsModal.vue';
import ChangeOsPasswordModal from '@/Components/Vps/ChangeOsPasswordModal.vue';
import ChangeIpModal from '@/Components/Vps/ChangeIpModal.vue';
import ExtendDiskModal from '@/Components/Vps/ExtendDiskModal.vue';
import { copyToClipboard, formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { askConfirm } from '@/Composables/useConfirmAction';
import { downloadRdpFile, formatFullVpsCredential } from '@/Composables/useVpsConnect';
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
    Trash2,
    Minus,
    Plus,
    Loader2,
    Pencil,
    Download,
    Globe,
    Cpu,
    HardDrive,
    Calendar,
    KeyRound,
    ShieldAlert,
    Sparkles,
    Lock,
    CheckCircle2,
    AlertCircle,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    vps: any;
    images: Array<{ id: number; label: string; distro: string; group: string }>;
    renewQuotes: Record<string, any>;
    addonRates: { cpu_monthly: number; ram_monthly: number; disk_10gb_monthly: number };
    changeIpConfig?: { enabled: boolean; price: number; list_price: number; limit_per_month: number };
}>();

const toast = useToast();
const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const isVpsOutOfStock = computed(() => Boolean((page.props.system as any)?.vps_out_of_stock));

const liveVps = ref({ ...props.vps });
watch(
    () => props.vps,
    (next) => {
        liveVps.value = { ...next };
    },
    { deep: true }
);

const showPass = ref(false);
const showPasswordModal = ref(false);
const showRebuildModal = ref(false);
const showChangeIpModal = ref(false);
const showExtendDiskModal = ref(false);
const editingLabel = ref(false);
const copiedTag = ref<string | null>(null);
const busyPowerAction = ref<string | null>(null);

const labelForm = useForm({
    label: props.vps.label || '',
});

function saveLabel() {
    if (!labelForm.label.trim()) return;
    labelForm.patch(`/vps/${liveVps.value.id}/label`, {
        preserveScroll: true,
        onSuccess: () => {
            liveVps.value.label = labelForm.label.trim();
            editingLabel.value = false;
        },
    });
}

// Auto-poll ngầm khi máy chủ đang cài đặt / đổi IP / mở rộng ổ cứng / khởi động lại (không chặn mở trang)
let pollTimer: ReturnType<typeof setInterval> | null = null;
let initialPollTimer: ReturnType<typeof setTimeout> | null = null;
let isPolling = false;

function ensurePollingActive() {
    if (!pollTimer) {
        pollTimer = setInterval(pollLiveStatus, 8000);
    }
}

async function pollLiveStatus() {
    if (isPolling || (typeof document !== 'undefined' && document.hidden)) return;
    isPolling = true;
    const prevIp = liveVps.value.public_ip;
    const prevState = liveVps.value.state;
    try {
        const { data } = await axios.get(`/vps/${liveVps.value.id}/status`);
        liveVps.value.status = data.status;
        liveVps.value.state = data.state;
        if (data.public_ip) liveVps.value.public_ip = data.public_ip;
        if (data.login_username) liveVps.value.login_username = data.login_username;
        if (data.root_password) liveVps.value.root_password = data.root_password;

        if (prevIp && data.public_ip && data.public_ip !== prevIp) {
            toast.success(`Đã cập nhật IPv4 mới: ${data.public_ip}`);
        } else if (prevState === 'provisioning' && data.state === 'running') {
            toast.success(`Máy chủ ${liveVps.value.label} đã hoàn tất xử lý và sẵn sàng hoạt động!`);
        }

        if (!data.is_provisioning && data.state !== 'rebooting' && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    } catch {
    } finally {
        isPolling = false;
    }
}

watch(
    () => liveVps.value.state,
    (nextState) => {
        if (nextState === 'provisioning' || nextState === 'rebooting') {
            ensurePollingActive();
        }
    }
);

onMounted(() => {
    if (liveVps.value.state === 'provisioning' || liveVps.value.state === 'rebooting') {
        initialPollTimer = setTimeout(pollLiveStatus, 300);
        ensurePollingActive();
    }
});

onUnmounted(() => {
    if (initialPollTimer) clearTimeout(initialPollTimer);
    if (pollTimer) clearInterval(pollTimer);
});

const isDeleted = computed(() => Boolean(liveVps.value.is_deleted || liveVps.value.state === 'deleted'));
const isWindows = computed(() => liveVps.value.os_distro === 'windows');
const defaultPort = computed(() => (isWindows.value ? '3389 (RDP)' : '22 (SSH)'));

const connectCommand = computed(() => {
    if (!liveVps.value.public_ip) return '';
    return isWindows.value
        ? `mstsc /v:${liveVps.value.public_ip}`
        : `ssh ${liveVps.value.login_username || 'root'}@${liveVps.value.public_ip}`;
});

const expiryBadge = computed(() => {
    if (isDeleted.value) {
        return { text: 'Đã hủy & thu hồi', cls: 'bg-rose-50 text-rose-700 border-rose-200' };
    }
    const d = liveVps.value.days_until_expiry;
    if (liveVps.value.is_expired || (d !== null && d < 0)) {
        return { text: 'Đã quá hạn', cls: 'bg-rose-50 text-rose-700 border-rose-200' };
    }
    if (d !== null && d <= 5) {
        return { text: `Sắp hết hạn (${d} ngày)`, cls: 'bg-amber-50 text-amber-800 border-amber-200' };
    }
    if (d !== null) {
        return { text: `Còn ${d} ngày`, cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    }
    return null;
});

async function copyField(val: string, label: string, tag?: string) {
    if (!val) return;
    if (await copyToClipboard(val)) {
        toast.success(`Đã sao chép ${label}`);
        if (tag) {
            copiedTag.value = tag;
            setTimeout(() => {
                if (copiedTag.value === tag) copiedTag.value = null;
            }, 1800);
        }
    }
}

function executePowerAction(action: 'boot' | 'shutdown' | 'reboot' | 'sync') {
    busyPowerAction.value = action;
    router.post(
        `/vps/${liveVps.value.id}/${action}`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busyPowerAction.value = null;
            },
        }
    );
}

function powerAction(action: 'boot' | 'shutdown' | 'reboot' | 'sync') {
    if (isDeleted.value || busyPowerAction.value) return;
    if (action === 'shutdown') {
        askConfirm({
            title: 'Tắt nguồn máy chủ',
            message: `Bạn có chắc muốn tắt nguồn máy chủ ${liveVps.value.label}? Mọi tiến trình đang chạy sẽ dừng lại.`,
            confirmText: 'Tắt nguồn',
            variant: 'danger',
            onConfirm: () => executePowerAction('shutdown'),
        });
        return;
    }
    if (action === 'reboot') {
        askConfirm({
            title: 'Khởi động lại máy chủ',
            message: `Xác nhận khởi động lại máy chủ ${liveVps.value.label}? Kết nối sẽ tạm gián đoạn trong 15–30 giây.`,
            confirmText: 'Khởi động lại',
            variant: 'primary',
            onConfirm: () => executePowerAction('reboot'),
        });
        return;
    }

    executePowerAction(action);
}

function confirmDelete() {
    if (isDeleted.value) return;
    askConfirm({
        title: 'Hủy máy chủ Cloud VPS',
        message: `Xác nhận HỦY máy chủ ${liveVps.value.label}?\nMáy chủ sẽ được tắt nguồn, tắt tự động gia hạn và chuyển sang mục Đã xóa. Chi phí chu kỳ còn dư sẽ không được hoàn lại.`,
        confirmText: 'Xác nhận hủy VPS',
        variant: 'danger',
        onConfirm: () => router.delete(`/vps/${liveVps.value.id}`),
    });
}

// ================= RENEW LOGIC =================
const renewForm = useForm({
    billing_cycle: Object.keys(props.renewQuotes)[0] || 'monthly',
    coupon_code: '',
    expected_total: 0,
});
const renewCouponInput = ref('');
const renewLiveQuote = ref<any>(null);
const renewQuoteLoading = ref(false);

const activeRenewPrice = computed(() => {
    if (
        renewLiveQuote.value &&
        renewLiveQuote.value._cycle === renewForm.billing_cycle &&
        renewLiveQuote.value._code === renewForm.coupon_code
    ) {
        return renewLiveQuote.value.final_price;
    }
    return props.renewQuotes[renewForm.billing_cycle]?.final_price || 0;
});

const normalizedRenewCoupon = computed(() => renewCouponInput.value.trim().toUpperCase());
const canApplyRenewCoupon = computed(
    () =>
        !renewQuoteLoading.value &&
        normalizedRenewCoupon.value.length > 0 &&
        normalizedRenewCoupon.value !== renewForm.coupon_code
);

async function fetchRenewQuote(code: string) {
    renewForm.coupon_code = code;
    renewQuoteLoading.value = true;
    try {
        const addonCpu = Math.max(0, Number(liveVps.value.cpu || 0) - Number(liveVps.value.base_cpu || liveVps.value.cpu || 0));
        const addonRam = Math.max(0, Number(liveVps.value.ram || 0) - Number(liveVps.value.base_ram || liveVps.value.ram || 0));
        const addonDisk = Math.max(0, Number(liveVps.value.disk || 0) - Number(liveVps.value.base_disk || liveVps.value.disk || 0));
        const { data } = await axios.post('/promotions/quote', {
            service_type: 'vps',
            plan: liveVps.value.plan_id,
            billing_cycle: renewForm.billing_cycle,
            addon_cpu: addonCpu,
            addon_ram: addonRam,
            addon_disk: addonDisk,
            is_renew: true,
            coupon_code: code || undefined,
        });
        renewLiveQuote.value = { ...data, _cycle: renewForm.billing_cycle, _code: code };
    } catch {
    } finally {
        renewQuoteLoading.value = false;
    }
}

async function applyRenewCoupon() {
    if (!canApplyRenewCoupon.value) return;
    renewCouponInput.value = normalizedRenewCoupon.value;
    await fetchRenewQuote(normalizedRenewCoupon.value);
}

function clearRenewCoupon() {
    renewCouponInput.value = '';
    renewForm.coupon_code = '';
    renewLiveQuote.value = null;
}

watch(
    () => renewForm.billing_cycle,
    () => {
        if (renewForm.coupon_code) fetchRenewQuote(renewForm.coupon_code);
    }
);

function submitRenew() {
    if (isDeleted.value || !liveVps.value.can_renew) return;
    renewForm.expected_total = activeRenewPrice.value;
    renewForm.post(`/vps/${liveVps.value.id}/renew`, { preserveScroll: true });
}

function toggleVpsAutoRenew() {
    if (isDeleted.value) return;
    router.post(
        `/vps/${liveVps.value.id}/auto-renew`,
        { is_auto_renew: !liveVps.value.is_auto_renew },
        { preserveScroll: true }
    );
}

// ================= UPGRADE LOGIC (0ms Instant + Debounced Guard) =================
const upgradeForm = useForm({
    addon_cpu: 0,
    addon_ram: 0,
    addon_disk: 0,
    expected_total: 0,
});

const upgradeLiveQuote = ref<any>(null);
const syncedUpgradeKey = ref('0|0|0');
let upgradeTimer: ReturnType<typeof setTimeout> | null = null;
let upgradeAbort: AbortController | null = null;
let upgradeSeq = 0;

const currentUpgradeKey = computed(() => `${upgradeForm.addon_cpu}|${upgradeForm.addon_ram}|${upgradeForm.addon_disk}`);
const hasUpgradeSelection = computed(
    () => upgradeForm.addon_cpu > 0 || upgradeForm.addon_ram > 0 || upgradeForm.addon_disk > 0
);

const instantUpgradePrice = computed(() => {
    if (!hasUpgradeSelection.value) return 0;
    const days = Math.max(1, Number(liveVps.value.days_remaining || 30));
    const fullMonths = Math.floor(days / 30);
    const rem = days % 30;
    const factor = Math.max(0.5, fullMonths + (rem > 15 ? 1 : rem > 0 ? 0.5 : 0));
    const monthly =
        upgradeForm.addon_cpu * props.addonRates.cpu_monthly +
        upgradeForm.addon_ram * props.addonRates.ram_monthly +
        Math.floor(upgradeForm.addon_disk / 10) * props.addonRates.disk_10gb_monthly;
    const listPrice = Math.round(monthly * factor);
    const userDiscPct = Math.min(100, Math.max(0, Number(user.value?.discount_percent || 0)));
    const userDisc = userDiscPct > 0 ? Math.round(listPrice * (userDiscPct / 100)) : 0;
    return Math.max(0, listPrice - userDisc);
});

const estimatedUpgradePrice = computed(() => {
    if (!hasUpgradeSelection.value) return 0;
    if (upgradeLiveQuote.value && syncedUpgradeKey.value === currentUpgradeKey.value) {
        return upgradeLiveQuote.value.final_price;
    }
    return instantUpgradePrice.value;
});

const isUpgradeSyncing = computed(() => hasUpgradeSelection.value && syncedUpgradeKey.value !== currentUpgradeKey.value);

function stepUpgrade(field: 'addon_cpu' | 'addon_ram' | 'addon_disk', delta: number, min: number, max: number) {
    if (isDeleted.value) return;
    upgradeForm[field] = Math.min(max, Math.max(min, Number(upgradeForm[field] || 0) + delta));
}

async function fetchUpgradeQuoteNow() {
    if (upgradeTimer) {
        clearTimeout(upgradeTimer);
        upgradeTimer = null;
    }
    if (upgradeAbort) upgradeAbort.abort();
    if (!hasUpgradeSelection.value) {
        upgradeLiveQuote.value = null;
        syncedUpgradeKey.value = currentUpgradeKey.value;
        return;
    }

    const targetKey = currentUpgradeKey.value;
    const mySeq = ++upgradeSeq;
    upgradeAbort = new AbortController();

    try {
        const { data } = await axios.post(
            '/promotions/quote',
            {
                service_type: 'vps_upgrade',
                vps_id: liveVps.value.id,
                addon_cpu: upgradeForm.addon_cpu,
                addon_ram: upgradeForm.addon_ram,
                addon_disk: upgradeForm.addon_disk,
            },
            { signal: upgradeAbort.signal }
        );
        if (mySeq === upgradeSeq) {
            upgradeLiveQuote.value = data;
            syncedUpgradeKey.value = targetKey;
        }
    } catch (err: any) {
        if (axios.isCancel(err) || err?.code === 'ERR_CANCELED') return;
        if (mySeq === upgradeSeq) {
            upgradeLiveQuote.value = null;
            syncedUpgradeKey.value = targetKey;
        }
    }
}

watch(
    () => [upgradeForm.addon_cpu, upgradeForm.addon_ram, upgradeForm.addon_disk],
    () => {
        if (upgradeTimer) clearTimeout(upgradeTimer);
        if (upgradeAbort) upgradeAbort.abort();
        if (!hasUpgradeSelection.value) {
            upgradeLiveQuote.value = null;
            syncedUpgradeKey.value = currentUpgradeKey.value;
            return;
        }
        upgradeTimer = setTimeout(fetchUpgradeQuoteNow, 160);
    }
);

async function submitUpgrade() {
    if (isDeleted.value || isVpsOutOfStock.value || upgradeForm.processing || !hasUpgradeSelection.value) return;
    if (isUpgradeSyncing.value) {
        const seen = estimatedUpgradePrice.value;
        await fetchUpgradeQuoteNow();
        if (estimatedUpgradePrice.value !== seen) return;
    }
    upgradeForm.expected_total = estimatedUpgradePrice.value;
    upgradeForm.post(`/vps/${liveVps.value.id}/upgrade`, {
        preserveScroll: true,
        onSuccess: () => {
            upgradeForm.reset();
        },
    });
}
</script>

<template>
    <ClientLayout :title="`Máy chủ ${liveVps.label}`">
        <Head :title="liveVps.label" />

        <RebuildOsModal
            v-if="!isDeleted"
            v-model="showRebuildModal"
            :vps-id="liveVps.id"
            :vps-label="liveVps.label"
            :current-os-id="liveVps.os_id"
            :images="images"
        />

        <ChangeOsPasswordModal
            v-if="!isDeleted"
            v-model="showPasswordModal"
            :vps-id="liveVps.id"
            :vps-label="liveVps.label"
            :vps-state="liveVps.state"
            :current-password="liveVps.root_password"
        />

        <ChangeIpModal
            v-if="!isDeleted && changeIpConfig?.enabled"
            v-model="showChangeIpModal"
            :vps-id="liveVps.id"
            :vps-label="liveVps.label"
            :vps-state="liveVps.state"
            :current-ip="liveVps.public_ip"
            :current-password="liveVps.root_password"
            :price="changeIpConfig.price"
            :limit-per-month="changeIpConfig.limit_per_month"
            :user-balance="user?.balance || 0"
            @submitted="ensurePollingActive"
        />

        <ExtendDiskModal
            v-if="!isDeleted && liveVps.can_extend_disk"
            v-model="showExtendDiskModal"
            :vps-id="liveVps.id"
            :vps-label="liveVps.label"
            :vps-state="liveVps.state"
            :current-disk="liveVps.disk"
            :current-password="liveVps.root_password"
            @submitted="ensurePollingActive"
        />

        <div class="space-y-6 pb-10">
            <!-- 1. HERO INSTANCE COMMAND HEADER -->
            <div class="surface-card p-5 sm:p-6 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <Link href="/vps" class="inline-flex items-center gap-1.5 font-semibold text-slate-500 hover:text-[#0B1437]">
                        <ArrowLeft class="w-3.5 h-3.5" />
                        <span>Danh sách Cloud VPS</span>
                    </Link>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-semibold">
                            Gói {{ liveVps.plan_name }}
                        </span>
                        <span class="px-2.5 py-0.5 rounded-md bg-blue-50 text-[#1D6AE5] font-semibold">
                            Datacenter FPT (TP.HCM)
                        </span>
                        <span v-if="liveVps.created_at" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold">
                            <Calendar class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                            <span>Khởi tạo: <strong class="text-slate-900 font-extrabold font-mono-num">{{ liveVps.created_at }}</strong></span>
                        </span>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <!-- Left: OS Icon + Editable Hostname + Status -->
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200/90 flex items-center justify-center shrink-0">
                            <OsIcon :distro="liveVps.os_distro" :size="28" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <form v-if="editingLabel" @submit.prevent="saveLabel" class="flex items-center gap-2">
                                <input
                                    v-model="labelForm.label"
                                    type="text"
                                    maxlength="64"
                                    class="px-3 py-1.5 rounded-lg border border-[#1D6AE5] text-base font-bold text-[#0B1437] focus:outline-none"
                                />
                                <button
                                    type="submit"
                                    :disabled="labelForm.processing"
                                    class="px-3 py-1.5 rounded-lg bg-[#1D6AE5] text-white text-xs font-bold cursor-pointer"
                                >
                                    Lưu
                                </button>
                                <button
                                    type="button"
                                    @click="
                                        editingLabel = false;
                                        labelForm.label = liveVps.label;
                                    "
                                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 cursor-pointer"
                                >
                                    Hủy
                                </button>
                            </form>

                            <div v-else class="flex flex-wrap items-center gap-2.5">
                                <h1 class="text-xl sm:text-2xl font-extrabold text-[#0B1437] tracking-tight truncate">
                                    {{ liveVps.label }}
                                </h1>
                                <button
                                    type="button"
                                    @click="editingLabel = true"
                                    title="Đổi tên gợi nhớ máy chủ"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-[#1D6AE5] hover:bg-blue-50 transition-colors cursor-pointer"
                                >
                                    <Pencil class="w-3.5 h-3.5" />
                                </button>
                                <StatusBadge :state="liveVps.state" :label="liveVps.status" />
                            </div>

                            <div class="text-[13.5px] text-slate-600 font-medium mt-0.5 truncate">
                                {{ liveVps.os_label }} · Toàn quyền {{ isWindows ? 'Administrator (RDP)' : 'Root (SSH)' }}
                            </div>
                        </div>
                    </div>

                    <!-- Right: Context-Aware Power & Console Toolbar -->
                    <div v-if="isDeleted" class="flex flex-wrap items-center gap-2.5">
                        <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold">
                            <Lock class="w-3.5 h-3.5" />
                            <span>Máy chủ đã thu hồi tài nguyên</span>
                        </span>
                        <Link
                            :href="`/store/${liveVps.plan_id || 'lg-4'}`"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold transition-colors"
                        >
                            <Plus class="w-4 h-4" />
                            <span>Khởi tạo mới cấu hình này</span>
                        </Link>
                    </div>

                    <div v-else class="flex flex-wrap items-center gap-2">
                        <a
                            v-if="liveVps.state === 'running'"
                            :href="`/vps/${liveVps.id}/console`"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold transition-colors"
                        >
                            <Terminal class="w-3.5 h-3.5" />
                            <span>Mở VNC Console</span>
                        </a>

                        <button
                            v-if="isWindows && liveVps.public_ip"
                            type="button"
                            @click="downloadRdpFile(liveVps.public_ip, liveVps.login_username, liveVps.label)"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-50 hover:bg-blue-100 border border-blue-200 text-[#1D6AE5] text-xs font-bold cursor-pointer transition-colors"
                        >
                            <Download class="w-3.5 h-3.5" />
                            <span>Tải file .RDP</span>
                        </button>

                        <div class="inline-flex items-center p-1 rounded-xl bg-slate-100 border border-slate-200 gap-1">
                            <button
                                type="button"
                                :disabled="liveVps.state === 'running' || !!busyPowerAction"
                                @click="powerAction('boot')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-white hover:bg-emerald-50 text-emerald-700 shadow-2xs"
                            >
                                <Play class="w-3.5 h-3.5" />
                                <span>Bật</span>
                            </button>
                            <button
                                type="button"
                                :disabled="liveVps.state === 'stopped' || !!busyPowerAction"
                                @click="powerAction('reboot')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-white hover:bg-slate-50 text-slate-700 shadow-2xs"
                            >
                                <RotateCw class="w-3.5 h-3.5" :class="busyPowerAction === 'reboot' ? 'animate-spin' : ''" />
                                <span>Reboot</span>
                            </button>
                            <button
                                type="button"
                                :disabled="liveVps.state === 'stopped' || !!busyPowerAction"
                                @click="powerAction('shutdown')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed bg-white hover:bg-amber-50 text-amber-700 shadow-2xs"
                            >
                                <Square class="w-3.5 h-3.5" />
                                <span>Tắt</span>
                            </button>
                            <button
                                type="button"
                                :disabled="!!busyPowerAction"
                                @click="powerAction('sync')"
                                title="Đồng bộ trạng thái mới nhất"
                                class="p-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-600 shadow-2xs cursor-pointer"
                            >
                                <RefreshCw class="w-3.5 h-3.5" :class="busyPowerAction === 'sync' ? 'animate-spin' : ''" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. BỐN THẺ THÔNG SỐ HẠ TẦNG & THỜI HẠN RÕ NÉT -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="surface-card p-4 sm:p-5 flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#1D6AE5] flex items-center justify-center shrink-0">
                        <Globe class="w-5 h-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-bold text-slate-600">Địa chỉ IPv4 &amp; Cổng mạng</div>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="font-mono-num text-[16px] font-extrabold text-[#0B1437] truncate">
                                {{ liveVps.public_ip || 'Đang cấp phát...' }}
                            </span>
                            <button
                                v-if="liveVps.public_ip"
                                type="button"
                                @click="copyField(liveVps.public_ip, 'Địa chỉ IPv4', 'ip')"
                                class="p-1 rounded-md hover:bg-slate-100 text-slate-400 hover:text-[#1D6AE5] cursor-pointer shrink-0"
                            >
                                <Check v-if="copiedTag === 'ip'" class="w-4 h-4 text-emerald-600" />
                                <Copy v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="text-xs text-slate-600 font-medium mt-0.5 flex flex-wrap items-center justify-between gap-2">
                            <span>Port {{ liveVps.network_mbps >= 1000 ? '1 Gbps' : `${liveVps.network_mbps} Mbps` }}</span>
                            <button
                                v-if="!isDeleted && changeIpConfig?.enabled && liveVps.public_ip"
                                type="button"
                                @click="showChangeIpModal = true"
                                class="text-[11.5px] font-bold text-[#1D6AE5] hover:text-[#1557C0] cursor-pointer transition-colors"
                            >
                                Đổi IPv4 ({{ formatVnd(changeIpConfig.price) }})
                            </button>
                        </div>
                    </div>
                </div>

                <div class="surface-card p-4 sm:p-5 flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <Cpu class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-slate-600">Vi xử lý &amp; Bộ nhớ RAM</div>
                        <div class="mt-1 font-mono-num text-[16px] font-extrabold text-[#0B1437]">
                            {{ liveVps.cpu }} vCPU · {{ liveVps.ram }} GB RAM
                        </div>
                        <div class="text-xs text-slate-600 font-medium mt-0.5">Intel Xeon Gold / Platinum</div>
                    </div>
                </div>

                <div class="surface-card p-4 sm:p-5 flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <HardDrive class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-slate-600">Lưu trữ &amp; Băng thông</div>
                        <div class="mt-1 font-mono-num text-[16px] font-extrabold text-[#0B1437]">
                            {{ liveVps.disk }} GB SSD
                        </div>
                        <div class="text-xs text-slate-600 font-medium mt-0.5 flex flex-wrap items-center justify-between gap-2">
                            <span>SSD chuyên dụng · {{ liveVps.transfer_tb === 'Unlimited' ? 'BW Không giới hạn' : `${liveVps.transfer_tb} TB` }}</span>
                            <button
                                v-if="!isDeleted && liveVps.can_extend_disk"
                                type="button"
                                @click="showExtendDiskModal = true"
                                class="text-[11.5px] font-bold text-emerald-700 hover:text-emerald-800 cursor-pointer transition-colors"
                            >
                                Mở rộng phân vùng
                            </button>
                        </div>
                    </div>
                </div>

                <div class="surface-card p-4 sm:p-5 flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                        <Calendar class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-slate-600">Thời hạn dịch vụ</div>
                        <div class="mt-1 font-mono-num text-[15.5px] font-extrabold text-[#0B1437]">
                            {{ liveVps.expires_at || '—' }}
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <span
                                v-if="expiryBadge"
                                class="px-2 py-0.5 rounded-md border text-[11px] font-bold whitespace-nowrap"
                                :class="expiryBadge.cls"
                            >
                                {{ expiryBadge.text }}
                            </span>
                            <span class="text-xs font-semibold text-slate-600">· {{ liveVps.billing_cycle_label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. BỐ CỤC CLOUD CONSOLE 2 CỘT CÂN ĐỐI (KHÔNG DÙNG TAB RỜI RẠC, KHÔNG TRỐNG TRẢI) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- CỘT TRÁI (7/12): TRUY CẬP QUẢN TRỊ & NÂNG CẤP PHẦN CỨNG -->
                <div class="lg:col-span-7 space-y-6">
                    <!-- KHỐI A: THÔNG TIN ĐĂNG NHẬP & LỆNH KẾT NỐI NHANH -->
                    <section class="surface-card overflow-hidden">
                        <div class="px-5 sm:px-6 py-4 border-b border-slate-200/90 bg-slate-50/70 flex flex-wrap items-center justify-between gap-2.5">
                            <div class="flex items-center gap-2.5">
                                <KeyRound class="w-4 h-4 text-[#1D6AE5]" />
                                <h2 class="text-[15.5px] font-extrabold text-[#0B1437]">
                                    Thông tin Truy cập Quản trị ({{ isWindows ? 'Remote Desktop' : 'SSH Root' }})
                                </h2>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    v-if="liveVps.public_ip && liveVps.root_password"
                                    type="button"
                                    @click="copyField(formatFullVpsCredential(liveVps), 'IP|User|Pass', 'full')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer transition-colors"
                                >
                                    <Check v-if="copiedTag === 'full'" class="w-3.5 h-3.5" />
                                    <Copy v-else class="w-3.5 h-3.5" />
                                    <span>{{ copiedTag === 'full' ? 'Đã chép Full' : 'Copy IP|User|Pass' }}</span>
                                </button>

                                <button
                                    v-if="!isDeleted"
                                    type="button"
                                    @click="showPasswordModal = true"
                                    class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer transition-colors"
                                >
                                    Đổi mật khẩu OS
                                </button>

                                <button
                                    v-if="!isDeleted && changeIpConfig?.enabled && liveVps.public_ip"
                                    type="button"
                                    @click="showChangeIpModal = true"
                                    class="px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-xs font-bold text-[#1D6AE5] cursor-pointer transition-colors"
                                >
                                    Đổi IPv4 ({{ formatVnd(changeIpConfig.price) }})
                                </button>
                            </div>
                        </div>

                        <div class="p-5 sm:p-6 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90">
                                    <div class="text-xs font-semibold text-slate-500 mb-1">Giao thức &amp; Cổng</div>
                                    <div class="font-mono text-[14px] font-bold text-[#0B1437]">{{ defaultPort }}</div>
                                </div>

                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90">
                                    <div class="text-xs font-semibold text-slate-500 mb-1">Tài khoản (Username)</div>
                                    <div class="flex items-center justify-between gap-2 font-mono text-[14px] font-bold text-[#0B1437]">
                                        <span class="truncate">{{ liveVps.login_username }}</span>
                                        <button
                                            type="button"
                                            @click="copyField(liveVps.login_username, 'Username', 'user')"
                                            class="p-1 rounded hover:bg-slate-200/70 text-slate-400 hover:text-[#1D6AE5] cursor-pointer shrink-0"
                                        >
                                            <Check v-if="copiedTag === 'user'" class="w-3.5 h-3.5 text-emerald-600" />
                                            <Copy v-else class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90">
                                    <div class="text-xs font-semibold text-slate-500 mb-1">Mật khẩu OS</div>
                                    <div class="flex items-center justify-between gap-2 font-mono text-[14px] font-bold text-[#0B1437]">
                                        <span class="truncate select-all">
                                            {{ showPass ? liveVps.root_password || 'Đang đồng bộ...' : '••••••••••••' }}
                                        </span>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <button
                                                type="button"
                                                @click="showPass = !showPass"
                                                class="p-1 rounded hover:bg-slate-200/70 text-slate-400 hover:text-slate-700 cursor-pointer"
                                            >
                                                <EyeOff v-if="showPass" class="w-3.5 h-3.5" />
                                                <Eye v-else class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                v-if="liveVps.root_password"
                                                type="button"
                                                @click="copyField(liveVps.root_password, 'Mật khẩu', 'pass')"
                                                class="p-1 rounded hover:bg-slate-200/70 text-slate-400 hover:text-[#1D6AE5] cursor-pointer"
                                            >
                                                <Check v-if="copiedTag === 'pass'" class="w-3.5 h-3.5 text-emerald-600" />
                                                <Copy v-else class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Thanh lệnh kết nối nhanh -->
                            <div
                                v-if="connectCommand"
                                class="p-4 rounded-xl bg-[#0B1437] text-slate-100 flex flex-wrap items-center justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <div class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-1">
                                        {{ isWindows ? 'Lệnh mở nhanh Windows Run (Win + R)' : 'Lệnh kết nối SSH Terminal' }}
                                    </div>
                                    <code class="text-[13.5px] font-mono text-sky-300 select-all">{{ connectCommand }}</code>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        @click="copyField(connectCommand, 'lệnh kết nối', 'cmd')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-bold text-white cursor-pointer"
                                    >
                                        <Check v-if="copiedTag === 'cmd'" class="w-3.5 h-3.5 text-emerald-400" />
                                        <Copy v-else class="w-3.5 h-3.5" />
                                        <span>{{ copiedTag === 'cmd' ? 'Đã chép' : 'Sao chép lệnh' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- KHỐI B: NÂNG CẤP PHẦN CỨNG GIỮA KỲ (CHỈ HIỂN THỊ TƯƠNG TÁC KHI MÁY CHỦ CÒN HIỆU LỰC & KHO VPS ĐANG MỞ) -->
                    <section class="surface-card overflow-hidden" :class="isDeleted ? 'opacity-65' : ''">
                        <div class="px-5 sm:px-6 py-4 border-b border-slate-200/90 bg-slate-50/70 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <Sparkles class="w-4 h-4 text-[#1D6AE5]" />
                                <h2 class="text-[15.5px] font-extrabold text-[#0B1437]">
                                    Nâng cấp Tài nguyên Giữa kỳ (Giữ nguyên 100% dữ liệu)
                                </h2>
                            </div>
                            <span v-if="!isDeleted && !isVpsOutOfStock" class="text-xs font-bold text-slate-600">
                                Tính bù theo {{ liveVps.days_remaining }} ngày còn lại
                            </span>
                            <span
                                v-else-if="!isDeleted && isVpsOutOfStock"
                                class="px-2.5 py-0.5 rounded-md bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold"
                            >
                                Tạm ngưng nâng cấp
                            </span>
                        </div>

                        <div v-if="isDeleted" class="p-6 text-[13.5px] text-slate-600 font-medium">
                            Máy chủ đã ngừng hoạt động và thu hồi tài nguyên nên không thể nâng cấp phần cứng. Bạn có thể khởi tạo máy chủ mới với cấu hình cao hơn tại cửa hàng.
                        </div>

                        <div v-else-if="isVpsOutOfStock" class="p-5 sm:p-6">
                            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3 text-xs sm:text-[13px] text-amber-950 leading-relaxed">
                                <AlertCircle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                <div>
                                    <div class="font-bold text-amber-950">Cụm máy chủ Cloud VPS hiện đang tạm hết tài nguyên</div>
                                    <p class="text-amber-900/90 mt-0.5">
                                        Chức năng nâng cấp thêm vCPU, RAM và ổ cứng SSD đang tạm khóa để bổ sung hạ tầng. Bạn vẫn có thể sử dụng, cài lại hệ điều hành và <strong>gia hạn máy chủ bình thường</strong> ở cột bên cạnh.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form v-else @submit.prevent="submitUpgrade" class="p-5 sm:p-6 space-y-5">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                                <!-- vCPU -->
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90 space-y-2.5">
                                    <div class="flex items-center justify-between text-[13px]">
                                        <span class="font-bold text-slate-800 whitespace-nowrap">Thêm vCPU</span>
                                        <span class="font-mono-num font-extrabold text-[#1D6AE5]">+{{ upgradeForm.addon_cpu }} Core</span>
                                    </div>
                                    <input
                                        v-model.number="upgradeForm.addon_cpu"
                                        type="range"
                                        min="0"
                                        max="16"
                                        class="w-full accent-[#1D6AE5] cursor-pointer"
                                    />
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-baseline gap-0.5 whitespace-nowrap">
                                            <span class="text-[13px] font-extrabold font-mono-num text-[#0B1437]">{{ formatVnd(addonRates.cpu_monthly) }}</span>
                                            <span class="text-xs font-semibold text-slate-600">/Core/th</span>
                                        </div>
                                        <div class="inline-flex items-center gap-1 shrink-0">
                                            <button type="button" @click="stepUpgrade('addon_cpu', -1, 0, 16)" class="w-7 h-7 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 flex items-center justify-center cursor-pointer transition-colors"><Minus class="w-3.5 h-3.5" /></button>
                                            <button type="button" @click="stepUpgrade('addon_cpu', 1, 0, 16)" class="w-7 h-7 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 flex items-center justify-center cursor-pointer transition-colors"><Plus class="w-3.5 h-3.5" /></button>
                                        </div>
                                    </div>
                                </div>

                                <!-- RAM -->
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90 space-y-2.5">
                                    <div class="flex items-center justify-between text-[13px]">
                                        <span class="font-bold text-slate-800 whitespace-nowrap">Thêm RAM</span>
                                        <span class="font-mono-num font-extrabold text-[#1D6AE5]">+{{ upgradeForm.addon_ram }} GB</span>
                                    </div>
                                    <input
                                        v-model.number="upgradeForm.addon_ram"
                                        type="range"
                                        min="0"
                                        max="64"
                                        class="w-full accent-[#1D6AE5] cursor-pointer"
                                    />
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-baseline gap-0.5 whitespace-nowrap">
                                            <span class="text-[13px] font-extrabold font-mono-num text-[#0B1437]">{{ formatVnd(addonRates.ram_monthly) }}</span>
                                            <span class="text-xs font-semibold text-slate-600">/GB/th</span>
                                        </div>
                                        <div class="inline-flex items-center gap-1 shrink-0">
                                            <button type="button" @click="stepUpgrade('addon_ram', -1, 0, 64)" class="w-7 h-7 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 flex items-center justify-center cursor-pointer transition-colors"><Minus class="w-3.5 h-3.5" /></button>
                                            <button type="button" @click="stepUpgrade('addon_ram', 1, 0, 64)" class="w-7 h-7 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 flex items-center justify-center cursor-pointer transition-colors"><Plus class="w-3.5 h-3.5" /></button>
                                        </div>
                                    </div>
                                </div>

                                <!-- SSD -->
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90 space-y-2.5">
                                    <div class="flex items-center justify-between text-[13px]">
                                        <span class="font-bold text-slate-800 whitespace-nowrap">Thêm SSD</span>
                                        <span class="font-mono-num font-extrabold text-[#1D6AE5]">+{{ upgradeForm.addon_disk }} GB</span>
                                    </div>
                                    <input
                                        v-model.number="upgradeForm.addon_disk"
                                        type="range"
                                        min="0"
                                        max="200"
                                        step="10"
                                        class="w-full accent-[#1D6AE5] cursor-pointer"
                                    />
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-baseline gap-0.5 whitespace-nowrap">
                                            <span class="text-[13px] font-extrabold font-mono-num text-[#0B1437]">{{ formatVnd(addonRates.disk_10gb_monthly) }}</span>
                                            <span class="text-xs font-semibold text-slate-600">/10GB/th</span>
                                        </div>
                                        <div class="inline-flex items-center gap-1 shrink-0">
                                            <button type="button" @click="stepUpgrade('addon_disk', -10, 0, 200)" class="w-7 h-7 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 flex items-center justify-center cursor-pointer transition-colors"><Minus class="w-3.5 h-3.5" /></button>
                                            <button type="button" @click="stepUpgrade('addon_disk', 10, 0, 200)" class="w-7 h-7 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 flex items-center justify-center cursor-pointer transition-colors"><Plus class="w-3.5 h-3.5" /></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="space-y-0.5">
                                    <div class="text-xs font-semibold text-slate-500">Cấu hình sau nâng cấp:</div>
                                    <div class="font-mono-num text-[13.5px] font-bold text-[#0B1437]">
                                        {{ liveVps.cpu + upgradeForm.addon_cpu }} vCPU · {{ liveVps.ram + upgradeForm.addon_ram }} GB RAM · {{ liveVps.disk + upgradeForm.addon_disk }} GB SSD
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span class="text-xs font-semibold text-slate-600">Phí bù giữa kỳ</span>
                                            <span
                                                v-if="upgradeLiveQuote?.campaign?.badge_text && upgradeLiveQuote?.discount_amount > 0"
                                                class="px-1.5 py-0.5 rounded bg-rose-50 border border-rose-200 text-rose-700 text-[10px] font-extrabold"
                                            >
                                                {{ upgradeLiveQuote.campaign.badge_text }}
                                            </span>
                                        </div>
                                        <div class="flex items-baseline justify-end gap-1.5">
                                            <span
                                                v-if="upgradeLiveQuote?.discount_amount > 0 && upgradeLiveQuote?.list_price > estimatedUpgradePrice"
                                                class="font-mono-num text-xs text-slate-400 line-through"
                                            >
                                                {{ formatVnd(upgradeLiveQuote.list_price) }}
                                            </span>
                                            <span class="font-mono-num text-base font-extrabold text-[#1D6AE5]">
                                                {{ formatVnd(estimatedUpgradePrice) }}
                                            </span>
                                        </div>
                                    </div>
                                    <button
                                        type="submit"
                                        :disabled="upgradeForm.processing || isUpgradeSyncing || estimatedUpgradePrice <= 0"
                                        class="h-10 px-4 rounded-xl bg-[#0B1437] hover:bg-[#162354] disabled:opacity-40 text-white text-xs font-bold cursor-pointer inline-flex items-center gap-1.5 whitespace-nowrap"
                                    >
                                        <Loader2 v-if="upgradeForm.processing || isUpgradeSyncing" class="w-3.5 h-3.5 animate-spin" />
                                        <span>{{ isUpgradeSyncing ? 'Đang chốt giá...' : 'Nâng cấp ngay' }}</span>
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="liveVps.can_extend_disk"
                                class="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                            >
                                <div class="text-emerald-950 leading-relaxed">
                                    <strong class="font-bold">Đã nâng cấp thêm SSD?</strong> Nhấn mở rộng để gộp phần dung lượng mới vào phân vùng ổ đĩa trong OS (miễn phí, giữ nguyên dữ liệu).
                                </div>
                                <button
                                    type="button"
                                    @click="showExtendDiskModal = true"
                                    class="px-3.5 py-2 rounded-xl bg-white hover:bg-emerald-50 border border-emerald-300 text-emerald-800 text-xs font-bold shrink-0 cursor-pointer transition-colors"
                                >
                                    Mở rộng phân vùng SSD
                                </button>
                            </div>
                        </form>
                    </section>
                </div>

                <!-- CỘT PHẢI (5/12): GIA HẠN NHANH & CÀI LẠI OS / VÙNG CẨN TRỌNG -->
                <div class="lg:col-span-5 space-y-6">
                    <!-- KHỐI C: GIA HẠN DỊCH VỤ -->
                    <section class="surface-card overflow-hidden">
                        <div class="px-5 sm:px-6 py-4 border-b border-slate-200/90 bg-slate-50/70 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <Calendar class="w-4 h-4 text-[#1D6AE5]" />
                                <h2 class="text-[15.5px] font-extrabold text-[#0B1437]">Gia hạn thời gian sử dụng</h2>
                            </div>
                        </div>

                        <div v-if="isDeleted || !liveVps.can_renew" class="p-6 space-y-3">
                            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-[13px] text-rose-800 leading-relaxed">
                                Máy chủ này đã quá hạn trên 5 ngày (hoặc đã bị hủy) và đã giải phóng khỏi cụm vật lý nên không thể gia hạn tiếp.
                            </div>
                            <Link
                                :href="`/store/${liveVps.plan_id || 'lg-4'}`"
                                class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 px-4 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold"
                            >
                                <Plus class="w-4 h-4" />
                                <span>Thuê máy chủ mới ngay</span>
                            </Link>
                        </div>

                        <form v-else @submit.prevent="submitRenew" class="p-5 sm:p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-2.5">
                                <button
                                    v-for="(q, key) in renewQuotes"
                                    :key="key"
                                    type="button"
                                    @click="renewForm.billing_cycle = String(key)"
                                    class="p-3 rounded-xl border text-left transition-all cursor-pointer"
                                    :class="
                                        renewForm.billing_cycle === key
                                            ? 'border-[#1D6AE5] bg-blue-50/50 ring-1 ring-[#1D6AE5]'
                                            : 'border-slate-200 hover:border-slate-300'
                                    "
                                >
                                    <div class="text-xs font-bold text-slate-700">{{ q.label }}</div>
                                    <div class="font-mono-num text-[14.5px] font-extrabold text-[#0B1437] mt-0.5">
                                        {{ formatVnd(q.final_price) }}
                                    </div>
                                </button>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Mã giảm giá gia hạn (nếu có)</label>
                                <div class="flex gap-2">
                                    <div class="relative flex-1">
                                        <input
                                            v-model="renewCouponInput"
                                            type="text"
                                            placeholder="Nhập mã ưu đãi"
                                            class="w-full h-10 pl-3 pr-8 rounded-xl border border-slate-300 text-xs font-mono uppercase focus:outline-none focus:border-[#1D6AE5]"
                                            @keydown.enter.prevent.stop="applyRenewCoupon"
                                        />
                                        <button
                                            v-if="renewCouponInput || renewForm.coupon_code"
                                            type="button"
                                            @click="clearRenewCoupon"
                                            title="Xóa mã giảm giá"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 rounded-full text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer"
                                        >
                                            <X class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        :disabled="!canApplyRenewCoupon"
                                        @click="applyRenewCoupon"
                                        class="h-10 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-200 text-xs font-bold text-slate-800 cursor-pointer transition-colors disabled:opacity-45 disabled:cursor-not-allowed disabled:hover:bg-slate-100 shrink-0"
                                    >
                                        {{ renewQuoteLoading ? 'Đang kiểm tra...' : 'Áp dụng' }}
                                    </button>
                                </div>
                                <p
                                    v-if="renewLiveQuote?.coupon"
                                    class="mt-1.5 text-xs font-semibold"
                                    :class="renewLiveQuote.coupon.valid ? 'text-emerald-600' : 'text-rose-600'"
                                >
                                    {{ renewLiveQuote.coupon.message }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="renewForm.processing || renewQuoteLoading"
                                class="w-full py-2.5 px-4 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] disabled:opacity-50 text-white text-[13.5px] font-bold cursor-pointer inline-flex items-center justify-center gap-2 transition-colors"
                            >
                                <Loader2 v-if="renewForm.processing || renewQuoteLoading" class="w-4 h-4 animate-spin" />
                                <span>
                                    {{
                                        renewForm.processing
                                            ? 'Đang xử lý gia hạn...'
                                            : `Xác nhận Gia hạn — ${formatVnd(activeRenewPrice)}`
                                    }}
                                </span>
                            </button>

                            <p class="text-xs text-slate-600 font-medium leading-relaxed">
                                Lưu ý: Hệ thống giữ dữ liệu tối đa <strong>05 ngày</strong> sau khi hết hạn trước khi thu hồi tự động.
                            </p>

                            <!-- Bật/Tắt Tự động gia hạn trước 24h -->
                            <div class="pt-3.5 border-t border-slate-200/80 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-[13px] font-bold text-[#0B1437]">Tự động gia hạn (trước 24h)</div>
                                    <p class="text-xs text-slate-600 font-medium mt-0.5 leading-relaxed">
                                        Tự trừ ví trước hạn 01 ngày khi đủ số dư, báo email sớm nếu ví thiếu tiền.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="!!liveVps.is_auto_renew"
                                    @click="toggleVpsAutoRenew"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none mt-0.5"
                                    :class="liveVps.is_auto_renew ? 'bg-emerald-500' : 'bg-slate-300'"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition duration-200"
                                        :class="liveVps.is_auto_renew ? 'translate-x-5' : 'translate-x-0'"
                                    />
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- KHỐI D: CÀI ĐẶT LẠI OS & KHU VỰC NGUY HIỂM -->
                    <section v-if="!isDeleted" class="surface-card overflow-hidden border-rose-200/80">
                        <div class="px-5 sm:px-6 py-3.5 border-b border-rose-100 bg-rose-50/50 flex items-center gap-2">
                            <ShieldAlert class="w-4 h-4 text-rose-600" />
                            <h2 class="text-[14.5px] font-extrabold text-rose-950">Hệ điều hành &amp; Khu vực cẩn trọng</h2>
                        </div>

                        <div class="p-5 sm:p-6 space-y-4">
                            <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-200/80">
                                <div class="min-w-0">
                                    <div class="text-[13.5px] font-bold text-[#0B1437]">Cài đặt lại Hệ điều hành</div>
                                    <div class="text-xs text-slate-600 font-medium mt-0.5">
                                        Đổi sang Windows Server / Ubuntu / CentOS sạch
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="showRebuildModal = true"
                                    class="px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-800 shrink-0 cursor-pointer transition-colors"
                                >
                                    Chọn OS cài lại...
                                </button>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-[13.5px] font-bold text-rose-700">Hủy &amp; Thu hồi máy chủ</div>
                                    <div class="text-xs text-slate-600 font-medium mt-0.5">
                                        Xóa vĩnh viễn dữ liệu và giải phóng IPv4 ngay lập tức
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="confirmDelete"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white text-xs font-bold shrink-0 cursor-pointer transition-colors"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Hủy máy chủ</span>
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
