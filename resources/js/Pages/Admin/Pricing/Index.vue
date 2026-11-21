<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatVnd } from '@/Composables/useFormat';
import { RefreshCw, ShieldCheck, Edit3, X } from 'lucide-vue-next';

const props = defineProps<{
    items: Array<any>;
    defaults: {
        default_markup_percent: number;
        auto_publish_new: boolean;
    };
}>();

const activeTab = ref<'vps' | 'proxy' | 'server' | 'addon'>('vps');
const editing = ref<any | null>(null);

const filteredItems = computed(() => props.items.filter((i) => i.service_type === activeTab.value));

const defaultForm = useForm({
    default_markup_percent: props.defaults.default_markup_percent,
    auto_publish_new: props.defaults.auto_publish_new,
});

const editForm = useForm({
    name: '',
    description: '',
    badge: '',
    provider_cost_monthly: 0,
    markup_mode: 'fixed_price',
    markup_percent: 35,
    retail_price_monthly: 0,
    protect_cost_floor: true,
    min_margin_percent: 5,
    is_active: true,
    is_featured: false,
    sort_order: 10,
});

function openEdit(row: any) {
    editing.value = row;
    editForm.name = row.name;
    editForm.description = row.description || '';
    editForm.badge = row.badge || '';
    editForm.provider_cost_monthly = row.provider_cost_monthly;
    editForm.markup_mode = row.markup_mode;
    editForm.markup_percent = row.markup_percent;
    editForm.retail_price_monthly = row.raw_retail_price_monthly;
    editForm.protect_cost_floor = row.protect_cost_floor;
    editForm.min_margin_percent = row.min_margin_percent;
    editForm.is_active = row.is_active;
    editForm.is_featured = row.is_featured;
    editForm.sort_order = row.sort_order;
}

const previewRetail = computed(() => {
    if (editForm.markup_mode === 'percent_markup' && editForm.provider_cost_monthly > 0) {
        return Math.ceil((editForm.provider_cost_monthly * (1 + Number(editForm.markup_percent) / 100)) / 1000) * 1000;
    }
    return Number(editForm.retail_price_monthly) || 0;
});

function saveItem() {
    if (!editing.value) return;
    editForm.put(`/admin/pricing/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}
</script>

<template>
    <AdminLayout title="Bảng giá & Biên lợi nhuận Reseller">
        <Head title="Bảng giá & Biên lợi nhuận" />

        <div class="space-y-6">
            <!-- Top Policy & Upstream Sync Bar -->
            <div class="surface-card p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-5 rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="space-y-1 max-w-xl">
                    <div class="flex items-center gap-2 text-xs font-bold text-[#1D6AE5]">
                        <ShieldCheck class="w-4 h-4" />
                        <span>Cơ chế Bảo vệ Biên lợi nhuận Đại lý</span>
                    </div>
                    <p class="text-xs text-slate-600 font-medium leading-relaxed">
                        Hệ thống tách bạch <strong>Giá vốn gốc từ API đối tác</strong> và <strong>Giá bán lẻ LugiaCloud</strong>. Khi đối tác thêm gói mới, hệ thống tự động nhân hệ số tăng giá mặc định thay vì bán bằng giá gốc, đồng thời chặn không cho Sự kiện/Coupon làm âm vốn.
                    </p>
                </div>

                <form @submit.prevent="defaultForm.put('/admin/pricing/defaults')" class="flex flex-wrap items-center gap-3 shrink-0">
                    <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium">
                        <span class="text-slate-700 font-semibold">Markup mặc định gói mới:</span>
                        <input
                            v-model.number="defaultForm.default_markup_percent"
                            type="number"
                            min="5"
                            max="300"
                            class="w-16 px-2 py-0.5 rounded-lg border border-slate-300 font-mono-num font-bold text-right"
                        />
                        <span class="font-bold">%</span>
                    </div>

                    <button
                        type="submit"
                        :disabled="defaultForm.processing"
                        class="px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-800 transition shadow-2xs cursor-pointer active:scale-95"
                    >
                        Lưu quy tắc
                    </button>

                    <button
                        type="button"
                        @click="router.post('/admin/pricing/sync')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold transition shadow-2xs cursor-pointer active:scale-95"
                    >
                        <RefreshCw class="w-3.5 h-3.5" />
                        <span>Đồng bộ Giá Gốc API</span>
                    </button>
                </form>
            </div>

            <!-- Tabs -->
            <div class="inline-flex p-1 rounded-xl bg-white border border-slate-200 shadow-2xs max-w-full overflow-x-auto [&::-webkit-scrollbar]:hidden [scrollbar-width:none]">
                <button
                    v-for="t in [
                        { key: 'vps', label: 'Gói Cloud VPS' },
                        { key: 'proxy', label: 'Gói Proxy IPv4' },
                        { key: 'server', label: 'Máy chủ vật lý' },
                        { key: 'addon', label: 'Đơn giá Nâng cấp Addon' },
                    ]"
                    :key="t.key"
                    type="button"
                    @click="activeTab = t.key as any"
                    class="px-3.5 sm:px-4 py-1.5 rounded-lg text-xs font-bold cursor-pointer transition whitespace-nowrap shrink-0"
                    :class="activeTab === t.key ? 'bg-[#0B1437] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                >
                    {{ t.label }}
                </button>
            </div>

            <!-- Wholesale vs Retail Table -->
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="sm:hidden px-4 py-2 bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-500">
                    ← Vuốt ngang bảng để xem đầy đủ các cột →
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600 whitespace-nowrap">
                                <th class="py-3 px-4">Tên gói hiển thị</th>
                                <th class="py-3 px-4">Mã gốc API</th>
                                <th class="py-3 px-4 text-right">Giá vốn gốc / tháng</th>
                                <th class="py-3 px-4">Kiểu định giá</th>
                                <th class="py-3 px-4 text-right">Giá bán lẻ / tháng</th>
                                <th class="py-3 px-4 text-right">Lợi nhuận gộp</th>
                                <th class="py-3 px-4">Chặn lỗ</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4 text-right">Chỉnh sửa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-[#0B1437] flex items-center gap-2">
                                        <span>{{ item.name }}</span>
                                        <span v-if="item.badge" class="px-2 py-0.5 rounded-md bg-blue-50 text-[#1D6AE5] border border-blue-200 text-[10.5px] font-bold">
                                            {{ item.badge }}
                                        </span>
                                    </div>
                                    <div class="text-xs font-mono font-medium text-slate-600 mt-0.5">{{ item.slug }}</div>
                                </td>

                                <td class="py-3.5 px-4 font-mono font-medium text-slate-700">
                                    {{ item.provider_code }} {{ item.provider_product_id ? `(#${item.provider_product_id})` : '' }}
                                </td>

                                <td class="py-3.5 px-4 text-right font-mono-num font-bold text-slate-700">
                                    {{ formatVnd(item.provider_cost_monthly) }}<span v-if="item.slug === 'addon-change-ip'" class="text-[10.5px] font-normal text-slate-500">/lần</span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-xs">
                                        {{ item.markup_mode === 'percent_markup' ? `Tự động +${item.markup_percent}%` : 'Giá cố định' }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-right font-mono-num font-extrabold text-[#0B1437]">
                                    {{ formatVnd(item.retail_price_monthly) }}<span v-if="item.slug === 'addon-change-ip'" class="text-[10.5px] font-medium text-slate-500">/lần</span>
                                </td>

                                <td class="py-3.5 px-4 text-right font-mono-num">
                                    <span :class="item.profit_monthly > 0 ? 'text-emerald-700 font-extrabold' : 'text-rose-600 font-extrabold'">
                                        +{{ formatVnd(item.profit_monthly) }} ({{ item.margin_percent }}%)
                                    </span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span
                                        class="text-xs font-mono-num font-bold"
                                        :class="item.protect_cost_floor ? 'text-emerald-800' : 'text-slate-500'"
                                    >
                                        {{ item.protect_cost_floor ? `Min +${item.min_margin_percent}%` : 'Tắt' }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span
                                        class="px-2.5 py-0.5 rounded-md text-xs font-bold border"
                                        :class="item.is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'"
                                    >
                                        {{ item.is_active ? 'Đang bán' : 'Đang ẩn' }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <button
                                        type="button"
                                        @click="openEdit(item)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 font-bold text-slate-800 cursor-pointer transition shadow-2xs active:scale-95"
                                    >
                                        <Edit3 class="w-3 h-3 text-[#1D6AE5]" />
                                        <span>Sửa giá</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Edit Pricing Modal -->
            <Teleport to="body">
                <div
                    v-if="editing"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/55"
                    @click.self="editing = null"
                >
                    <form @submit.prevent="saveItem" class="w-full max-w-lg bg-white rounded-xl border border-slate-200 shadow-2xl p-6 space-y-4 text-xs">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                            <h3 class="text-sm font-bold text-[#0B1437]">Chỉnh sửa định giá: {{ editing.name }}</h3>
                            <button type="button" @click="editing = null" class="text-slate-400 hover:text-slate-700">
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Tên hiển thị cho khách</label>
                                <input v-model="editForm.name" type="text" required class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Nhãn nổi bật (Badge)</label>
                                <input v-model="editForm.badge" type="text" placeholder="VD: Phổ biến" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Mô tả ngắn</label>
                            <input v-model="editForm.description" type="text" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Giá vốn gốc đối tác (VNĐ/tháng)</label>
                                <input v-model.number="editForm.provider_cost_monthly" type="number" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold" />
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Chế độ tăng giá</label>
                                <select v-model="editForm.markup_mode" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                                    <option value="fixed_price">Ấn định giá bán lẻ cố định</option>
                                    <option value="percent_markup">Tự nhân % theo giá gốc API</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div v-if="editForm.markup_mode === 'percent_markup'">
                                <label class="block font-semibold text-slate-700 mb-1">% Lợi nhuận cộng thêm</label>
                                <input v-model.number="editForm.markup_percent" type="number" step="0.5" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold" />
                            </div>
                            <div v-else>
                                <label class="block font-semibold text-slate-700 mb-1">Giá bán lẻ niêm yết (VNĐ/tháng)</label>
                                <input v-model.number="editForm.retail_price_monthly" type="number" step="1000" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold text-[#1D6AE5]" />
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Biên lãi tối thiểu bảo vệ (%)</label>
                                <input v-model.number="editForm.min_margin_percent" type="number" step="0.5" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num" />
                            </div>
                        </div>

                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 flex justify-between items-center">
                            <span>Giá bán thực tế áp dụng: <strong class="font-mono-num text-[#0B1437]">{{ formatVnd(previewRetail) }}</strong></span>
                            <span class="font-mono-num text-emerald-700 font-bold">
                                Lãi gộp: +{{ formatVnd(previewRetail - editForm.provider_cost_monthly) }}
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-5 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="editForm.protect_cost_floor" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Bật chốt chặn không bán dưới giá vốn</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="editForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Hiển thị bán trên web</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="editForm.is_featured" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Đánh dấu gói nổi bật</span>
                            </label>
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                            <button type="button" @click="editing = null" class="px-4 py-2 rounded-lg border border-slate-300 font-semibold">Hủy</button>
                            <button type="submit" :disabled="editForm.processing" class="px-4 py-2 rounded-lg bg-[#0B1437] text-white font-semibold cursor-pointer">
                                Lưu cấu hình giá
                            </button>
                        </div>
                    </form>
                </div>
            </Teleport>
        </div>
    </AdminLayout>
</template>
