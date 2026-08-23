<?php

namespace App\Http\Requests;

use App\Models\BlogPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $post = $this->route('post');
        $isPublishing = in_array($this->input('status'), [BlogPost::STATUS_PUBLISHED, BlogPost::STATUS_SCHEDULED], true)
            || $this->input('action') === 'publish';

        return [
            'title' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($post?->id),
            ],
            'excerpt' => 'nullable|string|max:1000',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'author' => 'nullable|string|max:120',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'canonical' => 'nullable|url|max:500',
            'primary_keyword' => 'nullable|string|max:120',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'robots' => 'nullable|in:index,noindex',
            'hero_image_alt' => [$isPublishing ? 'required' : 'nullable', 'string', 'max:255'],
            'hero_image_caption' => 'nullable|string|max:500',
            'status' => 'required|in:draft,published,scheduled',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'gallery_urls' => 'nullable|string',
            'gallery_files' => 'nullable|array',
            'gallery_files.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'gallery_replace' => 'nullable|array',
            'gallery_replace.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'gallery_existing' => 'nullable|array',
            'gallery_existing.*' => 'string|max:500',
            'gallery_alt' => 'nullable|array',
            'gallery_alt.*' => 'nullable|string|max:255',
            'gallery_caption' => 'nullable|array',
            'gallery_caption.*' => 'nullable|string|max:500',
            'faq_questions' => 'nullable|array',
            'faq_questions.*' => 'nullable|string|max:500',
            'faq_answers' => 'nullable|array',
            'faq_answers.*' => 'nullable|string|max:5000',
            'related_product_slugs' => 'nullable|array',
            'related_product_slugs.*' => ['string', Rule::exists('products', 'slug')],
            'related_project_ids' => 'nullable|array',
            'related_project_ids.*' => ['integer', Rule::exists('projects', 'id')],
            'related_service_slugs' => 'nullable|array',
            'related_service_slugs.*' => ['string', Rule::exists('services', 'slug')],
            'related_article_slugs' => 'nullable|array',
            'related_article_slugs.*' => ['string', Rule::exists('blog_posts', 'slug')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'hero_image_alt.required' => 'Hero alt text is required before publishing.',
            'slug.regex' => 'Slug may only contain lowercase letters, numbers, and hyphens.',
        ];
    }
}
