<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { copyToClipboard, formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { Ticket, Copy, Check, Sparkles, Clock, Server, Globe, Gift, Loader2 } from 'lucide-vue-next';

interface OwnedVoucher {
    id: number;
    coupon_id: number;
    code: string;
    description: string;
    discount_label: string;
    applies_to: 'all' | 'vps' | 'proxy';
    scope_label: string;
    apply_on_renew: boolean;
    min_order_amount: number;
    max_discount_amount: number | null;
    max_months: number | null;
    is_welcome: boolean;
    status: 'available' | 'used' | 'expired';
    status_reason: string;
    claimed_at: string | null;
    used_at: string | null;
    expires_at: string | null;
    is_upcoming: boolean;
}

interface HuntableVoucher {
    id: number;
    code: string;
    description: string;
    discount_label: string;
    min_order_amount: number;
    max_discount_amount: number | null;
    applies_to: string;
    remaining_claims: number | null;
    claim_limit: number | null;
    ends_at: string | null;
}

const props = defineProps<{
    vouchers: OwnedVoucher[];
    counts: {
        available: number;
        used: number;
        expired: number;
    };
    huntableVouchers: HuntableVoucher[];
}>();

const toast = useToast();
const activeTab = ref<'available' | 'used' | 'expired'>('available');
const copiedCode = ref<string | null>(null);
const claimingId = ref<number | null>(null);

const filteredVouchers = computed(() =>
    props.vouchers.filter((v) => v.status === activeTab.value)
);

async function copyCode(code: string) {
    if (await copyToClipboard(code)) {
        copiedCode.value = code;
        toast.success(`Đã sao chép mã ${code}`);
        setTimeout(() => {
            if (copiedCode.value === code) copiedCode.value = null;
        }, 1800);
    }
}

function claimVoucher(v: HuntableVoucher) {
    if (claimingId.value !== null) return;
    claimingId.value = v.id;
    router.post(
        `/vouchers/${v.id}/claim`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                claimingId.value = null;
            },
        }
    );
}
</script>

<template>
    <ClientLayout title="Ví Voucher của tôi">
        <Head title="Ví Voucher ưu đãi" />

        <div class="space-y-6">
            <!-- Summary Strip -->
            <div class="surface-card grid grid-cols-3 divide-x divide-slate-200 rounded-2xl border border-slate-200/90 shadow-xs text-xs">
                <button
                    type="button"
                    @click="activeTab = 'available'"
                    class="p-4 sm:p-5 text-left transition cursor-pointer hover:bg-slate-50/70"
                    :class="activeTab === 'available' ? 'bg-blue-50/40' : ''"
                >
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Sẵn sàng dùng</div>
                    <div class="text-2xl font-black font-mono-num text-[#1D6AE5] mt-1.5">{{ counts.available }}</div>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'used'"
                    class="p-4 sm:p-5 text-left transition cursor-pointer hover:bg-slate-50/70"
                    :class="activeTab === 'used' ? 'bg-emerald-50/40' : ''"
                >
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Đã sử dụng</div>
                    <div class="text-2xl font-black font-mono-num text-emerald-700 mt-1.5">{{ counts.used }}</div>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'expired'"
                    class="p-4 sm:p-5 text-left transition cursor-pointer hover:bg-slate-50/70"
                    :class="activeTab === 'expired' ? 'bg-slate-100/70' : ''"
                >
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Hết hạn / Đã đóng</div>
                    <div class="text-2xl font-black font-mono-num text-slate-500 mt-1.5">{{ counts.expired }}</div>
                </button>
            </div>

            <!-- Tabs + Owned Vouchers Grid -->
            <div class="surface-card rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            @click="activeTab = 'available'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                            :class="activeTab === 'available' ? 'bg-[#0B1437] text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'"
                        >
                            Khả dụng ({{ counts.available }})
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'used'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                            :class="activeTab === 'used' ? 'bg-[#0B1437] text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'"
                        >
                            Đã sử dụng ({{ counts.used }})
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'expired'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                            :class="activeTab === 'expired' ? 'bg-[#0B1437] text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'"
                        >
                            Hết hạn ({{ counts.expired }})
                        </button>
                    </div>
                    <span class="text-[11.5px] text-slate-500 font-medium">
                        Bấm chọn trực tiếp mã trong Ví tại trang đặt mua VPS / Proxy
                    </span>
                </div>

                <div v-if="filteredVouchers.length === 0" class="p-10 text-center space-y-2">
                    <div class="w-11 h-11 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 mx-auto">
                        <Ticket class="w-5 h-5" />
                    </div>
                    <div class="text-sm font-bold text-[#0B1437]">
                        {{
                            activeTab === 'available'
                                ? 'Hiện chưa có voucher khả dụng trong ví'
                                : activeTab === 'used'
                                  ? 'Bạn chưa sử dụng voucher nào'
                                  : 'Không có voucher hết hạn'
                        }}
                    </div>
                    <p class="text-xs text-slate-500">
                        {{
                            activeTab === 'available'
                                ? 'Theo dõi các chương trình khuyến mãi hoặc săn mã đang mở bên dưới để nhận giảm giá ngay.'
                                : 'Các mã giảm giá sau khi áp dụng hoặc hết hiệu lực sẽ được lưu lịch sử tại đây.'
                        }}
                    </p>
                </div>

                <div v-else class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="v in filteredVouchers"
                        :key="v.id"
                        class="rounded-2xl border p-4 flex flex-col justify-between gap-3.5 transition"
                        :class="
                            v.status === 'available'
                                ? 'bg-white border-slate-200 hover:border-[#1D6AE5]/50 shadow-2xs'
                                : 'bg-slate-50/70 border-slate-200/80 opacity-75'
                        "
                    >
                        <div class="space-y-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="px-2 py-0.5 rounded-md text-xs font-extrabold"
                                        :class="
                                            v.status === 'available'
                                                ? v.is_welcome
                                                    ? 'bg-emerald-600 text-white'
                                                    : 'bg-[#1D6AE5] text-white'
                                                : 'bg-slate-300 text-slate-700'
                                        "
                                    >
                                        {{ v.discount_label }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 text-[11px] font-bold text-slate-700">
                                        {{ v.scope_label }}
                                    </span>
                                    <span
                                        v-if="v.is_welcome"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-[11px] font-bold text-emerald-700"
                                    >
                                        <Gift class="w-3 h-3" />
                                        <span>Thành viên mới</span>
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    @click="copyCode(v.code)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 font-mono text-xs font-black text-[#0B1437] cursor-pointer transition shrink-0"
                                    title="Sao chép mã giảm giá"
                                >
                                    <span>{{ v.code }}</span>
                                    <Check v-if="copiedCode === v.code" class="w-3.5 h-3.5 text-emerald-600" />
                                    <Copy v-else class="w-3.5 h-3.5 text-slate-400" />
                                </button>
                            </div>

                            <div class="text-xs font-semibold text-slate-800 leading-relaxed">
                                {{ v.description }}
                            </div>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11.5px] text-slate-500">
                                <span>
                                    {{ v.min_order_amount > 0 ? `Đơn từ ${formatVnd(v.min_order_amount)}` : 'Không yêu cầu đơn tối thiểu' }}
                                </span>
                                <span v-if="v.max_discount_amount">· Giảm tối đa {{ formatVnd(v.max_discount_amount) }}</span>
                                <span v-if="v.max_months">· Gói ≤ {{ v.max_months }} tháng</span>
                                <span v-if="v.apply_on_renew">· Dùng được khi gia hạn</span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-[11.5px]">
                            <div class="inline-flex items-center gap-1.5 text-slate-500 font-medium">
                                <Clock class="w-3.5 h-3.5 shrink-0" />
                                <span v-if="v.status === 'available'">
                                    {{ v.expires_at ? `HSD: ${v.expires_at}` : v.status_reason }}
                                </span>
                                <span v-else>{{ v.status_reason }}</span>
                            </div>

                            <div v-if="v.status === 'available' && !v.is_upcoming" class="flex items-center gap-1.5">
                                <Link
                                    v-if="v.applies_to === 'all' || v.applies_to === 'vps'"
                                    href="/store"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-[#0B1437] hover:bg-[#162354] text-white text-[11px] font-bold transition"
                                >
                                    <Server class="w-3 h-3" />
                                    <span>Dùng mua VPS</span>
                                </Link>
                                <Link
                                    v-if="v.applies_to === 'all' || v.applies_to === 'proxy'"
                                    href="/proxy/store"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-[#0B1437] text-[11px] font-bold transition"
                                >
                                    <Globe class="w-3 h-3 text-emerald-600" />
                                    <span>Dùng mua Proxy</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Huntable Vouchers Section -->
            <div v-if="huntableVouchers.length > 0" class="surface-card rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Sparkles class="w-4 h-4 text-[#1D6AE5]" />
                        <h2 class="text-xs font-extrabold uppercase tracking-wider text-[#0B1437]">Voucher đang mở săn thêm</h2>
                    </div>
                    <span class="text-[11px] font-semibold text-slate-500">Lưu miễn phí vào ví, dùng khi thanh toán</span>
                </div>

                <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="hv in huntableVouchers"
                        :key="hv.id"
                        class="rounded-2xl border border-slate-200 bg-white p-4 flex flex-col justify-between gap-3"
                    >
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="px-2 py-0.5 rounded-md bg-blue-50 border border-blue-200 text-[#1D6AE5] text-xs font-extrabold">
                                    {{ hv.discount_label }}
                                </span>
                                <span class="font-mono text-xs font-black text-[#0B1437]">{{ hv.code }}</span>
                            </div>
                            <p class="text-xs font-semibold text-slate-700 leading-snug">{{ hv.description }}</p>
                            <div class="text-[11px] text-slate-500 space-x-2">
                                <span v-if="hv.min_order_amount > 0">Đơn từ {{ formatVnd(hv.min_order_amount) }}</span>
                                <span v-else>Mọi đơn hàng</span>
                                <span v-if="hv.remaining_claims !== null">· Còn {{ hv.remaining_claims }} lượt</span>
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="claimingId === hv.id"
                            @click="claimVoucher(hv)"
                            class="w-full inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer transition disabled:opacity-60"
                        >
                            <Loader2 v-if="claimingId === hv.id" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ claimingId === hv.id ? 'Đang lưu...' : 'Lưu vào Ví Voucher' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
