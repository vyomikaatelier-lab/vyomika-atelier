<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        if (Schema::hasTable('blog_posts')
            && Schema::hasColumn('blog_posts', 'related_project_slugs')
            && ! Schema::hasColumn('blog_posts', 'related_project_ids')) {
            Schema::table('blog_posts', function (Blueprint $table) {
                $table->json('related_project_ids')->nullable()->after('related_project_slugs');
            });
        }

        $slugToOldId = DB::table('projects')
            ->whereNotNull('slug')
            ->pluck('id', 'slug');

        if (Schema::hasColumn('blog_posts', 'related_project_ids')) {
            foreach (DB::table('blog_posts')->orderBy('id')->get() as $post) {
                $slugs = json_decode($post->related_project_slugs ?? '[]', true) ?: [];
                $ids = collect($slugs)
                    ->map(fn ($slug) => $slugToOldId[$slug] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                if ($ids !== []) {
                    DB::table('blog_posts')->where('id', $post->id)->update([
                        'related_project_ids' => json_encode($ids),
                    ]);
                }
            }
        }

        Schema::create('projects_gallery', function (Blueprint $table) {
            $table->id();
            $table->string('project_name');
            $table->string('work_type')->nullable();
            $table->string('city')->nullable();
            $table->string('client')->nullable();
            $table->string('size')->nullable();
            $table->string('price')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $oldIdToName = DB::table('projects')->pluck('title', 'id');

        foreach (DB::table('projects')->orderBy('display_order')->orderBy('id')->get() as $row) {
            $description = collect([
                $row->summary,
                filled($row->content ?? null) ? strip_tags((string) $row->content) : null,
                $row->design_details,
            ])->filter(fn ($value) => filled($value))->implode("\n\n");

            $base = [
                'project_name' => $row->title,
                'work_type' => $row->category,
                'city' => $row->location,
                'client' => $row->client,
                'size' => null,
                'price' => null,
                'description' => $description !== '' ? $description : null,
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];

            $images = [];
            if (filled($row->image)) {
                $images[] = $row->image;
            }

            $gallery = json_decode($row->gallery ?? '[]', true) ?: [];
            foreach ($gallery as $path) {
                if (filled($path) && ! in_array($path, $images, true)) {
                    $images[] = $path;
                }
            }

            if ($images === []) {
                DB::table('projects_gallery')->insert(array_merge($base, [
                    'image_path' => null,
                    'image_alt' => $row->title,
                    'display_order' => (int) ($row->display_order ?? 0),
                ]));

                continue;
            }

            foreach ($images as $index => $imagePath) {
                DB::table('projects_gallery')->insert(array_merge($base, [
                    'image_path' => $imagePath,
                    'image_alt' => $row->title,
                    'display_order' => ((int) ($row->display_order ?? 0) * 100) + $index,
                ]));
            }
        }

        if (Schema::hasColumn('blog_posts', 'related_project_ids')) {
            $nameToNewId = DB::table('projects_gallery')
                ->orderBy('display_order')
                ->orderBy('id')
                ->get(['id', 'project_name'])
                ->groupBy('project_name')
                ->map(fn ($group) => $group->first()->id);

            foreach (DB::table('blog_posts')->orderBy('id')->get() as $post) {
                $oldIds = json_decode($post->related_project_ids ?? '[]', true) ?: [];
                if ($oldIds === []) {
                    continue;
                }

                $newIds = collect($oldIds)
                    ->map(function ($oldId) use ($oldIdToName, $nameToNewId) {
                        $name = $oldIdToName[$oldId] ?? null;

                        return $name ? ($nameToNewId[$name] ?? null) : null;
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                DB::table('blog_posts')->where('id', $post->id)->update([
                    'related_project_ids' => $newIds !== [] ? json_encode($newIds) : null,
                ]);
            }
        }

        Schema::drop('projects');
        Schema::rename('projects_gallery', 'projects');
    }

    public function down(): void
    {
        // Intentionally omitted — legacy project detail schema is not restored.
    }
};
