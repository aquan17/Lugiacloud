<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class BlogController extends Controller
{
    private const SUMMARY_COLUMNS = [
        'id',
        'slug',
        'title',
        'excerpt',
        'category',
        'thumbnail_url',
        'is_featured',
        'views',
        'reading_minutes',
        'published_at',
        'created_at',
    ];

    public function index(Request $request)
    {
        $category = trim((string) $request->input('category', 'all'));
        $search = trim((string) $request->input('search', ''));

        $baseQuery = Article::published()->select(self::SUMMARY_COLUMNS);

        $featured = null;
        if ($category === 'all' && $search === '') {
            $featuredModel = (clone $baseQuery)
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->first();
            if ($featuredModel) {
                $featured = $this->formatSummary($featuredModel);
            }
        }

        $query = Article::published()
            ->select(self::SUMMARY_COLUMNS)
            ->when($category !== 'all' && array_key_exists($category, Article::CATEGORIES), fn ($q) => $q->where('category', $category))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($featured !== null, fn ($q) => $q->where('id', '!=', $featured['id']))
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $articles = $query->paginate(9)->withQueryString()->through(fn (Article $a) => $this->formatSummary($a));

        $categoryCounts = Cache::remember('lugiacloud.blog.cat_counts_v1', 120, function () {
            return Article::published()
                ->selectRaw('category, COUNT(*) as total')
                ->groupBy('category')
                ->pluck('total', 'category')
                ->all();
        });

        $seoTitle = 'Blog & Hướng dẫn Cloud VPS, Proxy IPv4 — LugiaCloud';
        $seoDesc = 'Tổng hợp hướng dẫn sử dụng Cloud VPS Windows/Linux, cách cài đặt Proxy IPv4 Việt Nam cho AdsPower/GoLogin/Proxifier và kinh nghiệm tối ưu hạ tầng.';

        return Inertia::render('Blog/Index', [
            'featured' => $featured,
            'articles' => $articles,
            'categories' => Article::CATEGORIES,
            'categoryCounts' => $categoryCounts,
            'totalPublished' => array_sum($categoryCounts),
            'filters' => [
                'category' => $category,
                'search' => $search,
            ],
        ])->withViewData([
            'seo' => [
                'title' => $seoTitle,
                'description' => $seoDesc,
                'type' => 'website',
                'canonical' => url('/blog'),
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        $viewedKey = 'blog_viewed_' . $article->id;
        if (!$request->session()->has($viewedKey)) {
            $article->increment('views');
            $request->session()->put($viewedKey, true);
        }

        $related = Article::published()
            ->select(self::SUMMARY_COLUMNS)
            ->where('id', '!=', $article->id)
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$article->category])
            ->orderByDesc('published_at')
            ->take(3)
            ->get()
            ->map(fn (Article $a) => $this->formatSummary($a))
            ->values();

        $metaTitle = $article->meta_title ?: ($article->title . ' — LugiaCloud');
        $metaDescription = $article->meta_description
            ?: ($article->excerpt ?: mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($article->content))), 0, 160));
        $ogImage = $article->thumbnail_url ?: asset('og-cover.jpg');
        $canonicalUrl = url('/blog/' . $article->slug);
        $publishedIso = ($article->published_at ?? $article->created_at)?->toIso8601String();
        $updatedIso = $article->updated_at?->toIso8601String();

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Article',
                    'headline' => $article->title,
                    'description' => $metaDescription,
                    'image' => [$ogImage],
                    'datePublished' => $publishedIso,
                    'dateModified' => $updatedIso,
                    'author' => [
                        '@type' => 'Organization',
                        'name' => 'LugiaCloud',
                        'url' => url('/'),
                    ],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => 'LugiaCloud',
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => asset('logo.png'),
                        ],
                    ],
                    'mainEntityOfPage' => [
                        '@type' => 'WebPage',
                        '@id' => $canonicalUrl,
                    ],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Trang chủ',
                            'item' => url('/'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Blog & Hướng dẫn',
                            'item' => url('/blog'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $article->title,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
            ],
        ];

        return Inertia::render('Blog/Show', [
            'article' => [
                ...$this->formatSummary($article),
                'content' => $article->content,
                'youtube_url' => $article->youtube_url,
                'youtube_id' => Article::extractYoutubeId($article->youtube_url),
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'cta_service' => $article->cta_service ?: 'vps',
                'published_iso' => $publishedIso,
                'updated_at' => $article->updated_at?->format('d/m/Y'),
            ],
            'related' => $related,
        ])->withViewData([
            'seo' => [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'type' => 'article',
                'image' => $ogImage,
                'canonical' => $canonicalUrl,
                'published_time' => $publishedIso,
                'json_ld' => $jsonLd,
            ],
        ]);
    }

    private function formatSummary(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'category' => $article->category,
            'category_label' => $article->categoryLabel(),
            'thumbnail_url' => $article->thumbnail_url,
            'has_video' => filled(Article::extractYoutubeId($article->youtube_url)),
            'excerpt' => $article->excerpt ?: mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($article->content))), 0, 180),
            'views' => (int) $article->views,
            'reading_minutes' => (int) ($article->reading_minutes ?: 3),
            'is_featured' => (bool) $article->is_featured,
            'published_at' => ($article->published_at ?? $article->created_at)?->format('d/m/Y'),
        ];
    }
}
