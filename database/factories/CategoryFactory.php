<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function slug(string $slug): static
    {
        return $this->state(fn () => ['slug' => $slug]);
    }

    /**
     * Canonical categories may already exist from migrations (archive/sync).
     * Reuse by slug instead of inserting duplicates.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $attributes = is_array($attributes) ? $attributes : [];

        if (! empty($attributes['slug'])) {
            $existing = Category::query()->where('slug', $attributes['slug'])->first();
            if ($existing) {
                $made = $this->make($attributes, $parent);
                $existing->fill(
                    collect($made->getAttributes())
                        ->except(['id', 'created_at'])
                        ->all()
                );
                $existing->save();

                return $existing->refresh();
            }
        }

        return parent::create($attributes, $parent);
    }
}
