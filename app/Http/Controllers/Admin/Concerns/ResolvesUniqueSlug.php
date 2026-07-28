<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin forms derive the public URL slug from a name/title field, so the value
 * that actually reaches the database is never covered by a `Rule::unique` on
 * the submitted input. Without this guard a duplicate name aborts the save with
 * a raw integrity-constraint error instead of a form validation message.
 */
trait ResolvesUniqueSlug
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function resolveUniqueSlug(
        string $modelClass,
        ?string $source,
        string $errorField,
        ?Model $existing = null,
        string $column = 'slug'
    ): string {
        $slug = Str::slug((string) $source);

        if ($slug === '') {
            throw ValidationException::withMessages([
                $errorField => 'Enter a value that produces a valid URL slug (letters or numbers).',
            ]);
        }

        $conflict = $modelClass::query()
            ->where($column, $slug)
            ->when($existing?->getKey() !== null, fn ($query) => $query->whereKeyNot($existing->getKey()))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                $errorField => 'Another entry already uses the URL "'.$slug.'". Choose a different name or set a unique slug.',
            ]);
        }

        return $slug;
    }
}
