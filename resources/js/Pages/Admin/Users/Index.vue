<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { formatVnd } from '@/Composables/useFormat';
import { Search } from 'lucide-vue-next';

const props = defineProps<{
    users: { data: Array<any>; links: Array<any> };
    filters: { search: string };
}>();

const search = ref(props.filters.search || '');

function submitSearch() {
    router.get('/admin/users', { search: search.value || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <AdminLayout title="Quản lý Người dùng & Đại lý">
        <Head title="Người dùng & Phân quyền" />

        <div class="space-y-5">
            <div class="flex items-center justify-between gap-4">
                <form @submit.prevent="submitSearch" class="relative w-full max-w-sm">
                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm theo tên, email, số điện thoại..."
                        class="w-full pl-8 pr-3 py-2 rounded-xl bg-white border border-slate-300 text-xs font-medium focus:outline-none focus:border-[#1D6AE5] shadow-2xs transition"
                    />
                </form>
            </div>

            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600">
                                <th class="py-3 px-4">Thành viên</th>
                                <th class="py-3 px-4">Phân quyền</th>
                                <th class="py-3 px-4 text-right">Số dư ví</th>
                                <th class="py-3 px-4">Chiết khấu riêng</th>
                                <th class="py-3 px-4">Dịch vụ</th>
                                <th class="py-3 px-4">Ngày tham gia</th>
                                <th class="py-3 px-4 text-right">Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="u in users.data" :key="u.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4">
                                    <Link :href="`/admin/users/${u.id}`" class="group block">
                                        <div class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors">{{ u.name }}</div>
                                        <div class="text-xs font-mono font-medium text-slate-600 group-hover:text-[#1D6AE5] transition-colors mt-0.5">{{ u.email }}</div>
                                    </Link>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span
                                        class="px-2.5 py-0.5 rounded-md text-xs font-bold border"
                                        :class="
                                            u.role === 1
                                                ? 'bg-amber-50 text-amber-800 border-amber-200'
                                                : u.role === 2
                                                  ? 'bg-blue-50 text-[#1D6AE5] border-blue-200'
                                                  : 'bg-slate-100 text-slate-700 border-slate-200'
                                        "
                                    >
                                        {{ u.role_label }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono-num font-extrabold text-[#0B1437]">{{ formatVnd(u.balance) }}</td>
                                <td class="py-3.5 px-4 font-mono-num font-bold text-slate-700">
                                    VPS -{{ u.discount_percent }}% · Proxy -{{ u.proxy_discount_percent }}%
                                </td>
                                <td class="py-3.5 px-4 font-mono-num font-bold text-slate-800 whitespace-nowrap">
                                    <Link
                                        :href="`/admin/instances?search=${encodeURIComponent(u.email)}`"
                                        class="hover:text-[#1D6AE5] transition-colors"
                                        title="Xem danh sách VPS của khách này"
                                    >
                                        {{ u.vps_count }} VPS
                                    </Link>
                                    <span class="mx-1 text-slate-400">·</span>
                                    <Link
                                        :href="`/admin/proxies?search=${encodeURIComponent(u.email)}`"
                                        class="hover:text-[#1D6AE5] transition-colors"
                                        title="Xem danh sách Proxy của khách này"
                                    >
                                        {{ u.proxy_count }} IP
                                    </Link>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-600 font-medium">{{ u.created_at }}</td>
                                <td class="py-3.5 px-4 text-right">
                                    <Link :href="`/admin/users/${u.id}`" class="font-bold text-[#1D6AE5] hover:text-[#1557C0] transition-colors">Quản lý</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PaginationBar :links="users.links" />
        </div>
    </AdminLayout>
</template>
