<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import AdminUserPicker from '@/Components/Admin/AdminUserPicker.vue';
import { formatVnd } from '@/Composables/useFormat';
import { Search, UserCheck, Eye, X } from 'lucide-vue-next';

const props = defineProps<{
    instances: { data: Array<any>; links: Array<any> };
    filters: { tab: string; search: string };
    counts: {
        all: number;
        running: number;
        provisioning: number;
        stopped: number;
        expired: number;
        deleted: number;
    };
}>();

const search = ref(props.filters.search || '');

const statusTabs = computed(() => [
    { key: 'running', label: 'Đang chạy', count: props.counts.running },
    { key: 'provisioning', label: 'Đang xử lý', count: props.counts.provisioning },
    { key: 'stopped', label: 'Đã tắt', count: props.counts.stopped },
    { key: 'expired', label: 'Hết hạn', count: props.counts.expired },
    { key: 'deleted', label: 'Đã xóa', count: props.counts.deleted },
    { key: 'all', label: 'Tất cả', count: props.counts.all },
]);

function filter(tab: string) {
    router.get('/admin/instances', { tab, search: search.value || undefined }, { preserveState: true, replace: true });
}

// --- Modal Chuyển chủ sở hữu VPS ---
const reassignTarget = ref<any>(null);
const reassignForm = useForm({
    user_id: '' as number | '',
    disable_auto_renew: true,
});

function openReassignModal(vps: any) {
    reassignTarget.value = vps;
    reassignForm.reset();
    reassignForm.user_id = '';
    reassignForm.disable_auto_renew = true;
}

function submitReassign() {
    if (!reassignTarget.value || !reassignForm.user_id) return;
    reassignForm.post(`/admin/instances/${reassignTarget.value.id}/reassign`, {
        preserveScroll: true,
        onSuccess: () => {
            reassignTarget.value = null;
        },
    });
}
</script>

<template>
    <AdminLayout title="Quản lý Toàn bộ Máy chủ Cloud VPS">
        <Head title="Toàn bộ VPS" />

        <div class="space-y-5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="inline-flex p-1 rounded-xl bg-white border border-slate-200 shadow-2xs max-w-full overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]">
                    <button
                        v-for="t in statusTabs"
                        :key="t.key"
                        type="button"
                        @click="filter(t.key)"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition whitespace-nowrap shrink-0 inline-flex items-center gap-1.5"
                        :class="filters.tab === t.key ? 'bg-[#0B1437] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <span>{{ t.label }}</span>
                        <span
                            class="px-1.5 py-0.2 rounded text-[10.5px] font-mono-num"
                            :class="filters.tab === t.key ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ t.count }}
                        </span>
                    </button>
                </div>

                <form @submit.prevent="filter(filters.tab)" class="relative w-full lg:w-72 shrink-0">
                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm IP, Hostname, PVS#, Email khách..."
                        class="w-full pl-8 pr-3 py-2 rounded-xl bg-white border border-slate-300 text-xs font-medium focus:outline-none focus:border-[#1D6AE5] shadow-2xs transition"
                    />
                </form>
            </div>

            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <!-- Mobile Card List -->
                <div class="lg:hidden divide-y divide-slate-200/80">
                    <div v-if="instances.data.length === 0" class="py-12 px-4 text-center text-xs text-slate-500 font-medium">
                        Không có máy chủ nào phù hợp với bộ lọc hiện tại.
                    </div>
                    <div v-for="v in instances.data" :key="`m-${v.id}`" class="p-4 space-y-3 bg-white">
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="min-w-0">
                                <Link :href="`/admin/instances/${v.id}`" class="block font-extrabold text-[13px] text-[#0B1437] hover:text-[#1D6AE5] truncate transition-colors">
                                    {{ v.label }}
                                </Link>
                                <div class="font-mono-num text-[11px] font-medium text-slate-500 mt-0.5 truncate">
                                    {{ v.public_ip || 'Chờ IP' }} · PVS#{{ v.provider_vps_id || '—' }}
                                </div>
                            </div>
                            <StatusBadge :state="v.state" :label="v.status" class="shrink-0" />
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 text-[11.5px]">
                            <div class="col-span-2 min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Khách hàng</div>
                                <Link v-if="v.user_id" :href="`/admin/users/${v.user_id}`" class="group block mt-0.5 truncate">
                                    <span class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5]">{{ v.user_name || '—' }}</span>
                                    <span class="font-mono text-[10.5px] text-slate-500 ml-1.5">{{ v.user_email }}</span>
                                </Link>
                                <div v-else class="text-slate-400 italic mt-0.5">Đã xóa</div>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cấu hình</div>
                                <div class="font-mono-num font-bold text-slate-800 mt-0.5 truncate">{{ v.specs }}</div>
                            </div>
                            <div class="min-w-0 text-right">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Đã thu / Vốn</div>
                                <div class="font-mono-num font-extrabold text-[#0B1437] mt-0.5">{{ formatVnd(v.paid_amount) }}</div>
                                <div class="font-mono-num text-[10.5px] text-slate-500">Vốn: {{ formatVnd(v.provider_cost) }}</div>
                            </div>
                            <div class="col-span-2 flex items-center justify-between pt-2 border-t border-slate-200/70 text-[11px]">
                                <span class="text-slate-500 font-semibold">Hết hạn</span>
                                <span class="font-mono-num font-bold text-slate-700">{{ v.expires_at || '—' }}</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <Link
                                :href="`/admin/instances/${v.id}`"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 text-xs font-bold transition"
                            >
                                <Eye class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                <span>Chi tiết</span>
                            </Link>
                            <button
                                type="button"
                                @click="openReassignModal(v)"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-blue-200 bg-blue-50/80 hover:bg-blue-100 text-[#1D6AE5] text-xs font-bold cursor-pointer transition"
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
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600 whitespace-nowrap">
                                <th class="py-3 px-4">Hostname / IP</th>
                                <th class="py-3 px-4">Khách hàng</th>
                                <th class="py-3 px-4">Cấu hình</th>
                                <th class="py-3 px-4 text-right">Đã thu / Vốn</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4">Hết hạn</th>
                                <th class="py-3 px-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-if="instances.data.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500 font-medium">
                                    Không có máy chủ nào phù hợp với bộ lọc hiện tại.
                                </td>
                            </tr>
                            <tr v-for="v in instances.data" :key="v.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <Link :href="`/admin/instances/${v.id}`" class="font-bold text-[#0B1437] hover:text-[#1D6AE5] transition-colors">
                                        {{ v.label }}
                                    </Link>
                                    <div class="font-mono-num text-[11.5px] font-medium text-slate-600 mt-0.5">
                                        {{ v.public_ip || 'Chờ IP' }} · PVS#{{ v.provider_vps_id || '—' }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <Link v-if="v.user_id" :href="`/admin/users/${v.user_id}`" class="group block">
                                        <div class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors">{{ v.user_name || '—' }}</div>
                                        <div class="font-mono text-[11px] text-slate-600 group-hover:text-[#1D6AE5] transition-colors">{{ v.user_email }}</div>
                                    </Link>
                                    <div v-else class="text-slate-400 italic">Đã xóa</div>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num font-bold text-slate-800 whitespace-nowrap">{{ v.specs }}</td>
                                <td class="py-3.5 px-4 text-right font-mono-num whitespace-nowrap">
                                    <div class="font-extrabold text-[#0B1437]">{{ formatVnd(v.paid_amount) }}</div>
                                    <div class="text-[11px] font-semibold text-slate-500 mt-0.5">Vốn: {{ formatVnd(v.provider_cost) }}</div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap"><StatusBadge :state="v.state" :label="v.status" /></td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-600 font-medium whitespace-nowrap">{{ v.expires_at || '—' }}</td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1.5">
                                    <Link
                                        :href="`/admin/instances/${v.id}`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 font-bold transition align-middle"
                                    >
                                        <Eye class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                        <span>Chi tiết</span>
                                    </Link>
                                    <button
                                        type="button"
                                        @click="openReassignModal(v)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-blue-200 bg-blue-50/70 hover:bg-blue-100 text-[#1D6AE5] font-bold cursor-pointer transition align-middle"
                                        title="Gán / Chuyển chủ sở hữu sang User khác"
                                    >
                                        <UserCheck class="w-3.5 h-3.5" />
                                        <span>Gán User</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PaginationBar :links="instances.links" />
        </div>

        <!-- Modal Chuyển chủ sở hữu VPS -->
        <Teleport to="body">
            <div v-if="reassignTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="reassignTarget = null">
                <form @submit.prevent="submitReassign" class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-200">
                        <div>
                            <h3 class="text-sm font-bold text-[#0B1437]">Gán Cloud VPS cho User khác</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                {{ reassignTarget.label }} ({{ reassignTarget.public_ip || 'Chờ IP' }}) · Chủ cũ: <strong>{{ reassignTarget.user_email }}</strong>
                            </p>
                        </div>
                        <button type="button" @click="reassignTarget = null" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="space-y-2">
                        <label class="block font-semibold text-slate-700">Tìm khách hàng nhận máy chủ</label>
                        <AdminUserPicker v-model="reassignForm.user_id" />
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer text-slate-700">
                        <input v-model="reassignForm.disable_auto_renew" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                        <span>Tắt tự động gia hạn sau khi chuyển chủ sở hữu</span>
                    </label>

                    <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                        <button type="button" @click="reassignTarget = null" class="px-4 py-2 rounded-lg border border-slate-300 font-semibold cursor-pointer">Hủy</button>
                        <button
                            type="submit"
                            :disabled="reassignForm.processing || !reassignForm.user_id"
                            class="px-4 py-2 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer disabled:opacity-50"
                        >
                            {{ reassignForm.processing ? 'Đang chuyển...' : 'Xác nhận chuyển' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AdminLayout>
</template>
