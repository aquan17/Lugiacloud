<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import CredentialsDeck from '@/Components/ResellerApi/CredentialsDeck.vue';
import CodeBlockCard from '@/Components/ResellerApi/CodeBlockCard.vue';
import EndpointSectionCard from '@/Components/ResellerApi/EndpointSectionCard.vue';
import { useToast } from '@/Composables/useToast';
import {
    CODE_LANGUAGES,
    DOC_GROUPS,
    HTTP_ERROR_SPECS,
    WEBHOOK_CREATED_SAMPLE,
    WEBHOOK_FAILED_SAMPLE,
    WEBHOOK_PHP_RECEIVER,
    buildEndpointSpecs,
    generateQuickstartSnippet,
    type ApiEndpointSpec,
    type CodeLang,
} from '@/Data/resellerApiSpec';
import { BookOpen, CheckCircle2, Webhook, ShieldAlert, ArrowRight } from 'lucide-vue-next';

const props = defineProps<{
    canUseApi: boolean;
    roleLabel: string;
    discountPercent: number;
    proxyDiscountPercent?: number;
    apiKey?: string | null;
    webhookUrl?: string | null;
    baseUrl: string;
    catalogPlans: Array<{
        id: number | string;
        slug: string;
        name: string;
        cpu: number;
        ram: number;
        disk: number;
        network_mbps: number;
        price_monthly: number;
    }>;
    proxyPlans?: Array<{
        id: number;
        slug: string;
        name: string;
        category: string;
        dedicated: boolean;
        price_monthly: number;
    }>;
    osList: Array<{
        id: number;
        name: string;
        group: string;
    }>;
}>();

const toast = useToast();
const activeSection = ref<string>('intro');
const codeLang = ref<CodeLang>('curl');
const sidebarNavRef = ref<HTMLElement | null>(null);

function switchCodeLang(lang: CodeLang, label: string) {
    codeLang.value = lang;
    toast.success(`Đã chuyển ví dụ gọi API sang ${label}`);

    // Nếu trên màn hình laptop (scaling 125%/150%) đang ở đầu trang chưa nhìn thấy khung code mẫu thì cuộn nhẹ tới khung code gần nhất
    const visibleCodeTabs = Array.from(document.querySelectorAll<HTMLElement>('[data-lang-code-card]')).some((el) => {
        const r = el.getBoundingClientRect();
        return r.top < window.innerHeight - 140 && r.bottom > 120;
    });

    if (!visibleCodeTabs) {
        const firstCard = document.getElementById('quickstart-code-anchor');
        firstCard?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

watch(activeSection, (id) => {
    const navEl = sidebarNavRef.value;
    if (!navEl) return;
    const btn = navEl.querySelector<HTMLElement>(`[data-nav-id="${id}"]`);
    btn?.scrollIntoView({ block: 'nearest' });
});

const sampleToken = computed(() => (props.apiKey ? `${props.apiKey.slice(0, 12)}...` : 'sk_live_your_api_key_here'));

const allEndpoints = computed(() => buildEndpointSpecs(props.baseUrl, props.discountPercent));
const endpointMap = computed(() => {
    const map: Record<string, ApiEndpointSpec> = {};
    for (const ep of allEndpoints.value) {
        map[ep.id] = ep;
    }
    return map;
});

const preCreateEndpoints = computed(() =>
    ['get-profile', 'get-plans', 'post-create', 'get-status']
        .map((id) => endpointMap.value[id])
        .filter((x): x is ApiEndpointSpec => Boolean(x))
);

const lifecycleEndpoints = computed(() =>
    ['post-action', 'post-renew', 'post-rebuild', 'post-upgrade', 'post-password', 'post-change-ip', 'post-extend-disk', 'post-delete']
        .map((id) => endpointMap.value[id])
        .filter((x): x is ApiEndpointSpec => Boolean(x))
);

const proxyEndpoints = computed(() =>
    [
        'get-proxy-plans',
        'post-proxy-create',
        'get-proxy-status',
        'post-proxy-renew',
        'post-proxy-credentials',
        'post-proxy-change-type',
        'post-proxy-sync-ip',
        'post-proxy-delete',
    ]
        .map((id) => endpointMap.value[id])
        .filter((x): x is ApiEndpointSpec => Boolean(x))
);

const orderedSectionIds = computed(() => DOC_GROUPS.flatMap((g) => g.items.map((i) => i.id)));

const requiredHeadersSample = computed(
    () => `Authorization: Bearer ${sampleToken.value}\nAccept: application/json\nContent-Type: application/json`
);

// Smooth Jump + Deterministic ScrollSpy
let isProgrammaticScroll = false;
let scrollLockTimer: ReturnType<typeof setTimeout> | null = null;
let rafId: number | null = null;

function jumpToSection(id: string) {
    activeSection.value = id;
    const el = document.getElementById(`sec-${id}`);
    if (!el) return;

    isProgrammaticScroll = true;
    if (scrollLockTimer) clearTimeout(scrollLockTimer);
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });

    scrollLockTimer = setTimeout(() => {
        isProgrammaticScroll = false;
    }, 650);
}

function updateActiveOnScroll() {
    if (isProgrammaticScroll) return;
    const threshold = 148;
    let current = orderedSectionIds.value[0] || 'intro';

    for (const id of orderedSectionIds.value) {
        const el = document.getElementById(`sec-${id}`);
        if (!el) continue;
        const rect = el.getBoundingClientRect();
        if (rect.top <= threshold) {
            current = id;
        } else {
            break;
        }
    }

    if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 40) {
        current = orderedSectionIds.value[orderedSectionIds.value.length - 1] || current;
    }

    activeSection.value = current;
}

function onWindowScroll() {
    if (rafId !== null) return;
    rafId = window.requestAnimationFrame(() => {
        updateActiveOnScroll();
        rafId = null;
    });
}

onMounted(() => {
    window.addEventListener('scroll', onWindowScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onWindowScroll);
    if (scrollLockTimer) clearTimeout(scrollLockTimer);
    if (rafId !== null) window.cancelAnimationFrame(rafId);
});

function navBadgeClasses(tone?: string): string {
    switch (tone) {
        case 'get':
            return 'bg-sky-100 text-sky-800';
        case 'post':
            return 'bg-emerald-100 text-emerald-800';
        case 'delete':
            return 'bg-rose-100 text-rose-800';
        case 'hook':
            return 'bg-purple-100 text-purple-800';
        case 'code':
            return 'bg-amber-100 text-amber-800';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}

function errorBadgeClasses(tone: string): string {
    switch (tone) {
        case 'emerald':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'amber':
            return 'bg-amber-100 text-amber-900 border-amber-200';
        case 'rose':
            return 'bg-rose-100 text-rose-800 border-rose-200';
        default:
            return 'bg-slate-200 text-slate-800 border-slate-300';
    }
}
</script>

<template>
    <ClientLayout title="Reseller API Portal & Tài liệu tích hợp">
        <Head title="Reseller API & Tài liệu kỹ thuật" />

        <div class="space-y-7 pb-16">
            <!-- PHẦN 1: KHỐI QUẢN LÝ KHÓA XÁC THỰC & WEBHOOK -->
            <CredentialsDeck
                :can-use-api="canUseApi"
                :role-label="roleLabel"
                :discount-percent="discountPercent"
                :api-key="apiKey"
                :webhook-url="webhookUrl"
                :base-url="baseUrl"
            />

            <!-- THANH ĐIỀU HƯỚNG NHANH CHO MOBILE / TABLET (STICKY KHI CUỘN) -->
            <div class="lg:hidden sticky top-[62px] z-20 surface-card p-3 shadow-sm flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 bg-white/95 backdrop-blur">
                <select
                    :value="activeSection"
                    @change="jumpToSection(($event.target as HTMLSelectElement).value)"
                    class="flex-1 h-10 px-3 rounded-lg border border-slate-300 bg-white text-[14px] font-semibold text-[#0B1437]"
                >
                    <optgroup v-for="group in DOC_GROUPS" :key="group.groupTitle" :label="group.groupTitle">
                        <option v-for="item in group.items" :key="item.id" :value="item.id">
                            {{ item.badge ? `[${item.badge}] ` : '' }}{{ item.label }}
                        </option>
                    </optgroup>
                </select>

                <div class="inline-flex p-1 rounded-lg bg-slate-100 border border-slate-200 self-start sm:self-auto">
                    <button
                        v-for="lang in CODE_LANGUAGES"
                        :key="lang.id"
                        type="button"
                        @click="switchCodeLang(lang.id, lang.label)"
                        class="px-2.5 py-1 rounded-md text-xs font-bold cursor-pointer whitespace-nowrap"
                        :class="codeLang === lang.id ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-500'"
                    >
                        {{ lang.label }}
                    </button>
                </div>
            </div>

            <!-- PHẦN 2: BỐ CỤC DEVELOPER PORTAL 2 CỘT (SIDEBAR CUỘN THEO TRANG + NỘI DUNG RÕ NÉT) -->
            <div class="grid grid-cols-1 lg:grid-cols-[276px_minmax(0,1fr)] gap-7 items-start">
                <!-- CỘT TRÁI: STICKY SIDEBAR CUỘN THEO TRANG MƯỢT MÀ -->
                <aside class="hidden lg:flex lg:flex-col lg:sticky lg:top-[76px] lg:max-h-[calc(100vh-92px)] surface-card overflow-hidden">
                    <div class="p-4 border-b border-slate-200/90 bg-slate-50/80 space-y-2.5 shrink-0">
                        <div class="flex items-center gap-2">
                            <BookOpen class="w-4 h-4 text-[#1D6AE5]" />
                            <span class="text-[13px] font-extrabold uppercase tracking-wide text-[#0B1437]">
                                Tài liệu tích hợp v1
                            </span>
                        </div>

                        <div class="space-y-1">
                            <div class="text-[11px] font-semibold text-slate-500">Ngôn ngữ ví dụ gọi API:</div>
                            <div class="grid grid-cols-4 gap-1 p-1 rounded-lg bg-slate-200/70">
                                <button
                                    v-for="lang in CODE_LANGUAGES"
                                    :key="lang.id"
                                    type="button"
                                    @click="switchCodeLang(lang.id, lang.label)"
                                    class="py-1 px-1.5 rounded-md text-[11.5px] font-bold transition-all cursor-pointer text-center whitespace-nowrap"
                                    :class="
                                        codeLang === lang.id
                                            ? 'bg-white text-[#1D6AE5] shadow-2xs'
                                            : 'text-slate-600 hover:text-slate-900'
                                    "
                                >
                                    {{ lang.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <nav ref="sidebarNavRef" class="flex-1 overflow-y-auto p-3.5 space-y-5">
                        <div v-for="group in DOC_GROUPS" :key="group.groupTitle" class="space-y-1">
                            <div class="px-2.5 pb-1 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                                {{ group.groupTitle }}
                            </div>

                            <button
                                v-for="item in group.items"
                                :key="item.id"
                                :data-nav-id="item.id"
                                type="button"
                                @click="jumpToSection(item.id)"
                                class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[13.5px] text-left transition-all cursor-pointer"
                                :class="
                                    activeSection === item.id
                                        ? 'bg-[#EFF6FF] text-[#1D6AE5] font-bold'
                                        : 'text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 font-medium'
                                "
                            >
                                <span class="truncate">{{ item.label }}</span>
                                <span
                                    v-if="item.badge"
                                    class="px-1.5 py-0.5 rounded font-mono text-[10.5px] font-extrabold shrink-0 whitespace-nowrap"
                                    :class="navBadgeClasses(item.badgeTone)"
                                >
                                    {{ item.badge }}
                                </span>
                            </button>
                        </div>
                    </nav>
                </aside>

                <!-- CỘT PHẢI: DÒNG TÀI LIỆU KỸ THUẬT RÕ RÀNG, CỠ CHỮ DỄ ĐỌC -->
                <div class="min-w-0 space-y-7">
                    <!-- 1. TỔNG QUAN & QUY TRÌNH 4 BƯỚC -->
                    <section id="sec-intro" class="surface-card p-6 sm:p-8 space-y-6 scroll-mt-[84px]">
                        <div class="space-y-2.5">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-200/80 text-[#1D6AE5] text-xs font-bold">
                                <span>RESTful JSON API · Chuẩn xác thực Bearer Token</span>
                            </div>
                            <h2 class="text-xl sm:text-[22px] font-extrabold text-[#0B1437] tracking-tight">
                                Hướng dẫn đấu nối LugiaCloud Reseller API cho người mới
                            </h2>
                            <p class="text-[15px] text-slate-700 leading-7">
                                Hệ thống API Đại lý được thiết kế tối giản theo chuẩn RESTful, cho phép website hoặc tool bán hàng của bạn tự động hóa trọn vẹn quy trình từ đồng bộ bảng giá, khởi tạo máy chủ Cloud VPS Việt Nam, nhận IP/mật khẩu qua Webhook cho tới gia hạn, cài lại OS và nâng cấp phần cứng.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-5 rounded-xl bg-slate-50/90 border border-slate-200/90 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-mono-num font-extrabold text-[#1D6AE5]">BƯỚC 01 · ĐỒNG BỘ MÃ GÓI</span>
                                    <ArrowRight class="w-4 h-4 text-slate-400" />
                                </div>
                                <h3 class="text-[15px] font-bold text-[#0B1437]">Gọi `GET /api/v1/plans`</h3>
                                <p class="text-[14px] text-slate-600 leading-relaxed">
                                    Lấy danh sách mã gói (<code class="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-semibold">lg-1</code> đến <code class="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-semibold">lg-32</code>), đơn giá vốn của bạn và danh sách mã hệ điều hành (<code class="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-semibold">os_id</code>).
                                </p>
                            </div>

                            <div class="p-5 rounded-xl bg-slate-50/90 border border-slate-200/90 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-mono-num font-extrabold text-[#1D6AE5]">BƯỚC 02 · GỬI LỆNH MUA</span>
                                    <ArrowRight class="w-4 h-4 text-slate-400" />
                                </div>
                                <h3 class="text-[15px] font-bold text-[#0B1437]">Gọi `POST /api/v1/vps/create`</h3>
                                <p class="text-[14px] text-slate-600 leading-relaxed">
                                    Nhận ngay phản hồi <code class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-bold">202 Accepted</code> kèm mã <code class="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-semibold">order_id</code>. Lưu mã này gắn với đơn hàng của khách trên web bạn.
                                </p>
                            </div>

                            <div class="p-5 rounded-xl bg-slate-50/90 border border-slate-200/90 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-mono-num font-extrabold text-[#1D6AE5]">BƯỚC 03 · NHẬN IP &amp; MẬT KHẨU</span>
                                    <ArrowRight class="w-4 h-4 text-slate-400" />
                                </div>
                                <h3 class="text-[15px] font-bold text-[#0B1437]">Nhận `Webhook` hoặc `GET /status`</h3>
                                <p class="text-[14px] text-slate-600 leading-relaxed">
                                    Sau 2–5 phút khi cài đặt xong OS, LugiaCloud tự động bắn IP &amp; Password sang URL Webhook của bạn (hoặc gọi API kiểm tra tiến độ).
                                </p>
                            </div>

                            <div class="p-5 rounded-xl bg-slate-50/90 border border-slate-200/90 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-mono-num font-extrabold text-[#1D6AE5]">BƯỚC 04 · QUẢN LÝ VÒNG ĐỜI</span>
                                    <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                                </div>
                                <h3 class="text-[15px] font-bold text-[#0B1437]">Action · Renew · Rebuild · Upgrade</h3>
                                <p class="text-[14px] text-slate-600 leading-relaxed">
                                    Dùng chính mã <code class="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-semibold">order_id</code> để khách hàng của bạn tự bấm tắt/bật, cài lại Windows/Linux, gia hạn hoặc nâng cấp cấu hình.
                                </p>
                            </div>
                        </div>

                        <div id="quickstart-code-anchor" class="space-y-3">
                            <CodeBlockCard
                                title="Hàm kết nối mẫu dùng chung & Gọi thử API đầu tiên"
                                :code="generateQuickstartSnippet(baseUrl, sampleToken, codeLang)"
                                :copy-label="`hàm kết nối ${codeLang.toUpperCase()}`"
                                :lang="codeLang"
                                show-lang-tabs
                                @update:lang="codeLang = $event"
                            />

                            <CodeBlockCard
                                title="3 HTTP Headers bắt buộc ở mọi Request"
                                :code="requiredHeadersSample"
                                copy-label="HTTP Headers"
                            />

                            <p class="text-[13.5px] text-slate-600 leading-relaxed">
                                <strong>Ghi chú tích hợp:</strong> Khi bạn bấm chọn <strong>cURL / PHP / Node.js / Python</strong>, toàn bộ các khung <em>Đoạn mã gọi mẫu</em> trên trang sẽ tự động chuyển sang ngôn ngữ đó; riêng dữ liệu trao đổi (<em>Request Body &amp; Response</em>) luôn cố định theo chuẩn <strong>JSON</strong>. Nhớ truyền <code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 text-xs font-semibold">Accept: application/json</code> ở mọi request.
                            </p>
                        </div>
                    </section>

                    <!-- NHÓM ENDPOINT KHỞI TẠO (PROFILE, PLANS, CREATE, STATUS) -->
                    <EndpointSectionCard
                        v-for="doc in preCreateEndpoints"
                        :key="doc.id"
                        :doc="doc"
                        :base-url="baseUrl"
                        :sample-token="sampleToken"
                        v-model:code-lang="codeLang"
                        :catalog-plans="catalogPlans"
                        :os-list="osList"
                    />

                    <!-- MỤC WEBHOOK CALLBACK -->
                    <section id="sec-webhook" class="surface-card p-6 sm:p-8 space-y-6 scroll-mt-[84px]">
                        <div class="space-y-2.5 pb-5 border-b border-slate-200/90">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-purple-100 text-purple-800 font-mono text-xs font-extrabold">
                                <Webhook class="w-3.5 h-3.5" />
                                <span>WEBHOOK CALLBACK · POST JSON</span>
                            </div>
                            <h2 class="text-lg sm:text-[20px] font-extrabold text-[#0B1437] tracking-tight">
                                Nhận kết quả tự động qua Webhook (`vps.created` &amp; `vps.failed`)
                            </h2>
                            <p class="text-[15px] text-slate-700 leading-7">
                                Ngay khi máy chủ hoàn tất cài đặt Hệ điều hành và có địa chỉ IP thực tế (hoặc khi phát sinh sự cố cần hoàn tiền tự động), LugiaCloud sẽ gửi một HTTP <code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 text-xs font-bold">POST</code> định dạng JSON tới đường dẫn Webhook bạn đã khai báo.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                            <CodeBlockCard
                                title="1A. JSON khi cấp phát thành công (vps.created)"
                                :code="WEBHOOK_CREATED_SAMPLE"
                                copy-label="JSON vps.created"
                                tone="success"
                                badge="vps.created"
                            />
                            <CodeBlockCard
                                title="1B. JSON khi lỗi & hoàn tiền (vps.failed)"
                                :code="WEBHOOK_FAILED_SAMPLE"
                                copy-label="JSON vps.failed"
                                tone="danger"
                                badge="vps.failed"
                            />
                        </div>

                        <CodeBlockCard
                            title="2. Code PHP mẫu nhận & xử lý Webhook trên Server của bạn"
                            :code="WEBHOOK_PHP_RECEIVER"
                            copy-label="code PHP nhận Webhook"
                        />

                        <div class="p-4 rounded-xl bg-emerald-50/90 border border-emerald-200 flex items-start gap-3 text-[14px] text-emerald-950 leading-relaxed">
                            <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                            <div>
                                <strong>Quy tắc xác nhận (Acknowledge):</strong> Endpoint nhận Webhook bên phía bạn cần phản hồi mã <code class="px-1.5 py-0.5 rounded bg-white border border-emerald-200 text-xs font-bold">HTTP 200 OK</code> trong vòng 10 giây. Nếu website của bạn viết bằng Laravel, hãy thêm đường dẫn nhận Webhook vào danh sách ngoại lệ CSRF (<code class="font-mono text-[13px]">validateCsrfTokens(except: [...])</code>).
                            </div>
                        </div>
                    </section>

                    <!-- NHÓM ENDPOINT QUẢN TRỊ VÒNG ĐỜI VPS -->
                    <EndpointSectionCard
                        v-for="doc in lifecycleEndpoints"
                        :key="doc.id"
                        :doc="doc"
                        :base-url="baseUrl"
                        :sample-token="sampleToken"
                        v-model:code-lang="codeLang"
                    />

                    <!-- NHÓM ENDPOINT DỊCH VỤ PROXY IPv4 -->
                    <EndpointSectionCard
                        v-for="doc in proxyEndpoints"
                        :key="doc.id"
                        :doc="doc"
                        :base-url="baseUrl"
                        :sample-token="sampleToken"
                        v-model:code-lang="codeLang"
                    />

                    <!-- MỤC CUỐI: BẢNG TỔNG HỢP MÃ LỖI HTTP -->
                    <section id="sec-errors" class="surface-card p-6 sm:p-8 space-y-6 scroll-mt-[84px]">
                        <div class="space-y-2 pb-5 border-b border-slate-200/90">
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-amber-100 text-amber-900 font-mono text-xs font-extrabold">
                                <ShieldAlert class="w-3.5 h-3.5" />
                                <span>HTTP STATUS CODES</span>
                            </div>
                            <h2 class="text-lg sm:text-[20px] font-extrabold text-[#0B1437] tracking-tight">
                                Bảng tổng hợp Mã phản hồi &amp; Mã lỗi HTTP
                            </h2>
                            <p class="text-[15px] text-slate-700 leading-7">
                                Dựa vào HTTP Status Code trả về để lập trình luồng xử lý tự động trên hệ thống của bạn (như báo hết số dư cho quản trị viên hoặc tự động hoàn tiền cho khách mua lẻ).
                            </p>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="err in HTTP_ERROR_SPECS"
                                :key="err.code"
                                class="p-5 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3"
                            >
                                <div class="flex flex-wrap items-center gap-3">
                                    <span
                                        class="px-3 py-1 rounded-md border font-mono text-xs font-extrabold whitespace-nowrap"
                                        :class="errorBadgeClasses(err.tone)"
                                    >
                                        HTTP {{ err.code }}
                                    </span>
                                    <h3 class="text-[15.5px] font-bold text-[#0B1437]">{{ err.name }}</h3>
                                </div>
                                <p class="text-[14.5px] text-slate-700 leading-relaxed">
                                    {{ err.desc }}
                                </p>
                                <pre class="p-3.5 rounded-lg bg-[#0B1437] text-slate-100 text-[13px] font-mono overflow-x-auto leading-relaxed"><code>{{ err.sample }}</code></pre>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
