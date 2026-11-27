<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { formatVnd } from '@/Composables/useFormat';
import { askConfirm } from '@/Composables/useConfirmAction';
import { Search, Check, X, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    topups: { data: Array<any>; links: Array<any> };
    filters: { search: string; status: string };
}>();

const search = ref(props.filters.search || '');
const busyId = ref<number | null>(null);

function filter(status: string) {
    router.get('/admin/topups', { status, search: search.value || undefined }, { preserveState: true, replace: true });
}

function approve(t: any) {
    if (busyId.value !== null) return;
    askConfirm({
        title: 'Duyệt cộng tiền ví',
        message: `Xác nhận duyệt cộng ${formatVnd(t.amount)} vào ví của ${t.user_email} (mã lệnh ${t.code})?`,
        confirmText: `Duyệt +${formatVnd(t.amount)}`,
        variant: 'primary',
        onConfirm: () => {
            busyId.value = t.id;
            router.post(
                `/admin/topups/${t.id}/approve`,
                { approved_amount: t.amount },
                { preserveScroll: true, onFinish: () => (busyId.value = null) }
            );
        },
    });
}

function reject(t: any) {
    if (busyId.value !== null) return;
    askConfirm({
        title: 'Từ chối lệnh nạp tiền',
        message: `Xác nhận từ chối yêu cầu nạp ${t.code} (${formatVnd(t.amount)}) của ${t.user_email}?`,
        confirmText: 'Từ chối lệnh nạp',
        variant: 'danger',
        onConfirm: () => {
            busyId.value = t.id;
            router.post(
                `/admin/topups/${t.id}/reject`,
                { admin_note: 'Không nhận được giao dịch' },
                { preserveScroll: true, onFinish: () => (busyId.value = null) }
            );
        },
    });
}
</script>

<template>
    <AdminLayout title="Đối soát & Duyệt lệnh nạp tiền">
        <Head title="Quản lý Nạp tiền" />

        <div class="space-y-5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="inline-flex p-1 rounded-xl bg-white border border-slate-200 shadow-2xs max-w-full overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]">
                    <button
                        v-for="s in [
                            { key: 'all', label: 'Tất cả' },
                            { key: 'pending', label: 'Chờ duyệt' },
                            { key: 'paid', label: 'PayOS tự động' },
                            { key: 'approved', label: 'Admin duyệt' },
                        ]"
                        :key="s.key"
                        type="button"
                        @click="filter(s.key)"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition whitespace-nowrap shrink-0"
                        :class="filters.status === s.key ? 'bg-[#0B1437] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        {{ s.label }}
                    </button>
                </div>

                <form @submit.prevent="filter(filters.status)" class="relative w-full lg:w-64 shrink-0">
                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm mã LUGIA, email..."
                        class="w-full pl-8 pr-3 py-2 rounded-xl bg-white border border-slate-300 text-xs font-medium focus:outline-none focus:border-[#1D6AE5] shadow-2xs transition"
                    />
                </form>
            </div>

            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <!-- Mobile Card List -->
                <div class="lg:hidden divide-y divide-slate-200/80">
                    <div v-if="topups.data.length === 0" class="py-12 px-4 text-center text-xs text-slate-500 font-medium">
                        Không có lệnh nạp tiền nào phù hợp.
                    </div>
                    <div v-for="t in topups.data" :key="`m-${t.id}`" class="p-4 space-y-3 bg-white text-xs">
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="min-w-0">
                                <div class="font-mono font-extrabold text-[13px] text-[#0B1437] truncate">{{ t.code }}</div>
                                <div class="font-mono-num text-[11px] text-slate-500 mt-0.5">{{ t.created_at }}</div>
                            </div>
                            <StatusBadge :state="t.status" :label="t.status_label" class="shrink-0" />
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 text-[11.5px]">
                            <div class="col-span-2 min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Khách hàng</div>
                                <Link v-if="t.user_id" :href="`/admin/users/${t.user_id}`" class="group block mt-0.5 truncate">
                                    <span class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5]">{{ t.user_name }}</span>
                                    <span class="font-mono text-[10.5px] text-slate-500 ml-1.5">{{ t.user_email }}</span>
                                </Link>
                                <div v-else class="mt-0.5 truncate">
                                    <span class="font-bold text-[#0B1437]">{{ t.user_name || 'Đã xóa' }}</span>
                                    <span class="font-mono text-[10.5px] text-slate-500 ml-1.5">{{ t.user_email }}</span>
                                </div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Số tiền nạp</div>
                                <div class="font-mono-num font-extrabold text-[#0B1437] mt-0.5">{{ formatVnd(t.approved_amount || t.amount) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Thưởng sự kiện</div>
                                <div class="font-mono-num font-bold mt-0.5" :class="t.bonus_amount > 0 ? 'text-emerald-700' : 'text-slate-400'">
                                    {{ t.bonus_amount > 0 ? '+' + formatVnd(t.bonus_amount) : '—' }}
                                </div>
                            </div>
                            <div v-if="t.status !== 'pending' && t.paid_at" class="col-span-2 flex items-center justify-between pt-2 border-t border-slate-200/70 text-[11px]">
                                <span class="text-slate-500 font-semibold">Hoàn tất lúc</span>
                                <span class="font-mono-num font-bold text-slate-700">{{ t.paid_at }}</span>
                            </div>
                        </div>

                        <div v-if="t.status === 'pending'" class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                :disabled="busyId !== null"
                                @click="approve(t)"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold cursor-pointer transition shadow-2xs disabled:opacity-50"
                            >
                                <Loader2 v-if="busyId === t.id" class="w-3.5 h-3.5 animate-spin" />
                                <Check v-else class="w-3.5 h-3.5" />
                                <span>Duyệt cộng tiền</span>
                            </button>
                            <button
                                type="button"
                                :disabled="busyId !== null"
                                @click="reject(t)"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-rose-300 hover:bg-rose-50 text-rose-700 font-bold cursor-pointer transition disabled:opacity-50"
                            >
                                <X class="w-3.5 h-3.5" />
                                <span>Từ chối</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600">
                                <th class="py-3 px-4">Mã nạp</th>
                                <th class="py-3 px-4">Khách hàng</th>
                                <th class="py-3 px-4 text-right">Số tiền</th>
                                <th class="py-3 px-4 text-right">Thưởng sự kiện</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4">Thời gian</th>
                                <th class="py-3 px-4 text-right">Xử lý</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="t in topups.data" :key="t.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-[#0B1437]">{{ t.code }}</td>
                                <td class="py-3.5 px-4">
                                    <Link
                                        v-if="t.user_id"
                                        :href="`/admin/users/${t.user_id}`"
                                        class="group block"
                                    >
                                        <div class="font-bold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors">{{ t.user_name }}</div>
                                        <div class="text-xs font-mono font-medium text-slate-600 group-hover:text-[#1D6AE5] transition-colors mt-0.5">{{ t.user_email }}</div>
                                    </Link>
                                    <template v-else>
                                        <div class="font-bold text-[#0B1437]">{{ t.user_name || 'Đã xóa' }}</div>
                                        <div class="text-xs font-mono font-medium text-slate-500 mt-0.5">{{ t.user_email }}</div>
                                    </template>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono-num font-extrabold text-[#0B1437]">{{ formatVnd(t.approved_amount || t.amount) }}</td>
                                <td class="py-3.5 px-4 text-right font-mono-num text-emerald-700 font-bold">
                                    {{ t.bonus_amount > 0 ? '+' + formatVnd(t.bonus_amount) : '—' }}
                                </td>
                                <td class="py-3.5 px-4"><StatusBadge :state="t.status" :label="t.status_label" /></td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-600 font-medium">{{ t.created_at }}</td>
                                <td class="py-3.5 px-4 text-right space-x-1.5">
                                    <template v-if="t.status === 'pending'">
                                        <button
                                            type="button"
                                            :disabled="busyId !== null"
                                            @click="approve(t)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold cursor-pointer transition shadow-2xs active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <Loader2 v-if="busyId === t.id" class="w-3.5 h-3.5 animate-spin" />
                                            <Check v-else class="w-3.5 h-3.5" />
                                            <span>Duyệt</span>
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="busyId !== null"
                                            @click="reject(t)"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-rose-300 hover:bg-rose-50 text-rose-700 font-bold cursor-pointer transition shadow-2xs active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <X class="w-3.5 h-3.5" />
                                            <span>Hủy</span>
                                        </button>
                                    </template>
                                    <span v-else class="text-slate-600 font-mono-num font-semibold">{{ t.paid_at || '—' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PaginationBar :links="topups.links" />
        </div>
    </AdminLayout>
</template>
