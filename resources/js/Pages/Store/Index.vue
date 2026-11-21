<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import EventShowcaseBanner from '@/Components/EventShowcaseBanner.vue';
import FlashSaleShowcase from '@/Components/FlashSaleShowcase.vue';
import { formatVnd } from '@/Composables/useFormat';
import { requireAuthOrVisit } from '@/Composables/useLoginPrompt';
import { ArrowRight, AlertCircle, Zap } from 'lucide-vue-next';

const props = defineProps<{
    plans: Array<any>;
    regions: Array<any>;
    billingCycles: Record<string, { months: number; label: string; discount_percent: number }>;
    availablePlanSlugs: string[];
    isOutOfStock?: boolean;
    flashSales?: Array<any>;
    huntVouchers?: Array<any>;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const LayoutComponent = computed(() => (user.value ? ClientLayout : PublicLayout));
const selectedCycle = ref('monthly');

const heroBanners = computed(() => (page.props.promotions as any)?.banners?.hero_showcase || []);

const cycleOptions = computed(() =>
    ['monthly', 'quarterly', 'semi_annually', 'annually']
        .filter((k) => props.billingCycles?.[k])
        .map((k) => ({ key: k, ...props.billingCycles[k] }))
);

function getQuote(plan: any) {
    return plan.cycle_quotes?.[selectedCycle.value] || {
        list_price: plan.list_price_per_month,
        final_price: plan.price_per_month,
        monthly_equivalent: plan.price_per_month,
        discount_amount: 0,
        months: 1,
    };
}
</script>

<template>
    <component :is="LayoutComponent" title="Cửa hàng Cloud VPS SSD">
        <Head title="Bảng giá & Khởi tạo Cloud VPS SSD" />

        <div :class="!user ? 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12' : ''" class="space-y-6">
            <EventShowcaseBanner v-if="heroBanners.length > 0" :banners="heroBanners" />
            <FlashSaleShowcase
                v-if="(flashSales && flashSales.length > 0) || (huntVouchers && huntVouchers.length > 0)"
                :flash-sales="flashSales"
                :hunt-vouchers="huntVouchers"
                compact
            />

            <div v-if="isOutOfStock" class="surface-card p-4 border-amber-300 bg-amber-50 flex items-center gap-3 text-xs text-amber-900">
                <AlertCircle class="w-4 h-4 text-amber-600 shrink-0" />
                <span>Cụm máy chủ Cloud VPS hiện đang tạm ngưng nhận đơn mới để nâng cấp dung lượng vật lý. Bạn vẫn có thể gia hạn các VPS hiện có hoặc sử dụng dịch vụ Proxy IPv4.</span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-extrabold text-[#0B1437] tracking-tight">Chọn cấu hình Cloud VPS SSD</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Đặt tại FPT Datacenter (TP.HCM) · Chip Intel Xeon Gold / Platinum · Full quyền Root / Administrator &amp; mở đủ Port TCP/UDP.</p>
                </div>

                <div class="inline-flex p-1 rounded-lg bg-white border border-slate-200 self-start max-w-full overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]">
                    <button
                        v-for="c in cycleOptions"
                        :key="c.key"
                        type="button"
                        @click="selectedCycle = c.key"
                        class="px-2.5 sm:px-3 py-1.5 rounded-md text-xs font-medium transition-colors cursor-pointer whitespace-nowrap shrink-0"
                        :class="selectedCycle === c.key ? 'bg-[#0B1437] text-white font-semibold' : 'text-slate-600 hover:text-slate-900'"
                    >
                        {{ c.label }}
                        <span v-if="c.discount_percent > 0" class="text-[10px] text-emerald-400 font-mono-num ml-1">-{{ c.discount_percent }}%</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="plan in plans"
                    :key="plan.slug"
                    class="surface-card-interactive p-5 flex flex-col justify-between"
                    :class="plan.is_featured ? 'ring-1 ring-[#1D6AE5] border-[#1D6AE5]' : ''"
                >
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-base font-bold text-[#0B1437]">{{ plan.name }}</h3>
                            <span
                                v-if="getQuote(plan).flash_sale"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-200"
                            >
                                <Zap class="w-3 h-3 fill-red-600 text-red-600" />
                                Flash Sale · Còn {{ getQuote(plan).flash_sale.remaining_slots }}/{{ getQuote(plan).flash_sale.total_slots }}
                            </span>
                            <span
                                v-else-if="plan.badge"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold"
                                :class="plan.is_featured ? 'bg-[#1D6AE5] text-white' : 'bg-slate-100 text-slate-700'"
                            >
                                {{ plan.badge }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">{{ plan.desc }}</p>

                        <div class="my-4 py-3.5 border-y border-slate-100">
                            <div v-if="getQuote(plan).discount_amount > 0" class="flex items-center gap-2 mb-0.5">
                                <span class="text-xs font-mono-num text-slate-400 line-through">{{ formatVnd(getQuote(plan).list_price) }}</span>
                                <span class="px-1.5 py-0.2 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-semibold">
                                    Tiết kiệm {{ formatVnd(getQuote(plan).discount_amount) }}
                                </span>
                            </div>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-2xl font-extrabold font-mono-num text-[#0B1437]">{{ formatVnd(getQuote(plan).final_price) }}</span>
                                <span class="text-xs text-slate-500">/ {{ billingCycles[selectedCycle]?.label }}</span>
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-y-2 text-xs mb-5">
                            <dt class="text-slate-500">Vi xử lý</dt>
                            <dd class="font-mono-num font-semibold text-slate-900 text-right">{{ plan.cores }} vCPU</dd>
                            <dt class="text-slate-500">Bộ nhớ RAM</dt>
                            <dd class="font-mono-num font-semibold text-slate-900 text-right">{{ plan.ram }} GB RAM</dd>
                            <dt class="text-slate-500">Ổ cứng SSD</dt>
                            <dd class="font-mono-num font-semibold text-slate-900 text-right">{{ plan.disk }} GB SSD</dd>
                            <dt class="text-slate-500">Port mạng</dt>
                            <dd class="font-mono-num font-semibold text-slate-900 text-right">
                                {{ plan.network_out_mbps >= 1000 ? '1 Gbps' : plan.network_out_mbps + ' Mbps' }}
                            </dd>
                        </dl>
                    </div>

                    <button
                        type="button"
                        :disabled="isOutOfStock"
                        @click="
                            !isOutOfStock &&
                                requireAuthOrVisit(
                                    `/store/${plan.slug}?cycle=${selectedCycle}`,
                                    `Khởi tạo ${plan.name} (${billingCycles[selectedCycle]?.label || '1 Tháng'})`,
                                    user
                                )
                        "
                        class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 px-4 rounded-lg text-xs font-semibold transition-colors"
                        :class="
                            isOutOfStock
                                ? 'bg-slate-200 text-slate-500 cursor-not-allowed'
                                : plan.is_featured
                                  ? 'bg-[#1D6AE5] hover:bg-[#1557C0] text-white cursor-pointer'
                                  : 'bg-[#0B1437] hover:bg-[#162354] text-white cursor-pointer'
                        "
                    >
                        <span>{{ isOutOfStock ? 'Tạm hết hàng' : 'Tùy chỉnh & Khởi tạo' }}</span>
                        <ArrowRight v-if="!isOutOfStock" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </component>
</template>
