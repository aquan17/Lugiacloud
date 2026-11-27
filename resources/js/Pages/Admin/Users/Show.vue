<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatVnd } from '@/Composables/useFormat';
import { askConfirm } from '@/Composables/useConfirmAction';
import { ArrowLeft, Trash2, Server, Globe, TrendingUp, Wallet, ScrollText } from 'lucide-vue-next';

const props = defineProps<{
    member: any;
    vpsInstances: Array<any>;
    proxies: Array<any>;
    transactions: Array<any>;
}>();

const balanceForm = useForm({
    amount: 50000,
    note: '',
});

const roleForm = useForm({
    discount_percent: props.member.discount_percent,
    proxy_discount_percent: props.member.proxy_discount_percent,
    role: props.member.role,
});
</script>

<template>
    <AdminLayout :title="`Hồ sơ thành viên: ${member.email}`">
        <Head :title="member.email" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Link href="/admin/users" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-[#0B1437]">
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Danh sách người dùng</span>
                </Link>
                <button
                    type="button"
                    @click="
                        askConfirm({
                            title: 'Xóa tài khoản người dùng',
                            message: `Bạn có chắc muốn xóa tài khoản ${member.email}? Thao tác này không thể hoàn tác.`,
                            confirmText: 'Xóa tài khoản',
                            onConfirm: () => router.delete(`/admin/users/${member.id}`),
                        })
                    "
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-rose-200 text-rose-600 text-xs font-semibold hover:bg-rose-50 cursor-pointer"
                >
                    <Trash2 class="w-3.5 h-3.5" />
                    <span>Xóa người dùng</span>
                </button>
            </div>

            <!-- Thanh liên kết nhanh chéo giữa các trang quản trị của khách này -->
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <Link
                    :href="`/admin/instances?search=${encodeURIComponent(member.email)}`"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 font-bold text-slate-700 hover:text-[#1D6AE5] transition shadow-2xs"
                >
                    <Server class="w-3.5 h-3.5 text-[#1D6AE5]" />
                    <span>Cloud VPS ({{ vpsInstances.length }})</span>
                </Link>
                <Link
                    :href="`/admin/proxies?search=${encodeURIComponent(member.email)}`"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 font-bold text-slate-700 hover:text-[#1D6AE5] transition shadow-2xs"
                >
                    <Globe class="w-3.5 h-3.5 text-indigo-600" />
                    <span>Proxy IPv4 ({{ proxies.length }})</span>
                </Link>
                <Link
                    :href="`/admin/revenue?search=${encodeURIComponent(member.email)}`"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 font-bold text-slate-700 hover:text-[#1D6AE5] transition shadow-2xs"
                >
                    <TrendingUp class="w-3.5 h-3.5 text-emerald-600" />
                    <span>Đơn hàng &amp; Doanh thu</span>
                </Link>
                <Link
                    :href="`/admin/topups?search=${encodeURIComponent(member.email)}`"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 font-bold text-slate-700 hover:text-[#1D6AE5] transition shadow-2xs"
                >
                    <Wallet class="w-3.5 h-3.5 text-amber-600" />
                    <span>Lịch sử nạp tiền</span>
                </Link>
                <Link
                    :href="`/admin/ledger?search=${encodeURIComponent(member.email)}`"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 font-bold text-slate-700 hover:text-[#1D6AE5] transition shadow-2xs"
                >
                    <ScrollText class="w-3.5 h-3.5 text-slate-600" />
                    <span>Sổ cái ví (Full Ledger)</span>
                </Link>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Balance Adjustment via WalletService -->
                <form
                    @submit.prevent="balanceForm.post(`/admin/users/${member.id}/balance`, { preserveScroll: true, onSuccess: () => balanceForm.reset('note') })"
                    class="surface-card p-6 space-y-4 text-xs"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-[#0B1437]">Điều chỉnh số dư ví (Có lưu sổ cái)</h3>
                        <span class="font-mono-num text-base font-extrabold text-[#1D6AE5]">{{ formatVnd(member.balance) }}</span>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Số tiền (+ để cộng, - để trừ VNĐ)</label>
                        <input v-model.number="balanceForm.amount" type="number" required class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Lý do hiển thị trên sao kê</label>
                        <input v-model="balanceForm.note" type="text" placeholder="VD: Hỗ trợ bù thời gian bảo trì" class="w-full px-3 py-2 rounded-lg border border-slate-300" />
                    </div>
                    <button type="submit" :disabled="balanceForm.processing" class="px-4 py-2 rounded-lg bg-[#0B1437] text-white font-semibold cursor-pointer">
                        Thực hiện điều chỉnh
                    </button>
                </form>

                <!-- Discount & Role -->
                <form
                    @submit.prevent="roleForm.post(`/admin/users/${member.id}/discount`, { preserveScroll: true })"
                    class="surface-card p-6 space-y-4 text-xs"
                >
                    <h3 class="text-sm font-bold text-[#0B1437]">Chiết khấu VIP &amp; Cấp quyền Đại lý API</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold mb-1">Chiết khấu Cloud VPS (%)</label>
                            <input v-model.number="roleForm.discount_percent" type="number" min="0" max="100" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold" />
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Chiết khấu Proxy (%)</label>
                            <input v-model.number="roleForm.proxy_discount_percent" type="number" min="0" max="100" class="w-full px-3 py-2 rounded-lg border border-slate-300 font-mono-num font-bold" />
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Vai trò hệ thống</label>
                        <select v-model.number="roleForm.role" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white">
                            <option :value="0">Khách hàng tiêu chuẩn</option>
                            <option :value="2">Đại lý Reseller (Mở quyền API v1)</option>
                            <option :value="1">Quản trị viên (Admin)</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="roleForm.processing" class="px-4 py-2 rounded-lg bg-[#1D6AE5] text-white font-semibold cursor-pointer">
                        Lưu phân quyền &amp; Chiết khấu
                    </button>
                </form>
            </div>

            <!-- Recent Transactions -->
            <div class="surface-card overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-200 text-xs font-bold uppercase text-slate-700">
                    20 Giao dịch gần nhất
                </div>
                <!-- Mobile Card List -->
                <div class="sm:hidden divide-y divide-slate-200/80">
                    <div v-if="transactions.length === 0" class="py-8 px-4 text-center text-xs text-slate-500">
                        Chưa có giao dịch nào.
                    </div>
                    <div v-for="t in transactions" :key="`m-${t.id}`" class="p-4 space-y-2 bg-white text-xs">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-mono font-bold text-[#0B1437] min-w-0 truncate">
                                <Link
                                    v-if="t.code"
                                    :href="`/admin/revenue?search=${encodeURIComponent(t.code)}`"
                                    class="hover:text-[#1D6AE5] transition-colors"
                                >
                                    {{ t.code }}
                                </Link>
                                <span v-else>#{{ t.id }}</span>
                            </div>
                            <div class="font-mono-num font-extrabold text-[#0B1437] shrink-0">{{ formatVnd(t.amount) }}</div>
                        </div>
                        <div class="text-slate-700 leading-relaxed break-words">{{ t.description }}</div>
                        <div class="flex flex-wrap items-center justify-between gap-1.5 pt-1 text-[11px]">
                            <span v-if="t.coupon_code" class="font-mono font-semibold text-emerald-700">
                                Mã: {{ t.coupon_code }}<span v-if="t.discount_amount > 0"> (-{{ formatVnd(t.discount_amount) }})</span>
                            </span>
                            <span v-else />
                            <span class="font-mono-num text-slate-500">{{ t.created_at }}</span>
                        </div>
                    </div>
                </div>
                <!-- Desktop Table -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="t in transactions" :key="t.id">
                                <td class="py-3 px-4 font-mono font-bold text-[#0B1437]">
                                    <Link
                                        v-if="t.code"
                                        :href="`/admin/revenue?search=${encodeURIComponent(t.code)}`"
                                        class="hover:text-[#1D6AE5] transition-colors"
                                    >
                                        {{ t.code }}
                                    </Link>
                                    <span v-else>#{{ t.id }}</span>
                                </td>
                                <td class="py-3 px-4 text-slate-700">
                                    <div>{{ t.description }}</div>
                                    <div v-if="t.coupon_code" class="text-[11px] font-mono font-semibold text-emerald-700 mt-0.5">
                                        Mã giảm giá: {{ t.coupon_code }}<span v-if="t.discount_amount > 0"> (-{{ formatVnd(t.discount_amount) }})</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right font-mono-num font-bold">{{ formatVnd(t.amount) }}</td>
                                <td class="py-3 px-4 font-mono-num text-slate-500 text-right">{{ t.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
