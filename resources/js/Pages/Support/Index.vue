<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import {
    MessageCircle,
    Phone,
    Globe,
    Flag,
    ShieldCheck,
    CheckCircle2,
    XCircle,
    Clock,
    HelpCircle,
    ShieldAlert,
    ExternalLink,
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const LayoutComponent = computed(() => (user.value ? ClientLayout : PublicLayout));

const channels = [
    {
        title: 'Hỗ trợ qua Zalo',
        desc: 'Nhắn tin trực tiếp với đội ngũ hỗ trợ qua Zalo cá nhân. Tư vấn cấu hình & custom riêng nhanh chóng.',
        cta: 'Nhắn tin Zalo',
        href: 'https://zalo.me/0862579104',
        external: true,
        icon: MessageCircle,
        iconBox: 'bg-blue-50 text-[#0068FF] border-blue-200/80',
        btnClass: 'bg-[#0068FF] hover:bg-[#0057D9] text-white',
    },
    {
        title: 'Hotline Khẩn cấp',
        desc: 'Cần xử lý gấp các sự cố kỹ thuật hoặc đơn hàng quan trọng? Gọi trực tiếp vào Hotline của LugiaCloud.',
        cta: 'Gọi 0862.579.104',
        href: 'tel:0862579104',
        external: false,
        icon: Phone,
        iconBox: 'bg-emerald-50 text-emerald-600 border-emerald-200/80',
        btnClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
    },
    {
        title: 'Facebook Cá nhân',
        desc: 'Kết nối qua Facebook cá nhân Admin để được hỗ trợ đối soát, tư vấn cấu hình và cập nhật thông tin.',
        cta: 'Liên hệ Facebook',
        href: 'https://www.facebook.com/profile.php?id=61592223410761',
        external: true,
        icon: Globe,
        iconBox: 'bg-indigo-50 text-[#1877F2] border-indigo-200/80',
        btnClass: 'bg-[#1877F2] hover:bg-[#1565D8] text-white',
    },
    {
        title: 'Fanpage Chính thức',
        desc: 'Theo dõi Page LugiaCloud để nhận thông báo bảo trì, sự kiện giảm giá mới nhất và các chương trình ưu đãi.',
        cta: 'Theo dõi Page ngay',
        href: 'https://www.facebook.com/profile.php?id=61591873231741',
        external: true,
        icon: Flag,
        iconBox: 'bg-sky-50 text-[#0084FF] border-sky-200/80',
        btnClass: 'bg-[#0084FF] hover:bg-[#0070D9] text-white',
    },
];

const faqs = [
    {
        q: 'Nạp tiền qua QR Code mất bao lâu?',
        a: 'Hệ thống áp dụng công nghệ khớp lệnh ngân hàng tự động. Thường chỉ mất 1 – 3 phút sau khi chuyển khoản đúng nội dung là tiền sẽ tự động cộng vào tài khoản của bạn.',
    },
    {
        q: 'Mua VPS / Proxy xong bao lâu có IP?',
        a: 'Đối với Proxy, thông tin IP:Port:User:Pass thường được trả về ngay lập tức. Đối với Cloud VPS, hệ thống cần khoảng 2 – 5 phút để tự động cài đặt hệ điều hành và thiết lập IP.',
    },
    {
        q: 'Tôi có thể tự cài lại Hệ điều hành (Reinstall) không?',
        a: 'Hoàn toàn được! Bạn chỉ cần vào phần Quản lý VPS, chọn tính năng "Cài lại Hệ điều hành" và đợi hệ thống tự động chạy trong vài phút.',
    },
    {
        q: 'Mua thêm ổ cứng SSD rồi tại sao trong Windows chưa thấy tăng dung lượng?',
        a: 'Sau khi nâng cấp thêm dung lượng ổ cứng, nếu phân vùng trong Windows chưa tự mở rộng, bạn bấm tổ hợp phím Win + R -> nhập diskmgmt.msc -> nhấn Enter rồi chuột phải vào ổ C chọn Extend Volume.',
    },
    {
        q: 'Bên mình có hỗ trợ mở Port hoặc đặt cấu hình Custom riêng không?',
        a: 'Có! Toàn bộ Cloud VPS được cấp quyền quản trị cao nhất (Root / Administrator) và hỗ trợ mở đầy đủ cổng TCP/UDP Port theo nhu cầu. Nếu cần cấu hình riêng ngoài bảng giá, hãy nhắn trực tiếp Zalo 0862.579.104 để được hỗ trợ 24/7.',
    },
];
</script>

<template>
    <component :is="LayoutComponent" title="Trung tâm Hỗ trợ">
        <Head title="Trung tâm Hỗ trợ — LugiaCloud" />

        <div :class="!user ? 'max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12' : ''" class="space-y-6">
            <!-- Header -->
            <div class="surface-card p-6 sm:p-7 flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-slate-200/90 shadow-xs">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-[#1D6AE5]">Hỗ trợ kỹ thuật 24/7</span>
                    <h1 class="text-2xl font-black text-[#0B1437] mt-1">Trung tâm Hỗ trợ LugiaCloud</h1>
                    <p class="text-xs sm:text-sm text-slate-600 font-medium mt-1">
                        Hỗ trợ kỹ thuật trực tiếp, tư vấn cấu hình phù hợp, nhận custom máy chủ riêng và đối soát giao dịch.
                    </p>
                </div>
                <a
                    href="https://zalo.me/g/bh9hrrcujly5ab2pqmfj"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-200 text-xs font-bold text-[#0B1437] shrink-0 transition shadow-2xs active:scale-95"
                >
                    <span>Tham gia Nhóm Zalo Cộng đồng</span>
                    <ExternalLink class="w-3.5 h-3.5 text-[#1D6AE5]" />
                </a>
            </div>

            <!-- 4 Kênh Liên hệ thực tế -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    v-for="ch in channels"
                    :key="ch.title"
                    class="surface-card-interactive p-5 flex flex-col justify-between text-center items-center rounded-2xl border border-slate-200/90 shadow-xs"
                >
                    <div class="flex flex-col items-center">
                        <div class="w-12 h-12 rounded-xl border flex items-center justify-center mb-3.5" :class="ch.iconBox">
                            <component :is="ch.icon" class="w-5 h-5" />
                        </div>
                        <h3 class="text-sm font-extrabold text-[#0B1437]">{{ ch.title }}</h3>
                        <p class="text-xs text-slate-600 font-medium mt-1.5 leading-relaxed mb-5">{{ ch.desc }}</p>
                    </div>
                    <a
                        :href="ch.href"
                        :target="ch.external ? '_blank' : undefined"
                        :rel="ch.external ? 'noopener noreferrer' : undefined"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-xs active:scale-95"
                        :class="ch.btnClass"
                    >
                        <span>{{ ch.cta }}</span>
                    </a>
                </div>
            </div>

            <!-- Chính sách Bảo hành & Câu hỏi thường gặp -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Chính sách Bảo hành -->
                <div class="lg:col-span-5 surface-card p-6 space-y-5 rounded-2xl border border-slate-200/90 shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200/80 text-amber-700 flex items-center justify-center shrink-0">
                            <ShieldCheck class="w-5 h-5" />
                        </div>
                        <h2 class="text-base font-extrabold text-[#0B1437]">Chính sách Bảo hành</h2>
                    </div>

                    <ul class="space-y-4 text-xs sm:text-[13px]">
                        <li class="flex items-start gap-3">
                            <CheckCircle2 class="w-4.5 h-4.5 text-emerald-600 shrink-0 mt-0.5" />
                            <div>
                                <strong class="block text-slate-900 font-bold">Hoàn tiền 100% tự động</strong>
                                <span class="text-slate-600 font-medium leading-relaxed">
                                    Hệ thống tự động hoàn tiền vào số dư tài khoản nếu quá trình khởi tạo VPS / Proxy gặp lỗi từ phía máy chủ.
                                </span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <XCircle class="w-4.5 h-4.5 text-rose-500 shrink-0 mt-0.5" />
                            <div>
                                <strong class="block text-slate-900 font-bold">Trường hợp KHÔNG bảo hành</strong>
                                <span class="text-slate-600 font-medium leading-relaxed">
                                    Không hoàn tiền cho VPS / Proxy đang hoạt động bình thường, hoặc bị khóa do vi phạm quy định sử dụng (DDoS, Scan SSH, vi phạm pháp luật...).
                                </span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <Clock class="w-4.5 h-4.5 text-[#1D6AE5] shrink-0 mt-0.5" />
                            <div>
                                <strong class="block text-slate-900 font-bold">Thời gian xử lý khiếu nại</strong>
                                <span class="text-slate-600 font-medium leading-relaxed">
                                    Cam kết tiếp nhận và xử lý các sự cố kỹ thuật trong vòng 15 – 30 phút (trong giờ hành chính) và trực hỗ trợ khẩn cấp 24/7.
                                </span>
                            </div>
                        </li>
                    </ul>

                    <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/70 flex items-start gap-2.5 text-xs text-amber-950 font-medium leading-relaxed">
                        <ShieldAlert class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                        <span>
                            Email liên hệ hợp tác &amp; Đại lý API:
                            <a href="mailto:quanpc2004@gmail.com" class="font-mono font-bold underline">quanpc2004@gmail.com</a>
                        </span>
                    </div>
                </div>

                <!-- Câu hỏi thường gặp -->
                <div class="lg:col-span-7 surface-card p-6 space-y-4 rounded-2xl border border-slate-200/90 shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 border border-blue-200/80 text-[#1D6AE5] flex items-center justify-center shrink-0">
                            <HelpCircle class="w-5 h-5" />
                        </div>
                        <h2 class="text-base font-extrabold text-[#0B1437]">Câu hỏi thường gặp (FAQ)</h2>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(item, idx) in faqs"
                            :key="idx"
                            class="p-4 rounded-xl bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 transition"
                        >
                            <h3 class="text-xs sm:text-sm font-extrabold text-[#0B1437]">{{ item.q }}</h3>
                            <p class="text-xs text-slate-600 font-medium mt-1.5 leading-relaxed">{{ item.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
