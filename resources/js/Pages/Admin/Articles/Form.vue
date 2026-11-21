<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useToast } from '@/Composables/useToast';
import { renderMarkdown } from '@/Composables/useMarkdown';
import {
    ArrowLeft,
    Save,
    Loader2,
    Upload,
    X,
    Eye,
    Columns2,
    PenLine,
    Heading2,
    Heading3,
    Bold,
    Italic,
    Code,
    FileCode2,
    List,
    ListOrdered,
    Quote,
    Link2,
    Image as ImageIcon,
    Table2,
    HelpCircle,
    Film,
    Youtube,
    Sparkles,
    CheckCircle2,
    AlertCircle,
    ExternalLink,
    Target,
} from 'lucide-vue-next';

const props = defineProps<{
    article: {
        id: number;
        title: string;
        slug: string;
        category: string;
        thumbnail_url: string | null;
        youtube_url: string | null;
        excerpt: string | null;
        content: string;
        meta_title: string | null;
        meta_description: string | null;
        cta_service: string;
        is_published: boolean;
        is_featured: boolean;
        views: number;
        published_at: string | null;
    } | null;
    categories: Record<string, string>;
    cdnUrl: string;
}>();

const toast = useToast();
const isEdit = computed(() => Boolean(props.article?.id));
const editorMode = ref<'write' | 'split' | 'preview'>('write');
const focusKeyword = ref('');

const uploadingImages = ref(false);
const pendingThumbFile = ref<File | null>(null);
const pendingThumbPreview = ref<string | null>(null);
const pendingInlineFiles = ref<Map<string, File>>(new Map());
const thumbFileInput = ref<HTMLInputElement | null>(null);
const inlineFileInput = ref<HTMLInputElement | null>(null);
const contentTextarea = ref<HTMLTextAreaElement | null>(null);

const form = useForm({
    title: props.article?.title || '',
    slug: props.article?.slug || '',
    category: props.article?.category || 'vps',
    thumbnail_url: props.article?.thumbnail_url || '',
    youtube_url: props.article?.youtube_url || '',
    excerpt: props.article?.excerpt || '',
    content: props.article?.content || '',
    meta_title: props.article?.meta_title || '',
    meta_description: props.article?.meta_description || '',
    cta_service: props.article?.cta_service || 'vps',
    is_published: props.article ? Boolean(props.article.is_published) : true,
    is_featured: props.article ? Boolean(props.article.is_featured) : false,
    sync_to_kb: false,
});

function slugify(val: string): string {
    return (val || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

const renderedPreview = computed(() => renderMarkdown(form.content, { allowBlobUrls: true }));
const activeThumbPreview = computed(() => pendingThumbPreview.value || form.thumbnail_url || '');
const isSaving = computed(() => uploadingImages.value || form.processing);

const pendingInlineCount = computed(() => {
    let count = 0;
    for (const blobUrl of pendingInlineFiles.value.keys()) {
        if (form.content.includes(blobUrl)) count++;
    }
    return count;
});

// ================= REALTIME CONTENT & SEO ANALYZER =================
const effectiveSlug = computed(() => form.slug.trim() || slugify(form.title));
const effectiveMetaTitle = computed(() => (form.meta_title || form.title || '').trim());
const effectiveMetaDesc = computed(() => (form.meta_description || form.excerpt || '').trim());

const wordCount = computed(() => {
    const plain = form.content
        .replace(/```[\s\S]*?```/g, ' ')
        .replace(/!\[[^\]]*\]\([^)]+\)/g, ' ')
        .replace(/[#>*`_\-|]/g, ' ')
        .trim();
    if (!plain) return 0;
    return plain.split(/\s+/).filter(Boolean).length;
});
const readingMinutes = computed(() => Math.max(1, Math.ceil(wordCount.value / 200)));
const h2Count = computed(() => (form.content.match(/^##\s+.+$/gm) || []).length);
const h3Count = computed(() => (form.content.match(/^###\s+.+$/gm) || []).length);
const inlineImageMatches = computed(() => Array.from(form.content.matchAll(/!\[([^\]]*)\]\(([^)\s]+)\)/g)));
const inlineImageCount = computed(() => inlineImageMatches.value.length);
const hasInternalLink = computed(
    () => /\]\(\/(blog|pricing|store|proxy|support)/.test(form.content) || form.cta_service !== 'none'
);

const seoChecks = computed(() => {
    const kw = focusKeyword.value.trim().toLowerCase();
    const kwSlug = slugify(kw);
    const titleLen = effectiveMetaTitle.value.length;
    const descLen = effectiveMetaDesc.value.length;
    const allImagesHaveAlt =
        inlineImageCount.value === 0 ||
        inlineImageMatches.value.every((m) => (m[1] || '').trim().length >= 3);

    const checks = [
        {
            id: 'title_len',
            ok: titleLen >= 35 && titleLen <= 70,
            label: `Tiêu đề SEO chuẩn độ dài (${titleLen}/60 ký tự)`,
            hint: 'Nên từ 35–70 ký tự để không bị Google cắt bớt.',
        },
        {
            id: 'desc_len',
            ok: descLen >= 110 && descLen <= 165,
            label: `Meta Description chuẩn (${descLen}/160 ký tự)`,
            hint: 'Nên từ 110–165 ký tự chứa lợi ích + từ khóa.',
        },
        {
            id: 'words',
            ok: wordCount.value >= 300,
            label: `Độ dài bài viết đủ sâu (${wordCount.value} từ)`,
            hint: 'Bài SEO/hướng dẫn nên đạt tối thiểu 300 từ.',
        },
        {
            id: 'headings',
            ok: h2Count.value >= 2,
            label: `Cấu trúc mục lục rõ ràng (${h2Count.value} thẻ H2)`,
            hint: 'Dùng ít nhất 2 thẻ H2 (##) để tự tạo Mục lục.',
        },
        {
            id: 'thumb',
            ok: Boolean(activeThumbPreview.value),
            label: 'Có ảnh bìa bài viết (OG Image / CDN)',
            hint: 'Giúp hiển thị đẹp khi share Facebook, Zalo & Google Discover.',
        },
        {
            id: 'alt',
            ok: allImagesHaveAlt,
            label: inlineImageCount.value > 0 ? `Tất cả ${inlineImageCount.value} ảnh trong bài có thẻ Alt` : 'Sẵn sàng chèn ảnh minh họa có Alt',
            hint: 'Đặt mô tả ngắn trong ![mô tả ảnh](url) để lên Google Images.',
        },
        {
            id: 'internal',
            ok: hasInternalLink.value,
            label: 'Có CTA / Liên kết nội bộ về dịch vụ',
            hint: 'Giúp giữ chân người đọc và chuyển đổi đơn hàng.',
        },
    ];

    if (kw) {
        checks.unshift(
            {
                id: 'kw_title',
                ok: effectiveMetaTitle.value.toLowerCase().includes(kw),
                label: `Tiêu đề chứa từ khóa "${focusKeyword.value.trim()}"`,
                hint: 'Đưa từ khóa chính vào tiêu đề H1 / Meta Title.',
            },
            {
                id: 'kw_slug',
                ok: kwSlug !== '' && effectiveSlug.value.includes(kwSlug),
                label: 'Đường dẫn URL (Slug) chứa từ khóa chính',
                hint: `Slug nên chứa "${kwSlug}".`,
            }
        );
    }

    return checks;
});

const seoScore = computed(() => {
    const total = seoChecks.value.length;
    if (!total) return 0;
    const passed = seoChecks.value.filter((c) => c.ok).length;
    return Math.round((passed / total) * 100);
});

const seoScoreBadge = computed(() => {
    if (seoScore.value >= 80) return { text: 'Chuẩn SEO tốt', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', bar: 'bg-emerald-500' };
    if (seoScore.value >= 55) return { text: 'Khá ổn — tối ưu thêm', cls: 'bg-amber-50 text-amber-800 border-amber-200', bar: 'bg-amber-500' };
    return { text: 'Cần bổ sung SEO', cls: 'bg-rose-50 text-rose-700 border-rose-200', bar: 'bg-rose-500' };
});

// ================= EDITOR TOOLBAR HELPERS =================
function wrapSelection(before: string, after: string, placeholder: string) {
    const el = contentTextarea.value;
    if (!el) {
        form.content = (form.content || '') + `${before}${placeholder}${after}`;
        return;
    }
    const start = el.selectionStart ?? form.content.length;
    const end = el.selectionEnd ?? form.content.length;
    const selected = form.content.slice(start, end) || placeholder;
    form.content = form.content.slice(0, start) + before + selected + after + form.content.slice(end);

    requestAnimationFrame(() => {
        el.focus();
        el.setSelectionRange(start + before.length, start + before.length + selected.length);
    });
}

function insertBlock(snippet: string) {
    const el = contentTextarea.value;
    if (!el) {
        form.content = (form.content || '') + snippet;
        return;
    }
    const start = el.selectionStart ?? form.content.length;
    const end = el.selectionEnd ?? form.content.length;
    const prefix = start > 0 && form.content[start - 1] !== '\n' ? '\n\n' : '';
    const text = prefix + snippet;
    form.content = form.content.slice(0, start) + text + form.content.slice(end);

    requestAnimationFrame(() => {
        el.focus();
        const cursor = start + text.length;
        el.setSelectionRange(cursor, cursor);
    });
}

function insertTableTemplate() {
    insertBlock(
        `| Tiêu chí | Gói A | Gói B |\n| --- | --- | --- |\n| Hiệu năng | Ổn định 24/7 | Tốc độ cao |\n| Chi phí | Tiết kiệm | Chuyên dụng |\n`
    );
}

function insertFaqTemplate() {
    insertBlock(
        `## Câu hỏi thường gặp (FAQ)\n\n### 1. Câu hỏi phổ biến của khách hàng?\nCâu trả lời ngắn gọn, rõ ràng và chứa từ khóa liên quan.\n\n### 2. Cần hỗ trợ trực tiếp thì liên hệ ở đâu?\nBạn có thể nhắn Zalo kỹ thuật 24/7 tại **0862.579.104** để được hỗ trợ ngay.\n`
    );
}

function insertVideoTemplate() {
    insertBlock(`[video](https://cdn.lugiacloud.com/blog/clip-huong-dan.mp4)\n`);
}

function generateSlugFromTitle() {
    if (!form.title.trim()) return;
    form.slug = slugify(form.title);
}

// ================= DEFERRED LOCAL PREVIEW -> UPLOAD TO R2 ON SUBMIT =================
function clearPendingThumb() {
    if (pendingThumbPreview.value) {
        URL.revokeObjectURL(pendingThumbPreview.value);
    }
    pendingThumbFile.value = null;
    pendingThumbPreview.value = null;
}

function clearPendingMedia() {
    clearPendingThumb();
    for (const blobUrl of pendingInlineFiles.value.keys()) {
        URL.revokeObjectURL(blobUrl);
    }
    pendingInlineFiles.value = new Map();
}

onUnmounted(() => clearPendingMedia());

function validateImageFile(file: File): boolean {
    const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!allowed.includes(file.type)) {
        toast.error('Chỉ hỗ trợ ảnh JPG, PNG, WebP hoặc GIF.');
        return false;
    }
    if (file.size > 5 * 1024 * 1024) {
        toast.error('Dung lượng ảnh tối đa 5MB.');
        return false;
    }
    return true;
}

function handleThumbSelect(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || !validateImageFile(file)) return;

    clearPendingThumb();
    pendingThumbFile.value = file;
    pendingThumbPreview.value = URL.createObjectURL(file);
}

function handleInlineImageSelect(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || !validateImageFile(file)) return;

    const blobUrl = URL.createObjectURL(file);
    pendingInlineFiles.value.set(blobUrl, file);
    const altText = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
    insertBlock(`![${altText}](${blobUrl})\n`);
}

async function uploadSingleImageToR2(file: File, prefix: string): Promise<string> {
    const fd = new FormData();
    fd.append('image', file);
    fd.append('folder', 'blog');
    fd.append('prefix', prefix);

    const { data } = await axios.post('/admin/media/upload', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.url;
}

async function submitArticle() {
    if (isSaving.value) return;

    const inlineEntriesToUpload: Array<[string, File]> = [];
    for (const [blobUrl, file] of pendingInlineFiles.value.entries()) {
        if (form.content.includes(blobUrl)) {
            inlineEntriesToUpload.push([blobUrl, file]);
        }
    }

    if (pendingThumbFile.value || inlineEntriesToUpload.length > 0) {
        uploadingImages.value = true;
        try {
            if (pendingThumbFile.value) {
                const cdnUrl = await uploadSingleImageToR2(pendingThumbFile.value, 'cover');
                form.thumbnail_url = cdnUrl;
                clearPendingThumb();
            }

            for (const [blobUrl, file] of inlineEntriesToUpload) {
                const cdnUrl = await uploadSingleImageToR2(file, 'img');
                form.content = form.content.split(blobUrl).join(cdnUrl);
                URL.revokeObjectURL(blobUrl);
                pendingInlineFiles.value.delete(blobUrl);
            }
        } catch (err: any) {
            toast.error(err?.response?.data?.message || 'Tải ảnh lên Cloudflare R2 thất bại. Chưa lưu bài viết.');
            uploadingImages.value = false;
            return;
        } finally {
            uploadingImages.value = false;
        }
    }

    const opts = {
        preserveScroll: true,
        onSuccess: () => clearPendingMedia(),
    };

    if (isEdit.value && props.article) {
        form.put(`/admin/articles/${props.article.id}`, opts);
    } else {
        form.post('/admin/articles', opts);
    }
}
</script>

<template>
    <AdminLayout :title="isEdit ? 'Chỉnh sửa bài viết Blog' : 'Soạn bài viết Blog chuẩn SEO'">
        <Head :title="isEdit ? `Sửa: ${article?.title}` : 'Viết bài Blog mới'" />

        <!-- Hidden file inputs -->
        <input ref="thumbFileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="handleThumbSelect" />
        <input ref="inlineFileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="handleInlineImageSelect" />

        <form @submit.prevent="submitArticle" class="space-y-6">
            <!-- Top Action Bar -->
            <div class="surface-card p-4 sm:px-6 rounded-2xl border border-slate-200/90 shadow-xs flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <Link
                        href="/admin/articles"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-xs font-bold text-slate-700 transition shrink-0"
                    >
                        <ArrowLeft class="w-3.5 h-3.5" />
                        <span>Danh sách bài</span>
                    </Link>
                    <div class="min-w-0">
                        <div class="text-sm font-extrabold text-[#0B1437] truncate">
                            {{ isEdit ? 'Chỉnh sửa bài viết chuẩn SEO' : 'Viết bài Blog / Hướng dẫn mới' }}
                        </div>
                        <div class="text-[11px] text-slate-500">
                            Ảnh chọn sẽ preview ngay và tự động tải lên Cloudflare R2 ({{ cdnUrl || 'https://cdn.lugiacloud.com' }}) khi bấm lưu.
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <Link
                        v-if="isEdit && article"
                        :href="`/blog/${article.slug}`"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition"
                    >
                        <ExternalLink class="w-3.5 h-3.5 text-[#1D6AE5]" />
                        <span>Xem bài thực tế</span>
                    </Link>
                    <button
                        type="submit"
                        :disabled="isSaving"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#0B1437] hover:bg-[#162354] disabled:opacity-50 text-white text-xs font-bold cursor-pointer transition shadow-xs"
                    >
                        <Loader2 v-if="isSaving" class="w-4 h-4 animate-spin" />
                        <Save v-else class="w-4 h-4" />
                        <span>
                            {{
                                uploadingImages
                                    ? 'Đang tải ảnh lên R2...'
                                    : form.processing
                                      ? 'Đang lưu bài viết...'
                                      : isEdit
                                        ? 'Lưu cập nhật'
                                        : 'Xuất bản bài viết'
                            }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Main 2-Column Workspace -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
                <!-- LEFT 8 COLS: Title, Slug, Excerpt & Full SEO Editor -->
                <div class="xl:col-span-8 space-y-5">
                    <!-- Title + Slug + Excerpt Card -->
                    <div class="surface-card p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 text-xs">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="font-bold text-slate-700">Tiêu đề bài viết (Thẻ H1)</label>
                                <span class="font-mono-num text-[11px]" :class="form.title.length > 70 ? 'text-amber-600 font-bold' : 'text-slate-400'">
                                    {{ form.title.length }} ký tự
                                </span>
                            </div>
                            <input
                                v-model="form.title"
                                type="text"
                                required
                                placeholder="VD: Hướng dẫn cài Proxy IPv4 Việt Nam vào AdsPower chuẩn IP sạch"
                                class="w-full h-11 px-4 rounded-xl border border-slate-300 focus:border-[#1D6AE5] focus:ring-2 focus:ring-[#1D6AE5]/15 outline-none font-bold text-sm text-[#0B1437] transition"
                            />
                            <p v-if="form.errors.title" class="text-rose-600 font-semibold mt-1">{{ form.errors.title }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="font-bold text-slate-700">Đường dẫn chuẩn SEO (Slug)</label>
                                    <button
                                        type="button"
                                        @click="generateSlugFromTitle"
                                        class="text-[11px] font-bold text-[#1D6AE5] hover:text-[#1557C0] cursor-pointer"
                                    >
                                        Tạo tự động từ tiêu đề
                                    </button>
                                </div>
                                <div class="flex items-center h-10 rounded-xl border border-slate-300 focus-within:border-[#1D6AE5] overflow-hidden bg-white">
                                    <span class="px-3 h-full flex items-center bg-slate-50 border-r border-slate-200 font-mono text-[11px] text-slate-500 shrink-0">
                                        /blog/
                                    </span>
                                    <input
                                        v-model="form.slug"
                                        type="text"
                                        placeholder="tu-dong-tao-tu-tieu-de"
                                        class="flex-1 h-full px-3 outline-none font-mono text-xs text-slate-800 min-w-0"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1.5">Tóm tắt ngắn (Excerpt)</label>
                                <input
                                    v-model="form.excerpt"
                                    type="text"
                                    placeholder="Tóm tắt 1–2 câu hấp dẫn hiện ngoài danh sách /blog"
                                    class="w-full h-10 px-3.5 rounded-xl border border-slate-300 focus:border-[#1D6AE5] outline-none text-xs"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Full SEO Markdown Editor Card -->
                    <div class="surface-card rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden bg-white">
                        <!-- Editor Header + View Switcher -->
                        <div class="px-4 py-3 border-b border-slate-200 bg-slate-50/90 flex flex-wrap items-center justify-between gap-2.5">
                            <span class="text-xs font-extrabold text-[#0B1437]">Nội dung bài viết (Trình soạn thảo chuẩn SEO)</span>

                            <div class="inline-flex p-0.5 rounded-lg bg-slate-200/75 text-[11.5px] font-bold">
                                <button
                                    type="button"
                                    @click="editorMode = 'write'"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md cursor-pointer transition"
                                    :class="editorMode === 'write' ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    <PenLine class="w-3.5 h-3.5" />
                                    <span>Soạn thảo</span>
                                </button>
                                <button
                                    type="button"
                                    @click="editorMode = 'split'"
                                    class="hidden md:inline-flex items-center gap-1 px-2.5 py-1 rounded-md cursor-pointer transition"
                                    :class="editorMode === 'split' ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    <Columns2 class="w-3.5 h-3.5" />
                                    <span>Chia đôi</span>
                                </button>
                                <button
                                    type="button"
                                    @click="editorMode = 'preview'"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md cursor-pointer transition"
                                    :class="editorMode === 'preview' ? 'bg-white text-[#0B1437] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    <Eye class="w-3.5 h-3.5" />
                                    <span>Xem trước</span>
                                </button>
                            </div>
                        </div>

                        <!-- Rich SEO Formatting Toolbar -->
                        <div class="px-3.5 py-2.5 border-b border-slate-200/90 bg-white flex flex-wrap items-center gap-1 text-xs">
                            <button type="button" @click="insertBlock('## Tiêu đề mục lớn (H2)\n')" title="Chèn thẻ H2 (tạo Mục lục)" class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-slate-100 text-slate-700 font-bold cursor-pointer">
                                <Heading2 class="w-4 h-4 text-[#1D6AE5]" /><span>H2</span>
                            </button>
                            <button type="button" @click="insertBlock('### Tiêu đề mục nhỏ (H3)\n')" title="Chèn thẻ H3" class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-slate-100 text-slate-700 font-bold cursor-pointer">
                                <Heading3 class="w-4 h-4 text-slate-600" /><span>H3</span>
                            </button>

                            <span class="w-px h-5 bg-slate-200 mx-1" />

                            <button type="button" @click="wrapSelection('**', '**', 'từ khóa nổi bật')" title="In đậm" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer">
                                <Bold class="w-4 h-4" />
                            </button>
                            <button type="button" @click="wrapSelection('*', '*', 'chữ nghiêng')" title="In nghiêng" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer">
                                <Italic class="w-4 h-4" />
                            </button>
                            <button type="button" @click="wrapSelection('`', '`', 'lệnh ngắn')" title="Code nội dòng" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer">
                                <Code class="w-4 h-4" />
                            </button>
                            <button type="button" @click="insertBlock('```bash\n# Dán lệnh hướng dẫn tại đây\n```\n')" title="Khối Code" class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-slate-100 text-slate-700 font-semibold cursor-pointer">
                                <FileCode2 class="w-4 h-4" /><span class="hidden sm:inline">Code</span>
                            </button>

                            <span class="w-px h-5 bg-slate-200 mx-1" />

                            <button type="button" @click="insertBlock('- Ý thứ nhất\n- Ý thứ hai\n')" title="Danh sách chấm" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer">
                                <List class="w-4 h-4" />
                            </button>
                            <button type="button" @click="insertBlock('1. Bước 1\n2. Bước 2\n')" title="Danh sách các bước" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer">
                                <ListOrdered class="w-4 h-4" />
                            </button>
                            <button type="button" @click="insertBlock('> **Lưu ý quan trọng:** Nội dung nổi bật giúp người đọc chú ý.\n')" title="Hộp lưu ý / Trích dẫn" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-700 cursor-pointer">
                                <Quote class="w-4 h-4" />
                            </button>
                            <button type="button" @click="insertTableTemplate" title="Chèn bảng so sánh chuẩn SEO" class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-slate-100 text-slate-700 font-semibold cursor-pointer">
                                <Table2 class="w-4 h-4" /><span class="hidden sm:inline">Bảng</span>
                            </button>
                            <button type="button" @click="insertFaqTemplate" title="Chèn khối Câu hỏi thường gặp (FAQ)" class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-slate-100 text-slate-700 font-semibold cursor-pointer">
                                <HelpCircle class="w-4 h-4" /><span class="hidden sm:inline">FAQ</span>
                            </button>

                            <span class="w-px h-5 bg-slate-200 mx-1" />

                            <button type="button" @click="wrapSelection('[', '](/pricing)', 'Xem bảng giá Cloud VPS')" title="Chèn liên kết nội bộ / ngoài" class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-slate-100 text-slate-700 font-semibold cursor-pointer">
                                <Link2 class="w-4 h-4" /><span class="hidden sm:inline">Link</span>
                            </button>
                            <button type="button" @click="insertVideoTemplate" title="Nhúng clip MP4 từ Cloudflare R2" class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-slate-100 text-slate-700 font-semibold cursor-pointer">
                                <Film class="w-4 h-4" /><span class="hidden sm:inline">MP4</span>
                            </button>
                            <button
                                type="button"
                                :disabled="isSaving"
                                @click="inlineFileInput?.click()"
                                class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 text-[#1D6AE5] font-bold cursor-pointer transition disabled:opacity-50"
                            >
                                <ImageIcon class="w-3.5 h-3.5" />
                                <span>Chèn ảnh{{ pendingInlineCount > 0 ? ` (${pendingInlineCount} chờ lưu)` : '' }}</span>
                            </button>
                        </div>

                        <!-- Editor / Split / Preview Body -->
                        <div :class="editorMode === 'split' ? 'grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-200' : ''">
                            <div v-show="editorMode === 'write' || editorMode === 'split'">
                                <textarea
                                    ref="contentTextarea"
                                    v-model="form.content"
                                    rows="22"
                                    required
                                    placeholder="Bắt đầu viết nội dung hướng dẫn hoặc bài SEO tại đây...&#10;&#10;Dùng thanh công cụ phía trên để chèn H2/H3, Bảng so sánh, Code, Ảnh (tự preview & up lên Cloudflare R2 khi bấm lưu) hoặc FAQ chuẩn SEO."
                                    class="w-full min-h-[520px] p-5 outline-none font-mono text-[13px] text-slate-800 leading-relaxed resize-y"
                                />
                            </div>

                            <div
                                v-show="editorMode === 'preview' || editorMode === 'split'"
                                class="min-h-[520px] max-h-[680px] overflow-y-auto p-5 sm:p-6 bg-white"
                            >
                                <div v-if="form.content.trim()" v-html="renderedPreview.html" />
                                <p v-else class="text-xs text-slate-400 italic">Chưa có nội dung để xem trước.</p>
                            </div>
                        </div>

                        <p v-if="form.errors.content" class="px-5 py-2 text-xs text-rose-600 font-semibold border-t border-rose-100 bg-rose-50/50">
                            {{ form.errors.content }}
                        </p>

                        <!-- Editor Status Bar -->
                        <div class="px-5 py-2.5 border-t border-slate-200 bg-slate-50/80 flex flex-wrap items-center justify-between gap-2 text-[11px] font-medium text-slate-600">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 font-mono-num">
                                <span><strong>{{ wordCount }}</strong> từ</span>
                                <span>~<strong>{{ readingMinutes }}</strong> phút đọc</span>
                                <span><strong>{{ h2Count }}</strong> H2 · <strong>{{ h3Count }}</strong> H3</span>
                                <span><strong>{{ inlineImageCount }}</strong> ảnh trong bài</span>
                            </div>
                            <span v-if="pendingInlineCount > 0 || pendingThumbFile" class="text-amber-700 font-bold">
                                {{ (pendingThumbFile ? 1 : 0) + pendingInlineCount }} ảnh đang preview cục bộ — sẽ đẩy lên R2 khi bấm Lưu
                            </span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT 4 COLS: Live SEO Checker, Google Preview, Cover Image, Publish Settings -->
                <div class="xl:col-span-4 space-y-5 text-xs">
                    <!-- 1. LIVE SEO SCORE & CHECKLIST -->
                    <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 bg-white">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 font-extrabold text-sm text-[#0B1437]">
                                <Sparkles class="w-4 h-4 text-[#1D6AE5]" />
                                <span>Chấm điểm chuẩn SEO</span>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-lg border font-extrabold text-[11px]" :class="seoScoreBadge.cls">
                                {{ seoScore }}/100 · {{ seoScoreBadge.text }}
                            </span>
                        </div>

                        <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="seoScoreBadge.bar"
                                :style="{ width: `${seoScore}%` }"
                            />
                        </div>

                        <div>
                            <label class="flex items-center gap-1.5 font-bold text-slate-700 mb-1.5">
                                <Target class="w-3.5 h-3.5 text-[#1D6AE5]" />
                                <span>Từ khóa chính (Focus Keyword)</span>
                            </label>
                            <input
                                v-model="focusKeyword"
                                type="text"
                                placeholder="VD: thuê cloud vps, cài proxy adspower..."
                                class="w-full h-9 px-3 rounded-xl border border-slate-300 focus:border-[#1D6AE5] outline-none text-xs"
                            />
                        </div>

                        <ul class="space-y-2">
                            <li v-for="c in seoChecks" :key="c.id" class="flex items-start gap-2 leading-snug">
                                <CheckCircle2 v-if="c.ok" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                                <AlertCircle v-else class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                                <div class="min-w-0">
                                    <div :class="c.ok ? 'text-slate-800 font-semibold' : 'text-slate-700 font-medium'">{{ c.label }}</div>
                                    <div v-if="!c.ok" class="text-[11px] text-slate-500 mt-0.5">{{ c.hint }}</div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- 2. GOOGLE SERP PREVIEW & META FIELDS -->
                    <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-3.5 bg-white">
                        <div class="font-extrabold text-sm text-[#0B1437]">Hiển thị trên Google (Meta SEO)</div>

                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/90 space-y-1">
                            <div class="text-[11px] text-emerald-700 font-mono truncate">
                                https://lugiacloud.com/blog/{{ effectiveSlug || 'duong-dan-bai-viet' }}
                            </div>
                            <div class="text-[13.5px] font-bold text-[#1a0dab] line-clamp-1">
                                {{ effectiveMetaTitle || 'Tiêu đề bài viết chuẩn SEO — LugiaCloud' }}
                            </div>
                            <div class="text-[11.5px] text-slate-600 line-clamp-2 leading-relaxed">
                                {{ effectiveMetaDesc || 'Mô tả Meta Description giúp tăng tỷ lệ click (CTR) từ kết quả tìm kiếm Google...' }}
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="font-bold text-slate-700">Meta Title</label>
                                <span class="font-mono-num text-[11px]" :class="effectiveMetaTitle.length > 65 ? 'text-amber-600 font-bold' : 'text-slate-400'">
                                    {{ effectiveMetaTitle.length }}/60
                                </span>
                            </div>
                            <input
                                v-model="form.meta_title"
                                type="text"
                                placeholder="Để trống sẽ tự dùng Tiêu đề H1"
                                class="w-full h-9 px-3 rounded-xl border border-slate-300 focus:border-[#1D6AE5] outline-none"
                            />
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="font-bold text-slate-700">Meta Description</label>
                                <span class="font-mono-num text-[11px]" :class="effectiveMetaDesc.length > 165 ? 'text-amber-600 font-bold' : 'text-slate-400'">
                                    {{ effectiveMetaDesc.length }}/160
                                </span>
                            </div>
                            <textarea
                                v-model="form.meta_description"
                                rows="3"
                                placeholder="Mô tả ngắn chứa từ khóa chính (120–160 ký tự)"
                                class="w-full p-3 rounded-xl border border-slate-300 focus:border-[#1D6AE5] outline-none leading-relaxed"
                            />
                        </div>
                    </div>

                    <!-- 3. COVER IMAGE (LOCAL PREVIEW -> R2 ON SUBMIT) -->
                    <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-3 bg-white">
                        <div class="flex items-center justify-between gap-2">
                            <label class="font-extrabold text-sm text-[#0B1437]">Ảnh bìa (OG Image)</label>
                            <button
                                type="button"
                                :disabled="isSaving"
                                @click="thumbFileInput?.click()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#1D6AE5] hover:bg-[#1557C0] text-white font-bold cursor-pointer disabled:opacity-50"
                            >
                                <Upload class="w-3.5 h-3.5" />
                                <span>Chọn ảnh bìa</span>
                            </button>
                        </div>

                        <div v-if="pendingThumbFile" class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-900">
                            <span class="truncate font-semibold">Chờ lưu lên R2: {{ pendingThumbFile.name }}</span>
                            <button type="button" @click="clearPendingThumb" class="text-amber-700 hover:text-rose-600 cursor-pointer shrink-0">
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <img
                            v-if="activeThumbPreview"
                            :src="activeThumbPreview"
                            alt="Preview ảnh bìa"
                            class="w-full aspect-[16/9] object-cover rounded-xl border border-slate-200"
                        />

                        <input
                            v-model="form.thumbnail_url"
                            type="text"
                            placeholder="Hoặc dán sẵn link https://cdn.lugiacloud.com/blog/..."
                            class="w-full h-9 px-3 rounded-xl bg-slate-50 focus:bg-white border border-slate-300 font-mono text-[11px]"
                            @input="clearPendingThumb"
                        />
                    </div>

                    <!-- 4. YOUTUBE, CATEGORY & PUBLISH OPTIONS -->
                    <div class="surface-card p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 bg-white">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1.5">Chuyên mục</label>
                                <select v-model="form.category" class="w-full h-9 px-2.5 rounded-xl border border-slate-300 bg-white font-semibold">
                                    <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1.5">Box CTA dịch vụ</label>
                                <select v-model="form.cta_service" class="w-full h-9 px-2.5 rounded-xl border border-slate-300 bg-white font-semibold">
                                    <option value="vps">Cloud VPS SSD</option>
                                    <option value="proxy">Proxy IPv4 VN</option>
                                    <option value="none">Không hiển thị</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center gap-1.5 font-bold text-slate-700 mb-1.5">
                                <Youtube class="w-3.5 h-3.5 text-red-600" />
                                <span>Link Video YouTube (Lazy Facade)</span>
                            </label>
                            <input
                                v-model="form.youtube_url"
                                type="text"
                                placeholder="https://www.youtube.com/watch?v=..."
                                class="w-full h-9 px-3 rounded-xl border border-slate-300 font-mono text-[11px]"
                            />
                        </div>

                        <div class="pt-2 border-t border-slate-100 space-y-2.5">
                            <label class="flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                                <input v-model="form.is_published" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Xuất bản công khai (/blog &amp; sitemap.xml)</span>
                            </label>
                            <label class="flex items-center gap-2 font-semibold text-slate-700 cursor-pointer">
                                <input v-model="form.is_featured" type="checkbox" class="rounded border-slate-300 text-[#1D6AE5]" />
                                <span>Ghim bài viết nổi bật đầu trang /blog</span>
                            </label>
                            <label class="flex items-center gap-2 font-semibold text-emerald-800 cursor-pointer">
                                <input v-model="form.sync_to_kb" type="checkbox" class="rounded border-emerald-400 text-emerald-600" />
                                <span>Đồng bộ nội dung vào Kho tri thức Chatbot AI</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
