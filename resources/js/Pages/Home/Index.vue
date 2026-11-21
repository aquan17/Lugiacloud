<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import OsIcon from '@/Components/OsIcon.vue';
import EventShowcaseBanner from '@/Components/EventShowcaseBanner.vue';
import FlashSaleShowcase from '@/Components/FlashSaleShowcase.vue';
import { formatVnd } from '@/Composables/useFormat';
import { openLoginPrompt, requireAuthOrVisit } from '@/Composables/useLoginPrompt';
import {
    ArrowRight,
    Check,
    Server,
    Globe,
    ShieldCheck,
    Cpu,
    MemoryStick,
    HardDrive,
    Gauge,
    Zap,
    QrCode,
    Terminal,
    Layers,
    Flame,
    Crown,
    Headphones,
    RefreshCw,
    Calendar,
    Filter,
    MapPin,
    KeyRound,
    MessageCircle,
    CheckCircle2,
    Gift,
    AlertCircle,
} from 'lucide-vue-next';

const props = defineProps<{
    plans: Array<any>;
    proxyProducts: Array<any>;
    billingCycles: Record<string, { months: number; label: string; discount_percent: number }>;
    regions: Array<{ code: string; name: string; datacenter: string; ping: string }>;
    availablePlanSlugs: string[];
    flashSales?: Array<any>;
    huntVouchers?: Array<any>;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const isVpsOutOfStock = computed(() => Boolean((page.props.system as any)?.vps_out_of_stock));
const activeTab = ref<'vps' | 'proxy'>('vps');
const selectedCycle = ref<string>('monthly');
const proxyCategoryFilter = ref<'all' | 'residential' | 'datacenter'>('all');

function goOrPromptLogin(targetUrl: string, actionLabel: string) {
    requireAuthOrVisit(targetUrl, actionLabel, user.value);
}

function openWelcomeOfferPrompt() {
    const offer = welcomeOffer.value;
    const cap = offer?.max_discount > 0 ? ` (tối đa ${formatVnd(offer.max_discount)})` : '';
    openLoginPrompt({
        title: 'Nhận ưu đãi thành viên mới',
        description: 'Tạo tài khoản mới để nhận tự động mã giảm giá vào Ví Voucher, hoặc đăng nhập nếu bạn đã có tài khoản LugiaCloud.',
        actionLabel: `Khách mới: Giảm ${offer?.percent || 10}% đơn đầu${cap}`,
        redirectUrl: '/store',
    });
}

function handleTabEvent(e: Event) {
    const tab = (e as CustomEvent)?.detail;
    if (tab === 'vps' || tab === 'proxy') {
        activeTab.value = tab;
    }
}

onMounted(() => {
    const qTab = new URLSearchParams(window.location.search).get('tab');
    if (qTab === 'proxy' || qTab === 'vps') {
        activeTab.value = qTab;
    }
    window.addEventListener('lugia:tab', handleTabEvent);
});

onUnmounted(() => {
    window.removeEventListener('lugia:tab', handleTabEvent);
});

const heroBanners = computed(() => (page.props.promotions as any)?.banners?.hero_showcase || []);
const activeCampaigns = computed(() => (page.props.promotions as any)?.campaigns || []);
const primaryCampaign = computed(() => activeCampaigns.value[0] || null);
const welcomeOffer = computed(() => (page.props.promotions as any)?.welcome_offer);

const displayCycles = computed(() => {
    const keys = ['monthly', 'quarterly', 'semi_annually', 'annually'];
    return keys
        .filter((k) => props.billingCycles && props.billingCycles[k])
        .map((k) => ({ key: k, ...props.billingCycles[k] }));
});

const filteredProxies = computed(() => {
    if (proxyCategoryFilter.value === 'all') return props.proxyProducts;
    return props.proxyProducts.filter((p) => p.category === proxyCategoryFilter.value);
});

function planQuote(plan: any) {
    return plan.cycle_quotes?.[selectedCycle.value] || {
        months: 1,
        list_price: plan.list_price_per_month || plan.price_per_month,
        final_price: plan.price_per_month,
        monthly_equivalent: plan.price_per_month,
        discount_amount: 0,
        campaign: plan.campaign || null,
    };
}

function planIcon(index: number, isFeatured: boolean) {
    if (isFeatured) return Flame;
    const icons = [Server, Layers, Cpu, Flame, Zap, Crown];
    return icons[index % icons.length];
}

function formatBandwidth(plan: any): string {
    const speed = plan.network_out_mbps >= 1000 ? '1 Gbps' : `${plan.network_out_mbps} Mbps`;
    const bw = !plan.transfer_tb || plan.transfer_tb === 'Unlimited' ? 'Không giới hạn' : `${plan.transfer_tb} TB`;
    return `${speed} · ${bw}`;
}

const osStack = [
    { key: 'windows', name: 'Windows Server 2012–2022' },
    { key: 'windows', name: 'Windows 10 64-bit' },
    { key: 'ubuntu', name: 'Ubuntu 20.04 / 22.04' },
    { key: 'debian', name: 'Debian 11' },
    { key: 'centos', name: 'CentOS 7' },
    { key: 'almalinux', name: 'AlmaLinux 8' },
];

const infraSpecs = [
    {
        icon: Cpu,
        iconBg: 'bg-blue-50 text-[#1D6AE5] border-blue-200/80',
        label: 'CPU máy chủ',
        title: 'Intel Xeon Gold & Platinum',
        desc: 'Xeon Gold 6133 / 6138 & Platinum 8171M chuyên tải đa luồng 24/7.',
    },
    {
        icon: MapPin,
        iconBg: 'bg-emerald-50 text-emerald-600 border-emerald-200/80',
        label: 'Trung tâm dữ liệu',
        title: 'FPT Datacenter (TP.HCM)',
        desc: 'Độ trễ thấp, ping nội địa dưới 15ms, đường truyền ổn định.',
    },
    {
        icon: HardDrive,
        iconBg: 'bg-indigo-50 text-indigo-600 border-indigo-200/80',
        label: 'Lưu trữ',
        title: '100% Ổ cứng SSD chuyên dụng',
        desc: 'Tốc độ đọc ghi nhanh, truy xuất dữ liệu mượt mà và bền bỉ.',
    },
    {
        icon: KeyRound,
        iconBg: 'bg-amber-50 text-amber-600 border-amber-200/80',
        label: 'Quyền quản trị',
        title: 'Full Root / Admin & Port',
        desc: 'Toàn quyền quản trị hệ điều hành, hỗ trợ mở đầy đủ TCP/UDP Port.',
    },
    {
        icon: ShieldCheck,
        iconBg: 'bg-sky-50 text-sky-600 border-sky-200/80',
        label: 'Bảo vệ mạng',
        title: 'Tích hợp sẵn Anti-DDoS',
        desc: 'Lọc lưu lượng bất thường ngay trên tuyến mạng, giảm thiểu gián đoạn.',
    },
    {
        icon: QrCode,
        iconBg: 'bg-violet-50 text-violet-600 border-violet-200/80',
        label: 'Khởi tạo tự động',
        title: 'VietQR 1–3 phút · Cấp ngay',
        desc: 'Nhận Proxy sau vài giây, tạo xong VPS trong 2–5 phút, hoạt động 24/7.',
    },
];
</script>

<template>
    <PublicLayout>
        <Head title="Hạ Tầng Cloud VPS Việt Nam & Proxy IPv4 Chất Lượng Cao" />

        <!-- PHẦN 1: HERO SECTION — ĐÚNG HẠ TẦNG THỰC TẾ LUGIACLOUD -->
        <section class="bg-hero-surface border-b border-slate-200/90 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-16">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
                    <!-- Cột trái: Nội dung chính -->
                    <div class="lg:col-span-7 space-y-5 sm:space-y-6">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50/90 border border-blue-200/90 text-xs font-semibold text-[#1D6AE5]">
                            <Zap class="w-3.5 h-3.5 fill-[#1D6AE5]/15" />
                            <span>Datacenter FPT (TP.HCM) · Tích hợp Anti DDoS · Lên đơn tự động 24/7</span>
                        </div>

                        <h1 class="text-3xl sm:text-4xl lg:text-[42px] font-extrabold text-[#0B1437] tracking-tight leading-[1.2]">
                            Hạ tầng Cloud VPS Việt Nam
                            <span class="text-[#1D6AE5]">SSD &amp; Proxy IPv4</span>
                            chất lượng cao
                        </h1>

                        <p class="text-sm sm:text-base text-slate-600 leading-relaxed max-w-2xl">
                            Hệ thống Cloud VPS tối ưu riêng cho chạy website, ứng dụng, server game, treo tool MMO, nuôi tài khoản và làm Proxy/VPN với hiệu năng cực kỳ ổn định. Vận hành trên chip <strong>Intel Xeon Gold / Platinum</strong> và <strong>100% ổ cứng SSD chuyên dụng</strong>.
                        </p>

                        <!-- 3 cam kết thực tế -->
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs sm:text-[13px] font-semibold text-slate-700">
                            <div class="inline-flex items-center gap-1.5">
                                <KeyRound class="w-4 h-4 text-[#1D6AE5] shrink-0" />
                                <span>Full quyền Root / Admin · Mở đủ Port TCP/UDP</span>
                            </div>
                            <div class="inline-flex items-center gap-1.5">
                                <RefreshCw class="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>Hoàn tiền tự động 100% nếu lỗi khởi tạo</span>
                            </div>
                            <div class="inline-flex items-center gap-1.5">
                                <Headphones class="w-4 h-4 text-amber-600 shrink-0" />
                                <span>Tư vấn &amp; Custom riêng 24/7</span>
                            </div>
                        </div>

                        <!-- Nút hành động -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-1">
                            <a
                                href="#pricing"
                                @click="activeTab = 'vps'"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-sm font-bold shadow-sm transition-all"
                            >
                                <Server class="w-4 h-4" />
                                <span>Xem bảng giá Cloud VPS</span>
                                <ArrowRight class="w-4 h-4" />
                            </a>
                            <a
                                href="#pricing"
                                @click="activeTab = 'proxy'"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white hover:bg-slate-50 text-[#0B1437] border border-slate-300 text-sm font-bold transition-all"
                            >
                                <Globe class="w-4 h-4 text-[#1D6AE5]" />
                                <span>Bảng giá Proxy IPv4</span>
                            </a>
                        </div>

                        <!-- Gợi ý quà thành viên mới tinh tế cho khách chưa đăng nhập -->
                        <div v-if="welcomeOffer?.mode === 'guest'" class="pt-0.5">
                            <button
                                type="button"
                                @click="openWelcomeOfferPrompt"
                                class="w-full sm:w-auto flex sm:inline-flex items-center justify-between sm:justify-start gap-2.5 px-3.5 py-2.5 sm:py-2 rounded-xl bg-emerald-50/90 hover:bg-emerald-100/80 border border-emerald-200/90 text-xs font-semibold text-emerald-900 transition-colors cursor-pointer text-left"
                            >
                                <span class="flex items-center gap-2 min-w-0">
                                    <Gift class="w-4 h-4 text-emerald-600 shrink-0" />
                                    <span class="leading-snug">
                                        Khách mới: <strong>Giảm {{ welcomeOffer.percent }}%</strong> đơn đầu
                                        <template v-if="welcomeOffer.max_discount > 0"> (tối đa {{ formatVnd(welcomeOffer.max_discount) }})</template>
                                    </span>
                                </span>
                                <span class="font-bold text-emerald-700 inline-flex items-center gap-0.5 shrink-0 whitespace-nowrap">
                                    Nhận ngay <ArrowRight class="w-3.5 h-3.5" />
                                </span>
                            </button>
                        </div>

                        <!-- Dải Hệ điều hành thực tế (Không bịa Docker / 1-click app) -->
                        <div class="pt-4 border-t border-slate-200/80">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2.5">
                                Hệ điều hành hỗ trợ cài đặt &amp; Reinstall tự động trong 2 – 5 phút
                            </div>
                            <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2">
                                <div
                                    v-for="(os, idx) in osStack"
                                    :key="idx"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white border border-slate-200/90 text-xs font-semibold text-slate-700 shadow-2xs"
                                >
                                    <OsIcon :distro="os.key" :size="16" />
                                    <span class="truncate">{{ os.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cột phải: Minh họa Thương hiệu LugiaCloud + Thông số thực tế -->
                    <div class="lg:col-span-5">
                        <div class="relative bg-gradient-to-b from-[#F0F7FF] to-white border border-blue-200/70 rounded-2xl p-5 sm:p-6 shadow-sm">
                            <div class="flex items-center justify-between pb-3 mb-2 border-b border-blue-100 text-xs">
                                <div class="flex items-center gap-2 font-bold text-[#0B1437]">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-500/15"></span>
                                    <span>Hạ tầng Cloud VPS LugiaCloud</span>
                                </div>
                                <span class="font-mono-num text-[11px] font-bold text-[#1D6AE5] bg-blue-100/70 px-2.5 py-0.5 rounded-md">
                                    FPT TP.HCM
                                </span>
                            </div>

                            <div class="flex justify-center py-2">
                                <picture>
                                    <source srcset="/hero-lugia.webp" type="image/webp" />
                                    <img
                                        src="/hero-lugia.png"
                                        alt="LugiaCloud Hạ tầng Cloud VPS SSD & Proxy"
                                        width="1024"
                                        height="753"
                                        fetchpriority="high"
                                        decoding="async"
                                        class="w-full max-w-[330px] sm:max-w-[360px] h-auto object-contain select-none drop-shadow-md"
                                    />
                                </picture>
                            </div>

                            <div class="grid grid-cols-3 gap-2 sm:gap-2.5 pt-3.5 mt-2 border-t border-blue-100/90">
                                <div class="bg-white border border-slate-200/90 rounded-xl p-2.5 text-center sm:text-left">
                                    <div class="flex items-center justify-center sm:justify-start gap-1 text-[10px] font-bold uppercase text-slate-400">
                                        <Cpu class="w-3 h-3 text-[#1D6AE5]" />
                                        <span>CPU Xeon</span>
                                    </div>
                                    <div class="text-[11px] sm:text-xs font-mono-num font-bold text-[#0B1437] mt-0.5">Gold · Platinum</div>
                                </div>
                                <div class="bg-white border border-slate-200/90 rounded-xl p-2.5 text-center sm:text-left">
                                    <div class="flex items-center justify-center sm:justify-start gap-1 text-[10px] font-bold uppercase text-slate-400">
                                        <HardDrive class="w-3 h-3 text-emerald-600" />
                                        <span>Lưu trữ</span>
                                    </div>
                                    <div class="text-[11px] sm:text-xs font-mono-num font-bold text-[#0B1437] mt-0.5">100% SSD</div>
                                </div>
                                <div class="bg-white border border-slate-200/90 rounded-xl p-2.5 text-center sm:text-left">
                                    <div class="flex items-center justify-center sm:justify-start gap-1 text-[10px] font-bold uppercase text-slate-400">
                                        <ShieldCheck class="w-3 h-3 text-[#1D6AE5]" />
                                        <span>Bảo vệ</span>
                                    </div>
                                    <div class="text-[11px] sm:text-xs font-mono-num font-bold text-[#1D6AE5] mt-0.5">Anti DDoS</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- PHẦN 2: BANNER SỰ KIỆN KHUYẾN MÃI THEO NĂM -->
        <section v-if="heroBanners.length > 0" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-7 sm:pt-9">
            <EventShowcaseBanner :banners="heroBanners" />
        </section>

        <!-- PHẦN 2.5: FLASH SALE GIỚI HẠN SUẤT & KHO SĂN VOUCHER (TỰ ẨN KHI KHÔNG CÓ CHƯƠNG TRÌNH) -->
        <section
            v-if="(flashSales && flashSales.length > 0) || (huntVouchers && huntVouchers.length > 0)"
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-7 sm:pt-9"
        >
            <FlashSaleShowcase :flash-sales="flashSales" :hunt-vouchers="huntVouchers" />
        </section>

        <!-- PHẦN 3: 6 THÔNG SỐ HẠ TẦNG THỰC CHIẾN CỦA LUGIACLOUD -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 sm:pt-10">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="(item, idx) in infraSpecs"
                    :key="idx"
                    class="surface-card-interactive p-5 flex items-start gap-3.5"
                >
                    <div
                        class="w-11 h-11 rounded-xl border flex items-center justify-center shrink-0"
                        :class="item.iconBg"
                    >
                        <component :is="item.icon" class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold text-slate-400">{{ item.label }}</div>
                        <h3 class="text-sm font-bold text-[#0B1437] mt-0.5">{{ item.title }}</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ item.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- PHẦN 4: BẢNG GIÁ TRỰC QUAN — TAB CỐ ĐỊNH VỊ TRÍ TUYỆT ĐỐI + TỐI ƯU MOBILE -->
        <section id="pricing" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
            <div class="space-y-2 mb-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#1D6AE5]">Bảng giá tự động</span>
                    <span
                        v-if="primaryCampaign"
                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200"
                    >
                        <Flame class="w-3 h-3" />
                        <span>{{ primaryCampaign.badge_text }}</span>
                    </span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-[#0B1437] tracking-tight">
                    Xem bảng giá &amp; lên đơn tự động tại LugiaCloud
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 max-w-2xl">
                    Giá hiển thị bên dưới đã bao gồm ưu đãi sự kiện hiện hành. Hỗ trợ nâng cấp thêm CPU, RAM và ổ cứng SSD linh hoạt ngay trong trang quản trị.
                </p>
            </div>

            <!-- THANH ĐIỀU KHIỂN CỐ ĐỊNH VỊ TRÍ: Cụm Tab VPS / Proxy luôn neo cố định bên trái -->
            <div class="surface-card p-2 sm:p-2.5 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-2.5">
                <div class="grid grid-cols-2 sm:inline-flex p-1 rounded-xl bg-slate-100 border border-slate-200/80 shrink-0">
                    <button
                        id="vps"
                        type="button"
                        @click="activeTab = 'vps'"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-xs sm:text-[13px] font-bold transition-all cursor-pointer"
                        :class="
                            activeTab === 'vps'
                                ? 'bg-[#0B1437] text-white shadow-xs'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                    >
                        <Server class="w-4 h-4" :class="activeTab === 'vps' ? 'text-sky-400' : 'text-slate-400'" />
                        <span>Cloud VPS ({{ plans.length }})</span>
                    </button>
                    <button
                        id="proxy"
                        type="button"
                        @click="activeTab = 'proxy'"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-xs sm:text-[13px] font-bold transition-all cursor-pointer"
                        :class="
                            activeTab === 'proxy'
                                ? 'bg-[#0B1437] text-white shadow-xs'
                                : 'text-slate-600 hover:text-slate-900'
                        "
                    >
                        <Globe class="w-4 h-4" :class="activeTab === 'proxy' ? 'text-sky-400' : 'text-slate-400'" />
                        <span>Proxy IPv4 ({{ proxyProducts.length }})</span>
                    </button>
                </div>

                <div class="flex items-center gap-2 overflow-x-auto pb-0.5 md:pb-0">
                    <template v-if="activeTab === 'vps'">
                        <span class="hidden xl:inline-flex items-center gap-1 text-xs font-semibold text-slate-500 mr-1 shrink-0">
                            <Calendar class="w-3.5 h-3.5 text-slate-400" />
                            <span>Chu kỳ thuê:</span>
                        </span>
                        <div class="inline-flex p-1 rounded-xl bg-slate-100/90 border border-slate-200/80 w-full sm:w-auto">
                            <button
                                v-for="c in displayCycles"
                                :key="c.key"
                                type="button"
                                @click="selectedCycle = c.key"
                                class="flex-1 sm:flex-initial px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all cursor-pointer"
                                :class="
                                    selectedCycle === c.key
                                        ? 'bg-white text-[#0B1437] shadow-xs font-bold'
                                        : 'text-slate-600 hover:text-slate-900'
                                "
                            >
                                <span>{{ c.label }}</span>
                                <span
                                    v-if="c.discount_percent > 0"
                                    class="ml-1 text-[10px] font-mono-num font-bold text-emerald-600"
                                >
                                    -{{ c.discount_percent }}%
                                </span>
                            </button>
                        </div>
                    </template>

                    <template v-else>
                        <span class="hidden xl:inline-flex items-center gap-1 text-xs font-semibold text-slate-500 mr-1 shrink-0">
                            <Filter class="w-3.5 h-3.5 text-slate-400" />
                            <span>Phân loại IP:</span>
                        </span>
                        <div class="inline-flex p-1 rounded-xl bg-slate-100/90 border border-slate-200/80 w-full sm:w-auto">
                            <button
                                v-for="f in [
                                    { key: 'all', label: 'Tất cả gói' },
                                    { key: 'residential', label: 'IP Dân cư VN' },
                                    { key: 'datacenter', label: 'IP Datacenter' },
                                ]"
                                :key="f.key"
                                type="button"
                                @click="proxyCategoryFilter = f.key as any"
                                class="flex-1 sm:flex-initial px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all cursor-pointer"
                                :class="
                                    proxyCategoryFilter === f.key
                                        ? 'bg-white text-[#0B1437] shadow-xs font-bold'
                                        : 'text-slate-600 hover:text-slate-900'
                                "
                            >
                                {{ f.label }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- NỘI DUNG TAB 1: CLOUD VPS SSD -->
            <template v-if="activeTab === 'vps'">
                <div
                    v-if="isVpsOutOfStock"
                    class="surface-card p-4 mb-4 border-amber-300 bg-amber-50 flex items-center gap-3 text-xs text-amber-900"
                >
                    <AlertCircle class="w-4 h-4 text-amber-600 shrink-0" />
                    <span>
                        Cụm máy chủ Cloud VPS hiện đang tạm ngưng nhận đơn khởi tạo mới để nâng cấp tài nguyên. Bạn vẫn có thể gia hạn các VPS hiện có hoặc đăng ký Proxy IPv4 bình thường.
                    </span>
                </div>

                <!-- Bảng Desktop (>= 1024px) -->
                <div class="hidden lg:block surface-card overflow-hidden">
                    <table class="w-full table-fixed text-left border-collapse">
                        <colgroup>
                            <col />
                            <col class="w-[96px] xl:w-[104px]" />
                            <col class="w-[108px] xl:w-[116px]" />
                            <col class="w-[112px] xl:w-[120px]" />
                            <col class="w-[162px] xl:w-[178px]" />
                            <col class="w-[164px] xl:w-[178px]" />
                            <col class="w-[128px] xl:w-[136px]" />
                        </colgroup>
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="py-3.5 px-5 whitespace-nowrap">Gói cấu hình máy chủ</th>
                                <th class="py-3.5 px-3 xl:px-4 whitespace-nowrap">Vi xử lý</th>
                                <th class="py-3.5 px-3 xl:px-4 whitespace-nowrap">Bộ nhớ RAM</th>
                                <th class="py-3.5 px-3 xl:px-4 whitespace-nowrap">Ổ cứng SSD</th>
                                <th class="py-3.5 px-3 xl:px-4 whitespace-nowrap">Tốc độ &amp; Lưu lượng</th>
                                <th class="py-3.5 px-3 xl:px-4 text-right whitespace-nowrap">Thành tiền chu kỳ</th>
                                <th class="py-3.5 px-5 text-right whitespace-nowrap">Đăng ký</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 text-sm">
                            <tr
                                v-for="(plan, idx) in plans"
                                :key="plan.slug"
                                class="hover:bg-blue-50/35 transition-colors"
                                :class="plan.is_featured ? 'bg-blue-50/25' : ''"
                            >
                                <td class="py-4 px-5">
                                    <div class="flex items-center gap-3.5">
                                        <div
                                            class="w-10 h-10 rounded-xl border flex items-center justify-center shrink-0"
                                            :class="
                                                plan.is_featured
                                                    ? 'bg-[#1D6AE5] text-white border-[#1D6AE5]'
                                                    : 'bg-slate-100 text-[#0B1437] border-slate-200'
                                            "
                                        >
                                            <component :is="planIcon(idx, plan.is_featured)" class="w-4.5 h-4.5" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-bold text-[#0B1437]">{{ plan.name }}</span>
                                                <span
                                                    v-if="planQuote(plan).flash_sale"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-50 text-red-700 border border-red-200"
                                                >
                                                    <Zap class="w-3 h-3 fill-red-600 text-red-600" />
                                                    Flash Sale · Còn {{ planQuote(plan).flash_sale.remaining_slots }}/{{ planQuote(plan).flash_sale.total_slots }}
                                                </span>
                                                <span
                                                    v-else-if="plan.badge"
                                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold border"
                                                    :class="
                                                        plan.is_featured
                                                            ? 'bg-[#1D6AE5] text-white border-[#1D6AE5]'
                                                            : 'bg-slate-100 text-slate-700 border-slate-200'
                                                    "
                                                >
                                                    {{ plan.badge }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-slate-500 mt-0.5">{{ plan.desc }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-4 px-3 xl:px-4 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 font-mono-num text-xs font-bold text-slate-800">
                                        <Cpu class="w-3.5 h-3.5 text-[#1D6AE5] shrink-0" />
                                        <span>{{ plan.cores }} vCPU</span>
                                    </div>
                                </td>

                                <td class="py-4 px-3 xl:px-4 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 font-mono-num text-xs font-bold text-slate-800">
                                        <MemoryStick class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                                        <span>{{ plan.ram }} GB RAM</span>
                                    </div>
                                </td>

                                <td class="py-4 px-3 xl:px-4 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 font-mono-num text-xs font-bold text-slate-800">
                                        <HardDrive class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                        <span>{{ plan.disk }} GB SSD</span>
                                    </div>
                                </td>

                                <td class="py-4 px-3 xl:px-4 whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600">
                                        <Gauge class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                        <span>{{ formatBandwidth(plan) }}</span>
                                    </div>
                                </td>

                                <td class="py-4 px-3 xl:px-4 text-right whitespace-nowrap">
                                    <div class="flex flex-col items-end justify-center h-[58px]">
                                        <div v-if="planQuote(plan).discount_amount > 0" class="flex items-center justify-end gap-1.5 mb-0.5 leading-none">
                                            <span class="text-[11px] font-mono-num text-slate-400 line-through">
                                                {{ formatVnd(planQuote(plan).list_price) }}
                                            </span>
                                            <span
                                                v-if="planQuote(plan).campaign"
                                                class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold leading-none"
                                            >
                                                Sự kiện
                                            </span>
                                        </div>
                                        <div class="inline-flex items-baseline justify-end gap-1 font-mono-num text-base font-extrabold text-[#0B1437] leading-tight">
                                            <span>{{ formatVnd(planQuote(plan).final_price) }}</span>
                                            <span
                                                v-if="planQuote(plan).months === 1"
                                                class="text-[11px] font-sans font-medium text-slate-500"
                                            >
                                                /tháng
                                            </span>
                                        </div>
                                        <div
                                            v-if="planQuote(plan).months > 1"
                                            class="text-[11px] font-mono-num text-slate-500 mt-0.5 leading-4"
                                        >
                                            ~{{ formatVnd(planQuote(plan).monthly_equivalent) }}/tháng
                                        </div>
                                    </div>
                                </td>

                                <td class="py-4 px-5 text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        :disabled="isVpsOutOfStock"
                                        @click="
                                            !isVpsOutOfStock &&
                                                goOrPromptLogin(
                                                    `/store/${plan.slug}?cycle=${selectedCycle}`,
                                                    `Khởi tạo ${plan.name} (${billingCycles[selectedCycle]?.label || '1 Tháng'})`
                                                )
                                        "
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all"
                                        :class="
                                            isVpsOutOfStock
                                                ? 'bg-slate-200 text-slate-500 cursor-not-allowed'
                                                : plan.is_featured
                                                  ? 'bg-[#1D6AE5] hover:bg-[#1557C0] text-white shadow-xs cursor-pointer'
                                                  : 'bg-[#0B1437] hover:bg-[#162354] text-white cursor-pointer'
                                        "
                                    >
                                        <span>{{ isVpsOutOfStock ? 'Tạm hết hàng' : 'Khởi tạo' }}</span>
                                        <ArrowRight v-if="!isVpsOutOfStock" class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Thẻ Dọc Mobile & Tablet (< 1024px) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:hidden">
                    <div
                        v-for="(plan, idx) in plans"
                        :key="'m_' + plan.slug"
                        class="surface-card p-5 flex flex-col justify-between space-y-4"
                        :class="plan.is_featured ? 'ring-2 ring-[#1D6AE5] border-[#1D6AE5]' : ''"
                    >
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-xl border flex items-center justify-center shrink-0"
                                        :class="
                                            plan.is_featured
                                                ? 'bg-[#1D6AE5] text-white border-[#1D6AE5]'
                                                : 'bg-slate-100 text-[#0B1437] border-slate-200'
                                        "
                                    >
                                        <component :is="planIcon(idx, plan.is_featured)" class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <div class="font-extrabold text-base text-[#0B1437]">{{ plan.name }}</div>
                                        <div class="text-xs text-slate-500 line-clamp-1">{{ plan.desc }}</div>
                                    </div>
                                </div>
                                <span
                                    v-if="plan.badge"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold shrink-0"
                                    :class="plan.is_featured ? 'bg-[#1D6AE5] text-white' : 'bg-slate-100 text-slate-700'"
                                >
                                    {{ plan.badge }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-xs">
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50 border border-slate-200/60">
                                    <Cpu class="w-3.5 h-3.5 text-[#1D6AE5] shrink-0" />
                                    <span class="font-mono-num font-bold text-slate-800">{{ plan.cores }} Core vCPU</span>
                                </div>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50 border border-slate-200/60">
                                    <MemoryStick class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                                    <span class="font-mono-num font-bold text-slate-800">{{ plan.ram }} GB RAM</span>
                                </div>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50 border border-slate-200/60">
                                    <HardDrive class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                    <span class="font-mono-num font-bold text-slate-800">{{ plan.disk }} GB SSD</span>
                                </div>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50 border border-slate-200/60">
                                    <Gauge class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                                    <span class="font-mono-num font-bold text-slate-800">
                                        {{ plan.network_out_mbps >= 1000 ? '1 Gbps' : plan.network_out_mbps + ' Mbps' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-end justify-between gap-3">
                            <div>
                                <div v-if="planQuote(plan).discount_amount > 0" class="flex items-center gap-1.5">
                                    <span class="text-xs font-mono-num text-slate-400 line-through">
                                        {{ formatVnd(planQuote(plan).list_price) }}
                                    </span>
                                    <span class="px-1.5 py-0.2 rounded bg-rose-50 text-rose-700 text-[10px] font-bold">Sự kiện</span>
                                </div>
                                <div class="flex items-baseline gap-1 mt-0.5">
                                    <span class="text-xl font-extrabold font-mono-num text-[#0B1437]">
                                        {{ formatVnd(planQuote(plan).final_price) }}
                                    </span>
                                    <span class="text-xs text-slate-500">/ {{ billingCycles[selectedCycle]?.label }}</span>
                                </div>
                            </div>

                            <button
                                type="button"
                                :disabled="isVpsOutOfStock"
                                @click="
                                    !isVpsOutOfStock &&
                                        goOrPromptLogin(
                                            `/store/${plan.slug}?cycle=${selectedCycle}`,
                                            `Khởi tạo ${plan.name} (${billingCycles[selectedCycle]?.label || '1 Tháng'})`
                                        )
                                "
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold shrink-0"
                                :class="
                                    isVpsOutOfStock
                                        ? 'bg-slate-200 text-slate-500 cursor-not-allowed'
                                        : plan.is_featured
                                          ? 'bg-[#1D6AE5] text-white cursor-pointer'
                                          : 'bg-[#0B1437] text-white cursor-pointer'
                                "
                            >
                                <span>{{ isVpsOutOfStock ? 'Tạm hết hàng' : 'Khởi tạo' }}</span>
                                <ArrowRight v-if="!isVpsOutOfStock" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- NỘI DUNG TAB 2: PROXY IPV4 VIỆT NAM -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                <div
                    v-for="prx in filteredProxies"
                    :key="prx.id"
                    class="surface-card-interactive p-5 flex flex-col justify-between"
                    :class="prx.is_featured ? 'ring-2 ring-[#1D6AE5] border-[#1D6AE5]' : ''"
                >
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold border"
                                :class="
                                    prx.category === 'residential'
                                        ? 'bg-sky-50 text-sky-800 border-sky-200'
                                        : 'bg-slate-100 text-slate-700 border-slate-200'
                                "
                            >
                                <OsIcon distro="vietnam" :size="14" />
                                <span>{{ prx.category === 'residential' ? 'IP Dân cư VN' : 'IP Datacenter' }}</span>
                            </span>
                            <span
                                v-if="prx.flash_sale"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-50 text-red-700 border border-red-200 text-[10px] font-bold"
                            >
                                <Zap class="w-3 h-3 fill-red-600 text-red-600" />
                                Flash Sale · Còn {{ prx.flash_sale.remaining_slots }}/{{ prx.flash_sale.total_slots }}
                            </span>
                            <span v-else-if="prx.badge" class="px-2 py-0.5 rounded-md bg-[#1D6AE5] text-white text-[10px] font-bold">
                                {{ prx.badge }}
                            </span>
                        </div>

                        <h3 class="text-base font-extrabold text-[#0B1437]">{{ prx.title }}</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ prx.description }}</p>

                        <div class="my-4 py-3.5 border-y border-slate-100">
                            <div v-if="prx.has_discount" class="flex items-center gap-1.5 mb-0.5">
                                <span class="text-xs font-mono-num text-slate-400 line-through">{{ formatVnd(prx.list_price) }}</span>
                                <span class="px-1.5 py-0.2 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold">
                                    Ưu đãi sự kiện
                                </span>
                            </div>
                            <div class="flex items-baseline gap-1">
                                <span class="text-2xl font-extrabold font-mono-num text-[#0B1437]">{{ formatVnd(prx.price) }}</span>
                                <span class="text-xs text-slate-500">/ IP / tháng</span>
                            </div>
                        </div>

                        <ul class="space-y-2 text-xs text-slate-600 mb-5">
                            <li class="flex items-center gap-2">
                                <Check class="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>Hỗ trợ chuẩn HTTP &amp; SOCKS5</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>Băng thông không giới hạn</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>Bảo mật User/Pass riêng biệt</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="w-4 h-4 text-emerald-600 shrink-0" />
                                <span>Cấp IP tự động ngay lập tức</span>
                            </li>
                        </ul>
                    </div>

                    <button
                        type="button"
                        @click="goOrPromptLogin(`/proxy/store/${prx.id}`, `Mua gói ${prx.title}`)"
                        class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 px-4 rounded-xl text-xs font-bold transition-colors cursor-pointer"
                        :class="
                            prx.is_featured
                                ? 'bg-[#1D6AE5] hover:bg-[#1557C0] text-white'
                                : 'bg-[#0B1437] hover:bg-[#162354] text-white'
                        "
                    >
                        <span>Mua Proxy này</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- Thanh tư vấn cấu hình phù hợp / Custom riêng 24/7 -->
            <div class="mt-6 surface-card p-4 sm:p-5 bg-gradient-to-r from-[#F8FAFF] to-white flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-start sm:items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-100/80 text-[#1D6AE5] flex items-center justify-center shrink-0">
                        <MessageCircle class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-sm font-bold text-[#0B1437]">
                            Cần tư vấn cấu hình phù hợp hoặc đặt cấu hình Custom riêng?
                        </div>
                        <p class="text-xs text-slate-600 mt-0.5">
                            Nhắn tin trực tiếp cho đội ngũ kỹ thuật LugiaCloud để được tư vấn tối ưu chi phí và hỗ trợ nhiệt tình 24/7.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <a
                        href="https://zalo.me/0862579104"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#0068FF] hover:bg-[#0057D9] text-white text-xs font-bold transition-colors"
                    >
                        <span>Nhắn Zalo 0862.579.104</span>
                    </a>
                    <a
                        href="https://www.facebook.com/profile.php?id=61591873231741"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 text-xs font-bold transition-colors"
                    >
                        <span>Nhắn Fanpage</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- PHẦN 5: TÌNH HUỐNG SỬ DỤNG THỰC TẾ (KHÔNG LẶP LẠI THÔNG SỐ PHẦN CỨNG PHẦN 3) -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-14">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Thẻ 1: MMO, Ads & Nuôi tài khoản -->
                <div class="surface-card p-6 flex flex-col justify-between space-y-5">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <div class="w-11 h-11 rounded-xl bg-amber-50 border border-amber-200/80 text-amber-600 flex items-center justify-center shrink-0">
                                <Globe class="w-5 h-5" />
                            </div>
                            <h3 class="text-base font-extrabold text-[#0B1437] leading-snug">
                                MMO, Chạy Ads &amp; Nuôi Tài Khoản
                            </h3>
                        </div>
                        <ul class="space-y-3 text-xs text-slate-600 leading-relaxed">
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                                <span>Tương thích mượt với <strong>AdsPower, GoLogin, Multilogin, Dolphin</strong> và các trình duyệt Antidetect.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                                <span>Pool <strong>IP Dân cư &amp; Datacenter sạch 100%</strong>, hạn chế tối đa checkpoint khi nuôi Facebook, Google, TikTok.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                                <span>Hỗ trợ đồng thời <strong>HTTP &amp; SOCKS5</strong>, băng thông không giới hạn, dễ dùng làm Proxy/VPN riêng.</span>
                            </li>
                        </ul>
                    </div>
                    <p class="pt-4 border-t border-slate-100 text-[11px] text-slate-500">
                        Phù hợp cho đội nhóm Ads Runner, Affiliate, làm dịch vụ mạng xã hội và treo tool 24/7.
                    </p>
                </div>

                <!-- Thẻ 2: Web, Bot & Server Game -->
                <div class="surface-card p-6 flex flex-col justify-between space-y-5">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <div class="w-11 h-11 rounded-xl bg-blue-50 border border-blue-200/80 text-[#1D6AE5] flex items-center justify-center shrink-0">
                                <Server class="w-5 h-5" />
                            </div>
                            <h3 class="text-base font-extrabold text-[#0B1437] leading-snug">
                                Website, Bot &amp; Server Game
                            </h3>
                        </div>
                        <ul class="space-y-3 text-xs text-slate-600 leading-relaxed">
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                                <span>Chạy ổn định Website bán hàng, API, Docker, Bot Telegram/Discord và máy chủ game (Minecraft, FiveM, MU...).</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                                <span>Sẵn kho hệ điều hành <strong>Windows Server, Windows 10</strong> và <strong>Ubuntu, Debian, CentOS, AlmaLinux</strong>.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                                <span>Dễ dàng <strong>nâng cấp thêm CPU, RAM, ổ cứng SSD</strong> trực tiếp trên web mà không cần mua máy mới.</span>
                            </li>
                        </ul>
                    </div>
                    <p class="pt-4 border-t border-slate-100 text-[11px] text-slate-500">
                        Phù hợp cho lập trình viên, chủ shop online, admin server game và doanh nghiệp nhỏ.
                    </p>
                </div>

                <!-- Thẻ 3: Chủ động vận hành & Bảo vệ chi phí -->
                <div class="surface-card p-6 flex flex-col justify-between space-y-5">
                    <div>
                        <div class="flex items-center gap-3.5 mb-4">
                            <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-600 flex items-center justify-center shrink-0">
                                <Zap class="w-5 h-5" />
                            </div>
                            <h3 class="text-base font-extrabold text-[#0B1437] leading-snug">
                                Chính Sách Minh Bạch &amp; Hỗ Trợ 24/7
                            </h3>
                        </div>
                        <ul class="space-y-3 text-xs text-slate-600 leading-relaxed">
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                                <span><strong>Hoàn tiền tự động 100%</strong> về ví nếu quá trình khởi tạo máy chủ hoặc cấp IP gặp lỗi hệ thống.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                                <span>Hỗ trợ bật/tắt <strong>tự động gia hạn trước 1 ngày</strong>, gửi email nhắc hạn sớm để tránh mất dữ liệu.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                                <span>Kỹ thuật viên trực <strong>Zalo &amp; Fanpage 24/7</strong>, nhận tư vấn chọn gói tối ưu và custom cấu hình theo yêu cầu.</span>
                            </li>
                        </ul>
                    </div>
                    <p class="pt-4 border-t border-slate-100 text-[11px] text-slate-500">
                        Giá hiển thị công khai theo từng chu kỳ, xuất chứng từ và lịch sử biến động số dư rõ ràng.
                    </p>
                </div>
            </div>
        </section>

        <!-- PHẦN 6: BẢNG ĐIỀU KHIỂN THỰC CHIẾN & TÍCH HỢP API ĐẠI LÝ -->
        <section id="architecture" class="bg-white border-t border-slate-200 py-14 sm:py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
                    <div class="lg:col-span-6 space-y-5">
                        <span class="text-xs font-bold uppercase tracking-wider text-[#1D6AE5]">
                            Tối ưu cho Quản trị viên &amp; Đại lý (Reseller)
                        </span>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-[#0B1437] tracking-tight">
                            Quản lý tập trung máy chủ, Proxy &amp; tích hợp API tự động
                        </h2>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Trang quản trị khách hàng (Client Area) được tối ưu tốc độ phản hồi, dễ dàng thao tác nhiều dịch vụ cùng lúc hoặc đấu nối API bán hàng tự động sang hệ thống riêng.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="flex items-center gap-2 font-bold text-xs text-[#0B1437] mb-1.5">
                                    <Cpu class="w-4 h-4 text-[#1D6AE5]" />
                                    <span>Toàn quyền nguồn &amp; Console</span>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Bật, tắt, khởi động lại, đổi mật khẩu, cài lại Windows/Linux và mở màn hình Console trực tiếp trên trình duyệt.
                                </p>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="flex items-center gap-2 font-bold text-xs text-[#0B1437] mb-1.5">
                                    <Layers class="w-4 h-4 text-indigo-600" />
                                    <span>Quản lý Proxy hàng loạt</span>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Tick chọn nhiều IP cùng lúc để sao chép nhanh định dạng IP:Port:User:Pass hoặc xuất file TXT, CSV.
                                </p>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="flex items-center gap-2 font-bold text-xs text-[#0B1437] mb-1.5">
                                    <Terminal class="w-4 h-4 text-emerald-600" />
                                    <span>API Đại lý &amp; Webhook</span>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Cung cấp khóa Bearer Token chuẩn RESTful và bắn Webhook tự động ngay khi VPS khởi tạo xong IP &amp; mật khẩu.
                                </p>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="flex items-center gap-2 font-bold text-xs text-[#0B1437] mb-1.5">
                                    <ShieldCheck class="w-4 h-4 text-amber-600" />
                                    <span>Sổ cái ví minh bạch 100%</span>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Lưu vết chi tiết biến động số dư, xuất biên lai dịch vụ và hưởng hoa hồng giới thiệu Affiliate 5% trọn đời.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Khung minh họa API đúng tên miền Lugiacloud.com -->
                    <div class="lg:col-span-6">
                        <div class="rounded-2xl bg-[#0B1437] border border-slate-800 overflow-hidden shadow-lg">
                            <div class="px-4 py-3 bg-slate-950/55 border-b border-white/10 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500/85"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400/85"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/85"></span>
                                    <span class="ml-2 font-mono text-slate-300 text-[11px]">POST /api/v1/vps/create</span>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-sky-500/15 text-[10px] font-mono font-semibold text-sky-300">Reseller API v1</span>
                            </div>
                            <pre class="p-4 sm:p-5 text-[11px] sm:text-xs font-mono text-slate-200 overflow-x-auto leading-relaxed"><code>curl -X POST https://lugiacloud.com/api/v1/vps/create \
  -H "Authorization: Bearer sk_live_98f2a4c..." \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": "lg-8",
    "os_id": 7,
    "duration": 3,
    "hostname": "may-chu-web-01"
  }'

<span class="text-emerald-400">// Phản hồi tự động: 202 Đang khởi tạo</span>
{
  "status": 202,
  "message": "VPS đang được khởi tạo tự động.",
  "data": {
    "order_id": "1763432910482",
    "status": "processing"
  }
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
