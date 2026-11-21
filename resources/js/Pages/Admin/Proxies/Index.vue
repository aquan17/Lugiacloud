<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AdminUserPicker from '@/Components/Admin/AdminUserPicker.vue';
import { formatVnd } from '@/Composables/useFormat';
import { askConfirm } from '@/Composables/useConfirmAction';
import { Search, Trash2, UserCheck, Eye, X } from 'lucide-vue-next';

const props = defineProps<{
    proxies: { data: Array<any>; links: Array<any> };
    filters: { search: string; tab: string; category: string };
    counts: {
        all: number;
        active: number;
        expiring: number;
        provisioning: number;
        deleted: number;
    };
}>();

const search = ref(props.filters.search || '');
const selectedIds = ref<number[]>([]);

const statusTabs = computed(() => [
    { key: 'active', label: 'Hoạt động', count: props.counts.active },
    { key: 'expiring', label: 'Sắp hết hạn', count: props.counts.expiring },
    { key: 'provisioning', label: 'Đang khởi tạo', count: props.counts.provisioning },
    { key: 'deleted', label: 'Đã xóa', count: props.counts.deleted },
    { key: 'all', label: 'Tất cả', count: props.counts.all },
]);

function applyFilter(tab = props.filters.tab, category = props.filters.category) {
    router.get(
        '/admin/proxies',
        {
            tab,
            category: category !== 'all' ? category : undefined,
            search: search.value || undefined,
        },
        { preserveState: true, replace: true }
    );
}

function bulkDelete() {
    if (selectedIds.value.length === 0) return;
    askConfirm({
        title: 'Xóa hàng loạt Proxy',
        message: `Bạn có chắc muốn xóa ${selectedIds.value.length} Proxy đã chọn khỏi hệ thống?`,
        confirmText: `Xóa ${selectedIds.value.length} Proxy`,
        variant: 'danger',
        onConfirm: () => router.post('/admin/proxies/bulk-destroy', { ids: selectedIds.value }, { onSuccess: () => (selectedIds.value = []) }),
    });
}

// --- Modal Gán / Chuyển chủ sở hữu ---
const showReassignModal = ref(false);
const reassignSingle = ref<any>(null);

const reassignForm = useForm({
    ids: [] as number[],
    user_id: '' as number | '',
    disable_auto_renew: true,
});

function openSingleReassign(proxy: any) {
    reassignSingle.value = proxy;
    reassignForm.reset();
    reassignForm.user_id = '';
    reassignForm.disable_auto_renew = true;
    showReassignModal.value = true;
}

function openBulkReassign() {
    if (selectedIds.value.length === 0) return;
    reassignSingle.value = null;
    reassignForm.reset();
    reassignForm.ids = [...selectedIds.value];
    reassignForm.user_id = '';
    reassignForm.disable_auto_renew = true;
    showReassignModal.value = true;
}

function submitReassign() {
    if (!reassignForm.user_id) return;
    if (reassignSingle.value) {
        reassignForm.post(`/admin/proxies/${reassignSingle.value.id}/reassign`, {
            preserveScroll: true,
            onSuccess: () => {
                showReassignModal.value = false;
                reassignSingle.value = null;
            },
        });
    } else {
        reassignForm.ids = [...selectedIds.value];
        reassignForm.post('/admin/proxies/bulk-reassign', {
            preserveScroll: true,
            onSuccess: () => {
                showReassignModal.value = false;
                selectedIds.value = [];
            },
        });
    }
}
</script>

<template>
    <AdminLayout title="Quản lý Toàn bộ Proxy IPv4">
        <Head title="Toàn bộ Proxy" />

        <div class="space-y-5">
            <!-- Status Tabs + Category + Search -->
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="inline-flex p-1 rounded-xl bg-white border border-slate-200 shadow-2xs max-w-full overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]">
                        <button
                            v-for="t in statusTabs"
                            :key="t.key"
                            type="button"
                            @click="applyFilter(t.key, filters.category)"
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

                    <select
                        :value="filters.category"
                        @change="applyFilter(filters.tab, ($event.target as HTMLSelectElement).value)"
                        class="h-9 px-3 rounded-xl bg-white border border-slate-300 text-xs font-bold text-slate-700 focus:outline-none focus:border-[#1D6AE5]"
                    >
                        <option value="all">Mọi dòng Proxy</option>
                        <option value="datacenter">Datacenter</option>
                        <option value="residential">Dân cư VN</option>
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <form @submit.prevent="applyFilter()" class="relative w-full sm:w-64">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Tìm IP, Port, User, Email..."
                            class="w-full pl-8 pr-3 py-2 rounded-xl bg-white border border-slate-300 text-xs font-medium focus:outline-none focus:border-[#1D6AE5] shadow-2xs transition"
                        />
                    </form>

                    <template v-if="selectedIds.length > 0">
                        <button
                            type="button"
                            @click="openBulkReassign"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer transition"
                        >
                            <UserCheck class="w-3.5 h-3.5" />
                            <span>Gán {{ selectedIds.length }} IP</span>
                        </button>
                        <button
                            type="button"
                            @click="bulkDelete"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold cursor-pointer transition"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            <span>Xóa {{ selectedIds.length }}</span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600 whitespace-nowrap">
                                <th class="py-3 px-4 w-9"></th>
                                <th class="py-3 px-4">Endpoint / Gói</th>
                                <th class="py-3 px-4">Khách hàng</th>
                                <th class="py-3 px-4 text-right">Đã thu</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4">Hết hạn</th>
                                <th class="py-3 px-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-if="proxies.data.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500 font-medium">
                                    Không có Proxy nào phù hợp với bộ lọc hiện tại.
                                </td>
                            </tr>
                            <tr v-for="p in proxies.data" :key="p.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4">
                                    <input v-model="selectedIds" :value="p.id" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <Link :href="`/admin/proxies/${p.id}`" class="font-mono font-extrabold text-[#0B1437] hover:text-[#1D6AE5] transition-colors">
                                            {{ p.endpoint }}
                                        </Link>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[10.5px] font-bold">{{ p.protocol }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        {{ p.product_name }} · PRX#{{ p.provider_proxy_id || '—' }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <Link v-if="p.user_id" :href="`/admin/users/${p.user_id}`" class="group block">
                                        <div class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors">{{ p.user_name || '—' }}</div>
                                        <div class="font-mono text-[11px] text-slate-600 group-hover:text-[#1D6AE5] transition-colors">{{ p.user_email }}</div>
                                    </Link>
                                    <div v-else class="text-slate-400 italic">Đã xóa</div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num font-extrabold text-[#0B1437] whitespace-nowrap">{{ formatVnd(p.paid_amount) }}</td>
                                <td class="py-3 px-4 whitespace-nowrap"><StatusBadge :state="p.state || p.status" :label="p.status" /></td>
                                <td class="py-3 px-4 font-mono-num text-slate-600 font-medium whitespace-nowrap">{{ p.expires_at || '—' }}</td>
                                <td class="py-3 px-4 text-right whitespace-nowrap space-x-1.5">
                                    <Link
                                        :href="`/admin/proxies/${p.id}`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 font-bold transition align-middle"
                                    >
                                        <Eye class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                        <span>Chi tiết</span>
                                    </Link>
                                    <button
                                        type="button"
                                        @click="openSingleReassign(p)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-blue-200 bg-blue-50/70 hover:bg-blue-100 text-[#1D6AE5] font-bold cursor-pointer transition align-middle"
                                        title="Gán / Chuyển Proxy sang User khác"
                                    >
                                        <UserCheck class="w-3.5 h-3.5" />
                                        <span>Gán User</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            askConfirm({
                                                title: 'Xóa Proxy',
                                                message: `Xóa Proxy ${p.ip || '#' + p.id} khỏi hệ thống?`,
                                                onConfirm: () => router.delete(`/admin/proxies/${p.id}`, { preserveScroll: true }),
                                            })
                                        "
                                        class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 cursor-pointer transition align-middle"
                                        title="Xóa Proxy"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PaginationBar :links="proxies.links" />
        </div>

        <!-- Modal Gán / Chuyển chủ sở hữu Proxy -->
        <Teleport to="body">
            <div v-if="showReassignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="showReassignModal = false">
                <form @submit.prevent="submitReassign" class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4 text-xs">
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-200">
                        <div>
                            <h3 class="text-sm font-bold text-[#0B1437]">
                                {{ reassignSingle ? 'Gán Proxy IPv4 cho User khác' : `Gán ${selectedIds.length} Proxy đã chọn cho User` }}
                            </h3>
                            <p v-if="reassignSingle" class="text-[11px] text-slate-500 mt-0.5">
                                {{ reassignSingle.endpoint }} · Chủ cũ: <strong>{{ reassignSingle.user_email }}</strong>
                            </p>
                        </div>
                        <button type="button" @click="showReassignModal = false" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="space-y-2">
                        <label class="block font-semibold text-slate-700">Tìm khách hàng nhận Proxy</label>
                        <AdminUserPicker v-model="reassignForm.user_id" />
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer text-slate-700">
                        <input v-model="reassignForm.disable_auto_renew" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                        <span>Tắt tự động gia hạn sau khi chuyển chủ sở hữu</span>
                    </label>

                    <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                        <button type="button" @click="showReassignModal = false" class="px-4 py-2 rounded-lg border border-slate-300 font-semibold cursor-pointer">Hủy</button>
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
