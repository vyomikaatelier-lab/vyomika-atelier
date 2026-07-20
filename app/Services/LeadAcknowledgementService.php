<?php

namespace App\Services;

use App\Support\EnquiryType;
use App\Support\LeadProtectionStatus;

class LeadAcknowledgementService
{
  public function message(string $protectionStatus, ?string $enquiryType = null, bool $isDuplicate = false): string
  {
    if ($isDuplicate || $protectionStatus === LeadProtectionStatus::DUPLICATE) {
      return config('lead_qualification.acknowledgements.duplicate');
    }

    if ($protectionStatus === LeadProtectionStatus::MARKETING_VENDOR || $enquiryType === EnquiryType::VENDOR_MARKETING) {
      return config('lead_qualification.acknowledgements.vendor');
    }

    if ($enquiryType === EnquiryType::CATALOGUE) {
      return config('lead_qualification.acknowledgements.catalogue');
    }

    if (in_array($protectionStatus, [LeadProtectionStatus::NEEDS_VERIFICATION, LeadProtectionStatus::SPAM_SUSPECTED], true)) {
      return config('lead_qualification.acknowledgements.incomplete');
    }

    return config('lead_qualification.acknowledgements.genuine');
  }
}
