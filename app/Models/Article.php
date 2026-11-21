<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    public const CATEGORIES = [
        'vps' => 'Hướng dẫn VPS',
        'proxy' => 'Hướng dẫn Proxy',
        'guide' => 'Thao tác hệ thống',
        'seo' => 'Kiến thức & Kinh nghiệm',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'category',
        'thumbnail_url',
        'youtube_url',
        'excerpt',
        'content',
        'meta_title',
        'meta_description',
        'cta_service',
        'is_published',
        'is_featured',
        'views',
        'reading_minutes',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'views' => 'integer',
        'reading_minutes' => 'integer',
        'published_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Hướng dẫn';
    }

    public static function estimateReadingMinutes(string $content): int
    {
        $plain = trim(strip_tags($content));
        $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);
        $count = is_array($words) ? count($words) : 0;

        return max(1, (int) ceil($count / 200));
    }

    public static function extractYoutubeId(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
            return $url;
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([a-zA-Z0-9_-]{11})~i', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
