<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CommandPalette from '@/Components/CommandPalette.vue';
import ToastStack from '@/Components/ToastStack.vue';
import {
    TrendingUp,
    Percent,
    Megaphone,
    KeyRound,
    Server,
    Globe,
    Cpu,
    Users,
    Wallet,
    Share2,
    BookOpen,
    FileText,
    Settings,
    ArrowLeft,
    Menu,
    X,
    Search,
    LogOut,
    LifeBuoy,
    ScrollText,
} from 'lucide-vue-next';

defineProps<{
    title?: string;
}>();

const page = usePage();
const mobileOpen = ref(false);
const paletteOpen = ref(false);
const currentUrl = computed(() => page.url);
const user = computed(() => (page.props.auth as any)?.user);

const navGroups = [
    {
        label: 'Kinh doanh & Định giá',
        items: [
            { label: 'Doanh thu & Lợi nhuận', href: '/admin/revenue', icon: TrendingUp },
            { label: 'Bảng giá & Biên lợi nhuận', href: '/admin/pricing', icon: Percent },
            { label: 'Sự kiện, Banner & Coupon', href: '/admin/promotions', icon: Megaphone },
        ],
    },
    {
        label: 'Hạ tầng & Nguồn cung',
        items: [
            { label: 'Ví & API CloudSunny', href: '/admin/accounts', icon: KeyRound },
            { label: 'Quản lý toàn bộ VPS', href: '/admin/instances', icon: Server },
            { label: 'Quản lý toàn bộ Proxy', href: '/admin/proxies', icon: Globe },
            { label: 'Quản lý Máy chủ vật lý', href: '/admin/servers', icon: Cpu },
            { label: 'Ticket hỗ trợ hạ tầng', href: '/admin/tickets', icon: LifeBuoy },
        ],
    },
    {
        label: 'Khách hàng & Hệ thống',
        items: [
            { label: 'Người dùng & Phân quyền', href: '/admin/users', icon: Users },
            { label: 'Lịch sử & Duyệt nạp tiền', href: '/admin/topups', icon: Wallet },
            { label: 'Sổ cái ví toàn hệ thống', href: '/admin/ledger', icon: ScrollText },
            { label: 'Đối soát Affiliate F1', href: '/admin/affiliates', icon: Share2 },
            { label: 'Blog & Hướng dẫn SEO', href: '/admin/articles', icon: FileText },
            { label: 'Kho tri thức Chatbot AI', href: '/admin/knowledge-bases', icon: BookOpen },
            { label: 'Cài đặt hệ thống', href: '/admin/settings', icon: Settings },
        ],
    },
];

function isActive(href: string): boolean {
    return currentUrl.value.startsWith(href);
}
</script>

<template>
    <div class="min-h-screen bg-[#F6F8FA] text-[#0F172A] flex">
        <ToastStack />
        <CommandPalette v-model="paletteOpen" />

        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="mobileOpen = false" />

        <!-- Dark Navy Backoffice Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 w-[256px] bg-[#0B1437] text-slate-300 flex flex-col transition-transform duration-200 lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="h-14 px-5 border-b border-white/10 flex items-center justify-between">
                <Link href="/admin/revenue" class="flex items-center gap-2.5">
                    <img src="/bird-icon.webp" alt="Lugia" width="28" height="28" decoding="async" class="w-7 h-7 object-contain" />
                    <div>
                        <span class="text-sm font-extrabold text-white tracking-tight">LugiaCloud</span>
                        <span class="ml-1.5 px-1.5 py-0.5 rounded bg-sky-500/20 text-sky-300 text-[10px] font-bold uppercase">Admin</span>
                    </div>
                </Link>
                <button @click="mobileOpen = false" class="lg:hidden text-slate-400 hover:text-white">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div class="p-3 border-b border-white/10">
                <Link
                    href="/dashboard"
                    class="w-full inline-flex items-center justify-center gap-2 py-1.5 px-3 rounded-lg bg-white/8 hover:bg-white/14 text-white text-xs font-medium transition-colors"
                >
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Về giao diện Khách hàng</span>
                </Link>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-5 text-[13px]">
                <div v-for="group in navGroups" :key="group.label">
                    <div class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400/80">
                        {{ group.label }}
                    </div>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg transition-colors"
                            :class="
                                isActive(item.href)
                                    ? 'bg-[#1D6AE5] text-white font-semibold'
                                    : 'text-slate-300 hover:bg-white/8 hover:text-white'
                            "
                        >
                            <component :is="item.icon" class="w-4 h-4 shrink-0" />
                            <span>{{ item.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div v-if="user" class="p-3 border-t border-white/10 flex items-center justify-between gap-2 text-xs">
                <div class="min-w-0">
                    <div class="font-semibold text-white truncate">{{ user.name }}</div>
                    <div class="text-[11px] text-slate-400 truncate">{{ user.email }}</div>
                </div>
                <button @click="router.post('/logout')" class="p-1.5 rounded hover:bg-white/10 text-slate-400 hover:text-rose-400 cursor-pointer">
                    <LogOut class="w-4 h-4" />
                </button>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 lg:pl-[256px]">
            <header class="sticky top-0 z-30 h-14 bg-white/95 backdrop-blur border-b border-slate-200 px-3 sm:px-6 flex items-center justify-between gap-2 sm:gap-4">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
                    <button @click="mobileOpen = true" class="lg:hidden p-1.5 -ml-1 rounded-md text-slate-600 hover:bg-slate-100 shrink-0" aria-label="Mở menu quản trị">
                        <Menu class="w-5 h-5" />
                    </button>
                    <h1 class="text-[13.5px] sm:text-sm font-bold text-slate-900 truncate">{{ title || 'Quản trị hệ thống' }}</h1>
                </div>

                <button
                    type="button"
                    @click="paletteOpen = true"
                    class="inline-flex items-center gap-2 px-2.5 sm:px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200/70 border border-slate-200 text-xs text-slate-500 cursor-pointer shrink-0"
                    title="Chuyển trang nhanh"
                >
                    <Search class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">Chuyển trang nhanh</span>
                    <kbd class="hidden sm:inline-block px-1.5 py-0.5 text-[10px] font-mono bg-white rounded border border-slate-200">Ctrl K</kbd>
                </button>
            </header>

            <main class="flex-1 px-3.5 py-4 pb-12 sm:p-6 lg:p-7 max-w-[1440px] w-full mx-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
