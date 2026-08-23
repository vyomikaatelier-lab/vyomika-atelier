<?php

namespace App\Support\Seo;

use App\Models\BlogPost;
use Illuminate\Support\Str;

class BlogSeo
{
    /** @return array<string, mixed> */
    public static function pageData(BlogPost $post): array
    {
        $canonical = filled($post->canonical_url)
            ? (string) $post->canonical_url
            : route('blog.show', $post->slug);

        $robots = null;
        if (! $post->isPublished()) {
            $robots = 'noindex,nofollow';
        } elseif ($post->robots_index === false) {
            $robots = 'noindex,follow';
        }

        return PageSeo::make([
            'title' => $post->meta_title ?: ($post->title.' | Vyomika Atelier LLP'),
            'description' => $post->meta_description
                ?: Str::limit(strip_tags((string) ($post->excerpt ?? '')), 160, ''),
            'canonical' => $canonical,
            'robots' => $robots,
            'og_title' => filled($post->og_title ?? null) ? (string) $post->og_title : null,
            'og_description' => filled($post->og_description ?? null) ? (string) $post->og_description : null,
            'og_image' => $post->og_image ?: $post->image,
            'og_type' => 'article',
            'primary_keyword' => $post->primary_keyword,
        ]);
    }
}
