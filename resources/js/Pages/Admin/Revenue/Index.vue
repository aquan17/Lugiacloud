<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { formatVnd } from '@/Composables/useFormat';
import { askConfirm } from '@/Composables/useConfirmAction';
import { Search, Trash2, Ticket } from 'lucide-vue-next';

const props = defineProps<{
    metrics: {
        total_revenue: number;
        total_cost: number;
        total_affiliate: number;
        total_profit: number;
        month_revenue: number;
        month_cost: number;
        month_affiliate: number;
        month_profit: number;
    };
    year: number;
    availableYears: number[];
    monthlyChart: Array<{ month: number; label: string; revenue: number; cost: number; profit: number }>;
    transactions: { data: Array<any>; links: Array<any> };
    filters: { search: string };
}>();

const search = ref(props.filters.search || '');

const maxBarValue = computed(() => Math.max(1, ...props.monthlyChart.map((m) => m.revenue)));

function changeYear(y: number) {
    router.get('/admin/revenue', { year: y, search: search.value || undefined }, { preserveState: true });
}

function submitSearch() {
    router.get('/admin/revenue', { year: props.year, search: search.value || undefined }, { preserveState: true });
}

function filterByCoupon(code: string) {
    search.value = code;
    submitSearch();
}

function confirmDeleteTransaction(t: any) {
    askConfirm({
        title: 'Xóa bản ghi giao dịch',
        message: `Bạn có chắc muốn xóa bản ghi giao dịch ${t.code} (${t.user_email})? Thao tác này không thể hoàn tác.`,
        confirmText: 'Xóa bản ghi',
        onConfirm: () => {
            router.delete(`/admin/revenue/${t.id}`, { preserveScroll: true });
        },
    });
}
</script>

<template>
    <AdminLayout title="Báo cáo Doanh thu & Lợi nhuận Ròng">
        <Head title="Doanh thu & Lợi nhuận" />

        <div class="space-y-6">
            <!-- Net Profit Breakdown Strip -->
            <div class="surface-card grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-slate-200 text-xs rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Doanh thu tháng này</div>
                    <div class="text-2xl font-black font-mono-num text-[#0B1437] mt-2">{{ formatVnd(metrics.month_revenue) }}</div>
                    <div class="text-xs font-mono-num font-semibold text-slate-600 mt-1">Lũy kế: {{ formatVnd(metrics.total_revenue) }}</div>
                </div>
                <div class="p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Giá vốn đối tác (Tháng)</div>
                    <div class="text-2xl font-black font-mono-num text-slate-800 mt-2">{{ formatVnd(metrics.month_cost) }}</div>
                    <div class="text-xs font-mono-num font-semibold text-slate-600 mt-1">Lũy kế: {{ formatVnd(metrics.total_cost) }}</div>
                </div>
                <div class="p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Chi Affiliate (Tháng)</div>
                    <div class="text-2xl font-black font-mono-num text-amber-700 mt-2">{{ formatVnd(metrics.month_affiliate) }}</div>
                    <div class="text-xs font-mono-num font-semibold text-slate-600 mt-1">Lũy kế: {{ formatVnd(metrics.total_affiliate) }}</div>
                </div>
                <div class="p-5 bg-emerald-50/50">
                    <div class="text-xs text-emerald-900 font-bold uppercase tracking-wider">Lợi nhuận ròng (Tháng)</div>
                    <div class="text-2xl font-black font-mono-num text-emerald-800 mt-2">{{ formatVnd(metrics.month_profit) }}</div>
                    <div class="text-xs text-emerald-800 font-bold font-mono-num mt-1">Lãi ròng lũy kế: {{ formatVnd(metrics.total_profit) }}</div>
                </div>
            </div>

            <!-- Yearly Monthly Chart -->
            <div class="surface-card p-6 space-y-4 rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Biểu đồ Doanh thu vs Giá vốn theo tháng ({{ year }})</h3>
                        <p class="text-xs text-slate-600 font-medium mt-0.5">Đã tự động loại trừ các giao dịch lỗi được hoàn tiền tự động.</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            v-for="y in availableYears"
                            :key="y"
                            type="button"
                            @click="changeYear(y)"
                            class="px-3 py-1.5 rounded-lg text-xs font-mono-num font-bold border cursor-pointer transition shadow-2xs"
                            :class="y === year ? 'bg-[#0B1437] text-white border-[#0B1437]' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'"
                        >
                            {{ y }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-2 items-end h-44 pt-4 border-b border-slate-200">
                    <div v-for="m in monthlyChart" :key="m.month" class="flex flex-col items-center gap-1.5 h-full justify-end group">
                        <div class="w-full flex justify-center items-end gap-1 h-36">
                            <div
                                class="w-3 sm:w-4 rounded-t bg-[#1D6AE5] transition-all"
                                :style="{ height: `${Math.max(3, Math.round((m.revenue / maxBarValue) * 100))}%` }"
                                :title="`Doanh thu: ${formatVnd(m.revenue)}`"
                            />
                            <div
                                class="w-3 sm:w-4 rounded-t bg-emerald-600 transition-all"
                                :style="{ height: `${Math.max(2, Math.round((Math.max(0, m.profit) / maxBarValue) * 100))}%` }"
                                :title="`Lãi gộp: ${formatVnd(m.profit)}`"
                            />
                        </div>
                        <span class="text-xs font-mono-num font-bold text-slate-600 pb-1.5">{{ m.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Transaction Audit Table -->
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Đối soát từng đơn hàng</h3>
                    <form @submit.prevent="submitSearch" class="relative w-full sm:w-72">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Tìm email, mã đơn, IP, mã coupon..."
                            class="w-full pl-8 pr-3 py-2 rounded-xl border border-slate-300 text-xs font-medium focus:outline-none focus:border-[#1D6AE5] shadow-2xs transition"
                        />
                    </form>
                </div>

                <!-- Mobile Card List -->
                <div class="lg:hidden divide-y divide-slate-200/80">
                    <div v-if="transactions.data.length === 0" class="py-10 px-4 text-center text-xs text-slate-500 font-medium">
                        Không có đơn hàng nào khớp bộ lọc.
                    </div>
                    <div v-for="t in transactions.data" :key="`m-${t.id}`" class="p-4 space-y-2.5 bg-white text-xs">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-mono font-bold text-[#0B1437] truncate">{{ t.code }}</div>
                                <div class="font-mono-num text-[11px] text-slate-500 mt-0.5">{{ t.created_at }}</div>
                            </div>
                            <button
                                type="button"
                                @click="confirmDeleteTransaction(t)"
                                class="p-1.5 rounded-lg border border-slate-200 text-slate-400 hover:text-rose-600 hover:border-rose-200 cursor-pointer transition shrink-0"
                                title="Xóa bản ghi"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <div>
                            <div class="font-bold text-[#0B1437] leading-relaxed break-words">{{ t.description }}</div>
                            <div v-if="t.coupon_code" class="mt-1">
                                <button
                                    type="button"
                                    @click="filterByCoupon(t.coupon_code)"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-[11px] font-mono font-bold text-emerald-800 cursor-pointer transition"
                                >
                                    <Ticket class="w-3 h-3 text-emerald-600 shrink-0" />
                                    <span>{{ t.coupon_code }}</span>
                                    <span v-if="t.discount_amount > 0">(-{{ formatVnd(t.discount_amount) }})</span>
                                </button>
                            </div>
                            <div v-else-if="t.discount_amount > 0" class="mt-0.5 text-[11px] font-mono font-semibold text-indigo-700">
                                Ưu đãi / Chiết khấu (-{{ formatVnd(t.discount_amount) }})
                            </div>
                            <div v-if="t.status === 'refunded'" class="mt-0.5 text-[11px] font-bold text-rose-600">Đơn lỗi — Đã hoàn tiền</div>
                        </div>

                        <div class="rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 space-y-2 text-[11px]">
                            <div class="flex items-center justify-between gap-2 min-w-0">
                                <span class="text-slate-500 font-semibold shrink-0">Khách</span>
                                <Link v-if="t.user_id" :href="`/admin/users/${t.user_id}`" class="font-bold text-[#0B1437] hover:text-[#1D6AE5] truncate">
                                    {{ t.user_name ? `${t.user_name} (${t.user_email})` : t.user_email }}
                                </Link>
                                <span v-else class="font-mono text-slate-400 italic truncate">{{ t.user_email }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-200/70 font-mono-num">
                                <div>
                                    <div class="text-[10px] font-sans font-bold uppercase text-slate-400">Thu khách</div>
                                    <div class="font-extrabold text-[#0B1437] mt-0.5">{{ formatVnd(t.amount) }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-[10px] font-sans font-bold uppercase text-slate-400">Vốn gốc</div>
                                    <div class="font-bold text-slate-700 mt-0.5">{{ formatVnd(t.provider_cost) }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] font-sans font-bold uppercase text-slate-400">Lãi đơn</div>
                                    <div class="font-black mt-0.5" :class="t.profit >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                                        {{ formatVnd(t.profit) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600">
                                <th class="py-3 px-4">Mã GD</th>
                                <th class="py-3 px-4">Khách hàng</th>
                                <th class="py-3 px-4">Nội dung</th>
                                <th class="py-3 px-4 text-right">Thu khách</th>
                                <th class="py-3 px-4 text-right">Vốn gốc</th>
                                <th class="py-3 px-4 text-right">Lãi đơn</th>
                                <th class="py-3 px-4">Thời gian</th>
                                <th class="py-3 px-4 text-right">Xóa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="t in transactions.data" :key="t.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 font-mono font-bold text-[#0B1437]">{{ t.code }}</td>
                                <td class="py-3 px-4">
                                    <Link
                                        v-if="t.user_id"
                                        :href="`/admin/users/${t.user_id}`"
                                        class="group block"
                                    >
                                        <div v-if="t.user_name" class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors">{{ t.user_name }}</div>
                                        <div class="font-mono text-[11px] font-medium text-slate-600 group-hover:text-[#1D6AE5] transition-colors">{{ t.user_email }}</div>
                                    </Link>
                                    <span v-else class="font-mono font-medium text-slate-400 italic">{{ t.user_email }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-[#0B1437]">{{ t.description }}</div>
                                    <div v-if="t.coupon_code" class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="filterByCoupon(t.coupon_code)"
                                            title="Lọc các đơn dùng mã giảm giá này"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-[11px] font-mono font-bold text-emerald-800 cursor-pointer transition"
                                        >
                                            <Ticket class="w-3 h-3 text-emerald-600 shrink-0" />
                                            <span>Mã giảm giá: {{ t.coupon_code }}</span>
                                            <span v-if="t.discount_amount > 0">(-{{ formatVnd(t.discount_amount) }})</span>
                                        </button>
                                    </div>
                                    <div v-else-if="t.discount_amount > 0" class="mt-0.5 text-[11px] font-mono font-semibold text-indigo-700">
                                        Ưu đãi / Chiết khấu (-{{ formatVnd(t.discount_amount) }})
                                    </div>
                                    <div v-if="t.status === 'refunded'" class="mt-0.5 text-xs font-bold text-rose-600">Đơn lỗi — Đã hoàn tiền</div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num font-extrabold text-[#0B1437]">{{ formatVnd(t.amount) }}</td>
                                <td class="py-3 px-4 text-right font-mono-num font-bold text-slate-700">{{ formatVnd(t.provider_cost) }}</td>
                                <td class="py-3 px-4 text-right font-mono-num font-black" :class="t.profit >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                                    {{ formatVnd(t.profit) }}
                                </td>
                                <td class="py-3 px-4 font-mono-num text-slate-600 font-medium">{{ t.created_at }}</td>
                                <td class="py-3 px-4 text-right">
                                    <button
                                        type="button"
                                        @click="confirmDeleteTransaction(t)"
                                        class="p-1 rounded text-slate-400 hover:text-rose-600 cursor-pointer transition"
                                        title="Xóa bản ghi"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 pb-4">
                    <PaginationBar :links="transactions.links" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
