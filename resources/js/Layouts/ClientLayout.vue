<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CommandPalette from '@/Components/CommandPalette.vue';
import ToastStack from '@/Components/ToastStack.vue';
import AiSupportWidget from '@/Components/AiSupportWidget.vue';
import { formatVnd } from '@/Composables/useFormat';
import {
    LayoutDashboard,
    Server,
    Globe,
    Cpu,
    Plus,
    ChevronDown,
    ChevronRight,
    Wallet,
    Receipt,
    FileSpreadsheet,
    Ticket,
    Users,
    Terminal,
    UserCog,
    Bell,
    Headphones,
    BookOpen,
    ShieldCheck,
    LogOut,
    Menu,
    X,
    Search,
} from 'lucide-vue-next';

defineProps<{
    title?: string;
}>();

const page = usePage();
const mobileSidebar = ref(false);
const paletteOpen = ref(false);
const createMenuOpen = ref(false);
const createMenuRef = ref<HTMLElement | null>(null);

const user = computed(() => (page.props.auth as any)?.user);
const currentUrl = computed(() => page.url);

function isActive(prefixes: string[]): boolean {
    const path = currentUrl.value.split('?')[0];
    return prefixes.some((p) => path === p || path.startsWith(p + '/'));
}

function toggleCreateMenu() {
    createMenuOpen.value = !createMenuOpen.value;
}

function closeCreateMenu() {
    createMenuOpen.value = false;
    mobileSidebar.value = false;
}

function handleClickOutside(e: MouseEvent) {
    if (createMenuRef.value && !createMenuRef.value.contains(e.target as Node)) {
        createMenuOpen.value = false;
    }
}

function handleKeyDown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        createMenuOpen.value = false;
    }
}

let removeNavigateListener: (() => void) | null = null;

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeyDown);
    removeNavigateListener = router.on('navigate', () => {
        createMenuOpen.value = false;
        mobileSidebar.value = false;
    });
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeyDown);
    if (removeNavigateListener) {
        removeNavigateListener();
        removeNavigateListener = null;
    }
});

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-[#F6F8FA] text-[#0F172A] flex">
        <ToastStack />
        <CommandPalette v-model="paletteOpen" />
        <AiSupportWidget v-if="user" />

        <!-- Mobile Backdrop -->
        <div
            v-if="mobileSidebar"
            class="fixed inset-0 z-40 bg-slate-950/45 lg:hidden"
            @click="mobileSidebar = false"
        />

        <!-- Left Navigation Rail -->
        <aside
            class="fixed inset-y-0 left-0 z-50 w-[248px] bg-white border-r border-slate-200 flex flex-col transition-transform duration-200 lg:translate-x-0 no-print"
            :class="mobileSidebar ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="h-14 px-5 border-b border-slate-200/80 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2.5">
                    <img src="/logo.webp" alt="LugiaCloud" width="112" height="28" decoding="async" class="h-7 w-auto object-contain" />
                </Link>
                <button @click="mobileSidebar = false" class="lg:hidden text-slate-400 hover:text-slate-700">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Khởi tạo dịch vụ mới: Floating Popover khớp đúng chiều rộng nút, không đẩy nav hay sinh thanh cuộn -->
            <div ref="createMenuRef" class="relative p-3 border-b border-slate-100 shrink-0">
                <button
                    type="button"
                    @click="toggleCreateMenu"
                    class="w-full h-9 inline-flex items-center justify-between px-3 rounded-lg bg-[#0B1437] hover:bg-[#152044] text-white text-xs font-bold transition-colors shadow-xs cursor-pointer"
                    :class="createMenuOpen ? 'ring-2 ring-[#1D6AE5]/30 bg-[#152044]' : ''"
                    aria-haspopup="menu"
                    :aria-expanded="createMenuOpen"
                >
                    <span class="inline-flex items-center gap-2 min-w-0">
                        <Plus class="w-4 h-4 text-sky-400 shrink-0 transition-transform duration-200" :class="createMenuOpen ? 'rotate-45' : ''" />
                        <span class="truncate">Khởi tạo dịch vụ mới</span>
                    </span>
                    <ChevronDown
                        class="w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform duration-200"
                        :class="createMenuOpen ? 'rotate-180 text-white' : ''"
                    />
                </button>

                <!-- Floating Popover: Khóa đúng khung left-3 right-3 (224px), nổi z-70 không làm xô lệch Sidebar -->
                <Transition
                    enter-active-class="transition duration-100 ease-out origin-top"
                    enter-from-class="opacity-0 scale-95 -translate-y-1"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-75 ease-in origin-top"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 -translate-y-1"
                >
                    <div
                        v-if="createMenuOpen"
                        role="menu"
                        class="absolute left-3 right-3 top-[calc(100%-4px)] z-[70] rounded-xl bg-white border border-slate-200 shadow-xl ring-1 ring-slate-950/5 p-1.5 space-y-1"
                    >
                        <div class="px-2 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            Chọn dịch vụ cần tạo
                        </div>

                        <Link
                            href="/store"
                            role="menuitem"
                            @click="closeCreateMenu"
                            class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-blue-50/80 text-[#0B1437] transition-colors group"
                        >
                            <div class="w-8 h-8 rounded-lg bg-blue-50 border border-blue-100 text-[#1D6AE5] flex items-center justify-center shrink-0 group-hover:bg-[#1D6AE5] group-hover:text-white group-hover:border-[#1D6AE5] transition-colors">
                                <Server class="w-4 h-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-[#0B1437] group-hover:text-[#1D6AE5] truncate">Cloud VPS SSD</div>
                                <div class="text-[11px] font-medium text-slate-500 truncate">FPT HCM · Khởi tạo 2p</div>
                            </div>
                            <ChevronRight class="w-3.5 h-3.5 text-slate-400 group-hover:text-[#1D6AE5] shrink-0" />
                        </Link>

                        <Link
                            href="/proxy/store"
                            role="menuitem"
                            @click="closeCreateMenu"
                            class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-emerald-50/80 text-[#0B1437] transition-colors group"
                        >
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white group-hover:border-emerald-600 transition-colors">
                                <Globe class="w-4 h-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-[#0B1437] group-hover:text-emerald-700 truncate">Proxy IPv4 Sạch</div>
                                <div class="text-[11px] font-medium text-slate-500 truncate">Dân cư &amp; Datacenter</div>
                            </div>
                            <ChevronRight class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 shrink-0" />
                        </Link>

                        <Link
                            href="/servers/store"
                            role="menuitem"
                            @click="closeCreateMenu"
                            class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-indigo-50/80 text-[#0B1437] transition-colors group"
                        >
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shrink-0 group-hover:bg-indigo-600 group-hover:text-white group-hover:border-indigo-600 transition-colors">
                                <Cpu class="w-4 h-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-[#0B1437] group-hover:text-indigo-700 truncate">Máy chủ vật lý</div>
                                <div class="text-[11px] font-medium text-slate-500 truncate">Dual Xeon · 1Gbps · IPMI</div>
                            </div>
                            <ChevronRight class="w-3.5 h-3.5 text-slate-400 group-hover:text-indigo-600 shrink-0" />
                        </Link>
                    </div>
                </Transition>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-5 text-[13.5px]">
                <div>
                    <div class="px-2.5 mb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Hạ tầng đám mây
                    </div>
                    <div class="space-y-0.5">
                        <Link
                            href="/dashboard"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/dashboard']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <LayoutDashboard class="w-4 h-4 shrink-0" :class="isActive(['/dashboard']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Tổng quan</span>
                        </Link>
                        <Link
                            href="/vps"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/vps']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <Server class="w-4 h-4 shrink-0" :class="isActive(['/vps']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Máy chủ Cloud VPS</span>
                        </Link>
                        <Link
                            href="/proxy"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/proxy']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <Globe class="w-4 h-4 shrink-0" :class="isActive(['/proxy']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Proxy IPv4 Việt Nam</span>
                        </Link>
                        <Link
                            href="/servers"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/servers']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <Cpu class="w-4 h-4 shrink-0" :class="isActive(['/servers']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Máy chủ vật lý</span>
                        </Link>
                    </div>
                </div>

                <div>
                    <div class="px-2.5 mb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Tài chính & Chứng từ
                    </div>
                    <div class="space-y-0.5">
                        <Link
                            href="/topup"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/topup']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <Wallet class="w-4 h-4 shrink-0" :class="isActive(['/topup']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Nạp số dư</span>
                        </Link>
                        <Link
                            href="/billing"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/billing']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <Receipt class="w-4 h-4 shrink-0" :class="isActive(['/billing']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Biến động số dư</span>
                        </Link>
                        <Link
                            href="/invoices"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/invoices']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <FileSpreadsheet class="w-4 h-4 shrink-0" :class="isActive(['/invoices']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Hóa đơn dịch vụ</span>
                        </Link>
                        <Link
                            href="/vouchers"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/vouchers']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <Ticket class="w-4 h-4 shrink-0" :class="isActive(['/vouchers']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Ví Voucher</span>
                        </Link>
                    </div>
                </div>

                <div>
                    <div class="px-2.5 mb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        Đối tác & Tài khoản
                    </div>
                    <div class="space-y-0.5">
                        <Link
                            href="/affiliate"
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/affiliate']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <Users class="w-4 h-4 shrink-0" :class="isActive(['/affiliate']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Tiếp thị liên kết (5%)</span>
                        </Link>
                        <Link
                            href="/reseller-api"
                            prefetch
                            class="flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/reseller-api']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <span class="flex items-center gap-2.5 min-w-0">
                                <Terminal class="w-4 h-4 shrink-0" :class="isActive(['/reseller-api']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                                <span class="truncate">Reseller API &amp; Docs</span>
                            </span>
                            <span
                                v-if="!user?.is_reseller && !user?.is_admin"
                                class="px-1.5 py-0.2 rounded bg-slate-100 text-[10px] font-bold text-slate-500 shrink-0"
                            >
                                Đại lý
                            </span>
                        </Link>
                        <Link
                            href="/profile"
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/profile']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <UserCog class="w-4 h-4 shrink-0" :class="isActive(['/profile']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Hồ sơ & Bảo mật</span>
                        </Link>
                        <Link
                            href="/blog"
                            prefetch
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/blog']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <BookOpen class="w-4 h-4 shrink-0" :class="isActive(['/blog']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Hướng dẫn &amp; Blog</span>
                        </Link>
                        <Link
                            href="/support"
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg font-medium transition-colors"
                            :class="isActive(['/support']) ? 'bg-slate-100 text-[#0B1437] font-semibold' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        >
                            <Headphones class="w-4 h-4 shrink-0" :class="isActive(['/support']) ? 'text-[#1D6AE5]' : 'text-slate-400'" />
                            <span>Trung tâm hỗ trợ</span>
                        </Link>
                    </div>
                </div>

                <div v-if="user?.is_admin" class="pt-2 border-t border-slate-100">
                    <Link
                        href="/admin/pricing"
                        class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg bg-amber-50/80 border border-amber-200/80 text-amber-900 font-semibold text-xs hover:bg-amber-100/70 transition-colors"
                    >
                        <ShieldCheck class="w-4 h-4 text-amber-600 shrink-0" />
                        <span>Quản trị Hệ thống (Admin)</span>
                    </Link>
                </div>
            </nav>

            <!-- User Footer -->
            <div v-if="user" class="p-3 border-t border-slate-200 bg-slate-50/60 flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <div class="text-xs font-semibold text-slate-900 truncate">{{ user.name }}</div>
                    <div class="text-[11px] text-slate-500 truncate">{{ user.email }}</div>
                </div>
                <button
                    type="button"
                    @click="logout"
                    title="Đăng xuất"
                    class="p-1.5 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                >
                    <LogOut class="w-4 h-4" />
                </button>
            </div>
        </aside>

        <!-- Right Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-[248px]">
            <header class="sticky top-0 z-30 h-14 bg-white/95 backdrop-blur border-b border-slate-200 px-3 sm:px-6 flex items-center justify-between gap-2 sm:gap-4 no-print">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
                    <button
                        type="button"
                        class="lg:hidden p-1.5 -ml-1 rounded-md text-slate-600 hover:bg-slate-100 shrink-0"
                        @click="mobileSidebar = true"
                        aria-label="Mở menu"
                    >
                        <Menu class="w-5 h-5" />
                    </button>
                    <h1 v-if="title" class="text-[13.5px] sm:text-sm font-bold text-slate-900 truncate">{{ title }}</h1>
                </div>

                <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                    <button
                        type="button"
                        @click="paletteOpen = true"
                        class="hidden sm:inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200/70 border border-slate-200 text-xs text-slate-500 transition-colors cursor-pointer"
                    >
                        <Search class="w-3.5 h-3.5" />
                        <span>Tìm nhanh...</span>
                        <kbd class="px-1.5 py-0.5 text-[10px] font-mono bg-white rounded border border-slate-200 text-slate-500">Ctrl K</kbd>
                    </button>

                    <template v-if="user">
                        <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50 pl-2.5 sm:pl-3 pr-1 py-1 gap-1.5 sm:gap-2 shrink-0">
                            <span class="text-[11.5px] sm:text-xs font-mono-num font-bold text-[#0B1437] whitespace-nowrap">{{ formatVnd(user.balance) }}</span>
                            <Link
                                href="/topup"
                                class="inline-flex items-center justify-center gap-0.5 px-2 sm:px-2.5 py-1 rounded-md bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-[11px] font-bold whitespace-nowrap shrink-0 transition-colors"
                                title="Nạp số dư"
                            >
                                <span>+</span>
                                <span class="hidden sm:inline">Nạp</span>
                            </Link>
                        </div>

                        <Link
                            href="/notifications"
                            class="p-1.5 sm:p-2 rounded-lg border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-colors shrink-0"
                            title="Cảnh báo dịch vụ"
                        >
                            <Bell class="w-4 h-4" />
                        </Link>
                    </template>
                </div>
            </header>

            <main class="flex-1 px-3.5 py-4 pb-20 sm:p-6 lg:p-7 max-w-[1400px] w-full mx-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
