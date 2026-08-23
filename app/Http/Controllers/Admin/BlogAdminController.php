<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Admin\Concerns\ResolvesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\BlogPostRequest;
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Support\BlogContent;
use App\Support\BlogHtmlSanitizer;
use App\Support\Seo\BlogSeo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogAdminController extends Controller
{
    use HandlesAdminUploads;
    use ResolvesUniqueSlug;

    public function index(Request $request): View
    {
        $query = BlogPost::query();

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', $request->featured === '1');
        }

        $sort = $request->input('sort', 'published_desc');
        match ($sort) {
            'published_asc' => $query->orderBy('published_at'),
            'updated_desc' => $query->latest('updated_at'),
            'title_asc' => $query->orderBy('title'),
            default => $query->latest('published_at'),
        };

        $posts = $query->paginate(15)->withQueryString();
        $categories = BlogPost::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('admin.blog.index', compact('posts', 'categories', 'sort'));
    }

    public function create(): View
    {
        return view('admin.blog.form', $this->formOptions());
    }

    public function store(BlogPostRequest $request): RedirectResponse
    {
        if ($this->multipartPayloadFailed($request)) {
            return back()->withInput()->with('error', 'Upload too large for the server limit. Save text changes first, then upload images in smaller batches (max 5 MB each).');
        }

        $validated = $this->prepareValidated($request);
        $validated['slug'] = $this->resolvePostSlug($request, null);

        $post = BlogPost::create($validated);
        $this->enforceSingleFeatured($post);

        return redirect()->route('admin.blog.index')->with('success', 'Blog post created.');
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.form', ['post' => $post, ...$this->formOptions($post)]);
    }

    public function update(BlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        if ($this->multipartPayloadFailed($request)) {
            return back()->withInput()->with('error', 'Upload too large for the server limit. Save text changes first, then upload images in smaller batches (max 5 MB each).');
        }

        $validated = $this->prepareValidated($request, $post);
        $newSlug = $this->resolvePostSlug($request, $post);

        if ($newSlug !== $post->slug && $post->isPublished()) {
            session()->flash('warning', 'Published URL changed. Old links may need a redirect.');
        }

        $validated['slug'] = $newSlug;

        if (($validated['content'] ?? null) !== $post->content) {
            $validated['content_updated_at'] = now();
        }

        $post->update($validated);
        $this->enforceSingleFeatured($post->fresh());

        return redirect()->route('admin.blog.index')->with('success', 'Blog post updated.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->deletePostMedia($post);
        $post->delete();

        return redirect()->route('admin.blog.index')->with('success', 'Blog post deleted.');
    }

    public function preview(BlogPost $post): View
    {
        $relatedProducts = Product::query()
            ->whereIn('slug', $post->relatedProductSlugs())
            ->where('is_active', true)
            ->get();

        $relatedProjects = Project::query()
            ->whereIn('id', $post->relatedProjectIds())
            ->where('is_active', true)
            ->get();

        $relatedServices = Service::query()
            ->whereIn('slug', $post->relatedServiceSlugs())
            ->where('is_active', true)
            ->get();

        $relatedArticles = BlogContent::relatedPosts($post, 3);
        $adjacent = BlogContent::adjacentPosts($post);
        $pageSeo = BlogSeo::pageData($post);
        $pageSeo['robots'] = 'noindex,nofollow';

        return view('blog.show', compact(
            'post',
            'relatedProducts',
            'relatedProjects',
            'relatedServices',
            'relatedArticles',
            'adjacent',
            'pageSeo'
        ));
    }

    public function duplicate(BlogPost $post): RedirectResponse
    {
        $copy = $post->replicate([
            'slug',
            'published_at',
            'content_updated_at',
            'is_featured',
        ]);
        $copy->title = $post->title.' (Copy)';
        $copy->slug = $this->resolveUniqueSlug(BlogPost::class, $copy->title, 'title');
        $copy->status = BlogPost::STATUS_DRAFT;
        $copy->is_featured = false;
        $copy->published_at = null;
        $copy->save();

        return redirect()->route('admin.blog.edit', $copy)->with('success', 'Post duplicated as draft.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:publish,draft,schedule,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:blog_posts,id',
            'confirm' => 'nullable|boolean',
        ]);

        if ($validated['action'] === 'delete' && ! $request->boolean('confirm')) {
            return back()->with('error', 'Confirm bulk delete before proceeding.');
        }

        $posts = BlogPost::query()->whereIn('id', $validated['ids'])->get();
        $count = $posts->count();

        match ($validated['action']) {
            'publish' => $posts->each(function (BlogPost $post) {
                $post->update([
                    'status' => BlogPost::STATUS_PUBLISHED,
                    'published_at' => $post->published_at ?? now(),
                    'is_active' => true,
                ]);
            }),
            'draft' => BlogPost::query()->whereIn('id', $validated['ids'])->update([
                'status' => BlogPost::STATUS_DRAFT,
            ]),
            'schedule' => $posts->each(function (BlogPost $post) {
                $post->update([
                    'status' => BlogPost::STATUS_SCHEDULED,
                    'published_at' => $post->published_at?->isFuture() ? $post->published_at : now()->addDay(),
                    'is_active' => true,
                ]);
            }),
            'delete' => $posts->each(fn (BlogPost $post) => $this->deletePost($post)),
        };

        $message = match ($validated['action']) {
            'publish' => "{$count} post(s) published.",
            'draft' => "{$count} post(s) moved to draft.",
            'schedule' => "{$count} post(s) scheduled.",
            'delete' => "{$count} post(s) deleted.",
        };

        return redirect()->route('admin.blog.index')->with('success', $message);
    }

    /** @return array<string, mixed> */
    private function formOptions(?BlogPost $post = null): array
    {
        return [
            'products' => Product::query()->orderBy('name')->pluck('name', 'slug'),
            'projects' => Project::query()->orderBy('project_name')->pluck('project_name', 'id'),
            'services' => Service::query()->orderBy('name')->pluck('name', 'slug'),
            'articles' => BlogPost::query()
                ->when($post, fn ($q) => $q->whereKeyNot($post->id))
                ->orderBy('title')
                ->pluck('title', 'slug'),
            'categories' => collect(BlogContent::categories())->pluck('label', 'slug'),
        ];
    }

    /** @return array<string, mixed> */
    private function prepareValidated(BlogPostRequest $request, ?BlogPost $existing = null): array
    {
        $validated = $request->validated();
        $validated['canonical_url'] = $validated['canonical'] ?? null;
        unset($validated['canonical']);
        $validated['content'] = BlogHtmlSanitizer::clean($validated['content'] ?? null);
        $validated['excerpt'] = trim((string) ($validated['excerpt'] ?? '')) ?: null;
        $validated['author'] = filled($validated['author'] ?? null)
            ? $validated['author']
            : BlogPost::DEFAULT_AUTHOR;
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $this->checkboxBoolean($request, 'is_active');
        $validated['status'] = $request->input('status', BlogPost::STATUS_DRAFT);
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', $existing?->image, 'blog');
        $validated['gallery'] = $this->resolveGalleryField($request, 'gallery_files', 'gallery_urls', $existing?->gallery, 'blog');
        $validated['gallery_meta'] = $this->parseGalleryMeta($request, $validated['gallery'] ?? []);
        $validated['faq'] = $this->parseFaq($request);
        $validated['related_product_slugs'] = $this->parseRelatedSlugs($request, 'related_product_slugs');
        $validated['related_project_ids'] = $this->parseRelatedIds($request, 'related_project_ids');
        $validated['related_service_slugs'] = $this->parseRelatedSlugs($request, 'related_service_slugs');
        $validated['related_article_slugs'] = $this->parseRelatedArticles($request, $existing);
        $validated['robots_index'] = $request->input('robots', 'index') !== 'noindex';
        $validated['reading_time_minutes'] = BlogContent::readingTimeMinutes($validated['content'] ?? null);

        if ($validated['status'] === BlogPost::STATUS_PUBLISHED && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        if ($validated['status'] === BlogPost::STATUS_SCHEDULED && empty($validated['published_at'])) {
            $validated['published_at'] = now()->addDay();
        }

        return $validated;
    }

    private function resolvePostSlug(BlogPostRequest $request, ?BlogPost $existing): string
    {
        $source = filled($request->input('slug'))
            ? $request->input('slug')
            : $request->input('title');

        return $this->resolveUniqueSlug(BlogPost::class, $source, 'slug', $existing, 'slug');
    }

    private function enforceSingleFeatured(BlogPost $post): void
    {
        if (! $post->is_featured) {
            return;
        }

        BlogPost::query()
            ->where('is_featured', true)
            ->whereKeyNot($post->id)
            ->update(['is_featured' => false]);
    }

    private function deletePost(BlogPost $post): void
    {
        $this->deletePostMedia($post);
        $post->delete();
    }

    private function deletePostMedia(BlogPost $post): void
    {
        $this->deleteStoredPath($post->image);

        foreach ($post->gallery ?? [] as $path) {
            if (is_string($path)) {
                $this->deleteStoredPath($path);
            }
        }
    }

    /** @param  array<int, string>|null  $galleryPaths
     * @return array<int, array{alt: ?string, caption: ?string}>|null
     */
    private function parseGalleryMeta(Request $request, ?array $galleryPaths): ?array
    {
        if ($galleryPaths === null || $galleryPaths === []) {
            return null;
        }

        $alts = (array) $request->input('gallery_alt', []);
        $captions = (array) $request->input('gallery_caption', []);
        $meta = [];

        foreach (array_values($galleryPaths) as $index => $path) {
            $meta[] = [
                'alt' => filled($alts[$index] ?? null) ? trim((string) $alts[$index]) : null,
                'caption' => filled($captions[$index] ?? null) ? trim((string) $captions[$index]) : null,
            ];
        }

        return $meta;
    }

    /** @return array<int, array{question: string, answer: string}>|null */
    private function parseFaq(Request $request): ?array
    {
        $questions = (array) $request->input('faq_questions', []);
        $answers = (array) $request->input('faq_answers', []);
        $items = [];

        foreach ($questions as $index => $question) {
            $question = trim((string) $question);
            $answer = trim((string) ($answers[$index] ?? ''));

            if ($question === '' && $answer === '') {
                continue;
            }

            if ($question === '' || $answer === '') {
                continue;
            }

            $items[] = ['question' => $question, 'answer' => $answer];
        }

        return $items !== [] ? $items : null;
    }

    /** @return array<int, string>|null */
    private function parseRelatedSlugs(Request $request, string $field): ?array
    {
        $items = array_values(array_filter((array) $request->input($field, [])));

        return $items !== [] ? $items : null;
    }

    /** @return array<int, int>|null */
    private function parseRelatedIds(Request $request, string $field): ?array
    {
        $items = array_values(array_filter(array_map('intval', (array) $request->input($field, []))));

        return $items !== [] ? $items : null;
    }

    /** @return array<int, string>|null */
    private function parseRelatedArticles(Request $request, ?BlogPost $existing): ?array
    {
        $items = array_values(array_filter((array) $request->input('related_article_slugs', [])));

        if ($existing) {
            $items = array_values(array_filter($items, fn (string $slug) => $slug !== $existing->slug));
        }

        return $items !== [] ? $items : null;
    }
}
