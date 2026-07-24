<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogAdminController extends Controller
{
    use HandlesAdminUploads;

    public function index(Request $request)
    {
        $query = BlogPost::query()->latest('published_at');

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('title', 'like', "%{$q}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $posts = $query->paginate(15)->withQueryString();

        return view('admin.blog.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.blog.form', $this->formOptions());
    }

    public function store(Request $request)
    {
        $validated = $this->validatePost($request);
        $validated['slug'] = Str::slug($validated['title']);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['status'] = $request->input('status', 'draft');
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', null, 'blog');
        $validated['gallery'] = $this->resolveGalleryField($request, 'gallery_files', 'gallery_urls', null, 'blog');
        $validated['faq'] = $this->parseFaq($request);
        $validated['related_product_slugs'] = $this->parseRelatedSlugs($request, 'related_product_slugs');
        $validated['related_project_slugs'] = $this->parseRelatedSlugs($request, 'related_project_slugs');
        $validated['related_service_slugs'] = $this->parseRelatedSlugs($request, 'related_service_slugs');

        BlogPost::create($validated);

        return redirect()->route('admin.blog.index')->with('success', 'Blog post created.');
    }

    public function edit(BlogPost $post)
    {
        return view('admin.blog.form', ['post' => $post, ...$this->formOptions()]);
    }

    public function update(Request $request, BlogPost $post)
    {
        $validated = $this->validatePost($request);
        $validated['slug'] = Str::slug($validated['title']);
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['status'] = $request->input('status', 'draft');
        $validated['image'] = $this->resolveImageField($request, 'image_file', 'image', $post->image, 'blog');
        $validated['gallery'] = $this->resolveGalleryField($request, 'gallery_files', 'gallery_urls', $post->gallery, 'blog');
        $validated['faq'] = $this->parseFaq($request);
        $validated['related_product_slugs'] = $this->parseRelatedSlugs($request, 'related_product_slugs');
        $validated['related_project_slugs'] = $this->parseRelatedSlugs($request, 'related_project_slugs');
        $validated['related_service_slugs'] = $this->parseRelatedSlugs($request, 'related_service_slugs');

        $post->update($validated);

        return redirect()->route('admin.blog.index')->with('success', 'Blog post updated.');
    }

    public function destroy(BlogPost $post)
    {
        $this->deleteStoredPath($post->image);

        foreach ($post->gallery ?? [] as $path) {
            $this->deleteStoredPath($path);
        }

        $post->delete();

        return redirect()->route('admin.blog.index')->with('success', 'Blog post deleted.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'products' => Product::query()->orderBy('name')->pluck('name', 'slug'),
            'projects' => Project::query()->orderBy('title')->pluck('title', 'slug'),
            'services' => Service::query()->orderBy('name')->pluck('name', 'slug'),
        ];
    }

    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:1000',
            'content' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'author' => 'nullable|string|max:120',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|url|max:500',
            'hero_image_alt' => 'nullable|string|max:255',
            'status' => 'required|in:draft,published',
            'image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'gallery_urls' => 'nullable|string',
            'gallery_files' => 'nullable|array',
            'gallery_files.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
            'faq_questions' => 'nullable|array',
            'faq_questions.*' => 'nullable|string|max:500',
            'faq_answers' => 'nullable|array',
            'faq_answers.*' => 'nullable|string|max:5000',
            'related_product_slugs' => 'nullable|array',
            'related_product_slugs.*' => ['string', Rule::exists('products', 'slug')],
            'related_project_slugs' => 'nullable|array',
            'related_project_slugs.*' => ['string', Rule::exists('projects', 'slug')],
            'related_service_slugs' => 'nullable|array',
            'related_service_slugs.*' => ['string', Rule::exists('services', 'slug')],
        ]);
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

            if ($question !== '' && $answer !== '') {
                $items[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $items !== [] ? $items : null;
    }

    /** @return array<int, string>|null */
    private function parseRelatedSlugs(Request $request, string $field): ?array
    {
        $items = array_values(array_filter((array) $request->input($field, [])));

        return $items !== [] ? $items : null;
    }
}
