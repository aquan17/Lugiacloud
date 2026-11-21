<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { formatVnd } from '@/Composables/useFormat';
import { ArrowRight } from 'lucide-vue-next';

defineProps<{
    invoices: Array<any>;
    stats: {
        total_count: number;
        total_service_amount: number;
        total_topup_amount: number;
    };
}>();
</script>

<template>
    <ClientLayout title="Hóa đơn & Chứng từ">
        <Head title="Quản lý Hóa đơn" />

        <div class="space-y-5">
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                        Toàn bộ chứng từ thanh toán ({{ stats.total_count }})
                    </h3>
                </div>

                <div v-if="invoices.length === 0" class="p-10 text-center text-xs text-slate-600 font-medium">
                    Chưa có hóa đơn nào được phát hành.
                </div>

                <template v-else>
                    <!-- Mobile Card View (0px overflow) -->
                    <div class="md:hidden divide-y divide-slate-200/80">
                        <div v-for="inv in invoices" :key="'m_' + inv.id" class="p-4 space-y-2 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono font-bold text-[#0B1437]">{{ inv.code }}</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-bold whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" />
                                    <span>{{ inv.status }}</span>
                                </span>
                            </div>
                            <div class="font-bold text-slate-900 leading-snug">{{ inv.title }}</div>
                            <div v-if="inv.description" class="text-[11px] text-slate-600 line-clamp-2">{{ inv.description }}</div>
                            <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-slate-100">
                                <div>
                                    <div class="font-mono-num text-sm font-black text-[#0B1437]">{{ formatVnd(inv.amount) }}</div>
                                    <div class="text-[11px] font-mono-num text-slate-500">{{ inv.created_at }}</div>
                                </div>
                                <Link :href="inv.detail_url" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-blue-50 text-[#1D6AE5] font-bold">
                                    <span>Chi tiết</span>
                                    <ArrowRight class="w-3.5 h-3.5" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse text-[13px]">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-3.5 px-4 whitespace-nowrap">Số hóa đơn</th>
                                    <th class="py-3.5 px-4 min-w-[200px]">Hạng mục</th>
                                    <th class="py-3.5 px-4 text-right whitespace-nowrap">Tổng tiền</th>
                                    <th class="py-3.5 px-4 text-center whitespace-nowrap">Trạng thái</th>
                                    <th class="py-3.5 px-4 whitespace-nowrap">Ngày lập</th>
                                    <th class="py-3.5 px-4 text-right whitespace-nowrap">Xem / In</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80">
                                <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 font-mono font-bold text-[#0B1437] whitespace-nowrap">{{ inv.code }}</td>
                                    <td class="py-3.5 px-4 min-w-[200px]">
                                        <div class="font-bold text-[#0B1437]">{{ inv.title }}</div>
                                        <div class="text-xs text-slate-600 font-medium truncate max-w-md mt-0.5">{{ inv.description }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono-num font-extrabold text-[#0B1437] whitespace-nowrap">{{ formatVnd(inv.amount) }}</td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" />
                                            <span>{{ inv.status }}</span>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono-num text-slate-600 font-medium whitespace-nowrap">{{ inv.created_at }}</td>
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <Link :href="inv.detail_url" class="inline-flex items-center gap-1 font-bold text-[#1D6AE5] hover:underline">
                                            <span>Chi tiết</span>
                                            <ArrowRight class="w-3.5 h-3.5" />
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
