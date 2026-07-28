<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\MediaFile;
use App\Support\AdminImageUpload;
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

    /** Unchecked HTML checkboxes are omitted from the request; never default them to true. */
    protected function checkboxBoolean(Request $request, string $field): bool
    {
        return $request->has($field) && $request->boolean($field);
    }

    protected function multipartPayloadFailed(Request $request, ?string $probeField = null): bool
    {
        if (! $request->isMethod('POST') && ! $request->isMethod('PUT')) {
            return false;
        }

        $contentLength = (int) ($request->header('Content-Length') ?: ($_SERVER['CONTENT_LENGTH'] ?? 0));
        if ($contentLength <= 0) {
            return false;
        }

        $payload = $request->except(['_token', '_method']);
        $probes = $probeField ? [$probeField] : ['_page_save', '_landing_save'];

        foreach ($probes as $field) {
            if ($request->has($field)) {
                return false;
            }
        }

        $postMaxBytes = $this->iniSizeBytes(ini_get('post_max_size'));
        if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            return true;
        }

        return $payload === [];
    }

    protected function multipartPayloadErrorMessage(): string
    {
        return AdminImageUpload::multipartErrorMessage();
    }

    /** @return array<int, mixed> */
    protected function adminImageUploadRules(bool $nullable = true): array
    {
        return AdminImageUpload::rules($nullable);
    }

    /** @return array<string, string> */
    protected function adminImageUploadMessages(): array
    {
        return AdminImageUpload::messages();
    }

    protected function iniSizeBytes(string|false $value): int
    {
        if ($value === false || $value === '') {
            return 0;
        }

        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** @return array<int, string>|null */
    protected function resolveGalleryField(Request $request, string $filesField, string $urlsField, ?array $current, string $directory): ?array
    {
        if ($request->boolean('gallery_managed')) {
            $items = array_values(array_filter((array) $request->input('gallery_existing', []), fn ($item) => filled($item)));
            $hasGalleryUpload = $this->hasGalleryFileUpload($request, $filesField);

            if (
                $items === []
                && is_array($current)
                && $current !== []
                && $hasGalleryUpload
            ) {
                $items = array_values($current);
            } elseif (
                ! $request->has('gallery_existing')
                && is_array($current)
                && $current !== []
                && ! $hasGalleryUpload
                && ! $request->filled($urlsField)
                && ! $request->has('remove_gallery')
            ) {
                $items = $current;
            }
        } elseif ($request->has($urlsField)) {
            $items = $this->parseMultilineUrls($request->input($urlsField)) ?? [];
        } else {
            $items = $current ?? [];
        }

        $replacements = $request->file('gallery_replace', []);
        if (is_array($replacements)) {
            foreach ($replacements as $index => $file) {
                if (! $this->isUsableUpload($file)) {
                    continue;
                }

                $path = $this->storeGalleryUpload($file, $directory);
                $slot = (int) $index;

                if (isset($items[$slot])) {
                    $this->deleteStoredPath($items[$slot]);
                    $items[$slot] = $path;
                } else {
                    $items[] = $path;
                }
            }
        }

        if ($request->hasFile($filesField)) {
            $files = $request->file($filesField);
            if (! is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if (! $this->isUsableUpload($file)) {
                    continue;
                }

                $items[] = $this->storeGalleryUpload($file, $directory);
            }
        }

        if ($request->filled($urlsField)) {
            $urlItems = $this->parseMultilineUrls($request->input($urlsField)) ?? [];
            if ($urlItems !== []) {
                $items = array_merge($items, $urlItems);
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

    protected function hasGalleryFileUpload(Request $request, string $filesField): bool
    {
        if ($this->hasUsableUploads($request->file($filesField))) {
            return true;
        }

        return $this->hasUsableUploads($request->file('gallery_replace', []));
    }

    protected function hasUsableUploads(mixed $files): bool
    {
        if ($files instanceof \Illuminate\Http\UploadedFile) {
            return $this->isUsableUpload($files);
        }

        if (! is_array($files)) {
            return false;
        }

        foreach ($files as $file) {
            if ($this->isUsableUpload($file)) {
                return true;
            }
        }

        return false;
    }

    protected function isUsableUpload(mixed $file): bool
    {
        return $file instanceof \Illuminate\Http\UploadedFile
            && ! AdminImageUpload::isEmptyUpload($file)
            && $file->isValid();
    }

    protected function storeGalleryUpload(\Illuminate\Http\UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');

        MediaFile::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'is_private' => false,
        ]);

        return $path;
    }

    /** @param array<int, string>|null $gallery */
    protected function galleryLinesForForm(?array $gallery): string
    {
        return is_array($gallery) ? implode("\n", $gallery) : '';
    }
}
