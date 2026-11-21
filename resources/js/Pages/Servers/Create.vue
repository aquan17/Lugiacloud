<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { formatVnd } from '@/Composables/useFormat';
import { ArrowLeft, Cpu, HardDrive, Network, ShieldCheck, Info, Wallet, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    server: any;
    balance: number;
}>();

const cycleList = computed(() => Object.values(props.server.cycles || {}) as Array<any>);

const form = useForm({
    plan_slug: props.server.slug,
    billing_cycle: cycleList.value[0]?.key || 'monthly',
    quantity: 1,
    label: '',
    os_note: 'Ubuntu 22.04 LTS / Proxmox VE / Windows Server (tùy chọn)',
});

const selectedCycle = computed(() => props.server.cycles?.[form.billing_cycle] || cycleList.value[0]);

const totalRental = computed(() => Number(selectedCycle.value?.final_price || 0) * Number(form.quantity || 1));
const totalSetup = computed(() => Number(props.server.setup_fee || 0) * Number(form.quantity || 1));
const grandTotal = computed(() => totalRental.value + totalSetup.value);
const isEnoughBalance = computed(() => props.balance >= grandTotal.value);

function submit() {
    if (!isEnoughBalance.value || form.processing) return;
    form.post('/servers/store');
}
</script>

<template>
    <ClientLayout :title="`Đặt thuê ${server.name}`">
        <Head :title="`Đặt thuê ${server.name}`" />

        <div class="space-y-6">
            <Link href="/servers/store" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-[#0B1437]">
                <ArrowLeft class="w-4 h-4" />
                <span>Quay lại Danh sách Máy chủ vật lý</span>
            </Link>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Configuration Column -->
                <div class="lg:col-span-2 space-y-5">
                    <!-- Hardware Summary -->
                    <div class="surface-card p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h1 class="text-xl font-black text-[#0B1437]">{{ server.name }}</h1>
                                <p class="text-xs text-slate-500 font-medium mt-1">Datacenter: {{ server.config.datacenter || 'HCM / HN' }} · {{ server.config.ip_count || 1 }} IPv4 riêng</p>
                            </div>
                            <span v-if="server.badge" class="px-2.5 py-1 rounded-lg bg-blue-50 border border-blue-200 text-[#1D6AE5] text-xs font-bold">
                                {{ server.badge }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <div class="text-[10.5px] font-bold text-slate-500 uppercase flex items-center gap-1"><Cpu class="w-3 h-3 text-[#1D6AE5]" />CPU</div>
                                <div class="font-bold text-[#0B1437] mt-1">{{ server.config.cpu_cores }}C / {{ server.config.cpu_threads }}T</div>
                                <div class="text-[11px] text-slate-500 truncate mt-0.5" :title="server.config.cpu_model">{{ server.config.cpu_model }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <div class="text-[10.5px] font-bold text-slate-500 uppercase flex items-center gap-1"><HardDrive class="w-3 h-3 text-indigo-600" />RAM</div>
                                <div class="font-bold text-[#0B1437] mt-1">{{ server.config.ram_gb }} GB</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ server.config.ram_type }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <div class="text-[10.5px] font-bold text-slate-500 uppercase">Ổ cứng</div>
                                <div class="font-bold text-[#0B1437] mt-1">{{ server.config.storage }}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ server.config.raid || 'Non-RAID' }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <div class="text-[10.5px] font-bold text-slate-500 uppercase flex items-center gap-1"><Network class="w-3 h-3 text-emerald-600" />Mạng</div>
                                <div class="font-bold text-[#0B1437] mt-1">{{ server.config.port_speed }}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ server.config.ipmi ? 'Có IPMI' : server.config.traffic }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Billing Cycle Selection -->
                    <div class="surface-card p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
                        <h2 class="text-sm font-extrabold text-[#0B1437]">1. Chọn chu kỳ thanh toán</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
                            <button
                                v-for="c in cycleList"
                                :key="c.key"
                                type="button"
                                @click="form.billing_cycle = c.key"
                                class="p-3 rounded-xl border text-left transition cursor-pointer"
                                :class="
                                    form.billing_cycle === c.key
                                        ? 'border-[#1D6AE5] bg-blue-50/50 ring-1 ring-[#1D6AE5]'
                                        : 'border-slate-200 bg-white hover:border-slate-300'
                                "
                            >
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-xs font-extrabold text-[#0B1437]">{{ c.label }}</span>
                                    <span v-if="c.save_percent > 0" class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-black">
                                        -{{ c.save_percent }}%
                                    </span>
                                </div>
                                <div class="font-mono-num text-xs font-black text-[#1D6AE5] mt-1.5">{{ formatVnd(c.final_price) }}</div>
                            </button>
                        </div>
                    </div>

                    <!-- Custom Label & Quantity -->
                    <div class="surface-card p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 text-xs">
                        <h2 class="text-sm font-extrabold text-[#0B1437]">2. Thông tin triển khai</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1.5">Tên gợi nhớ máy chủ (tùy chọn)</label>
                                <input
                                    v-model="form.label"
                                    type="text"
                                    placeholder="VD: Node-Proxmox-HCM-01"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:border-[#1D6AE5]"
                                />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1.5">Số lượng máy (tối đa 5)</label>
                                <input
                                    v-model.number="form.quantity"
                                    type="number"
                                    min="1"
                                    max="5"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 font-mono-num font-bold focus:outline-none focus:border-[#1D6AE5]"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1.5">Hệ điều hành / Yêu cầu cài đặt mong muốn (ghi chú kỹ thuật)</label>
                            <input
                                v-model="form.os_note"
                                type="text"
                                placeholder="VD: Proxmox VE 8, Ubuntu 22.04 LTS, Windows Server 2022..."
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:border-[#1D6AE5]"
                            />
                        </div>

                        <div class="p-3.5 rounded-xl bg-blue-50/70 border border-blue-200 text-slate-700 flex items-start gap-2.5 leading-relaxed">
                            <Info class="w-4 h-4 text-[#1D6AE5] shrink-0 mt-0.5" />
                            <div>
                                <strong>Quy trình bàn giao Máy chủ vật lý:</strong> Sau khi thanh toán, đơn hàng chuyển sang trạng thái <strong>Chờ bàn giao</strong> để kỹ thuật lắp đặt phần cứng tại Datacenter. Thời hạn sử dụng bắt đầu tính từ lúc bàn giao xong; toàn bộ IP, tài khoản quản trị &amp; IPMI sẽ hiển thị trực tiếp trong mục <strong>Máy chủ vật lý</strong>.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Order Summary -->
                <div class="lg:col-span-1">
                    <div class="surface-card p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 sticky top-20 text-xs">
                        <h3 class="text-sm font-extrabold text-[#0B1437]">Tóm tắt đơn hàng</h3>

                        <div class="space-y-2.5 pb-4 border-b border-slate-200">
                            <div class="flex justify-between">
                                <span class="text-slate-600">Cấu hình</span>
                                <span class="font-bold text-[#0B1437]">{{ server.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Chu kỳ thuê</span>
                                <span class="font-bold text-[#0B1437]">{{ selectedCycle?.label }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Số lượng</span>
                                <span class="font-mono-num font-bold text-[#0B1437]">{{ form.quantity }} máy</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Tiền thuê máy chủ</span>
                                <span class="font-mono-num font-bold text-[#0B1437]">{{ formatVnd(totalRental) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Phí khởi tạo (thu 1 lần)</span>
                                <span class="font-mono-num font-bold" :class="totalSetup > 0 ? 'text-amber-700' : 'text-emerald-700'">
                                    {{ totalSetup > 0 ? formatVnd(totalSetup) : 'Miễn phí (0đ)' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-baseline justify-between">
                            <span class="font-bold text-slate-700">Tổng thanh toán</span>
                            <span class="text-xl font-black font-mono-num text-[#1D6AE5]">{{ formatVnd(grandTotal) }}</span>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 text-slate-600 font-semibold">
                                <Wallet class="w-3.5 h-3.5 text-slate-400" />
                                <span>Số dư ví</span>
                            </span>
                            <span class="font-mono-num font-extrabold" :class="isEnoughBalance ? 'text-emerald-700' : 'text-rose-600'">
                                {{ formatVnd(balance) }}
                            </span>
                        </div>

                        <div v-if="!isEnoughBalance" class="space-y-2">
                            <p class="text-[11.5px] font-semibold text-rose-600">
                                Cần nạp thêm {{ formatVnd(grandTotal - balance) }} để đặt thuê cấu hình này.
                            </p>
                            <Link
                                href="/topup"
                                class="w-full inline-flex items-center justify-center py-2.5 px-4 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold transition"
                            >
                                Nạp số dư ngay
                            </Link>
                        </div>

                        <button
                            v-else
                            type="button"
                            :disabled="form.processing"
                            @click="submit"
                            class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold cursor-pointer transition disabled:opacity-60"
                        >
                            <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                            <span>{{ form.processing ? 'Đang tạo đơn hàng...' : 'Xác nhận thanh toán & Đặt máy' }}</span>
                        </button>

                        <div class="flex items-center gap-1.5 text-[11px] text-slate-500 justify-center">
                            <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
                            <span>Tự động hoàn 100% tiền về ví nếu hết máy</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
