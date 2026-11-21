<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import OsIcon from '@/Components/OsIcon.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AutoRenewSwitch from '@/Components/AutoRenewSwitch.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { copyToClipboard, formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import {
    downloadRdpFile,
    downloadVpsListTxt,
    formatFullVpsCredential,
} from '@/Composables/useVpsConnect';
import {
    Plus,
    Search,
    X,
    Copy,
    Eye,
    EyeOff,
    Download,
    ChevronRight,
    Cpu,
    Calendar,
    ClipboardList,
    FileDown,
    RefreshCw,
} from 'lucide-vue-next';

const props = defineProps<{
    instances: { data: Array<any>; links: Array<any> };
    filters: { status: string; search: string };
    counts: { all: number; active: number; stopped?: number; expired: number; deleted: number };
}>();

const toast = useToast();
const search = ref(props.filters.search || '');
const revealedIds = ref<Record<number, boolean>>({});
const selectedIds = ref<number[]>([]);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilter(status: string) {
    selectedIds.value = [];
    router.get(
        '/vps',
        { status, search: search.value.trim() || undefined },
        { preserveState: true, replace: true }
    );
}

watch(search, (val) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            '/vps',
            { status: props.filters.status, search: val.trim() || undefined },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 260);
});

function clearSearch() {
    search.value = '';
}

function toggleReveal(id: number) {
    revealedIds.value[id] = !revealedIds.value[id];
}

function toggleAutoRenew(v: any) {
    if (v.state === 'deleted') return;
    router.post(
        `/vps/${v.id}/auto-renew`,
        { is_auto_renew: !v.is_auto_renew },
        { preserveScroll: true }
    );
}

const allPageIds = computed(() => props.instances.data.map((v) => Number(v.id)));
const isAllSelected = computed(
    () => allPageIds.value.length > 0 && allPageIds.value.every((id) => selectedIds.value.includes(id))
);

function toggleSelectAll() {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = [...allPageIds.value];
    }
}

function toggleRowSelect(id: number) {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) selectedIds.value.splice(idx, 1);
    else selectedIds.value.push(id);
}

const selectedItems = computed(() =>
    props.instances.data.filter((v) => selectedIds.value.includes(Number(v.id)))
);

async function copyText(val: string, label: string) {
    if (!val) return;
    if (await copyToClipboard(val)) {
        toast.success(`Đã sao chép ${label}`);
    }
}

async function copySelectedCredentials() {
    if (selectedItems.value.length === 0) return;
    const text = selectedItems.value.map((v) => formatFullVpsCredential(v)).join('\n');
    if (await copyToClipboard(text)) {
        toast.success(`Đã chép ${selectedItems.value.length} dòng (IP|User|Pass)`);
    }
}

function exportSelectedTxt() {
    if (selectedItems.value.length === 0) return;
    downloadVpsListTxt(selectedItems.value);
    toast.success(`Đã tải danh sách ${selectedItems.value.length} VPS (.txt)`);
}

function expiryBadge(v: any): { text: string; cls: string } | null {
    if (v.state === 'deleted') {
        return { text: 'Đã thu hồi', cls: 'bg-zinc-100 text-zinc-600 border-zinc-200' };
    }
    if (v.state === 'expired' || (v.days_left !== null && v.days_left <= 0)) {
        return { text: 'Quá hạn', cls: 'bg-rose-50 text-rose-700 border-rose-200' };
    }
    if (v.days_left !== null && v.days_left <= 5) {
        return { text: `Còn ${v.days_left} ngày`, cls: 'bg-amber-50 text-amber-800 border-amber-200' };
    }
    if (v.days_left !== null) {
        return { text: `Còn ${v.days_left} ngày`, cls: 'bg-slate-100 text-slate-700 border-slate-200' };
    }
    return null;
}

const filterTabs = computed(() => [
    { key: 'active', label: 'Đang hoạt động', count: props.counts.active, dot: 'bg-emerald-500' },
    { key: 'stopped', label: 'Đã tắt', count: props.counts.stopped ?? 0, dot: 'bg-slate-400' },
    { key: 'expired', label: 'Quá hạn', count: props.counts.expired, dot: 'bg-rose-500' },
    { key: 'deleted', label: 'Đã xóa', count: props.counts.deleted, dot: 'bg-zinc-400' },
    { key: 'all', label: 'Tất cả', count: props.counts.all, dot: 'bg-blue-500' },
]);

// Tự động đồng bộ IP & trạng thái cho các VPS đang khởi tạo ngay ngoài trang danh sách /vps
const pendingSyncList = computed(() =>
    props.instances.data.filter(
        (v) =>
            v.state === 'provisioning' ||
            (v.state !== 'deleted' && v.state !== 'failed' && v.state !== 'expired' && !v.public_ip)
    )
);

const isSyncingPending = ref(false);
let pollTimer: ReturnType<typeof setInterval> | null = null;
let initialTimer: ReturnType<typeof setTimeout> | null = null;

async function syncPendingVps(manual = false) {
    if (pendingSyncList.value.length === 0 || isSyncingPending.value) return;
    if (!manual && typeof document !== 'undefined' && document.hidden) return;

    isSyncingPending.value = true;
    try {
        const { data } = await axios.get('/vps/sync-pending', {
            params: manual ? { force: 1 } : undefined,
        });

        if (data?.changed) {
            router.reload({
                only: ['instances', 'counts'],
                preserveScroll: true,
                preserveState: true,
            });
            if (Array.isArray(data.newly_ready) && data.newly_ready.length > 0) {
                toast.success(`Đã cấp phát xong: ${data.newly_ready.join(', ')}`);
            }
        } else if (manual) {
            toast.info('Máy chủ vẫn đang cài đặt HĐH bên đối tác, trang sẽ tự cập nhật khi sẵn sàng.');
        }
    } catch {
        if (manual) toast.error('Không thể đồng bộ lúc này, vui lòng thử lại sau ít giây.');
    } finally {
        isSyncingPending.value = false;
    }
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    if (initialTimer) {
        clearTimeout(initialTimer);
        initialTimer = null;
    }
}

function ensurePolling() {
    if (pendingSyncList.value.length === 0) {
        stopPolling();
        return;
    }
    if (!pollTimer) {
        initialTimer = setTimeout(() => syncPendingVps(false), 800);
        pollTimer = setInterval(() => syncPendingVps(false), 8000);
    }
}

watch(
    () => pendingSyncList.value.length,
    () => ensurePolling()
);

onMounted(() => ensurePolling());
onUnmounted(() => stopPolling());
</script>

<template>
    <ClientLayout title="Quản lý Máy chủ Cloud VPS">
        <Head title="Danh sách Cloud VPS" />

        <div class="space-y-4">
            <!-- Thanh lọc 4 trạng thái & Tìm kiếm tức thì -->
            <div class="surface-card p-3 sm:p-3.5 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 lg:pb-0">
                    <button
                        v-for="tab in filterTabs"
                        :key="tab.key"
                        type="button"
                        @click="applyFilter(tab.key)"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[13px] font-semibold whitespace-nowrap transition-all cursor-pointer border"
                        :class="
                            filters.status === tab.key
                                ? 'bg-[#0B1437] text-white border-[#0B1437] font-bold shadow-2xs'
                                : 'bg-white text-slate-600 border-transparent hover:bg-slate-100 hover:text-slate-900'
                        "
                    >
                        <span class="w-2 h-2 rounded-full shrink-0" :class="tab.dot" />
                        <span>{{ tab.label }}</span>
                        <span
                            class="px-1.5 py-0.2 rounded-full text-[11px] font-mono-num font-bold"
                            :class="filters.status === tab.key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'"
                        >
                            {{ tab.count }}
                        </span>
                    </button>
                </div>

                <div class="flex items-center gap-2.5">
                    <div class="relative flex-1 lg:w-72">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Tìm tên máy, IPv4, mã gói..."
                            class="w-full h-10 pl-9 pr-8 rounded-xl bg-slate-50 focus:bg-white border border-slate-200 focus:border-[#1D6AE5] text-[13.5px] text-slate-900 focus:outline-none transition-colors"
                        />
                        <button
                            v-if="search"
                            type="button"
                            @click="clearSearch"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-700 cursor-pointer"
                            title="Xóa tìm kiếm"
                        >
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <button
                        v-if="pendingSyncList.length > 0"
                        type="button"
                        :disabled="isSyncingPending"
                        @click="syncPendingVps(true)"
                        class="h-10 inline-flex items-center gap-1.5 px-3.5 rounded-xl bg-blue-50 hover:bg-blue-100 border border-blue-200 text-[#1D6AE5] text-[13px] font-bold shrink-0 whitespace-nowrap transition-colors cursor-pointer disabled:opacity-70"
                        title="Tự động đồng bộ IP và trạng thái mỗi 6 giây"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncingPending }" />
                        <span>{{ isSyncingPending ? 'Đang đồng bộ...' : `Đồng bộ IP (${pendingSyncList.length})` }}</span>
                    </button>

                    <Link
                        href="/store"
                        class="h-10 inline-flex items-center gap-1.5 px-4 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-[13px] font-bold shrink-0 whitespace-nowrap transition-colors"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Mua VPS mới</span>
                    </Link>
                </div>
            </div>

            <!-- Thanh thao tác hàng loạt khi chọn nhiều VPS -->
            <div
                v-if="selectedIds.length > 0"
                class="rounded-xl px-4 py-3 bg-[#0B1437] text-white border border-slate-800 shadow-sm flex flex-wrap items-center justify-between gap-3"
            >
                <div class="text-[13px] font-semibold">
                    Đã chọn <strong class="font-mono-num text-sky-300">{{ selectedIds.length }}</strong> máy chủ trên trang này
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="copySelectedCredentials"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer"
                    >
                        <ClipboardList class="w-3.5 h-3.5" />
                        <span>Copy tất cả (IP|User|Pass)</span>
                    </button>
                    <button
                        type="button"
                        @click="exportSelectedTxt"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold cursor-pointer"
                    >
                        <FileDown class="w-3.5 h-3.5" />
                        <span>Xuất file .TXT</span>
                    </button>
                    <button
                        type="button"
                        @click="selectedIds = []"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white cursor-pointer"
                    >
                        Bỏ chọn
                    </button>
                </div>
            </div>

            <!-- Trạng thái trống -->
            <div v-if="instances.data.length === 0" class="surface-card p-12 text-center space-y-3">
                <p class="text-base font-extrabold text-[#0B1437]">Không tìm thấy máy chủ nào trong mục này</p>
                <p class="text-[13.5px] text-slate-500">
                    Triển khai máy chủ ảo Cloud VPS SSD Việt Nam tự động chỉ trong 2 – 5 phút.
                </p>
                <div class="pt-1 flex items-center justify-center gap-2.5">
                    <button
                        v-if="search || filters.status !== 'active'"
                        type="button"
                        @click="
                            search = '';
                            applyFilter('all');
                        "
                        class="px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer"
                    >
                        Xem tất cả máy chủ
                    </button>
                    <Link
                        href="/store"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Khởi tạo Cloud VPS mới</span>
                    </Link>
                </div>
            </div>

            <template v-else>
                <!-- BẢNG DESKTOP ĐẦY ĐỦ THÔNG TIN TRUY CẬP NHANH -->
                <div class="hidden lg:block surface-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600">
                                    <th class="py-3.5 pl-3.5 pr-1.5 w-9">
                                        <input
                                            type="checkbox"
                                            :checked="isAllSelected"
                                            @change="toggleSelectAll"
                                            class="w-4 h-4 rounded border-slate-300 text-[#1D6AE5] cursor-pointer"
                                        />
                                    </th>
                                    <th class="py-3.5 px-3">Máy chủ &amp; Hệ điều hành</th>
                                    <th class="py-3.5 px-3">IPv4 &amp; Cấu hình</th>
                                    <th class="py-3.5 px-3 w-[216px] min-w-[216px]">Truy cập nhanh</th>
                                    <th class="py-3.5 px-3">Thời hạn</th>
                                    <th class="py-3.5 px-2.5">Trạng thái</th>
                                    <th class="py-3.5 pl-2 pr-3.5 text-right">Quản trị</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-200/80 text-[13px]">
                                <tr
                                    v-for="v in instances.data"
                                    :key="v.id"
                                    class="hover:bg-blue-50/35 transition-colors group"
                                >
                                    <td class="py-3.5 pl-3.5 pr-1.5 align-middle">
                                        <input
                                            type="checkbox"
                                            :checked="selectedIds.includes(v.id)"
                                            @change="toggleRowSelect(v.id)"
                                            class="w-4 h-4 rounded border-slate-300 text-[#1D6AE5] cursor-pointer"
                                        />
                                    </td>

                                    <!-- Máy chủ & OS -->
                                    <td class="py-3.5 px-3 align-middle">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-lg bg-slate-100 border border-slate-200/80 flex items-center justify-center shrink-0">
                                                <OsIcon :distro="v.os_distro" :size="18" />
                                            </div>
                                            <div class="min-w-0">
                                                <Link
                                                    :href="`/vps/${v.id}`"
                                                    class="text-[13.5px] font-extrabold text-[#0B1437] hover:text-[#1D6AE5] block truncate max-w-[165px]"
                                                >
                                                    {{ v.label }}
                                                </Link>
                                                <div class="text-xs text-slate-600 font-medium truncate max-w-[175px]">
                                                    {{ v.os_label }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- IP & Cấu hình -->
                                    <td class="py-3.5 px-3 align-middle whitespace-nowrap">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <button
                                                    v-if="v.public_ip"
                                                    type="button"
                                                    @click="copyText(v.public_ip, `IP ${v.public_ip}`)"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-blue-50 text-[#0B1437] hover:text-[#1D6AE5] font-mono-num font-bold text-[13px] cursor-pointer transition-colors"
                                                >
                                                    <span>{{ v.public_ip }}</span>
                                                    <Copy class="w-3 h-3 opacity-60" />
                                                </button>
                                                <span v-else class="text-xs italic text-amber-600 font-semibold">Đang cấp IP...</span>

                                                <button
                                                    v-if="v.public_ip && v.os_distro === 'windows' && v.state !== 'deleted'"
                                                    type="button"
                                                    @click="downloadRdpFile(v.public_ip, v.login_username, v.label)"
                                                    title="Tải file kết nối Remote Desktop (.rdp)"
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-blue-50 hover:bg-blue-100 text-[#1D6AE5] border border-blue-200/80 text-[10.5px] font-bold cursor-pointer"
                                                >
                                                    <Download class="w-3 h-3" />
                                                    <span>.RDP</span>
                                                </button>
                                            </div>

                                            <div class="font-mono-num text-xs font-semibold text-slate-600">
                                                {{ v.cpu }}C · {{ v.ram }}GB · {{ v.disk }}GB SSD
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Truy cập nhanh (User + Pass cố định độ rộng chống nhảy dòng) -->
                                    <td class="py-3.5 px-3 align-middle w-[216px] min-w-[216px]">
                                        <div class="space-y-1.5 text-xs">
                                            <div class="flex items-center justify-between gap-1.5">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    <span class="text-xs font-bold uppercase text-slate-600 w-9 shrink-0">User</span>
                                                    <code class="font-mono font-bold text-slate-800 truncate max-w-[110px]">
                                                        {{ v.login_username }}
                                                    </code>
                                                </div>
                                                <div class="flex items-center gap-1 shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="copyText(v.login_username, 'Username')"
                                                        class="p-1 rounded hover:bg-slate-100 text-slate-400 hover:text-[#1D6AE5] cursor-pointer"
                                                        title="Sao chép Username"
                                                    >
                                                        <Copy class="w-3.5 h-3.5" />
                                                    </button>
                                                    <button
                                                        v-if="v.public_ip && v.root_password"
                                                        type="button"
                                                        @click="copyText(formatFullVpsCredential(v), 'IP|User|Pass')"
                                                        class="px-1.5 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-[10px] font-bold text-slate-700 cursor-pointer"
                                                        title="Sao chép nhanh IP|User|Pass"
                                                    >
                                                        Full
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between gap-1.5">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    <span class="text-xs font-bold uppercase text-slate-600 w-9 shrink-0">Pass</span>
                                                    <code
                                                        v-if="v.root_password"
                                                        class="font-mono font-bold text-slate-800 w-[98px] truncate inline-block"
                                                    >
                                                        {{ revealedIds[v.id] ? v.root_password : '••••••••••' }}
                                                    </code>
                                                    <span v-else class="text-slate-500 font-bold">—</span>
                                                </div>
                                                <div v-if="v.root_password" class="flex items-center gap-1 shrink-0">
                                                    <button
                                                        type="button"
                                                        @click="toggleReveal(v.id)"
                                                        class="p-1 rounded hover:bg-slate-100 text-slate-400 hover:text-slate-700 cursor-pointer"
                                                        :title="revealedIds[v.id] ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                                                    >
                                                        <EyeOff v-if="revealedIds[v.id]" class="w-3.5 h-3.5" />
                                                        <Eye v-else class="w-3.5 h-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="copyText(v.root_password, 'Mật khẩu')"
                                                        class="p-1 rounded hover:bg-slate-100 text-slate-400 hover:text-[#1D6AE5] cursor-pointer"
                                                        title="Sao chép Mật khẩu"
                                                    >
                                                        <Copy class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Thời hạn -->
                                    <td class="py-3.5 px-3 align-middle whitespace-nowrap">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono-num font-bold text-slate-900 text-[12.5px]">
                                                    {{ v.expires_date || v.expires_at || '—' }}
                                                </span>
                                                <span
                                                    v-if="expiryBadge(v)"
                                                    class="px-1.5 py-0.5 rounded border text-[10.5px] font-bold"
                                                    :class="expiryBadge(v)?.cls"
                                                >
                                                    {{ expiryBadge(v)?.text }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-slate-600 font-medium">
                                                {{ v.billing_cycle_label }} · <span class="font-mono-num font-semibold text-slate-700">{{ formatVnd(v.paid_amount) }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Trạng thái & Tự gia hạn -->
                                    <td class="py-3.5 px-2.5 align-middle whitespace-nowrap">
                                        <div class="flex flex-col items-start gap-1.5">
                                            <StatusBadge :state="v.state" :label="v.status" />
                                            <AutoRenewSwitch
                                                v-if="v.state !== 'deleted'"
                                                :model-value="!!v.is_auto_renew"
                                                @toggle="toggleAutoRenew(v)"
                                            />
                                        </div>
                                    </td>

                                    <!-- Quản trị -->
                                    <td class="py-3.5 pl-2 pr-3.5 align-middle text-right whitespace-nowrap">
                                        <Link
                                            :href="`/vps/${v.id}`"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-[#0B1437] hover:text-white hover:border-[#0B1437] text-slate-800 text-xs font-bold transition-colors"
                                        >
                                            <span>Chi tiết</span>
                                            <ChevronRight class="w-3.5 h-3.5" />
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- THẺ MOBILE / TABLET TỐI ƯU ĐẦY ĐỦ THAO TÁC -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 lg:hidden">
                    <div
                        v-for="v in instances.data"
                        :key="'mv_' + v.id"
                        class="surface-card p-4 space-y-3.5 text-[13px]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <OsIcon :distro="v.os_distro" :size="22" />
                                <div class="min-w-0">
                                    <Link :href="`/vps/${v.id}`" class="font-extrabold text-[14.5px] text-[#0B1437] block truncate">
                                        {{ v.label }}
                                    </Link>
                                    <div class="text-xs text-slate-600 font-medium truncate">{{ v.os_label }}</div>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1.5 shrink-0">
                                <StatusBadge :state="v.state" :label="v.status" />
                                <AutoRenewSwitch
                                    v-if="v.state !== 'deleted'"
                                    :model-value="!!v.is_auto_renew"
                                    @toggle="toggleAutoRenew(v)"
                                />
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 font-medium">IPv4:</span>
                                <button
                                    v-if="v.public_ip"
                                    type="button"
                                    @click="copyText(v.public_ip, 'IP')"
                                    class="inline-flex items-center gap-1 font-mono-num font-bold text-[#0B1437]"
                                >
                                    <span>{{ v.public_ip }}</span>
                                    <Copy class="w-3 h-3 text-slate-400" />
                                </button>
                                <span v-else class="italic text-amber-600">Đang cấp IP...</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 font-medium">Tài khoản:</span>
                                <span class="font-mono font-bold text-slate-800">{{ v.login_username }}</span>
                            </div>

                            <div v-if="v.root_password" class="flex items-center justify-between">
                                <span class="text-slate-600 font-medium">Mật khẩu:</span>
                                <div class="flex items-center gap-1.5">
                                    <code class="font-mono font-bold text-slate-800 max-w-[130px] truncate">
                                        {{ revealedIds[v.id] ? v.root_password : '••••••••••' }}
                                    </code>
                                    <button type="button" @click="toggleReveal(v.id)" class="text-slate-400">
                                        <EyeOff v-if="revealedIds[v.id]" class="w-3.5 h-3.5" />
                                        <Eye v-else class="w-3.5 h-3.5" />
                                    </button>
                                    <button type="button" @click="copyText(v.root_password, 'Mật khẩu')" class="text-slate-400">
                                        <Copy class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-600 font-mono-num">
                            <span class="inline-flex items-center gap-1">
                                <Cpu class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                {{ v.cpu }}C · {{ v.ram }}G · {{ v.disk }}G
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <Calendar class="w-3.5 h-3.5 text-slate-400" />
                                {{ v.expires_date || v.expires_at || '—' }}
                            </span>
                        </div>

                        <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between gap-2">
                            <button
                                v-if="v.public_ip && v.root_password"
                                type="button"
                                @click="copyText(formatFullVpsCredential(v), 'IP|User|Pass')"
                                class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700"
                            >
                                Copy IP|User|Pass
                            </button>
                            <span v-else />

                            <Link
                                :href="`/vps/${v.id}`"
                                class="inline-flex items-center gap-1 px-4 py-1.5 rounded-lg bg-[#0B1437] text-white text-xs font-bold"
                            >
                                <span>Chi tiết</span>
                                <ChevronRight class="w-3.5 h-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>
            </template>

            <PaginationBar :links="instances.links" />
        </div>
    </ClientLayout>
</template>
