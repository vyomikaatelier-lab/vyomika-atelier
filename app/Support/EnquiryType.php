<?php

namespace App\Support;

final class EnquiryType
{
  public const SHOP_PRODUCT = 'shop_product';
  public const STUDIO_PROJECT = 'studio_project';
  public const RAILINGS_QUOTE = 'railings_quote';
  public const PROFESSIONAL_B2B = 'professional_b2b';
  public const DEALER = 'dealer';
  public const CATALOGUE = 'catalogue';
  public const GENERAL = 'general';
  public const VENDOR_MARKETING = 'vendor_marketing';

  public static function label(?string $type): string
  {
    if (! $type) {
      return 'Unknown';
    }

    return config('lead_qualification.enquiry_types.' . $type, ucfirst(str_replace('_', ' ', $type)));
  }

  public static function fromLeadType(string $type, ?string $intent = null): string
  {
    if ($intent === config('form_protection.vendor_intent')) {
      return self::VENDOR_MARKETING;
    }

    return config('lead_qualification.type_to_enquiry.' . $type, self::GENERAL);
  }

  public static function isSalesQueue(?string $enquiryType): bool
  {
    return $enquiryType !== self::VENDOR_MARKETING;
  }
}
