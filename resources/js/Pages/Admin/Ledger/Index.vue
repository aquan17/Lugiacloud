<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { formatVnd } from '@/Composables/useFormat';
import { Search, Ticket, Server, Globe, Cpu, ArrowRight } from 'lucide-vue-next';

const props = defineProps<{
    entries: { data: Array<any>; links: Array<any> };
    filters: { tab: string; search: string };
    counts: {
        all: number;
        spending: number;
        refund: number;
        admin: number;
        bonus: number;
    };
    summary: {
        total_spending: number;
        total_refund: number;
        total_admin_add: number;
        total_admin_deduct: number;
        total_bonus: number;
    };
}>();

const search = ref(props.filters.search || '');

const tabs = [
    { key: 'all', label: 'Tất cả' },
    { key: 'spending', label: 'Mua · Gia hạn · Nâng cấp' },
    { key: 'refund', label: 'Hoàn tiền' },
    { key: 'admin', label: 'Admin cộng / trừ' },
    { key: 'bonus', label: 'Thưởng & Hoa hồng' },
] as const;

function switchTab(tab: string) {
    router.get('/admin/ledger', { tab, search: search.value || undefined }, { preserveState: true, replace: true });
}

function submitSearch() {
    router.get('/admin/ledger', { tab: props.filters.tab, search: search.value || undefined }, { preserveState: true, replace: true });
}

function filterByCoupon(code: string) {
    search.value = code;
    submitSearch();
}

function typeBadgeClass(type: string): string {
    const t = (type || '').toLowerCase();
    if (t === 'refund') return 'bg-rose-50 text-rose-700 border-rose-200';
    if (t === 'admin_add') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (t === 'admin_deduct') return 'bg-amber-50 text-amber-800 border-amber-200';
    if (t === 'topup_bonus' || t === 'affiliate_bonus') return 'bg-indigo-50 text-indigo-700 border-indigo-200';
    if (t === 'upgrade' || t === 'change_ip') return 'bg-sky-50 text-sky-700 border-sky-200';
    return 'bg-slate-100 text-slate-700 border-slate-200';
}
</script>

<template>
    <AdminLayout title="Sổ cái biến động ví toàn hệ thống">
        <Head title="Sổ cái ví toàn hệ thống" />

        <div class="space-y-6">
            <!-- 4 KPI Summary Cards -->
            <div class="surface-card grid grid-cols-2 lg:grid-cols-4 divide-y lg:divide-y-0 lg:divide-x divide-slate-200 rounded-2xl border border-slate-200/90 shadow-xs text-xs">
                <div class="p-5">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Tổng chi tiêu dịch vụ</div>
                    <div class="text-xl sm:text-2xl font-black font-mono-num text-[#0B1437] mt-1.5">{{ formatVnd(summary.total_spending) }}</div>
                    <div class="text-[11px] text-slate-500 font-medium mt-1">{{ counts.spending }} giao dịch mua/gia hạn/nâng cấp</div>
                </div>
                <div class="p-5">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Tổng đã hoàn tiền</div>
                    <div class="text-xl sm:text-2xl font-black font-mono-num text-rose-700 mt-1.5">{{ formatVnd(summary.total_refund) }}</div>
                    <div class="text-[11px] text-slate-500 font-medium mt-1">{{ counts.refund }} bản ghi hoàn tiền / đơn lỗi</div>
                </div>
                <div class="p-5">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Admin điều chỉnh ví</div>
                    <div class="text-base sm:text-lg font-black font-mono-num text-emerald-700 mt-1.5">+{{ formatVnd(summary.total_admin_add) }}</div>
                    <div class="text-xs font-mono-num font-bold text-amber-700 mt-0.5">-{{ formatVnd(summary.total_admin_deduct) }} ({{ counts.admin }} lệnh)</div>
                </div>
                <div class="p-5">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-indigo-700">Thưởng nạp &amp; Hoa hồng F1</div>
                    <div class="text-xl sm:text-2xl font-black font-mono-num text-indigo-700 mt-1.5">{{ formatVnd(summary.total_bonus) }}</div>
                    <div class="text-[11px] text-slate-500 font-medium mt-1">{{ counts.bonus }} giao dịch thưởng / hoa hồng</div>
                </div>
            </div>

            <!-- Tabs + Search Bar -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="inline-flex flex-nowrap overflow-x-auto max-w-full p-1 rounded-xl bg-white border border-slate-200 gap-0.5 [&::-webkit-scrollbar]:hidden [scrollbar-width:none]">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        @click="switchTab(t.key)"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap shrink-0 cursor-pointer transition"
                        :class="filters.tab === t.key ? 'bg-[#0B1437] text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    >
                        {{ t.label }} ({{ (counts as any)[t.key] ?? 0 }})
                    </button>
                </div>

                <form @submit.prevent="submitSearch" class="relative w-full lg:w-80">
                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm mã GD, email, IP VPS/Proxy, voucher..."
                        class="w-full pl-8 pr-3 py-2 rounded-xl bg-white border border-slate-300 text-xs font-medium focus:outline-none focus:border-[#1D6AE5] shadow-2xs transition"
                    />
                </form>
            </div>

            <!-- Full Wallet Ledger Table -->
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                                <th class="py-3 px-4 whitespace-nowrap">Mã GD</th>
                                <th class="py-3 px-4">Khách hàng</th>
                                <th class="py-3 px-4 whitespace-nowrap">Phân loại</th>
                                <th class="py-3 px-4">Nội dung &amp; Dịch vụ</th>
                                <th class="py-3 px-4 text-right whitespace-nowrap">Biến động</th>
                                <th class="py-3 px-4 text-right whitespace-nowrap">Số dư Trước → Sau</th>
                                <th class="py-3 px-4 whitespace-nowrap">Thời gian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-if="entries.data.length === 0">
                                <td colspan="7" class="py-10 text-center text-slate-500 font-medium">
                                    Không có bản ghi sổ cái nào khớp bộ lọc hiện tại.
                                </td>
                            </tr>
                            <tr v-for="e in entries.data" :key="e.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 font-mono font-bold text-[#0B1437] whitespace-nowrap">{{ e.code }}</td>
                                <td class="py-3 px-4">
                                    <Link v-if="e.user_id" :href="`/admin/users/${e.user_id}`" class="group block">
                                        <div v-if="e.user_name" class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors">{{ e.user_name }}</div>
                                        <div class="font-mono text-[11px] font-medium text-slate-600 group-hover:text-[#1D6AE5] transition-colors">{{ e.user_email }}</div>
                                    </Link>
                                    <span v-else class="font-mono text-slate-400 italic">{{ e.user_email }}</span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-md border text-[11px] font-bold"
                                        :class="typeBadgeClass(e.type)"
                                    >
                                        {{ e.type_label }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-[#0B1437]">{{ e.description }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <button
                                            v-if="e.coupon_code"
                                            type="button"
                                            @click="filterByCoupon(e.coupon_code)"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-[11px] font-mono font-bold text-emerald-800 cursor-pointer transition"
                                        >
                                            <Ticket class="w-3 h-3 text-emerald-600" />
                                            <span>{{ e.coupon_code }}</span>
                                            <span v-if="e.discount_amount > 0">(-{{ formatVnd(e.discount_amount) }})</span>
                                        </button>
                                        <span
                                            v-else-if="e.discount_amount > 0"
                                            class="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 border border-indigo-200 text-[11px] font-mono font-semibold text-indigo-700"
                                        >
                                            Ưu đãi -{{ formatVnd(e.discount_amount) }}
                                        </span>
                                        <Link
                                            v-if="e.vps_id"
                                            :href="`/admin/instances/${e.vps_id}`"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 hover:bg-blue-100 border border-blue-200 text-[11px] font-mono font-bold text-[#1D6AE5] transition"
                                        >
                                            <Server class="w-3 h-3" />
                                            <span>VPS #{{ e.vps_id }} ({{ e.vps_label }})</span>
                                        </Link>
                                        <Link
                                            v-if="e.proxy_id"
                                            :href="`/admin/proxies/${e.proxy_id}`"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-[11px] font-mono font-bold text-emerald-700 transition"
                                        >
                                            <Globe class="w-3 h-3" />
                                            <span>Proxy #{{ e.proxy_id }} ({{ e.proxy_label }})</span>
                                        </Link>
                                        <Link
                                            v-if="e.server_id"
                                            :href="`/admin/servers/${e.server_id}`"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-[11px] font-mono font-bold text-indigo-700 transition"
                                        >
                                            <Cpu class="w-3 h-3" />
                                            <span>Server #{{ e.server_id }} ({{ e.server_label }})</span>
                                        </Link>
                                        <span v-if="e.status === 'refunded'" class="text-[11px] font-bold text-rose-600">
                                            Đơn lỗi — Đã hoàn tiền
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="py-3 px-4 text-right font-mono-num font-black whitespace-nowrap"
                                    :class="
                                        e.status === 'refunded'
                                            ? 'text-slate-400 line-through font-semibold'
                                            : e.is_credit
                                              ? 'text-emerald-700'
                                              : 'text-[#0B1437]'
                                    "
                                >
                                    {{ e.amount >= 0 ? '+' : '' }}{{ formatVnd(e.amount) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num text-[11px] whitespace-nowrap">
                                    <span
                                        v-if="e.balance_before !== null && e.balance_after !== null"
                                        class="inline-flex items-center gap-1 text-slate-600 font-semibold"
                                    >
                                        <span>{{ formatVnd(e.balance_before) }}</span>
                                        <ArrowRight class="w-3 h-3 text-slate-400" />
                                        <strong class="text-[#0B1437]">{{ formatVnd(e.balance_after) }}</strong>
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="py-3 px-4 font-mono-num text-slate-600 font-medium whitespace-nowrap">{{ e.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 pb-4">
                    <PaginationBar :links="entries.links" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
