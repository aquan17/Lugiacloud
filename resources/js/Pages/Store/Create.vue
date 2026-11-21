<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import OsIcon from '@/Components/OsIcon.vue';
import EventShowcaseBanner from '@/Components/EventShowcaseBanner.vue';
import VoucherCombobox from '@/Components/VoucherCombobox.vue';
import { formatVnd } from '@/Composables/useFormat';
import { Check, Tag, Loader2, Server, ArrowLeft, Minus, Plus, RotateCcw, Zap } from 'lucide-vue-next';

const props = defineProps<{
    selectedPlanSlug: string;
    plan: any;
    allPlans: Array<any>;
    regions: Array<{ code: string; name: string; datacenter: string; ping: string }>;
    billingCycles: Record<string, { months: number; label: string; discount_percent: number }>;
    images: Array<{ id: number; label: string; distro: string; group: string }>;
    addonPrices: { cpu_monthly: number; ram_monthly: number; disk_10gb_monthly: number };
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
const isVpsOutOfStock = computed(() => Boolean((page.props.system as any)?.vps_out_of_stock));
const heroBanners = computed(() => (page.props.promotions as any)?.banners?.hero_showcase || []);
const activeCampaigns = computed<Array<any>>(() => (page.props.promotions as any)?.campaigns || []);

const urlCycle = new URLSearchParams(window.location.search).get('cycle');
const initialCycle = urlCycle && props.billingCycles[urlCycle] ? urlCycle : 'monthly';

const osTab = ref<'Linux' | 'Windows'>('Linux');

const form = useForm({
    plan: props.selectedPlanSlug,
    region: props.regions[0]?.code || 'vn',
    duration: initialCycle,
    quantity: 1,
    label: `lugia-${Math.floor(1000 + Math.random() * 9000)}`,
    image: props.images[0]?.id || 7,
    addon_cpu: 0,
    addon_ram: 0,
    addon_disk: 0,
    coupon_code: '',
    expected_total: 0,
});

const couponInput = ref('');
const quoteLoading = ref(false);
const liveQuote = ref<any>(null);
const syncedQuoteKey = ref<string>('');

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let abortController: AbortController | null = null;
let reqSeq = 0;

const activePlan = computed(() => props.allPlans.find((p) => p.slug === form.plan) || props.plan);
const filteredImages = computed(() => props.images.filter((img) => img.group === osTab.value));
const hasAddons = computed(() => form.addon_cpu > 0 || form.addon_ram > 0 || form.addon_disk > 0);

function clampQuantity(val: number): number {
    if (!Number.isFinite(val)) return 1;
    return Math.min(10, Math.max(1, Math.round(val)));
}

const safeQuantity = computed(() => clampQuantity(Number(form.quantity || 1)));
const labelPreviewRange = computed(() => {
    const base = (form.label || 'lugia').trim().slice(0, 28).replace(/-+$/, '') || 'lugia';
    return `${base}-1 → ${base}-${safeQuantity.value}`;
});

const currentQuoteKey = computed(
    () => `${form.plan}|${form.duration}|${safeQuantity.value}|${form.addon_cpu}|${form.addon_ram}|${form.addon_disk}|${form.coupon_code}`
);

function computeRawCampaignDiscount(basePrice: number, c: any): number {
    if (!c || basePrice <= 0) return 0;
    let raw = 0;
    if (c.discount_type === 'percent') {
        raw = Math.round(basePrice * (Math.min(100, Math.max(0, Number(c.discount_value || 0))) / 100));
    } else if (c.discount_type === 'fixed' || c.discount_type === 'fixed_amount') {
        raw = Math.max(0, Number(c.discount_value || 0));
    }
    if (c.max_discount_amount && Number(c.max_discount_amount) > 0) {
        raw = Math.min(raw, Number(c.max_discount_amount));
    }
    return Math.min(basePrice, raw);
}

/**
 * Báo giá tức thì (0ms, 60fps) tính ngay tại trình duyệt mỗi khi kéo thanh trượt Addon, đổi số lượng VPS hoặc chu kỳ.
 */
const instantQuote = computed(() => {
    const planObj = activePlan.value;
    const qty = safeQuantity.value;
    const cycleMeta = props.billingCycles[form.duration] || { months: 1, discount_percent: 0 };
    const months = Math.max(1, Number(cycleMeta.months || 1));
    const cycleDiscountPct = Math.max(0, Number(cycleMeta.discount_percent || 0));

    const planBaseList =
        planObj?.cycle_quotes?.[form.duration]?.list_price ??
        Math.round(Number(planObj?.list_price_per_month || planObj?.price_per_month || 0) * months * (1 - cycleDiscountPct / 100));

    const addonMonthly =
        form.addon_cpu * props.addonPrices.cpu_monthly +
        form.addon_ram * props.addonPrices.ram_monthly +
        Math.floor(form.addon_disk / 10) * props.addonPrices.disk_10gb_monthly;

    const addonListTotal = Math.round(addonMonthly * months * (1 - cycleDiscountPct / 100));
    const listPrice = (planBaseList + addonListTotal) * qty;

    // Kiểm tra Flash Sale đang gắn trên gói & chu kỳ hiện tại
    const cycleFlash = planObj?.cycle_quotes?.[form.duration]?.flash_sale || null;
    if (
        cycleFlash &&
        qty <= Number(cycleFlash.max_per_user || 1) &&
        qty <= Number(cycleFlash.remaining_slots || 0)
    ) {
        const flashDisc = Number(cycleFlash.discount_amount || 0) * qty;
        const finalFlashPrice = Math.max(0, listPrice - flashDisc);
        return {
            list_price: listPrice,
            final_price: finalFlashPrice,
            discount_amount: Math.max(0, listPrice - finalFlashPrice),
            user_discount_percent: 0,
            user_discount_amount: 0,
            campaign: null,
            flash_sale: {
                ...cycleFlash,
                discount_amount: Math.max(0, listPrice - finalFlashPrice),
            },
            coupon: null,
        };
    }

    const userDiscountPct = Math.min(100, Math.max(0, Number(user.value?.discount_percent || 0)));
    let userDiscountAmount = userDiscountPct > 0 ? Math.round(listPrice * (userDiscountPct / 100)) : 0;

    // Chọn chiến dịch khuyến mãi đang chạy khớp gói & chu kỳ
    let bestCamp: any = null;
    let campDiscount = 0;
    for (const c of activeCampaigns.value) {
        if (!c || c.discount_type === 'none' || Number(c.discount_value || 0) <= 0) continue;
        if (months < Number(c.min_months || 1)) continue;
        if (c.target_scope !== 'all' && c.target_scope !== 'vps') continue;
        if (Array.isArray(c.target_plans) && c.target_plans.length > 0 && !c.target_plans.includes(form.plan)) continue;

        const d = computeRawCampaignDiscount(listPrice, c);
        if (d > campDiscount) {
            campDiscount = d;
            bestCamp = c;
        }
    }

    let autoDiscountTotal = 0;
    if (bestCamp && bestCamp.stack_with_user_discount) {
        const afterUser = Math.max(0, listPrice - userDiscountAmount);
        campDiscount = computeRawCampaignDiscount(afterUser, bestCamp);
        autoDiscountTotal = userDiscountAmount + campDiscount;
    } else if (userDiscountAmount >= campDiscount) {
        autoDiscountTotal = userDiscountAmount;
        if (userDiscountAmount > 0) {
            bestCamp = null;
            campDiscount = 0;
        }
    } else {
        autoDiscountTotal = campDiscount;
        userDiscountAmount = 0;
    }

    const priceAfterAuto = Math.max(0, listPrice - autoDiscountTotal);

    // Giữ tạm mã coupon hợp lệ trước đó trong lúc chờ 160ms server xác nhận lại
    let couponObj = liveQuote.value?.coupon || null;
    const couponDiscount = couponObj?.valid && form.coupon_code ? Math.min(priceAfterAuto, Number(couponObj.discount_amount || 0)) : 0;
    const finalPrice = Math.max(0, priceAfterAuto - couponDiscount);

    return {
        list_price: listPrice,
        final_price: finalPrice,
        discount_amount: Math.max(0, listPrice - finalPrice),
        user_discount_percent: userDiscountPct,
        user_discount_amount: userDiscountAmount,
        campaign: bestCamp
            ? {
                  id: bestCamp.id,
                  name: bestCamp.name,
                  badge_text: bestCamp.badge_text,
                  discount_amount: campDiscount,
              }
            : null,
        coupon: form.coupon_code ? couponObj : null,
    };
});

/**
 * Nếu server đã chốt đúng cấu hình hiện tại -> hiển thị báo giá chuẩn từ server (kèm Cost-Floor & Coupon).
 * Ngay khi kéo thanh trượt lệch 1 nấc -> lập tức chuyển sang instantQuote (0ms độ trễ).
 */
const displayQuote = computed(() => {
    if (liveQuote.value && syncedQuoteKey.value === currentQuoteKey.value) {
        return liveQuote.value;
    }
    return instantQuote.value;
});

const summaryPrice = computed(() => displayQuote.value.final_price);
const summaryListPrice = computed(() => displayQuote.value.list_price);
const isQuoteSyncing = computed(() => syncedQuoteKey.value !== currentQuoteKey.value);
const insufficientBalance = computed(() => (user.value?.balance || 0) < summaryPrice.value);

function selectOsGroup(group: 'Linux' | 'Windows') {
    osTab.value = group;
    const first = props.images.find((i) => i.group === group);
    if (first) form.image = first.id;
}

function stepAddon(field: 'addon_cpu' | 'addon_ram' | 'addon_disk', delta: number, min: number, max: number) {
    form[field] = Math.min(max, Math.max(min, Number(form[field] || 0) + delta));
}

function stepQuantity(delta: number) {
    form.quantity = clampQuantity(Number(form.quantity || 1) + delta);
}

function resetAddons() {
    form.addon_cpu = 0;
    form.addon_ram = 0;
    form.addon_disk = 0;
}

async function fetchLiveQuoteNow() {
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
                service_type: 'vps',
                plan: form.plan,
                billing_cycle: form.duration,
                quantity: qty,
                addon_cpu: form.addon_cpu,
                addon_ram: form.addon_ram,
                addon_disk: form.addon_disk,
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
        // Nếu rớt mạng tạm thời: cho phép dùng báo giá nội bộ, Backend vẫn kiểm tra lại expected_total
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
        fetchLiveQuoteNow();
    }, 160);
}

function applyCoupon(code?: string) {
    const nextCode = (code !== undefined ? code : couponInput.value).trim().toUpperCase();
    couponInput.value = nextCode;
    if (nextCode === form.coupon_code) return;
    form.coupon_code = nextCode;
    fetchLiveQuoteNow();
}

const preCouponOrderAmount = computed(() => {
    const q = displayQuote.value;
    const base = Number(q?.list_price || 0);
    const camp = Number(q?.campaign?.discount_amount || 0);
    const usr = Number(q?.user_discount_amount || 0);
    return Math.max(0, base - camp - usr);
});

watch(
    () => [form.plan, form.duration, form.quantity, form.addon_cpu, form.addon_ram, form.addon_disk],
    () => {
        if (form.quantity < 1 || form.quantity > 10) {
            form.quantity = clampQuantity(form.quantity);
        }
        scheduleQuoteSync();
    }
);

onMounted(() => {
    const curImg = props.images.find((i) => i.id === form.image);
    if (curImg) osTab.value = (curImg.group as 'Linux' | 'Windows') || 'Linux';
    fetchLiveQuoteNow();
});

onUnmounted(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
    if (abortController) abortController.abort();
});

async function submit() {
    if (form.processing) return;
    form.quantity = clampQuantity(form.quantity);

    // Nếu người dùng kéo thanh trượt xong bấm Enter/Click cực nhanh khi request chốt giá còn đang bay -> chờ chốt xong và kiểm tra lại
    if (isQuoteSyncing.value) {
        const priceSeenBeforeSync = summaryPrice.value;
        await fetchLiveQuoteNow();
        if (summaryPrice.value !== priceSeenBeforeSync || insufficientBalance.value) {
            return;
        }
    }

    if (insufficientBalance.value) return;

    form.expected_total = summaryPrice.value;
    form.post('/store');
}
</script>

<template>
    <ClientLayout title="Trình khởi tạo Cloud VPS">
        <Head :title="`Khởi tạo ${activePlan?.name}`" />

        <div class="space-y-6">
            <!-- Event Showcase Banner -->
            <EventShowcaseBanner v-if="heroBanners.length > 0" :banners="heroBanners" />

            <!-- Breadcrumb Navigation -->
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Link href="/store" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-[#0B1437] transition-colors whitespace-nowrap">
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Danh sách gói cấu hình</span>
                </Link>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="hidden sm:inline-block px-2.5 py-0.5 rounded-md bg-blue-50 text-[#1D6AE5] border border-blue-200/80 text-xs font-semibold whitespace-nowrap">
                        Datacenter FPT (TP.HCM)
                    </span>
                    <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-xs font-semibold inline-flex items-center gap-1 whitespace-nowrap">
                        <Zap class="w-3 h-3 text-emerald-600" /> Ping &lt; 15ms
                    </span>
                </div>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left Steps Column -->
                <div class="lg:col-span-8 space-y-5">
                    <!-- STEP 1: Region -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">1</span>
                                <span>Vị trí Trung tâm dữ liệu</span>
                            </div>
                            <span class="text-xs text-slate-500 font-medium">Hạ tầng Tier III nội địa</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button
                                v-for="reg in regions"
                                :key="reg.code"
                                type="button"
                                @click="form.region = reg.code"
                                class="p-3.5 rounded-xl border text-left flex items-center justify-between transition-colors cursor-pointer"
                                :class="
                                    form.region === reg.code
                                        ? 'border-[#1D6AE5] bg-blue-50/35 ring-1 ring-[#1D6AE5]'
                                        : 'border-slate-200 hover:border-slate-300 bg-white'
                                "
                            >
                                <div class="flex items-center gap-3">
                                    <OsIcon distro="vietnam" :size="22" />
                                    <div>
                                        <div class="text-xs font-bold text-[#0B1437]">{{ reg.name }}</div>
                                        <div class="text-xs text-slate-600 font-medium">{{ reg.datacenter }}</div>
                                    </div>
                                </div>
                                <span class="font-mono-num text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded shrink-0">
                                    {{ reg.ping }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: OS Image -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl">
                        <div class="flex items-center justify-between gap-3 mb-3.5">
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">2</span>
                                <span>Hệ điều hành (OS Image)</span>
                            </div>
                            <div class="inline-flex p-0.5 rounded-lg bg-slate-100 border border-slate-200 text-xs">
                                <button
                                    type="button"
                                    @click="selectOsGroup('Linux')"
                                    class="px-3 py-1 rounded-md font-semibold cursor-pointer"
                                    :class="osTab === 'Linux' ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-600'"
                                >
                                    Linux
                                </button>
                                <button
                                    type="button"
                                    @click="selectOsGroup('Windows')"
                                    class="px-3 py-1 rounded-md font-semibold cursor-pointer"
                                    :class="osTab === 'Windows' ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-600'"
                                >
                                    Windows
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                            <button
                                v-for="img in filteredImages"
                                :key="img.id"
                                type="button"
                                @click="form.image = img.id"
                                class="p-3 rounded-xl border text-left flex items-center gap-2.5 transition-colors cursor-pointer"
                                :class="
                                    form.image === img.id
                                        ? 'border-[#1D6AE5] bg-blue-50/35 ring-1 ring-[#1D6AE5]'
                                        : 'border-slate-200 hover:border-slate-300 bg-white'
                                "
                            >
                                <OsIcon :distro="img.distro" :size="20" />
                                <span class="text-xs font-semibold text-slate-800 truncate">{{ img.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: Plan Selection & Custom Hardware Addons -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-5">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">3</span>
                            <span>Cấu hình phần cứng</span>
                        </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                        <button
                            v-for="p in allPlans"
                            :key="p.slug"
                            type="button"
                            @click="form.plan = p.slug"
                            class="p-3.5 rounded-lg border text-left transition-colors cursor-pointer"
                            :class="
                                form.plan === p.slug
                                    ? 'border-[#1D6AE5] bg-blue-50/35 ring-1 ring-[#1D6AE5]'
                                    : 'border-slate-200 hover:border-slate-300 bg-white'
                            "
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[#0B1437]">{{ p.name }}</span>
                                <Check v-if="form.plan === p.slug" class="w-3.5 h-3.5 text-[#1D6AE5]" />
                            </div>
                            <div class="text-[11px] font-mono-num text-slate-600 mt-1">
                                {{ p.cores }}C · {{ p.ram }}GB · {{ p.disk }}GB SSD
                            </div>
                            <div class="mt-2">
                                <div v-if="p.list_price_per_month && p.list_price_per_month > p.price_per_month" class="flex items-center gap-1.5 mb-0.5">
                                    <span class="text-[11px] font-mono-num text-slate-400 line-through">{{ formatVnd(p.list_price_per_month) }}</span>
                                    <span class="px-1.5 py-0.2 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold">
                                        -{{ Math.round((1 - p.price_per_month / p.list_price_per_month) * 100) }}%
                                    </span>
                                </div>
                                <div class="text-xs font-mono-num font-bold text-[#0B1437]">
                                    {{ formatVnd(p.price_per_month) }}/tháng
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- Optional Resource Addons (0ms Realtime + Stepper Buttons) -->
                    <div class="pt-4 border-t border-slate-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700">Mở rộng tài nguyên tùy chọn</span>
                            <button
                                v-if="hasAddons"
                                type="button"
                                @click="resetAddons"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 hover:text-[#1D6AE5] cursor-pointer"
                            >
                                <RotateCcw class="w-3 h-3" />
                                <span>Đặt lại +0</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                            <!-- Addon vCPU -->
                            <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/80 space-y-2.5">
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="font-bold text-slate-800 whitespace-nowrap">Thêm vCPU</span>
                                    <div class="inline-flex items-center gap-1 shrink-0">
                                        <button
                                            type="button"
                                            @click="stepAddon('addon_cpu', -1, 0, 16)"
                                            class="w-6 h-6 rounded border border-slate-200 bg-white hover:bg-slate-100 flex items-center justify-center text-slate-600 cursor-pointer"
                                            aria-label="Giảm CPU"
                                        >
                                            <Minus class="w-3 h-3" />
                                        </button>
                                        <span class="min-w-[58px] text-center font-mono-num font-bold text-[#1D6AE5]">
                                            +{{ form.addon_cpu }} Core
                                        </span>
                                        <button
                                            type="button"
                                            @click="stepAddon('addon_cpu', 1, 0, 16)"
                                            class="w-6 h-6 rounded border border-slate-200 bg-white hover:bg-slate-100 flex items-center justify-center text-slate-600 cursor-pointer"
                                            aria-label="Tăng CPU"
                                        >
                                            <Plus class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                                <input v-model.number="form.addon_cpu" type="range" min="0" max="16" step="1" class="w-full accent-[#1D6AE5] cursor-pointer" />
                                <div class="text-xs font-semibold text-slate-600">
                                    <span class="text-[13px] font-extrabold font-mono-num text-[#0B1437]">+{{ formatVnd(addonPrices.cpu_monthly) }}</span>/core/tháng
                                </div>
                            </div>

                            <!-- Addon RAM -->
                            <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/80 space-y-2.5">
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="font-bold text-slate-800 whitespace-nowrap">Thêm RAM</span>
                                    <div class="inline-flex items-center gap-1 shrink-0">
                                        <button
                                            type="button"
                                            @click="stepAddon('addon_ram', -1, 0, 64)"
                                            class="w-6 h-6 rounded border border-slate-200 bg-white hover:bg-slate-100 flex items-center justify-center text-slate-600 cursor-pointer"
                                            aria-label="Giảm RAM"
                                        >
                                            <Minus class="w-3 h-3" />
                                        </button>
                                        <span class="min-w-[52px] text-center font-mono-num font-bold text-[#1D6AE5]">
                                            +{{ form.addon_ram }} GB
                                        </span>
                                        <button
                                            type="button"
                                            @click="stepAddon('addon_ram', 1, 0, 64)"
                                            class="w-6 h-6 rounded border border-slate-200 bg-white hover:bg-slate-100 flex items-center justify-center text-slate-600 cursor-pointer"
                                            aria-label="Tăng RAM"
                                        >
                                            <Plus class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                                <input v-model.number="form.addon_ram" type="range" min="0" max="64" step="1" class="w-full accent-[#1D6AE5] cursor-pointer" />
                                <div class="text-xs font-semibold text-slate-600">
                                    <span class="text-[13px] font-extrabold font-mono-num text-[#0B1437]">+{{ formatVnd(addonPrices.ram_monthly) }}</span>/GB/tháng
                                </div>
                            </div>

                            <!-- Addon SSD -->
                            <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/80 space-y-2.5">
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="font-bold text-slate-800 whitespace-nowrap">Thêm SSD</span>
                                    <div class="inline-flex items-center gap-1 shrink-0">
                                        <button
                                            type="button"
                                            @click="stepAddon('addon_disk', -10, 0, 200)"
                                            class="w-6 h-6 rounded border border-slate-200 bg-white hover:bg-slate-100 flex items-center justify-center text-slate-600 cursor-pointer"
                                            aria-label="Giảm SSD"
                                        >
                                            <Minus class="w-3 h-3" />
                                        </button>
                                        <span class="min-w-[56px] text-center font-mono-num font-bold text-[#1D6AE5]">
                                            +{{ form.addon_disk }} GB
                                        </span>
                                        <button
                                            type="button"
                                            @click="stepAddon('addon_disk', 10, 0, 200)"
                                            class="w-6 h-6 rounded border border-slate-200 bg-white hover:bg-slate-100 flex items-center justify-center text-slate-600 cursor-pointer"
                                            aria-label="Tăng SSD"
                                        >
                                            <Plus class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                                <input v-model.number="form.addon_disk" type="range" min="0" max="200" step="10" class="w-full accent-[#1D6AE5] cursor-pointer" />
                                <div class="text-xs font-semibold text-slate-600">
                                    <span class="text-[13px] font-extrabold font-mono-num text-[#0B1437]">+{{ formatVnd(addonPrices.disk_10gb_monthly) }}</span>/10GB/tháng
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Billing Cycle, Quantity & Hostname -->
                <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">4</span>
                        <span>Chu kỳ thuê, Số lượng &amp; Định danh</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <button
                            v-for="(meta, cycleKey) in billingCycles"
                            :key="cycleKey"
                            type="button"
                            @click="form.duration = String(cycleKey)"
                            class="p-3 rounded-lg border text-left transition-colors cursor-pointer"
                            :class="
                                form.duration === cycleKey
                                    ? 'border-[#1D6AE5] bg-blue-50/35 ring-1 ring-[#1D6AE5]'
                                    : 'border-slate-200 hover:border-slate-300 bg-white'
                            "
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-900">{{ meta.label }}</span>
                                <span v-if="meta.discount_percent > 0" class="px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700 text-[10px] font-mono-num font-bold">
                                    -{{ meta.discount_percent }}%
                                </span>
                            </div>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                        <!-- Multi-quantity VPS selector (giống bên Proxy) -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Số lượng máy chủ cần tạo (Tối đa 10 VPS/đơn)
                            </label>
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center rounded-lg border border-slate-300 bg-white overflow-hidden">
                                    <button
                                        type="button"
                                        @click="stepQuantity(-1)"
                                        class="px-3 py-2 hover:bg-slate-100 text-slate-600 cursor-pointer"
                                        aria-label="Giảm số lượng VPS"
                                    >
                                        <Minus class="w-3.5 h-3.5" />
                                    </button>
                                    <input
                                        v-model.number="form.quantity"
                                        type="number"
                                        min="1"
                                        max="10"
                                        class="w-12 py-1.5 text-center text-sm font-mono-num font-bold focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        @click="stepQuantity(1)"
                                        class="px-3 py-2 hover:bg-slate-100 text-slate-600 cursor-pointer"
                                        aria-label="Tăng số lượng VPS"
                                    >
                                        <Plus class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    <button
                                        v-for="q in [1, 2, 5, 10]"
                                        :key="q"
                                        type="button"
                                        @click="form.quantity = q"
                                        class="px-2.5 py-1.5 rounded-lg border text-xs font-mono-num font-semibold cursor-pointer transition-colors"
                                        :class="
                                            safeQuantity === q
                                                ? 'border-[#1D6AE5] bg-blue-50 text-[#1D6AE5]'
                                                : 'border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700'
                                        "
                                    >
                                        x{{ q }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Hostname -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Tên định danh máy chủ (Hostname)
                            </label>
                            <input
                                v-model="form.label"
                                type="text"
                                required
                                placeholder="prod-web-01"
                                class="w-full px-3.5 py-2 rounded-lg border border-slate-300 text-sm font-mono focus:outline-none focus:border-[#1D6AE5]"
                            />
                            <p v-if="safeQuantity > 1" class="mt-1 text-[11px] font-mono text-[#1D6AE5]">
                                Hệ thống tự đánh số thứ tự: {{ labelPreviewRange }}
                            </p>
                            <p v-if="form.errors.label" class="mt-1 text-xs text-rose-600">{{ form.errors.label }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sticky Order Summary -->
            <div class="lg:col-span-4 lg:sticky lg:top-20 space-y-4">
                <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div class="flex items-center gap-2">
                            <Server class="w-4 h-4 text-[#1D6AE5]" />
                            <h3 class="text-sm font-extrabold text-[#0B1437]">Tóm tắt cấu hình Cloud VPS</h3>
                        </div>
                        <span v-if="isQuoteSyncing" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#1D6AE5]">
                            <Loader2 class="w-3.5 h-3.5 animate-spin" />
                            <span>Đang chốt giá...</span>
                        </span>
                    </div>

                    <dl class="space-y-2.5 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-slate-600 font-medium">Gói cơ sở &amp; Số lượng</dt>
                            <dd class="font-bold text-slate-900">
                                {{ activePlan?.name }} <span class="font-mono-num text-[#1D6AE5]">× {{ safeQuantity }} VPS</span>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-600 font-medium">Mỗi máy (vCPU / RAM)</dt>
                            <dd class="font-mono-num font-bold text-slate-900">
                                {{ (activePlan?.cores || 1) + form.addon_cpu }} vCPU · {{ (activePlan?.ram || 1) + form.addon_ram }} GB
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-600 font-medium">Dung lượng SSD</dt>
                            <dd class="font-mono-num font-bold text-slate-900">
                                {{ (activePlan?.disk || 20) + form.addon_disk }} GB SSD
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-600 font-medium">Chu kỳ thanh toán</dt>
                            <dd class="font-semibold text-slate-900">{{ billingCycles[form.duration]?.label }}</dd>
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
                            Đang áp dụng giá Flash Sale (áp dụng gói ≤ {{ displayQuote.flash_sale.max_months }} tháng, không dùng kèm voucher).
                        </p>
                    </div>

                    <!-- Coupon Input + Smart Voucher Wallet Dropdown -->
                    <div class="pt-3 border-t border-slate-100">
                        <VoucherCombobox
                            v-model="couponInput"
                            :applied-code="form.coupon_code"
                            :vouchers="claimedVouchers"
                            :order-amount="preCouponOrderAmount"
                            :current-months="billingCycles[form.duration]?.months || 1"
                            :disabled="Boolean(displayQuote?.flash_sale)"
                            :loading="quoteLoading"
                            :coupon-feedback="displayQuote?.coupon"
                            placeholder="Nhập mã (VD: LUGIA10K)"
                            @apply="applyCoupon"
                        />
                    </div>

                    <!-- Price Breakdown -->
                    <div class="pt-3 border-t border-slate-200 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600 font-medium">
                            <span>Giá niêm yết</span>
                            <span class="font-mono-num font-semibold text-slate-800">{{ formatVnd(summaryListPrice) }}</span>
                        </div>
                        <div v-if="displayQuote?.flash_sale" class="flex justify-between text-red-600 font-bold">
                            <span class="flex items-center gap-1">
                                <Zap class="w-3.5 h-3.5 fill-red-600 text-red-600" />
                                {{ displayQuote.flash_sale.badge_text }}
                            </span>
                            <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.flash_sale.discount_amount) }}</span>
                        </div>
                        <div v-if="displayQuote?.campaign" class="flex justify-between text-emerald-700 font-semibold">
                            <span class="flex items-center gap-1">
                                <Tag class="w-3.5 h-3.5" />
                                {{ displayQuote.campaign.badge_text }}
                            </span>
                            <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.campaign.discount_amount) }}</span>
                        </div>
                        <div v-if="displayQuote?.coupon?.valid" class="flex justify-between text-emerald-700 font-semibold">
                            <span>Mã {{ displayQuote.coupon.code }}</span>
                            <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.coupon.discount_amount) }}</span>
                        </div>
                        <div v-if="displayQuote?.user_discount_amount > 0" class="flex justify-between text-emerald-700 font-semibold">
                            <span>Chiết khấu tài khoản ({{ displayQuote.user_discount_percent }}%)</span>
                            <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.user_discount_amount) }}</span>
                        </div>

                        <div class="pt-3 mt-2 border-t border-slate-200 flex items-baseline justify-between">
                            <span class="font-bold text-slate-900 text-sm">Tổng thanh toán</span>
                            <span class="text-2xl font-black font-mono-num text-[#0B1437] transition-all">
                                {{ formatVnd(summaryPrice) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-xs pt-1">
                            <span class="text-slate-500 font-medium">Số dư ví hiện tại:</span>
                            <span class="font-mono-num font-bold" :class="insufficientBalance ? 'text-rose-600' : 'text-slate-800'">
                                {{ formatVnd(user?.balance || 0) }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="isVpsOutOfStock"
                        class="w-full text-center py-3 px-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold"
                    >
                        Hệ thống tạm hết hàng Cloud VPS
                    </div>
                    <Link
                        v-else-if="insufficientBalance"
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
                                    ? 'Đang cấp phát máy chủ...'
                                    : isQuoteSyncing
                                      ? 'Đang chốt giá cấu hình...'
                                      : `Thanh toán ${formatVnd(summaryPrice)} & Khởi tạo`
                            }}
                        </span>
                    </button>
                </div>

                <!-- Trust Guarantee Box -->
                <div class="surface-card p-4 border border-slate-200/90 shadow-xs rounded-2xl space-y-2.5 text-xs">
                    <div class="font-bold text-[#0B1437] flex items-center gap-1.5">
                        <Check class="w-4 h-4 text-emerald-600" />
                        <span>Cam kết dịch vụ Cloud VPS LugiaCloud</span>
                    </div>
                    <ul class="space-y-1.5 text-slate-600 font-medium">
                        <li class="flex items-center gap-2">
                            <Check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                            <span>100% Ổ cứng SSD chuyên dụng, Intel Xeon Gold / Platinum.</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <Check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                            <span>Toàn quyền Administrator (Windows) hoặc Root (Linux).</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <Check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                            <span>Cấp phát tự động 100% trong vòng 1-3 phút.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </form>
        </div>
    </ClientLayout>
</template>
