<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import OsIcon from '@/Components/OsIcon.vue';
import EventShowcaseBanner from '@/Components/EventShowcaseBanner.vue';
import VoucherCombobox from '@/Components/VoucherCombobox.vue';
import { formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
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
    Eye,
    EyeOff,
    Shuffle,
    Lock,
} from 'lucide-vue-next';

const props = defineProps<{
    selectedProductId: number;
    product: any;
    products: Array<any>;
    billingCycles: Record<string, any>;
    claimedVouchers?: Array<{
        id: number;
        code: string;
        discount_label: string;
        min_order_amount: number;
        max_discount_amount: number | null;
        description?: string;
    }>;
}>();

const toast = useToast();
const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const heroBanners = computed(() => (page.props.promotions as any)?.banners?.hero_showcase || []);

const form = useForm({
    product_id: props.selectedProductId || props.products[0]?.id || 12,
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

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let abortController: AbortController | null = null;
let reqSeq = 0;

const selectedProduct = computed(() => props.products.find((p) => p.id === form.product_id) || props.product || props.products[0]);

function clampQuantity(val: number): number {
    if (!Number.isFinite(val)) return 1;
    return Math.min(10, Math.max(1, Math.round(val)));
}

const safeQuantity = computed(() => clampQuantity(Number(form.quantity || 1)));
const currentQuoteKey = computed(() => `${form.product_id}|${form.billing_cycle}|${safeQuantity.value}|${form.coupon_code}`);

const instantQuote = computed(() => {
    const prod = selectedProduct.value;
    const qty = safeQuantity.value;
    const cycleQuote = prod?.cycle_quotes?.[form.billing_cycle] || null;
    const cycleMeta = props.billingCycles?.[form.billing_cycle] || { months: 1, discount_percent: 0 };
    const months = Number(cycleMeta.months || 1);
    const discPct = Number(cycleMeta.discount_percent || 0);

    const fallbackList = Math.round(Number(prod?.list_price || prod?.price || 0) * months * (1 - discPct / 100));
    const unitList = cycleQuote ? Number(cycleQuote.list_price || fallbackList) : fallbackList;
    const unitFinal = cycleQuote ? Number(cycleQuote.final_price || unitList) : Number(prod?.price || unitList) * months;

    const listTotal = unitList * qty;
    const afterAuto = unitFinal * qty;
    const autoDiscount = Math.max(0, listTotal - afterAuto);

    const prodFlash = cycleQuote?.flash_sale || (form.billing_cycle === 'monthly' ? prod?.flash_sale : null) || null;
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
            (cycleQuote?.campaign || prod?.campaign) && autoDiscount > 0
                ? {
                      ...(cycleQuote?.campaign || prod.campaign),
                      discount_amount: autoDiscount,
                  }
                : null,
        flash_sale: null,
        coupon: form.coupon_code ? couponObj : null,
    };
});

const selectedCycleLabel = computed(() => props.billingCycles?.[form.billing_cycle]?.label || '1 Tháng');

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

const preCouponOrderAmount = computed(() => {
    const q = displayQuote.value;
    const base = Number(q?.list_price || 0);
    const camp = Number(q?.campaign?.discount_amount || 0);
    const usr = Number(q?.user_discount_amount || 0);
    return Math.max(0, base - camp - usr);
});

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

watch(
    () => [form.product_id, form.billing_cycle, form.quantity],
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
    form.quantity = clampQuantity(Number(form.quantity || 1));

    if (isQuoteSyncing.value) {
        const seen = totalPrice.value;
        await fetchQuoteNow();
        if (insufficientBalance.value) {
            toast.error('Số dư ví không đủ để thanh toán đơn hàng này.');
            return;
        }
        if (totalPrice.value !== seen) {
            toast.info('Giá đơn hàng vừa được cập nhật lại, vui lòng bấm xác nhận thanh toán.');
            return;
        }
    }

    if (insufficientBalance.value) {
        toast.error('Số dư ví không đủ để thanh toán đơn hàng này.');
        return;
    }

    form.expected_total = totalPrice.value;
    form.post('/proxy/store', {
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            if (firstErr) {
                toast.error(String(firstErr));
            }
        },
    });
}
</script>

<template>
    <ClientLayout title="Tùy chỉnh & Khởi tạo Proxy IPv4">
        <Head :title="`Khởi tạo ${selectedProduct?.title || 'Proxy IPv4'} — LugiaCloud`" />

        <div class="space-y-5">
            <EventShowcaseBanner v-if="heroBanners.length > 0" :banners="heroBanners" />

            <!-- Breadcrumb đồng bộ chuẩn Store/Create.vue -->
            <div class="flex items-center justify-between gap-3">
                <Link
                    href="/proxy/store"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-[#0B1437] transition-colors"
                >
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Quay lại chọn gói Proxy</span>
                </Link>

                <span class="px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-xs font-semibold inline-flex items-center gap-1">
                    <OsIcon distro="vietnam" :size="14" />
                    <span>FPT Datacenter (TP.HCM) · Ping &lt; 15ms</span>
                </span>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Left Column (8 cols): Clean 3-Step Configurator -->
                <div class="lg:col-span-8 space-y-5">
                    <!-- STEP 1: Đổi nhanh gói Proxy (gọn như thanh đổi gói VPS ở Store/Create.vue) -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-3.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">1</span>
                                <span>Gói Proxy IPv4 đã chọn</span>
                            </div>
                            <span class="text-xs text-slate-500 font-medium">Bấm để đổi nhanh loại Proxy</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button
                                v-for="p in products"
                                :key="p.id"
                                type="button"
                                @click="form.product_id = p.id"
                                class="p-3.5 rounded-xl border text-left transition-all cursor-pointer flex items-center justify-between gap-3"
                                :class="
                                    form.product_id === p.id
                                        ? 'border-[#1D6AE5] bg-blue-50/40 ring-1 ring-[#1D6AE5]'
                                        : 'border-slate-200 hover:border-slate-300 bg-white'
                                "
                            >
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs font-extrabold text-[#0B1437] truncate">{{ p.title }}</span>
                                        <span
                                            v-if="p.flash_sale"
                                            class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-red-50 text-red-700 border border-red-200 text-[9.5px] font-bold"
                                        >
                                            <Zap class="w-2.5 h-2.5 fill-red-600 text-red-600" />
                                            Còn {{ p.flash_sale.remaining_slots }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        {{ p.category === 'residential' ? 'Dân cư VN' : 'Datacenter' }} · {{ p.dedicated ? 'Riêng 1:1' : 'Chia sẻ' }}
                                    </div>
                                </div>
                                <div class="text-right shrink-0 font-mono-num">
                                    <div v-if="p.has_discount" class="text-[10.5px] text-slate-400 line-through">{{ formatVnd(p.list_price) }}</div>
                                    <div class="text-xs font-extrabold" :class="p.flash_sale ? 'text-red-600' : 'text-[#0B1437]'">
                                        {{ formatVnd(p.price) }}<span class="text-[10px] font-medium text-slate-500">/th</span>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: Số lượng IP & Giao thức -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-5">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">2</span>
                            <span>Số lượng IP &amp; Giao thức kết nối</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Quantity -->
                            <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/80 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-[#0B1437]">Số lượng IPv4</span>
                                    <span class="text-[11px] text-slate-500">Tối đa 10 IP / đơn</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="stepQuantity(-1)"
                                        :disabled="safeQuantity <= 1"
                                        class="w-9 h-9 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center cursor-pointer"
                                    >
                                        <Minus class="w-4 h-4" />
                                    </button>
                                    <input
                                        v-model.number="form.quantity"
                                        type="number"
                                        min="1"
                                        max="10"
                                        class="flex-1 h-9 text-center rounded-lg border border-slate-300 bg-white font-mono-num text-sm font-black text-[#0B1437]"
                                    />
                                    <button
                                        type="button"
                                        @click="stepQuantity(1)"
                                        :disabled="safeQuantity >= 10"
                                        class="w-9 h-9 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center cursor-pointer"
                                    >
                                        <Plus class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Protocol -->
                            <div class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/80 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-[#0B1437]">Giao thức khởi tạo</span>
                                    <span class="text-[11px] text-emerald-700 font-semibold">Đổi HTTP ↔ SOCKS5 tự do</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        v-for="proto in ['HTTP', 'SOCKS5']"
                                        :key="proto"
                                        type="button"
                                        @click="form.type_proxy = proto"
                                        class="h-9 rounded-lg border text-xs font-bold transition cursor-pointer"
                                        :class="
                                            form.type_proxy === proto
                                                ? 'bg-[#0B1437] text-white border-[#0B1437]'
                                                : 'bg-white text-slate-700 border-slate-300 hover:border-slate-400'
                                        "
                                    >
                                        {{ proto }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Chu kỳ thuê -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-[#0B1437]">Chu kỳ thanh toán</span>
                                <span class="text-[11px] text-slate-500">Thuê càng dài, ưu đãi càng tốt</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                                <button
                                    v-for="(meta, key) in billingCycles"
                                    :key="key"
                                    type="button"
                                    @click="form.billing_cycle = String(key)"
                                    class="p-2.5 rounded-xl border text-left transition cursor-pointer"
                                    :class="
                                        form.billing_cycle === key
                                            ? 'border-[#1D6AE5] bg-blue-50/50 ring-1 ring-[#1D6AE5]'
                                            : 'border-slate-200 bg-white hover:border-slate-300'
                                    "
                                >
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-xs font-bold text-[#0B1437]">{{ meta.label }}</span>
                                        <span
                                            v-if="meta.discount_percent > 0"
                                            class="px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700 text-[10px] font-extrabold"
                                        >
                                            -{{ meta.discount_percent }}%
                                        </span>
                                    </div>
                                    <div class="font-mono-num text-[11.5px] font-extrabold text-[#1D6AE5] mt-1">
                                        {{ formatVnd(selectedProduct?.cycle_quotes?.[key]?.final_price || (selectedProduct?.price || 0) * (meta.months || 1)) }}
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: Tài khoản xác thực -->
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <span class="w-5 h-5 rounded-full bg-blue-50 text-[#1D6AE5] inline-flex items-center justify-center text-[11px] font-extrabold">3</span>
                                <span>Tài khoản xác thực Proxy (Tùy chọn)</span>
                            </div>
                            <button
                                type="button"
                                @click="generateRandomCredentials"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-[#1D6AE5] text-[11px] font-bold cursor-pointer transition"
                            >
                                <Shuffle class="w-3 h-3" />
                                <span>Tạo ngẫu nhiên</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Username xác thực</label>
                                <input
                                    v-model="form.custom_username"
                                    type="text"
                                    placeholder="Để trống = tạo tự động"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-mono font-medium focus:outline-none focus:border-[#1D6AE5]"
                                />
                                <p v-if="form.errors.custom_username" class="mt-1 text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.custom_username }}
                                </p>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-semibold text-slate-700">Mật khẩu xác thực</label>
                                    <button
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        class="text-[11px] font-bold text-slate-600 hover:text-slate-900 cursor-pointer inline-flex items-center gap-1"
                                    >
                                        <component :is="showPassword ? EyeOff : Eye" class="w-3 h-3" />
                                        <span>{{ showPassword ? 'Ẩn' : 'Hiện' }}</span>
                                    </button>
                                </div>
                                <input
                                    v-model="form.custom_password"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Để trống = tạo tự động"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-mono font-medium focus:outline-none focus:border-[#1D6AE5]"
                                />
                                <p v-if="form.errors.custom_password" class="mt-1 text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.custom_password }}
                                </p>
                            </div>
                        </div>

                        <p class="text-[11.5px] text-slate-500 flex items-center gap-1.5">
                            <Lock class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                            <span>Từ 5 – 31 ký tự, chỉ gồm chữ cái không dấu và chữ số (không dùng khoảng trắng hay ký tự đặc biệt). Bỏ trống để tạo tự động.</span>
                        </p>
                    </div>
                </div>

                <!-- Right Column (4 cols): Sticky Order Summary -->
                <div class="lg:col-span-4 lg:sticky lg:top-20 space-y-4">
                    <div class="surface-card p-5 border border-slate-200/90 shadow-xs rounded-2xl space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                            <div class="flex items-center gap-2">
                                <Globe class="w-4 h-4 text-[#1D6AE5]" />
                                <h3 class="text-sm font-extrabold text-[#0B1437]">Tóm tắt đơn hàng</h3>
                            </div>
                            <span v-if="isQuoteSyncing" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#1D6AE5]">
                                <Loader2 class="w-3.5 h-3.5 animate-spin" />
                                <span>Đang tính...</span>
                            </span>
                        </div>

                        <dl class="space-y-2.5 text-xs">
                            <div class="flex justify-between gap-2">
                                <dt class="text-slate-600 font-medium">Gói Proxy</dt>
                                <dd class="font-bold text-[#0B1437] text-right">{{ selectedProduct?.title }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-600 font-medium">Số lượng</dt>
                                <dd class="font-mono-num font-extrabold text-[#1D6AE5]">{{ safeQuantity }} IPv4</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-600 font-medium">Giao thức</dt>
                                <dd class="font-mono-num font-bold text-[#0B1437]">{{ form.type_proxy }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-600 font-medium">Chu kỳ thuê</dt>
                                <dd class="font-semibold text-slate-800">{{ selectedCycleLabel }}</dd>
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

                        <!-- Smart Voucher Combobox -->
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

                            <div v-if="displayQuote?.flash_sale" class="flex justify-between text-red-600 font-bold">
                                <span class="flex items-center gap-1">
                                    <Zap class="w-3.5 h-3.5 fill-red-600 text-red-600" />
                                    <span>{{ displayQuote.flash_sale.badge_text }}</span>
                                </span>
                                <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.flash_sale.discount_amount) }}</span>
                            </div>

                            <div v-if="displayQuote?.campaign" class="flex justify-between text-emerald-700 font-semibold">
                                <span class="flex items-center gap-1">
                                    <Tag class="w-3.5 h-3.5" />
                                    <span>{{ displayQuote.campaign.badge_text }}</span>
                                </span>
                                <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.campaign.discount_amount) }}</span>
                            </div>

                            <div v-if="displayQuote?.coupon?.valid" class="flex justify-between text-emerald-700 font-semibold">
                                <span>Mã {{ displayQuote.coupon.code }}</span>
                                <span class="font-mono-num font-bold">-{{ formatVnd(displayQuote.coupon.discount_amount) }}</span>
                            </div>

                            <div class="pt-3 mt-2 border-t border-slate-200 flex items-baseline justify-between">
                                <span class="font-bold text-slate-900 text-sm">Tổng thanh toán</span>
                                <span class="text-2xl font-black font-mono-num text-[#0B1437]">
                                    {{ formatVnd(totalPrice) }}
                                </span>
                            </div>

                            <div class="flex justify-between text-xs pt-1">
                                <span class="text-slate-600 font-medium">Số dư ví hiện tại:</span>
                                <span class="font-mono-num font-bold" :class="insufficientBalance ? 'text-rose-600' : 'text-slate-900'">
                                    {{ formatVnd(user?.balance || 0) }}
                                </span>
                            </div>
                        </div>

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
                                          ? 'Đang chốt giá...'
                                          : `Thanh toán ${formatVnd(totalPrice)} & Nhận IP`
                                }}
                            </span>
                        </button>
                    </div>

                    <div class="surface-card p-4 border border-slate-200/90 shadow-xs rounded-2xl space-y-2.5 text-xs">
                        <div class="font-bold text-[#0B1437] flex items-center gap-1.5">
                            <ShieldCheck class="w-4 h-4 text-emerald-600" />
                            <span>Cam kết dịch vụ Proxy LugiaCloud</span>
                        </div>
                        <ul class="space-y-1.5 text-slate-600 font-medium">
                            <li class="flex items-center gap-2">
                                <Check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <span>100% IP sạch, kiểm tra blacklist trước khi cấp.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <span>Chuyển đổi linh hoạt HTTP ↔ SOCKS5 và đổi mật khẩu tức thì.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <Check class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                                <span>Cấp phát tự động 100% trong 5–15 giây.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </ClientLayout>
</template>
