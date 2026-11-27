<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { Search, Plus, Cpu, ArrowRight } from 'lucide-vue-next';

const props = defineProps<{
    servers: { data: Array<any>; links: Array<any> };
    filters: { tab: string; search: string };
    counts: { all: number; active: number; waiting: number; expired: number; deleted: number };
}>();

const search = ref(props.filters.search || '');
const tabs = [
    { key: 'active', label: 'Đang hoạt động' },
    { key: 'waiting', label: 'Chờ bàn giao' },
    { key: 'expired', label: 'Hết hạn' },
    { key: 'deleted', label: 'Đã hủy' },
    { key: 'all', label: 'Tất cả' },
] as const;

function switchTab(tab: string) {
    router.get('/servers', { tab, search: search.value || undefined }, { preserveState: true, replace: true });
}

function submitSearch() {
    router.get('/servers', { tab: props.filters.tab, search: search.value || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <ClientLayout title="Máy chủ vật lý (Dedicated Server)">
        <Head title="Máy chủ vật lý của tôi" />

        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
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

                <div class="flex items-center gap-2">
                    <form @submit.prevent="submitSearch" class="relative flex-1 sm:w-60">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Tìm tên máy, cấu hình, IP..."
                            class="w-full pl-8 pr-3 py-2 rounded-xl bg-white border border-slate-300 text-xs font-medium focus:outline-none focus:border-[#1D6AE5]"
                        />
                    </form>
                    <Link
                        href="/servers/store"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold whitespace-nowrap transition"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Thuê máy mới</span>
                    </Link>
                </div>
            </div>

            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div v-if="servers.data.length === 0" class="p-12 text-center space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 mx-auto">
                        <Cpu class="w-6 h-6" />
                    </div>
                    <div class="text-sm font-bold text-[#0B1437]">Chưa có Máy chủ vật lý nào trong mục này</div>
                    <p class="text-xs text-slate-500">Khởi tạo máy chủ Bare-Metal riêng 100% tài nguyên với Dual Xeon Gold &amp; 1Gbps Unmetered.</p>
                    <Link
                        href="/servers/store"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0B1437] text-white text-xs font-bold hover:bg-[#162354] transition"
                    >
                        <span>Xem bảng giá Máy chủ vật lý</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>

                <template v-else>
                    <!-- Mobile Card List -->
                    <div class="lg:hidden divide-y divide-slate-200/80">
                        <div v-for="s in servers.data" :key="`m-${s.id}`" class="p-4 space-y-3 bg-white text-xs">
                            <div class="flex items-start justify-between gap-2.5">
                                <div class="min-w-0">
                                    <Link :href="`/servers/${s.id}`" class="block font-extrabold text-[13px] text-[#0B1437] hover:text-[#1D6AE5] truncate transition-colors">
                                        {{ s.label }}
                                    </Link>
                                    <div class="text-[11px] text-slate-500 font-medium mt-0.5 truncate">{{ s.plan_name }} · {{ s.billing_cycle_label }}</div>
                                </div>
                                <StatusBadge :state="s.state" :label="s.status" class="shrink-0" />
                            </div>

                            <div class="rounded-xl bg-slate-50/90 border border-slate-200/70 p-2.5 space-y-2 text-[11px]">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cấu hình phần cứng</div>
                                    <div class="font-bold text-slate-800 mt-0.5">{{ s.config.cpu_cores }}C/{{ s.config.cpu_threads }}T · {{ s.config.ram_gb }}GB {{ s.config.ram_type }}</div>
                                    <div class="text-slate-500 mt-0.5">{{ s.config.storage }} · {{ s.config.port_speed }}</div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/70">
                                    <div class="min-w-0">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">IPv4</div>
                                        <div class="font-mono mt-0.5 truncate">
                                            <span v-if="s.public_ip" class="font-bold text-[#0B1437]">{{ s.public_ip }}</span>
                                            <span v-else class="text-slate-400 italic">Đang bàn giao</span>
                                        </div>
                                    </div>
                                    <div class="text-right min-w-0">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hết hạn</div>
                                        <div class="font-mono-num font-bold text-slate-700 mt-0.5">{{ s.expires_at || 'Tính khi bàn giao' }}</div>
                                    </div>
                                </div>
                            </div>

                            <Link
                                :href="`/servers/${s.id}`"
                                class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white hover:bg-blue-50 hover:border-blue-200 text-[#0B1437] hover:text-[#1D6AE5] font-bold transition"
                            >
                                <span>Quản lý máy chủ</span>
                                <ArrowRight class="w-3.5 h-3.5" />
                            </Link>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                                <th class="py-3 px-4">Máy chủ</th>
                                <th class="py-3 px-4">Cấu hình phần cứng</th>
                                <th class="py-3 px-4">IPv4 / Bàn giao</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4">Hết hạn</th>
                                <th class="py-3 px-4 text-right">Quản lý</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="s in servers.data" :key="s.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4">
                                    <Link :href="`/servers/${s.id}`" class="font-bold text-[#0B1437] hover:text-[#1D6AE5] transition-colors">
                                        {{ s.label }}
                                    </Link>
                                    <div class="text-[11px] text-slate-500 font-medium mt-0.5">{{ s.plan_name }} · {{ s.billing_cycle_label }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-slate-700 font-medium">
                                    <div>{{ s.config.cpu_cores }}C/{{ s.config.cpu_threads }}T · {{ s.config.ram_gb }}GB {{ s.config.ram_type }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">{{ s.config.storage }} · {{ s.config.port_speed }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-mono">
                                    <span v-if="s.public_ip" class="font-bold text-[#0B1437]">{{ s.public_ip }}</span>
                                    <span v-else class="text-slate-400 italic">Đang bàn giao</span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <StatusBadge :state="s.state" :label="s.status" />
                                </td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-600 font-medium">
                                    {{ s.expires_at || 'Tính khi bàn giao' }}
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <Link
                                        :href="`/servers/${s.id}`"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-blue-50 hover:border-blue-200 text-[#0B1437] hover:text-[#1D6AE5] font-bold transition"
                                    >
                                        <span>Chi tiết</span>
                                        <ArrowRight class="w-3 h-3" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </template>
            </div>

            <PaginationBar :links="servers.links" />
        </div>
    </ClientLayout>
</template>
