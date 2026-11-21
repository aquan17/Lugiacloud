<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Plus, MessageSquare, Clock, AlertCircle, Image as ImageIcon, Loader2, X, Eye } from 'lucide-vue-next';

const props = defineProps<{
    tickets: Array<any>;
    counts: { moi: number; dang_xu_ly: number; cho_khach: number; da_dong: number };
    meta: { total: number; per_page: number; current_page: number };
    filters: { status: string; page: number; service_type?: string; service_id?: number | null };
    vpsOptions: Array<{ id: number; local_id: number; label: string }>;
    proxyOptions: Array<{ id: number; local_id: number; label: string }>;
    serverOptions?: Array<{ id: number; local_id: number; label: string }>;
    apiError?: string | null;
}>();

const totalAll = computed(
    () => (props.counts.moi || 0) + (props.counts.dang_xu_ly || 0) + (props.counts.cho_khach || 0) + (props.counts.da_dong || 0)
);

const tabs = computed(() => [
    { key: 'all', label: 'Tất cả', count: totalAll.value },
    { key: 'cho_khach', label: 'Chờ phản hồi', count: props.counts.cho_khach || 0 },
    { key: 'dang_xu_ly', label: 'Đang xử lý', count: props.counts.dang_xu_ly || 0 },
    { key: 'moi', label: 'Mới gửi', count: props.counts.moi || 0 },
    { key: 'da_dong', label: 'Đã đóng', count: props.counts.da_dong || 0 },
]);

function filterStatus(status: string, page = 1) {
    router.get('/admin/tickets', { status, page: page > 1 ? page : undefined }, { preserveState: true, replace: true });
}

function badgeClass(status: string): string {
    switch (status) {
        case 'cho_khach':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'dang_xu_ly':
            return 'bg-blue-50 text-[#1D6AE5] border-blue-200';
        case 'moi':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
}

// --- Modal Tạo Ticket mới ---
const showCreateModal = ref(false);
const createForm = useForm({
    loai_dich_vu: (props.filters.service_type === 'proxy' ? 'proxy' : props.filters.service_type === 'server' ? 'server' : 'vps') as 'vps' | 'proxy' | 'server',
    dich_vu_id: props.filters.service_id || ('' as number | ''),
    tieu_de: '',
    noi_dung: '',
    images: [] as File[],
});

const serviceOptions = computed(() => {
    if (createForm.loai_dich_vu === 'proxy') return props.proxyOptions;
    if (createForm.loai_dich_vu === 'server') return props.serverOptions || [];
    return props.vpsOptions;
});

function onSelectFiles(e: Event) {
    const files = Array.from((e.target as HTMLInputElement).files || []).slice(0, 5);
    createForm.images = files;
}

function submitCreate() {
    createForm.post('/admin/tickets', {
        forceFormData: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
}

onMounted(() => {
    if (props.filters.service_id) {
        showCreateModal.value = true;
    }
});

const totalPages = computed(() => Math.max(1, Math.ceil((props.meta.total || 0) / Math.max(1, props.meta.per_page || 20))));
</script>

<template>
    <AdminLayout title="Ticket Hỗ trợ Hạ tầng (CloudSunny)">
        <Head title="Ticket hỗ trợ hạ tầng" />

        <div class="space-y-5">
            <!-- Header bar -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="inline-flex p-1 rounded-xl bg-white border border-slate-200 shadow-2xs max-w-full overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        @click="filterStatus(t.key)"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition whitespace-nowrap shrink-0 inline-flex items-center gap-1.5"
                        :class="filters.status === t.key ? 'bg-[#0B1437] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <span>{{ t.label }}</span>
                        <span
                            class="px-1.5 py-0.2 rounded text-[10.5px] font-mono-num"
                            :class="filters.status === t.key ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ t.count }}
                        </span>
                    </button>
                </div>

                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer transition shadow-xs shrink-0"
                >
                    <Plus class="w-4 h-4" />
                    <span>Tạo Ticket kỹ thuật</span>
                </button>
            </div>

            <div v-if="apiError" class="p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-2.5 text-xs text-amber-900 font-semibold">
                <AlertCircle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                <span>{{ apiError }}</span>
            </div>

            <!-- Tickets Table -->
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600 whitespace-nowrap">
                                <th class="py-3 px-4">Mã / Tiêu đề</th>
                                <th class="py-3 px-4">Dịch vụ</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4 text-center">Tin nhắn</th>
                                <th class="py-3 px-4">Cập nhật gần nhất</th>
                                <th class="py-3 px-4 text-right">Xem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-if="tickets.length === 0">
                                <td colspan="6" class="py-12 text-center text-slate-500 font-medium">
                                    Chưa có ticket nào trong mục này.
                                </td>
                            </tr>
                            <tr v-for="tk in tickets" :key="tk.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4">
                                    <Link :href="`/admin/tickets/${tk.id}`" class="font-bold text-[#0B1437] hover:text-[#1D6AE5] transition-colors">
                                        <span class="font-mono text-slate-500 mr-1.5">{{ tk.ma || `#${tk.id}` }}</span>
                                        <span>{{ tk.tieu_de }}</span>
                                    </Link>
                                    <div v-if="tk.tu_dong_dong_luc && tk.trang_thai === 'cho_khach'" class="text-[11px] text-amber-700 font-medium mt-0.5 flex items-center gap-1">
                                        <Clock class="w-3 h-3" />
                                        <span>Tự đóng lúc {{ tk.tu_dong_dong_luc }} nếu không phản hồi</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-bold text-[11px] mr-1.5">{{ tk.ten_loai_dich_vu || tk.loai_dich_vu }}</span>
                                    <span class="font-mono font-bold text-[#0B1437]">{{ tk.dich_vu }}</span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 rounded-md border text-xs font-bold" :class="badgeClass(tk.trang_thai)">
                                        {{ tk.ten_trang_thai || tk.trang_thai }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono-num font-bold text-slate-700">
                                    <span class="inline-flex items-center gap-1">
                                        <MessageSquare class="w-3.5 h-3.5 text-slate-400" />
                                        {{ tk.so_tin }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-600 whitespace-nowrap">
                                    {{ tk.cap_nhat_luc || tk.tao_luc }}
                                </td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <Link
                                        :href="`/admin/tickets/${tk.id}`"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-800 font-bold transition"
                                    >
                                        <Eye class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                        <span>Hội thoại</span>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Simple Pagination -->
            <div v-if="totalPages > 1" class="flex items-center justify-end gap-2 text-xs">
                <button
                    type="button"
                    :disabled="meta.current_page <= 1"
                    @click="filterStatus(filters.status, meta.current_page - 1)"
                    class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white font-bold disabled:opacity-40 cursor-pointer"
                >
                    Trang trước
                </button>
                <span class="font-mono-num font-bold text-slate-700">Trang {{ meta.current_page }} / {{ totalPages }}</span>
                <button
                    type="button"
                    :disabled="meta.current_page >= totalPages"
                    @click="filterStatus(filters.status, meta.current_page + 1)"
                    class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white font-bold disabled:opacity-40 cursor-pointer"
                >
                    Trang sau
                </button>
            </div>
        </div>

        <!-- Modal Tạo Ticket kỹ thuật -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55" @click.self="!createForm.processing && (showCreateModal = false)">
                <form @submit.prevent="submitCreate" class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden text-xs">
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-extrabold text-[#0B1437]">Gửi Ticket Hỗ trợ Hạ tầng</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Kênh kỹ thuật nội bộ Admin ↔ CloudSunny (tối đa 10 ticket/ngày, 1 ticket mở/dịch vụ)</p>
                        </div>
                        <button type="button" @click="!createForm.processing && (showCreateModal = false)" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Loại dịch vụ</label>
                                <select
                                    v-model="createForm.loai_dich_vu"
                                    @change="createForm.dich_vu_id = ''"
                                    class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-white font-semibold"
                                >
                                    <option value="vps">Cloud VPS</option>
                                    <option value="proxy">Proxy IPv4</option>
                                    <option value="server">Máy chủ vật lý</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block font-bold text-slate-700 mb-1">Chọn dịch vụ gặp sự cố</label>
                                <select v-model="createForm.dich_vu_id" required class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-white font-mono">
                                    <option value="" disabled>-- Chọn {{ createForm.loai_dich_vu.toUpperCase() }} --</option>
                                    <option v-for="opt in serviceOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                                </select>
                                <p v-if="createForm.errors.dich_vu_id" class="mt-1 text-rose-600 font-semibold">{{ createForm.errors.dich_vu_id }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tiêu đề sự cố (5 – 150 ký tự)</label>
                            <input
                                v-model="createForm.tieu_de"
                                type="text"
                                required
                                minlength="5"
                                maxlength="150"
                                placeholder="VD: VPS không kết nối được RDP/SSH dù đã bật nguồn"
                                class="w-full px-3 py-2 rounded-xl border border-slate-300"
                            />
                            <p v-if="createForm.errors.tieu_de" class="mt-1 text-rose-600 font-semibold">{{ createForm.errors.tieu_de }}</p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Mô tả chi tiết lỗi (10 – 5.000 ký tự)</label>
                            <textarea
                                v-model="createForm.noi_dung"
                                rows="4"
                                required
                                minlength="10"
                                maxlength="5000"
                                placeholder="Mô tả thời điểm phát sinh, lỗi hiển thị, thao tác đã thử..."
                                class="w-full px-3 py-2 rounded-xl border border-slate-300 resize-y"
                            />
                            <p v-if="createForm.errors.noi_dung" class="mt-1 text-rose-600 font-semibold">{{ createForm.errors.noi_dung }}</p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1 flex items-center gap-1.5">
                                <ImageIcon class="w-3.5 h-3.5 text-slate-500" />
                                <span>Ảnh chụp lỗi đính kèm (tối đa 5 ảnh JPG/PNG/WEBP ≤ 5MB)</span>
                            </label>
                            <input
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                @change="onSelectFiles"
                                class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-700 file:font-bold hover:file:bg-slate-200"
                            />
                            <div v-if="createForm.images.length > 0" class="mt-1.5 text-[11px] text-emerald-700 font-semibold">
                                Đã chọn {{ createForm.images.length }} ảnh: {{ createForm.images.map((f) => f.name).join(', ') }}
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="!createForm.processing && (showCreateModal = false)" class="px-4 py-2 rounded-xl border border-slate-300 font-bold cursor-pointer">Hủy</button>
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] disabled:opacity-50 text-white font-bold cursor-pointer inline-flex items-center gap-1.5"
                            >
                                <Loader2 v-if="createForm.processing" class="w-3.5 h-3.5 animate-spin" />
                                <span>{{ createForm.processing ? 'Đang gửi...' : 'Gửi Ticket ngay' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </Teleport>
    </AdminLayout>
</template>
