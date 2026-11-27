<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { formatVnd } from '@/Composables/useFormat';

defineProps<{
    referrals: { data: Array<any>; links: Array<any> };
    totalPaidOut: number;
}>();
</script>

<template>
    <AdminLayout title="Đối soát Hoa hồng Affiliate F1">
        <Head title="Quản lý Affiliate" />

        <div class="space-y-5">
            <div class="surface-card p-5 flex items-center justify-between text-xs">
                <span class="text-slate-600 font-medium">Tổng hoa hồng giới thiệu đã chi trả toàn hệ thống</span>
                <span class="text-lg font-extrabold font-mono-num text-emerald-700">{{ formatVnd(totalPaidOut) }}</span>
            </div>

            <div class="surface-card overflow-hidden">
                <!-- Mobile Card List -->
                <div class="md:hidden divide-y divide-slate-200/80">
                    <div v-if="referrals.data.length === 0" class="py-10 px-4 text-center text-xs text-slate-500">
                        Chưa có dữ liệu giới thiệu nào.
                    </div>
                    <div v-for="r in referrals.data" :key="`m-${r.id}`" class="p-4 space-y-2.5 bg-white text-xs">
                        <div class="flex items-start justify-between gap-2">
                            <Link :href="`/admin/users/${r.id}`" class="group min-w-0 block">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Khách F1</div>
                                <div class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5] truncate mt-0.5">{{ r.buyer_name }}</div>
                                <div class="font-mono text-[11px] text-slate-500 truncate">{{ r.buyer_email }}</div>
                            </Link>
                            <div class="text-right shrink-0">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hoa hồng</div>
                                <div class="font-mono-num font-extrabold text-emerald-700 mt-0.5">{{ formatVnd(r.total_commission) }}</div>
                            </div>
                        </div>

                        <div class="rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 space-y-1.5 text-[11px]">
                            <div class="flex items-center justify-between gap-2 min-w-0">
                                <span class="text-slate-500 font-semibold shrink-0">Đối tác GT</span>
                                <Link v-if="r.referrer_id" :href="`/admin/users/${r.referrer_id}`" class="font-bold text-[#0B1437] hover:text-[#1D6AE5] truncate">
                                    {{ r.referrer_name || r.referrer_email }} <span class="font-mono text-slate-400">({{ r.referrer_code }})</span>
                                </Link>
                                <span v-else class="font-mono text-slate-500 truncate">
                                    {{ r.referrer_email || 'Đã xóa' }} <span v-if="r.referrer_code" class="text-slate-400">({{ r.referrer_code }})</span>
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-slate-200/70">
                                <span class="text-slate-500 font-semibold">Ngày đăng ký</span>
                                <span class="font-mono-num text-slate-600">{{ r.joined_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase text-slate-500">
                                <th class="py-3 px-4">Người được giới thiệu (F1)</th>
                                <th class="py-3 px-4">Người giới thiệu (Đối tác)</th>
                                <th class="py-3 px-4 text-right">Tổng hoa hồng phát sinh</th>
                                <th class="py-3 px-4">Ngày đăng ký</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="r in referrals.data" :key="r.id">
                                <td class="py-3.5 px-4">
                                    <Link :href="`/admin/users/${r.id}`" class="group block">
                                        <div class="font-bold text-slate-900 group-hover:text-[#1D6AE5] transition-colors">{{ r.buyer_name }}</div>
                                        <div class="font-mono text-[11px] text-slate-500 group-hover:text-[#1D6AE5] transition-colors">{{ r.buyer_email }}</div>
                                    </Link>
                                </td>
                                <td class="py-3.5 px-4">
                                    <Link
                                        v-if="r.referrer_id"
                                        :href="`/admin/users/${r.referrer_id}`"
                                        class="group block"
                                    >
                                        <div v-if="r.referrer_name" class="font-bold text-slate-900 group-hover:text-[#1D6AE5] transition-colors">{{ r.referrer_name }}</div>
                                        <div class="font-mono text-[11px] text-slate-600 group-hover:text-[#1D6AE5] transition-colors">
                                            {{ r.referrer_email }} <span class="text-slate-400">({{ r.referrer_code }})</span>
                                        </div>
                                    </Link>
                                    <span v-else class="font-mono text-slate-500">
                                        {{ r.referrer_email || 'Đã xóa' }} <span v-if="r.referrer_code" class="text-slate-400">({{ r.referrer_code }})</span>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono-num font-bold text-emerald-600">
                                    {{ formatVnd(r.total_commission) }}
                                </td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-500">{{ r.joined_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PaginationBar :links="referrals.links" />
        </div>
    </AdminLayout>
</template>
