<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { copyToClipboard, formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import { ArrowLeft, Copy, CheckCircle2, Loader2, ExternalLink } from 'lucide-vue-next';

const props = defineProps<{
    order: {
        id: number;
        code: string;
        amount: number;
        bonus_amount: number;
        bonus_percent: number;
        status: string;
        status_label: string;
        vietqr_url: string;
        checkout_url?: string | null;
        bank_id: string;
        account_no: string;
        account_name: string;
        created_at: string;
        paid_at?: string | null;
    };
}>();

const toast = useToast();
const currentStatus = ref(props.order.status);
const isPaid = ref(props.order.status === 'paid' || props.order.status === 'approved');
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (!isPaid.value && currentStatus.value === 'pending') {
        timer = setInterval(async () => {
            try {
                const { data } = await axios.get(`/topup/${props.order.id}/status`);
                currentStatus.value = data.status;
                if (data.paid) {
                    isPaid.value = true;
                    if (timer) clearInterval(timer);
                    toast.success('Đã nhận thanh toán! Số dư ví đã được cập nhật.');
                    router.reload({ only: ['auth', 'order'] });
                }
            } catch {}
        }, 4000);
    }
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

async function copyField(val: string, name: string) {
    if (await copyToClipboard(val)) toast.success(`Đã sao chép ${name}`);
}
</script>

<template>
    <ClientLayout :title="`Thanh toán lệnh nạp ${order.code}`">
        <Head :title="`Nạp tiền ${order.code}`" />

        <div class="max-w-3xl mx-auto space-y-5">
            <Link href="/topup" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-[#0B1437] transition">
                <ArrowLeft class="w-4 h-4" />
                <span>Quay lại danh sách lệnh nạp</span>
            </Link>

            <!-- Paid Confirmation Banner -->
            <div v-if="isPaid" class="surface-card p-8 text-center space-y-4 border border-emerald-300 bg-emerald-50/50 rounded-2xl shadow-xs">
                <CheckCircle2 class="w-12 h-12 text-emerald-600 mx-auto" />
                <div>
                    <h2 class="text-xl font-black text-[#0B1437]">Giao dịch nạp tiền đã hoàn tất thành công</h2>
                    <p class="text-xs text-slate-700 font-medium mt-1 leading-relaxed">
                        Số tiền <strong class="font-mono-num font-bold text-slate-900">{{ formatVnd(order.amount) }}</strong>
                        <template v-if="order.bonus_amount > 0">
                            cùng thưởng sự kiện <strong class="font-mono-num font-extrabold text-emerald-800">+{{ formatVnd(order.bonus_amount) }}</strong>
                        </template>
                        đã được cộng vào số dư ví của bạn.
                    </p>
                </div>
                <div class="pt-2 flex justify-center gap-3">
                    <Link href="/store" class="px-5 py-2.5 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold transition shadow-xs">
                        Khởi tạo dịch vụ ngay
                    </Link>
                    <Link :href="`/invoices/${order.code}`" class="px-5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-800 transition shadow-xs">
                        Xem biên nhận
                    </Link>
                </div>
            </div>

            <!-- Active QR Payment Card -->
            <div v-else class="surface-card p-6 sm:p-8 grid grid-cols-1 md:grid-cols-12 gap-6 items-center rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="md:col-span-5 flex flex-col items-center">
                    <div class="p-3.5 rounded-2xl border border-slate-200 bg-white shadow-2xs">
                        <img :src="order.vietqr_url" :alt="order.code" class="w-56 h-auto object-contain" />
                    </div>
                    <div class="mt-3.5 inline-flex items-center gap-2 text-xs font-bold text-[#1D6AE5] bg-blue-50 border border-blue-200 px-3 py-1 rounded-full">
                        <Loader2 class="w-3.5 h-3.5 animate-spin" />
                        <span>Đang chờ đối soát tự động...</span>
                    </div>
                </div>

                <div class="md:col-span-7 space-y-4">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Thông tin chuyển khoản</span>
                        <StatusBadge :state="currentStatus" :label="order.status_label" />
                    </div>

                    <div class="divide-y divide-slate-200/80 border border-slate-200 rounded-xl bg-slate-50/70 text-xs">
                        <div class="p-3 flex items-center justify-between">
                            <span class="text-slate-600 font-medium">Ngân hàng thụ hưởng</span>
                            <span class="font-extrabold text-[#0B1437]">{{ order.bank_id }}</span>
                        </div>
                        <div class="p-3 flex items-center justify-between">
                            <span class="text-slate-600 font-medium">Chủ tài khoản</span>
                            <span class="font-bold text-slate-900">{{ order.account_name }}</span>
                        </div>
                        <div class="p-3 flex items-center justify-between">
                            <span class="text-slate-600 font-medium">Số tài khoản</span>
                            <button @click="copyField(order.account_no, 'Số tài khoản')" class="inline-flex items-center gap-1.5 font-mono-num font-bold text-[#0B1437] hover:text-[#1D6AE5] px-2 py-0.5 rounded hover:bg-slate-200/60 transition cursor-pointer">
                                <span>{{ order.account_no }}</span>
                                <Copy class="w-3.5 h-3.5 text-slate-500" />
                            </button>
                        </div>
                        <div class="p-3 flex items-center justify-between">
                            <span class="text-slate-600 font-medium">Số tiền chính xác</span>
                            <button @click="copyField(String(order.amount), 'Số tiền')" class="inline-flex items-center gap-1.5 font-mono-num font-black text-[#1D6AE5] text-base hover:opacity-80 px-2 py-0.5 rounded hover:bg-slate-200/60 transition cursor-pointer">
                                <span>{{ formatVnd(order.amount) }}</span>
                                <Copy class="w-3.5 h-3.5 text-[#1D6AE5]" />
                            </button>
                        </div>
                        <div class="p-3 flex items-center justify-between bg-amber-50/80">
                            <span class="text-amber-900 font-bold">Nội dung chuyển khoản</span>
                            <button @click="copyField(order.code, 'Nội dung CK')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-200 text-amber-950 font-mono font-black text-xs hover:bg-amber-300 transition cursor-pointer shadow-2xs">
                                <span>{{ order.code }}</span>
                                <Copy class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-slate-600 font-medium leading-relaxed">
                        Quét trực tiếp mã QR bên trái để ứng dụng ngân hàng tự điền chính xác <strong>Số tiền</strong> và <strong>Nội dung chuyển khoản</strong>. Hệ thống khớp lệnh tự động trong 1 – 3 phút.
                    </p>

                    <a
                        v-if="order.checkout_url"
                        :href="order.checkout_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#1D6AE5] hover:underline"
                    >
                        <span>Mở cổng thanh toán PayOS trực tiếp</span>
                        <ExternalLink class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
