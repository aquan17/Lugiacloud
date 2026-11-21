<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CountdownClock from '@/Components/CountdownClock.vue';
import ToastStack from '@/Components/ToastStack.vue';
import LoginRequiredModal from '@/Components/LoginRequiredModal.vue';
import { copyToClipboard, formatVnd } from '@/Composables/useFormat';
import { useToast } from '@/Composables/useToast';
import {
    loginPromptState,
    closeLoginPrompt,
    isAuthProtectedPath,
    openLoginPrompt,
    requireAuthOrVisit,
} from '@/Composables/useLoginPrompt';
import {
    Menu,
    X,
    ArrowRight,
    Copy,
    LayoutDashboard,
    Server,
    Globe,
    Cpu,
    Tag,
    Code2,
    Headphones,
    BookOpen,
    Sparkles,
    ShieldCheck,
    CreditCard,
    LogIn,
    Phone,
    Mail,
    MapPin,
    Gift,
} from 'lucide-vue-next';

const page = usePage();
const toast = useToast();
const mobileOpen = ref(false);
const showPopup = ref(false);
const hideTopBar = ref(false);

const user = computed(() => (page.props.auth as any)?.user);
const topBar = computed(() => (page.props.promotions as any)?.banners?.top_bar);
const welcomeOffer = computed(() => (page.props.promotions as any)?.welcome_offer);
const popupBanner = computed(() => (page.props.promotions as any)?.banners?.popup_modal);

// Nếu là thành viên mới còn hạn ưu đãi -> thay thanh đỉnh trang bằng ưu đãi chào mừng (hết hạn / dùng xong tự quay về Sự kiện)
const showWelcomeEligibleTopBar = computed(() => welcomeOffer.value?.mode === 'eligible');
const showWelcomeGuestTopBar = computed(() => !showWelcomeEligibleTopBar.value && !topBar.value && welcomeOffer.value?.mode === 'guest');

onMounted(() => {
    if (popupBanner.value?.id) {
        const key = `lugia_popup_${popupBanner.value.id}_${new Date().toISOString().slice(0, 10)}`;
        if (!localStorage.getItem(key)) {
            showPopup.value = true;
        }
    }
});

function dismissPopup() {
    if (popupBanner.value?.id) {
        const key = `lugia_popup_${popupBanner.value.id}_${new Date().toISOString().slice(0, 10)}`;
        localStorage.setItem(key, '1');
    }
    showPopup.value = false;
}

async function copyCode(code: string) {
    if (await copyToClipboard(code)) {
        toast.success(`Đã sao chép mã ưu đãi ${code}`);
    }
}

function goSection(hash: string, tab?: 'vps' | 'proxy') {
    mobileOpen.value = false;
    const onHome = page.url === '/' || page.url.startsWith('/?') || page.url.startsWith('/#');
    if (onHome) {
        if (tab) {
            window.dispatchEvent(new CustomEvent('lugia:tab', { detail: tab }));
        }
        const el = document.querySelector(hash);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth' });
            return;
        }
    }
    router.visit(tab ? `/?tab=${tab}${hash}` : `/${hash}`);
}

function goBannerUrl(rawUrl?: string, actionLabel?: string) {
    const url = (rawUrl || '#pricing').trim();
    if (/^https?:\/\//i.test(url)) {
        window.open(url, '_blank', 'noopener,noreferrer');
        return;
    }
    if (url.startsWith('#') || url.startsWith('/#')) {
        goSection(url.replace(/^\//, ''));
        return;
    }
    if (!user.value && isAuthProtectedPath(url)) {
        openLoginPrompt({
            actionLabel: actionLabel || 'Tiếp tục xem ưu đãi & sử dụng dịch vụ',
            redirectUrl: url,
        });
        return;
    }
    router.visit(url);
}

function goPopupBannerCta() {
    const url = popupBanner.value?.cta_url || '#pricing';
    const label = popupBanner.value?.cta_text || popupBanner.value?.title || 'Xem chương trình ưu đãi';
    dismissPopup();
    goBannerUrl(url, label);
}

function openWelcomeOfferPrompt() {
    const offer = welcomeOffer.value;
    const cap = offer?.max_discount > 0 ? ` (tối đa ${formatVnd(offer.max_discount)})` : '';
    openLoginPrompt({
        title: 'Nhận ưu đãi thành viên mới',
        description: 'Tạo tài khoản mới để nhận tự động mã giảm giá vào Ví Voucher, hoặc đăng nhập nếu bạn đã có tài khoản LugiaCloud.',
        actionLabel: `Khách mới: Giảm ${offer?.percent || 10}% đơn đầu${cap}`,
        redirectUrl: '/store',
    });
}
</script>

<template>
    <div class="min-h-screen flex flex-col bg-[#F5F7FB] text-[#0F172A]">
        <ToastStack />

        <!-- Thanh thông báo đỉnh trang thông minh: Thành viên mới (7 ngày) -> Sự kiện -> Mời đăng ký -->
        <div
            v-if="showWelcomeEligibleTopBar && !hideTopBar"
            class="text-white text-xs border-b border-white/10"
            style="background: linear-gradient(90deg, #065F46 0%, #0D9488 100%)"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-2.5">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded bg-white/18 text-emerald-100 text-[10px] font-bold tracking-wide uppercase shrink-0">
                        <Gift class="w-3 h-3" />
                        <span>Thành viên mới</span>
                    </span>
                    <span class="font-medium truncate text-[11px] sm:text-xs">
                        Giảm {{ welcomeOffer.percent }}% đơn đầu tiên<template v-if="welcomeOffer.max_discount > 0"> (tối đa {{ formatVnd(welcomeOffer.max_discount) }})</template> — gói {{ welcomeOffer.max_months }} tháng
                    </span>
                    <CountdownClock v-if="welcomeOffer.expires_at" :ends-at="welcomeOffer.expires_at" compact class="hidden lg:inline-flex shrink-0" />
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        @click="copyCode(welcomeOffer.code)"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-950/35 border border-emerald-200/40 font-mono-num text-[11px] font-bold text-emerald-100 hover:bg-slate-950/55 cursor-pointer"
                    >
                        <span>{{ welcomeOffer.code }}</span>
                        <Copy class="w-3 h-3" />
                    </button>
                    <Link
                        href="/store"
                        class="hidden sm:inline-flex items-center gap-1 font-bold text-white hover:text-emerald-100"
                    >
                        <span>Dùng ngay</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                    <button @click="hideTopBar = true" class="text-white/70 hover:text-white p-0.5 cursor-pointer" aria-label="Đóng thông báo">
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <div
            v-else-if="showWelcomeGuestTopBar && !hideTopBar"
            class="text-white text-xs border-b border-white/10"
            style="background: linear-gradient(90deg, #0B1437 0%, #1D6AE5 100%)"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-2.5">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-400/20 text-emerald-300 text-[10px] font-bold tracking-wide uppercase shrink-0">
                        <Gift class="w-3 h-3" />
                        <span>Ưu đãi khách mới</span>
                    </span>
                    <span class="font-medium truncate text-[11px] sm:text-xs">
                        Tặng ngay mã giảm {{ welcomeOffer.percent }}%<template v-if="welcomeOffer.max_discount > 0"> (tối đa {{ formatVnd(welcomeOffer.max_discount) }})</template> cho đơn VPS &amp; Proxy đầu tiên khi đăng ký tài khoản
                    </span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        @click="openWelcomeOfferPrompt"
                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded bg-white text-[#0B1437] hover:bg-blue-50 font-bold text-[11px] transition cursor-pointer"
                    >
                        <span>Đăng ký nhận mã</span>
                        <ArrowRight class="w-3 h-3" />
                    </button>
                    <button @click="hideTopBar = true" class="text-white/60 hover:text-white p-0.5 cursor-pointer" aria-label="Đóng thông báo">
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <div
            v-else-if="topBar && !hideTopBar"
            class="text-white text-xs border-b border-white/10"
            :style="{ background: `linear-gradient(90deg, ${topBar.bg_from || '#0B1437'} 0%, ${topBar.bg_to || '#1D4ED8'} 100%)` }"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-2.5">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <span
                        v-if="topBar.eyebrow"
                        class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-400/20 text-amber-300 text-[10px] font-bold tracking-wide uppercase shrink-0"
                    >
                        <Sparkles class="w-3 h-3" />
                        <span>{{ topBar.eyebrow }}</span>
                    </span>
                    <span class="font-medium truncate text-[11px] sm:text-xs">{{ topBar.title }}</span>
                    <CountdownClock v-if="topBar.ends_at" :ends-at="topBar.ends_at" compact class="hidden lg:inline-flex shrink-0" />
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button
                        v-if="topBar.coupon_code_display"
                        type="button"
                        @click="copyCode(topBar.coupon_code_display)"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-950/45 border border-amber-300/40 font-mono-num text-[11px] font-bold text-amber-300 hover:bg-slate-950/70 cursor-pointer"
                    >
                        <span>{{ topBar.coupon_code_display }}</span>
                        <Copy class="w-3 h-3" />
                    </button>
                    <button
                        type="button"
                        @click="goBannerUrl(topBar.cta_url, topBar.cta_text || topBar.title)"
                        class="hidden sm:inline-flex items-center gap-1 font-semibold text-sky-200 hover:text-white cursor-pointer"
                    >
                        <span>{{ topBar.cta_text || 'Xem ngay' }}</span>
                        <ArrowRight class="w-3.5 h-3.5" />
                    </button>
                    <button @click="hideTopBar = true" class="text-white/60 hover:text-white p-0.5" aria-label="Đóng thông báo">
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Thanh điều hướng chính -->
        <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/90">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
                <div class="flex items-center gap-5 xl:gap-7 min-w-0">
                    <Link href="/" class="flex items-center gap-2.5 shrink-0">
                        <img src="/logo.webp" alt="LugiaCloud" width="136" height="34" decoding="async" class="h-8 sm:h-8.5 w-auto object-contain" />
                    </Link>

                    <nav class="hidden lg:flex items-center gap-0.5 xl:gap-1 text-[13px] font-semibold text-slate-600">
                        <button type="button" @click="goSection('#pricing', 'vps')" class="inline-flex items-center gap-1.5 px-2.5 xl:px-3 py-2 rounded-lg hover:bg-slate-100/80 hover:text-[#0B1437] transition-colors cursor-pointer whitespace-nowrap shrink-0">
                            <Server class="w-3.5 h-3.5 text-[#1D6AE5] shrink-0" />
                            <span>Máy chủ VPS</span>
                        </button>
                        <button type="button" @click="goSection('#pricing', 'proxy')" class="inline-flex items-center gap-1.5 px-2.5 xl:px-3 py-2 rounded-lg hover:bg-slate-100/80 hover:text-[#0B1437] transition-colors cursor-pointer whitespace-nowrap shrink-0">
                            <Globe class="w-3.5 h-3.5 text-[#1D6AE5] shrink-0" />
                            <span>Proxy Việt Nam</span>
                        </button>
                        <Link href="/servers/store" prefetch class="inline-flex items-center gap-1.5 px-2.5 xl:px-3 py-2 rounded-lg hover:bg-slate-100/80 hover:text-[#0B1437] transition-colors whitespace-nowrap shrink-0">
                            <Cpu class="w-3.5 h-3.5 text-indigo-600 shrink-0" />
                            <span>Máy chủ vật lý</span>
                        </Link>
                        <Link href="/pricing" prefetch class="inline-flex items-center gap-1.5 px-2.5 xl:px-3 py-2 rounded-lg hover:bg-slate-100/80 hover:text-[#0B1437] transition-colors whitespace-nowrap shrink-0">
                            <Tag class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                            <span>Bảng giá</span>
                        </Link>
                        <Link href="/blog" prefetch class="inline-flex items-center gap-1.5 px-2.5 xl:px-3 py-2 rounded-lg hover:bg-slate-100/80 hover:text-[#0B1437] transition-colors whitespace-nowrap shrink-0">
                            <BookOpen class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                            <span>Blog</span>
                        </Link>
                        <Link href="/support" prefetch class="inline-flex items-center gap-1.5 px-2.5 xl:px-3 py-2 rounded-lg hover:bg-slate-100/80 hover:text-[#0B1437] transition-colors whitespace-nowrap shrink-0">
                            <Headphones class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                            <span>Hỗ trợ 24/7</span>
                        </Link>
                    </nav>
                </div>

                <div class="hidden md:flex items-center gap-2 shrink-0">
                    <template v-if="user">
                        <span class="px-3 py-1.5 rounded-lg bg-slate-100 border border-slate-200 text-xs font-mono-num font-bold text-[#0B1437] whitespace-nowrap">
                            {{ formatVnd(user.balance) }}
                        </span>
                        <Link
                            href="/dashboard"
                            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold transition-colors whitespace-nowrap shrink-0"
                        >
                            <LayoutDashboard class="w-3.5 h-3.5 text-sky-400 shrink-0" />
                            <span>Bảng điều khiển</span>
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[13px] font-semibold text-slate-700 hover:text-[#0B1437] hover:bg-slate-100 transition-colors whitespace-nowrap shrink-0"
                        >
                            <LogIn class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                            <span>Đăng nhập</span>
                        </Link>
                        <Link
                            href="/register"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-[13px] font-bold shadow-xs transition-colors whitespace-nowrap shrink-0"
                        >
                            <span>Đăng ký miễn phí</span>
                            <ArrowRight class="w-3.5 h-3.5 shrink-0" />
                        </Link>
                    </template>
                </div>

                <button
                    type="button"
                    class="lg:hidden p-2 rounded-lg text-slate-700 hover:bg-slate-100 border border-slate-200"
                    @click="mobileOpen = !mobileOpen"
                    aria-label="Mở trình đơn"
                >
                    <Menu v-if="!mobileOpen" class="w-5 h-5" />
                    <X v-else class="w-5 h-5" />
                </button>
            </div>

            <!-- Menu di động (Mobile Drawer) -->
            <div v-if="mobileOpen" class="lg:hidden border-t border-slate-200 bg-white px-4 py-4 space-y-1.5 shadow-lg">
                <button type="button" @click="goSection('#pricing', 'vps')" class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 text-left">
                    <Server class="w-4 h-4 text-[#1D6AE5]" />
                    <span>Máy chủ Cloud VPS SSD</span>
                </button>
                <button type="button" @click="goSection('#pricing', 'proxy')" class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 text-left">
                    <Globe class="w-4 h-4 text-[#1D6AE5]" />
                    <span>Proxy IPv4 Việt Nam</span>
                </button>
                <Link href="/servers/store" @click="mobileOpen = false" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    <Cpu class="w-4 h-4 text-indigo-600" />
                    <span>Máy chủ vật lý (Dedicated)</span>
                </Link>
                <Link href="/pricing" @click="mobileOpen = false" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    <Tag class="w-4 h-4 text-slate-500" />
                    <span>Bảng giá chi tiết</span>
                </Link>
                <button type="button" @click="goSection('#architecture')" class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 text-left">
                    <Code2 class="w-4 h-4 text-slate-500" />
                    <span>Tích hợp API Đại lý</span>
                </button>
                <Link href="/blog" @click="mobileOpen = false" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    <BookOpen class="w-4 h-4 text-slate-500" />
                    <span>Blog &amp; Hướng dẫn</span>
                </Link>
                <Link href="/support" @click="mobileOpen = false" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    <Headphones class="w-4 h-4 text-slate-500" />
                    <span>Trung tâm hỗ trợ 24/7</span>
                </Link>

                <div class="pt-3 mt-2 border-t border-slate-100 grid grid-cols-2 gap-2.5">
                    <template v-if="user">
                        <Link href="/dashboard" class="col-span-2 text-center py-2.5 rounded-xl bg-[#0B1437] text-white text-sm font-bold">
                            Vào Bảng điều khiển ({{ formatVnd(user.balance) }})
                        </Link>
                    </template>
                    <template v-else>
                        <Link href="/login" class="text-center py-2.5 rounded-xl border border-slate-300 text-sm font-semibold text-slate-800">
                            Đăng nhập
                        </Link>
                        <Link href="/register" class="text-center py-2.5 rounded-xl bg-[#1D6AE5] text-white text-sm font-bold">
                            Đăng ký ngay
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <!-- Chân trang chuẩn thông tin thực tế LugiaCloud -->
        <footer class="bg-[#0B1437] text-slate-300 border-t border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-8 pb-10 border-b border-white/10">
                    <div class="sm:col-span-2 lg:col-span-4 space-y-3.5">
                        <div class="flex items-center gap-2.5">
                            <img src="/bird-icon.webp" alt="LugiaCloud" width="32" height="32" loading="lazy" decoding="async" class="w-8 h-8 object-contain" />
                            <span class="text-lg font-extrabold tracking-tight text-white">LugiaCloud</span>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-400 leading-relaxed max-w-md">
                            LugiaCloud vận hành hệ thống Cloud VPS 100% ổ cứng SSD chuyên dụng (Intel Xeon Gold / Platinum, đặt tại FPT Datacenter TP.HCM) và Proxy IPv4 Tĩnh &amp; Dân cư sạch, tối ưu chi phí sát giá gốc cho cộng đồng.
                        </p>
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-400/20 text-xs font-medium text-emerald-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>FPT Datacenter (TP.HCM) · Tích hợp sẵn Anti DDoS</span>
                        </div>
                    </div>

                    <div class="lg:col-span-2 space-y-2.5 text-xs sm:text-sm">
                        <div class="text-xs font-bold uppercase tracking-wider text-white">Sản phẩm chính</div>
                        <ul class="space-y-2 text-slate-400">
                            <li><button type="button" @click="goSection('#pricing', 'vps')" class="hover:text-white transition-colors cursor-pointer">Cloud VPS SSD</button></li>
                            <li><button type="button" @click="goSection('#pricing', 'vps')" class="hover:text-white transition-colors cursor-pointer">VPS Windows &amp; Linux</button></li>
                            <li><button type="button" @click="goSection('#pricing', 'proxy')" class="hover:text-white transition-colors cursor-pointer">Proxy Dân cư Việt Nam</button></li>
                            <li><button type="button" @click="goSection('#pricing', 'proxy')" class="hover:text-white transition-colors cursor-pointer">Proxy Datacenter Tĩnh</button></li>
                            <li><Link href="/servers/store" class="hover:text-white transition-colors">Máy chủ vật lý (Dedicated)</Link></li>
                        </ul>
                    </div>

                    <div class="lg:col-span-2 space-y-2.5 text-xs sm:text-sm">
                        <div class="text-xs font-bold uppercase tracking-wider text-white">Khách hàng &amp; Đại lý</div>
                        <ul class="space-y-2 text-slate-400">
                            <li><Link href="/pricing" class="hover:text-white transition-colors">Bảng giá tự động</Link></li>
                            <li>
                                <button
                                    type="button"
                                    @click="requireAuthOrVisit('/reseller-api', 'Truy cập tài liệu & khóa API Đại lý', user)"
                                    class="hover:text-white transition-colors cursor-pointer"
                                >
                                    API cho Developer
                                </button>
                            </li>
                            <li>
                                <button
                                    type="button"
                                    @click="requireAuthOrVisit('/affiliate', 'Tham gia chương trình Affiliate Hoa hồng 5%', user)"
                                    class="hover:text-white transition-colors cursor-pointer"
                                >
                                    Affiliate Hoa hồng 5%
                                </button>
                            </li>
                            <li><Link href="/blog" class="hover:text-white transition-colors">Blog &amp; Hướng dẫn</Link></li>
                            <li><Link href="/support" class="hover:text-white transition-colors">Chính sách &amp; FAQ</Link></li>
                        </ul>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-4 space-y-3 text-xs sm:text-sm">
                        <div class="text-xs font-bold uppercase tracking-wider text-white">Liên hệ &amp; Hỗ trợ 24/7</div>
                        <ul class="space-y-2 text-xs text-slate-400">
                            <li class="flex items-start gap-2.5">
                                <MapPin class="w-4 h-4 text-sky-400 shrink-0 mt-0.5" />
                                <span>Quốc Oai, Hà Nội, Việt Nam</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <Phone class="w-4 h-4 text-emerald-400 shrink-0" />
                                <a href="tel:0862579104" class="hover:text-white font-mono-num font-semibold text-slate-200 transition-colors">0862.579.104</a>
                                <span class="text-slate-600">·</span>
                                <a href="https://zalo.me/0862579104" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:text-sky-300 font-semibold">Zalo trực tiếp</a>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <Mail class="w-4 h-4 text-sky-400 shrink-0" />
                                <a href="mailto:quanpc2004@gmail.com" class="hover:text-white font-mono text-slate-300 transition-colors">quanpc2004@gmail.com</a>
                            </li>
                        </ul>
                        <div class="flex flex-wrap gap-2 pt-1 text-[11px] font-semibold">
                            <a href="https://zalo.me/g/bh9hrrcujly5ab2pqmfj" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-sky-500/15 border border-sky-400/25 text-sky-300 hover:bg-sky-500/25 transition-colors">
                                Nhóm Zalo Cộng đồng
                            </a>
                            <a href="https://www.facebook.com/profile.php?id=61591873231741" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-white/8 border border-white/10 text-slate-200 hover:bg-white/15 transition-colors">
                                Fanpage chính thức
                            </a>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-white/8 border border-white/10 text-slate-300">
                                <CreditCard class="w-3 h-3 text-emerald-400" /> Nạp QR 1–3 phút
                            </span>
                        </div>
                    </div>
                </div>

                <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                    <div>© {{ new Date().getFullYear() }} Lugiacloud.com — Tất cả các quyền được bảo lưu.</div>
                    <div class="flex items-center gap-5">
                        <Link href="/support" class="hover:text-slate-300">Chính sách Bảo hành</Link>
                        <Link href="/support" class="hover:text-slate-300">Câu hỏi thường gặp (FAQ)</Link>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Nút Zalo hỗ trợ tư vấn cấu hình custom 24/7 -->
        <a
            href="https://zalo.me/0862579104"
            target="_blank"
            rel="noopener noreferrer"
            class="fixed bottom-5 right-5 z-40 inline-flex items-center gap-2 px-3.5 py-2.5 rounded-full bg-[#0068FF] hover:bg-[#0057D9] text-white text-xs font-bold shadow-lg shadow-blue-600/30 transition-all hover:-translate-y-0.5 no-print"
            title="Nhắn Zalo tư vấn cấu hình & custom riêng 24/7"
        >
            <img src="/image/zalo.svg" alt="Zalo" class="w-5 h-5 object-contain" />
            <span class="hidden sm:inline">Zalo hỗ trợ 24/7</span>
        </a>

        <!-- Popup Sự kiện tùy chọn -->
        <Teleport to="body">
            <div
                v-if="showPopup && popupBanner"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-[1px]"
                @click.self="dismissPopup"
            >
                <div class="w-full max-w-md rounded-2xl overflow-hidden bg-white border border-slate-200 shadow-2xl">
                    <div
                        class="p-6 text-white relative"
                        :style="{ background: `linear-gradient(135deg, ${popupBanner.bg_from || '#0B1437'} 0%, ${popupBanner.bg_to || '#1D4ED8'} 100%)` }"
                    >
                        <button @click="dismissPopup" class="absolute top-3.5 right-3.5 text-white/70 hover:text-white">
                            <X class="w-4 h-4" />
                        </button>
                        <span v-if="popupBanner.eyebrow" class="text-[11px] font-bold uppercase tracking-wider text-amber-300">
                            {{ popupBanner.eyebrow }}
                        </span>
                        <h3 class="text-lg font-extrabold mt-1">{{ popupBanner.title }}</h3>
                        <p v-if="popupBanner.subtitle" class="text-xs text-blue-100 mt-1.5 leading-relaxed">
                            {{ popupBanner.subtitle }}
                        </p>
                    </div>
                    <div class="p-5 flex items-center justify-between gap-3 bg-white">
                        <button
                            v-if="popupBanner.coupon_code_display"
                            type="button"
                            @click="copyCode(popupBanner.coupon_code_display)"
                            class="px-3 py-2 rounded-lg bg-slate-100 border border-slate-200 font-mono-num text-xs font-bold text-[#0B1437] inline-flex items-center gap-1.5"
                        >
                            <span>{{ popupBanner.coupon_code_display }}</span>
                            <Copy class="w-3.5 h-3.5 text-slate-500" />
                        </button>
                        <button
                            type="button"
                            @click="goPopupBannerCta"
                            class="ml-auto px-4 py-2 rounded-xl bg-[#1D6AE5] hover:bg-[#1557C0] text-white text-xs font-bold cursor-pointer"
                        >
                            {{ popupBanner.cta_text || 'Xem chương trình' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <LoginRequiredModal
            :open="loginPromptState.open"
            :title="loginPromptState.title"
            :description="loginPromptState.description"
            :action-label="loginPromptState.actionLabel"
            :redirect-url="loginPromptState.redirectUrl"
            @close="closeLoginPrompt"
        />
    </div>
</template>
