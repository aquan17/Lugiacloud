<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
    isVpsOutOfStock: boolean;
    requireEmailVerification: boolean;
    depositConfig: {
        provider: string;
        bank_id: string;
        account_no: string;
        account_name: string;
        payos_configured: boolean;
    };
}>();

function toggleStock() {
    router.post('/admin/settings/toggle-vps-stock', {
        is_out_of_stock: !props.isVpsOutOfStock,
    });
}

function toggleEmailVerification() {
    router.post('/admin/settings/toggle-email-verification', {
        require_email_verification: !props.requireEmailVerification,
    });
}
</script>

<template>
    <AdminLayout title="Cấu hình Vận hành Hệ thống">
        <Head title="Cài đặt hệ thống" />

        <div class="max-w-3xl space-y-6">
            <div class="surface-card p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="space-y-1">
                    <h3 class="text-sm font-extrabold text-[#0B1437]">Trạng thái kho tài nguyên Cloud VPS</h3>
                    <p class="text-xs text-slate-600 font-medium leading-relaxed">
                        Khi bật chế độ tạm hết hàng, toàn bộ mua mới VPS (kể cả Flash Sale &amp; API Đại lý) và nâng cấp thêm CPU/RAM/SSD sẽ tạm khóa — khách vẫn gia hạn VPS hiện có và mua Proxy bình thường.
                    </p>
                </div>
                <button
                    type="button"
                    @click="toggleStock"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold shrink-0 cursor-pointer transition shadow-xs active:scale-95"
                    :class="
                        isVpsOutOfStock
                            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                            : 'bg-rose-600 text-white hover:bg-rose-700'
                    "
                >
                    {{ isVpsOutOfStock ? 'Đang khóa đơn mới — Bấm để Mở lại' : 'Đang mở bán — Bấm để Tạm khóa' }}
                </button>
            </div>

            <div class="surface-card p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="space-y-1">
                    <h3 class="text-sm font-extrabold text-[#0B1437]">Xác thực Email khi đăng ký tài khoản</h3>
                    <p class="text-xs text-slate-600 font-medium leading-relaxed">
                        Mặc định bật để chống tài khoản ảo. Khi nhà cung cấp email tạm hết hạn mức hoặc gặp sự cố, bạn có thể tạm tắt để khách đăng ký mới được kích hoạt ngay và vào thẳng Dashboard (không gửi mail xác thực).
                    </p>
                </div>
                <button
                    type="button"
                    @click="toggleEmailVerification"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold shrink-0 cursor-pointer transition shadow-xs active:scale-95"
                    :class="
                        requireEmailVerification
                            ? 'bg-amber-600 text-white hover:bg-amber-700'
                            : 'bg-emerald-600 text-white hover:bg-emerald-700'
                    "
                >
                    {{ requireEmailVerification ? 'Đang bật xác thực — Bấm để Tạm tắt' : 'Đang tắt xác thực — Bấm để Bật lại' }}
                </button>
            </div>

            <div class="surface-card p-6 space-y-4 text-xs rounded-2xl border border-slate-200/90 shadow-xs">
                <h3 class="text-sm font-extrabold text-[#0B1437]">Thông tin Cổng thanh toán đang áp dụng (`.env`)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50/80 border border-slate-200 rounded-xl p-4">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-600">Cổng thanh toán mặc định</div>
                        <div class="font-mono font-black text-slate-900 mt-1 uppercase text-sm">{{ depositConfig.provider }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-600">Kết nối PayOS Webhook</div>
                        <div class="font-bold mt-1 text-sm" :class="depositConfig.payos_configured ? 'text-emerald-700' : 'text-amber-700'">
                            {{ depositConfig.payos_configured ? 'Đã cấu hình khóa API' : 'Chạy VietQR thủ công' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-600">Ngân hàng &amp; Số tài khoản</div>
                        <div class="font-mono font-black text-slate-900 mt-1 text-sm">{{ depositConfig.bank_id }} — {{ depositConfig.account_no }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-600">Chủ tài khoản</div>
                        <div class="font-bold text-slate-900 mt-1 text-sm">{{ depositConfig.account_name }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
