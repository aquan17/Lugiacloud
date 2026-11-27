<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import AdminUserPicker from '@/Components/Admin/AdminUserPicker.vue';
import { formatVnd } from '@/Composables/useFormat';
import { Search, Cpu, UserCheck, Eye, X, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    servers: { data: Array<any>; links: Array<any> };
    filters: { tab: string; search: string };
    counts: { all: number; active: number; waiting: number; expired: number; deleted: number };
}>();

const search = ref(props.filters.search || '');
const tabs = [
    { key: 'all', label: 'Tất cả' },
    { key: 'waiting', label: 'Chờ bàn giao' },
    { key: 'active', label: 'Hoạt động' },
    { key: 'expired', label: 'Hết hạn' },
    { key: 'deleted', label: 'Đã hủy' },
] as const;

function switchTab(tab: string) {
    router.get('/admin/servers', { tab, search: search.value || undefined }, { preserveState: true, replace: true });
}

function submitSearch() {
    router.get('/admin/servers', { tab: props.filters.tab, search: search.value || undefined }, { preserveState: true, replace: true });
}

const reassignTarget = ref<any | null>(null);
const reassignForm = useForm({
    user_id: '' as number | '',
    disable_auto_renew: true,
});

function openReassign(s: any) {
    reassignTarget.value = s;
    reassignForm.user_id = '';
    reassignForm.disable_auto_renew = true;
}

function submitReassign() {
    if (!reassignTarget.value || !reassignForm.user_id) return;
    reassignForm.post(`/admin/servers/${reassignTarget.value.id}/reassign`, {
        preserveScroll: true,
        onSuccess: () => (reassignTarget.value = null),
    });
}
</script>

<template>
    <AdminLayout title="Quản lý Máy chủ vật lý (Dedicated Server)">
        <Head title="Quản lý Máy chủ vật lý" />

        <div class="space-y-5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="inline-flex flex-nowrap overflow-x-auto max-w-full p-1 rounded-xl bg-white border border-slate-200 gap-0.5 [&::-webkit-scrollbar]:hidden [scrollbar-width:none]">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        @click="switchTab(t.key)"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap shrink-0 cursor-pointer transition"
                        :class="filters.tab === t.key ? 'bg-[#0B1437] text-white' : 'text-slate-600 hover:bg-slate-50'"
                    >
                        {{ t.label }} ({{ (counts as any)[t.key] ?? 0 }})
                    </button>
                </div>

                <form @submit.prevent="submitSearch" class="relative w-full lg:w-72">
                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm IP, tên máy, email khách, ID..."
                        class="w-full pl-8 pr-3 py-2 rounded-xl bg-white border border-slate-300 text-xs font-medium focus:outline-none focus:border-[#1D6AE5]"
                    />
                </form>
            </div>

            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <!-- Mobile Card List -->
                <div class="lg:hidden divide-y divide-slate-200/80">
                    <div v-if="servers.data.length === 0" class="py-12 px-4 text-center text-xs text-slate-500 font-medium">
                        <Cpu class="w-6 h-6 text-slate-300 mx-auto mb-2" />
                        Chưa có đơn Máy chủ vật lý nào trong mục này.
                    </div>
                    <div v-for="s in servers.data" :key="`m-${s.id}`" class="p-4 space-y-3 bg-white">
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="min-w-0">
                                <Link :href="`/admin/servers/${s.id}`" class="block font-extrabold text-[13px] text-[#0B1437] hover:text-[#1D6AE5] truncate">
                                    {{ s.label }}
                                </Link>
                                <div class="text-[11px] font-mono text-slate-500 mt-0.5 truncate">
                                    #{{ s.id }} · SRV#{{ s.provider_server_id || '—' }} · {{ s.plan_name }}
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <StatusBadge :state="s.state" :label="s.status" />
                                <div v-if="!s.has_credentials && s.state !== 'deleted'" class="text-[10px] font-bold text-amber-700 mt-1">
                                    Chưa bàn giao IP/IPMI
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 text-[11.5px]">
                            <div class="col-span-2 min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Khách hàng</div>
                                <Link v-if="s.user_id" :href="`/admin/users/${s.user_id}`" class="group block mt-0.5 truncate">
                                    <span class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5]">{{ s.user_name }}</span>
                                    <span class="font-mono text-[10.5px] text-slate-500 ml-1.5">{{ s.user_email }}</span>
                                </Link>
                                <span v-else class="text-slate-400 italic mt-0.5 block">Đã xóa</span>
                            </div>
                            <div class="col-span-2 min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">IP & Cấu hình</div>
                                <div class="font-mono font-bold text-[#0B1437] mt-0.5">{{ s.public_ip || 'Chưa nhập IP bàn giao' }}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    {{ s.config.cpu_cores }}C/{{ s.config.cpu_threads }}T · {{ s.config.ram_gb }}GB · {{ s.config.storage }}
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Thu / Vốn</div>
                                <div class="font-mono-num font-extrabold text-[#0B1437] mt-0.5">{{ formatVnd(s.paid_amount) }}</div>
                                <div class="font-mono-num text-[10.5px] text-slate-500">Vốn: {{ formatVnd(s.provider_cost) }}</div>
                            </div>
                            <div class="min-w-0 text-right">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hết hạn</div>
                                <div class="font-mono-num font-bold text-slate-700 mt-0.5">{{ s.expires_at || 'Chờ bàn giao' }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <Link
                                :href="`/admin/servers/${s.id}`"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold transition"
                            >
                                <Eye class="w-3.5 h-3.5" />
                                <span>Quản trị</span>
                            </Link>
                            <button
                                type="button"
                                @click="openReassign(s)"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold cursor-pointer transition"
                            >
                                <UserCheck class="w-3.5 h-3.5" />
                                <span>Gán User</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                                <th class="py-3 px-4">Máy chủ</th>
                                <th class="py-3 px-4">Khách hàng</th>
                                <th class="py-3 px-4">Cấu hình / IP</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4 text-right">Thu / Vốn</th>
                                <th class="py-3 px-4">Hết hạn</th>
                                <th class="py-3 px-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-if="servers.data.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500 font-medium">
                                    <Cpu class="w-6 h-6 text-slate-300 mx-auto mb-2" />
                                    Chưa có đơn Máy chủ vật lý nào trong mục này.
                                </td>
                            </tr>
                            <tr v-for="s in servers.data" :key="s.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4">
                                    <Link :href="`/admin/servers/${s.id}`" class="font-bold text-[#0B1437] hover:text-[#1D6AE5]">
                                        {{ s.label }}
                                    </Link>
                                    <div class="text-[11px] font-mono text-slate-500 mt-0.5">
                                        #{{ s.id }} · SRV#{{ s.provider_server_id || '—' }} · {{ s.plan_name }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <Link v-if="s.user_id" :href="`/admin/users/${s.user_id}`" class="group block">
                                        <div class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5]">{{ s.user_name }}</div>
                                        <div class="font-mono text-[11px] text-slate-500 group-hover:text-[#1D6AE5]">{{ s.user_email }}</div>
                                    </Link>
                                    <span v-else class="text-slate-400 italic">Đã xóa</span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-mono font-bold text-[#0B1437]">{{ s.public_ip || 'Chưa nhập IP bàn giao' }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        {{ s.config.cpu_cores }}C/{{ s.config.cpu_threads }}T · {{ s.config.ram_gb }}GB · {{ s.config.storage }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <StatusBadge :state="s.state" :label="s.status" />
                                    <div v-if="!s.has_credentials && s.state !== 'deleted'" class="text-[10.5px] font-bold text-amber-700 mt-0.5">
                                        Chưa bàn giao IP/IPMI
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num">
                                    <div class="font-extrabold text-[#0B1437]">{{ formatVnd(s.paid_amount) }}</div>
                                    <div class="text-[11px] text-slate-500">Vốn: {{ formatVnd(s.provider_cost) }}</div>
                                </td>
                                <td class="py-3 px-4 font-mono-num text-slate-600">{{ s.expires_at || 'Chờ bàn giao' }}</td>
                                <td class="py-3 px-4 text-right whitespace-nowrap space-x-1.5">
                                    <Link
                                        :href="`/admin/servers/${s.id}`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-[#0B1437] hover:bg-[#162354] text-white font-bold transition"
                                    >
                                        <Eye class="w-3.5 h-3.5" />
                                        <span>Quản trị</span>
                                    </Link>
                                    <button
                                        type="button"
                                        @click="openReassign(s)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold cursor-pointer"
                                        title="Chuyển chủ sở hữu"
                                    >
                                        <UserCheck class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 pb-4">
                    <PaginationBar :links="servers.links" />
                </div>
            </div>
        </div>

        <!-- Reassign Modal -->
        <div v-if="reassignTarget" class="fixed inset-0 z-50 bg-slate-950/50 flex items-center justify-center p-4" @click.self="reassignTarget = null">
            <div class="w-full max-w-md rounded-2xl bg-white border border-slate-200 shadow-xl p-6 space-y-4 text-xs">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-extrabold text-[#0B1437]">Chuyển chủ sở hữu Máy chủ vật lý</h3>
                    <button type="button" @click="reassignTarget = null" class="text-slate-400 hover:text-slate-700 cursor-pointer"><X class="w-4 h-4" /></button>
                </div>
                <p class="text-slate-600">
                    Đang chuyển máy chủ <strong>{{ reassignTarget.label }}</strong> ({{ reassignTarget.plan_name }}).
                </p>
                <AdminUserPicker v-model="reassignForm.user_id" />
                <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                    <input v-model="reassignForm.disable_auto_renew" type="checkbox" class="rounded border-slate-300" />
                    <span>Tắt tự động gia hạn sau khi chuyển</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="reassignTarget = null" class="px-4 py-2 rounded-xl border border-slate-200 font-bold text-slate-600 cursor-pointer">Hủy</button>
                    <button
                        type="button"
                        :disabled="!reassignForm.user_id || reassignForm.processing"
                        @click="submitReassign"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer disabled:opacity-50"
                    >
                        <Loader2 v-if="reassignForm.processing" class="w-3.5 h-3.5 animate-spin" />
                        <span>Xác nhận chuyển</span>
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
