<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import YoutubeFacade from '@/Components/YoutubeFacade.vue';
import { renderMarkdown } from '@/Composables/useMarkdown';
import { requireAuthOrVisit } from '@/Composables/useLoginPrompt';
import {
    ArrowLeft,
    ArrowRight,
    Clock,
    Eye,
    Calendar,
    List,
    Server,
    Globe,
    MessageCircle,
} from 'lucide-vue-next';

const props = defineProps<{
    article: {
        id: number;
        title: string;
        slug: string;
        category: string;
        category_label: string;
        thumbnail_url: string | null;
        youtube_id: string | null;
        excerpt: string;
        content: string;
        meta_title: string;
        meta_description: string;
        cta_service: 'vps' | 'proxy' | 'none';
        views: number;
        reading_minutes: number;
        published_at: string;
        updated_at: string;
    };
    related: Array<any>;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const isVpsOutOfStock = computed(() => Boolean((page.props.system as any)?.vps_out_of_stock));
const LayoutComponent = computed(() => (user.value ? ClientLayout : PublicLayout));

const parsed = computed(() => renderMarkdown(props.article.content));

function goCtaService() {
    if (props.article.cta_service === 'proxy') {
        requireAuthOrVisit('/proxy/store', 'Xem bảng giá & đăng ký Proxy IPv4', user.value);
        return;
    }
    if (isVpsOutOfStock.value) return;
    requireAuthOrVisit('/store', 'Xem bảng giá & khởi tạo Cloud VPS SSD', user.value);
}
</script>

<template>
    <component :is="LayoutComponent" :title="article.title">
        <Head :title="article.meta_title || article.title">
            <meta name="description" :content="article.meta_description" />
        </Head>

        <div :class="!user ? 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10' : ''" class="space-y-8">
            <!-- Breadcrumb -->
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 font-medium">
                <Link href="/blog" class="inline-flex items-center gap-1.5 font-bold text-slate-600 hover:text-[#1D6AE5] transition-colors">
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Blog &amp; Hướng dẫn</span>
                </Link>
                <span>/</span>
                <Link :href="`/blog?category=${article.category}`" class="hover:text-[#1D6AE5] transition-colors">
                    {{ article.category_label }}
                </Link>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-7 items-start">
                <!-- Main Article Column -->
                <article class="lg:col-span-8 surface-card rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden bg-white">
                    <div class="p-6 sm:p-8 space-y-5">
                        <div class="flex flex-wrap items-center gap-2.5 text-xs">
                            <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-[#1D6AE5] border border-blue-200 font-bold">
                                {{ article.category_label }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-slate-500 font-medium">
                                <Calendar class="w-3.5 h-3.5" /> {{ article.published_at }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-slate-500 font-medium">
                                <Clock class="w-3.5 h-3.5" /> {{ article.reading_minutes }} phút đọc
                            </span>
                            <span class="inline-flex items-center gap-1 text-slate-500 font-mono-num font-medium">
                                <Eye class="w-3.5 h-3.5" /> {{ article.views }} lượt xem
                            </span>
                        </div>

                        <h1 class="text-2xl sm:text-3xl font-black text-[#0B1437] tracking-tight leading-tight">
                            {{ article.title }}
                        </h1>

                        <p v-if="article.excerpt" class="text-sm sm:text-[15px] text-slate-600 leading-relaxed bg-slate-50 border border-slate-200/80 rounded-xl p-4">
                            {{ article.excerpt }}
                        </p>

                        <!-- Cover Image (Cloudflare R2 CDN) -->
                        <div v-if="article.thumbnail_url" class="rounded-2xl overflow-hidden border border-slate-200/90">
                            <img
                                :src="article.thumbnail_url"
                                :alt="article.title"
                                decoding="async"
                                class="w-full max-h-[420px] object-cover block"
                            />
                        </div>

                        <!-- Optional YouTube Tutorial (Lazy Facade for 95-100 PageSpeed) -->
                        <YoutubeFacade
                            v-if="article.youtube_id"
                            :youtube-id="article.youtube_id"
                            :title="article.title"
                        />

                        <!-- Rendered Safe Markdown Body -->
                        <div class="pt-2" v-html="parsed.html" />
                    </div>
                </article>

                <!-- Sticky Sidebar: TOC + Service CTA + Support -->
                <aside class="lg:col-span-4 space-y-5 lg:sticky lg:top-20">
                    <!-- Table of Contents -->
                    <div v-if="parsed.toc.length > 0" class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-3 bg-white">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-[#0B1437]">
                            <List class="w-4 h-4 text-[#1D6AE5]" />
                            <span>Mục lục bài viết</span>
                        </div>
                        <nav class="space-y-1.5 text-xs">
                            <a
                                v-for="item in parsed.toc"
                                :key="item.id"
                                :href="`#${item.id}`"
                                class="block py-1 text-slate-600 hover:text-[#1D6AE5] font-medium transition-colors"
                                :class="item.level === 3 ? 'pl-3.5 text-[11.5px] text-slate-500' : 'font-semibold text-slate-700'"
                            >
                                {{ item.text }}
                            </a>
                        </nav>
                    </div>

                    <!-- Contextual Conversion CTA -->
                    <div
                        v-if="article.cta_service !== 'none'"
                        class="rounded-2xl p-5 text-white bg-[#0B1437] shadow-md space-y-3.5"
                    >
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/15 text-[11px] font-bold text-sky-200">
                            <Server v-if="article.cta_service === 'vps'" class="w-3.5 h-3.5" />
                            <Globe v-else class="w-3.5 h-3.5" />
                            <span>{{ article.cta_service === 'vps' ? 'Cloud VPS SSD FPT HCM' : 'Proxy IPv4 Việt Nam Sạch' }}</span>
                        </div>
                        <h3 class="text-base font-extrabold leading-snug">
                            {{
                                article.cta_service === 'vps'
                                    ? 'Cần thuê Cloud VPS SSD ổn định, cấp IP tự động 2 phút?'
                                    : 'Cần Proxy IPv4 Dân cư / Datacenter sạch, băng thông không giới hạn?'
                            }}
                        </h3>
                        <p class="text-xs text-blue-100/90 leading-relaxed">
                            {{
                                article.cta_service === 'vps'
                                    ? 'Chip Intel Xeon Gold/Platinum, 100% SSD chuyên dụng, Full Root/Admin & hoàn tiền 100% nếu lỗi khởi tạo.'
                                    : 'Tương thích mượt AdsPower, GoLogin, Proxifier, hỗ trợ đồng thời HTTP & SOCKS5.'
                            }}
                        </p>
                        <button
                            type="button"
                            :disabled="article.cta_service === 'vps' && isVpsOutOfStock"
                            @click="goCtaService"
                            class="w-full py-2.5 px-4 rounded-xl bg-white hover:bg-blue-50 disabled:opacity-60 disabled:cursor-not-allowed text-[#0B1437] text-xs font-extrabold inline-flex items-center justify-center gap-1.5 cursor-pointer transition"
                        >
                            <span>
                                {{
                                    article.cta_service === 'vps' && isVpsOutOfStock
                                        ? 'Cloud VPS đang tạm hết hàng'
                                        : article.cta_service === 'vps'
                                          ? 'Xem bảng giá & Khởi tạo VPS'
                                          : 'Xem bảng giá & Mua Proxy ngay'
                                }}
                            </span>
                            <ArrowRight v-if="!(article.cta_service === 'vps' && isVpsOutOfStock)" class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Quick Support -->
                    <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs bg-white space-y-3 text-xs">
                        <div class="font-extrabold text-[#0B1437] flex items-center gap-2">
                            <MessageCircle class="w-4 h-4 text-[#0068FF]" />
                            <span>Cần hỗ trợ thao tác trực tiếp?</span>
                        </div>
                        <p class="text-slate-600 leading-relaxed">
                            Đội ngũ kỹ thuật LugiaCloud trực 24/7 sẵn sàng hỗ trợ cài đặt, mở port và xử lý lỗi nhanh qua Zalo.
                        </p>
                        <a
                            href="https://zalo.me/0862579104"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="w-full py-2 px-3 rounded-xl bg-[#0068FF] hover:bg-[#0057D9] text-white font-bold inline-flex items-center justify-center gap-1.5 transition"
                        >
                            <span>Nhắn Zalo kỹ thuật 24/7</span>
                        </a>
                    </div>
                </aside>
            </div>

            <!-- Related Articles -->
            <section v-if="related.length > 0" class="space-y-4">
                <h2 class="text-lg font-extrabold text-[#0B1437]">Bài viết liên quan</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Link
                        v-for="r in related"
                        :key="r.id"
                        :href="`/blog/${r.slug}`"
                        class="group surface-card-interactive p-5 rounded-2xl border border-slate-200/90 bg-white flex flex-col justify-between gap-3"
                    >
                        <div class="space-y-2">
                            <span class="inline-block px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-bold">
                                {{ r.category_label }}
                            </span>
                            <h3 class="text-sm font-extrabold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors line-clamp-2">
                                {{ r.title }}
                            </h3>
                            <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">{{ r.excerpt }}</p>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-100">
                            <span>{{ r.published_at }}</span>
                            <span class="font-bold text-[#1D6AE5]">Đọc tiếp →</span>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </component>
</template>
