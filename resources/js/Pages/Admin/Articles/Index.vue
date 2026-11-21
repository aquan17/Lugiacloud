<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { askConfirm } from '@/Composables/useConfirmAction';
import { Plus, Edit3, Trash2, Power, Search, ExternalLink, BookOpen } from 'lucide-vue-next';

const props = defineProps<{
    articles: { data: Array<any>; links: Array<any> };
    categories: Record<string, string>;
    filters: { search: string; category: string };
    stats: { total: number; published: number; views: number };
}>();

const search = ref(props.filters.search || '');

function submitFilter(category?: string) {
    router.get(
        '/admin/articles',
        {
            category: (category ?? props.filters.category) === 'all' ? undefined : (category ?? props.filters.category),
            search: search.value.trim() || undefined,
        },
        { preserveState: true }
    );
}

function deleteArticle(a: any) {
    askConfirm({
        title: 'Xóa bài viết Blog',
        message: `Bạn có chắc muốn xóa bài viết "${a.title}"? Ảnh bìa trên Cloudflare R2 cũng sẽ được dọn dẹp tự động.`,
        confirmText: 'Xóa bài viết',
        onConfirm: () => router.delete(`/admin/articles/${a.id}`, { preserveScroll: true }),
    });
}
</script>

<template>
    <AdminLayout title="Quản lý Blog & Bài viết Hướng dẫn SEO">
        <Head title="Blog & Hướng dẫn SEO" />

        <div class="space-y-5">
            <!-- Top KPI & Actions -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 font-bold text-slate-800 shadow-2xs">
                        Tổng bài viết: <strong class="font-mono-num text-[#0B1437]">{{ stats.total }}</strong>
                    </span>
                    <span class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 font-bold text-slate-800 shadow-2xs">
                        Đang hiển thị: <strong class="font-mono-num text-emerald-700">{{ stats.published }}</strong>
                    </span>
                    <span class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 font-bold text-slate-800 shadow-2xs">
                        Tổng lượt đọc: <strong class="font-mono-num text-[#1D6AE5]">{{ stats.views }}</strong>
                    </span>
                </div>

                <div class="flex items-center gap-2.5">
                    <Link
                        href="/blog"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition shadow-2xs"
                    >
                        <ExternalLink class="w-3.5 h-3.5 text-[#1D6AE5]" />
                        <span>Xem trang /blog</span>
                    </Link>
                    <Link
                        href="/admin/articles/create"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#0B1437] hover:bg-[#162354] text-white text-xs font-bold transition shadow-xs"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Viết bài mới</span>
                    </Link>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex flex-wrap p-1 rounded-xl bg-white border border-slate-200 text-xs shadow-2xs">
                    <button
                        type="button"
                        @click="submitFilter('all')"
                        class="px-3 py-1.5 rounded-lg font-bold cursor-pointer transition"
                        :class="filters.category === 'all' ? 'bg-[#0B1437] text-white' : 'text-slate-600 hover:text-slate-900'"
                    >
                        Tất cả
                    </button>
                    <button
                        v-for="(label, key) in categories"
                        :key="key"
                        type="button"
                        @click="submitFilter(String(key))"
                        class="px-3 py-1.5 rounded-lg font-bold cursor-pointer transition"
                        :class="filters.category === key ? 'bg-[#0B1437] text-white' : 'text-slate-600 hover:text-slate-900'"
                    >
                        {{ label }}
                    </button>
                </div>

                <form @submit.prevent="submitFilter()" class="relative w-full sm:w-72">
                    <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Tìm tiêu đề, slug bài viết..."
                        class="w-full pl-8 pr-3 py-2 rounded-xl bg-white border border-slate-300 text-xs font-medium focus:outline-none focus:border-[#1D6AE5] shadow-2xs"
                    />
                </form>
            </div>

            <!-- Articles Table -->
            <div class="surface-card overflow-hidden rounded-2xl border border-slate-200/90 shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-wider text-slate-600 whitespace-nowrap">
                                <th class="py-3 px-4">Bài viết</th>
                                <th class="py-3 px-4">Chuyên mục</th>
                                <th class="py-3 px-4 text-right">Lượt xem</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4">Ngày đăng</th>
                                <th class="py-3 px-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80">
                            <tr v-for="a in articles.data" :key="a.id" class="hover:bg-slate-50/70 transition">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-14 h-9 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                                            <img v-if="a.thumbnail_url" :src="a.thumbnail_url" alt="" loading="lazy" decoding="async" class="w-full h-full object-cover" />
                                            <BookOpen v-else class="w-4 h-4 text-slate-400" />
                                        </div>
                                        <div class="min-w-0 max-w-md">
                                            <div class="flex items-center gap-1.5">
                                                <span v-if="a.is_featured" class="px-1.5 py-0.2 rounded bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-bold shrink-0">Nổi bật</span>
                                                <Link
                                                    :href="`/admin/articles/${a.id}/edit`"
                                                    class="font-bold text-[#0B1437] hover:text-[#1D6AE5] truncate transition-colors"
                                                >
                                                    {{ a.title }}
                                                </Link>
                                            </div>
                                            <div class="font-mono text-[11px] text-slate-500 truncate mt-0.5">/blog/{{ a.slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="px-2.5 py-0.5 rounded-md bg-blue-50 text-[#1D6AE5] border border-blue-200 font-bold">
                                        {{ a.category_label }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono-num font-bold text-slate-700 whitespace-nowrap">
                                    {{ a.views }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span
                                        class="px-2.5 py-0.5 rounded-md font-bold border"
                                        :class="
                                            a.is_published
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                : 'bg-slate-100 text-slate-600 border-slate-200'
                                        "
                                    >
                                        {{ a.is_published ? 'Công khai' : 'Bản nháp' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono-num text-slate-600 whitespace-nowrap">{{ a.published_at }}</td>
                                <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1.5">
                                    <Link
                                        :href="`/blog/${a.slug}`"
                                        target="_blank"
                                        class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 hover:text-[#1D6AE5] inline-flex items-center align-middle transition"
                                        title="Xem bài trên /blog"
                                    >
                                        <ExternalLink class="w-3.5 h-3.5" />
                                    </Link>
                                    <Link
                                        :href="`/admin/articles/${a.id}/edit`"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-blue-50 hover:border-blue-200 text-slate-700 hover:text-[#1D6AE5] font-bold transition align-middle"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                        <span>Sửa</span>
                                    </Link>
                                    <button
                                        type="button"
                                        @click="router.post(`/admin/articles/${a.id}/toggle`, {}, { preserveScroll: true })"
                                        class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 cursor-pointer align-middle"
                                        title="Ẩn / Hiện bài viết"
                                    >
                                        <Power class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteArticle(a)"
                                        class="p-1.5 rounded-lg border border-rose-200 hover:bg-rose-50 text-rose-600 cursor-pointer align-middle"
                                        title="Xóa bài viết"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PaginationBar :links="articles.links" />
        </div>
    </AdminLayout>
</template>
