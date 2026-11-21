<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { copyToClipboard, formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { Copy, Users } from 'lucide-vue-next';

const props = defineProps<{
    referralUrl: string;
    refCode: string;
    commissionRatePercent: number;
    totalCommission: number;
    referralCount: number;
    logs: { data: Array<any>; links: Array<any> };
    referrals: Array<any>;
}>();

const toast = useToast();

async function copyLink() {
    if (await copyToClipboard(props.referralUrl)) toast.success('Đã sao chép liên kết giới thiệu');
}
</script>

<template>
    <ClientLayout title="Tiếp thị liên kết (Affiliate)">
        <Head title="Tiếp thị liên kết" />

        <div class="space-y-6">
            <!-- Referral Link Card -->
            <div class="surface-card p-6 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="lg:col-span-7 space-y-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-[#1D6AE5]">Chương trình Đối tác F1</span>
                    <h2 class="text-lg font-black text-[#0B1437]">
                        Nhận hoa hồng tự động {{ commissionRatePercent }}% trọn đời
                    </h2>
                    <p class="text-xs text-slate-600 font-medium leading-relaxed">
                        Mỗi khi khách hàng đăng ký qua liên kết của bạn phát sinh giao dịch mua mới, gia hạn hoặc nâng cấp VPS / Proxy, hệ thống tự động cộng ngay <strong>{{ commissionRatePercent }}%</strong> vào số dư tài khoản. Hạn mức rút tiền mặt tối thiểu là <strong>20.000đ</strong> (nhắn trực tiếp <a href="https://zalo.me/0862579104" target="_blank" rel="noopener noreferrer" class="text-[#1D6AE5] font-bold underline">Zalo Admin 0862.579.104</a> để đối soát nhận tiền).
                    </p>
                </div>

                <div class="lg:col-span-5 space-y-2">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-700">Liên kết giới thiệu độc quyền của bạn</div>
                    <div class="flex gap-2">
                        <input
                            :value="referralUrl"
                            readonly
                            class="flex-1 px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-300 text-xs font-mono font-bold text-slate-800 focus:outline-none"
                        />
                        <button
                            type="button"
                            @click="copyLink"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold shrink-0 cursor-pointer transition shadow-xs active:scale-95"
                        >
                            <Copy class="w-3.5 h-3.5" />
                            <span>Sao chép</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="surface-card grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 text-xs rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Mã đối tác</div>
                    <div class="text-xl font-black font-mono text-[#0B1437] mt-2">{{ refCode }}</div>
                </div>
                <div class="p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Thành viên đã giới thiệu</div>
                    <div class="text-xl font-black font-mono-num text-[#0B1437] mt-2">{{ referralCount }} tài khoản</div>
                </div>
                <div class="p-5">
                    <div class="text-xs text-slate-600 font-bold uppercase tracking-wider">Tổng hoa hồng đã nhận</div>
                    <div class="text-xl font-black font-mono-num text-emerald-700 mt-2">+{{ formatVnd(totalCommission) }}</div>
                </div>
            </div>

            <!-- Commission Logs -->
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex items-center gap-2">
                    <Users class="w-4 h-4 text-[#1D6AE5]" />
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Lịch sử cộng hoa hồng</h3>
                </div>

                <div v-if="logs.data.length === 0" class="p-10 text-center text-xs text-slate-600 font-medium">
                    Chưa có giao dịch hoa hồng nào.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600">
                                <th class="py-3 px-4">Tài khoản F1</th>
                                <th class="py-3 px-4">Loại giao dịch</th>
                                <th class="py-3 px-4 text-right">Giá trị đơn</th>
                                <th class="py-3 px-4 text-right">Hoa hồng nhận</th>
                                <th class="py-3 px-4">Thời gian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="l in logs.data" :key="l.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ l.buyer_email }}</td>
                                <td class="py-3 px-4 text-slate-700 font-medium">{{ l.transaction_type }}</td>
                                <td class="py-3 px-4 text-right font-mono-num font-bold text-slate-800">{{ formatVnd(l.amount) }}</td>
                                <td class="py-3 px-4 text-right font-mono-num font-black text-emerald-700">+{{ formatVnd(l.commission) }}</td>
                                <td class="py-3 px-4 font-mono-num text-slate-600 font-medium">{{ l.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 pb-4">
                    <PaginationBar :links="logs.links" />
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
