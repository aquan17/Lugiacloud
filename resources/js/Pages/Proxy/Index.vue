<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AutoRenewSwitch from '@/Components/AutoRenewSwitch.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { copyToClipboard, downloadTextFile } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { askConfirm } from '@/Composables/useConfirmAction';
import { Plus, Search, Copy, Download, RefreshCw, Calendar, ArrowRight } from 'lucide-vue-next';

const props = defineProps<{
    instances: { data: Array<any>; links: Array<any> };
    filters: { status: string; search: string };
    counts: { all: number; active: number; expiring: number; expired: number; deleted?: number };
}>();

const toast = useToast();
const search = ref(props.filters.search || '');
const selectedIds = ref<number[]>([]);

// Debounced realtime search (280ms)
let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(search, (newVal) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        selectedIds.value = [];
        router.get(
            '/proxy',
            { status: props.filters.status, search: newVal || undefined },
            { preserveState: true, replace: true }
        );
    }, 280);
});

const selectableInstances = computed(() => props.instances.data.filter((p) => p.state !== 'deleted'));

const allPageSelected = computed(
    () => selectableInstances.value.length > 0 && selectableInstances.value.every((p) => selectedIds.value.includes(p.id))
);

function toggleAll() {
    if (allPageSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = selectableInstances.value.map((p) => p.id);
    }
}

function toggleOne(id: number) {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
}

const selectedProxies = computed(() => props.instances.data.filter((p) => selectedIds.value.includes(p.id)));

async function copySelected(format: 'raw' | 'uri') {
    const lines = selectedProxies.value
        .map((p) => (format === 'uri' ? p.uri_string : p.connection_string))
        .filter(Boolean);
    if (lines.length === 0) {
        toast.error('Các Proxy đang chọn chưa có IP sẵn sàng.');
        return;
    }
    if (await copyToClipboard(lines.join('\n'))) {
        toast.success(`Đã sao chép ${lines.length} dòng Proxy`);
    }
}

function exportSelected(ext: 'txt' | 'csv') {
    const rawList = selectedProxies.value.length > 0 ? selectedProxies.value : props.instances.data;
    const list = rawList.filter((p) => p.state !== 'deleted' && p.connection_string);
    if (list.length === 0) {
        toast.error('Không có Proxy đang hoạt động để xuất.');
        return;
    }
    if (ext === 'txt') {
        const content = list.map((p) => p.connection_string).filter(Boolean).join('\n');
        downloadTextFile(`lugiacloud-proxies-${Date.now()}.txt`, content);
    } else {
        const header = 'ID,GiaoThuc,IP,Port,TaiKhoan,MatKhau,HetHan';
        const rows = list.map((p) => `${p.id},${p.protocol},${p.ip || ''},${p.port || ''},${p.username || ''},${p.password || ''},${p.expires_at || ''}`);
        downloadTextFile(`lugiacloud-proxies-${Date.now()}.csv`, [header, ...rows].join('\n'), 'text/csv;charset=utf-8');
    }
    toast.success(`Đã xuất danh sách ${list.length} Proxy ra file .${ext.toUpperCase()}`);
}

function submitBulkAction(action: 'enable_autorenew' | 'disable_autorenew' | 'renew') {
    router.post(
        '/proxy/bulk-action',
        { ids: selectedIds.value, action },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
        }
    );
}

function runBulkAction(action: 'enable_autorenew' | 'disable_autorenew' | 'renew') {
    if (selectedIds.value.length === 0) return;
    if (action === 'renew') {
        askConfirm({
            title: 'Gia hạn hàng loạt Proxy',
            message: `Xác nhận gia hạn thêm 1 tháng cho ${selectedIds.value.length} Proxy đã chọn? Số tiền tương ứng sẽ được trừ trực tiếp từ ví của bạn.`,
            confirmText: `Gia hạn ${selectedIds.value.length} Proxy`,
            variant: 'primary',
            onConfirm: () => submitBulkAction('renew'),
        });
        return;
    }
    submitBulkAction(action);
}

function toggleAutoRenew(p: any) {
    router.post(`/proxy/${p.id}/auto-renew`, { is_auto_renew: !p.is_auto_renew }, { preserveScroll: true });
}

function filterStatus(status: string) {
    selectedIds.value = [];
    router.get('/proxy', { status, search: search.value || undefined }, { preserveState: true, replace: true });
}

function submitSearch() {
    selectedIds.value = [];
    router.get('/proxy', { status: props.filters.status, search: search.value || undefined }, { preserveState: true, replace: true });
}

async function copySingle(str: string | null) {
    if (!str) return;
    if (await copyToClipboard(str)) toast.success('Đã sao chép chuỗi kết nối Proxy (IP:Port:User:Pass)');
}

// Tự động đồng bộ IP cho các Proxy đang khởi tạo ngay ngoài trang danh sách /proxy
const pendingProxyList = computed(() =>
    props.instances.data.filter(
        (p) => p.state === 'provisioning' || (p.state !== 'deleted' && p.state !== 'failed' && !p.ip)
    )
);
const isSyncingProxies = ref(false);
let proxyPollTimer: ReturnType<typeof setInterval> | null = null;
let proxyInitialTimer: ReturnType<typeof setTimeout> | null = null;

async function syncPendingProxies() {
    const targets = pendingProxyList.value.slice(0, 6);
    if (targets.length === 0 || isSyncingProxies.value) return;

    isSyncingProxies.value = true;
    let changed = false;
    try {
        const results = await Promise.allSettled(targets.map((p) => axios.get(`/proxy/${p.id}/status`)));
        results.forEach((res, idx) => {
            if (res.status !== 'fulfilled') return;
            const prev = targets[idx];
            const data = res.value?.data || {};
            if ((data.ip && data.ip !== prev.ip) || (data.state && data.state !== prev.state)) {
                changed = true;
            }
        });
        if (changed) {
            router.reload({ only: ['instances', 'counts'], preserveScroll: true, preserveState: true });
        }
    } finally {
        isSyncingProxies.value = false;
    }
}

function stopProxyPolling() {
    if (proxyPollTimer) {
        clearInterval(proxyPollTimer);
        proxyPollTimer = null;
    }
    if (proxyInitialTimer) {
        clearTimeout(proxyInitialTimer);
        proxyInitialTimer = null;
    }
}

function ensureProxyPolling() {
    if (pendingProxyList.value.length === 0) {
        stopProxyPolling();
        return;
    }
    if (!proxyPollTimer) {
        proxyInitialTimer = setTimeout(() => syncPendingProxies(), 1200);
        proxyPollTimer = setInterval(() => syncPendingProxies(), 6000);
    }
}

watch(() => pendingProxyList.value.length, () => ensureProxyPolling());
onMounted(() => ensureProxyPolling());
onUnmounted(() => stopProxyPolling());
</script>

<template>
    <ClientLayout title="Quản lý Proxy IPv4 Việt Nam">
        <Head title="Quản lý Proxy IPv4" />

        <div class="space-y-5 pb-20">
            <!-- Thanh lọc & Xuất dữ liệu -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="inline-flex p-1 rounded-xl bg-white border border-slate-200 overflow-x-auto shadow-xs">
                    <button
                        v-for="t in [
                            { key: 'active', label: 'Đang hoạt động', count: counts.active, dot: 'bg-emerald-500' },
                            { key: 'expiring', label: 'Sắp hết hạn', count: counts.expiring, dot: 'bg-amber-500' },
                            { key: 'deleted', label: 'Đã xóa', count: counts.deleted ?? 0, dot: 'bg-slate-400' },
                            { key: 'all', label: 'Tất cả', count: counts.all, dot: 'bg-slate-400' },
                        ]"
                        :key="t.key"
                        type="button"
                        @click="filterStatus(t.key)"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
                        :class="filters.status === t.key ? 'bg-[#0B1437] text-white font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <span class="w-2 h-2 rounded-full shrink-0" :class="t.dot" />
                        <span>{{ t.label }}</span>
                        <span class="font-mono-num opacity-75 ml-0.5">({{ t.count }})</span>
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <form @submit.prevent="submitSearch" class="relative flex-1 sm:w-64">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Tìm IP, Port, Tài khoản..."
                            class="w-full pl-8 pr-3 py-2 rounded-xl bg-white border border-slate-300 text-xs focus:outline-hidden focus:border-[#1D6AE5] transition shadow-xs"
                        />
                    </form>

                    <button
                        v-if="selectableInstances.length > 0"
                        type="button"
                        @click="exportSelected('txt')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-300 text-xs font-semibold text-slate-700 cursor-pointer shadow-xs transition"
                    >
                        <Download class="w-3.5 h-3.5 text-slate-500" />
                        <span>Xuất TXT</span>
                    </button>

                    <Link
                        href="/proxy/store"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold shadow-xs cursor-pointer transition active:scale-95"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Mua Proxy Mới</span>
                    </Link>
                </div>
            </div>

            <div v-if="instances.data.length === 0" class="surface-card p-12 text-center space-y-3 rounded-2xl border border-slate-200">
                <p class="text-sm font-bold text-slate-800">Chưa có Proxy nào trong mục này</p>
                <Link href="/proxy/store" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#0B1437] text-white text-xs font-bold">
                    <Plus class="w-3.5 h-3.5" />
                    <span>Đăng ký Proxy IPv4 ngay</span>
                </Link>
            </div>

            <template v-else>
                <!-- Bảng trên Desktop -->
                <div class="hidden md:block surface-card overflow-hidden rounded-2xl border border-slate-200 shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-3 px-3 w-8">
                                        <input
                                            type="checkbox"
                                            :checked="allPageSelected"
                                            :disabled="selectableInstances.length === 0"
                                            @change="toggleAll"
                                            class="rounded border-slate-300 text-[#1D6AE5] disabled:opacity-40"
                                        />
                                    </th>
                                    <th class="py-3 px-3">Gói dịch vụ</th>
                                    <th class="py-3 px-3">Kết nối (IP:Port)</th>
                                    <th class="py-3 px-3">Trạng thái</th>
                                    <th class="py-3 px-3">Hết hạn</th>
                                    <th class="py-3 px-3 text-right">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 text-[13px]">
                                <tr
                                    v-for="p in instances.data"
                                    :key="p.id"
                                    class="hover:bg-slate-50/70 transition-colors"
                                    :class="selectedIds.includes(p.id) ? 'bg-blue-50/35' : ''"
                                >
                                    <td class="py-3 px-3">
                                        <input
                                            type="checkbox"
                                            :checked="selectedIds.includes(p.id)"
                                            :disabled="p.state === 'deleted'"
                                            @change="toggleOne(p.id)"
                                            class="rounded border-slate-300 text-[#1D6AE5] disabled:opacity-40"
                                        />
                                    </td>

                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <div class="font-bold text-[#0B1437]">{{ p.product_name }}</div>
                                        <div class="text-xs font-mono font-medium text-slate-600 mt-0.5">#{{ p.id }} · {{ p.protocol }} · 1 Gbps</div>
                                    </td>

                                    <td class="py-3 px-3 font-mono-num whitespace-nowrap">
                                        <span v-if="p.state === 'deleted'" class="text-slate-400 text-xs font-mono">
                                            {{ p.ip || '—' }}
                                        </span>
                                        <button
                                            v-else-if="p.connection_string"
                                            type="button"
                                            @click="copySingle(p.connection_string)"
                                            title="Click để sao chép chuỗi kết nối IP:Port:User:Pass"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-mono font-medium cursor-pointer transition"
                                        >
                                            <span class="font-bold text-slate-900">{{ p.ip }}:{{ p.port }}</span>
                                            <span class="text-slate-600 font-medium">({{ p.username }})</span>
                                            <Copy class="w-3 h-3 text-slate-400 hover:text-[#1D6AE5] shrink-0" />
                                        </button>
                                        <span v-else class="text-slate-400 italic">Đang cấp IP...</span>
                                    </td>

                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <div class="flex flex-col items-start gap-1.5">
                                            <StatusBadge :state="p.state" :label="p.status" />
                                            <AutoRenewSwitch
                                                v-if="p.state !== 'deleted'"
                                                :model-value="!!p.is_auto_renew"
                                                @toggle="toggleAutoRenew(p)"
                                            />
                                        </div>
                                    </td>

                                    <td class="py-3 px-3 whitespace-nowrap font-mono-num text-xs">
                                        <div class="flex items-center gap-1.5 text-slate-800 font-semibold">
                                            <span>{{ p.expires_at || '—' }}</span>
                                            <span
                                                v-if="p.state === 'deleted'"
                                                class="px-1.5 py-0.2 rounded-md text-[10.5px] font-bold border bg-slate-100 text-slate-500 border-slate-200"
                                            >
                                                Đã hủy
                                            </span>
                                            <span
                                                v-else-if="p.is_expired"
                                                class="px-1.5 py-0.2 rounded-md text-[10.5px] font-bold border bg-rose-50 text-rose-700 border-rose-200"
                                            >
                                                Đã hết hạn
                                            </span>
                                            <span
                                                v-else-if="p.days_until_expiry !== null && p.days_until_expiry <= 5"
                                                class="px-1.5 py-0.2 rounded-md text-[10.5px] font-bold border bg-amber-50 text-amber-800 border-amber-200"
                                            >
                                                Còn {{ p.days_until_expiry }} ngày
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-3 px-3 text-right whitespace-nowrap">
                                        <Link
                                            v-if="p.state !== 'deleted'"
                                            :href="`/proxy/${p.id}`"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-[#1D6AE5]/10 hover:bg-[#1D6AE5]/20 text-[#1D6AE5] text-xs font-bold transition cursor-pointer"
                                        >
                                            <span>Quản lý</span>
                                            <ArrowRight class="w-3 h-3" />
                                        </Link>
                                        <span v-else class="text-xs font-medium text-slate-400">Đã thu hồi</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Thẻ dọc trên Điện thoại (Mobile) -->
                <div class="grid grid-cols-1 gap-3 md:hidden">
                    <div v-for="p in instances.data" :key="'mp_' + p.id" class="surface-card p-4 space-y-3 text-xs rounded-2xl border border-slate-200 shadow-xs">
                        <div class="flex items-start justify-between gap-2">
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(p.id)"
                                    :disabled="p.state === 'deleted'"
                                    @change="toggleOne(p.id)"
                                    class="rounded border-slate-300 text-[#1D6AE5] disabled:opacity-40"
                                />
                                <div>
                                    <div class="font-bold text-slate-900">{{ p.product_name }}</div>
                                    <div class="text-xs font-mono font-medium text-slate-600 mt-0.5">#{{ p.id }} · {{ p.protocol }} · 1 Gbps</div>
                                </div>
                            </label>
                            <div class="flex flex-col items-end gap-1.5 shrink-0">
                                <StatusBadge :state="p.state" :label="p.status" />
                                <AutoRenewSwitch
                                    v-if="p.state !== 'deleted'"
                                    :model-value="!!p.is_auto_renew"
                                    @toggle="toggleAutoRenew(p)"
                                />
                            </div>
                        </div>

                        <div
                            v-if="p.state === 'deleted'"
                            class="w-full px-3 py-2 rounded-lg bg-slate-50 font-mono text-[11px] text-slate-400"
                        >
                            IP: {{ p.ip || '—' }} · Thông tin kết nối đã được ẩn
                        </div>
                        <button
                            v-else-if="p.connection_string"
                            type="button"
                            @click="copySingle(p.connection_string)"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-slate-100 font-mono text-[11px] font-medium text-slate-900"
                        >
                            <span>{{ p.ip }}:{{ p.port }} <span class="text-slate-600 font-medium">({{ p.username }})</span></span>
                            <span class="inline-flex items-center gap-1 text-[#1D6AE5] font-semibold text-[11px]">
                                <Copy class="w-3 h-3" />
                                <span>Sao chép</span>
                            </span>
                        </button>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 text-xs font-mono-num text-slate-600 font-medium">
                                <Calendar class="w-3.5 h-3.5" /> {{ p.expires_at || '—' }}
                            </span>
                            <Link v-if="p.state !== 'deleted'" :href="`/proxy/${p.id}`" class="font-bold text-[#1D6AE5] inline-flex items-center gap-1">
                                <span>Quản lý</span>
                                <ArrowRight class="w-3 h-3" />
                            </Link>
                            <span v-else class="text-xs font-medium text-slate-400">Đã thu hồi</span>
                        </div>
                    </div>
                </div>
            </template>

            <PaginationBar :links="instances.links" />

            <!-- THANH THAO TÁC HÀNG LOẠT -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="translate-y-4 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="translate-y-4 opacity-0"
            >
                <div
                    v-if="selectedIds.length > 0"
                    class="fixed bottom-4 left-4 right-4 sm:left-1/2 sm:right-auto sm:-translate-x-1/2 z-40 bg-[#0B1437] text-white px-4 py-2.5 rounded-xl shadow-2xl border border-slate-700 flex flex-wrap items-center justify-center gap-2 text-xs"
                >
                    <span class="font-mono-num font-bold text-sky-300 pr-2 border-r border-white/15">
                        Đã chọn {{ selectedIds.length }} IP
                    </span>

                    <button
                        type="button"
                        @click="copySelected('raw')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 font-medium cursor-pointer"
                    >
                        <Copy class="w-3.5 h-3.5" />
                        <span>Sao chép IP:Port:U:P</span>
                    </button>

                    <button
                        type="button"
                        @click="exportSelected('csv')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 font-medium cursor-pointer"
                    >
                        <Download class="w-3.5 h-3.5" />
                        <span>Xuất CSV</span>
                    </button>

                    <button
                        type="button"
                        @click="runBulkAction('enable_autorenew')"
                        class="px-2.5 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 font-medium cursor-pointer"
                    >
                        Bật tự gia hạn
                    </button>

                    <button
                        type="button"
                        @click="runBulkAction('renew')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] font-bold cursor-pointer"
                    >
                        <RefreshCw class="w-3.5 h-3.5" />
                        <span>Gia hạn +1 tháng</span>
                    </button>
                </div>
            </Transition>
        </div>
    </ClientLayout>
</template>
