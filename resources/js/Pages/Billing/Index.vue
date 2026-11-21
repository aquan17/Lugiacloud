<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { formatVnd } from '@/Composables/useFormat';
import { FileText } from 'lucide-vue-next';

defineProps<{
    timeline: Array<any>;
    summary: {
        total_topup: number;
        total_spend: number;
        current_balance: number;
    };
}>();
</script>

<template>
    <ClientLayout title="Sổ cái biến động số dư">
        <Head title="Biến động số dư" />

        <div class="space-y-5">
            <!-- Summary Strip -->
            <div class="surface-card grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 text-xs rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="p-4 sm:p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Số dư ví hiện tại</div>
                    <div class="text-2xl font-black font-mono-num text-[#0B1437] mt-2">{{ formatVnd(summary.current_balance) }}</div>
                </div>
                <div class="p-4 sm:p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Tổng tiền đã nạp</div>
                    <div class="text-2xl font-black font-mono-num text-emerald-700 mt-2">+{{ formatVnd(summary.total_topup) }}</div>
                </div>
                <div class="p-4 sm:p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Tổng thanh toán dịch vụ</div>
                    <div class="text-2xl font-black font-mono-num text-slate-800 mt-2">-{{ formatVnd(summary.total_spend) }}</div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Sổ cái chi tiết giao dịch</h3>
                    <Link href="/invoices" class="text-xs font-bold text-[#1D6AE5] hover:underline">Danh sách hóa đơn →</Link>
                </div>

                <div v-if="timeline.length === 0" class="p-10 text-center text-xs text-slate-600 font-medium">
                    Chưa có biến động số dư nào.
                </div>

                <template v-else>
                    <!-- Mobile Card View (0px overflow) -->
                    <div class="md:hidden divide-y divide-slate-200/80">
                        <div v-for="item in timeline" :key="'m_' + item.id" class="p-4 space-y-2 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-mono font-bold text-[#0B1437]">{{ item.code }}</div>
                                    <div class="font-semibold text-slate-800 mt-0.5 leading-snug">{{ item.description }}</div>
                                    <div v-if="item.coupon_code" class="text-[11px] text-emerald-700 font-mono font-semibold mt-0.5">
                                        Mã {{ item.coupon_code }} (-{{ formatVnd(item.discount_amount) }})
                                    </div>
                                </div>
                                <div
                                    class="font-mono-num text-sm font-black whitespace-nowrap shrink-0"
                                    :class="
                                        item.status === 'refunded'
                                            ? 'text-slate-500 font-medium line-through'
                                            : item.is_credit
                                              ? 'text-emerald-700'
                                              : 'text-[#0B1437]'
                                    "
                                >
                                    {{ item.amount >= 0 ? '+' : '' }}{{ formatVnd(item.amount) }}
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-1 text-[11px] text-slate-500">
                                <span class="font-mono-num">Số dư: <strong class="text-slate-700">{{ formatVnd(item.balance_after) }}</strong> · {{ item.at_formatted }}</span>
                                <Link :href="item.invoice_url" class="inline-flex items-center gap-1 text-[#1D6AE5] font-bold shrink-0">
                                    <FileText class="w-3.5 h-3.5" />
                                    <span>Biên nhận</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse text-[13px]">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-3 px-4 whitespace-nowrap">Mã giao dịch</th>
                                    <th class="py-3 px-4">Nội dung</th>
                                    <th class="py-3 px-4 text-right whitespace-nowrap">Biến động</th>
                                    <th class="py-3 px-4 text-right whitespace-nowrap">Số dư sau GD</th>
                                    <th class="py-3 px-4 whitespace-nowrap">Thời gian</th>
                                    <th class="py-3 px-4 text-right whitespace-nowrap">Chứng từ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80">
                                <tr v-for="item in timeline" :key="item.id" class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-4 font-mono font-bold text-[#0B1437] whitespace-nowrap">{{ item.code }}</td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-[#0B1437]">{{ item.description }}</div>
                                        <div v-if="item.coupon_code" class="text-xs text-emerald-800 font-mono font-semibold mt-0.5">
                                            Mã giảm giá: {{ item.coupon_code }} (-{{ formatVnd(item.discount_amount) }})
                                        </div>
                                    </td>
                                    <td
                                        class="py-3 px-4 text-right font-mono-num font-extrabold whitespace-nowrap"
                                        :class="
                                            item.status === 'refunded'
                                                ? 'text-slate-500 font-medium line-through'
                                                : item.is_credit
                                                  ? 'text-emerald-700'
                                                  : 'text-[#0B1437]'
                                        "
                                    >
                                        {{ item.amount >= 0 ? '+' : '' }}{{ formatVnd(item.amount) }}
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono-num font-bold text-slate-800 whitespace-nowrap">
                                        {{ formatVnd(item.balance_after) }}
                                    </td>
                                    <td class="py-3 px-4 font-mono-num text-slate-600 font-medium whitespace-nowrap">{{ item.at_formatted }}</td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <Link :href="item.invoice_url" class="inline-flex items-center gap-1 text-[#1D6AE5] hover:underline font-bold">
                                            <FileText class="w-3.5 h-3.5" />
                                            <span>Biên nhận</span>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </ClientLayout>
</template>
