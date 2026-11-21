<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { formatVnd } from '@/Composables/useFormat';
import { QrCode, Gift, ArrowRight } from 'lucide-vue-next';

const props = defineProps<{
    requests: { data: Array<any>; links: Array<any> };
    bonusCampaign?: { bonus_percent: number; campaign?: any } | null;
    bankInfo: { bank_id: string; account_no: string; account_name: string; provider: string };
}>();

const presets = [50000, 100000, 200000, 500000, 1000000, 2000000];

const form = useForm({
    amount: 100000,
});

const estimatedBonus = computed(() => {
    if (!props.bonusCampaign?.bonus_percent) return 0;
    return Math.floor((Number(form.amount) || 0) * (props.bonusCampaign.bonus_percent / 100));
});
</script>

<template>
    <ClientLayout title="Nạp số dư tài khoản">
        <Head title="Nạp số dư (VietQR / PayOS)" />

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Left Create Topup -->
            <div class="lg:col-span-5 surface-card p-6 space-y-5 rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-center text-[#1D6AE5] shrink-0">
                        <QrCode class="w-5 h-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-[#0B1437]">Tạo mã nạp tự động 24/7</h2>
                        <p class="text-xs text-slate-600 font-medium mt-0.5">Quét mã VietQR trên mọi ứng dụng Ngân hàng / Ví điện tử</p>
                    </div>
                </div>

                <div
                    v-if="bonusCampaign && bonusCampaign.bonus_percent > 0"
                    class="p-4 rounded-xl bg-emerald-50/90 border border-emerald-200 flex items-start gap-2.5 text-xs text-emerald-950 font-medium leading-relaxed"
                >
                    <Gift class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                    <div>
                        <strong class="font-bold text-emerald-900">Ưu đãi sự kiện đang mở:</strong> Tặng tự động <strong class="font-extrabold text-emerald-800">+{{ bonusCampaign.bonus_percent }}%</strong> giá trị nạp ngay khi hệ thống xác nhận giao dịch!
                    </div>
                </div>

                <form @submit.prevent="form.post('/topup')" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Chọn nhanh mệnh giá</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="val in presets"
                                :key="val"
                                type="button"
                                @click="form.amount = val"
                                class="py-2.5 px-2.5 rounded-xl border text-xs font-mono-num font-extrabold transition cursor-pointer"
                                :class="
                                    form.amount === val
                                        ? 'border-[#1D6AE5] bg-blue-50/60 text-[#1D6AE5] ring-1 ring-[#1D6AE5]'
                                        : 'border-slate-200 bg-white text-slate-800 hover:bg-slate-50'
                                "
                            >
                                {{ formatVnd(val) }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Hoặc nhập số tiền cần nạp (VNĐ)</label>
                        <input
                            v-model.number="form.amount"
                            type="number"
                            min="10000"
                            step="1000"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-base font-mono-num font-extrabold text-[#0B1437] focus:outline-none focus:border-[#1D6AE5] focus:ring-1 focus:ring-[#1D6AE5]"
                        />
                        <p v-if="form.errors.amount" class="mt-1 text-xs font-bold text-rose-600">{{ form.errors.amount }}</p>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-600 font-medium">Số tiền chuyển khoản</span>
                            <span class="font-mono-num font-bold text-slate-900">{{ formatVnd(form.amount) }}</span>
                        </div>
                        <div v-if="estimatedBonus > 0" class="flex justify-between text-emerald-700 font-medium">
                            <span>Thưởng sự kiện (+{{ bonusCampaign?.bonus_percent }}%)</span>
                            <span class="font-mono-num font-bold text-emerald-800">+{{ formatVnd(estimatedBonus) }}</span>
                        </div>
                        <div class="pt-2 border-t border-slate-200 flex justify-between items-baseline font-bold text-slate-900">
                            <span class="text-slate-800">Thực nhận vào ví</span>
                            <span class="font-mono-num text-base font-black text-[#1D6AE5]">{{ formatVnd((Number(form.amount) || 0) + estimatedBonus) }}</span>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold cursor-pointer transition shadow-xs active:scale-98"
                    >
                        <span>{{ form.processing ? 'Đang khởi tạo mã QR...' : 'Tạo mã VietQR thanh toán' }}</span>
                        <ArrowRight class="w-4 h-4" />
                    </button>
                </form>
            </div>

            <!-- Right Topup History -->
            <div class="lg:col-span-7 surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Lịch sử lệnh nạp gần đây</h3>
                </div>

                <div v-if="requests.data.length === 0" class="p-10 text-center text-xs text-slate-600 font-medium">
                    Chưa có lệnh nạp nào được khởi tạo.
                </div>

                <template v-else>
                    <!-- Mobile Card View (0px overflow) -->
                    <div class="md:hidden divide-y divide-slate-200/80">
                        <div v-for="r in requests.data" :key="'m_' + r.id" class="p-4 space-y-2.5 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono font-bold text-[#0B1437]">{{ r.code }}</span>
                                <StatusBadge :state="r.status" :label="r.status_label" />
                            </div>
                            <div class="flex items-baseline justify-between gap-2">
                                <div>
                                    <span class="font-mono-num text-sm font-black text-slate-900">{{ formatVnd(r.approved_amount || r.amount) }}</span>
                                    <span v-if="r.bonus_amount > 0" class="ml-1.5 font-mono-num text-xs font-bold text-emerald-700">
                                        +{{ formatVnd(r.bonus_amount) }}
                                    </span>
                                </div>
                                <Link :href="`/topup/${r.id}`" class="font-bold text-[#1D6AE5] hover:underline whitespace-nowrap">
                                    Xem QR →
                                </Link>
                            </div>
                            <div class="text-[11px] font-mono-num text-slate-500">{{ r.created_at }}</div>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-3 px-4 whitespace-nowrap">Mã nạp</th>
                                    <th class="py-3 px-4 whitespace-nowrap">Số tiền</th>
                                    <th class="py-3 px-4 whitespace-nowrap">Thưởng sự kiện</th>
                                    <th class="py-3 px-4 whitespace-nowrap">Trạng thái</th>
                                    <th class="py-3 px-4 whitespace-nowrap">Thời gian</th>
                                    <th class="py-3 px-4 text-right whitespace-nowrap">QR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80">
                                <tr v-for="r in requests.data" :key="r.id" class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-4 font-mono font-bold text-[#0B1437] whitespace-nowrap">{{ r.code }}</td>
                                    <td class="py-3 px-4 font-mono-num font-bold text-slate-900 whitespace-nowrap">{{ formatVnd(r.approved_amount || r.amount) }}</td>
                                    <td class="py-3 px-4 font-mono-num text-emerald-700 font-bold whitespace-nowrap">
                                        {{ r.bonus_amount > 0 ? '+' + formatVnd(r.bonus_amount) : '—' }}
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <StatusBadge :state="r.status" :label="r.status_label" />
                                    </td>
                                    <td class="py-3 px-4 font-mono-num text-slate-600 font-medium whitespace-nowrap">{{ r.created_at }}</td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <Link :href="`/topup/${r.id}`" class="font-bold text-[#1D6AE5] hover:underline">
                                            Xem
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <div class="px-5 pb-4">
                    <PaginationBar :links="requests.links" />
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
