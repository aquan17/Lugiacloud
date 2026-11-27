<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { formatVnd } from '@/Composables/useFormat';
import { ArrowLeft, Printer } from 'lucide-vue-next';

defineProps<{
    invoice: {
        type: string;
        number: string;
        created_at: string;
        payment_method: string;
        status: string;
        title: string;
        coupon_code?: string | null;
        subtotal: number;
        discount_amount: number;
        total_amount: number;
        items: Array<any>;
    };
    customer: {
        name: string;
        email: string;
        phone?: string | null;
    };
}>();

function printPage() {
    window.print();
}
</script>

<template>
    <ClientLayout :title="`Hóa đơn ${invoice.number}`">
        <Head :title="`Hóa đơn ${invoice.number}`" />

        <div class="max-w-3xl mx-auto space-y-4">
            <div class="flex items-center justify-between no-print">
                <Link href="/invoices" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-[#0B1437] transition">
                    <ArrowLeft class="w-4 h-4" />
                    <span>Quay lại danh sách hóa đơn</span>
                </Link>
                <button
                    type="button"
                    @click="printPage"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold transition shadow-xs cursor-pointer active:scale-95"
                >
                    <Printer class="w-4 h-4" />
                    <span>In / Xuất PDF</span>
                </button>
            </div>

            <div class="surface-card p-5 sm:p-10 space-y-6 sm:space-y-8 bg-white rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-6 border-b border-slate-200">
                    <div>
                        <img src="/logo.webp" alt="LugiaCloud" width="128" height="32" decoding="async" class="h-8 w-auto object-contain mb-2" />
                        <p class="text-xs text-slate-600 font-medium">Hạ tầng Máy chủ ảo Cloud VPS &amp; Proxy IPv4 Việt Nam</p>
                    </div>
                    <div class="sm:text-right">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700">Chứng từ điện tử</div>
                        <div class="text-xl font-extrabold font-mono text-[#0B1437] mt-0.5">{{ invoice.number }}</div>
                        <div class="text-xs font-mono-num text-slate-600 font-medium mt-1">Ngày lập: <strong class="text-slate-800">{{ invoice.created_at }}</strong></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Khách hàng</div>
                        <div class="font-extrabold text-[#0B1437] text-sm">{{ customer.name }}</div>
                        <div class="text-slate-700 font-medium mt-0.5">{{ customer.email }}</div>
                        <div v-if="customer.phone" class="font-mono-num text-slate-700 font-medium mt-0.5">{{ customer.phone }}</div>
                    </div>
                    <div class="sm:text-right">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Phương thức &amp; Trạng thái</div>
                        <div class="font-bold text-slate-900">{{ invoice.payment_method }}</div>
                        <div class="inline-flex items-center gap-1.5 mt-2 px-3 py-1 rounded-md bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-extrabold whitespace-nowrap">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                            <span>{{ invoice.status }}</span>
                        </div>
                    </div>
                </div>

                <!-- Mobile Item Cards (screen only; print always uses table below) -->
                <div class="sm:hidden print:hidden divide-y divide-slate-200 border-y border-slate-200 text-xs">
                    <div v-for="(item, idx) in invoice.items" :key="`m-${idx}`" class="py-3.5 space-y-2">
                        <div class="font-bold text-[#0B1437] text-sm">{{ item.name }}</div>
                        <div v-if="item.description" class="text-slate-600 font-medium leading-relaxed break-words">{{ item.description }}</div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-slate-500 font-medium">{{ item.cycle }}</span>
                            <span class="font-mono-num font-extrabold text-[#0B1437] text-sm">{{ formatVnd(item.price) }}</span>
                        </div>
                    </div>
                </div>

                <table class="hidden sm:table print:table w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-y border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-600">
                            <th class="py-3 px-3">Chi tiết dịch vụ</th>
                            <th class="py-3 px-3">Chu kỳ</th>
                            <th class="py-3 px-3 text-right">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="(item, idx) in invoice.items" :key="idx">
                            <td class="py-4 px-3">
                                <div class="font-bold text-[#0B1437] text-sm">{{ item.name }}</div>
                                <div class="text-slate-600 font-medium mt-0.5">{{ item.description }}</div>
                            </td>
                            <td class="py-4 px-3 text-slate-700 font-medium whitespace-nowrap">{{ item.cycle }}</td>
                            <td class="py-4 px-3 text-right font-mono-num font-extrabold text-[#0B1437] whitespace-nowrap">
                                {{ formatVnd(item.price) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex justify-end">
                    <div class="w-full sm:w-72 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600 font-medium">
                            <span>Tạm tính</span>
                            <span class="font-mono-num font-bold text-slate-800">{{ formatVnd(invoice.subtotal) }}</span>
                        </div>
                        <div v-if="invoice.discount_amount > 0" class="flex justify-between text-emerald-700 font-medium">
                            <span>Ưu đãi {{ invoice.coupon_code ? `(${invoice.coupon_code})` : '' }}</span>
                            <span class="font-mono-num font-bold text-emerald-800">-{{ formatVnd(invoice.discount_amount) }}</span>
                        </div>
                        <div class="pt-2.5 border-t border-slate-200 flex justify-between items-baseline">
                            <span class="font-bold text-slate-900 text-sm">Tổng thanh toán</span>
                            <span class="font-mono-num text-xl font-black text-[#0B1437]">{{ formatVnd(invoice.total_amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
