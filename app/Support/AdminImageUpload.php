<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class AdminImageUpload
{
    public const MAX_KILOBYTES = 5120;

    /** @return array<int, mixed> */
    public static function rules(bool $nullable = true): array
    {
        $rules = [
            self::inspectRule(),
            File::types(['jpg', 'jpeg', 'png', 'webp'])
                ->image()
                ->max(self::MAX_KILOBYTES),
        ];

        if ($nullable) {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            '*.image' => 'Use a JPEG, PNG, or WebP image (max 5 MB).',
            '*.mimes' => 'Use a JPEG, PNG, or WebP image (max 5 MB).',
            '*.max' => 'Each image must be 5 MB or smaller. Phone photos are often larger — compress or upload one at a time.',
        ];
    }

    public static function acceptAttribute(): string
    {
        return 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp';
    }

    public static function hintText(): string
    {
        return 'JPEG, PNG, or WebP only, max 5 MB each. Upload one image at a time on mobile — phone photos are often too large for multiple uploads at once. iPhone: Settings → Camera → Formats → Most Compatible (saves JPEG instead of HEIC).';
    }

    public static function multipartErrorMessage(): string
    {
        return 'Upload too large for the server limit. Phone photos are often 5–15 MB each — save text changes first, then upload images one at a time (max 5 MB each, JPEG/PNG/WebP). iPhone users: enable Settings → Camera → Formats → Most Compatible.';
    }

    public static function isHeic(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['heic', 'heif'], true)) {
            return true;
        }

        $path = $file->getPathname();
        if ($path === '' || ! is_readable($path)) {
            return false;
        }

        $mime = strtolower((string) $file->getMimeType());

        return str_contains($mime, 'heic') || str_contains($mime, 'heif');
    }

    public static function heicMessage(): string
    {
        return 'iPhone HEIC photos are not supported. On your iPhone go to Settings → Camera → Formats → Most Compatible, or convert the photo to JPEG before uploading.';
    }

    public static function uploadFailedMessage(): string
    {
        return 'The image failed to upload — it may exceed the 5 MB server limit. Compress the photo or upload one image at a time.';
    }

    /** @return \Closure(string, mixed, \Closure): void */
    private static function inspectRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! $value instanceof UploadedFile) {
                return;
            }

            if (! $value->isValid() && $value->getError() !== UPLOAD_ERR_NO_FILE) {
                $fail(self::uploadFailedMessage());

                return;
            }

            if (self::isHeic($value)) {
                $fail(self::heicMessage());
            }
        };
    }
}
