<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import {
    Search,
    BookOpen,
    Clock,
    Eye,
    ArrowRight,
    PlayCircle,
    Sparkles,
    Server,
    Globe,
    FileText,
} from 'lucide-vue-next';

const props = defineProps<{
    featured: any | null;
    articles: { data: Array<any>; links: Array<any> };
    categories: Record<string, string>;
    categoryCounts: Record<string, number>;
    totalPublished: number;
    filters: { category: string; search: string };
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);
const LayoutComponent = computed(() => (user.value ? ClientLayout : PublicLayout));

const search = ref(props.filters.search || '');

function filterBy(category: string) {
    router.get(
        '/blog',
        {
            category: category === 'all' ? undefined : category,
            search: search.value.trim() || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
}

function submitSearch() {
    filterBy(props.filters.category || 'all');
}

function categoryBadgeClass(cat: string): string {
    switch (cat) {
        case 'vps':
            return 'bg-blue-50 text-[#1D6AE5] border-blue-200';
        case 'proxy':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'seo':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
}
</script>

<template>
    <component :is="LayoutComponent" title="Blog & Hướng dẫn sử dụng">
        <Head title="Blog & Hướng dẫn Cloud VPS, Proxy IPv4" />

        <div :class="!user ? 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12' : ''" class="space-y-6">
            <!-- Header + Search -->
            <div class="surface-card p-6 sm:p-7 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div class="space-y-1.5 max-w-2xl">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-[#1D6AE5]">
                        <BookOpen class="w-3.5 h-3.5" />
                        <span>Thư viện Hướng dẫn &amp; Kiến thức Hạ tầng</span>
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-black text-[#0B1437] tracking-tight">
                        Blog &amp; Hướng dẫn sử dụng LugiaCloud
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Hướng dẫn đăng nhập VPS Windows/Linux, mở rộng SSD, cài Proxy IPv4 vào AdsPower/GoLogin/Proxifier và kinh nghiệm tối ưu hạ tầng thực chiến.
                    </p>
                </div>

                <form @submit.prevent="submitSearch" class="relative w-full lg:w-80 shrink-0">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm bài viết (RDP, SSH, AdsPower, SSD...)"
                        class="w-full h-11 pl-10 pr-4 rounded-xl bg-slate-50 focus:bg-white border border-slate-300 focus:border-[#1D6AE5] outline-none text-xs sm:text-[13px] font-medium transition"
                    />
                </form>
            </div>

            <!-- Category Filter Pills -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    @click="filterBy('all')"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold border transition cursor-pointer"
                    :class="
                        filters.category === 'all'
                            ? 'bg-[#0B1437] text-white border-[#0B1437] shadow-xs'
                            : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 hover:bg-slate-50'
                    "
                >
                    Tất cả ({{ totalPublished }})
                </button>
                <button
                    v-for="(label, key) in categories"
                    :key="key"
                    type="button"
                    @click="filterBy(String(key))"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold border transition cursor-pointer"
                    :class="
                        filters.category === key
                            ? 'bg-[#0B1437] text-white border-[#0B1437] shadow-xs'
                            : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 hover:bg-slate-50'
                    "
                >
                    {{ label }} ({{ categoryCounts[key] || 0 }})
                </button>
            </div>

            <!-- Featured Spotlight -->
            <Link
                v-if="featured"
                :href="`/blog/${featured.slug}`"
                class="group surface-card-interactive overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs grid grid-cols-1 lg:grid-cols-12"
            >
                <div class="lg:col-span-5 relative bg-[#0B1437] min-h-[210px] flex items-center justify-center overflow-hidden">
                    <img
                        v-if="featured.thumbnail_url"
                        :src="featured.thumbnail_url"
                        :alt="featured.title"
                        class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300"
                    />
                    <div v-else class="p-8 text-white/90 text-center space-y-3">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-white/12 border border-white/20 flex items-center justify-center">
                            <Server v-if="featured.category === 'vps'" class="w-7 h-7 text-sky-300" />
                            <Globe v-else-if="featured.category === 'proxy'" class="w-7 h-7 text-emerald-300" />
                            <FileText v-else class="w-7 h-7 text-amber-300" />
                        </div>
                        <div class="text-xs font-bold uppercase tracking-wider text-sky-200">Bài hướng dẫn nổi bật</div>
                    </div>
                    <span
                        v-if="featured.has_video"
                        class="absolute top-3.5 left-3.5 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-950/75 text-white text-[11px] font-bold backdrop-blur-xs"
                    >
                        <PlayCircle class="w-3.5 h-3.5 text-red-400" />
                        <span>Có Video</span>
                    </span>
                </div>

                <div class="lg:col-span-7 p-6 sm:p-7 flex flex-col justify-between gap-4 bg-white">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 font-bold text-[11px]">
                                <Sparkles class="w-3 h-3" />
                                <span>Nổi bật</span>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-md border text-[11px] font-bold" :class="categoryBadgeClass(featured.category)">
                                {{ featured.category_label }}
                            </span>
                            <span class="text-slate-400 font-medium">{{ featured.published_at }}</span>
                        </div>

                        <h2 class="text-xl sm:text-2xl font-extrabold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors leading-snug">
                            {{ featured.title }}
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed line-clamp-3">
                            {{ featured.excerpt }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-100 text-xs">
                        <div class="flex items-center gap-4 text-slate-500 font-medium">
                            <span class="inline-flex items-center gap-1">
                                <Clock class="w-3.5 h-3.5" /> {{ featured.reading_minutes }} phút đọc
                            </span>
                            <span class="inline-flex items-center gap-1 font-mono-num">
                                <Eye class="w-3.5 h-3.5" /> {{ featured.views }} lượt xem
                            </span>
                        </div>
                        <span class="inline-flex items-center gap-1 font-bold text-[#1D6AE5]">
                            <span>Đọc ngay</span>
                            <ArrowRight class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
                        </span>
                    </div>
                </div>
            </Link>

            <!-- Articles Grid -->
            <div v-if="articles.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <Link
                    v-for="item in articles.data"
                    :key="item.id"
                    :href="`/blog/${item.slug}`"
                    class="group surface-card-interactive overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs flex flex-col justify-between bg-white"
                >
                    <div>
                        <div class="relative aspect-[16/9] bg-[#0B1437] overflow-hidden flex items-center justify-center">
                            <img
                                v-if="item.thumbnail_url"
                                :src="item.thumbnail_url"
                                :alt="item.title"
                                loading="lazy"
                                decoding="async"
                                class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300"
                            />
                            <div v-else class="flex flex-col items-center gap-2 text-white/85">
                                <Server v-if="item.category === 'vps'" class="w-8 h-8 text-sky-300" />
                                <Globe v-else-if="item.category === 'proxy'" class="w-8 h-8 text-emerald-300" />
                                <BookOpen v-else class="w-8 h-8 text-blue-200" />
                                <span class="text-[11px] font-bold uppercase tracking-wider text-blue-100">{{ item.category_label }}</span>
                            </div>
                            <span
                                v-if="item.has_video"
                                class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-950/75 text-white text-[10.5px] font-bold"
                            >
                                <PlayCircle class="w-3 h-3 text-red-400" /> Video
                            </span>
                        </div>

                        <div class="p-5 space-y-2.5">
                            <div class="flex items-center justify-between gap-2 text-[11px]">
                                <span class="px-2 py-0.5 rounded-md border font-bold" :class="categoryBadgeClass(item.category)">
                                    {{ item.category_label }}
                                </span>
                                <span class="text-slate-400 font-medium">{{ item.published_at }}</span>
                            </div>
                            <h3 class="text-[15.5px] font-extrabold text-[#0B1437] group-hover:text-[#1D6AE5] transition-colors leading-snug line-clamp-2">
                                {{ item.title }}
                            </h3>
                            <p class="text-xs text-slate-600 leading-relaxed line-clamp-3">
                                {{ item.excerpt }}
                            </p>
                        </div>
                    </div>

                    <div class="px-5 py-3.5 border-t border-slate-100 flex items-center justify-between text-[11.5px] text-slate-500 font-medium">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1"><Clock class="w-3.5 h-3.5" /> {{ item.reading_minutes }}p</span>
                            <span class="inline-flex items-center gap-1 font-mono-num"><Eye class="w-3.5 h-3.5" /> {{ item.views }}</span>
                        </div>
                        <span class="font-bold text-[#1D6AE5] inline-flex items-center gap-1">
                            Xem bài <ArrowRight class="w-3.5 h-3.5" />
                        </span>
                    </div>
                </Link>
            </div>

            <div
                v-else-if="!featured"
                class="surface-card p-10 text-center rounded-2xl border border-slate-200/90 space-y-2"
            >
                <BookOpen class="w-8 h-8 text-slate-300 mx-auto" />
                <div class="text-sm font-bold text-[#0B1437]">Chưa tìm thấy bài viết phù hợp</div>
                <p class="text-xs text-slate-500">Hãy thử từ khóa khác hoặc chọn xem tất cả chuyên mục.</p>
            </div>

            <PaginationBar v-if="articles.links && articles.links.length > 3" :links="articles.links" />
        </div>
    </component>
</template>
