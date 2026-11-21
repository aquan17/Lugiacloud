<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import OsIcon from '@/Components/OsIcon.vue';
import EventShowcaseBanner from '@/Components/EventShowcaseBanner.vue';
import FlashSaleShowcase from '@/Components/FlashSaleShowcase.vue';
import VoucherCombobox from '@/Components/VoucherCombobox.vue';
import { formatVnd } from '@/Composables/useFormat';
import {
    Globe,
    Tag,
    Loader2,
    ArrowLeft,
    Minus,
    Plus,
    Check,
    Zap,
    ShieldCheck,
    Sparkles,
    Eye,
    EyeOff,
    Shuffle,
    Server,
    Network,
    Lock,
    HelpCircle,
    Ticket
} from 'lucide-vue-next';

const props = defineProps<{
    products: Array<any>;
    billingCycles: Record<string, any>;
    flashSales?: Array<any>;
    huntVouchers?: Array<any>;
    claimedVouchers?: Array<{
        id: number;
        code: string;
        discount_label: string;
        min_order_amount: number;
        max_discount_amount: number | null;
        description?: string;
    }>;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const heroBanners = computed(() => (page.props.promotions as any)?.banners?.hero_showcase || []);

const sp = new URLSearchParams(window.location.search);
const urlPid = Number(sp.get('product_id') || sp.get('product') || 0);
const initialProduct = props.products.find((p) => p.id === urlPid) || props.products[0];

const form = useForm({
    product_id: initialProduct?.id || 12,
    billing_cycle: 'monthly',
    quantity: 1,
    type_proxy: 'HTTP',
    custom_username: '',
    custom_password: '',
    coupon_code: '',
    expected_total: 0,
});

const couponInput = ref('');
const quoteLoading = ref(false);
const liveQuote = ref<any>(null);
const syncedQuoteKey = ref<string>('');
const showPassword = ref(false);
const categoryFilter = ref<'all' | 'datacenter' | 'residential'>('all');

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let abortController: AbortController | null = null;
let reqSeq = 0;

const selectedProduct = computed(() => props.products.find((p) => p.id === form.product_id) || props.products[0]);

const filteredProducts = computed(() => {
    if (categoryFilter.value === 'all') return props.products;
    return props.products.filter((p) => p.category === categoryFilter.value);
});

function clampQuantity(val: number): number {
    if (!Number.isFinite(val)) return 1;
    return Math.min(10, Math.max(1, Math.round(val)));
}

const safeQuantity = computed(() => clampQuantity(Number(form.quantity || 1)));
const currentQuoteKey = computed(() => `${form.product_id}|${form.billing_cycle}|${safeQuantity.value}|${form.coupon_code}`);

/**
 * Báo giá Proxy tức thì (0ms, 60fps) tính toán ngay tại client khi người dùng đổi gói/số lượng.
 */
const instantQuote = computed(() => {
    const prod = selectedProduct.value;
    const qty = safeQuantity.value;
    const unitList = Number(prod?.list_price || prod?.price || 0);
    const unitFinal = Number(prod?.price || unitList);

    const listTotal = unitList * qty;
    const afterAuto = unitFinal * qty;
    const autoDiscount = Math.max(0, listTotal - afterAuto);

    const prodFlash = prod?.flash_sale || null;
    if (
        prodFlash &&
        qty <= Number(prodFlash.max_per_user || 1) &&
        qty <= Number(prodFlash.remaining_slots || 0)
    ) {
        return {
            list_price: listTotal,
            final_price: afterAuto,
            discount_amount: autoDiscount,
            campaign: null,
            flash_sale: {
                ...prodFlash,
                discount_amount: autoDiscount,
            },
            coupon: null,
        };
    }

    const couponObj = liveQuote.value?.coupon || null;
    const couponDiscount = couponObj?.valid && form.coupon_code ? Math.min(afterAuto, Number(couponObj.discount_amount || 0)) : 0;
    const finalTotal = Math.max(0, afterAuto - couponDiscount);

    return {
        list_price: listTotal,
        final_price: finalTotal,
        discount_amount: Math.max(0, listTotal - finalTotal),
        campaign:
            prod?.campaign && autoDiscount > 0
                ? {
                      ...prod.campaign,
                      discount_amount: autoDiscount,
                  }
                : null,
        flash_sale: null,
        coupon: form.coupon_code ? couponObj : null,
    };
});

const displayQuote = computed(() => {
    if (liveQuote.value && syncedQuoteKey.value === currentQuoteKey.value) {
        return liveQuote.value;
    }
    return instantQuote.value;
});

const totalPrice = computed(() => displayQuote.value.final_price);
const listPrice = computed(() => displayQuote.value.list_price);
const isQuoteSyncing = computed(() => syncedQuoteKey.value !== currentQuoteKey.value);
const insufficientBalance = computed(() => (user.value?.balance || 0) < totalPrice.value);

function stepQuantity(delta: number) {
    form.quantity = clampQuantity(Number(form.quantity || 1) + delta);
}

function generateRandomCredentials() {
    const randUser = 'lg' + Math.random().toString(36).substring(2, 9);
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    let randPass = '';
    for (let i = 0; i < 12; i++) {
        randPass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    form.custom_username = randUser;
    form.custom_password = randPass;
}

async function fetchQuoteNow() {
    if (!selectedProduct.value) return;
    if (debounceTimer) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }
    if (abortController) {
        abortController.abort();
    }

    const qty = safeQuantity.value;
    const targetKey = currentQuoteKey.value;
    const mySeq = ++reqSeq;
    abortController = new AbortController();
    quoteLoading.value = true;

    try {
        const { data } = await axios.post(
            '/promotions/quote',
            {
                service_type: 'proxy',
                product_id: form.product_id,
                billing_cycle: form.billing_cycle,
                quantity: qty,
                coupon_code: form.coupon_code || undefined,
            },
            { signal: abortController.signal }
        );

        if (mySeq === reqSeq) {
            liveQuote.value = data;
            syncedQuoteKey.value = targetKey;
        }
    } catch (err: any) {
        if (axios.isCancel(err) || err?.code === 'ERR_CANCELED') {
            return;
        }
        if (mySeq === reqSeq) {
            liveQuote.value = null;
            syncedQuoteKey.value = targetKey;
        }
    } finally {
        if (mySeq === reqSeq) {
            quoteLoading.value = false;
        }
    }
}

function scheduleQuoteSync() {
    if (debounceTimer) clearTimeout(debounceTimer);
    if (abortController) abortController.abort();
    quoteLoading.value = true;
    debounceTimer = setTimeout(() => {
        fetchQuoteNow();
    }, 150);
}

function applyCoupon(code?: string) {
    const nextCode = (code !== undefined ? code : couponInput.value).trim().toUpperCase();
    couponInput.value = nextCode;
    if (nextCode === form.coupon_code) return;
    form.coupon_code = nextCode;
    fetchQuoteNow();
}

const preCouponOrderAmount = computed(() => {
    const q = displayQuote.value;
    const base = Number(q?.list_price || 0);
    const camp = Number(q?.campaign?.discount_amount || 0);
    const usr = Number(q?.user_discount_amount || 0);
    return Math.max(0, base - camp - usr);
});

watch(
    () => [form.product_id, form.quantity],
    () => {
        if (form.quantity < 1 || form.quantity > 10) {
            form.quantity = clampQuantity(form.quantity);
        }
        scheduleQuoteSync();
    }
);

onMounted(() => fetchQuoteNow());

onUnmounted(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
    if (abortController) abortController.abort();
});

async function submit() {
    if (form.processing) return;
    form.quantity = clampQuantity(form.quantity);

    if (isQuoteSyncing.value) {
        const seen = totalPrice.value;
        await fetchQuoteNow();
        if (totalPrice.value !== seen || insufficientBalance.value) {
            return;
        }
    }

    if (insufficientBalance.value) return;

    form.expected_total = totalPrice.value;
    form.post('/proxy/store');
}
</script>

<template>
    <ClientLayout title="Đăng ký Proxy IPv4">
        <Head title="Mua Proxy IPv4 Việt Nam — LugiaCloud" />

        <div class="space-y-6">
            <!-- Promotional Campaign Banner (If any) -->
            <EventShowcaseBanner v-if="heroBanners.length > 0" :banners="heroBanners" />

            <!-- Flash Sale & Voucher Hunt Showcase -->
            <FlashSaleShowcase
                v-if="(flashSales && flashSales.length > 0) || (huntVouchers && huntVouchers.length > 0)"
                :flash-sales="flashSales"
                :hunt-vouchers="huntVouchers"
                compact
            />

            <!-- Breadcrumb Navigation -->
            <div class="flex items-center justify-between">
                <Link
                    href="/proxy"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-[#0B1437] transition-colors"
                >
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Quay lại Quản lý Proxy IPv4</span>
                </Link>

                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-md bg-blue-50 text-[#1D6AE5] border border-blue-200/80 text-xs font-semibold">
                        Datacenter FPT (TP.HCM)
                    </span>
                    <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-xs font-semibold flex items-center gap-1">
                        <Zap class="w-3 h-3 text-emerald-600" /> Ping &lt; 15ms
                    </span>
                </div>
            </div>

            <!-- Main Order Form (SaaS 2-Column Responsive Layout) -->
            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left Column (8 cols): Step by Step Wizard -->
                <div class="lg:col-span-8 space-y-5">
                    <!-- STEP 1: Hạ tầng & Trung tâm dữ liệu (Tương đồng chuẩn VPS Store) -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">1</span>
                                <span>Vị trí & Hạ tầng Trung tâm dữ liệu</span>
                            </div>
                            <span class="text-xs text-slate-700 font-semibold">100% IP Sạch tại Việt Nam</span>
                        </div>

                        <div class="p-4 rounded-xl border border-[#1D6AE5] bg-blue-50/30 ring-1 ring-[#1D6AE5]/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3.5">
                                <div class="w-11 h-11 rounded-xl bg-white border border-blue-200 flex items-center justify-center shrink-0 shadow-2xs">
                                    <OsIcon distro="vietnam" :size="24" />
                                </div>
                                <div>
                                    <div class="text-sm font-extrabold text-[#0B1437] flex items-center gap-2">
                                        <span>FPT Telecom Datacenter (TP.HCM)</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                            Hoạt động 24/7
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-600 font-medium mt-0.5">
                                        Cổng mạng Uplink 1 Gbps sạch · Anti-DDoS tự động · Độ trễ nội địa cực thấp
                                    </div>
                                </div>
                            </div>

                            <span class="font-mono-num text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-lg shrink-0 flex items-center gap-1 self-start sm:self-auto">
                                <Zap class="w-3.5 h-3.5 text-emerald-600" /> Ping &lt; 15ms
                            </span>
                        </div>
                    </div>

                    <!-- STEP 2: Chọn gói phân loại Proxy IPv4 (Có hiển thị đầy đủ Giá gốc + Badge giảm giá) -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                    <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">2</span>
                                    <span>Chọn phân loại gói Proxy IPv4</span>
                                </div>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">
                                    Hỗ trợ IP Datacenter tốc độ cao và IP Dân cư tĩnh (Residential) độ trust cao.
                                </p>
                            </div>

                            <!-- Filter Category Tabs -->
                            <div class="inline-flex p-1 rounded-lg bg-slate-100 border border-slate-200 text-xs self-start">
                                <button
                                    type="button"
                                    @click="categoryFilter = 'all'"
                                    class="px-3 py-1 rounded-md font-bold transition cursor-pointer"
                                    :class="categoryFilter === 'all' ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    Tất cả ({{ products.length }})
                                </button>
                                <button
                                    type="button"
                                    @click="categoryFilter = 'datacenter'"
                                    class="px-3 py-1 rounded-md font-bold transition cursor-pointer"
                                    :class="categoryFilter === 'datacenter' ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    Datacenter
                                </button>
                                <button
                                    type="button"
                                    @click="categoryFilter = 'residential'"
                                    class="px-3 py-1 rounded-md font-bold transition cursor-pointer"
                                    :class="categoryFilter === 'residential' ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    Dân cư VN
                                </button>
                            </div>
                        </div>

                        <!-- 4 Plan Cards Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <button
                                v-for="p in filteredProducts"
                                :key="p.id"
                                type="button"
                                @click="form.product_id = p.id"
                                class="p-4 sm:p-5 rounded-xl border text-left transition-all duration-150 cursor-pointer flex flex-col justify-between group relative"
                                :class="
                                    form.product_id === p.id
                                        ? 'border-[#1D6AE5] bg-blue-50/30 ring-2 ring-[#1D6AE5] shadow-xs'
                                        : 'border-slate-200 hover:border-slate-300 bg-white hover:shadow-2xs'
                                "
                            >
                                <!-- Top Row: Badges, Categories, Active Check -->
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <!-- Category Badge -->
                                            <span
                                                class="text-[10.5px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                                :class="
                                                    p.category === 'residential'
                                                        ? 'bg-purple-50 text-purple-700 border-purple-200'
                                                        : 'bg-blue-50 text-[#1D6AE5] border-blue-200'
                                                "
                                            >
                                                {{ p.category === 'residential' ? 'Dân cư Tĩnh' : 'Datacenter' }}
                                            </span>

                                            <!-- Dedicated / Shared Tag -->
                                            <span
                                                class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md border"
                                                :class="
                                                    p.dedicated
                                                        ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                        : 'bg-slate-100 text-slate-700 border-slate-200'
                                                "
                                            >
                                                {{ p.dedicated ? 'Riêng 1:1' : 'Chia sẻ' }}
                                            </span>

                                            <!-- Flash Sale Synced Badge -->
                                            <span
                                                v-if="p.flash_sale"
                                                class="inline-flex items-center gap-1 text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-red-50 text-red-700 border border-red-200"
                                            >
                                                <Zap class="w-3 h-3 fill-red-600 text-red-600" />
                                                Flash Sale · Còn {{ p.flash_sale.remaining_slots }}/{{ p.flash_sale.total_slots }}
                                            </span>

                                            <!-- Best Seller Badge -->
                                            <span
                                                v-else-if="p.badge"
                                                class="text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-amber-500 text-white shadow-2xs"
                                            >
                                                {{ p.badge }}
                                            </span>
                                        </div>

                                        <!-- Active Check Icon -->
                                        <div
                                            class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 transition"
                                            :class="
                                                form.product_id === p.id
                                                    ? 'bg-[#1D6AE5] text-white'
                                                    : 'border border-slate-200 text-transparent group-hover:border-slate-300'
                                            "
                                        >
                                            <Check class="w-3.5 h-3.5 stroke-[3]" />
                                        </div>
                                    </div>

                                    <!-- Title & Description -->
                                    <div class="text-[15px] font-extrabold text-[#0B1437] mt-1 group-hover:text-[#1D6AE5] transition-colors">
                                        {{ p.title }}
                                    </div>
                                    <p class="text-xs text-slate-600 font-medium mt-1 leading-relaxed line-clamp-2">
                                        {{ p.description }}
                                    </p>

                                    <!-- Feature Specs Tags -->
                                    <div class="mt-3 pt-3 border-t border-slate-100 grid grid-cols-2 gap-2 text-xs">
                                        <div class="flex items-center gap-1.5 text-slate-600 font-medium">
                                            <Network class="w-3.5 h-3.5 text-[#1D6AE5] shrink-0" />
                                            <span>HTTP & SOCKS5</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-slate-600 font-medium">
                                            <Zap class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                            <span>Băng thông 1 Gbps</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bottom Pricing Area with EXPLICIT DISCOUNT (Gói hiện rõ giá niêm yết + Tiết kiệm) -->
                                <div class="mt-4 pt-3.5 border-t border-slate-100/90">
                                    <div v-if="p.has_discount || p.list_price > p.price" class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-mono-num text-slate-500 line-through font-semibold">
                                            {{ formatVnd(p.list_price) }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200 text-[11px] font-bold font-mono-num flex items-center gap-1">
                                            <Tag class="w-3 h-3" />
                                            <span>Tiết kiệm {{ formatVnd(p.discount_amount || (p.list_price - p.price)) }}</span>
                                            <span>(-{{ Math.round(((p.discount_amount || (p.list_price - p.price)) / p.list_price) * 100) }}%)</span>
                                        </span>
                                    </div>

                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-2xl font-black font-mono-num text-[#0B1437]">
                                            {{ formatVnd(p.price) }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-600">/ IP / Tháng</span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: Giao thức kết nối chính (HTTP / SOCKS5 với mô tả chuyên nghiệp) -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-3.5">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                    <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">3</span>
                                    <span>Giao thức kết nối ưu tiên</span>
                                </div>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">
                                    Hệ thống cấp sẵn cả 2 cổng HTTP và SOCKS5 song song trên từng IP. Bạn có thể chọn cổng ưu tiên hiển thị.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <button
                                type="button"
                                @click="form.type_proxy = 'HTTP'"
                                class="p-4 rounded-xl border text-left transition cursor-pointer flex items-start gap-3"
                                :class="
                                    form.type_proxy === 'HTTP'
                                        ? 'border-[#1D6AE5] bg-blue-50/30 ring-2 ring-[#1D6AE5] shadow-xs'
                                        : 'border-slate-200 hover:border-slate-300 bg-white'
                                "
                            >
                                <div
                                    class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                                    :class="form.type_proxy === 'HTTP' ? 'bg-[#1D6AE5] text-white' : 'bg-slate-100 text-slate-600'"
                                >
                                    <Globe class="w-5 h-5" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-extrabold text-[#0B1437]">HTTP / HTTPS</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-[#1D6AE5]">Cổng 8080</span>
                                    </div>
                                    <p class="text-xs text-slate-600 font-medium mt-1 leading-relaxed">
                                        Khuyên dùng cho Web, Trình duyệt Antidetect (Gologin, AdsPower), Tool crawl dữ liệu.
                                    </p>
                                </div>
                            </button>

                            <button
                                type="button"
                                @click="form.type_proxy = 'SOCKS5'"
                                class="p-4 rounded-xl border text-left transition cursor-pointer flex items-start gap-3"
                                :class="
                                    form.type_proxy === 'SOCKS5'
                                        ? 'border-[#1D6AE5] bg-blue-50/30 ring-2 ring-[#1D6AE5] shadow-xs'
                                        : 'border-slate-200 hover:border-slate-300 bg-white'
                                "
                            >
                                <div
                                    class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                                    :class="form.type_proxy === 'SOCKS5' ? 'bg-[#1D6AE5] text-white' : 'bg-slate-100 text-slate-600'"
                                >
                                    <Zap class="w-5 h-5" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-extrabold text-[#0B1437]">SOCKS5 Socket</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Cổng 1080</span>
                                    </div>
                                    <p class="text-xs text-slate-600 font-medium mt-1 leading-relaxed">
                                        Mã hóa socket tầng thấp, tối ưu cho Game, Giả lập Android (LDPlayer), Telegram & MMO Bot.
                                    </p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 4: Số lượng & Thông tin xác thực tùy chỉnh -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">4</span>
                            <span>Số lượng IP & Thông tin xác thực</span>
                        </div>

                        <!-- Quantity Stepper -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-800">
                                        Số lượng địa chỉ IPv4 cần cấp phát
                                    </label>
                                    <p class="text-xs text-slate-600 font-medium mt-0.5">
                                        Tối đa 10 IP mỗi đơn hàng. Mỗi IP có tài khoản riêng độc lập.
                                    </p>
                                </div>

                                <div class="flex items-center gap-2 self-start sm:self-auto">
                                    <div class="inline-flex items-center rounded-xl border border-slate-300 bg-white shadow-2xs overflow-hidden">
                                        <button
                                            type="button"
                                            @click="stepQuantity(-1)"
                                            class="px-3.5 py-2 hover:bg-slate-100 text-slate-700 cursor-pointer transition active:scale-95"
                                            aria-label="Giảm 1 IP"
                                        >
                                            <Minus class="w-4 h-4" />
                                        </button>
                                        <input
                                            v-model.number="form.quantity"
                                            type="number"
                                            min="1"
                                            max="10"
                                            class="w-14 py-2 text-center text-sm font-mono-num font-black text-[#0B1437] focus:outline-none border-x border-slate-200"
                                        />
                                        <button
                                            type="button"
                                            @click="stepQuantity(1)"
                                            class="px-3.5 py-2 hover:bg-slate-100 text-slate-700 cursor-pointer transition active:scale-95"
                                            aria-label="Tăng 1 IP"
                                        >
                                            <Plus class="w-4 h-4" />
                                        </button>
                                    </div>

                                    <!-- Quick Presets -->
                                    <div class="flex gap-1">
                                        <button
                                            v-for="q in [1, 2, 5, 10]"
                                            :key="q"
                                            type="button"
                                            @click="form.quantity = q"
                                            class="px-3 py-2 rounded-xl border text-xs font-mono-num font-bold cursor-pointer transition"
                                            :class="
                                                safeQuantity === q
                                                    ? 'border-[#1D6AE5] bg-[#1D6AE5] text-white shadow-xs'
                                                    : 'border-slate-300 bg-white hover:bg-slate-100 text-slate-700'
                                            "
                                        >
                                            x{{ q }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Credentials Inputs -->
                        <div class="space-y-3 pt-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800">Tài khoản xác thực Proxy (Tùy chọn)</span>
                                <button
                                    type="button"
                                    @click="generateRandomCredentials"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-[#1D6AE5] hover:text-[#1557C0] cursor-pointer"
                                >
                                    <Shuffle class="w-3.5 h-3.5" />
                                    <span>Tạo ngẫu nhiên an toàn</span>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Tên đăng nhập (Username)</label>
                                    <input
                                        v-model="form.custom_username"
                                        type="text"
                                        placeholder="Để trống = sinh tự động"
                                        class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-mono font-medium focus:outline-none focus:border-[#1D6AE5] focus:ring-1 focus:ring-[#1D6AE5]"
                                    />
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-xs font-semibold text-slate-600">Mật khẩu (Password)</label>
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="text-[11px] font-bold text-slate-700 hover:text-slate-900 cursor-pointer inline-flex items-center gap-1"
                                        >
                                            <component :is="showPassword ? EyeOff : Eye" class="w-3 h-3" />
                                            <span>{{ showPassword ? 'Ẩn' : 'Hiện' }}</span>
                                        </button>
                                    </div>
                                    <input
                                        v-model="form.custom_password"
                                        :type="showPassword ? 'text' : 'password'"
                                        placeholder="Để trống = sinh tự động"
                                        class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-mono font-medium focus:outline-none focus:border-[#1D6AE5] focus:ring-1 focus:ring-[#1D6AE5]"
                                    />
                                </div>
                            </div>
                            <p class="text-xs text-slate-600 font-medium">
                                * Lưu ý: Tên đăng nhập và mật khẩu chỉ gồm chữ cái và số (alpha-numeric), không dấu và không chứa ký tự đặc biệt.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Column (4 cols): Sticky Order Summary Sidebar -->
                <div class="lg:col-span-4 lg:sticky lg:top-20 space-y-4">
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                        <!-- Header with Status -->
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                            <div class="flex items-center gap-2">
                                <Globe class="w-4 h-4 text-[#1D6AE5]" />
                                <h3 class="text-sm font-extrabold text-[#0B1437]">Tóm tắt đơn hàng Proxy</h3>
                            </div>
                            <span v-if="isQuoteSyncing" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#1D6AE5]">
                                <Loader2 class="w-3.5 h-3.5 animate-spin" />
                                <span>Đang chốt giá...</span>
                            </span>
                        </div>

                        <!-- Spec List -->
                        <dl class="space-y-2.5 text-xs">
                            <div class="flex justify-between">
                                <dt class="text-slate-600 font-medium">Gói dịch vụ</dt>
                                <dd class="font-bold text-[#0B1437] text-right">{{ selectedProduct?.title }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-600 font-medium">Hạ tầng & Vị trí</dt>
                                <dd class="font-semibold text-slate-800">FPT Datacenter HCM</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-600 font-medium">Giao thức & Port</dt>
                                <dd class="font-mono-num font-bold text-[#0B1437]">{{ form.type_proxy }} & SOCKS5 (2 cổng)</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-600 font-medium">Số lượng cấp phát</dt>
                                <dd class="font-mono-num font-extrabold text-[#1D6AE5]">{{ safeQuantity }} địa chỉ IPv4</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-600 font-medium">Thời hạn thuê</dt>
                                <dd class="font-semibold text-slate-800">1 Tháng (hỗ trợ tự gia hạn)</dd>
                            </div>
                        </dl>

                        <!-- Active Flash Sale Notice -->
                        <div
                            v-if="displayQuote?.flash_sale"
                            class="p-3 rounded-xl bg-red-50/90 border border-red-200 text-xs space-y-1"
                        >
                            <div class="flex items-center justify-between gap-2 font-extrabold text-[#0B1437]">
                                <span class="inline-flex items-center gap-1.5 text-red-600">
                                    <Zap class="w-3.5 h-3.5 fill-red-600 text-red-600" />
                                    <span>{{ displayQuote.flash_sale.badge_text }}</span>
                                </span>
                                <span class="font-mono-num text-[11px] text-red-700 font-bold">
                                    Còn {{ displayQuote.flash_sale.remaining_slots }}/{{ displayQuote.flash_sale.total_slots }} suất
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600 font-medium">
                                Đang áp dụng giá Flash Sale (tối đa {{ displayQuote.flash_sale.max_per_user }} suất/KH, không dùng kèm voucher).
                            </p>
                        </div>

                        <!-- Coupon Input + Smart Voucher Wallet Dropdown -->
                        <div class="pt-3 border-t border-slate-100">
                            <VoucherCombobox
                                v-model="couponInput"
                                :applied-code="form.coupon_code"
                                :vouchers="claimedVouchers"
                                :order-amount="preCouponOrderAmount"
                                :disabled="Boolean(displayQuote?.flash_sale)"
                                :loading="quoteLoading"
                                :coupon-feedback="displayQuote?.coupon"
                                placeholder="Nhập mã (VD: PROXY15)"
                                @apply="applyCoupon"
                            />
                        </div>

                        <!-- Price Breakdown -->
                        <div class="pt-3 border-t border-slate-200 space-y-2 text-xs">
                            <div class="flex justify-between text-slate-600 font-medium">
                                <span>Giá niêm yết ({{ safeQuantity }} IP)</span>
                                <span class="font-mono-num font-semibold text-slate-800">{{ formatVnd(listPrice) }}</span>
                            </div>

                            <!-- Flash Sale Discount -->
                            <div v-if="displayQuote?.flash_sale" class="flex justify-between text-red-600 font-bold">
                                <span class="flex items-center gap-1">
                                    <Zap class="w-3.5 h-3.5 fill-red-600 text-red-600" />
                                    <span>{{ displayQuote.flash_sale.badge_text }}</span>
                                </span>
                                <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.flash_sale.discount_amount) }}</span>
                            </div>

                            <!-- Discount Campaign -->
                            <div v-if="displayQuote?.campaign" class="flex justify-between text-emerald-700 font-semibold">
                                <span class="flex items-center gap-1">
                                    <Tag class="w-3.5 h-3.5" />
                                    <span>{{ displayQuote.campaign.badge_text }}</span>
                                </span>
                                <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.campaign.discount_amount) }}</span>
                            </div>

                            <!-- Coupon Discount -->
                            <div v-if="displayQuote?.coupon?.valid" class="flex justify-between text-emerald-700 font-semibold">
                                <span>Mã ưu đãi ({{ displayQuote.coupon.code }})</span>
                                <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.coupon.discount_amount) }}</span>
                            </div>

                            <!-- Final Total -->
                            <div class="pt-3 mt-2 border-t border-slate-200 flex items-baseline justify-between">
                                <span class="font-bold text-slate-900 text-sm">Tổng thanh toán</span>
                                <span class="text-2xl font-black font-mono-num text-[#0B1437]">
                                    {{ formatVnd(totalPrice) }}
                                </span>
                            </div>

                            <!-- User Balance -->
                            <div class="flex justify-between text-xs pt-1">
                                <span class="text-slate-700 font-medium">Số dư ví hiện tại:</span>
                                <span class="font-mono-num font-bold text-slate-900">{{ formatVnd(user?.balance || 0) }}</span>
                            </div>
                        </div>

                        <!-- Action Button (CTA) -->
                        <Link
                            v-if="insufficientBalance"
                            href="/topup"
                            class="w-full block text-center py-3 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-xs transition"
                        >
                            Số dư ví không đủ — Nạp thêm tiền
                        </Link>
                        <button
                            v-else
                            type="submit"
                            :disabled="form.processing || isQuoteSyncing"
                            class="w-full py-3 px-4 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer inline-flex items-center justify-center gap-2 shadow-xs transition active:scale-95"
                        >
                            <Loader2 v-if="form.processing || isQuoteSyncing" class="w-4 h-4 animate-spin" />
                            <span>
                                {{
                                    form.processing
                                        ? 'Đang cấp phát Proxy...'
                                        : isQuoteSyncing
                                          ? 'Đang chốt giá đơn hàng...'
                                          : `Thanh toán ${formatVnd(totalPrice)} & Nhận IP`
                                }}
                            </span>
                        </button>
                    </div>

                    <!-- Trust & SLA Guarantee Card -->
                    <div class="surface-card p-4 border border-slate-200/90 shadow-xs rounded-2xl space-y-2.5 text-xs">
                        <div class="font-bold text-[#0B1437] flex items-center gap-1.5">
                            <ShieldCheck class="w-4 h-4 text-emerald-600" />
                            <span>Cam kết chất lượng LugiaCloud</span>
                        </div>
                        <ul class="space-y-1.5 text-slate-600 font-medium">
                            <li class="flex items-center gap-2">
                                <Check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <span>100% IP Sạch, kiểm tra blacklist trước khi cấp.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <span>Hỗ trợ đổi IP trong 24h nếu upstream lỗi.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <span>Cấp phát tự động 100% chỉ trong 5-15 giây.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </ClientLayout>
</template>
