<?php

namespace App\Support;

class ProductImageSizes
{
    /** Gallery grids and shop product cards (1:1 square cell, object-fit: contain). */
    public const GALLERY_WIDTH = 800;

    public const GALLERY_HEIGHT = 800;

    public const GALLERY_RATIO = '1:1';

    /** PDP hero image (3:4 portrait frame, object-fit: contain). */
    public const PDP_WIDTH = 1200;

    public const PDP_HEIGHT = 1600;

    public const PDP_RATIO = '3:4';

    public static function galleryDimensionsLabel(): string
    {
        return sprintf('%d×%d px', self::GALLERY_WIDTH, self::GALLERY_HEIGHT);
    }

    public static function pdpDimensionsLabel(): string
    {
        return sprintf('%d×%d px', self::PDP_WIDTH, self::PDP_HEIGHT);
    }

    public static function uploadHintText(): string
    {
        return sprintf(
            '%s %s',
            self::adminDimensionSummary(),
            AdminImageUpload::hintText()
        );
    }

    public static function adminDimensionSummary(): string
    {
        return sprintf(
            'Recommended: %s (%s) for shop/studio gallery cards; %s (%s) for the product detail hero.',
            self::galleryDimensionsLabel(),
            self::GALLERY_RATIO,
            self::pdpDimensionsLabel(),
            self::PDP_RATIO
        );
    }

    public static function galleryAdminHint(): string
    {
        return sprintf(
            'Gallery & shop cards display in a %s square without cropping — upload %s (%s). Portrait or landscape originals are fine; the site letterboxes them on cream.',
            self::GALLERY_RATIO,
            self::galleryDimensionsLabel(),
            self::GALLERY_RATIO
        );
    }

    public static function pdpAdminHint(): string
    {
        return sprintf(
            'Product page hero uses a %s frame without cropping — %s (%s) gives the sharpest result on desktop.',
            self::PDP_RATIO,
            self::pdpDimensionsLabel(),
            self::PDP_RATIO
        );
    }
}
