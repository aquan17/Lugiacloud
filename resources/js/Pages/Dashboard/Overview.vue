<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EventShowcaseBanner from '@/Components/EventShowcaseBanner.vue';
import CountdownClock from '@/Components/CountdownClock.vue';
import { formatVnd } from '@/Composables/useFormat';
import { Server, Globe, Wallet, Receipt, ArrowUpRight, Plus, AlertTriangle, Gift } from 'lucide-vue-next';

const props = defineProps<{
    stats: {
        vps_total: number;
        vps_active: number;
        vps_expired: number;
        vps_deleted: number;
        proxy_total: number;
        proxy_active: number;
        proxy_expiring: number;
        proxy_expired: number;
        total_deposited: number;
        total_spent: number;
    };
    expiringVps: Array<any>;
    expiringProxies: Array<any>;
    recentTransactions: Array<any>;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const dashBanner = computed(() => {
    const b = (page.props.promotions as any)?.banners?.dashboard_banner;
    return b ? [b] : [];
});
const welcomeOffer = computed(() => (page.props.promotions as any)?.welcome_offer);
</script>

<template>
    <ClientLayout title="Tổng quan hạ tầng">
        <Head title="Tổng quan hạ tầng" />

        <div class="space-y-6">
            <!-- Ưu đãi thành viên mới (tự hiện thay banner khi còn hạn 7 ngày & chưa mua đơn đầu; dùng xong tự trả về Sự kiện) -->
            <div
                v-if="welcomeOffer?.mode === 'eligible'"
                class="rounded-2xl p-4 sm:p-5 text-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
                style="background: linear-gradient(95deg, #065F46 0%, #0D9488 100%)"
            >
                <div class="flex items-start sm:items-center gap-3.5 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center shrink-0">
                        <Gift class="w-5 h-5 text-emerald-100" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded bg-white/18 text-[10.5px] font-extrabold uppercase tracking-wide text-emerald-50">
                                Quà thành viên mới
                            </span>
                            <span class="font-mono text-xs font-black px-2 py-0.5 rounded bg-slate-950/30 text-emerald-100">
                                {{ welcomeOffer.code }}
                            </span>
                            <CountdownClock v-if="welcomeOffer.expires_at" :ends-at="welcomeOffer.expires_at" compact />
                        </div>
                        <div class="text-sm font-extrabold mt-1">
                            Giảm {{ welcomeOffer.percent }}% đơn đầu tiên<template v-if="welcomeOffer.max_discount > 0"> (tối đa {{ formatVnd(welcomeOffer.max_discount) }})</template> cho toàn bộ gói VPS &amp; Proxy {{ welcomeOffer.max_months }} tháng
                        </div>
                        <div class="text-xs text-emerald-100/90 mt-0.5">
                            Mã đã được lưu sẵn trong <strong>Ví của bạn</strong> — chỉ cần chọn lúc thanh toán.
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <Link
                        href="/store"
                        class="px-3.5 py-2 rounded-xl bg-white text-emerald-900 hover:bg-emerald-50 text-xs font-extrabold transition shadow-xs"
                    >
                        Mua VPS (-{{ welcomeOffer.percent }}%)
                    </Link>
                    <Link
                        href="/proxy/store"
                        class="px-3.5 py-2 rounded-xl bg-white/15 hover:bg-white/25 border border-white/25 text-white text-xs font-bold transition"
                    >
                        Mua Proxy
                    </Link>
                </div>
            </div>

            <!-- Seasonal Dashboard Promo Strip -->
            <EventShowcaseBanner v-else-if="dashBanner.length > 0" :banners="dashBanner" />

            <!-- Top Quick Action Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-extrabold text-[#0B1437] tracking-tight">
                        Xin chào, {{ user?.name }}
                    </h2>
                    <p class="text-[13.5px] text-slate-600 font-medium mt-0.5">
                        Theo dõi trạng thái hoạt động máy chủ, Proxy IPv4 và biến động số dư tài khoản.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <Link
                        href="/proxy/store"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-300 text-[13px] font-bold text-slate-800 transition shadow-2xs"
                    >
                        <Plus class="w-4 h-4 text-[#1D6AE5]" />
                        <span>Mua Proxy IPv4</span>
                    </Link>
                    <Link
                        href="/store"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-[13px] font-bold transition shadow-2xs"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Khởi tạo Cloud VPS</span>
                    </Link>
                </div>
            </div>

            <!-- 4 KPI Cards Độc Lập — Cân Đối, Sắc Nét & Dễ Đọc -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <!-- Card 1: Số dư khả dụng -->
                <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between gap-3.5 hover:border-blue-200 transition-colors">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[13px] font-semibold text-slate-600">Số dư khả dụng</span>
                        <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-100 text-[#1D6AE5] flex items-center justify-center shrink-0">
                            <Wallet class="w-4.5 h-4.5" />
                        </div>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-[22px] sm:text-2xl font-extrabold font-mono-num text-[#0B1437] tracking-tight">
                            {{ formatVnd(user?.balance || 0) }}
                        </span>
                        <Link href="/topup" class="inline-flex items-center gap-0.5 text-[13px] font-bold text-[#1D6AE5] hover:underline whitespace-nowrap">
                            <span>Nạp ví →</span>
                        </Link>
                    </div>
                </div>

                <!-- Card 2: Cloud VPS đang chạy -->
                <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between gap-3.5 hover:border-indigo-200 transition-colors">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[13px] font-semibold text-slate-600">Cloud VPS đang chạy</span>
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                            <Server class="w-4.5 h-4.5" />
                        </div>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-[22px] sm:text-2xl font-extrabold font-mono-num text-[#0B1437] tracking-tight">{{ stats.vps_active }}</span>
                            <span class="text-[13.5px] font-semibold text-slate-500">/ {{ stats.vps_total }} máy</span>
                        </div>
                        <Link
                            v-if="stats.vps_expired > 0"
                            href="/vps?status=expired"
                            class="text-xs font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 px-2.5 py-0.5 rounded-md border border-amber-200 whitespace-nowrap transition-colors"
                        >
                            {{ stats.vps_expired }} quá hạn
                        </Link>
                        <Link v-else href="/vps" class="text-[13px] font-bold text-slate-600 hover:text-[#1D6AE5] whitespace-nowrap">
                            Chi tiết →
                        </Link>
                    </div>
                </div>

                <!-- Card 3: Proxy IPv4 hoạt động -->
                <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between gap-3.5 hover:border-emerald-200 transition-colors">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[13px] font-semibold text-slate-600">Proxy IPv4 hoạt động</span>
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                            <Globe class="w-4.5 h-4.5" />
                        </div>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-[22px] sm:text-2xl font-extrabold font-mono-num text-[#0B1437] tracking-tight">{{ stats.proxy_active }}</span>
                            <span class="text-[13.5px] font-semibold text-slate-500">/ {{ stats.proxy_total }} IP</span>
                        </div>
                        <Link
                            v-if="stats.proxy_expiring > 0"
                            href="/proxy?status=expiring"
                            class="text-xs font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 px-2.5 py-0.5 rounded-md border border-amber-200 whitespace-nowrap transition-colors"
                        >
                            {{ stats.proxy_expiring }} sắp hết hạn
                        </Link>
                        <Link v-else href="/proxy" class="text-[13px] font-bold text-slate-600 hover:text-emerald-700 whitespace-nowrap">
                            Quản lý →
                        </Link>
                    </div>
                </div>

                <!-- Card 4: Tổng chi tiêu lũy kế -->
                <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between gap-3.5 hover:border-amber-200 transition-colors">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[13px] font-semibold text-slate-600">Tổng chi tiêu lũy kế</span>
                        <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                            <Receipt class="w-4.5 h-4.5" />
                        </div>
                    </div>
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-[22px] sm:text-2xl font-extrabold font-mono-num text-[#0B1437] tracking-tight">
                            {{ formatVnd(stats.total_spent) }}
                        </span>
                        <Link href="/billing" class="text-[13px] font-bold text-slate-700 hover:text-[#1D6AE5] whitespace-nowrap">
                            Sổ cái →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Two Column Main Section: Expiring Resources & Recent Ledger -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Expiring / Action Required -->
                <div class="lg:col-span-7 surface-card overflow-hidden border border-slate-200/90 rounded-2xl shadow-xs">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-amber-600" />
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                Dịch vụ cần gia hạn (7 ngày tới)
                            </h3>
                        </div>
                        <Link href="/vps" class="text-xs font-bold text-[#1D6AE5] hover:underline">
                            Quản lý tất cả
                        </Link>
                    </div>

                    <div v-if="expiringVps.length === 0 && expiringProxies.length === 0" class="p-8 text-center">
                        <p class="text-xs text-slate-600 font-medium">Toàn bộ máy chủ và Proxy của bạn đều đang trong chu kỳ hoạt động an toàn.</p>
                    </div>

                    <div v-else class="divide-y divide-slate-200/80 text-[13px]">
                        <div
                            v-for="v in expiringVps"
                            :key="'v_' + v.id"
                            class="px-5 py-3.5 flex items-center justify-between gap-4 hover:bg-slate-50/70 transition"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md bg-blue-50 text-[11px] font-mono font-bold text-[#1D6AE5] border border-blue-200">VPS</span>
                                    <span class="font-bold text-[#0B1437] truncate">{{ v.label }}</span>
                                    <span v-if="v.public_ip" class="font-mono-num text-xs font-semibold text-slate-700">({{ v.public_ip }})</span>
                                </div>
                                <div class="text-xs text-slate-600 font-medium mt-1">Hết hạn: <strong class="font-mono-num font-bold text-amber-800">{{ v.expires_at }}</strong></div>
                            </div>
                            <Link
                                :href="`/vps/${v.id}`"
                                class="px-3.5 py-1.5 rounded-lg bg-[#0B1437] text-white text-xs font-bold shrink-0 hover:bg-[#162354] transition shadow-2xs"
                            >
                                Gia hạn
                            </Link>
                        </div>

                        <div
                            v-for="p in expiringProxies"
                            :key="'p_' + p.id"
                            class="px-5 py-3.5 flex items-center justify-between gap-4 hover:bg-slate-50/70 transition"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md bg-purple-50 text-[11px] font-mono font-bold text-purple-700 border border-purple-200">{{ p.type_proxy }}</span>
                                    <span class="font-mono-num font-bold text-[#0B1437]">{{ p.endpoint }}</span>
                                </div>
                                <div class="text-xs text-slate-600 font-medium mt-1">Hết hạn: <strong class="font-mono-num font-bold text-amber-800">{{ p.expires_at }}</strong></div>
                            </div>
                            <Link
                                href="/proxy?status=expiring"
                                class="px-3.5 py-1.5 rounded-lg border border-slate-300 text-slate-800 text-xs font-bold shrink-0 hover:bg-slate-100 transition shadow-2xs"
                            >
                                Xử lý
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Recent Ledger Entries -->
                <div class="lg:col-span-5 surface-card overflow-hidden border border-slate-200/90 rounded-2xl shadow-xs">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Giao dịch ví gần đây</h3>
                        <Link href="/billing" class="inline-flex items-center gap-1 text-[13px] font-bold text-[#1D6AE5] hover:underline">
                            <span>Sổ cái</span>
                            <ArrowUpRight class="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    <div v-if="recentTransactions.length === 0" class="p-8 text-center text-[13px] text-slate-600 font-medium">
                        Chưa phát sinh giao dịch nào.
                    </div>

                    <div v-else class="divide-y divide-slate-200/80 text-[13px]">
                        <div
                            v-for="t in recentTransactions"
                            :key="t.id"
                            class="px-5 py-3.5 flex items-center justify-between gap-3 hover:bg-slate-50/50 transition"
                        >
                            <div class="min-w-0">
                                <div class="font-bold text-[#0B1437] truncate">{{ t.description }}</div>
                                <div class="text-xs text-slate-600 font-mono-num font-medium mt-0.5">{{ t.code }} · {{ t.created_at }}</div>
                            </div>
                            <div
                                class="font-mono-num text-[13.5px] font-extrabold shrink-0"
                                :class="
                                    t.status === 'refunded'
                                        ? 'text-slate-400 line-through'
                                        : t.is_credit
                                          ? 'text-emerald-700'
                                          : 'text-[#0B1437]'
                                "
                            >
                                {{ t.is_credit ? '+' : '-' }}{{ formatVnd(Math.abs(t.amount)) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
