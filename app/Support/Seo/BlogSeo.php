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
            'title' => $post->meta_title ?: ($post->title.' | Vyomika Atelier'),
            'description' => $post->meta_description
                ?: Str::limit(strip_tags((string) ($post->excerpt ?? '')), 160, ''),
            'canonical' => $canonical,
            'robots' => $robots,
            'og_title' => filled($post->og_title ?? null) ? (string) $post->og_title : null,
            'og_description' => filled($post->og_description ?? null) ? (string) $post->og_description : null,
            'og_image' => $post->ogImageUrl(),
            'og_type' => 'article',
            'primary_keyword' => $post->primary_keyword,
        ]);
    }

    /** @return array<string, mixed>|null */
    public static function faqSchema(BlogPost $post): ?array
    {
        $items = $post->validFaqItems();

        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ], $items),
        ];
    }

    /** @return array<string, mixed> */
    public static function articleSchema(BlogPost $post): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $post->seoDescription(),
            'author' => [
                '@type' => 'Organization',
                'name' => $post->author ?? BlogPost::DEFAULT_AUTHOR,
                'url' => url('/'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Vyomika Atelier',
                'url' => url('/'),
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $post->canonicalUrl(),
            ],
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => ($post->lastUpdatedAt() ?? $post->updated_at ?? $post->published_at)?->toAtomString(),
        ];

        $schemaImage = $post->ogImageUrl() ?: $post->imageUrl();
        if ($schemaImage) {
            $schema['image'] = [$schemaImage];
        }

        return $schema;
    }

    /** @return array<string, mixed> */
    public static function breadcrumbSchema(BlogPost $post): array
    {
        $items = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('blog.index')],
        ];

        if ($post->categoryLabel()) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $post->categoryLabel(),
                'item' => route('blog.index', ['category' => $post->categorySlug()]),
            ];
            $items[] = [
                '@type' => 'ListItem',
                'position' => 4,
                'name' => $post->title,
                'item' => $post->canonicalUrl(),
            ];
        } else {
            $items[] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $post->title,
                'item' => $post->canonicalUrl(),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
