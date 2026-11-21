<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import EventShowcaseBanner from '@/Components/EventShowcaseBanner.vue';
import FlashSaleShowcase from '@/Components/FlashSaleShowcase.vue';
import { formatVnd } from '@/Composables/useFormat';
import { ArrowRight, Zap } from 'lucide-vue-next';

const props = defineProps<{
    products: Array<any>;
    billingCycles: Record<string, any>;
    flashSales?: Array<any>;
    huntVouchers?: Array<any>;
}>();

const page = usePage();
const heroBanners = computed(() => (page.props.promotions as any)?.banners?.hero_showcase || []);
const categoryFilter = ref<'all' | 'datacenter' | 'residential'>('all');

const filteredProducts = computed(() => {
    if (categoryFilter.value === 'all') return props.products;
    return props.products.filter((p) => p.category === categoryFilter.value);
});
</script>

<template>
    <ClientLayout title="Cửa hàng Proxy IPv4">
        <Head title="Bảng giá & Khởi tạo Proxy IPv4 Việt Nam" />

        <div class="space-y-6">
            <EventShowcaseBanner v-if="heroBanners.length > 0" :banners="heroBanners" />

            <FlashSaleShowcase
                v-if="(flashSales && flashSales.length > 0) || (huntVouchers && huntVouchers.length > 0)"
                :flash-sales="flashSales"
                :hunt-vouchers="huntVouchers"
                compact
            />

            <!-- Header & Category Filter (Đồng bộ 100% với Store/Index.vue của Cloud VPS) -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-extrabold text-[#0B1437] tracking-tight">Chọn gói Proxy IPv4 Việt Nam</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Đặt tại FPT Datacenter (TP.HCM) · 100% IP sạch không blacklist · Hỗ trợ chuyển đổi linh hoạt HTTP ↔ SOCKS5 · Cấp IP tự động sau 5–15 giây.
                    </p>
                </div>

                <div class="inline-flex p-1 rounded-lg bg-white border border-slate-200 self-start">
                    <button
                        v-for="tab in [
                            { key: 'all', label: `Tất cả (${products.length})` },
                            { key: 'datacenter', label: 'Datacenter' },
                            { key: 'residential', label: 'Dân cư VN' },
                        ]"
                        :key="tab.key"
                        type="button"
                        @click="categoryFilter = tab.key as any"
                        class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors cursor-pointer whitespace-nowrap"
                        :class="categoryFilter === tab.key ? 'bg-[#0B1437] text-white font-semibold' : 'text-slate-600 hover:text-slate-900'"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Grid thẻ gói Proxy đồng bộ chuẩn Card VPS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="p in filteredProducts"
                    :key="p.id"
                    class="surface-card-interactive p-5 flex flex-col justify-between"
                    :class="p.is_featured ? 'ring-1 ring-[#1D6AE5] border-[#1D6AE5]' : ''"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                :class="
                                    p.category === 'residential'
                                        ? 'bg-sky-50 text-sky-800 border-sky-200'
                                        : 'bg-slate-100 text-slate-700 border-slate-200'
                                "
                            >
                                {{ p.category === 'residential' ? 'IP Dân cư VN' : 'IP Datacenter' }}
                            </span>

                            <span
                                v-if="p.flash_sale"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 shrink-0"
                            >
                                <Zap class="w-3 h-3 fill-red-600 text-red-600" />
                                Flash Sale · Còn {{ p.flash_sale.remaining_slots }}/{{ p.flash_sale.total_slots }}
                            </span>
                            <span
                                v-else-if="p.badge"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold shrink-0"
                                :class="p.is_featured ? 'bg-[#1D6AE5] text-white' : 'bg-slate-100 text-slate-700'"
                            >
                                {{ p.badge }}
                            </span>
                        </div>

                        <h3 class="text-[14.5px] font-bold text-[#0B1437] mt-2.5 leading-snug">{{ p.title }}</h3>
                        <p class="text-xs text-slate-500 mt-1 line-clamp-2 leading-relaxed min-h-[38px]">{{ p.description }}</p>

                        <div class="my-4 py-3.5 border-y border-slate-100">
                            <div v-if="p.has_discount && p.discount_amount > 0" class="flex items-center gap-2 mb-0.5">
                                <span class="text-xs font-mono-num text-slate-400 line-through">{{ formatVnd(p.list_price) }}</span>
                                <span class="px-1.5 py-0.2 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-semibold">
                                    Tiết kiệm {{ formatVnd(p.discount_amount) }}
                                </span>
                            </div>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-2xl font-extrabold font-mono-num text-[#0B1437]">{{ formatVnd(p.price) }}</span>
                                <span class="text-xs text-slate-500">/ IP / tháng</span>
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-y-2 text-xs mb-5">
                            <dt class="text-slate-500">Quyền sử dụng</dt>
                            <dd class="font-semibold text-slate-900 text-right">{{ p.dedicated ? 'Riêng biệt 1:1' : 'Chia sẻ' }}</dd>
                            <dt class="text-slate-500">Giao thức</dt>
                            <dd class="font-mono-num font-semibold text-slate-900 text-right">HTTP &amp; SOCKS5</dd>
                            <dt class="text-slate-500">Quốc gia</dt>
                            <dd class="font-semibold text-slate-900 text-right">Việt Nam (FPT)</dd>
                            <dt class="text-slate-500">Băng thông</dt>
                            <dd class="font-semibold text-slate-900 text-right">Không giới hạn</dd>
                        </dl>
                    </div>

                    <Link
                        :href="`/proxy/store/${p.id}`"
                        class="w-full inline-flex items-center justify-center gap-1.5 py-2.5 px-4 rounded-lg text-xs font-semibold transition-colors"
                        :class="
                            p.is_featured
                                ? 'bg-[#1D6AE5] hover:bg-[#1557C0] text-white'
                                : 'bg-[#0B1437] hover:bg-[#162354] text-white'
                        "
                    >
                        <span>Tùy chỉnh &amp; Khởi tạo</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
