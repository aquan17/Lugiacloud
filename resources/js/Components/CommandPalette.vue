<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Search, Server, Globe, Cpu, Wallet, FileText, Terminal, Shield, Percent, Megaphone, BookOpen, KeyRound, X } from 'lucide-vue-next';

const open = defineModel<boolean>({ default: false });
const query = ref('');
const page = usePage();

const isAdmin = computed(() => Boolean((page.props.auth as any)?.user?.is_admin));
const isReseller = computed(() => Boolean((page.props.auth as any)?.user?.is_reseller));

const items = computed(() => {
    const base = [
        { label: 'Tổng quan hạ tầng', desc: 'Thống kê VPS, Proxy & tài chính', href: '/dashboard', icon: Shield },
        { label: 'Danh sách Cloud VPS', desc: 'Quản lý máy chủ ảo đang sở hữu', href: '/vps', icon: Server },
        { label: 'Khởi tạo Cloud VPS mới', desc: 'Chọn OS, cấu hình SSD & chu kỳ thuê', href: '/store', icon: Server },
        { label: 'Quản lý Proxy IPv4', desc: 'Sao chép hàng loạt, xuất TXT/CSV, gia hạn', href: '/proxy', icon: Globe },
        { label: 'Mua Proxy IPv4 mới', desc: 'Proxy Dân cư & Datacenter Việt Nam', href: '/proxy/store', icon: Globe },
        { label: 'Máy chủ vật lý (Dedicated)', desc: 'Quản lý máy chủ vật lý, IPMI & gia hạn', href: '/servers', icon: Cpu },
        { label: 'Thuê Máy chủ vật lý mới', desc: 'Dual Xeon Gold · 1Gbps Unmetered · IPMI', href: '/servers/store', icon: Cpu },
        { label: 'Nạp số dư (VietQR / PayOS)', desc: 'Cộng tiền tự động vào tài khoản', href: '/topup', icon: Wallet },
        { label: 'Sổ cái biến động số dư', desc: 'Lịch sử nạp tiền, thanh toán & hoàn tiền', href: '/billing', icon: FileText },
        { label: 'Hóa đơn dịch vụ', desc: 'Xem & in chứng từ thanh toán', href: '/invoices', icon: FileText },
        { label: 'Hướng dẫn & Blog', desc: 'Tài liệu RDP, SSH, Proxy & kiến thức SEO', href: '/blog', icon: BookOpen },
    ];

    if (isReseller.value || isAdmin.value) {
        base.push({ label: 'Tài liệu & Khóa Reseller API', desc: 'Tích hợp API v1 & Webhook tự động', href: '/reseller-api', icon: Terminal });
    }

    if (isAdmin.value) {
        base.push(
            { label: '[Admin] Quản lý Máy chủ vật lý', desc: 'Bàn giao IP/IPMI, đồng bộ & điều phối Dedicated', href: '/admin/servers', icon: Cpu },
            { label: '[Admin] Bảng giá & Biên lợi nhuận', desc: 'Đồng bộ giá gốc & cấu hình % Markup', href: '/admin/pricing', icon: Percent },
            { label: '[Admin] Chiến dịch Sự kiện & Banner', desc: 'Tạo khuyến mãi theo năm, Banner & Coupon', href: '/admin/promotions', icon: Megaphone },
            { label: '[Admin] Báo cáo Doanh thu & Lợi nhuận', desc: 'Đối soát doanh thu ròng thực tế', href: '/admin/revenue', icon: Wallet },
            { label: '[Admin] Ví & API CloudSunny', desc: 'Xem số dư gốc & cấu hình API CloudSunny', href: '/admin/accounts', icon: KeyRound },
            { label: '[Admin] Blog & Hướng dẫn SEO', desc: 'Quản lý bài viết hướng dẫn & ảnh R2 CDN', href: '/admin/articles', icon: FileText }
        );
    }

    const q = query.value.trim().toLowerCase();
    if (!q) return base;
    return base.filter((i) => i.label.toLowerCase().includes(q) || i.desc.toLowerCase().includes(q));
});

function go(href: string) {
    open.value = false;
    query.value = '';
    router.visit(href);
}

function onKeydown(e: KeyboardEvent) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        open.value = !open.value;
    } else if (e.key === 'Escape' && open.value) {
        open.value = false;
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[90] flex items-start justify-center pt-[12vh] px-4 bg-slate-950/50 backdrop-blur-[1px]"
            @click.self="open = false"
        >
            <div class="w-full max-w-lg bg-white rounded-xl border border-slate-200 shadow-2xl overflow-hidden">
                <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-200">
                    <Search class="w-4 h-4 text-slate-400 shrink-0" />
                    <input
                        v-model="query"
                        type="text"
                        autofocus
                        placeholder="Tìm nhanh tính năng, trang quản lý..."
                        class="w-full text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none"
                        @keydown.enter="items[0] && go(items[0].href)"
                    />
                    <button @click="open = false" class="text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="max-h-80 overflow-y-auto p-2 space-y-0.5">
                    <button
                        v-for="item in items"
                        :key="item.href"
                        type="button"
                        @click="go(item.href)"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-100 text-left transition-colors"
                    >
                        <div class="w-8 h-8 rounded-md bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700 shrink-0">
                            <component :is="item.icon" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-semibold text-slate-900 truncate">{{ item.label }}</div>
                            <div class="text-[11px] text-slate-500 truncate">{{ item.desc }}</div>
                        </div>
                    </button>
                    <div v-if="items.length === 0" class="py-8 text-center text-xs text-slate-500">
                        Không tìm thấy mục phù hợp.
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
