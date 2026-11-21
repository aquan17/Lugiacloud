<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { formatVnd } from '@/Composables/useFormat';
import { Ticket, ChevronDown, Check, X } from 'lucide-vue-next';

export interface ClaimedVoucherOption {
    id: number;
    code: string;
    discount_label: string;
    min_order_amount: number;
    max_discount_amount: number | null;
    max_months?: number | null;
    is_welcome?: boolean;
    expires_display?: string | null;
    description?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        appliedCode?: string;
        vouchers?: ClaimedVoucherOption[];
        orderAmount?: number;
        currentMonths?: number;
        disabled?: boolean;
        loading?: boolean;
        placeholder?: string;
        couponFeedback?: {
            valid: boolean;
            message: string;
            code?: string;
        } | null;
    }>(),
    {
        appliedCode: '',
        vouchers: () => [],
        orderAmount: 0,
        currentMonths: 1,
        disabled: false,
        loading: false,
        placeholder: 'Nhập mã giảm giá...',
        couponFeedback: null,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', val: string): void;
    (e: 'apply', code: string): void;
}>();

const rootRef = ref<HTMLElement | null>(null);
const isOpen = ref(false);

const hasVouchers = computed(() => props.vouchers && props.vouchers.length > 0);
const normalizedInput = computed(() => (props.modelValue || '').trim().toUpperCase());
const normalizedApplied = computed(() => (props.appliedCode || '').trim().toUpperCase());
const canApply = computed(
    () =>
        !props.disabled &&
        !props.loading &&
        normalizedInput.value.length > 0 &&
        normalizedInput.value !== normalizedApplied.value
);

const enrichedVouchers = computed(() => {
    const q = (props.modelValue || '').trim().toUpperCase();
    const amount = Number(props.orderAmount || 0);

    return (props.vouchers || [])
        .map((v) => {
            const minReq = Number(v.min_order_amount || 0);
            const shortage = minReq > 0 && amount < minReq ? minReq - amount : 0;
            const exceedsMonths = Boolean(v.max_months && Number(props.currentMonths || 1) > Number(v.max_months));
            const eligible = shortage === 0 && !exceedsMonths;
            const ineligibleReason = exceedsMonths
                ? `Chỉ áp dụng gói ≤ ${v.max_months} tháng`
                : shortage > 0
                  ? `Cần thêm ${formatVnd(shortage)} để dùng mã này`
                  : '';
            return {
                ...v,
                eligible,
                shortage,
                ineligibleReason,
                isApplied: props.appliedCode?.toUpperCase() === v.code.toUpperCase(),
            };
        })
        .filter((v) => {
            // Nếu ô đang trống hoặc trùng khớp 1 mã đã chọn -> show full danh sách để đổi dễ dàng
            if (!q || props.vouchers.some((x) => x.code.toUpperCase() === q)) {
                return true;
            }
            return v.code.toUpperCase().includes(q) || (v.description || '').toUpperCase().includes(q);
        })
        .sort((a, b) => Number(b.eligible) - Number(a.eligible));
});

const eligibleCount = computed(() =>
    (props.vouchers || []).filter(
        (v) =>
            Number(props.orderAmount || 0) >= Number(v.min_order_amount || 0) &&
            !(v.max_months && Number(props.currentMonths || 1) > Number(v.max_months))
    ).length
);

function onInput(e: Event) {
    const val = (e.target as HTMLInputElement).value.toUpperCase();
    emit('update:modelValue', val);
    if (hasVouchers.value && !props.disabled) {
        isOpen.value = true;
    }
}

function handleApply() {
    if (!canApply.value) return;
    isOpen.value = false;
    emit('apply', normalizedInput.value);
}

function clearCoupon() {
    emit('update:modelValue', '');
    emit('apply', '');
}

function selectVoucher(v: { code: string; eligible: boolean; isApplied: boolean }) {
    if (!v.eligible || props.disabled) return;
    isOpen.value = false;
    if (v.isApplied) {
        emit('update:modelValue', '');
        emit('apply', '');
    } else {
        emit('update:modelValue', v.code);
        emit('apply', v.code);
    }
}

function toggleDropdown() {
    if (props.disabled || !hasVouchers.value) return;
    isOpen.value = !isOpen.value;
}

function handleClickOutside(e: MouseEvent) {
    if (rootRef.value && !rootRef.value.contains(e.target as Node)) {
        isOpen.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside));
</script>

<template>
    <div ref="rootRef" class="relative space-y-1.5">
        <!-- Label Row + Ví Voucher Trigger -->
        <div class="flex items-center justify-between gap-2">
            <label class="text-xs font-semibold text-slate-600">Mã giảm giá</label>
            <button
                v-if="hasVouchers && !disabled"
                type="button"
                @click="toggleDropdown"
                class="inline-flex items-center gap-1 text-[11.5px] font-bold text-red-600 hover:text-red-700 cursor-pointer transition"
            >
                <Ticket class="w-3.5 h-3.5" />
                <span>Ví của bạn ({{ vouchers.length }})</span>
                <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="isOpen ? 'rotate-180' : ''" />
            </button>
        </div>

        <!-- Input + Apply Button -->
        <div class="flex gap-2">
            <div class="relative flex-1">
                <input
                    :value="modelValue"
                    type="text"
                    :disabled="disabled"
                    :placeholder="disabled ? 'Không dùng kèm Flash Sale' : placeholder"
                    class="w-full pl-3 pr-8 py-2 rounded-xl border border-slate-300 text-xs font-mono uppercase font-bold focus:outline-none focus:border-[#1D6AE5] disabled:bg-slate-100 disabled:text-slate-400"
                    @input="onInput"
                    @focus="hasVouchers && !disabled && (isOpen = true)"
                    @keydown.enter.prevent.stop="handleApply"
                    @keydown.escape="isOpen = false"
                />
                <button
                    v-if="(modelValue || appliedCode) && !disabled"
                    type="button"
                    @click="clearCoupon"
                    class="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 rounded-full text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer"
                    title="Bỏ chọn mã"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
            <button
                type="button"
                :disabled="!canApply"
                @click="handleApply"
                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-200 text-xs font-bold text-slate-800 cursor-pointer transition active:scale-95 disabled:opacity-45 disabled:cursor-not-allowed disabled:hover:bg-slate-100 disabled:active:scale-100 shrink-0"
            >
                {{ loading ? 'Đang kiểm tra...' : 'Áp dụng' }}
            </button>
        </div>

        <!-- Smart Dropdown Popover -->
        <div
            v-if="isOpen && hasVouchers && !disabled"
            class="absolute left-0 right-0 top-full mt-1.5 z-40 rounded-xl bg-white border border-slate-200 shadow-xl overflow-hidden"
        >
            <div class="px-3 py-2 bg-slate-50 border-b border-slate-100 flex items-center justify-between text-[11px]">
                <span class="font-bold text-slate-700">Voucher đã lưu ({{ eligibleCount }}/{{ vouchers.length }} dùng được)</span>
                <span class="text-slate-400">Hoặc nhập tay mã khác</span>
            </div>

            <div class="max-h-56 overflow-y-auto divide-y divide-slate-100">
                <div v-if="enrichedVouchers.length === 0" class="px-3 py-3 text-xs text-slate-500 text-center">
                    Không khớp voucher đã lưu — bấm <strong>Áp dụng</strong> để thử mã bạn vừa nhập.
                </div>

                <button
                    v-for="v in enrichedVouchers"
                    :key="v.id"
                    type="button"
                    :disabled="!v.eligible"
                    @click="selectVoucher(v)"
                    class="w-full px-3 py-2.5 text-left flex items-center justify-between gap-2.5 transition"
                    :class="
                        v.isApplied
                            ? 'bg-emerald-50/70'
                            : v.eligible
                              ? 'hover:bg-red-50/50 cursor-pointer'
                              : 'bg-slate-50/70 opacity-60 cursor-not-allowed'
                    "
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-mono text-xs font-black text-[#0B1437]">{{ v.code }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded text-[10.5px] font-extrabold"
                                :class="v.eligible ? (v.is_welcome ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white') : 'bg-slate-300 text-slate-700'"
                            >
                                {{ v.discount_label }}
                            </span>
                            <span
                                v-if="v.is_welcome"
                                class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold"
                            >
                                Khách mới
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-600 mt-0.5 truncate">
                            <span v-if="v.min_order_amount > 0">Đơn từ {{ formatVnd(v.min_order_amount) }}</span>
                            <span v-else>Mọi giá trị đơn</span>
                            <span v-if="v.max_discount_amount"> · Giảm tối đa {{ formatVnd(v.max_discount_amount) }}</span>
                            <span v-if="v.expires_display"> · HSD {{ v.expires_display }}</span>
                        </div>
                        <div v-if="!v.eligible" class="text-[10.5px] font-semibold text-amber-700 mt-0.5">
                            {{ v.ineligibleReason }}
                        </div>
                    </div>

                    <div class="shrink-0">
                        <span
                            v-if="v.isApplied"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-emerald-600 text-white text-[10.5px] font-bold"
                        >
                            <Check class="w-3 h-3" />
                            <span>Đang dùng</span>
                        </span>
                        <span
                            v-else-if="v.eligible"
                            class="inline-block px-2.5 py-1 rounded-md border border-red-200 bg-red-50 text-red-700 text-[11px] font-bold"
                        >
                            Chọn
                        </span>
                        <span
                            v-else
                            class="inline-block px-2 py-1 rounded-md bg-slate-200 text-slate-500 text-[10.5px] font-semibold"
                        >
                            Chưa đủ
                        </span>
                    </div>
                </button>
            </div>
        </div>

        <!-- Feedback line -->
        <p
            v-if="couponFeedback"
            class="text-xs font-bold"
            :class="couponFeedback.valid ? 'text-emerald-600' : 'text-rose-600'"
        >
            {{ couponFeedback.message }}
        </p>
    </div>
</template>
