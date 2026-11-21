<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CountdownClock from '@/Components/CountdownClock.vue';
import SaleStarburstBadge from '@/Components/SaleStarburstBadge.vue';
import { formatVnd } from '@/Composables/useFormat';
import { openLoginPrompt, requireAuthOrVisit } from '@/Composables/useLoginPrompt';
import { Zap, Ticket, ArrowRight, Check, Clock, Copy, ChevronLeft, ChevronRight, Flame } from 'lucide-vue-next';

export interface FlashSaleDeal {
    id: number;
    name: string;
    badge_text: string;
    service_type: 'vps' | 'proxy';
    plan_slug: string;
    plan_name: string;
    spec_summary: string;
    product_id: number;
    list_price: number;
    sale_price: number;
    discount_amount: number;
    discount_percent: number;
    total_slots: number;
    sold_slots: number;
    remaining_slots: number;
    max_per_user: number;
    max_months: number;
    is_live: boolean;
    is_upcoming: boolean;
    is_sold_out: boolean;
    starts_at?: string | null;
    ends_at?: string | null;
    starts_display?: string | null;
    buy_url: string;
    user_used_slots?: number;
    user_reached_limit?: boolean;
}

export interface HuntVoucherItem {
    id: number;
    code: string;
    description: string;
    discount_label: string;
    discount_type: string;
    discount_value: number;
    max_discount_amount: number | null;
    min_order_amount: number;
    applies_to: 'all' | 'vps' | 'proxy';
    claim_limit: number | null;
    claimed_count: number;
    remaining_claims: number | null;
    is_out_of_stock: boolean;
    ends_at?: string | null;
    is_claimed?: boolean;
    is_used?: boolean;
}

const props = withDefaults(
    defineProps<{
        flashSales?: FlashSaleDeal[];
        huntVouchers?: HuntVoucherItem[];
        compact?: boolean;
    }>(),
    {
        flashSales: () => [],
        huntVouchers: () => [],
        compact: false,
    }
);

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const isVpsOutOfStock = computed(() => Boolean((page.props.system as any)?.vps_out_of_stock));

const hasDeals = computed(() => props.flashSales && props.flashSales.length > 0);
const hasVouchers = computed(() => props.huntVouchers && props.huntVouchers.length > 0);
const hasBoth = computed(() => hasDeals.value && hasVouchers.value);
const hasAnything = computed(() => hasDeals.value || hasVouchers.value);

const activeTab = ref<'deals' | 'vouchers'>(hasDeals.value ? 'deals' : 'vouchers');

watch([hasDeals, hasVouchers], () => {
    if (activeTab.value === 'deals' && !hasDeals.value && hasVouchers.value) {
        activeTab.value = 'vouchers';
    } else if (activeTab.value === 'vouchers' && !hasVouchers.value && hasDeals.value) {
        activeTab.value = 'deals';
    }
});

const earliestEnd = computed(() => {
    const liveEnds = (props.flashSales || [])
        .filter((d) => d.is_live && d.ends_at)
        .map((d) => d.ends_at as string)
        .sort();
    return liveEnds[0] || null;
});

const dealsRailRef = ref<HTMLElement | null>(null);
const vouchersRailRef = ref<HTMLElement | null>(null);

function scrollRail(dir: -1 | 1) {
    const el = activeTab.value === 'deals' ? dealsRailRef.value : vouchersRailRef.value;
    if (!el) return;
    el.scrollBy({ left: dir * 340, behavior: 'smooth' });
}

const showCarouselArrows = computed(() => {
    const count = activeTab.value === 'deals' ? (props.flashSales?.length || 0) : (props.huntVouchers?.length || 0);
    return count > 3;
});

const claimingId = ref<number | null>(null);
const copiedCode = ref<string | null>(null);

function handleBuyDeal(deal: FlashSaleDeal) {
    if (deal.service_type === 'vps' && isVpsOutOfStock.value) {
        return;
    }
    requireAuthOrVisit(deal.buy_url, `Săn Flash Sale: ${deal.plan_name}`, user.value);
}

function claimVoucher(v: HuntVoucherItem) {
    if (!user.value) {
        openLoginPrompt({
            actionLabel: `Lưu mã voucher ${v.code} vào ví`,
            redirectUrl: page.url || '/',
        });
        return;
    }
    if (v.is_claimed || v.is_out_of_stock || claimingId.value === v.id) return;
    claimingId.value = v.id;
    router.post(
        `/vouchers/${v.id}/claim`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                claimingId.value = null;
            },
        }
    );
}

async function copyCode(code: string) {
    try {
        await navigator.clipboard.writeText(code);
        copiedCode.value = code;
        setTimeout(() => {
            if (copiedCode.value === code) copiedCode.value = null;
        }, 1800);
    } catch {
        // ignore
    }
}

function slotPercent(d: FlashSaleDeal): number {
    if (!d.total_slots) return 100;
    return Math.min(100, Math.max(8, Math.round((d.sold_slots / d.total_slots) * 100)));
}

function scopeLabel(scope: string): string {
    if (scope === 'vps') return 'Cloud VPS';
    if (scope === 'proxy') return 'Proxy IPv4';
    return 'VPS & Proxy';
}

function shortVoucherBadge(v: HuntVoucherItem): string {
    if (v.discount_type === 'percent') return `-${v.discount_value}%`;
    if (v.discount_value >= 1000 && v.discount_value % 1000 === 0) {
        return `-${v.discount_value / 1000}K`;
    }
    return `-${formatVnd(v.discount_value)}`;
}
</script>

<template>
    <div v-if="hasAnything" class="rounded-2xl border border-red-200/80 bg-white shadow-sm overflow-hidden">
        <!-- Bold Crimson Flash Sale Header (Mobile 2 compact rows, Desktop 3-col grid locks the 2-tab pill strictly in place) -->
        <div class="bg-gradient-to-r from-[#B91C1C] via-[#DC2626] to-[#EA580C] px-3.5 py-3 sm:px-6 grid grid-cols-1 lg:grid-cols-[1fr_auto_1fr] items-center gap-2.5 lg:gap-3">
            <!-- Left: Starburst Emblem + Title (+ Mobile Compact Countdown on right of row 1) -->
            <div class="flex items-center justify-between gap-2.5 min-w-0">
                <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                    <SaleStarburstBadge :size="36" variant="amber">
                        <Zap class="w-4 h-4 fill-[#7C2D12] text-[#7C2D12]" />
                    </SaleStarburstBadge>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-[13.5px] sm:text-base font-black text-white tracking-tight uppercase leading-tight">
                                {{ hasBoth ? 'Flash Sale & Voucher' : hasDeals ? 'Flash Sale giờ vàng' : 'Kho Voucher ưu đãi' }}
                            </h2>
                            <span
                                v-if="hasDeals"
                                class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-white/18 text-[11px] font-bold text-amber-100"
                            >
                                <Flame class="w-3 h-3 text-amber-300 fill-amber-300" />
                                Số lượng có hạn
                            </span>
                        </div>
                        <p class="hidden sm:block text-[11.5px] text-red-100/95 font-medium mt-0.5 truncate">
                            {{
                                activeTab === 'deals' && hasDeals
                                    ? 'Ưu đãi sâu theo khung giờ — hết suất về giá thường.'
                                    : 'Lưu sẵn mã vào ví để chọn nhanh khi thanh toán.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Mobile-only inline countdown (saves an entire header row on phones!) -->
                <div v-if="earliestEnd" class="lg:hidden shrink-0">
                    <CountdownClock :ends-at="earliestEnd" compact />
                </div>
            </div>

            <!-- Center: Fixed-Position 2 Tabs (Full-width 50/50 on mobile, centered pill on desktop) -->
            <div v-if="hasBoth" class="w-full lg:w-auto lg:justify-self-center">
                <div class="grid grid-cols-2 lg:inline-flex p-0.5 rounded-lg bg-black/20 border border-white/15 w-full lg:w-auto">
                    <button
                        type="button"
                        @click="activeTab = 'deals'"
                        class="inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 py-1.5 rounded-md text-xs font-bold cursor-pointer whitespace-nowrap"
                        :class="activeTab === 'deals' ? 'bg-white text-red-700 shadow-xs' : 'text-white/95 hover:text-white'"
                    >
                        <Zap class="w-3.5 h-3.5 shrink-0" />
                        <span>Flash Sale ({{ flashSales.length }})</span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'vouchers'"
                        class="inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 py-1.5 rounded-md text-xs font-bold cursor-pointer whitespace-nowrap"
                        :class="activeTab === 'vouchers' ? 'bg-white text-red-700 shadow-xs' : 'text-white/95 hover:text-white'"
                    >
                        <Ticket class="w-3.5 h-3.5 shrink-0" />
                        <span>Kho Voucher ({{ huntVouchers.length }})</span>
                    </button>
                </div>
            </div>
            <div v-else class="hidden lg:block"></div>

            <!-- Right (Desktop only): Always-visible Countdown + Carousel Arrows -->
            <div class="hidden lg:flex items-center gap-2 lg:justify-self-end">
                <div v-if="earliestEnd" class="flex items-center gap-1.5">
                    <span class="hidden xl:inline-flex items-center gap-1 text-[11px] font-bold text-red-100">
                        <Clock class="w-3.5 h-3.5" />
                        <span>Kết thúc:</span>
                    </span>
                    <CountdownClock :ends-at="earliestEnd" :compact="compact" />
                </div>

                <div v-if="showCarouselArrows" class="inline-flex items-center gap-1">
                    <button
                        type="button"
                        @click="scrollRail(-1)"
                        class="w-7 h-7 rounded-lg bg-white/15 hover:bg-white/25 text-white inline-flex items-center justify-center cursor-pointer transition"
                        aria-label="Trượt sang trái"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button
                        type="button"
                        @click="scrollRail(1)"
                        class="w-7 h-7 rounded-lg bg-white/15 hover:bg-white/25 text-white inline-flex items-center justify-center cursor-pointer transition"
                        aria-label="Trượt sang phải"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Body: Full-width when 1 item on mobile, smooth hidden-scrollbar peek carousel when >= 2 -->
        <div class="p-3.5 sm:p-5 bg-gradient-to-b from-red-50/35 to-white">
            <!-- TAB 1: FLASH SALE -->
            <div
                v-if="activeTab === 'deals' && hasDeals"
                ref="dealsRailRef"
                class="flex gap-3 sm:gap-3.5 overflow-x-auto snap-x snap-mandatory [&::-webkit-scrollbar]:hidden [scrollbar-width:none]"
                :class="flashSales.length <= 3 ? 'md:grid md:overflow-visible ' + (flashSales.length === 1 ? 'md:grid-cols-1 md:max-w-md' : flashSales.length === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3') : ''"
            >
                <div
                    v-for="deal in flashSales"
                    :key="deal.id"
                    class="relative snap-start shrink-0 rounded-xl border p-4 flex flex-col justify-between transition-all"
                    :class="[
                        flashSales.length === 1
                            ? 'w-full'
                            : flashSales.length > 3
                              ? 'w-[86%] sm:w-[320px]'
                              : 'w-[86%] sm:w-[320px] md:w-auto',
                        deal.is_sold_out
                            ? 'border-slate-200 bg-slate-50/80 opacity-80'
                            : 'border-red-200/80 hover:border-red-500 bg-white hover:shadow-md',
                    ]"
                >
                    <!-- Starburst Discount Seal (-25%) -->
                    <div class="absolute top-3 right-3">
                        <SaleStarburstBadge
                            :label="`-${deal.discount_percent}%`"
                            :size="46"
                            :variant="deal.is_sold_out ? 'amber' : 'red'"
                        />
                    </div>

                    <div>
                        <!-- Service Type Tag -->
                        <div class="flex items-center gap-1.5 pr-12 mb-2">
                            <span
                                class="text-[10.5px] font-bold px-2 py-0.5 rounded-md border"
                                :class="
                                    deal.service_type === 'proxy'
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200/80'
                                        : 'bg-slate-100 text-slate-700 border-slate-200'
                                "
                            >
                                {{ deal.service_type === 'proxy' ? 'Proxy IPv4' : 'Cloud VPS' }}
                            </span>
                            <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-md bg-red-50 text-red-700 border border-red-200/80">
                                Gói {{ deal.max_months }} tháng
                            </span>
                        </div>

                        <!-- Plan Title & Specs -->
                        <div class="text-[15px] font-extrabold text-[#0B1437] leading-snug pr-12">{{ deal.plan_name }}</div>
                        <div class="text-xs text-slate-600 font-medium mt-0.5">{{ deal.spec_summary }}</div>

                        <!-- Pricing -->
                        <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-baseline justify-between gap-2">
                            <div>
                                <div class="text-xs font-mono-num text-slate-400 line-through">{{ formatVnd(deal.list_price) }}</div>
                                <div class="flex items-baseline gap-1">
                                    <span class="text-xl font-black font-mono-num text-red-600">{{ formatVnd(deal.sale_price) }}</span>
                                    <span class="text-xs text-slate-500 font-medium">/ tháng</span>
                                </div>
                            </div>
                            <div class="text-right text-[11px] text-slate-500 font-medium">
                                Tối đa {{ deal.max_per_user }} suất/KH
                            </div>
                        </div>

                        <!-- Fire Progress Bar -->
                        <div class="mt-3">
                            <div class="relative w-full h-5 rounded-full bg-rose-100 overflow-hidden flex items-center justify-center">
                                <div
                                    class="absolute left-0 top-0 bottom-0 rounded-full transition-all duration-300"
                                    :class="deal.is_sold_out ? 'bg-slate-400' : 'bg-gradient-to-r from-red-600 to-orange-500'"
                                    :style="{ width: slotPercent(deal) + '%' }"
                                ></div>
                                <span class="relative z-10 text-[10.5px] font-extrabold tracking-wide drop-shadow-2xs" :class="slotPercent(deal) >= 55 ? 'text-white' : 'text-red-900'">
                                    {{
                                        deal.is_sold_out
                                            ? 'Đã hết suất'
                                            : deal.is_upcoming
                                              ? `Mở lúc ${deal.starts_display}`
                                              : `Còn ${deal.remaining_slots}/${deal.total_slots} suất`
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="mt-3.5">
                        <div
                            v-if="deal.service_type === 'vps' && isVpsOutOfStock"
                            class="w-full py-2 px-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold text-center"
                        >
                            Tạm hết hàng VPS
                        </div>
                        <div
                            v-else-if="deal.is_sold_out"
                            class="w-full py-2 px-3 rounded-lg bg-slate-100 text-slate-500 text-xs font-bold text-center"
                        >
                            Đã hết suất
                        </div>
                        <div
                            v-else-if="deal.is_upcoming"
                            class="w-full py-2 px-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold text-center"
                        >
                            Mở lúc {{ deal.starts_display }}
                        </div>
                        <div
                            v-else-if="deal.user_reached_limit"
                            class="w-full py-2 px-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold text-center inline-flex items-center justify-center gap-1.5"
                        >
                            <Check class="w-3.5 h-3.5" />
                            <span>Bạn đã mua suất này</span>
                        </div>
                        <button
                            v-else
                            type="button"
                            @click="handleBuyDeal(deal)"
                            class="w-full py-2.5 px-3 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-extrabold inline-flex items-center justify-center gap-1.5 shadow-xs transition-colors cursor-pointer"
                        >
                            <span>Săn ngay</span>
                            <ArrowRight class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: KHO VOUCHER (CUỐNG VÉ ĐỤC LỖ) -->
            <div
                v-else-if="hasVouchers"
                ref="vouchersRailRef"
                class="flex gap-3 sm:gap-3.5 overflow-x-auto snap-x snap-mandatory [&::-webkit-scrollbar]:hidden [scrollbar-width:none]"
                :class="huntVouchers.length <= 3 ? 'md:grid md:overflow-visible ' + (huntVouchers.length === 1 ? 'md:grid-cols-1 md:max-w-md' : huntVouchers.length === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3') : ''"
            >
                <div
                    v-for="v in huntVouchers"
                    :key="v.id"
                    class="relative snap-start shrink-0 rounded-xl border bg-white overflow-hidden flex transition-all"
                    :class="[
                        huntVouchers.length === 1
                            ? 'w-full'
                            : huntVouchers.length > 3
                              ? 'w-[88%] sm:w-[330px]'
                              : 'w-[88%] sm:w-[330px] md:w-auto',
                        v.is_claimed
                            ? 'border-emerald-300'
                            : v.is_out_of_stock
                              ? 'border-slate-200 opacity-75'
                              : 'border-red-200/90 hover:border-red-400 hover:shadow-sm',
                    ]"
                >
                    <!-- Left Ticket Stub -->
                    <div
                        class="w-[74px] sm:w-20 shrink-0 flex flex-col items-center justify-center px-1.5 sm:px-2 py-3 text-center relative border-r border-dashed"
                        :class="
                            v.is_claimed
                                ? 'bg-emerald-600 text-white border-emerald-400/60'
                                : v.is_out_of_stock
                                  ? 'bg-slate-200 text-slate-600 border-slate-300'
                                  : 'bg-gradient-to-b from-red-600 to-orange-600 text-white border-red-300/60'
                        "
                    >
                        <!-- Ticket notches -->
                        <span class="w-3 h-3 rounded-full bg-white absolute -top-1.5 -right-1.5 border border-slate-200"></span>
                        <span class="w-3 h-3 rounded-full bg-white absolute -bottom-1.5 -right-1.5 border border-slate-200"></span>

                        <span class="font-mono-num text-[15px] sm:text-base font-black leading-none">{{ shortVoucherBadge(v) }}</span>
                        <span class="text-[9px] sm:text-[9.5px] font-bold uppercase tracking-wider mt-1 opacity-90">
                            {{ scopeLabel(v.applies_to) }}
                        </span>
                    </div>

                    <!-- Right Ticket Body -->
                    <div class="flex-1 min-w-0 p-2.5 sm:p-3 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-mono text-xs font-black text-[#0B1437] tracking-wide">{{ v.code }}</div>
                            <div class="text-[11px] text-slate-600 font-medium mt-0.5 line-clamp-1" :title="v.description">
                                {{ v.description }}
                            </div>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10.5px] text-slate-500 mt-1">
                                <span>{{ v.min_order_amount > 0 ? `Đơn từ ${formatVnd(v.min_order_amount)}` : 'Mọi đơn hàng' }}</span>
                                <span v-if="v.remaining_claims !== null" class="font-bold whitespace-nowrap" :class="v.is_out_of_stock ? 'text-slate-400' : 'text-red-600'">
                                    {{ v.is_out_of_stock ? 'Hết lượt' : `Còn ${v.remaining_claims} mã` }}
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0">
                            <div v-if="v.is_claimed" class="flex flex-col items-end gap-1">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white text-[11px] font-bold">
                                    <Check class="w-3.5 h-3.5" />
                                    <span>{{ v.is_used ? 'Đã dùng' : 'Đã lưu' }}</span>
                                </span>
                                <button
                                    type="button"
                                    @click="copyCode(v.code)"
                                    class="inline-flex items-center gap-1 text-[10.5px] font-semibold text-slate-500 hover:text-red-600 cursor-pointer"
                                >
                                    <Copy class="w-3 h-3" />
                                    <span>{{ copiedCode === v.code ? 'Đã chép' : 'Chép mã' }}</span>
                                </button>
                            </div>
                            <span
                                v-else-if="v.is_out_of_stock"
                                class="inline-block px-2.5 py-1.5 rounded-lg bg-slate-200 text-slate-500 text-[11px] font-bold"
                            >
                                Hết mã
                            </span>
                            <button
                                v-else
                                type="button"
                                :disabled="claimingId === v.id"
                                @click="claimVoucher(v)"
                                class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-bold cursor-pointer transition active:scale-95 disabled:opacity-60 whitespace-nowrap"
                            >
                                {{ claimingId === v.id ? 'Đang lưu...' : 'Lưu mã' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
