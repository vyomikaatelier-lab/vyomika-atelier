<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\MediaFile;
use App\Support\ResponsiveHero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait HandlesAdminUploads
{
    protected function storeUpload(Request $request, string $field, string $directory, bool $private = false): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        $path = $file->store($directory, $private ? 'local' : 'public');

        MediaFile::create([
            'disk' => $private ? 'local' : 'public',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'is_private' => $private,
        ]);

        return $path;
    }

    protected function resolveImageField(
        Request $request,
        string $fileField,
        string $urlField,
        ?string $current,
        string $directory,
        bool $deletePrevious = true
    ): ?string {
        $uploaded = $this->storeUpload($request, $fileField, $directory);
        if ($uploaded) {
            if ($deletePrevious) {
                $this->deleteStoredPath($current);
            }

            return $uploaded;
        }

        if ($request->boolean(str_replace('_file', '_remove', $fileField))
            || $request->boolean($urlField.'_remove')) {
            if ($deletePrevious) {
                $this->deleteStoredPath($current);
            }

            return null;
        }

        $url = $request->input($urlField);

        return filled($url) ? $url : $current;
    }

    /**
     * @param  array<string, mixed>  $storedHero
     * @return array<string, string|null>
     */
    public function persistResponsiveHeroFlatFields(
        Request $request,
        string $prefix,
        array $storedHero,
        string $directory,
        bool $deletePrevious = true
    ): array {
        $persisted = [];

        foreach (ResponsiveHero::storageKeys() as $storageKey) {
            $flatField = ResponsiveHero::flatFieldForStorageKey($prefix, $storageKey);
            $persisted[$storageKey] = $this->resolveImageField(
                $request,
                $flatField.'_file',
                $flatField,
                $storedHero[$storageKey] ?? null,
                $directory,
                $deletePrevious
            );
        }

        return array_filter($persisted, fn ($value) => filled($value));
    }

    protected function deleteStoredPath(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http')) {
            return;
        }

        $media = MediaFile::query()->where('path', $path)->first();

        if ($media) {
            if ($media->referenceCount() > 0) {
                return;
            }

            Storage::disk($media->disk)->delete($path);
            $media->delete();

            return;
        }

        Storage::disk('public')->delete($path);
        Storage::disk('local')->delete($path);
    }

    /** @return array<int, string>|null */
    protected function parseMultilineUrls(?string $raw): ?array
    {
        if (! filled($raw)) {
            return null;
        }

        $urls = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));

        return $urls ?: null;
    }

    /** @return array<int, string>|null */
    protected function resolveGalleryField(Request $request, string $filesField, string $urlsField, ?array $current, string $directory): ?array
    {
        $items = $this->parseMultilineUrls($request->input($urlsField)) ?? [];

        if ($request->hasFile($filesField)) {
            $files = $request->file($filesField);
            if (! is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }

                $path = $file->store($directory, 'public');
                MediaFile::create([
                    'disk' => 'public',
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize() ?: 0,
                    'is_private' => false,
                ]);
                $items[] = $path;
            }
        }

        $remove = array_filter((array) $request->input('remove_gallery', []));
        if ($remove !== []) {
            $items = array_values(array_filter(
                $items,
                fn (string $item) => ! in_array($item, $remove, true)
            ));

            foreach ($remove as $path) {
                if (is_string($path)) {
                    $this->deleteStoredPath($path);
                }
            }
        }

        return $items !== [] ? array_values(array_unique($items)) : null;
    }

    /** @param array<int, string>|null $gallery */
    protected function galleryLinesForForm(?array $gallery): string
    {
        return is_array($gallery) ? implode("\n", $gallery) : '';
    }
}
