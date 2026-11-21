<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\KnowledgeBase;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $category = trim((string) $request->input('category', 'all'));

        $articles = Article::query()
            ->select([
                'id',
                'title',
                'slug',
                'category',
                'thumbnail_url',
                'is_published',
                'is_featured',
                'views',
                'reading_minutes',
                'published_at',
                'created_at',
            ])
            ->when($category !== 'all' && array_key_exists($category, Article::CATEGORIES), fn ($q) => $q->where('category', $category))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Article $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
                'category' => $a->category,
                'category_label' => $a->categoryLabel(),
                'thumbnail_url' => $a->thumbnail_url,
                'is_published' => (bool) $a->is_published,
                'is_featured' => (bool) $a->is_featured,
                'views' => (int) $a->views,
                'reading_minutes' => (int) $a->reading_minutes,
                'published_at' => ($a->published_at ?? $a->created_at)?->format('d/m/Y H:i'),
            ]);

        $agg = Article::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published')
            ->selectRaw('COALESCE(SUM(views), 0) as views')
            ->first();

        return Inertia::render('Admin/Articles/Index', [
            'articles' => $articles,
            'categories' => Article::CATEGORIES,
            'filters' => [
                'search' => $search,
                'category' => $category,
            ],
            'stats' => [
                'total' => (int) ($agg->total ?? 0),
                'published' => (int) ($agg->published ?? 0),
                'views' => (int) ($agg->views ?? 0),
            ],
            'cdnUrl' => (string) config('filesystems.disks.r2.url', ''),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Articles/Form', [
            'article' => null,
            'categories' => Article::CATEGORIES,
            'cdnUrl' => (string) config('filesystems.disks.r2.url', ''),
        ]);
    }

    public function edit(Article $article)
    {
        return Inertia::render('Admin/Articles/Form', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'category' => $article->category,
                'thumbnail_url' => $article->thumbnail_url,
                'youtube_url' => $article->youtube_url,
                'excerpt' => $article->excerpt,
                'content' => $article->content,
                'meta_title' => $article->meta_title,
                'meta_description' => $article->meta_description,
                'cta_service' => $article->cta_service ?: 'vps',
                'is_published' => (bool) $article->is_published,
                'is_featured' => (bool) $article->is_featured,
                'views' => (int) $article->views,
                'published_at' => ($article->published_at ?? $article->created_at)?->format('d/m/Y H:i'),
            ],
            'categories' => Article::CATEGORIES,
            'cdnUrl' => (string) config('filesystems.disks.r2.url', ''),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateArticle($request);
        $data['slug'] = $this->buildUniqueSlug($data['slug'] ?: $data['title']);
        $data['user_id'] = Auth::id();
        $data['reading_minutes'] = Article::estimateReadingMinutes($data['content']);
        $data['published_at'] = $data['is_published'] ? now() : null;

        $syncToKb = $request->boolean('sync_to_kb');
        unset($data['sync_to_kb']);

        $article = Article::create($data);

        if ($syncToKb) {
            $this->syncToKnowledgeBase($article);
        }

        $this->flushBlogCaches();

        return redirect()
            ->route('admin.articles.index')
            ->with('success', "Đã xuất bản bài viết \"{$article->title}\".");
    }

    public function update(Request $request, Article $article, MediaStorageService $storage)
    {
        $oldThumb = $article->thumbnail_url;
        $data = $this->validateArticle($request, $article->id);
        $data['slug'] = $this->buildUniqueSlug($data['slug'] ?: $data['title'], $article->id);
        $data['reading_minutes'] = Article::estimateReadingMinutes($data['content']);

        if ($data['is_published'] && !$article->published_at) {
            $data['published_at'] = now();
        }

        $syncToKb = $request->boolean('sync_to_kb');
        unset($data['sync_to_kb']);

        $article->update($data);

        if ($oldThumb && $oldThumb !== $article->thumbnail_url) {
            $storage->deleteByUrl($oldThumb);
        }

        if ($syncToKb) {
            $this->syncToKnowledgeBase($article);
        }

        $this->flushBlogCaches();

        return redirect()
            ->route('admin.articles.index')
            ->with('success', "Đã cập nhật bài viết \"{$article->title}\".");
    }

    public function toggle(Article $article)
    {
        $next = !$article->is_published;
        $article->update([
            'is_published' => $next,
            'published_at' => $next && !$article->published_at ? now() : $article->published_at,
        ]);

        $this->flushBlogCaches();

        return back()->with('success', $next ? 'Đã xuất bản bài viết.' : 'Đã chuyển bài viết sang bản nháp.');
    }

    public function destroy(Article $article, MediaStorageService $storage)
    {
        $articleId = $article->id;
        $storage->deleteByUrl($article->thumbnail_url);
        $article->delete();

        $this->flushBlogCaches();

        if (preg_match('#/admin/articles/' . $articleId . '(?:[/?#]|$)#', (string) url()->previous())) {
            return redirect()->route('admin.articles.index')->with('success', 'Đã xóa bài viết.');
        }

        return back()->with('success', 'Đã xóa bài viết.');
    }

    private function flushBlogCaches(): void
    {
        Cache::forget('lugiacloud.blog.cat_counts_v1');
        Cache::forget('lugiacloud.sitemap_xml_v1');
    }

    private function validateArticle(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:220',
            'slug' => 'nullable|string|max:240',
            'category' => 'required|in:' . implode(',', array_keys(Article::CATEGORIES)),
            'thumbnail_url' => 'nullable|string|max:500',
            'youtube_url' => 'nullable|string|max:300',
            'excerpt' => 'nullable|string|max:600',
            'content' => 'required|string|min:20',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:320',
            'cta_service' => 'required|in:vps,proxy,none',
            'is_published' => 'required|boolean',
            'is_featured' => 'required|boolean',
            'sync_to_kb' => 'sometimes|boolean',
        ], [
            'title.required' => 'Vui lòng nhập tiêu đề bài viết.',
            'content.required' => 'Vui lòng nhập nội dung bài viết.',
            'content.min' => 'Nội dung bài viết quá ngắn (tối thiểu 20 ký tự).',
        ]);
    }

    private function buildUniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: ('bai-viet-' . Str::lower(Str::random(6)));
        $slug = $base;
        $counter = 2;

        while (
            Article::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function syncToKnowledgeBase(Article $article): void
    {
        KnowledgeBase::updateOrCreate(
            ['title' => $article->title],
            [
                'content' => trim($article->excerpt . "\n\n" . $article->content),
                'is_active' => $article->is_published,
            ]
        );
    }
}
