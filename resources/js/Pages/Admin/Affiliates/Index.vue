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
                <div class="overflow-x-auto">
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
