<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatVnd } from '@/Composables/useFormat';
import { Cpu, HardDrive, Network, ShieldCheck, CheckCircle2, ArrowRight, Server, MapPin } from 'lucide-vue-next';

const props = defineProps<{
    servers: Array<any>;
}>();

const page = usePage();
const isAuthenticated = computed(() => Boolean((page.props.auth as any)?.user));
</script>

<template>
    <component :is="isAuthenticated ? ClientLayout : PublicLayout" title="Thuê Máy chủ vật lý (Dedicated Server)">
        <Head title="Máy chủ vật lý (Dedicated Server)" />

        <div :class="isAuthenticated ? 'space-y-6' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8'">
            <!-- Header Banner -->
            <div class="surface-card p-6 sm:p-8 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 border border-blue-200 text-[#1D6AE5] text-[11px] font-bold uppercase tracking-wider">
                        <Cpu class="w-3.5 h-3.5" />
                        <span>Bare-Metal Dedicated Server · Việt Nam</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-[#0B1437] tracking-tight">
                        Máy chủ vật lý riêng 100% tài nguyên
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Hiệu năng phần cứng tối đa với CPU Intel Xeon Enterprise, RAM DDR4 ECC, băng thông 1 Gbps không giới hạn lưu lượng, hỗ trợ quản trị từ xa <strong>IPMI / iDRAC</strong> tại Datacenter HCM &amp; Hà Nội.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs shrink-0">
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="font-bold text-[#0B1437]">Tài nguyên độc lập</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Không chia sẻ CPU/RAM</div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="font-bold text-[#0B1437]">Có IPMI / KVM</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Cài OS &amp; reboot chủ động</div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="servers.length === 0" class="surface-card p-12 rounded-2xl border border-slate-200/90 text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 mx-auto">
                    <Server class="w-6 h-6" />
                </div>
                <div class="text-sm font-bold text-[#0B1437]">Hiện chưa có cấu hình Máy chủ vật lý mở bán</div>
                <p class="text-xs text-slate-500">Vui lòng liên hệ đội ngũ kỹ thuật để được tư vấn cấu hình riêng theo nhu cầu.</p>
            </div>

            <!-- Server Cards Grid -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div
                    v-for="s in servers"
                    :key="s.slug"
                    class="surface-card rounded-2xl border p-6 flex flex-col justify-between gap-5 transition hover:border-[#1D6AE5]/60 shadow-xs"
                    :class="s.is_featured ? 'border-[#1D6AE5] ring-1 ring-[#1D6AE5]/15' : 'border-slate-200/90'"
                >
                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h2 class="text-lg font-black text-[#0B1437]">{{ s.name }}</h2>
                                    <span
                                        v-if="s.badge"
                                        class="px-2 py-0.5 rounded-md bg-blue-50 border border-blue-200 text-[#1D6AE5] text-[10.5px] font-bold"
                                    >
                                        {{ s.badge }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 font-medium mt-1">
                                    <MapPin class="w-3.5 h-3.5 text-slate-400" />
                                    <span>Datacenter: {{ s.config.datacenter || 'HCM / HN' }}</span>
                                    <span>·</span>
                                    <span>{{ s.config.ip_count || 1 }} IPv4 riêng</span>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-xl sm:text-2xl font-black font-mono-num text-[#0B1437]">
                                    {{ formatVnd(s.monthly_price) }}
                                </div>
                                <div class="text-[11px] text-slate-500 font-semibold">/ tháng</div>
                                <div v-if="s.setup_fee > 0" class="text-[10.5px] text-amber-700 font-bold mt-0.5">
                                    Phí khởi tạo: {{ formatVnd(s.setup_fee) }}
                                </div>
                                <div v-else class="text-[10.5px] text-emerald-700 font-bold mt-0.5">
                                    Miễn phí khởi tạo
                                </div>
                            </div>
                        </div>

                        <!-- Hardware Specs Table -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <div class="text-[10.5px] font-bold uppercase text-slate-500 flex items-center gap-1">
                                    <Cpu class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                    <span>Vi xử lý (CPU)</span>
                                </div>
                                <div class="font-bold text-[#0B1437] mt-1">{{ s.config.cpu_model }}</div>
                                <div class="text-[11px] text-slate-600 font-mono-num mt-0.5">
                                    {{ s.config.cpu_cores }} Cores / {{ s.config.cpu_threads }} Threads
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <div class="text-[10.5px] font-bold uppercase text-slate-500 flex items-center gap-1">
                                    <HardDrive class="w-3.5 h-3.5 text-indigo-600" />
                                    <span>Bộ nhớ &amp; Lưu trữ</span>
                                </div>
                                <div class="font-bold text-[#0B1437] mt-1">
                                    {{ s.config.ram_gb }} GB {{ s.config.ram_type }}
                                </div>
                                <div class="text-[11px] text-slate-600 mt-0.5">
                                    {{ s.config.storage }}<span v-if="s.config.raid"> ({{ s.config.raid }})</span>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <div class="text-[10.5px] font-bold uppercase text-slate-500 flex items-center gap-1">
                                    <Network class="w-3.5 h-3.5 text-emerald-600" />
                                    <span>Kết nối mạng</span>
                                </div>
                                <div class="font-bold text-[#0B1437] mt-1">{{ s.config.port_speed }}</div>
                                <div class="text-[11px] text-slate-600 mt-0.5">Lưu lượng: {{ s.config.traffic }}</div>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <div class="text-[10.5px] font-bold uppercase text-slate-500 flex items-center gap-1">
                                    <ShieldCheck class="w-3.5 h-3.5 text-amber-600" />
                                    <span>Quản trị phần cứng</span>
                                </div>
                                <div class="font-bold text-[#0B1437] mt-1">
                                    {{ s.config.ipmi ? 'Hỗ trợ IPMI / iDRAC' : 'Hỗ trợ qua Ticket 24/7' }}
                                </div>
                                <div class="text-[11px] text-slate-600 mt-0.5">
                                    {{ s.config.gpu_model ? `GPU: ${s.config.gpu_model}` : 'Bàn giao & cài OS theo yêu cầu' }}
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px] text-slate-600">
                            <span class="inline-flex items-center gap-1">
                                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                                <span>Hỗ trợ thuê 1–36 tháng (giảm giá chu kỳ dài)</span>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                                <span>Bàn giao trọn bộ IP + IPMI trực tiếp trên Dashboard</span>
                            </span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200/80 flex items-center justify-between gap-3">
                        <div class="text-xs text-slate-500">
                            <span v-if="s.cycles?.quarterly">
                                Từ 3 tháng: <strong class="font-mono-num text-[#0B1437]">{{ formatVnd(s.cycles.quarterly.final_price) }}</strong>
                            </span>
                        </div>
                        <Link
                            :href="isAuthenticated ? `/servers/store/${s.slug}` : `/login?redirect=${encodeURIComponent('/servers/store/' + s.slug)}`"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold transition shadow-xs"
                        >
                            <span>Cấu hình &amp; Đặt thuê</span>
                            <ArrowRight class="w-3.5 h-3.5" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
