<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Exhibition;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceDesign;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Detects references to a locally stored media path across every first-party
 * location that can hold one.
 *
 * Matching is by normalized, complete path — never by raw substring — so
 * unrelated files sharing a prefix (e.g. "products/a.jpg" vs
 * "products/a.jpg.webp") are not confused, and JSON values are compared
 * element by element after recursive parsing. Known equivalents (leading
 * slash, "storage/" prefix, backslashes, same-host absolute URLs) normalize
 * to the same value. Remote URLs on foreign hosts are never treated as local
 * references.
 */
class MediaReferenceScanner
{
    /**
     * Schema-verified media columns per model:
     * [model class, plain string columns, JSON array columns].
     *
     * products:        image, og_image, gallery (json)
     * categories:      image, og_image
     * projects:        image_path
     * services:        image
     * service_designs: image
     * blog_posts:      image, og_image, gallery (json)
     * exhibitions:     cover_image, gallery (json)
     *
     * Site-setting JSON values (page_heroes, collection_pages,
     * service_page_heroes, hero, homepage, finish_swatches, static_pages,
     * landing_pages, brand, seo, …) are scanned separately — every row.
     */
    private const MODEL_COLUMNS = [
        [Product::class, ['image', 'og_image'], ['gallery']],
        [Category::class, ['image', 'og_image'], []],
        [Project::class, ['image_path'], []],
        [Service::class, ['image'], []],
        [ServiceDesign::class, ['image'], []],
        [BlogPost::class, ['image', 'og_image'], ['gallery']],
        [Exhibition::class, ['cover_image'], ['gallery']],
    ];

    /**
     * Whether any first-party record still references the given local path.
     *
     * @throws InvalidArgumentException when the path cannot be interpreted as
     *                                  a local media path — callers must fail
     *                                  closed and refuse deletion.
     */
    public function isReferenced(string $path): bool
    {
        return $this->referenceCount($path) > 0;
    }

    /**
     * Number of records referencing the given local path.
     *
     * @throws InvalidArgumentException see isReferenced()
     */
    public function referenceCount(string $path): int
    {
        $target = self::normalizeLocalPath($path);

        if ($target === null) {
            throw new InvalidArgumentException('Cannot determine media references for path: '.$path);
        }

        $needle = basename($target);

        if ($needle === '' || $needle === '.' || $needle === '..') {
            throw new InvalidArgumentException('Cannot determine media references for path: '.$path);
        }

        $count = 0;

        foreach (self::MODEL_COLUMNS as [$model, $stringColumns, $jsonColumns]) {
            $count += $this->countModelReferences($model::query(), $stringColumns, $jsonColumns, $target, $needle);
        }

        $count += $this->countSiteSettingReferences($target, $needle);

        return $count;
    }

    /**
     * Normalize a candidate local media path for comparison and for disk
     * operations. Returns null for anything that is not a plain local path:
     * blanks, remote URLs, traversal attempts and null bytes.
     */
    public static function normalizeLocalPath(?string $value): ?string
    {
        if (! is_string($value) || str_contains($value, "\0")) {
            return null;
        }

        $value = trim(str_replace('\\', '/', $value));

        if ($value === '' || self::isRemoteUrl($value)) {
            return null;
        }

        $value = ltrim($value, '/');

        if (str_starts_with($value, 'storage/')) {
            $value = ltrim(substr($value, strlen('storage/')), '/');
        }

        if ($value === '' || preg_match('#(^|/)\.\.(/|$)#', $value)) {
            return null;
        }

        return $value;
    }

    public static function isRemoteUrl(string $value): bool
    {
        $value = ltrim(str_replace('\\', '/', $value));

        return str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, '//');
    }

    /**
     * Normalize a stored reference value for comparison. Same-host absolute
     * URLs count as references to their local path; foreign-host URLs never
     * match a local file.
     */
    private static function normalizeReferenceValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(str_replace('\\', '/', $value));

        if ($value === '') {
            return null;
        }

        if (self::isRemoteUrl($value)) {
            $host = parse_url($value, PHP_URL_HOST);
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

            if (! is_string($host) || $host === '' || ! is_string($appHost) || strcasecmp($host, $appHost) !== 0) {
                return null;
            }

            $value = (string) (parse_url($value, PHP_URL_PATH) ?? '');

            if ($value === '') {
                return null;
            }
        }

        return self::normalizeLocalPath($value);
    }

    /** Recursively check parsed JSON / array data for a normalized exact match. */
    private static function containsReference(mixed $value, string $target): bool
    {
        if (is_string($value)) {
            return self::normalizeReferenceValue($value) === $target;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::containsReference($item, $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $stringColumns
     * @param  list<string>  $jsonColumns
     */
    private function countModelReferences(Builder $query, array $stringColumns, array $jsonColumns, string $target, string $needle): int
    {
        $columns = array_merge($stringColumns, $jsonColumns);

        // The filename LIKE clause is only a coarse prefilter to keep the
        // candidate set small; every candidate is verified below by exact
        // normalized comparison. LIKE wildcards in the filename can only
        // widen the candidate set, never narrow it, so they are safe.
        $like = '%'.$needle.'%';

        $rows = $query
            ->where(function (Builder $builder) use ($columns, $like) {
                foreach ($columns as $column) {
                    $builder->orWhere($column, 'like', $like);
                }
            })
            ->get($columns);

        $count = 0;

        foreach ($rows as $row) {
            foreach ($stringColumns as $column) {
                if (self::normalizeReferenceValue($row->{$column}) === $target) {
                    $count++;

                    continue 2;
                }
            }

            foreach ($jsonColumns as $column) {
                if (self::containsReference($row->{$column}, $target)) {
                    $count++;

                    continue 2;
                }
            }
        }

        return $count;
    }

    private function countSiteSettingReferences(string $target, string $needle): int
    {
        return SiteSetting::query()
            ->where('value', 'like', '%'.$needle.'%')
            ->get(['id', 'key', 'value'])
            ->filter(fn (SiteSetting $setting) => self::containsReference($setting->value, $target))
            ->count();
    }
}
