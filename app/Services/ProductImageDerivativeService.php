<?php

namespace App\Services;

use App\Support\MediaUrl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductImageDerivativeService
{
    /** @var list<int> */
    public const WIDTHS = [400, 800, 1200];

    public function derivativeRelativePath(string $originalPath, int $width): string
    {
        $normalized = str_replace('\\', '/', trim($originalPath));
        $basename = pathinfo($normalized, PATHINFO_FILENAME);
        $directory = pathinfo($normalized, PATHINFO_DIRNAME);
        $derivativeDir = $directory === '.' || $directory === ''
            ? 'derivatives'
            : $directory.'/derivatives';

        return $derivativeDir.'/'.$basename.'-'.$width.'w.webp';
    }

    /**
     * @return list<string> Relative storage paths of generated derivatives.
     */
    public function generateForPath(string $relativePath): array
    {
        if ($relativePath === '' || str_starts_with($relativePath, 'http')) {
            return [];
        }

        if (! Storage::disk('public')->exists($relativePath)) {
            return [];
        }

        $absolute = Storage::disk('public')->path($relativePath);
        $imageInfo = @getimagesize($absolute);
        if ($imageInfo === false) {
            return [];
        }

        [$sourceWidth, $sourceHeight, $type] = $imageInfo;
        $source = $this->createImageResource($absolute, $type);
        if ($source === null) {
            return [];
        }

        $generated = [];

        try {
            foreach (self::WIDTHS as $targetWidth) {
                if ($sourceWidth <= 0 || $sourceHeight <= 0) {
                    continue;
                }

                $width = min($targetWidth, $sourceWidth);
                $height = (int) round($sourceHeight * ($width / $sourceWidth));
                $derivativePath = $this->derivativeRelativePath($relativePath, $width);

                if ($this->writeWebpDerivative($source, $sourceWidth, $sourceHeight, $width, $height, $derivativePath)) {
                    $generated[] = $derivativePath;
                }
            }
        } finally {
            imagedestroy($source);
        }

        return $generated;
    }

    /** @return array{src: string, srcset: string, sizes: string, width: int, height: int, webp_srcset: string}|null */
    public function responsiveSources(?string $path, string $context = 'card'): ?array
    {
        $src = MediaUrl::resolve($path);
        if ($src === null || $path === null || str_starts_with($path, 'http')) {
            return $src ? [
                'src' => $src,
                'srcset' => $src,
                'sizes' => $this->sizesAttribute($context),
                'width' => $context === 'pdp' ? 1200 : 400,
                'height' => $context === 'pdp' ? 1600 : 500,
                'webp_srcset' => '',
            ] : null;
        }

        $absolute = Storage::disk('public')->path($path);
        $dimensions = @getimagesize($absolute);
        $originalWidth = $dimensions[0] ?? ($context === 'pdp' ? 1200 : 400);
        $originalHeight = $dimensions[1] ?? ($context === 'pdp' ? 1600 : 500);

        $jpegParts = [];
        $webpParts = [];

        foreach (self::WIDTHS as $width) {
            if ($width > $originalWidth) {
                continue;
            }

            $derivative = $this->derivativeRelativePath($path, $width);
            if (Storage::disk('public')->exists($derivative)) {
                $url = MediaUrl::resolve($derivative);
                if ($url) {
                    $webpParts[] = $url.' '.$width.'w';
                }
            }

            $jpegParts[] = $src.' '.$width.'w';
        }

        if ($jpegParts === []) {
            $jpegParts[] = $src.' '.$originalWidth.'w';
        }

        return [
            'src' => $src,
            'srcset' => implode(', ', $jpegParts),
            'sizes' => $this->sizesAttribute($context),
            'width' => $context === 'pdp' ? min(1200, $originalWidth) : min(400, $originalWidth),
            'height' => $context === 'pdp'
                ? (int) round(min(1200, $originalWidth) * ($originalHeight / max(1, $originalWidth)))
                : (int) round(min(400, $originalWidth) * ($originalHeight / max(1, $originalWidth))),
            'webp_srcset' => implode(', ', $webpParts),
        ];
    }

    public function deleteDerivatives(?string $relativePath): void
    {
        if (! filled($relativePath) || str_starts_with($relativePath, 'http')) {
            return;
        }

        foreach (self::WIDTHS as $width) {
            $derivative = $this->derivativeRelativePath($relativePath, $width);
            if (Storage::disk('public')->exists($derivative)) {
                Storage::disk('public')->delete($derivative);
            }
        }
    }

    private function sizesAttribute(string $context): string
    {
        return match ($context) {
            'pdp' => '(min-width: 1024px) 50vw, 100vw',
            default => '(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw',
        };
    }

    /** @return resource|null */
    private function createImageResource(string $absolute, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolute) ?: null,
            IMAGETYPE_PNG => @imagecreatefrompng($absolute) ?: null,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($absolute) ?: null) : null,
            default => null,
        };
    }

    /**
     * @param  resource  $source
     */
    private function writeWebpDerivative(
        $source,
        int $sourceWidth,
        int $sourceHeight,
        int $width,
        int $height,
        string $derivativePath
    ): bool {
        if (! function_exists('imagewebp')) {
            return false;
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            return false;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $resampled = imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight
        );

        if (! $resampled) {
            imagedestroy($canvas);

            return false;
        }

        $directory = dirname(Storage::disk('public')->path($derivativePath));
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $written = imagewebp($canvas, Storage::disk('public')->path($derivativePath), 82);
        imagedestroy($canvas);

        if (! $written) {
            Log::warning('product_image_derivative_failed', ['path' => $derivativePath]);

            return false;
        }

        return true;
    }
}
