<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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
    Trash2,
    Globe,
    Network,
    KeyRound,
    User,
    ShieldCheck,
    Terminal,
    Calendar,
    Zap,
    AlertCircle,
    CheckCircle2,
    Smartphone,
    Laptop,
    HelpCircle,
    ArrowLeftRight,
    Shuffle,
    Loader2,
    X,
} from 'lucide-vue-next';

interface RenewQuote {
    cycle: string;
    label: string;
    months: number;
    discount_percent?: number;
    price: number;
    list_price: number;
    discount_amount: number;
}

const props = defineProps<{
    proxy: {
        id: number;
        product_id?: number;
        product_name: string;
        category?: string;
        protocol: string;
        ip: string | null;
        port: string | number | null;
        username: string | null;
        password: string | null;
        http_port?: string | number | null;
        http_username?: string | null;
        http_password?: string | null;
        sock5_port?: string | number | null;
        sock5_username?: string | null;
        sock5_password?: string | null;
        connection_string: string | null;
        connection_url: string | null;
        status: string;
        state: string;
        days_until_expiry: number | null;
        is_expired: boolean;
        is_auto_renew: boolean;
        renew_price: number;
        renew_quotes?: Record<string, RenewQuote>;
        paid_amount: number;
        billing_cycle?: string;
        billing_cycle_label?: string;
        expires_at: string | null;
        created_at: string | null;
    };
}>();

const toast = useToast();
const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const isDeleted = computed(() => props.proxy.state === 'deleted');
const isResidential = computed(() => props.proxy.category === 'residential');
const targetProtocol = computed<'HTTP' | 'SOCKS5'>(() => (props.proxy.protocol === 'SOCKS5' ? 'HTTP' : 'SOCKS5'));

// Self-service actions: Edit Credentials, Change Type, Sync IP (Residential)
const showEditCredsModal = ref(false);
const isChangingType = ref(false);
const isSyncingIp = ref(false);

const credsForm = useForm({
    username: props.proxy.username || '',
    password: props.proxy.password || '',
});

function openEditCredsModal() {
    credsForm.clearErrors();
    credsForm.username = props.proxy.username || '';
    credsForm.password = props.proxy.password || '';
    showEditCredsModal.value = true;
}

function generateRandomCreds() {
    const randUser = 'lg' + Math.random().toString(36).substring(2, 9);
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    let randPass = '';
    for (let i = 0; i < 12; i++) {
        randPass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    credsForm.username = randUser;
    credsForm.password = randPass;
}

function submitEditCreds() {
    if (credsForm.processing || isDeleted.value) return;
    credsForm.post(`/proxy/${props.proxy.id}/credentials`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditCredsModal.value = false;
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            if (firstErr) toast.error(String(firstErr));
        },
    });
}

function confirmChangeProtocol() {
    if (isDeleted.value || isChangingType.value) return;
    const nextProto = targetProtocol.value;
    askConfirm({
        title: `Chuyển Proxy #${props.proxy.id} sang ${nextProto}`,
        message: `Xác nhận chuyển giao thức Proxy #${props.proxy.id} từ ${props.proxy.protocol} sang ${nextProto}? Cổng kết nối có thể thay đổi theo giao thức mới.`,
        confirmText: `Chuyển sang ${nextProto}`,
        variant: 'primary',
        onConfirm: () => {
            isChangingType.value = true;
            router.post(
                `/proxy/${props.proxy.id}/change-type`,
                { type: nextProto },
                {
                    preserveScroll: true,
                    onFinish: () => {
                        isChangingType.value = false;
                    },
                }
            );
        },
    });
}

function confirmSyncIp() {
    if (isDeleted.value || !isResidential.value || isSyncingIp.value) return;
    askConfirm({
        title: `Đổi IP mới cho Proxy #${props.proxy.id}`,
        message: 'Hệ thống sẽ yêu cầu cấp địa chỉ IP dân cư mới cho Proxy này (miễn phí). Kết nối hiện tại sẽ chuyển sang IP mới sau 5 – 10 giây.',
        confirmText: 'Đổi IP mới ngay',
        variant: 'primary',
        onConfirm: () => {
            isSyncingIp.value = true;
            router.post(
                `/proxy/${props.proxy.id}/sync-ip`,
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setTimeout(() => {
                            router.reload({ only: ['proxy'] });
                        }, 7000);
                    },
                    onFinish: () => {
                        isSyncingIp.value = false;
                    },
                }
            );
        },
    });
}

// Hiển thị / Ẩn mật khẩu
const showPassword = ref(false);

// Trạng thái copy feedback theo từng key
const copiedKey = ref<string | null>(null);

async function copyText(text: string | number | null | undefined, key: string, label: string) {
    if (!text) return;
    const ok = await copyToClipboard(String(text));
    if (ok) {
        copiedKey.value = key;
        toast.success(`Đã sao chép ${label}`);
        setTimeout(() => {
            if (copiedKey.value === key) copiedKey.value = null;
        }, 1800);
    }
}

// Tab định dạng chuỗi kết nối
const formatType = ref<'standard' | 'url' | 'socks5'>('standard');

const currentConnectionString = computed(() => {
    const p = props.proxy;
    if (!p.ip) return 'Đang cấp phát IP...';

    if (formatType.value === 'url') {
        const u = p.username || 'user';
        const pass = p.password || 'pass';
        const port = p.port || 8080;
        return `http://${u}:${pass}@${p.ip}:${port}`;
    }

    if (formatType.value === 'socks5') {
        const u = p.sock5_username || p.username || 'user';
        const pass = p.sock5_password || p.password || 'pass';
        const port = p.sock5_port || p.port || 1080;
        return `socks5://${u}:${pass}@${p.ip}:${port}`;
    }

    // Standard IP:Port:User:Pass
    const port = p.port || 8080;
    const u = p.username || '';
    const pass = p.password || '';
    return `${p.ip}:${port}:${u}:${pass}`;
});

// Tab kiểm tra qua dòng lệnh (Terminal / Code)
const testTab = ref<'curl' | 'powershell' | 'python' | 'nodejs'>('curl');

const testCommands = computed(() => {
    const ip = props.proxy.ip || '180.93.200.88';
    const port = props.proxy.port || '8080';
    const u = props.proxy.username || 'admin_px01';
    const pass = props.proxy.password || 'adm_pass_888';

    return {
        curl: `curl -x http://${u}:${pass}@${ip}:${port} https://api.ipify.org?format=json`,
        curl_socks5: props.proxy.sock5_port
            ? `curl -x socks5://${props.proxy.sock5_username || u}:${props.proxy.sock5_password || pass}@${ip}:${props.proxy.sock5_port} https://api.ipify.org?format=json`
            : null,
        powershell: `# Kiểm tra Proxy qua Windows PowerShell\n$proxy = "http://${ip}:${port}"\n$cred = [System.Net.NetworkCredential]::new("${u}", "${pass}")\nInvoke-RestMethod -Uri "https://api.ipify.org?format=json" -Proxy $proxy -ProxyCredential $cred`,
        python: `import requests\n\nproxies = {\n    "http": "http://${u}:${pass}@${ip}:${port}",\n    "https": "http://${u}:${pass}@${ip}:${port}",\n}\n\nresp = requests.get("https://api.ipify.org?format=json", proxies=proxies, timeout=10)\nprint("IP thực tế nhận diện:", resp.json())`,
        nodejs: `import axios from 'axios';\nimport { HttpsProxyAgent } from 'https-proxy-agent';\n\nconst agent = new HttpsProxyAgent('http://${u}:${pass}@${ip}:${port}');\nconst response = await axios.get('https://api.ipify.org?format=json', { httpsAgent: agent });\nconsole.log('Kết quả IP:', response.data);`,
    };
});

// Tab hướng dẫn sử dụng
const guideTab = ref<'antidetect' | 'browser' | 'proxifier' | 'mobile'>('antidetect');

// Chu kỳ gia hạn được chọn
const selectedCycle = ref<string>('monthly');
const isSubmittingRenew = ref(false);

const defaultQuotes: Record<string, RenewQuote> = {
    monthly: { cycle: 'monthly', label: '1 Tháng', months: 1, price: props.proxy.renew_price || 15000, list_price: props.proxy.renew_price || 15000, discount_amount: 0 },
};

const activeQuotes = computed<Record<string, RenewQuote>>(() => {
    const raw = props.proxy.renew_quotes;
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && Object.keys(raw).length > 0) {
        return raw;
    }
    if (Array.isArray(raw) && raw.length > 0) {
        const dict: Record<string, RenewQuote> = {};
        for (const item of raw) {
            if (item && item.cycle) dict[item.cycle] = item;
        }
        if (Object.keys(dict).length > 0) return dict;
    }
    return defaultQuotes;
});

const currentSelectedQuote = computed<RenewQuote>(() => {
    return (
        activeQuotes.value[selectedCycle.value] ||
        activeQuotes.value['monthly'] ||
        Object.values(activeQuotes.value)[0] ||
        defaultQuotes.monthly
    );
});

function renewProxy() {
    if (isDeleted.value || isSubmittingRenew.value) return;
    const q = currentSelectedQuote.value;
    const userBalance = user.value?.balance || 0;

    if (userBalance < q.price) {
        toast.error(`Số dư ví hiện tại (${formatVnd(userBalance)}) không đủ thanh toán (${formatVnd(q.price)}). Vui lòng nạp thêm tiền!`);
        return;
    }

    askConfirm({
        title: `Gia hạn Proxy #${props.proxy.id}`,
        message: `Xác nhận gia hạn thêm ${q.label} cho Proxy #${props.proxy.id} với chi phí ${formatVnd(q.price)}?`,
        confirmText: `Gia hạn (${formatVnd(q.price)})`,
        variant: 'primary',
        onConfirm: () => {
            isSubmittingRenew.value = true;
            router.post(
                `/proxy/${props.proxy.id}/renew`,
                { billing_cycle: selectedCycle.value },
                {
                    preserveScroll: true,
                    onFinish: () => {
                        isSubmittingRenew.value = false;
                    },
                }
            );
        },
    });
}

function toggleAutoRenew() {
    if (isDeleted.value) return;
    router.post(
        `/proxy/${props.proxy.id}/auto-renew`,
        { is_auto_renew: !props.proxy.is_auto_renew },
        { preserveScroll: true }
    );
}

function removeProxy() {
    if (isDeleted.value) return;
    askConfirm({
        title: `Hủy Proxy #${props.proxy.id}`,
        message: 'Proxy sẽ tắt tự động gia hạn và chuyển sang mục Đã xóa. Chi phí chu kỳ còn dư sẽ không được hoàn lại.',
        confirmText: 'Xác nhận hủy Proxy',
        variant: 'danger',
        onConfirm: () => router.delete(`/proxy/${props.proxy.id}`),
    });
}
</script>

<template>
    <ClientLayout :title="`Proxy IPv4 #${proxy.id} — LugiaCloud`">
        <Head :title="`Chi tiết Proxy #${proxy.id} — LugiaCloud`" />

        <div class="space-y-6">
            <!-- Top Breadcrumb -->
            <div class="flex items-center justify-between">
                <Link
                    href="/proxy"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-[#0B1437] transition-colors"
                >
                    <ArrowLeft class="w-4 h-4" />
                    <span>Danh sách Proxy IPv4</span>
                </Link>

                <div class="px-2.5 py-1 rounded-md bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold font-mono-num">
                    Mã dịch vụ: <strong class="text-[#0B1437] font-bold">#{{ proxy.id }}</strong>
                </div>
            </div>

            <!-- Header Card: Overview Hero & Clean Horizontal Specs -->
            <div class="surface-card p-6 border border-slate-200/90 shadow-xs rounded-2xl">
                <!-- Top Row: Title, Status, Expiry Pill, Action Buttons -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-xl sm:text-2xl font-black text-[#0B1437] tracking-tight">
                            {{ proxy.product_name }}
                        </h1>
                        <StatusBadge :state="proxy.state" :label="proxy.status" />

                        <span
                            v-if="!isDeleted && proxy.days_until_expiry !== null"
                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-medium border"
                            :class="
                                proxy.days_until_expiry <= 0
                                    ? 'bg-rose-50 text-rose-700 border-rose-200/90'
                                    : proxy.days_until_expiry <= 5
                                      ? 'bg-amber-50 text-amber-800 border-amber-200/90'
                                      : 'bg-slate-100 text-slate-700 border-slate-200/90'
                            "
                        >
                            <Calendar class="w-3.5 h-3.5 text-slate-400" />
                            <span>{{ proxy.days_until_expiry > 0 ? `Còn ${proxy.days_until_expiry} ngày` : 'Đã hết hạn' }}</span>
                        </span>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div v-if="!isDeleted" class="flex items-center gap-2.5 shrink-0">
                        <button
                            type="button"
                            @click="copyText(currentConnectionString, 'quick_top', 'chuỗi kết nối')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-xs cursor-pointer transition active:scale-95"
                        >
                            <component :is="copiedKey === 'quick_top' ? Check : Copy" class="w-3.5 h-3.5 text-[#1D6AE5]" />
                            <span>{{ copiedKey === 'quick_top' ? 'Đã sao chép' : 'Sao chép kết nối' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Divider -->
                <div class="my-5 border-t border-slate-100"></div>

                <!-- Bottom Specs Grid: 5 Clean Columns, Senior SaaS Standard (No Awkward Word Wrap) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-y-4 gap-x-6">
                    <!-- Col 1: Địa chỉ IP & Port -->
                    <div>
                        <div class="text-xs font-bold text-slate-600 uppercase tracking-wider">Địa chỉ IPv4 & Port</div>
                        <div class="mt-1 flex items-center gap-1.5">
                            <span class="font-mono-num text-[15px] font-extrabold text-[#0B1437] truncate">
                                {{ proxy.ip }}:{{ proxy.port }}
                            </span>
                            <button
                                type="button"
                                @click="copyText(`${proxy.ip}:${proxy.port}`, 'hero_ip_port', 'IP:Port')"
                                title="Sao chép IP:Port"
                                class="p-1 rounded text-slate-400 hover:text-[#1D6AE5] hover:bg-slate-100 cursor-pointer transition shrink-0"
                            >
                                <component :is="copiedKey === 'hero_ip_port' ? Check : Copy" class="w-3.5 h-3.5 text-slate-400" />
                            </button>
                        </div>
                        <div class="text-xs text-slate-600 font-medium mt-0.5 truncate">
                            Port SOCKS5: <strong class="text-slate-800 font-bold font-mono">{{ proxy.sock5_port || '—' }}</strong>
                        </div>
                    </div>

                    <!-- Col 2: Giao thức mạng -->
                    <div>
                        <div class="text-xs font-bold text-slate-600 uppercase tracking-wider">Giao thức mạng</div>
                        <div class="mt-1 text-[15px] font-extrabold text-[#0B1437] whitespace-nowrap">
                            {{ proxy.protocol }}
                        </div>
                        <div class="text-xs text-slate-600 font-medium mt-0.5">Chuyển đổi HTTP ↔ SOCKS5</div>
                    </div>

                    <!-- Col 3: Băng thông & Tốc độ -->
                    <div>
                        <div class="text-xs font-bold text-slate-600 uppercase tracking-wider">Băng thông & Mạng</div>
                        <div class="mt-1 text-[15px] font-extrabold text-emerald-600 whitespace-nowrap">
                            Không giới hạn
                        </div>
                        <div class="text-xs text-slate-600 font-medium mt-0.5">Cổng Uplink 1 Gbps sạch</div>
                    </div>

                    <!-- Col 4: Hạ tầng & Vị trí -->
                    <div>
                        <div class="text-xs font-bold text-slate-600 uppercase tracking-wider">Hạ tầng & Vị trí</div>
                        <div class="mt-1 text-[15px] font-extrabold text-[#0B1437] whitespace-nowrap">
                            FPT Datacenter HCM
                        </div>
                        <div class="text-xs text-emerald-600 font-medium mt-0.5 flex items-center gap-1">
                            <Zap class="w-3 h-3" /> Ping &lt; 15ms trong nước
                        </div>
                    </div>

                    <!-- Col 5: Thời hạn -->
                    <div class="col-span-2 sm:col-span-1">
                        <div class="text-xs font-bold text-slate-600 uppercase tracking-wider">Ngày hết hạn</div>
                        <div class="mt-1 font-mono-num text-[15px] font-extrabold text-[#0B1437] whitespace-nowrap">
                            {{ proxy.expires_at || '—' }}
                        </div>
                        <div class="text-xs text-slate-600 font-medium mt-0.5">
                            Khởi tạo: <strong class="text-slate-800 font-bold">{{ proxy.created_at || '—' }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asymmetric 2-Column Console Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left Column (8 cols): Credentials, Formats, CMD Test, Usage Guides -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Card 1: Chuỗi kết nối nhanh (Multi-format Quick Copy) -->
                    <div class="surface-card p-6 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-bold text-[#0B1437] flex items-center gap-2">
                                    <Zap class="w-4 h-4 text-[#1D6AE5]" />
                                    <span>Chuỗi kết nối nhanh</span>
                                </h2>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">
                                    Hỗ trợ dán 1-click vào mọi phần mềm Antidetect, Tool MMO, hoặc dùng trong Script/Code.
                                </p>
                            </div>

                            <!-- Tabs chọn định dạng -->
                            <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-semibold">
                                <button
                                    type="button"
                                    @click="formatType = 'standard'"
                                    class="px-3 py-1.5 rounded-md transition cursor-pointer"
                                    :class="formatType === 'standard' ? 'bg-white text-[#0B1437] shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                >
                                    IP:Port:User:Pass
                                </button>
                                <button
                                    type="button"
                                    @click="formatType = 'url'"
                                    class="px-3 py-1.5 rounded-md transition cursor-pointer"
                                    :class="formatType === 'url' ? 'bg-white text-[#0B1437] shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                >
                                    URL (http://)
                                </button>
                                <button
                                    v-if="proxy.sock5_port"
                                    type="button"
                                    @click="formatType = 'socks5'"
                                    class="px-3 py-1.5 rounded-md transition cursor-pointer"
                                    :class="formatType === 'socks5' ? 'bg-white text-[#0B1437] shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 font-medium'"
                                >
                                    SOCKS5 (socks5://)
                                </button>
                            </div>
                        </div>

                        <!-- Dark Code Display -->
                        <div class="p-4 rounded-xl bg-[#0B1437] text-white flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 border border-slate-800 shadow-inner">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs uppercase font-bold tracking-wider text-slate-300 mb-1 flex items-center gap-1.5">
                                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span>Định dạng: {{ formatType === 'standard' ? 'Chuẩn Tool MMO (IP:Port:User:Pass)' : formatType.toUpperCase() }}</span>
                                </div>
                                <div class="font-mono text-xs sm:text-sm text-sky-300 break-all select-all font-semibold">
                                    {{ currentConnectionString }}
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="copyText(currentConnectionString, 'main_str', 'chuỗi kết nối')"
                                class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold shrink-0 shadow-sm cursor-pointer transition active:scale-95"
                            >
                                <component :is="copiedKey === 'main_str' ? Check : Copy" class="w-4 h-4" />
                                <span>{{ copiedKey === 'main_str' ? 'Đã sao chép!' : 'Sao chép' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Card 2: Thông số kết nối chi tiết (Có nút Copy icon cho từng trường) -->
                    <div class="surface-card p-6 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-bold text-[#0B1437] flex items-center gap-2">
                                    <KeyRound class="w-4 h-4 text-[#1D6AE5]" />
                                    <span>Thông tin xác thực &amp; Cổng kết nối</span>
                                </h2>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">
                                    Từng trường độc lập, nhấn vào biểu tượng để sao chép nhanh vào clipboard.
                                </p>
                            </div>

                            <div v-if="!isDeleted" class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    @click="openEditCredsModal"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold cursor-pointer transition"
                                >
                                    <KeyRound class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                    <span>Đổi User / Pass</span>
                                </button>

                                <button
                                    type="button"
                                    :disabled="isChangingType"
                                    @click="confirmChangeProtocol"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 disabled:opacity-50 text-slate-700 text-xs font-bold cursor-pointer transition"
                                >
                                    <Loader2 v-if="isChangingType" class="w-3.5 h-3.5 animate-spin text-[#1D6AE5]" />
                                    <ArrowLeftRight v-else class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                    <span>Đổi sang {{ targetProtocol }}</span>
                                </button>

                                <button
                                    v-if="isResidential"
                                    type="button"
                                    :disabled="isSyncingIp"
                                    @click="confirmSyncIp"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 disabled:opacity-50 text-emerald-800 text-xs font-bold cursor-pointer transition"
                                >
                                    <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncingIp }" />
                                    <span>Đổi IP mới</span>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                            <!-- IPv4 -->
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 hover:border-blue-300 transition group flex flex-col justify-between">
                                <div class="flex items-center justify-between text-slate-600 font-semibold text-xs">
                                    <span class="flex items-center gap-1.5 font-bold">
                                        <Globe class="w-3.5 h-3.5 text-slate-500 group-hover:text-[#1D6AE5]" />
                                        <span>Địa chỉ IPv4</span>
                                    </span>
                                    <button
                                        type="button"
                                        @click="copyText(proxy.ip, 'ip', 'Địa chỉ IPv4')"
                                        title="Sao chép IP"
                                        class="p-1 rounded text-slate-400 hover:text-[#1D6AE5] hover:bg-white cursor-pointer transition"
                                    >
                                        <component :is="copiedKey === 'ip' ? Check : Copy" class="w-3.5 h-3.5" :class="{ 'text-emerald-600': copiedKey === 'ip' }" />
                                    </button>
                                </div>
                                <div class="font-mono text-sm font-bold text-[#0B1437] mt-2 select-all">
                                    {{ proxy.ip || 'Đang cấp phát...' }}
                                </div>
                            </div>

                            <!-- Port HTTP / HTTPS -->
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 hover:border-blue-300 transition group flex flex-col justify-between">
                                <div class="flex items-center justify-between text-slate-600 font-semibold text-xs">
                                    <span class="flex items-center gap-1.5 font-bold">
                                        <Network class="w-3.5 h-3.5 text-slate-500 group-hover:text-[#1D6AE5]" />
                                        <span>Cổng HTTP / HTTPS</span>
                                    </span>
                                    <button
                                        type="button"
                                        @click="copyText(proxy.http_port || proxy.port, 'http_port', 'Cổng HTTP')"
                                        title="Sao chép Cổng HTTP"
                                        class="p-1 rounded text-slate-400 hover:text-[#1D6AE5] hover:bg-white cursor-pointer transition"
                                    >
                                        <component :is="copiedKey === 'http_port' ? Check : Copy" class="w-3.5 h-3.5" :class="{ 'text-emerald-600': copiedKey === 'http_port' }" />
                                    </button>
                                </div>
                                <div class="font-mono text-sm font-bold text-[#0B1437] mt-2 select-all">
                                    {{ proxy.http_port || proxy.port || '—' }}
                                </div>
                            </div>

                            <!-- Port SOCKS5 -->
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 hover:border-blue-300 transition group flex flex-col justify-between">
                                <div class="flex items-center justify-between text-slate-600 font-semibold text-xs">
                                    <span class="flex items-center gap-1.5 font-bold">
                                        <Zap class="w-3.5 h-3.5 text-slate-500 group-hover:text-amber-500" />
                                        <span>Cổng SOCKS5</span>
                                    </span>
                                    <button
                                        v-if="proxy.sock5_port"
                                        type="button"
                                        @click="copyText(proxy.sock5_port, 'socks5_port', 'Cổng SOCKS5')"
                                        title="Sao chép Cổng SOCKS5"
                                        class="p-1 rounded text-slate-400 hover:text-[#1D6AE5] hover:bg-white cursor-pointer transition"
                                    >
                                        <component :is="copiedKey === 'socks5_port' ? Check : Copy" class="w-3.5 h-3.5" :class="{ 'text-emerald-600': copiedKey === 'socks5_port' }" />
                                    </button>
                                </div>
                                <div class="font-mono text-sm font-bold text-[#0B1437] mt-2 select-all">
                                    {{ proxy.sock5_port || 'Hỗ trợ tự động qua HTTP' }}
                                </div>
                            </div>

                            <!-- Username -->
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 hover:border-blue-300 transition group flex flex-col justify-between">
                                <div class="flex items-center justify-between text-slate-600 font-semibold text-xs">
                                    <span class="flex items-center gap-1.5 font-bold">
                                        <User class="w-3.5 h-3.5 text-slate-500 group-hover:text-[#1D6AE5]" />
                                        <span>Tài khoản (Username)</span>
                                    </span>
                                    <button
                                        type="button"
                                        @click="copyText(proxy.username, 'username', 'Tên đăng nhập')"
                                        title="Sao chép Username"
                                        class="p-1 rounded text-slate-400 hover:text-[#1D6AE5] hover:bg-white cursor-pointer transition"
                                    >
                                        <component :is="copiedKey === 'username' ? Check : Copy" class="w-3.5 h-3.5" :class="{ 'text-emerald-600': copiedKey === 'username' }" />
                                    </button>
                                </div>
                                <div class="font-mono text-sm font-bold text-[#0B1437] mt-2 select-all truncate">
                                    {{ proxy.username || '—' }}
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 hover:border-blue-300 transition group flex flex-col justify-between sm:col-span-2">
                                <div class="flex items-center justify-between text-slate-600 font-semibold text-xs">
                                    <span class="flex items-center gap-1.5 font-bold">
                                        <KeyRound class="w-3.5 h-3.5 text-slate-500 group-hover:text-[#1D6AE5]" />
                                        <span>Mật khẩu xác thực (Password)</span>
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="p-1 rounded text-slate-400 hover:text-slate-700 hover:bg-white cursor-pointer transition"
                                            title="Hiện/Ẩn mật khẩu"
                                        >
                                            <component :is="showPassword ? EyeOff : Eye" class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="copyText(proxy.password, 'password', 'Mật khẩu')"
                                            title="Sao chép Mật khẩu"
                                            class="p-1 rounded text-slate-400 hover:text-[#1D6AE5] hover:bg-white cursor-pointer transition"
                                        >
                                            <component :is="copiedKey === 'password' ? Check : Copy" class="w-3.5 h-3.5" :class="{ 'text-emerald-600': copiedKey === 'password' }" />
                                        </button>
                                    </div>
                                </div>
                                <div class="font-mono text-sm font-bold text-[#0B1437] mt-2 select-all tracking-wider">
                                    {{ showPassword ? (proxy.password || '—') : '••••••••••••••••' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Kiểm tra hoạt động qua dòng lệnh (CMD / Terminal / Script) -->
                    <div class="surface-card p-6 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-bold text-[#0B1437] flex items-center gap-2">
                                    <Terminal class="w-4 h-4 text-[#1D6AE5]" />
                                    <span>Kiểm tra kết nối qua dòng lệnh (Live Test)</span>
                                </h2>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">
                                    Copy lệnh dưới đây và dán thẳng vào CMD / PowerShell / Terminal để kiểm tra xem Proxy hoạt động hay không.
                                </p>
                            </div>

                            <!-- Tabs chọn ngôn ngữ / terminal -->
                            <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-semibold">
                                <button
                                    type="button"
                                    @click="testTab = 'curl'"
                                    class="px-2.5 py-1.5 rounded-md transition cursor-pointer"
                                    :class="testTab === 'curl' ? 'bg-white text-[#0B1437] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    cURL (CMD/Bash)
                                </button>
                                <button
                                    type="button"
                                    @click="testTab = 'powershell'"
                                    class="px-2.5 py-1.5 rounded-md transition cursor-pointer"
                                    :class="testTab === 'powershell' ? 'bg-white text-[#0B1437] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    PowerShell
                                </button>
                                <button
                                    type="button"
                                    @click="testTab = 'python'"
                                    class="px-2.5 py-1.5 rounded-md transition cursor-pointer"
                                    :class="testTab === 'python' ? 'bg-white text-[#0B1437] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    Python
                                </button>
                                <button
                                    type="button"
                                    @click="testTab = 'nodejs'"
                                    class="px-2.5 py-1.5 rounded-md transition cursor-pointer"
                                    :class="testTab === 'nodejs' ? 'bg-white text-[#0B1437] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    Node.js
                                </button>
                            </div>
                        </div>

                        <!-- Snippet Block -->
                        <div class="relative rounded-xl bg-[#090E23] border border-slate-800 p-4 text-white overflow-hidden shadow-inner">
                            <div class="flex items-center justify-between text-xs text-slate-400 pb-2.5 border-b border-slate-800/80 mb-3">
                                <span class="font-mono text-[11px] text-sky-400">
                                    {{ testTab === 'curl' ? 'Terminal / Command Prompt (CMD)' : testTab === 'powershell' ? 'Windows PowerShell' : testTab === 'python' ? 'Python 3 (requests)' : 'Node.js (ES Modules)' }}
                                </span>
                                <button
                                    type="button"
                                    @click="copyText(testCommands[testTab], 'cmd_snippet', 'lệnh kiểm tra')"
                                    class="inline-flex items-center gap-1.5 text-xs text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 px-2.5 py-1 rounded cursor-pointer transition"
                                >
                                    <component :is="copiedKey === 'cmd_snippet' ? Check : Copy" class="w-3.5 h-3.5 text-sky-400" />
                                    <span>{{ copiedKey === 'cmd_snippet' ? 'Đã copy lệnh' : 'Sao chép lệnh' }}</span>
                                </button>
                            </div>

                            <pre class="font-mono text-xs sm:text-[13px] leading-relaxed text-sky-200 overflow-x-auto whitespace-pre-wrap select-all">{{ testCommands[testTab] }}</pre>

                            <!-- Kết quả mong đợi -->
                            <div class="mt-4 pt-3 border-t border-slate-800/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                                <div class="flex items-center gap-2 text-slate-300 font-medium">
                                    <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                                    <span>Kết quả trả về nếu Proxy hoạt động:</span>
                                </div>
                                <code class="px-2.5 py-1 rounded bg-emerald-950/60 border border-emerald-800/60 text-emerald-300 font-mono text-xs font-bold">
                                    {"ip": "{{ proxy.ip || '180.93.200.88' }}"}
                                </code>
                            </div>
                        </div>
                    </div>

                    <!-- Card 4: Cẩm nang hướng dẫn sử dụng chi tiết (Setup Guides) -->
                    <div class="surface-card p-6 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                        <div>
                            <h2 class="text-base font-bold text-[#0B1437] flex items-center gap-2">
                                <HelpCircle class="w-4 h-4 text-[#1D6AE5]" />
                                <span>Cẩm nang cách sử dụng Proxy chi tiết</span>
                            </h2>
                            <p class="text-xs text-slate-600 font-medium mt-0.5">
                                Hướng dẫn từng bước thiết lập trên các công cụ, phần mềm phổ biến nhất.
                            </p>
                        </div>

                        <!-- Guide Tabs -->
                        <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
                            <button
                                type="button"
                                @click="guideTab = 'antidetect'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                                :class="guideTab === 'antidetect' ? 'bg-[#0B1437] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            >
                                <Laptop class="w-3.5 h-3.5" />
                                <span>Antidetect Browser (Gologin, AdsPower...)</span>
                            </button>

                            <button
                                type="button"
                                @click="guideTab = 'browser'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                                :class="guideTab === 'browser' ? 'bg-[#0B1437] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            >
                                <Globe class="w-3.5 h-3.5" />
                                <span>Trình duyệt Chrome / Cốc Cốc</span>
                            </button>

                            <button
                                type="button"
                                @click="guideTab = 'proxifier'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                                :class="guideTab === 'proxifier' ? 'bg-[#0B1437] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            >
                                <Network class="w-3.5 h-3.5" />
                                <span>Proxifier (Toàn máy tính)</span>
                            </button>

                            <button
                                type="button"
                                @click="guideTab = 'mobile'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                                :class="guideTab === 'mobile' ? 'bg-[#0B1437] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            >
                                <Smartphone class="w-3.5 h-3.5" />
                                <span>Điện thoại & Giả lập Android</span>
                            </button>
                        </div>

                        <!-- Guide Content: Antidetect -->
                        <div v-if="guideTab === 'antidetect'" class="space-y-3 text-xs text-slate-600 leading-relaxed">
                            <div class="p-3.5 rounded-xl bg-blue-50/70 border border-blue-200/80 text-blue-900 font-medium">
                                Áp dụng cho: <strong>Gologin, AdsPower, Hidemyacc, Genlogin, MoreLogin, Undetectable</strong>...
                            </div>
                            <ol class="list-decimal list-inside space-y-2 text-slate-700 font-normal">
                                <li>Mở hồ sơ Profile cần gán Proxy > Chọn mục <strong>Proxy</strong>.</li>
                                <li>Chọn loại giao thức: <strong>HTTP</strong> hoặc <strong>SOCKS5</strong>.</li>
                                <li>
                                    Tại ô <strong>Quick Import / Dán chuỗi</strong>: Dán trực tiếp chuỗi
                                    <code class="px-1.5 py-0.5 rounded bg-slate-100 font-mono text-[#0B1437] font-semibold">{{ currentConnectionString }}</code>.
                                    Phần mềm sẽ tự động tách thành IP, Cổng, User và Password.
                                </li>
                                <li>Bấm nút <strong>Kiểm tra Proxy (Check Proxy)</strong>: Hệ thống sẽ hiển thị kết quả IP Việt Nam (VN), múi giờ Asia/Ho_Chi_Minh (+07:00).</li>
                                <li>Khởi chạy Profile và duyệt web bình thường.</li>
                            </ol>
                        </div>

                        <!-- Guide Content: Browser Extension -->
                        <div v-if="guideTab === 'browser'" class="space-y-3 text-xs text-slate-600 leading-relaxed">
                            <div class="p-3.5 rounded-xl bg-blue-50/70 border border-blue-200/80 text-blue-900 font-medium">
                                Sử dụng Extension <strong>Proxy SwitchyOmega</strong> hoặc <strong>FoxyProxy</strong> trên Google Chrome, Brave, MS Edge, Cốc Cốc.
                            </div>
                            <ol class="list-decimal list-inside space-y-2 text-slate-700">
                                <li>Cài đặt extension <strong>Proxy SwitchyOmega</strong> từ Chrome Web Store.</li>
                                <li>Tạo Profile mới (ví dụ đặt tên: <em>LugiaCloud</em>) với giao thức <strong>HTTP</strong>.</li>
                                <li>Điền <strong>Server:</strong> <code class="font-mono font-bold">{{ proxy.ip || '180.93.200.88' }}</code>, <strong>Port:</strong> <code class="font-mono font-bold">{{ proxy.port || '8080' }}</code>.</li>
                                <li>Bấm vào biểu tượng ổ khóa bên cạnh Port > Điền <strong>Username</strong> và <strong>Password</strong> của Proxy > Bấm <em>Save changes</em>.</li>
                                <li>Bấm <strong>Apply changes</strong>, sau đó chọn Profile <em>LugiaCloud</em> trên thanh công cụ trình duyệt để bắt đầu dùng.</li>
                            </ol>
                        </div>

                        <!-- Guide Content: Proxifier -->
                        <div v-if="guideTab === 'proxifier'" class="space-y-3 text-xs text-slate-600 leading-relaxed">
                            <div class="p-3.5 rounded-xl bg-blue-50/70 border border-blue-200/80 text-blue-900 font-medium">
                                Dùng để ép toàn bộ ứng dụng trên Windows/Mac hoặc từng phần mềm cụ thể (Telegram, Chrome, LDPlayer) chạy qua Proxy.
                            </div>
                            <ol class="list-decimal list-inside space-y-2 text-slate-700">
                                <li>Mở <strong>Proxifier</strong> > Menu <strong>Profile</strong> > Chọn <strong>Proxy Servers...</strong> > Bấm <strong>Add</strong>.</li>
                                <li>Nhập <strong>Address:</strong> <code class="font-mono font-bold">{{ proxy.ip || '180.93.200.88' }}</code>, <strong>Port:</strong> <code class="font-mono font-bold">{{ proxy.port || '8080' }}</code>, Protocol: <strong>HTTPS</strong> (hoặc SOCKS Version 5).</li>
                                <li>Tích chọn <strong>Enable Authentication</strong> > Nhập Username và Password của Proxy.</li>
                                <li>Bấm <strong>Check</strong> để kiểm tra kết nối (mục Testing chuyển sang màu xanh <em>Ready to work</em>).</li>
                                <li>Vào <strong>Proxification Rules</strong> để chỉ định app nào được phép đi qua Proxy.</li>
                            </ol>
                        </div>

                        <!-- Guide Content: Mobile / Android Emulator -->
                        <div v-if="guideTab === 'mobile'" class="space-y-3 text-xs text-slate-600 leading-relaxed">
                            <div class="p-3.5 rounded-xl bg-blue-50/70 border border-blue-200/80 text-blue-900 font-medium">
                                Áp dụng cho thiết bị <strong>Android, iOS, LDPlayer, NoxPlayer, Memu, Phone Farm</strong>.
                            </div>
                            <ol class="list-decimal list-inside space-y-2 text-slate-700">
                                <li>Mở <strong>Cài đặt (Settings)</strong> > <strong>WiFi</strong> > Nhấn giữ tên WiFi đang kết nối > <strong>Sửa đổi cấu hình mạng</strong>.</li>
                                <li>Chọn <strong>Proxy: Thủ công (Manual)</strong>.</li>
                                <li>Nhập <strong>Tên máy chủ proxy:</strong> <code class="font-mono font-bold">{{ proxy.ip || '180.93.200.88' }}</code>, <strong>Cổng proxy:</strong> <code class="font-mono font-bold">{{ proxy.port || '8080' }}</code> > Lưu lại.</li>
                                <li>Khi mở trình duyệt trên điện thoại lần đầu, hệ thống sẽ hiện popup yêu cầu điền Username & Password của bạn.</li>
                                <li><em>Lưu ý:</em> Đối với các tool tự động chạy trên giả lập hoặc ứng dụng nền, khuyến nghị cài app <strong>Super Proxy</strong> hoặc <strong>Postern</strong> để tự động xác thực ngầm không cần popup.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Right Column (4 cols): Renewal Box, Auto-renew Switch, Quality Badge, Danger Zone -->
                <div class="lg:col-span-4 space-y-6">
                    <!-- Thông báo khi Proxy đã hủy -->
                    <div v-if="isDeleted" class="surface-card p-6 border border-slate-200 bg-slate-50 rounded-2xl space-y-3">
                        <div class="flex items-center gap-2 text-slate-700">
                            <AlertCircle class="w-4 h-4 text-rose-600 shrink-0" />
                            <h3 class="text-sm font-extrabold text-[#0B1437]">Dịch vụ đã bị hủy</h3>
                        </div>
                        <p class="text-xs text-slate-600 font-medium leading-relaxed">
                            Proxy này đã được chuyển sang mục <strong>Đã xóa</strong> và tắt tự động gia hạn. Các thao tác gia hạn hoặc bật tự động gia hạn đã bị khóa.
                        </p>
                        <Link
                            href="/proxy/store"
                            class="w-full py-2.5 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold transition flex items-center justify-center gap-1.5"
                        >
                            <span>Đăng ký Proxy mới</span>
                        </Link>
                    </div>

                    <!-- Card 1: Hộp Gia hạn dịch vụ (Renewal Console) -->
                    <div v-if="!isDeleted" class="surface-card p-6 border border-slate-200/90 shadow-xs rounded-2xl space-y-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-extrabold text-[#0B1437] uppercase tracking-wider flex items-center gap-2">
                                <Calendar class="w-4 h-4 text-[#1D6AE5]" />
                                <span>Gia hạn dịch vụ</span>
                            </h3>
                            <span class="text-xs font-medium text-slate-500">
                                Số dư: <strong class="text-emerald-600 font-mono-num font-bold">{{ formatVnd(user?.balance || 0) }}</strong>
                            </span>
                        </div>

                        <!-- Chọn chu kỳ gia hạn -->
                        <div class="space-y-2">
                            <div class="text-xs font-semibold text-slate-600">Chọn chu kỳ gia hạn thêm:</div>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="(q, key) in activeQuotes"
                                    :key="key"
                                    type="button"
                                    @click="selectedCycle = String(key)"
                                    class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                                    :class="
                                        selectedCycle === key
                                            ? 'border-[#1D6AE5] bg-blue-50/50 ring-1 ring-[#1D6AE5]'
                                            : 'border-slate-200 bg-white hover:border-slate-300'
                                    "
                                >
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-xs font-bold text-[#0B1437]">{{ q.label }}</span>
                                        <span
                                            v-if="(q.discount_percent || 0) > 0"
                                            class="px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700 text-[10px] font-extrabold"
                                        >
                                            -{{ q.discount_percent }}%
                                        </span>
                                    </div>
                                    <div class="font-mono-num text-xs font-extrabold text-[#1D6AE5] mt-0.5">
                                        {{ formatVnd(q.price) }}
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Tóm tắt chi phí gia hạn -->
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs">
                            <div class="flex justify-between text-slate-600">
                                <span>Thời hạn hiện tại:</span>
                                <strong class="text-slate-900 font-mono-num">{{ proxy.expires_at || '—' }}</strong>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Gia hạn thêm:</span>
                                <strong class="text-slate-900 font-semibold">{{ currentSelectedQuote.label }}</strong>
                            </div>
                            <div v-if="currentSelectedQuote.discount_amount > 0" class="flex justify-between text-emerald-600 font-semibold">
                                <span>Ưu đãi chu kỳ:</span>
                                <span>-{{ formatVnd(currentSelectedQuote.discount_amount) }}</span>
                            </div>
                            <div class="pt-2 border-t border-slate-200 flex justify-between items-center text-sm font-extrabold text-[#0B1437]">
                                <span>Cần thanh toán:</span>
                                <span class="text-base text-[#1D6AE5] font-mono-num">{{ formatVnd(currentSelectedQuote.price) }}</span>
                            </div>
                        </div>

                        <!-- Nút Gia hạn -->
                        <button
                            type="button"
                            @click="renewProxy"
                            :disabled="isSubmittingRenew"
                            class="w-full py-3 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] disabled:bg-slate-300 text-white text-xs font-bold uppercase tracking-wider shadow-md hover:shadow-lg transition cursor-pointer flex items-center justify-center gap-2"
                        >
                            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isSubmittingRenew }" />
                            <span>{{ isSubmittingRenew ? 'Đang xử lý...' : `Gia hạn ngay (${formatVnd(currentSelectedQuote.price)})` }}</span>
                        </button>
                    </div>

                    <!-- Card 2: Cấu hình tự động gia hạn (Auto-Renew Switch) -->
                    <div v-if="!isDeleted" class="surface-card p-6 border border-slate-200/90 shadow-xs rounded-2xl space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Zap class="w-4 h-4 text-amber-500" />
                                <h3 class="text-sm font-bold text-[#0B1437]">Tự động gia hạn</h3>
                            </div>
                            <!-- Switch button -->
                            <button
                                type="button"
                                @click="toggleAutoRenew"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden"
                                :class="proxy.is_auto_renew ? 'bg-[#1D6AE5]' : 'bg-slate-300'"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                    :class="proxy.is_auto_renew ? 'translate-x-5' : 'translate-x-0'"
                                />
                            </button>
                        </div>
                        <p class="text-xs text-slate-600 font-medium leading-relaxed">
                            Khi bật tính năng này, hệ thống sẽ tự động trừ số dư ví <strong>trước thời điểm hết hạn 01 ngày (24 giờ)</strong> để duy trì Proxy liên tục, tránh bị thu hồi IP. Nếu ví chưa đủ tiền, hệ thống sẽ gửi email cảnh báo sớm để bạn nạp kịp thời.
                        </p>
                    </div>

                    <!-- Card 3: Thông tin cam kết & Chất lượng LugiaCloud -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-3 text-xs">
                        <h4 class="font-bold text-[#0B1437] flex items-center gap-2">
                            <ShieldCheck class="w-4 h-4 text-emerald-600" />
                            <span>Cam kết chất lượng Proxy LugiaCloud</span>
                        </h4>
                        <ul class="space-y-2 text-slate-600">
                            <li class="flex items-start gap-2">
                                <Check class="w-3.5 h-3.5 text-emerald-600 mt-0.5 shrink-0" />
                                <span><strong>100% IP sạch tại Việt Nam</strong>: Đặt trực tiếp tại cụm máy chủ FPT Datacenter HCM.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <Check class="w-3.5 h-3.5 text-emerald-600 mt-0.5 shrink-0" />
                                <span><strong>Băng thông không giới hạn</strong>: Thoải mái lướt web, nuôi nick, crawl data 24/7.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <Check class="w-3.5 h-3.5 text-emerald-600 mt-0.5 shrink-0" />
                                <span><strong>Hỗ trợ đổi mới trong 24h</strong>: Đổi IP mới ngay nếu phát hiện lỗi kết nối upstream.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Card 4: Xóa Proxy (Vùng nguy hiểm) -->
                    <div v-if="!isDeleted" class="surface-card p-5 border border-rose-200 bg-rose-50/40 rounded-2xl space-y-3">
                        <div class="flex items-center gap-2 text-rose-700">
                            <AlertCircle class="w-4 h-4" />
                            <h4 class="text-xs font-bold uppercase tracking-wider">Hủy dịch vụ</h4>
                        </div>
                        <p class="text-xs text-slate-600 font-medium leading-relaxed">
                            Hủy Proxy này, tắt tự động gia hạn và chuyển sang mục <strong>Đã xóa</strong>. Chi phí chu kỳ hiện tại sẽ không được hoàn lại.
                        </p>
                        <button
                            type="button"
                            @click="removeProxy"
                            class="w-full py-2.5 rounded-xl border border-rose-300 hover:border-rose-400 bg-white hover:bg-rose-50 text-rose-600 hover:text-rose-700 text-xs font-bold transition cursor-pointer flex items-center justify-center gap-1.5"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>Hủy &amp; chuyển sang Đã xóa</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Đổi Username & Password Proxy -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-120 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showEditCredsModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55 backdrop-blur-xs"
                    role="dialog"
                    aria-modal="true"
                    @click.self="!credsForm.processing && (showEditCredsModal = false)"
                >
                    <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/80">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-blue-50 border border-blue-200/80 text-[#1D6AE5] flex items-center justify-center">
                                    <KeyRound class="w-4 h-4" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-extrabold text-[#0B1437]">Đổi tài khoản Proxy #{{ proxy.id }}</h3>
                                    <p class="text-[11px] text-slate-500">Giữ nguyên IP &amp; Port hiện tại</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="!credsForm.processing && (showEditCredsModal = false)"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 cursor-pointer"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <form @submit.prevent="submitEditCreds" class="p-6 space-y-4">
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    @click="generateRandomCreds"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-[#1D6AE5] text-[11px] font-bold cursor-pointer transition"
                                >
                                    <Shuffle class="w-3 h-3" />
                                    <span>Tạo ngẫu nhiên</span>
                                </button>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Username mới</label>
                                <input
                                    v-model="credsForm.username"
                                    type="text"
                                    required
                                    minlength="5"
                                    maxlength="100"
                                    placeholder="Từ 5 – 100 ký tự (chữ cái, số, _)"
                                    class="w-full h-10 px-3.5 rounded-xl border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none text-xs font-mono"
                                />
                                <p v-if="credsForm.errors.username" class="mt-1 text-xs font-semibold text-rose-600">{{ credsForm.errors.username }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Mật khẩu mới</label>
                                <input
                                    v-model="credsForm.password"
                                    type="text"
                                    required
                                    minlength="5"
                                    maxlength="100"
                                    placeholder="Từ 5 – 100 ký tự (chữ cái, số, _)"
                                    class="w-full h-10 px-3.5 rounded-xl border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none text-xs font-mono"
                                />
                                <p v-if="credsForm.errors.password" class="mt-1 text-xs font-semibold text-rose-600">{{ credsForm.errors.password }}</p>
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
                                <button
                                    type="button"
                                    @click="!credsForm.processing && (showEditCredsModal = false)"
                                    class="px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer"
                                >
                                    Hủy bỏ
                                </button>
                                <button
                                    type="submit"
                                    :disabled="credsForm.processing"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] disabled:opacity-50 text-white text-xs font-bold cursor-pointer"
                                >
                                    <Loader2 v-if="credsForm.processing" class="w-3.5 h-3.5 animate-spin" />
                                    <span>{{ credsForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </ClientLayout>
</template>
