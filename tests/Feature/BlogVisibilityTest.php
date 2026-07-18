<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_blog_post_is_not_publicly_visible(): void
    {
        // A published post is required so BlogContent switches from the
        // static config catalog to database-backed posts.
        BlogPost::create([
            'title' => 'Published Post',
            'slug' => 'published-post',
            'excerpt' => 'Excerpt',
            'content' => '<p>Content</p>',
            'author' => 'Vyomika Atelier LLP',
            'is_active' => true,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $draft = BlogPost::create([
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'excerpt' => 'Excerpt',
            'content' => '<p>Content</p>',
            'author' => 'Vyomika Atelier LLP',
            'is_active' => true,
            'status' => 'draft',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get(route('blog.show', $draft->slug));

        $response->assertNotFound();
    }

    public function test_published_blog_post_is_publicly_visible(): void
    {
        $published = BlogPost::create([
            'title' => 'Published Post',
            'slug' => 'published-post-2',
            'excerpt' => 'Excerpt',
            'content' => '<p>Content</p>',
            'author' => 'Vyomika Atelier LLP',
            'is_active' => true,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get(route('blog.show', $published->slug));

        $response->assertOk();
    }
}
