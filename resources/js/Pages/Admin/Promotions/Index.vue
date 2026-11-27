<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import EventShowcaseBanner from '@/Components/EventShowcaseBanner.vue';
import { formatVnd } from '@/Composables/useFormat';
import { askConfirm } from '@/Composables/useConfirmAction';
import { Plus, Edit3, Trash2, Power, X, Mail, AlertTriangle, ShieldCheck, Zap, Ticket, Gift, Upload, Monitor, Smartphone, Loader2, CalendarPlus } from 'lucide-vue-next';

const props = defineProps<{
    campaigns: Array<any>;
    banners: Array<any>;
    coupons: Array<any>;
    flashSales?: Array<any>;
    welcomeOffer?: {
        enabled: boolean;
        code: string;
        percent: number;
        max_discount: number;
        valid_days: number;
        max_months: number;
    };
    catalogItems?: Array<{
        slug: string;
        name: string;
        service_type: 'vps' | 'proxy' | 'addon';
        provider_cost: number;
        retail_price: number;
        min_margin_percent: number;
    }>;
}>();

const welcomeForm = useForm({
    enabled: props.welcomeOffer?.enabled ?? true,
    code: props.welcomeOffer?.code ?? 'WELCOME10',
    percent: props.welcomeOffer?.percent ?? 10,
    max_discount: props.welcomeOffer?.max_discount ?? 50000,
    valid_days: props.welcomeOffer?.valid_days ?? 7,
    max_months: props.welcomeOffer?.max_months ?? 1,
});

function saveWelcomeOffer() {
    welcomeForm.put('/admin/promotions/welcome-offer', { preserveScroll: true });
}

const activeTab = ref<'campaigns' | 'flash_sales' | 'banners' | 'coupons'>('campaigns');

const showCampaignModal = ref(false);
const editingCampaignId = ref<number | null>(null);

const showBannerModal = ref(false);
const editingBannerId = ref<number | null>(null);
const bannerPreviewDevice = ref<'desktop' | 'mobile'>('desktop');
const uploadingBannerSlot = ref<'desktop' | 'mobile' | null>(null);
const bannerUploadError = ref<string>('');

async function handleBannerFileUpload(slot: 'desktop' | 'mobile', event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    bannerUploadError.value = '';
    uploadingBannerSlot.value = slot;

    try {
        const fd = new FormData();
        fd.append('image', file);
        fd.append('slot', slot);
        const { data } = await axios.post('/admin/promotions/banners/upload-image', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (slot === 'desktop') {
            bannerForm.image_desktop = data.url;
        } else {
            bannerForm.image_mobile = data.url;
        }
    } catch (err: any) {
        bannerUploadError.value =
            err?.response?.data?.errors?.image?.[0] ||
            err?.response?.data?.message ||
            'Không thể tải ảnh lên. Vui lòng kiểm tra định dạng JPG/PNG/WebP (≤ 4MB).';
    } finally {
        uploadingBannerSlot.value = null;
        input.value = '';
    }
}

const showCouponModal = ref(false);
const editingCouponId = ref<number | null>(null);

const showFlashSaleModal = ref(false);
const editingFlashSaleId = ref<number | null>(null);

const campaignForm = useForm({
    name: '',
    event_tag: 'seasonal',
    badge_text: 'SALE SỰ KIỆN -20%',
    accent_color: '#1D6AE5',
    description: '',
    starts_at: '',
    ends_at: '',
    is_active: true,
    send_launch_email: true,
    priority: 15,
    discount_type: 'percent',
    discount_value: 20,
    max_discount_amount: null as number | null,
    target_scope: 'all',
    min_months: 1,
    apply_on_renew: false,
    apply_on_upgrade: false,
    stack_with_user_discount: false,
    protect_cost_floor: true,
    topup_bonus_percent: 0,
});

const bannerForm = useForm({
    campaign_id: props.campaigns[0]?.id || (null as number | null),
    placement: 'hero_showcase',
    eyebrow: 'SỰ KIỆN TRONG NĂM',
    title: '',
    subtitle: '',
    cta_text: 'Nhận ưu đãi ngay',
    cta_url: '#pricing',
    coupon_code_display: '',
    image_desktop: '',
    image_mobile: '',
    theme_preset: 'navy_ocean',
    bg_from: '#0B1437',
    bg_to: '#1D4ED8',
    starts_at: '',
    ends_at: '',
    is_active: true,
    sort_order: 1,
});

const couponForm = useForm({
    campaign_id: props.campaigns[0]?.id || (null as number | null),
    code: '',
    description: '',
    discount_type: 'percent',
    discount_value: 10,
    max_discount_amount: 50000 as number | null,
    min_order_amount: 100000,
    applies_to: 'all',
    apply_on_renew: false,
    protect_cost_floor: true,
    max_uses: 200 as number | null,
    max_uses_per_user: 2,
    is_huntable: false,
    claim_limit: null as number | null,
    starts_at: '',
    ends_at: '',
    is_active: true,
});

const flashSaleEligibleItems = computed(() =>
    (props.catalogItems || []).filter((it) => it.service_type === 'vps' || it.service_type === 'proxy')
);

const flashSaleForm = useForm({
    name: '',
    badge_text: 'FLASH SALE',
    plan_slug: (props.catalogItems || []).find((it) => it.service_type === 'vps')?.slug || 'lg-4',
    discount_type: 'percent' as 'percent' | 'fixed' | 'fixed_price',
    discount_value: 25,
    total_slots: 10,
    max_per_user: 1,
    max_months: 1,
    protect_cost_floor: true,
    starts_at: '',
    ends_at: '',
    is_active: true,
});

const placementLabels: Record<string, string> = {
    top_bar: 'Thanh thông báo đỉnh trang (Countdown)',
    hero_showcase: 'Banner lớn ngoài Trang chủ',
    popup_modal: 'Popup chào mừng Trang chủ',
    dashboard_banner: 'Banner bên trong Dashboard khách',
};

const scopeLabels: Record<string, string> = {
    all: 'VPS & Proxy',
    vps: 'Chỉ Cloud VPS',
    proxy: 'Chỉ Proxy IPv4',
    upgrade: 'Chỉ Nâng cấp CPU/RAM/SSD',
};

interface RiskItem {
    name: string;
    service_type: string;
    retail: number;
    cost: number;
    afterPrice: number;
    floorPrice: number;
    profit: number;
    marginPct: number;
    safeMaxPct: number;
    status: 'loss' | 'clamped' | 'safe';
}

function evaluateDiscountRisk(opts: {
    discountType: string;
    discountValue: number;
    maxDiscount: number | null;
    scope: string;
    includeUpgrade?: boolean;
    protectFloor: boolean;
    topupBonusPct?: number;
}) {
    const items = props.catalogItems || [];
    const targetItems = items.filter((it) => {
        if (opts.scope === 'upgrade') return it.service_type === 'addon';
        if (opts.scope === 'vps') return it.service_type === 'vps' || (opts.includeUpgrade && it.service_type === 'addon');
        if (opts.scope === 'proxy') return it.service_type === 'proxy';
        return it.service_type === 'vps' || it.service_type === 'proxy' || (opts.includeUpgrade && it.service_type === 'addon');
    });

    const bonusPct = Math.max(0, Number(opts.topupBonusPct || 0));
    const hasServiceDisc = opts.discountType !== 'none' && Number(opts.discountValue || 0) > 0;
    const rows: RiskItem[] = [];

    for (const it of targetItems) {
        const retail = Number(it.retail_price || 0);
        const cost = Number(it.provider_cost || 0);
        const minMargin = Number(it.min_margin_percent ?? 5);
        if (retail <= 0 || cost <= 0) continue;

        let rawDisc = 0;
        if (hasServiceDisc) {
            rawDisc =
                opts.discountType === 'percent'
                    ? Math.round(retail * (Math.min(100, Math.max(0, Number(opts.discountValue))) / 100))
                    : Math.min(retail, Math.max(0, Number(opts.discountValue)));
            if (opts.maxDiscount && opts.maxDiscount > 0) {
                rawDisc = Math.min(rawDisc, opts.maxDiscount);
            }
        }

        const rawAfter = Math.max(0, retail - rawDisc);
        const effectiveCash = bonusPct > 0 ? Math.round(rawAfter / (1 + bonusPct / 100)) : rawAfter;
        const floorPrice = Math.ceil(cost * (1 + minMargin / 100));
        const finalPrice = opts.protectFloor ? Math.max(rawAfter, Math.min(floorPrice, retail)) : rawAfter;
        const profit = finalPrice - cost;
        const marginPct = finalPrice > 0 ? (profit / finalPrice) * 100 : -100;
        const safeMaxPct = Math.max(0, Math.floor((1 - floorPrice / retail) * 1000) / 10);

        let status: 'loss' | 'clamped' | 'safe' = 'safe';
        if (!opts.protectFloor && (rawAfter < cost || effectiveCash < cost)) {
            status = 'loss';
        } else if (rawAfter < floorPrice || effectiveCash < cost) {
            status = 'clamped';
        }

        rows.push({
            name: it.name,
            service_type: it.service_type,
            retail,
            cost,
            afterPrice: opts.protectFloor ? finalPrice : rawAfter,
            floorPrice,
            profit,
            marginPct,
            safeMaxPct,
            status,
        });
    }

    const losses = rows.filter((r) => r.status === 'loss');
    const clamped = rows.filter((r) => r.status === 'clamped');
    const minSafePct = rows.length ? Math.min(...rows.map((r) => r.safeMaxPct)) : 0;
    const minMarginPct = rows.length ? Math.min(...rows.map((r) => r.marginPct)) : 0;

    return {
        total: rows.length,
        losses,
        clamped,
        minSafePct,
        minMarginPct,
        hasWarning: losses.length > 0 || clamped.length > 0,
        severity: losses.length > 0 ? 'danger' : clamped.length > 0 ? 'warning' : 'safe',
    };
}

const campaignRisk = computed(() =>
    evaluateDiscountRisk({
        discountType: campaignForm.discount_type,
        discountValue: Number(campaignForm.discount_value || 0),
        maxDiscount: campaignForm.max_discount_amount,
        scope: campaignForm.target_scope,
        includeUpgrade: campaignForm.apply_on_upgrade,
        protectFloor: campaignForm.protect_cost_floor,
        topupBonusPct: Number(campaignForm.topup_bonus_percent || 0),
    })
);

const couponRisk = computed(() =>
    evaluateDiscountRisk({
        discountType: couponForm.discount_type,
        discountValue: Number(couponForm.discount_value || 0),
        maxDiscount: couponForm.max_discount_amount,
        scope: couponForm.applies_to,
        includeUpgrade: false,
        protectFloor: couponForm.protect_cost_floor,
        topupBonusPct: 0,
    })
);

const flashSaleRisk = computed(() => {
    const item = flashSaleEligibleItems.value.find((it) => it.slug === flashSaleForm.plan_slug);
    if (!item) return null;
    const retail = Number(item.retail_price || 0);
    const cost = Number(item.provider_cost || 0);
    const minMargin = Number(item.min_margin_percent ?? 5);
    const val = Number(flashSaleForm.discount_value || 0);

    let rawSale = retail;
    if (flashSaleForm.discount_type === 'fixed_price') {
        rawSale = Math.max(0, val);
    } else if (flashSaleForm.discount_type === 'fixed') {
        rawSale = Math.max(0, retail - val);
    } else {
        rawSale = Math.max(0, retail - Math.round(retail * (Math.min(90, Math.max(0, val)) / 100)));
    }

    const floorPrice = Math.ceil(cost * (1 + minMargin / 100));
    const effectiveSale = flashSaleForm.protect_cost_floor ? Math.max(rawSale, Math.min(floorPrice, retail)) : rawSale;
    const profit = effectiveSale - cost;
    const marginPct = effectiveSale > 0 ? (profit / effectiveSale) * 100 : -100;
    const totalMaxLoss = profit < 0 ? Math.abs(profit) * Number(flashSaleForm.total_slots || 0) : 0;

    let severity: 'safe' | 'warning' | 'danger' = 'safe';
    if (!flashSaleForm.protect_cost_floor && rawSale < cost) {
        severity = 'danger';
    } else if (rawSale < floorPrice) {
        severity = 'warning';
    }

    return {
        planName: item.name,
        retail,
        cost,
        floorPrice,
        rawSale,
        effectiveSale,
        profit,
        marginPct,
        totalMaxLoss,
        severity,
    };
});

// --- Campaign Create / Edit ---
function openCreateCampaign() {
    editingCampaignId.value = null;
    campaignForm.reset();
    campaignForm.clearErrors();
    campaignForm.event_tag = 'seasonal';
    campaignForm.badge_text = 'SALE SỰ KIỆN -20%';
    campaignForm.accent_color = '#1D6AE5';
    campaignForm.is_active = true;
    campaignForm.send_launch_email = true;
    campaignForm.priority = 15;
    campaignForm.discount_type = 'percent';
    campaignForm.discount_value = 20;
    campaignForm.max_discount_amount = null;
    campaignForm.target_scope = 'all';
    campaignForm.min_months = 1;
    campaignForm.apply_on_renew = false;
    campaignForm.apply_on_upgrade = false;
    campaignForm.stack_with_user_discount = false;
    campaignForm.protect_cost_floor = true;
    campaignForm.topup_bonus_percent = 0;
    showCampaignModal.value = true;
}

function openEditCampaign(c: any) {
    editingCampaignId.value = c.id;
    campaignForm.clearErrors();
    campaignForm.name = c.name || '';
    campaignForm.event_tag = c.event_tag || 'seasonal';
    campaignForm.badge_text = c.badge_text || '';
    campaignForm.accent_color = c.accent_color || '#1D6AE5';
    campaignForm.description = c.description || '';
    campaignForm.starts_at = c.starts_at || '';
    campaignForm.ends_at = c.ends_at || '';
    campaignForm.is_active = Boolean(c.is_active);
    campaignForm.send_launch_email = c.send_launch_email !== undefined ? Boolean(c.send_launch_email) : true;
    campaignForm.priority = Number(c.priority ?? 15);
    campaignForm.discount_type = c.discount_type || 'percent';
    campaignForm.discount_value = Number(c.discount_value ?? 0);
    campaignForm.max_discount_amount = c.max_discount_amount ? Number(c.max_discount_amount) : null;
    campaignForm.target_scope = c.target_scope || 'all';
    campaignForm.min_months = Number(c.min_months ?? 1);
    campaignForm.apply_on_renew = Boolean(c.apply_on_renew);
    campaignForm.apply_on_upgrade = Boolean(c.apply_on_upgrade);
    campaignForm.stack_with_user_discount = Boolean(c.stack_with_user_discount);
    campaignForm.protect_cost_floor = Boolean(c.protect_cost_floor);
    campaignForm.topup_bonus_percent = Number(c.topup_bonus_percent ?? 0);
    showCampaignModal.value = true;
}

function saveCampaign() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showCampaignModal.value = false;
            editingCampaignId.value = null;
        },
    };
    if (editingCampaignId.value) {
        campaignForm.put(`/admin/promotions/campaigns/${editingCampaignId.value}`, opts);
    } else {
        campaignForm.post('/admin/promotions/campaigns', opts);
    }
}

// --- Banner Create / Edit ---
function openCreateBanner() {
    editingBannerId.value = null;
    bannerForm.reset();
    bannerForm.clearErrors();
    bannerForm.campaign_id = props.campaigns[0]?.id || null;
    bannerForm.placement = 'hero_showcase';
    bannerForm.eyebrow = 'SỰ KIỆN TRONG NĂM';
    bannerForm.cta_text = 'Nhận ưu đãi ngay';
    bannerForm.cta_url = '#pricing';
    bannerForm.theme_preset = 'navy_ocean';
    bannerForm.bg_from = '#0B1437';
    bannerForm.bg_to = '#1D4ED8';
    bannerForm.is_active = true;
    bannerForm.sort_order = 1;
    showBannerModal.value = true;
}

function openEditBanner(b: any) {
    editingBannerId.value = b.id;
    bannerForm.clearErrors();
    bannerForm.campaign_id = b.campaign_id ?? null;
    bannerForm.placement = b.placement || 'hero_showcase';
    bannerForm.eyebrow = b.eyebrow || '';
    bannerForm.title = b.title || '';
    bannerForm.subtitle = b.subtitle || '';
    bannerForm.cta_text = b.cta_text || 'Nhận ưu đãi ngay';
    bannerForm.cta_url = b.cta_url || '#pricing';
    bannerForm.coupon_code_display = b.coupon_code_display || '';
    bannerForm.image_desktop = b.image_desktop || '';
    bannerForm.image_mobile = b.image_mobile || '';
    bannerForm.theme_preset = b.theme_preset || 'navy_ocean';
    bannerForm.bg_from = b.bg_from || '#0B1437';
    bannerForm.bg_to = b.bg_to || '#1D4ED8';
    bannerForm.starts_at = b.starts_at || '';
    bannerForm.ends_at = b.ends_at || '';
    bannerForm.is_active = Boolean(b.is_active);
    bannerForm.sort_order = Number(b.sort_order ?? 1);
    showBannerModal.value = true;
}

function saveBanner() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showBannerModal.value = false;
            editingBannerId.value = null;
        },
    };
    if (editingBannerId.value) {
        bannerForm.put(`/admin/promotions/banners/${editingBannerId.value}`, opts);
    } else {
        bannerForm.post('/admin/promotions/banners', opts);
    }
}

// --- Coupon Create / Edit ---
function openCreateCoupon() {
    editingCouponId.value = null;
    couponForm.reset();
    couponForm.clearErrors();
    couponForm.campaign_id = props.campaigns[0]?.id || null;
    couponForm.discount_type = 'percent';
    couponForm.discount_value = 10;
    couponForm.max_discount_amount = 50000;
    couponForm.min_order_amount = 100000;
    couponForm.applies_to = 'all';
    couponForm.apply_on_renew = false;
    couponForm.protect_cost_floor = true;
    couponForm.max_uses = 200;
    couponForm.max_uses_per_user = 2;
    couponForm.is_huntable = false;
    couponForm.claim_limit = null;
    couponForm.is_active = true;
    showCouponModal.value = true;
}

function openEditCoupon(cp: any) {
    editingCouponId.value = cp.id;
    couponForm.clearErrors();
    couponForm.campaign_id = cp.campaign_id ?? null;
    couponForm.code = cp.code || '';
    couponForm.description = cp.description || '';
    couponForm.discount_type = cp.discount_type || 'percent';
    couponForm.discount_value = Number(cp.discount_value ?? 10);
    couponForm.max_discount_amount = cp.max_discount_amount ? Number(cp.max_discount_amount) : null;
    couponForm.min_order_amount = Number(cp.min_order_amount ?? 0);
    couponForm.applies_to = cp.applies_to || 'all';
    couponForm.apply_on_renew = Boolean(cp.apply_on_renew);
    couponForm.protect_cost_floor = Boolean(cp.protect_cost_floor);
    couponForm.max_uses = cp.max_uses ? Number(cp.max_uses) : null;
    couponForm.max_uses_per_user = Number(cp.max_uses_per_user ?? 1);
    couponForm.is_huntable = Boolean(cp.is_huntable);
    couponForm.claim_limit = cp.claim_limit ? Number(cp.claim_limit) : null;
    couponForm.starts_at = cp.starts_at || '';
    couponForm.ends_at = cp.ends_at || '';
    couponForm.is_active = Boolean(cp.is_active);
    showCouponModal.value = true;
}

function saveCoupon() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showCouponModal.value = false;
            editingCouponId.value = null;
        },
    };
    if (editingCouponId.value) {
        couponForm.put(`/admin/promotions/coupons/${editingCouponId.value}`, opts);
    } else {
        couponForm.post('/admin/promotions/coupons', opts);
    }
}

// --- Flash Sale Create / Edit ---
function openCreateFlashSale() {
    editingFlashSaleId.value = null;
    flashSaleForm.reset();
    flashSaleForm.clearErrors();
    flashSaleForm.name = '';
    flashSaleForm.badge_text = 'FLASH SALE';
    flashSaleForm.plan_slug = flashSaleEligibleItems.value[0]?.slug || 'lg-4';
    flashSaleForm.discount_type = 'percent';
    flashSaleForm.discount_value = 25;
    flashSaleForm.total_slots = 10;
    flashSaleForm.max_per_user = 1;
    flashSaleForm.max_months = 1;
    flashSaleForm.protect_cost_floor = true;
    flashSaleForm.starts_at = '';
    flashSaleForm.ends_at = '';
    flashSaleForm.is_active = true;
    showFlashSaleModal.value = true;
}

function openEditFlashSale(fs: any) {
    editingFlashSaleId.value = fs.id;
    flashSaleForm.clearErrors();
    flashSaleForm.name = fs.name || '';
    flashSaleForm.badge_text = fs.badge_text || 'FLASH SALE';
    flashSaleForm.plan_slug = fs.plan_slug || flashSaleEligibleItems.value[0]?.slug || 'lg-4';
    flashSaleForm.discount_type = fs.discount_type || 'percent';
    flashSaleForm.discount_value = Number(fs.discount_value ?? 20);
    flashSaleForm.total_slots = Number(fs.total_slots ?? 10);
    flashSaleForm.max_per_user = Number(fs.max_per_user ?? 1);
    flashSaleForm.max_months = Number(fs.max_months ?? 1);
    flashSaleForm.protect_cost_floor = Boolean(fs.protect_cost_floor);
    flashSaleForm.starts_at = fs.starts_at || '';
    flashSaleForm.ends_at = fs.ends_at || '';
    flashSaleForm.is_active = Boolean(fs.is_active);
    showFlashSaleModal.value = true;
}

function saveFlashSale() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showFlashSaleModal.value = false;
            editingFlashSaleId.value = null;
        },
    };
    if (editingFlashSaleId.value) {
        flashSaleForm.put(`/admin/promotions/flash-sales/${editingFlashSaleId.value}`, opts);
    } else {
        flashSaleForm.post('/admin/promotions/flash-sales', opts);
    }
}
</script>

<template>
    <AdminLayout title="Chiến dịch Sự kiện, Banner & Mã giảm giá">
        <Head title="Sự kiện theo năm & Banner" />

        <div class="space-y-6">
            <!-- Header & Tabs -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="inline-flex flex-nowrap overflow-x-auto max-w-full p-1 rounded-lg bg-white border border-slate-200 gap-0.5 [&::-webkit-scrollbar]:hidden [scrollbar-width:none]">
                    <button
                        v-for="t in [
                            { key: 'campaigns', label: `Sự kiện theo năm (${campaigns.length})` },
                            { key: 'flash_sales', label: `Flash Sale theo suất (${flashSales?.length || 0})` },
                            { key: 'banners', label: `Banner hiển thị (${banners.length})` },
                            { key: 'coupons', label: `Mã Coupon & Săn Voucher (${coupons.length})` },
                        ]"
                        :key="t.key"
                        type="button"
                        @click="activeTab = t.key as any"
                        class="px-3.5 sm:px-4 py-1.5 rounded-md text-xs font-semibold cursor-pointer transition-colors whitespace-nowrap shrink-0"
                        :class="activeTab === t.key ? 'bg-[#0B1437] text-white' : 'text-slate-600 hover:text-slate-900'"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <div>
                    <button
                        v-if="activeTab === 'campaigns'"
                        type="button"
                        @click="openCreateCampaign"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-semibold cursor-pointer"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Tạo Sự kiện Khuyến mãi mới</span>
                    </button>
                    <button
                        v-else-if="activeTab === 'flash_sales'"
                        type="button"
                        @click="openCreateFlashSale"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-semibold cursor-pointer"
                    >
                        <Zap class="w-3.5 h-3.5" />
                        <span>Tạo phiên Flash Sale mới</span>
                    </button>
                    <button
                        v-else-if="activeTab === 'banners'"
                        type="button"
                        @click="openCreateBanner"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-semibold cursor-pointer"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Thêm Banner Hiển thị</span>
                    </button>
                    <button
                        v-else
                        type="button"
                        @click="openCreateCoupon"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-semibold cursor-pointer"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Phát hành Mã giảm giá</span>
                    </button>
                </div>
            </div>

            <!-- TAB 1: YEARLY CAMPAIGNS -->
            <div v-if="activeTab === 'campaigns'" class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div v-if="campaigns.length === 0" class="p-10 text-center text-xs text-slate-600 font-medium">
                    Chưa có chiến dịch sự kiện nào. Nhấn nút tạo mới để lên lịch Tết, Black Friday, Sinh nhật...
                </div>
                <template v-else>
                    <!-- Mobile Cards -->
                    <div class="lg:hidden divide-y divide-slate-200/80">
                        <div v-for="c in campaigns" :key="`mc-${c.id}`" class="p-4 space-y-3 bg-white text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-extrabold text-[13px] text-[#0B1437] break-words">{{ c.name }}</div>
                                    <span v-if="c.badge_text" class="inline-block mt-1 px-2 py-0.5 rounded-md bg-blue-50 text-[#1D6AE5] border border-blue-200 text-[10.5px] font-bold">
                                        {{ c.badge_text }}
                                    </span>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded-md text-[11px] font-bold border shrink-0"
                                    :class="c.is_running_now ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                >
                                    {{ c.is_running_now ? 'Đang diễn ra' : c.is_active ? 'Chờ lịch chạy' : 'Đã tắt' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 text-[11px]">
                                <div>
                                    <div class="text-[10px] font-bold uppercase text-slate-400">Giảm giá dịch vụ</div>
                                    <div class="font-mono-num font-extrabold text-[#0B1437] mt-0.5">
                                        <template v-if="c.discount_type === 'percent'">-{{ c.discount_value }}%</template>
                                        <template v-else-if="c.discount_type === 'fixed'">-{{ formatVnd(c.discount_value) }}</template>
                                        <template v-else>Không giảm gói</template>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] font-bold uppercase text-slate-400">Thưởng nạp ví</div>
                                    <div class="font-mono-num font-extrabold text-emerald-700 mt-0.5">
                                        {{ c.topup_bonus_percent > 0 ? `+${c.topup_bonus_percent}%` : '—' }}
                                    </div>
                                </div>
                                <div class="col-span-2 pt-2 border-t border-slate-200/70 text-slate-600 space-y-0.5">
                                    <div>
                                        Phạm vi: <strong class="text-slate-800">{{ scopeLabels[c.target_scope] || c.target_scope.toUpperCase() }}</strong>
                                        <span v-if="c.apply_on_renew"> · Gia hạn</span>
                                        <span v-if="c.apply_on_upgrade && c.target_scope !== 'upgrade'"> · Nâng cấp</span>
                                    </div>
                                    <div class="font-mono-num">Hiệu lực: {{ c.starts_display }} → {{ c.ends_display }}</div>
                                    <div class="font-semibold">
                                        <span v-if="c.launch_email_sent_at" class="text-emerald-700">Đã gửi mail ({{ c.launch_email_count }} KH) · {{ c.launch_email_sent_at }}</span>
                                        <span v-else-if="c.send_launch_email" class="text-[#1D6AE5]">Tự gửi mail khi mở sự kiện</span>
                                        <span v-else class="text-slate-400">Không gửi mail tự động</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5">
                                <button
                                    type="button"
                                    @click="openEditCampaign(c)"
                                    class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-2 rounded-xl border border-slate-300 bg-white hover:bg-blue-50 text-slate-800 font-bold cursor-pointer"
                                >
                                    <Edit3 class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                    <span>Sửa</span>
                                </button>
                                <button
                                    type="button"
                                    @click="
                                        askConfirm({
                                            title: 'Gửi email quảng bá sự kiện',
                                            message: c.launch_email_sent_at
                                                ? `Sự kiện này đã gửi mail lúc ${c.launch_email_sent_at}. Gửi lại email quảng bá cho toàn bộ khách hàng?`
                                                : `Gửi email thông báo sự kiện '${c.name}' tới toàn bộ khách hàng ngay bây giờ?`,
                                            confirmText: 'Gửi email ngay',
                                            variant: 'primary',
                                            onConfirm: () => router.post(`/admin/promotions/campaigns/${c.id}/broadcast`, {}, { preserveScroll: true }),
                                        })
                                    "
                                    class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-blue-200 bg-blue-50/70 text-[#1D6AE5] font-bold cursor-pointer"
                                >
                                    <Mail class="w-3.5 h-3.5" />
                                    <span>Mail</span>
                                </button>
                                <button
                                    v-if="c.starts_at || c.ends_at"
                                    type="button"
                                    @click="
                                        askConfirm({
                                            title: 'Đẩy lịch sự kiện sang năm sau (+1 năm)',
                                            message: `Dời ngày bắt đầu & kết thúc của '${c.name}' (kèm toàn bộ Coupon và Banner gắn theo) sang năm kế tiếp để tái sử dụng?`,
                                            confirmText: 'Đẩy lịch +1 năm',
                                            variant: 'primary',
                                            onConfirm: () => router.post(`/admin/promotions/campaigns/${c.id}/roll-year`, {}, { preserveScroll: true }),
                                        })
                                    "
                                    class="inline-flex items-center gap-1 px-2.5 py-2 rounded-xl border border-emerald-200 bg-emerald-50/70 text-emerald-700 font-bold cursor-pointer"
                                >
                                    <CalendarPlus class="w-3.5 h-3.5" />
                                    <span>+1 năm</span>
                                </button>
                                <button
                                    type="button"
                                    @click="router.post(`/admin/promotions/campaigns/${c.id}/toggle`, {}, { preserveScroll: true })"
                                    class="p-2 rounded-xl border border-slate-200 text-slate-700 cursor-pointer"
                                    title="Bật / Tắt"
                                >
                                    <Power class="w-3.5 h-3.5" />
                                </button>
                                <button
                                    type="button"
                                    @click="
                                        askConfirm({
                                            title: 'Xóa chiến dịch sự kiện',
                                            message: `Bạn có chắc muốn xóa chiến dịch '${c.name}'?`,
                                            onConfirm: () => router.delete(`/admin/promotions/campaigns/${c.id}`, { preserveScroll: true }),
                                        })
                                    "
                                    class="p-2 rounded-xl border border-rose-200 text-rose-600 cursor-pointer"
                                    title="Xóa"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600 whitespace-nowrap">
                                <th class="py-3 px-4">Tên chương trình sự kiện</th>
                                <th class="py-3 px-4">Mức giảm giá dịch vụ</th>
                                <th class="py-3 px-4">Thưởng nạp tiền</th>
                                <th class="py-3 px-4">Thời gian hiệu lực</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="c in campaigns" :key="c.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-[#0B1437] flex items-center gap-2">
                                        <span>{{ c.name }}</span>
                                        <span v-if="c.badge_text" class="px-2 py-0.5 rounded-md bg-blue-50 text-[#1D6AE5] border border-blue-200 text-[10.5px] font-bold">
                                            {{ c.badge_text }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-600 font-medium mt-1">
                                        Phạm vi: <strong>{{ scopeLabels[c.target_scope] || c.target_scope.toUpperCase() }}</strong>
                                        <span v-if="c.apply_on_renew"> · Gia hạn</span>
                                        <span v-if="c.apply_on_upgrade && c.target_scope !== 'upgrade'"> · Nâng cấp</span>
                                        · Ưu tiên: {{ c.priority }} · {{ c.banners_count }} Banner · {{ c.coupons_count }} Mã
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num font-extrabold text-[#0B1437]">
                                    <template v-if="c.discount_type === 'percent'">-{{ c.discount_value }}%</template>
                                    <template v-else-if="c.discount_type === 'fixed'">-{{ formatVnd(c.discount_value) }}</template>
                                    <template v-else>Không giảm giá gói</template>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num font-extrabold text-emerald-700">
                                    {{ c.topup_bonus_percent > 0 ? `+${c.topup_bonus_percent}%` : '—' }}
                                </td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-700 font-medium">
                                    <div>Từ: {{ c.starts_display }}</div>
                                    <div>Đến: {{ c.ends_display }}</div>
                                </td>
                                <td class="py-3.5 px-4 space-y-1.5">
                                    <div>
                                        <span
                                            class="px-2.5 py-0.5 rounded-md text-xs font-bold border"
                                            :class="
                                                c.is_running_now
                                                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                    : 'bg-slate-100 text-slate-700 border-slate-200'
                                            "
                                        >
                                            {{ c.is_running_now ? 'Đang diễn ra' : c.is_active ? 'Chờ lịch chạy' : 'Đã tắt' }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] font-semibold">
                                        <span v-if="c.launch_email_sent_at" class="text-emerald-700">
                                            Đã gửi mail ({{ c.launch_email_count }} KH) · {{ c.launch_email_sent_at }}
                                        </span>
                                        <span v-else-if="c.send_launch_email" class="text-[#1D6AE5]">
                                            Tự gửi mail khi mở sự kiện
                                        </span>
                                        <span v-else class="text-slate-500">
                                            Không gửi mail tự động
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1.5">
                                    <button
                                        type="button"
                                        @click="
                                            askConfirm({
                                                title: 'Gửi email quảng bá sự kiện',
                                                message: c.launch_email_sent_at
                                                    ? `Sự kiện này đã gửi mail lúc ${c.launch_email_sent_at}. Gửi lại email quảng bá cho toàn bộ khách hàng?`
                                                    : `Gửi email thông báo sự kiện '${c.name}' tới toàn bộ khách hàng ngay bây giờ?`,
                                                confirmText: 'Gửi email ngay',
                                                variant: 'primary',
                                                onConfirm: () => router.post(`/admin/promotions/campaigns/${c.id}/broadcast`, {}, { preserveScroll: true }),
                                            })
                                        "
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-blue-200 bg-blue-50/70 hover:bg-blue-100 text-[#1D6AE5] font-bold cursor-pointer transition shadow-2xs active:scale-95"
                                        title="Gửi email quảng bá sự kiện tới toàn bộ khách hàng"
                                    >
                                        <Mail class="w-3.5 h-3.5" />
                                        <span>Gửi mail</span>
                                    </button>
                                    <button
                                        v-if="c.starts_at || c.ends_at"
                                        type="button"
                                        @click="
                                            askConfirm({
                                                title: 'Đẩy lịch sự kiện sang năm sau (+1 năm)',
                                                message: `Dời ngày bắt đầu & kết thúc của '${c.name}' (kèm toàn bộ Coupon và Banner gắn theo) sang năm kế tiếp để tái sử dụng?`,
                                                confirmText: 'Đẩy lịch +1 năm',
                                                variant: 'primary',
                                                onConfirm: () => router.post(`/admin/promotions/campaigns/${c.id}/roll-year`, {}, { preserveScroll: true }),
                                            })
                                        "
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50/70 hover:bg-emerald-100 text-emerald-700 font-bold cursor-pointer transition shadow-2xs active:scale-95"
                                        title="Đẩy lịch sự kiện + Coupon + Banner sang năm tiếp theo để tái sử dụng"
                                    >
                                        <CalendarPlus class="w-3.5 h-3.5" />
                                        <span>+1 năm</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="openEditCampaign(c)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-blue-50 hover:border-blue-200 text-slate-700 hover:text-[#1D6AE5] font-bold cursor-pointer transition shadow-2xs active:scale-95"
                                        title="Chỉnh sửa sự kiện"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                        <span>Sửa</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="router.post(`/admin/promotions/campaigns/${c.id}/toggle`, {}, { preserveScroll: true })"
                                        class="p-1.5 rounded border border-slate-200 hover:bg-slate-100 text-slate-700 cursor-pointer align-middle"
                                        title="Bật / Tắt"
                                    >
                                        <Power class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            askConfirm({
                                                title: 'Xóa chiến dịch sự kiện',
                                                message: `Bạn có chắc muốn xóa chiến dịch '${c.name}'?`,
                                                onConfirm: () => router.delete(`/admin/promotions/campaigns/${c.id}`, { preserveScroll: true }),
                                            })
                                        "
                                        class="p-1.5 rounded border border-rose-200 hover:bg-rose-50 text-rose-600 cursor-pointer align-middle"
                                        title="Xóa"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </template>
            </div>

            <!-- TAB 1.5: FLASH SALES BY SLOT -->
            <div v-else-if="activeTab === 'flash_sales'" class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div v-if="!flashSales || flashSales.length === 0" class="p-10 text-center text-xs text-slate-600 font-medium">
                    Chưa có phiên Flash Sale nào. Nhấn <strong>Tạo phiên Flash Sale mới</strong> để mở suất ưu đãi gắn trực tiếp vào gói gốc VPS / Proxy.
                </div>
                <template v-else>
                    <!-- Mobile Cards -->
                    <div class="lg:hidden divide-y divide-slate-200/80">
                        <div v-for="fs in flashSales" :key="`mfs-${fs.id}`" class="p-4 space-y-3 bg-white text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-extrabold text-[13px] text-[#0B1437] break-words">{{ fs.name }}</div>
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded bg-blue-50 text-[#1D6AE5] border border-blue-200 text-[10.5px] font-bold">
                                        {{ fs.plan_name }} ({{ fs.plan_slug }})
                                    </span>
                                </div>
                                <span
                                    class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-md text-[11px] font-bold border shrink-0"
                                    :class="
                                        fs.is_live_now
                                            ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                            : fs.is_upcoming
                                              ? 'bg-amber-50 text-amber-800 border-amber-200'
                                              : 'bg-slate-100 text-slate-700 border-slate-200'
                                    "
                                >
                                    {{ fs.is_live_now ? 'Đang mở săn' : fs.is_upcoming ? 'Sắp mở' : fs.remaining_slots <= 0 ? 'Đã hết suất' : 'Đã tắt' }}
                                </span>
                            </div>

                            <div class="rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 space-y-2 text-[11px]">
                                <div class="flex items-baseline justify-between gap-2 font-mono-num">
                                    <div>
                                        <span class="font-extrabold text-sm text-[#1D6AE5]">{{ formatVnd(fs.sale_price) }}/th</span>
                                        <span class="ml-1.5 line-through text-slate-400">{{ formatVnd(fs.list_price) }}</span>
                                    </div>
                                    <span class="text-slate-500">Vốn: {{ formatVnd(fs.provider_cost) }}</span>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between font-bold mb-1">
                                        <span class="text-[#0B1437]">Đã săn {{ fs.sold_slots }}/{{ fs.total_slots }}</span>
                                        <span :class="fs.remaining_slots > 0 ? 'text-emerald-700' : 'text-rose-600'">
                                            {{ fs.remaining_slots > 0 ? `Còn ${fs.remaining_slots} suất` : 'Hết suất' }}
                                        </span>
                                    </div>
                                    <div class="w-full h-2 rounded-full bg-slate-200/70 overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="fs.remaining_slots <= 0 ? 'bg-slate-400' : 'bg-[#1D6AE5]'"
                                            :style="{ width: Math.min(100, Math.round((fs.sold_slots / Math.max(1, fs.total_slots)) * 100)) + '%' }"
                                        ></div>
                                    </div>
                                </div>

                                <div class="pt-1.5 border-t border-slate-200/70 text-slate-600 font-mono-num space-y-0.5">
                                    <div>Tối đa {{ fs.max_per_user }} suất/KH · ≤ {{ fs.max_months }} tháng</div>
                                    <div class="text-slate-500">{{ fs.starts_display }} → {{ fs.ends_display }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="openEditFlashSale(fs)"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 bg-white hover:bg-blue-50 text-slate-800 font-bold cursor-pointer"
                                >
                                    <Edit3 class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                    <span>Chỉnh sửa</span>
                                </button>
                                <button
                                    type="button"
                                    @click="router.post(`/admin/promotions/flash-sales/${fs.id}/toggle`, {}, { preserveScroll: true })"
                                    class="p-2 rounded-xl border border-slate-200 text-slate-700 cursor-pointer shrink-0"
                                    title="Bật / Tắt"
                                >
                                    <Power class="w-4 h-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="
                                        askConfirm({
                                            title: 'Xóa phiên Flash Sale',
                                            message: `Bạn có chắc muốn xóa phiên Flash Sale '${fs.name}'?`,
                                            onConfirm: () => router.delete(`/admin/promotions/flash-sales/${fs.id}`, { preserveScroll: true }),
                                        })
                                    "
                                    class="p-2 rounded-xl border border-rose-200 text-rose-600 cursor-pointer shrink-0"
                                    title="Xóa"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600 whitespace-nowrap">
                                <th class="py-3 px-4">Phiên Flash Sale &amp; Gói gốc</th>
                                <th class="py-3 px-4">Giá Flash Sale / Gốc</th>
                                <th class="py-3 px-4">Tiến độ suất săn</th>
                                <th class="py-3 px-4">Giới hạn &amp; Thời gian</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="fs in flashSales" :key="fs.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-[#0B1437] flex items-center gap-2">
                                        <span>{{ fs.name }}</span>
                                        <span class="px-2 py-0.5 rounded bg-blue-50 text-[#1D6AE5] border border-blue-200 text-[10.5px] font-bold">
                                            {{ fs.plan_name }} ({{ fs.plan_slug }})
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-600 font-medium mt-1">
                                        Loại: <strong>{{ fs.service_type === 'proxy' ? 'Proxy IPv4' : 'Cloud VPS' }}</strong>
                                        · {{ fs.protect_cost_floor ? 'Có khóa sàn giá vốn' : 'Cho phép bán dưới giá vốn (Marketing)' }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num">
                                    <div class="font-extrabold text-[#1D6AE5]">{{ formatVnd(fs.sale_price) }}/th</div>
                                    <div class="text-[11px] text-slate-500">
                                        Niêm yết: <span class="line-through">{{ formatVnd(fs.list_price) }}</span> · Vốn: {{ formatVnd(fs.provider_cost) }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 min-w-[170px]">
                                    <div class="flex items-center justify-between text-[11px] font-bold mb-1">
                                        <span class="text-[#0B1437]">Đã săn {{ fs.sold_slots }}/{{ fs.total_slots }}</span>
                                        <span :class="fs.remaining_slots > 0 ? 'text-emerald-700' : 'text-rose-600'">
                                            {{ fs.remaining_slots > 0 ? `Còn ${fs.remaining_slots} suất` : 'Hết suất' }}
                                        </span>
                                    </div>
                                    <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="fs.remaining_slots <= 0 ? 'bg-slate-400' : 'bg-[#1D6AE5]'"
                                            :style="{ width: Math.min(100, Math.round((fs.sold_slots / Math.max(1, fs.total_slots)) * 100)) + '%' }"
                                        ></div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-700 font-medium">
                                    <div>Tối đa {{ fs.max_per_user }} suất/KH · ≤ {{ fs.max_months }} tháng</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">{{ fs.starts_display }} → {{ fs.ends_display }}</div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-md text-xs font-bold border"
                                        :class="
                                            fs.is_live_now
                                                ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                : fs.is_upcoming
                                                  ? 'bg-amber-50 text-amber-800 border-amber-200'
                                                  : 'bg-slate-100 text-slate-700 border-slate-200'
                                        "
                                    >
                                        {{ fs.is_live_now ? 'Đang mở săn' : fs.is_upcoming ? 'Sắp mở' : fs.remaining_slots <= 0 ? 'Đã hết suất' : 'Đã tắt / Hết hạn' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1.5">
                                    <button
                                        type="button"
                                        @click="openEditFlashSale(fs)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-blue-50 hover:border-blue-200 text-slate-700 hover:text-[#1D6AE5] font-bold cursor-pointer transition shadow-2xs active:scale-95"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                        <span>Sửa</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="router.post(`/admin/promotions/flash-sales/${fs.id}/toggle`, {}, { preserveScroll: true })"
                                        class="p-1.5 rounded border border-slate-200 hover:bg-slate-100 text-slate-700 cursor-pointer align-middle"
                                        title="Bật / Tắt"
                                    >
                                        <Power class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            askConfirm({
                                                title: 'Xóa phiên Flash Sale',
                                                message: `Bạn có chắc muốn xóa phiên Flash Sale '${fs.name}'?`,
                                                onConfirm: () => router.delete(`/admin/promotions/flash-sales/${fs.id}`, { preserveScroll: true }),
                                            })
                                        "
                                        class="p-1.5 rounded border border-rose-200 hover:bg-rose-50 text-rose-600 cursor-pointer align-middle"
                                        title="Xóa"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </template>
            </div>

            <!-- TAB 2: BANNERS -->
            <div v-else-if="activeTab === 'banners'" class="space-y-4">
                <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                    <div v-if="banners.length === 0" class="p-10 text-center text-xs text-slate-600 font-medium">
                        Chưa có Banner nào.
                    </div>
                    <template v-else>
                        <!-- Mobile Cards -->
                        <div class="lg:hidden divide-y divide-slate-200/80">
                            <div v-for="b in banners" :key="`mb-${b.id}`" class="p-4 space-y-2.5 bg-white text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="text-[10.5px] font-bold uppercase tracking-wider text-[#1D6AE5]">
                                            {{ placementLabels[b.placement] || b.placement }}
                                        </div>
                                        <div class="font-extrabold text-[13px] text-[#0B1437] mt-0.5 break-words">{{ b.title }}</div>
                                        <div v-if="b.subtitle" class="text-[11px] text-slate-500 mt-0.5 line-clamp-2">{{ b.subtitle }}</div>
                                    </div>
                                    <span
                                        class="px-2 py-0.5 rounded-md text-[11px] font-bold border shrink-0"
                                        :class="b.is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                    >
                                        {{ b.is_active ? 'Hiển thị' : 'Đang ẩn' }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 text-[11px]">
                                    <div>
                                        <div class="text-[10px] font-bold uppercase text-slate-400">Mã hiển thị</div>
                                        <div class="font-mono font-bold text-amber-700 mt-0.5">{{ b.coupon_code_display || '—' }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[10px] font-bold uppercase text-slate-400">Chiến dịch</div>
                                        <div class="font-semibold text-slate-700 mt-0.5 truncate">{{ b.campaign_name || 'Độc lập' }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEditBanner(b)"
                                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 bg-white hover:bg-blue-50 text-slate-800 font-bold cursor-pointer"
                                    >
                                        <Edit3 class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                        <span>Chỉnh sửa</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="router.post(`/admin/promotions/banners/${b.id}/toggle`, {}, { preserveScroll: true })"
                                        class="p-2 rounded-xl border border-slate-200 text-slate-700 cursor-pointer shrink-0"
                                        title="Ẩn / Hiện"
                                    >
                                        <Power class="w-4 h-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            askConfirm({
                                                title: 'Xóa Banner',
                                                message: `Bạn có chắc muốn xóa Banner '${b.title || '#' + b.id}'?`,
                                                onConfirm: () => router.delete(`/admin/promotions/banners/${b.id}`, { preserveScroll: true }),
                                            })
                                        "
                                        class="p-2 rounded-xl border border-rose-200 text-rose-600 cursor-pointer shrink-0"
                                        title="Xóa"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Table -->
                        <div class="hidden lg:block overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600 whitespace-nowrap">
                                    <th class="py-3 px-4">Vị trí hiển thị</th>
                                    <th class="py-3 px-4">Tiêu đề &amp; Phụ đề</th>
                                    <th class="py-3 px-4">Mã hiển thị</th>
                                    <th class="py-3 px-4">Chiến dịch gắn kèm</th>
                                    <th class="py-3 px-4">Trạng thái</th>
                                    <th class="py-3 px-4 text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80">
                                <tr v-for="b in banners" :key="b.id" class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 font-bold text-[#0B1437]">
                                        {{ placementLabels[b.placement] || b.placement }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900">{{ b.title }}</div>
                                        <div v-if="b.subtitle" class="text-xs text-slate-600 font-medium truncate max-w-md mt-0.5">{{ b.subtitle }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono font-bold text-amber-700">
                                        {{ b.coupon_code_display || '—' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-700 font-medium">{{ b.campaign_name || 'Độc lập' }}</td>
                                    <td class="py-3.5 px-4">
                                        <span
                                            class="px-2.5 py-0.5 rounded-md text-xs font-bold border"
                                            :class="b.is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                        >
                                            {{ b.is_active ? 'Hiển thị' : 'Đang ẩn' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1.5">
                                        <button
                                            type="button"
                                            @click="openEditBanner(b)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-blue-50 hover:border-blue-200 text-slate-700 hover:text-[#1D6AE5] font-bold cursor-pointer transition shadow-2xs active:scale-95"
                                            title="Chỉnh sửa Banner"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                            <span>Sửa</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="router.post(`/admin/promotions/banners/${b.id}/toggle`, {}, { preserveScroll: true })"
                                            class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 cursor-pointer align-middle transition"
                                            title="Ẩn / Hiện"
                                        >
                                            <Power class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="
                                                askConfirm({
                                                    title: 'Xóa Banner',
                                                    message: `Bạn có chắc muốn xóa Banner '${b.title || '#' + b.id}'?`,
                                                    onConfirm: () => router.delete(`/admin/promotions/banners/${b.id}`, { preserveScroll: true }),
                                                })
                                            "
                                            class="p-1.5 rounded-lg border border-rose-200 hover:bg-rose-50 text-rose-600 cursor-pointer align-middle transition"
                                            title="Xóa"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    </template>
                </div>
            </div>

            <!-- TAB 3: COUPONS & WELCOME OFFER -->
            <div v-else class="space-y-4">
                <!-- Cấu hình tự động Ưu đãi thành viên mới -->
                <form
                    @submit.prevent="saveWelcomeOffer"
                    class="surface-card p-5 rounded-2xl border border-emerald-200/90 bg-gradient-to-r from-emerald-50/50 to-white shadow-xs space-y-3.5 text-xs"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                                <Gift class="w-4.5 h-4.5" />
                            </div>
                            <div>
                                <div class="text-sm font-extrabold text-[#0B1437]">Ưu đãi tự động cho Thành viên mới</div>
                                <div class="text-xs text-slate-600">
                                    Tự tặng vào Ví Voucher khi đăng ký tài khoản mới · Áp dụng toàn bộ gói VPS &amp; Proxy cho đơn đầu tiên · Không cộng dồn Sự kiện/Flash Sale.
                                </div>
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-2 font-bold text-[#0B1437] cursor-pointer">
                            <input v-model="welcomeForm.enabled" type="checkbox" class="rounded border-slate-300 text-emerald-600" />
                            <span>{{ welcomeForm.enabled ? 'Đang bật' : 'Đang tắt' }}</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 items-end">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Mã Voucher</label>
                            <input v-model="welcomeForm.code" type="text" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300 bg-white font-mono uppercase font-bold" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Giảm (%)</label>
                            <input v-model.number="welcomeForm.percent" type="number" min="1" max="50" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300 bg-white font-mono-num font-bold" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Giảm tối đa (VNĐ)</label>
                            <input v-model.number="welcomeForm.max_discount" type="number" min="0" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300 bg-white font-mono-num" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Hạn từ lúc ĐK (ngày)</label>
                            <input v-model.number="welcomeForm.valid_days" type="number" min="1" max="90" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300 bg-white font-mono-num" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Chu kỳ tối đa (tháng)</label>
                            <input v-model.number="welcomeForm.max_months" type="number" min="1" max="12" required class="w-full px-3 py-1.5 rounded-lg border border-slate-300 bg-white font-mono-num" />
                        </div>
                        <div>
                            <button
                                type="submit"
                                :disabled="welcomeForm.processing"
                                class="w-full py-2 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold cursor-pointer transition disabled:opacity-60"
                            >
                                Lưu cấu hình
                            </button>
                        </div>
                    </div>
                </form>

                <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                    <div v-if="coupons.length === 0" class="p-10 text-center text-xs text-slate-600 font-medium">
                        Chưa phát hành mã giảm giá nào.
                    </div>
                    <template v-else>
                    <!-- Mobile Cards -->
                    <div class="lg:hidden divide-y divide-slate-200/80">
                        <div v-for="cp in coupons" :key="`mcp-${cp.id}`" class="p-4 space-y-2.5 bg-white text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center flex-wrap gap-1.5">
                                        <span class="font-mono font-black text-[13.5px] text-[#0B1437]">{{ cp.code }}</span>
                                        <span
                                            v-if="cp.is_huntable"
                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-50 text-[#1D6AE5] border border-blue-200 text-[10px] font-bold"
                                        >
                                            <Ticket class="w-3 h-3" />
                                            Kho Săn
                                        </span>
                                    </div>
                                    <div v-if="cp.description" class="text-[11px] text-slate-500 mt-0.5 break-words">{{ cp.description }}</div>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded-md text-[11px] font-bold border shrink-0"
                                    :class="cp.is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                >
                                    {{ cp.is_active ? 'Hoạt động' : 'Đã khóa' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 text-[11px] font-mono-num">
                                <div>
                                    <div class="text-[10px] font-sans font-bold uppercase text-slate-400">Mức giảm</div>
                                    <div class="font-extrabold text-emerald-800 mt-0.5">
                                        {{ cp.discount_type === 'percent' ? `-${cp.discount_value}%` : `-${formatVnd(cp.discount_value)}` }}
                                    </div>
                                    <div v-if="cp.max_discount_amount" class="text-[10px] font-sans text-slate-500">
                                        Tối đa {{ formatVnd(cp.max_discount_amount) }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] font-sans font-bold uppercase text-slate-400">Đơn tối thiểu</div>
                                    <div class="font-bold text-slate-800 mt-0.5">{{ formatVnd(cp.min_order_amount) }}</div>
                                </div>
                                <div class="col-span-2 pt-2 border-t border-slate-200/70 flex items-center justify-between gap-2">
                                    <span class="font-bold text-slate-800">Dùng: {{ cp.used_count }} / {{ cp.max_uses || '∞' }}</span>
                                    <span v-if="cp.is_huntable" class="text-[#1D6AE5] font-semibold">
                                        Đã săn: {{ cp.claimed_count }} / {{ cp.claim_limit || cp.max_uses || '∞' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="openEditCoupon(cp)"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 bg-white hover:bg-blue-50 text-slate-800 font-bold cursor-pointer"
                                >
                                    <Edit3 class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                    <span>Chỉnh sửa</span>
                                </button>
                                <button
                                    type="button"
                                    @click="router.post(`/admin/promotions/coupons/${cp.id}/toggle`, {}, { preserveScroll: true })"
                                    class="p-2 rounded-xl border border-slate-200 text-slate-700 cursor-pointer shrink-0"
                                    title="Bật / Khóa"
                                >
                                    <Power class="w-4 h-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="
                                        askConfirm({
                                            title: 'Xóa mã giảm giá',
                                            message: `Bạn có chắc muốn xóa mã giảm giá ${cp.code}?`,
                                            onConfirm: () => router.delete(`/admin/promotions/coupons/${cp.id}`, { preserveScroll: true }),
                                        })
                                    "
                                    class="p-2 rounded-xl border border-rose-200 text-rose-600 cursor-pointer shrink-0"
                                    title="Xóa"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600 whitespace-nowrap">
                                <th class="py-3 px-4">Mã Coupon</th>
                                <th class="py-3 px-4">Mức giảm</th>
                                <th class="py-3 px-4">Đơn tối thiểu</th>
                                <th class="py-3 px-4">Lượt sử dụng</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="cp in coupons" :key="cp.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-black text-[#0B1437]">{{ cp.code }}</span>
                                        <span
                                            v-if="cp.is_huntable"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-[#1D6AE5] border border-blue-200 text-[10.5px] font-bold"
                                        >
                                            <Ticket class="w-3 h-3" />
                                            Kho Săn Voucher
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-600 font-medium mt-0.5">{{ cp.description }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num font-extrabold text-emerald-800">
                                    {{ cp.discount_type === 'percent' ? `-${cp.discount_value}%` : `-${formatVnd(cp.discount_value)}` }}
                                    <span v-if="cp.max_discount_amount" class="text-xs text-slate-600 font-semibold">
                                        (tối đa {{ formatVnd(cp.max_discount_amount) }})
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-700 font-bold">{{ formatVnd(cp.min_order_amount) }}</td>
                                <td class="py-3.5 px-4 font-mono-num font-bold text-slate-900">
                                    <div>Dùng: {{ cp.used_count }} / {{ cp.max_uses || '∞' }}</div>
                                    <div v-if="cp.is_huntable" class="text-[11px] text-[#1D6AE5] font-semibold mt-0.5">
                                        Đã săn: {{ cp.claimed_count }} / {{ cp.claim_limit || cp.max_uses || '∞' }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span
                                        class="px-2.5 py-0.5 rounded-md text-xs font-bold border"
                                        :class="cp.is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                    >
                                        {{ cp.is_active ? 'Hoạt động' : 'Đã khóa' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1.5">
                                    <button
                                        type="button"
                                        @click="openEditCoupon(cp)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-blue-50 hover:border-blue-200 text-slate-700 hover:text-[#1D6AE5] font-bold cursor-pointer transition shadow-2xs active:scale-95"
                                        title="Chỉnh sửa mã Coupon"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                        <span>Sửa</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="router.post(`/admin/promotions/coupons/${cp.id}/toggle`, {}, { preserveScroll: true })"
                                        class="p-1.5 rounded border border-slate-200 hover:bg-slate-100 cursor-pointer align-middle"
                                        title="Bật / Khóa"
                                    >
                                        <Power class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            askConfirm({
                                                title: 'Xóa mã giảm giá',
                                                message: `Bạn có chắc muốn xóa mã giảm giá ${cp.code}?`,
                                                onConfirm: () => router.delete(`/admin/promotions/coupons/${cp.id}`, { preserveScroll: true }),
                                            })
                                        "
                                        class="p-1.5 rounded border border-rose-200 hover:bg-rose-50 text-rose-600 cursor-pointer align-middle"
                                        title="Xóa"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </template>
                </div>
            </div>

            <!-- MODAL CREATE / EDIT CAMPAIGN -->
            <Teleport to="body">
                <div v-if="showCampaignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showCampaignModal = false">
                    <form
                        @submit.prevent="saveCampaign"
                        class="w-full max-w-xl bg-white rounded-xl border border-slate-200 shadow-2xl p-6 space-y-3.5 text-xs max-h-[90vh] overflow-y-auto"
                    >
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <h3 class="text-sm font-bold text-[#0B1437]">
                                {{ editingCampaignId ? 'Chỉnh sửa Chiến dịch Sự kiện' : 'Tạo Chiến dịch Sự kiện Khuyến mãi' }}
                            </h3>
                            <button type="button" @click="showCampaignModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Tên sự kiện (VD: Sale Tết Nguyên Đán)</label>
                                <input v-model="campaignForm.name" type="text" required class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Nhãn gắn trên bảng giá</label>
                                <input v-model="campaignForm.badge_text" type="text" placeholder="SALE TẾT -20%" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Mô tả ngắn chương trình</label>
                            <input v-model="campaignForm.description" type="text" placeholder="Giảm trực tiếp toàn bộ Cloud VPS SSD & Proxy IPv4..." class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                        </div>

                        <div class="grid grid-cols-4 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Kiểu giảm giá</label>
                                <select v-model="campaignForm.discount_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                    <option value="percent">Theo %</option>
                                    <option value="fixed">Tiền cố định</option>
                                    <option value="none">Không giảm gói</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Giá trị giảm</label>
                                <input v-model.number="campaignForm.discount_value" type="number" min="0" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Giảm tối đa (đ)</label>
                                <input v-model.number="campaignForm.max_discount_amount" type="number" min="0" placeholder="Không giới hạn" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Thưởng % Nạp ví</label>
                                <input v-model.number="campaignForm.topup_bonus_percent" type="number" min="0" max="100" step="0.5" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold text-emerald-700" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Áp dụng cho dịch vụ</label>
                                <select v-model="campaignForm.target_scope" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                    <option value="all">VPS &amp; Proxy</option>
                                    <option value="vps">Chỉ Cloud VPS</option>
                                    <option value="proxy">Chỉ Proxy IPv4</option>
                                    <option value="upgrade">Chỉ Nâng cấp CPU / RAM / SSD</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Chu kỳ tối thiểu (tháng)</label>
                                <input v-model.number="campaignForm.min_months" type="number" min="1" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Độ ưu tiên (cao = trước)</label>
                                <input v-model.number="campaignForm.priority" type="number" min="0" max="999" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Thời điểm bắt đầu (trống = mở ngay)</label>
                                <input v-model="campaignForm.starts_at" type="datetime-local" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Thời điểm kết thúc (Countdown)</label>
                                <input v-model="campaignForm.ends_at" type="datetime-local" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-x-5 gap-y-2.5 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="campaignForm.protect_cost_floor" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span class="font-semibold text-slate-800">Bảo vệ không bán dưới giá vốn đối tác</span>
                            </label>
                            <label v-if="campaignForm.target_scope !== 'upgrade'" class="flex items-center gap-2 cursor-pointer">
                                <input v-model="campaignForm.apply_on_renew" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Áp dụng cho Gia hạn</span>
                            </label>
                            <label v-if="campaignForm.target_scope !== 'proxy' && campaignForm.target_scope !== 'upgrade'" class="flex items-center gap-2 cursor-pointer">
                                <input v-model="campaignForm.apply_on_upgrade" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Áp dụng cho Nâng cấp CPU/RAM/SSD giữa kỳ</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="campaignForm.stack_with_user_discount" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Cộng dồn với chiết khấu VIP cá nhân</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="campaignForm.send_launch_email" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span class="font-semibold text-[#1D6AE5]">Tự động gửi Email cho toàn bộ khách khi sự kiện bắt đầu</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="campaignForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Kích hoạt sự kiện</span>
                            </label>
                        </div>

                        <!-- BỘ PHÂN TÍCH RỦI RO LÃI / LỖ TỨC THÌ -->
                        <div
                            v-if="campaignRisk.total > 0"
                            class="p-3.5 rounded-xl border text-xs space-y-2"
                            :class="
                                campaignRisk.severity === 'danger'
                                    ? 'bg-rose-50 border-rose-300 text-rose-900'
                                    : campaignRisk.severity === 'warning'
                                      ? 'bg-amber-50 border-amber-300 text-amber-900'
                                      : 'bg-emerald-50 border-emerald-200 text-emerald-900'
                            "
                        >
                            <div class="flex items-start gap-2">
                                <AlertTriangle
                                    v-if="campaignRisk.severity !== 'safe'"
                                    class="w-4 h-4 shrink-0 mt-0.5"
                                    :class="campaignRisk.severity === 'danger' ? 'text-rose-600' : 'text-amber-600'"
                                />
                                <ShieldCheck v-else class="w-4 h-4 shrink-0 mt-0.5 text-emerald-600" />
                                <div class="min-w-0 flex-1">
                                    <div v-if="campaignRisk.severity === 'danger'" class="font-extrabold">
                                        CẢNH BÁO BÁN LỖ VỐN ({{ campaignRisk.losses.length }}/{{ campaignRisk.total }} gói bị âm lãi!)
                                    </div>
                                    <div v-else-if="campaignRisk.severity === 'warning'" class="font-extrabold">
                                        Cảnh báo chạm sàn giá vốn ({{ campaignRisk.clamped.length }}/{{ campaignRisk.total }} gói vượt ngưỡng an toàn {{ campaignRisk.minSafePct }}%)
                                    </div>
                                    <div v-else class="font-bold">
                                        An toàn lợi nhuận — Cả {{ campaignRisk.total }} gói áp dụng đều còn lãi tốt (ngưỡng giảm tối đa an toàn nhóm này: {{ campaignRisk.minSafePct }}%).
                                    </div>

                                    <p v-if="campaignRisk.severity === 'danger'" class="mt-0.5 leading-relaxed">
                                        Bạn đang tắt bảo vệ giá vốn. Hãy giảm mức chiết khấu xuống dưới <strong>{{ campaignRisk.minSafePct }}%</strong> hoặc bật lại <strong>Bảo vệ giá vốn</strong>.
                                    </p>
                                    <p v-else-if="campaignRisk.severity === 'warning'" class="mt-0.5 leading-relaxed">
                                        Hệ thống sẽ tự động chặn giá ở mức sàn (lãi tối thiểu 5%) nên <strong>không bị lỗ</strong>, nhưng khách mua các gói bên dưới sẽ nhận mức giảm thực tế thấp hơn quảng cáo. Nên đặt giảm tối đa <strong>≤ {{ campaignRisk.minSafePct }}%</strong> cho nhóm này:
                                    </p>
                                </div>
                            </div>

                            <div v-if="campaignRisk.losses.length > 0 || campaignRisk.clamped.length > 0" class="bg-white/80 rounded-lg border border-black/10 divide-y divide-slate-200/70 max-h-36 overflow-y-auto">
                                <div
                                    v-for="row in [...campaignRisk.losses, ...campaignRisk.clamped]"
                                    :key="row.name"
                                    class="px-2.5 py-1.5 flex items-center justify-between gap-2 font-mono-num text-[11.5px]"
                                >
                                    <span class="font-sans font-bold text-slate-800 truncate">{{ row.name }}</span>
                                    <span class="shrink-0">
                                        Vốn {{ formatVnd(row.cost) }} · Bán {{ formatVnd(row.afterPrice) }}
                                        <strong :class="row.profit < 0 ? 'text-rose-700' : 'text-amber-800'">
                                            ({{ row.profit >= 0 ? '+' : '' }}{{ formatVnd(row.profit) }} · An toàn ≤ {{ row.safeMaxPct }}%)
                                        </strong>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                            <button type="button" @click="showCampaignModal = false" class="px-4 py-2 rounded-lg border border-slate-300 font-semibold cursor-pointer">Đóng</button>
                            <button type="submit" :disabled="campaignForm.processing" class="px-4 py-2 rounded-lg bg-[#0B1437] text-white font-semibold cursor-pointer">
                                {{ editingCampaignId ? 'Lưu cập nhật sự kiện' : 'Tạo sự kiện mới' }}
                            </button>
                        </div>
                    </form>
                </div>
            </Teleport>

            <!-- MODAL CREATE / EDIT BANNER (WITH LIVE PREVIEW) -->
            <Teleport to="body">
                <div v-if="showBannerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showBannerModal = false">
                    <form
                        @submit.prevent="saveBanner"
                        class="w-full max-w-2xl bg-white rounded-xl border border-slate-200 shadow-2xl p-6 space-y-3.5 text-xs max-h-[92vh] overflow-y-auto"
                    >
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <h3 class="text-sm font-bold text-[#0B1437]">
                                {{ editingBannerId ? 'Chỉnh sửa Nội dung Banner' : 'Thêm Banner Sự kiện Hiển thị Ngoài / Dashboard' }}
                            </h3>
                            <button type="button" @click="showBannerModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Vị trí hiển thị</label>
                                <select v-model="bannerForm.placement" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                    <option value="hero_showcase">Banner lớn ngoài Trang chủ</option>
                                    <option value="top_bar">Thanh thông báo đỉnh trang (Countdown)</option>
                                    <option value="popup_modal">Popup chào mừng Trang chủ</option>
                                    <option value="dashboard_banner">Banner bên trong Dashboard khách</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Gắn với Chiến dịch</label>
                                <select v-model="bannerForm.campaign_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                    <option :value="null">— Độc lập —</option>
                                    <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Thứ tự sắp xếp</label>
                                <input v-model.number="bannerForm.sort_order" type="number" min="0" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Nhãn phụ (Eyebrow)</label>
                                <input v-model="bannerForm.eyebrow" type="text" placeholder="BLACK FRIDAY" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                            </div>
                            <div class="col-span-2">
                                <label class="block font-semibold mb-1">Tiêu đề chính của Banner</label>
                                <input v-model="bannerForm.title" type="text" required placeholder="Giảm 25% toàn bộ Cloud VPS SSD..." class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Mô tả chi tiết</label>
                            <input v-model="bannerForm.subtitle" type="text" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Mã Coupon hiển thị</label>
                                <input v-model="bannerForm.coupon_code_display" type="text" placeholder="TET2027" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono uppercase" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Chữ trên nút CTA</label>
                                <input v-model="bannerForm.cta_text" type="text" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Đường dẫn khi bấm</label>
                                <input v-model="bannerForm.cta_url" type="text" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono" />
                            </div>
                        </div>

                        <!-- Khu vực Tải ảnh Banner (Web & Điện thoại tách tỉ lệ chuẩn) -->
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="font-bold text-[#0B1437]">Ảnh Banner thiết kế sẵn (Tùy chọn — bỏ trống sẽ dùng giao diện chữ + màu nền)</div>
                                <span class="text-[11px] text-slate-500">Hỗ trợ JPG, PNG, WebP · Tối đa 4MB</span>
                            </div>

                            <div v-if="bannerUploadError" class="px-3 py-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 font-semibold text-[11.5px]">
                                {{ bannerUploadError }}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <!-- Desktop Image -->
                                <div class="p-3 rounded-lg bg-white border border-slate-200 space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold text-[#0B1437] inline-flex items-center gap-1.5">
                                            <Monitor class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                            <span>Ảnh Web (Desktop)</span>
                                        </span>
                                        <span class="px-1.5 py-0.5 rounded bg-blue-50 text-[#1D6AE5] font-mono-num text-[10px] font-bold">
                                            {{ bannerForm.placement === 'popup_modal' ? '1200 × 800 px (3:2 / 4:5)' : '1280 × 320 px (4:1)' }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#0B1437] hover:bg-[#162354] text-white font-bold cursor-pointer transition shrink-0">
                                            <Loader2 v-if="uploadingBannerSlot === 'desktop'" class="w-3.5 h-3.5 animate-spin" />
                                            <Upload v-else class="w-3.5 h-3.5" />
                                            <span>{{ uploadingBannerSlot === 'desktop' ? 'Đang tải...' : 'Tải ảnh lên' }}</span>
                                            <input
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp"
                                                class="hidden"
                                                @change="handleBannerFileUpload('desktop', $event)"
                                            />
                                        </label>
                                        <input
                                            v-model="bannerForm.image_desktop"
                                            type="text"
                                            placeholder="Hoặc dán link /uploads/..."
                                            class="flex-1 min-w-0 px-2.5 py-1.5 rounded-lg border border-slate-300 font-mono text-[11px]"
                                        />
                                        <button
                                            v-if="bannerForm.image_desktop"
                                            type="button"
                                            @click="bannerForm.image_desktop = ''"
                                            class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 cursor-pointer shrink-0"
                                            title="Xóa ảnh Desktop"
                                        >
                                            <X class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Mobile Image -->
                                <div class="p-3 rounded-lg bg-white border border-slate-200 space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold text-[#0B1437] inline-flex items-center gap-1.5">
                                            <Smartphone class="w-3.5 h-3.5 text-emerald-600" />
                                            <span>Ảnh Điện thoại (Mobile)</span>
                                        </span>
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono-num text-[10px] font-bold">
                                            {{ bannerForm.placement === 'popup_modal' ? '800 × 1000 px (4:5 / 1:1)' : '800 × 450 px (16:9)' }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold cursor-pointer transition shrink-0">
                                            <Loader2 v-if="uploadingBannerSlot === 'mobile'" class="w-3.5 h-3.5 animate-spin" />
                                            <Upload v-else class="w-3.5 h-3.5" />
                                            <span>{{ uploadingBannerSlot === 'mobile' ? 'Đang tải...' : 'Tải ảnh lên' }}</span>
                                            <input
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp"
                                                class="hidden"
                                                @change="handleBannerFileUpload('mobile', $event)"
                                            />
                                        </label>
                                        <input
                                            v-model="bannerForm.image_mobile"
                                            type="text"
                                            placeholder="Bỏ trống = dùng ảnh Web"
                                            class="flex-1 min-w-0 px-2.5 py-1.5 rounded-lg border border-slate-300 font-mono text-[11px]"
                                        />
                                        <button
                                            v-if="bannerForm.image_mobile"
                                            type="button"
                                            @click="bannerForm.image_mobile = ''"
                                            class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 cursor-pointer shrink-0"
                                            title="Xóa ảnh Mobile"
                                        >
                                            <X class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block font-semibold mb-1 text-slate-700">Màu nền đầu (khi không dùng ảnh)</label>
                                    <input v-model="bannerForm.bg_from" type="color" class="w-full h-8 rounded border border-slate-300 cursor-pointer" />
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1 text-slate-700">Màu nền cuối (khi không dùng ảnh)</label>
                                    <input v-model="bannerForm.bg_to" type="color" class="w-full h-8 rounded border border-slate-300 cursor-pointer" />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Bắt đầu hiển thị (trống = hiện ngay)</label>
                                <input v-model="bannerForm.starts_at" type="datetime-local" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Kết thúc hiển thị (Countdown)</label>
                                <input v-model="bannerForm.ends_at" type="datetime-local" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <!-- Live Visual Preview (Desktop / Mobile Toggle) -->
                        <div class="pt-2 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-[11px] font-bold uppercase text-slate-500">Xem trước trực tiếp</div>
                                <div class="inline-flex p-0.5 rounded-lg bg-slate-100 border border-slate-200">
                                    <button
                                        type="button"
                                        @click="bannerPreviewDevice = 'desktop'"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold cursor-pointer"
                                        :class="bannerPreviewDevice === 'desktop' ? 'bg-[#0B1437] text-white' : 'text-slate-600'"
                                    >
                                        <Monitor class="w-3 h-3" />
                                        <span>Web (4:1)</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="bannerPreviewDevice = 'mobile'"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold cursor-pointer"
                                        :class="bannerPreviewDevice === 'mobile' ? 'bg-[#0B1437] text-white' : 'text-slate-600'"
                                    >
                                        <Smartphone class="w-3 h-3" />
                                        <span>Điện thoại (16:9)</span>
                                    </button>
                                </div>
                            </div>
                            <div :class="bannerPreviewDevice === 'mobile' ? 'max-w-[360px] mx-auto' : 'w-full'">
                                <EventShowcaseBanner
                                    :preview-mode="bannerPreviewDevice"
                                    :banners="[
                                        {
                                            id: 0,
                                            eyebrow: bannerForm.eyebrow,
                                            title: bannerForm.title || 'Tiêu đề sự kiện hiển thị tại đây',
                                            subtitle: bannerForm.subtitle,
                                            cta_text: bannerForm.cta_text,
                                            cta_url: bannerForm.cta_url,
                                            coupon_code_display: bannerForm.coupon_code_display,
                                            image_desktop: bannerForm.image_desktop,
                                            image_mobile: bannerForm.image_mobile,
                                            bg_from: bannerForm.bg_from,
                                            bg_to: bannerForm.bg_to,
                                            ends_at: bannerForm.ends_at || undefined,
                                        },
                                    ]"
                                />
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex items-center justify-between gap-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="bannerForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span class="font-semibold">Hiển thị Banner ngay</span>
                            </label>
                            <div class="flex gap-2">
                                <button type="button" @click="showBannerModal = false" class="px-4 py-2 rounded-lg border border-slate-300 font-semibold cursor-pointer">Đóng</button>
                                <button type="submit" :disabled="bannerForm.processing" class="px-4 py-2 rounded-lg bg-[#0B1437] text-white font-semibold cursor-pointer">
                                    {{ editingBannerId ? 'Lưu cập nhật Banner' : 'Xuất bản Banner' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </Teleport>

            <!-- MODAL CREATE / EDIT COUPON -->
            <Teleport to="body">
                <div v-if="showCouponModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showCouponModal = false">
                    <form
                        @submit.prevent="saveCoupon"
                        class="w-full max-w-lg bg-white rounded-xl border border-slate-200 shadow-2xl p-6 space-y-3.5 text-xs max-h-[90vh] overflow-y-auto"
                    >
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <h3 class="text-sm font-bold text-[#0B1437]">
                                {{ editingCouponId ? 'Chỉnh sửa Mã Giảm Giá (Coupon)' : 'Phát hành Mã Giảm Giá (Coupon)' }}
                            </h3>
                            <button type="button" @click="showCouponModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Mã giảm giá</label>
                                <input v-model="couponForm.code" type="text" required placeholder="TET2027" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono uppercase font-bold" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Gắn với Chiến dịch</label>
                                <select v-model="couponForm.campaign_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                    <option :value="null">— Độc lập —</option>
                                    <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Loại giảm</label>
                                <select v-model="couponForm.discount_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                    <option value="percent">Theo %</option>
                                    <option value="fixed">Tiền cố định (đ)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Giá trị giảm</label>
                                <input v-model.number="couponForm.discount_value" type="number" required min="1" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Giảm tối đa (VNĐ)</label>
                                <input v-model.number="couponForm.max_discount_amount" type="number" min="0" placeholder="Không giới hạn" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Đơn tối thiểu (đ)</label>
                                <input v-model.number="couponForm.min_order_amount" type="number" min="0" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Tổng lượt tối đa</label>
                                <input v-model.number="couponForm.max_uses" type="number" min="1" placeholder="Không giới hạn" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Lượt / 1 khách</label>
                                <input v-model.number="couponForm.max_uses_per_user" type="number" min="1" max="100" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Áp dụng cho</label>
                                <select v-model="couponForm.applies_to" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                    <option value="all">VPS &amp; Proxy</option>
                                    <option value="vps">Chỉ Cloud VPS</option>
                                    <option value="proxy">Chỉ Proxy IPv4</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Mở từ</label>
                                <input v-model="couponForm.starts_at" type="datetime-local" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Hết hạn</label>
                                <input v-model="couponForm.ends_at" type="datetime-local" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Mô tả ngắn</label>
                            <input v-model="couponForm.description" type="text" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                        </div>

                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer font-semibold text-[#0B1437]">
                                <input v-model="couponForm.is_huntable" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Hiển thị tại Kho Săn Voucher (khách bấm Lưu mã vào ví &amp; chọn 1-click khi mua)</span>
                            </label>
                            <div v-if="couponForm.is_huntable" class="grid grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block font-semibold mb-1 text-slate-700">Giới hạn suất săn lưu mã</label>
                                    <input
                                        v-model.number="couponForm.claim_limit"
                                        type="number"
                                        min="1"
                                        placeholder="Bỏ trống = theo Tổng lượt tối đa"
                                        class="w-full px-3 py-1.5 rounded-lg border border-slate-300 bg-white font-mono-num"
                                    />
                                </div>
                                <div class="text-[11px] text-slate-600 self-end pb-1">
                                    Mỗi tài khoản chỉ săn lưu tối đa 1 lần vào Ví Voucher cá nhân.
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-4 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="couponForm.protect_cost_floor" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Bảo vệ sàn giá vốn</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="couponForm.apply_on_renew" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Cho phép dùng khi Gia hạn</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="couponForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Đang hoạt động</span>
                            </label>
                        </div>

                        <div
                            v-if="couponRisk.total > 0"
                            class="p-3 rounded-xl border text-xs flex items-start gap-2"
                            :class="
                                couponRisk.severity === 'danger'
                                    ? 'bg-rose-50 border-rose-300 text-rose-900'
                                    : couponRisk.severity === 'warning'
                                      ? 'bg-amber-50 border-amber-300 text-amber-900'
                                      : 'bg-emerald-50 border-emerald-200 text-emerald-900'
                            "
                        >
                            <AlertTriangle v-if="couponRisk.severity !== 'safe'" class="w-4 h-4 shrink-0 mt-0.5" :class="couponRisk.severity === 'danger' ? 'text-rose-600' : 'text-amber-600'" />
                            <ShieldCheck v-else class="w-4 h-4 shrink-0 mt-0.5 text-emerald-600" />
                            <div>
                                <div v-if="couponRisk.severity === 'danger'" class="font-extrabold">
                                    CẢNH BÁO LỖ VỐN: {{ couponRisk.losses.length }} gói sẽ bị bán dưới giá gốc nếu dùng mã này!
                                </div>
                                <div v-else-if="couponRisk.severity === 'warning'" class="font-bold">
                                    {{ couponRisk.clamped.length }} gói sẽ chạm sàn giá vốn (ngưỡng giảm tối đa an toàn không chạm sàn: ≤ {{ couponRisk.minSafePct }}%).
                                </div>
                                <div v-else class="font-bold">
                                    Mã an toàn lợi nhuận trên toàn bộ {{ couponRisk.total }} gói (ngưỡng an toàn tối đa: {{ couponRisk.minSafePct }}%).
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                            <button type="button" @click="showCouponModal = false" class="px-4 py-2 rounded-lg border border-slate-300 font-semibold cursor-pointer">Hủy</button>
                            <button type="submit" :disabled="couponForm.processing" class="px-4 py-2 rounded-lg bg-[#0B1437] text-white font-semibold cursor-pointer">
                                {{ editingCouponId ? 'Lưu cập nhật Mã' : 'Phát hành Mã' }}
                            </button>
                        </div>
                    </form>
                </div>
            </Teleport>

            <!-- MODAL CREATE / EDIT FLASH SALE -->
            <Teleport to="body">
                <div v-if="showFlashSaleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showFlashSaleModal = false">
                    <form
                        @submit.prevent="saveFlashSale"
                        class="w-full max-w-xl bg-white rounded-xl border border-slate-200 shadow-2xl p-6 space-y-3.5 text-xs max-h-[90vh] overflow-y-auto"
                    >
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <h3 class="text-sm font-bold text-[#0B1437]">
                                {{ editingFlashSaleId ? 'Chỉnh sửa phiên Flash Sale theo suất' : 'Tạo phiên Flash Sale theo suất mới' }}
                            </h3>
                            <button type="button" @click="showFlashSaleModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Tên chương trình Flash Sale</label>
                                <input v-model="flashSaleForm.name" type="text" required placeholder="Flash Sale Giờ Vàng VPS 4GB" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Nhãn hiển thị (Badge)</label>
                                <input v-model="flashSaleForm.badge_text" type="text" placeholder="FLASH SALE -25%" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-bold" />
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Gắn trực tiếp vào Gói gốc (KHÔNG tạo gói trùng lặp)</label>
                            <select v-model="flashSaleForm.plan_slug" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white font-semibold">
                                <option v-for="item in flashSaleEligibleItems" :key="item.slug" :value="item.slug">
                                    [{{ item.service_type.toUpperCase() }}] {{ item.name }} ({{ item.slug }}) — Niêm yết {{ formatVnd(item.retail_price) }} · Vốn {{ formatVnd(item.provider_cost) }}
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Kiểu giảm giá</label>
                                <select v-model="flashSaleForm.discount_type" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                    <option value="percent">Giảm theo phần trăm (%)</option>
                                    <option value="fixed_price">Đặt giá Flash Sale cố định / tháng (VNĐ)</option>
                                    <option value="fixed">Giảm trực tiếp tiền mặt / tháng (VNĐ)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">
                                    {{
                                        flashSaleForm.discount_type === 'percent'
                                            ? 'Mức giảm (%)'
                                            : flashSaleForm.discount_type === 'fixed_price'
                                              ? 'Giá bán Flash Sale / tháng (VNĐ)'
                                              : 'Số tiền giảm / tháng (VNĐ)'
                                    }}
                                </label>
                                <input v-model.number="flashSaleForm.discount_value" type="number" required min="1" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Tổng số suất mở bán</label>
                                <input v-model.number="flashSaleForm.total_slots" type="number" required min="1" max="10000" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Tối đa suất / 1 KH</label>
                                <input v-model.number="flashSaleForm.max_per_user" type="number" required min="1" max="50" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Chu kỳ áp dụng tối đa (tháng)</label>
                                <input v-model.number="flashSaleForm.max_months" type="number" required min="1" max="36" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Giờ bắt đầu mở săn (trống = mở ngay)</label>
                                <input v-model="flashSaleForm.starts_at" type="datetime-local" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Giờ kết thúc (Countdown)</label>
                                <input v-model="flashSaleForm.ends_at" type="datetime-local" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-4 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="flashSaleForm.protect_cost_floor" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Bảo vệ sàn giá vốn (không bán lỗ)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="flashSaleForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Kích hoạt phiên Flash Sale</span>
                            </label>
                        </div>

                        <div
                            v-if="flashSaleRisk"
                            class="p-3.5 rounded-xl border text-xs flex items-start gap-2.5"
                            :class="
                                flashSaleRisk.severity === 'danger'
                                    ? 'bg-rose-50 border-rose-300 text-rose-900'
                                    : flashSaleRisk.severity === 'warning'
                                      ? 'bg-amber-50 border-amber-300 text-amber-900'
                                      : 'bg-emerald-50 border-emerald-200 text-emerald-900'
                            "
                        >
                            <AlertTriangle v-if="flashSaleRisk.severity !== 'safe'" class="w-4 h-4 shrink-0 mt-0.5" :class="flashSaleRisk.severity === 'danger' ? 'text-rose-600' : 'text-amber-600'" />
                            <ShieldCheck v-else class="w-4 h-4 shrink-0 mt-0.5 text-emerald-600" />
                            <div class="space-y-1">
                                <div class="font-extrabold">
                                    Giá Flash Sale thực tế: {{ formatVnd(flashSaleRisk.effectiveSale) }}/tháng
                                    (Giá vốn: {{ formatVnd(flashSaleRisk.cost) }} ·
                                    {{ flashSaleRisk.profit >= 0 ? `Lãi +${formatVnd(flashSaleRisk.profit)}/suất` : `LỖ -${formatVnd(Math.abs(flashSaleRisk.profit))}/suất` }})
                                </div>
                                <div v-if="flashSaleRisk.severity === 'danger'" class="font-semibold">
                                    CẢNH BÁO NGÂN SÁCH MARKETING: Tổng mức bù lỗ tối đa cho {{ flashSaleForm.total_slots }} suất là
                                    <strong>{{ formatVnd(flashSaleRisk.totalMaxLoss) }}</strong>. (Flash Sale tự động chặn cộng dồn Coupon &amp; Sự kiện khác).
                                </div>
                                <div v-else-if="flashSaleRisk.severity === 'warning'" class="font-semibold">
                                    Giá tính toán ({{ formatVnd(flashSaleRisk.rawSale) }}) thấp hơn sàn an toàn ({{ formatVnd(flashSaleRisk.floorPrice) }}) nên hệ thống tự nâng về giá sàn để không bị lỗ.
                                </div>
                                <div v-else class="font-semibold">
                                    Biên lợi nhuận an toàn (+{{ flashSaleRisk.marginPct.toFixed(1) }}%). Độc lập hoàn toàn, không bị cộng dồn Sự kiện hay Coupon.
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                            <button type="button" @click="showFlashSaleModal = false" class="px-4 py-2 rounded-lg border border-slate-300 font-semibold cursor-pointer">Hủy</button>
                            <button type="submit" :disabled="flashSaleForm.processing" class="px-4 py-2 rounded-lg bg-[#0B1437] text-white font-semibold cursor-pointer">
                                {{ editingFlashSaleId ? 'Lưu cập nhật Flash Sale' : 'Mở phiên Flash Sale' }}
                            </button>
                        </div>
                    </form>
                </div>
            </Teleport>
        </div>
    </AdminLayout>
</template>
